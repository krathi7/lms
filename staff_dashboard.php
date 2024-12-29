<?php
session_start();

// Check if the user is logged in as staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.html');
    exit();
}

// Database connection
$host = 'localhost';
$db = 'dbms';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Add a new book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $available_copies = $_POST['available_copies'];

    $stmt = $conn->prepare("INSERT INTO books (title, author, available_copies) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $title, $author, $available_copies);
    $stmt->execute();
    $stmt->close();
}

// Edit an existing book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book'])) {
    $book_id = $_POST['book_id'];
    $title = $_POST['edit_title'];
    $author = $_POST['edit_author'];
    $available_copies = $_POST['edit_available_copies'];

    $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, available_copies = ? WHERE book_id = ?");
    $stmt->bind_param('ssii', $title, $author, $available_copies, $book_id);
    $stmt->execute();
    $stmt->close();
}

// Delete a book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    $book_id = $_POST['book_id'];

    // Ensure the book has no active transactions
    $transaction_check = $conn->prepare("SELECT * FROM transactions WHERE book_id = ? AND return_date IS NULL");
    $transaction_check->bind_param('i', $book_id);
    $transaction_check->execute();
    $result = $transaction_check->get_result();

    if ($result->num_rows > 0) {
        echo "<p class='error'>Cannot delete this book as it is currently issued to a student.</p>";
    } else {
        $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        echo "<p class='success'>Book deleted successfully.</p>";
    }
    $transaction_check->close();
    $stmt->close();
}

// Issue a book (transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $usn = $_POST['usn']; // Now using USN
    $book_title = $_POST['book_title'];
    $issue_date = date('Y-m-d');
    $return_date = NULL;

    // Check if the book is available
    $stmt = $conn->prepare("SELECT book_id, available_copies FROM books WHERE title = ?");
    $stmt->bind_param('s', $book_title);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
        if ($book['available_copies'] > 0) {
            // Reduce the book copies and insert the transaction
            $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?");
            $stmt->bind_param('i', $book['book_id']);
            $stmt->execute();

            // Get student ID from USN
            $stmt = $conn->prepare("SELECT student_id FROM stud WHERE usn = ?");
            $stmt->bind_param('s', $usn);
            $stmt->execute();
            $student_result = $stmt->get_result();
            if ($student_result->num_rows > 0) {
                $student = $student_result->fetch_assoc();
                $student_id = $student['student_id'];

                $stmt = $conn->prepare("INSERT INTO transactions (student_id, book_id, issue_date, return_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiss', $student_id, $book['book_id'], $issue_date, $return_date);
                $stmt->execute();

                echo "<p class='success'>Book issued successfully!</p>";
            } else {
                echo "<p class='error'>Student not found with the provided USN.</p>";
            }
        } else {
            echo "<p class='error'>The book is currently unavailable!</p>";
        }
    } else {
        echo "<p class='error'>The book title does not exist!</p>";
    }
}

// Mark book as returned
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $transaction_id = $_POST['transaction_id'];
    $return_date = date('Y-m-d');

    // Update the transaction return date
    $stmt = $conn->prepare("UPDATE transactions SET return_date = ? WHERE transaction_id = ?");
    $stmt->bind_param('si', $return_date, $transaction_id);
    $stmt->execute();

    // Restore the available copies
    $stmt = $conn->prepare("SELECT book_id FROM transactions WHERE transaction_id = ?");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();

    $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
    $stmt->bind_param('i', $transaction['book_id']);
    $stmt->execute();

    echo "<p class='success'>Book returned successfully!</p>";
}

// Get all books
$books_result = $conn->query("SELECT * FROM books");

// Get all transactions (issued books)
$transactions_result = $conn->query("
    SELECT t.transaction_id, s.student_id, s.usn, b.title, t.issue_date, t.return_date
    FROM transactions t
    JOIN stud s ON t.student_id = s.student_id
    JOIN books b ON t.book_id = b.book_id
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Library Management</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .button-return {
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }

        .return-not-returned {
            background-color: red;
            color: white;
        }

        .return-returned {
            background-color: green;
            color: white;
        }

        /* Scrollable Container */
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            overflow-y: auto;
            height: 80vh; /* Adjust height to your preference */
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        /* For better scroll on the table itself */
        .scrollable-table-container {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Welcome, Staff: <?php echo $_SESSION['username']; ?></h2>

        <!-- Add Book Form -->
        <h3>Add New Book</h3>
        <form action="staff_dashboard.php" method="POST">
            <div class="input-group">
                <label for="title">Book Title</label>
                <input type="text" name="title" required>
            </div>
            <div class="input-group">
                <label for="author">Author</label>
                <input type="text" name="author" required>
            </div>
            <div class="input-group">
                <label for="available_copies">Number of Copies</label>
                <input type="number" name="available_copies" required>
            </div>
            <button type="submit" name="add_book">Add Book</button>
        </form>

        <!-- List of Books -->
        <h3>Books Available</h3>
        <div class="scrollable-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Available Copies</th>
                        <th>Edit</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($book = $books_result->fetch_assoc()) : ?>
                        <tr>
                            <form action="staff_dashboard.php" method="POST">
                                <td><input type="text" name="edit_title" value="<?php echo $book['title']; ?>" required></td>
                                <td><input type="text" name="edit_author" value="<?php echo $book['author']; ?>" required></td>
                                <td><input type="number" name="edit_available_copies" value="<?php echo $book['available_copies']; ?>" required></td>
                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                <td><button type="submit" name="edit_book">Edit</button></td>
                            </form>
                            <form action="staff_dashboard.php" method="POST">
                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                <td><button type="submit" name="delete_book">Delete</button></td>
                            </form>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Issue a Book -->
        <h3>Issue a Book</h3>
        <form action="staff_dashboard.php" method="POST">
            <div class="input-group">
                <label for="usn">Student USN</label>
                <input type="text" name="usn" required>
            </div>
            <div class="input-group">
                <label for="book_title">Book Title</label>
                <select name="book_title" required>
                    <option value="">Select a Book</option>
                    <?php
                    // Display available books in the dropdown
                    $books_result = $conn->query("SELECT title FROM books WHERE available_copies > 0");
                    while ($book = $books_result->fetch_assoc()) {
                        echo "<option value='" . $book['title'] . "'>" . $book['title'] . "</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" name="issue_book">Issue Book</button>
        </form>

        <!-- Return Book -->
        <h3>Return a Book</h3>
        <form action="staff_dashboard.php" method="POST">
            <div class="input-group">
                <label for="transaction_id">Transaction ID</label>
                <input type="number" name="transaction_id" required>
            </div>
            <button type="submit" name="return_book">Return Book</button>
        </form>

        <!-- Transaction History -->
        <h3>Transaction History</h3>
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Student USN</th>
                    <th>Book Title</th>
                    <th>Issue Date</th>
                    <th>Return Date</th>
                    <th>Return Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($transaction = $transactions_result->fetch_assoc()) : ?>
                    <tr>
                        <td><?php echo $transaction['transaction_id']; ?></td>
                        <td><?php echo $transaction['usn']; ?></td>
                        <td><?php echo $transaction['title']; ?></td>
                        <td><?php echo $transaction['issue_date']; ?></td>
                        <td><?php echo $transaction['return_date'] ?: 'Not Returned'; ?></td>
                        <td>
                            <?php if ($transaction['return_date']) : ?>
                                <button class="button-return return-returned" disabled>Returned</button>
                            <?php else : ?>
                                <button class="button-return return-not-returned" name="return_book" value="<?php echo $transaction['transaction_id']; ?>">Return</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
$conn->close();
?>
