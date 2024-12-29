<?php
include 'db_connection.php';

if (isset($_POST['issue_book'])) {
    $student_usn = $_POST['student_usn'];
    $book_title = $_POST['book_title'];

    // Check if the book is available
    $book_query = $conn->prepare("SELECT available_copies FROM books WHERE title = ?");
    $book_query->bind_param("s", $book_title);
    $book_query->execute();
    $result = $book_query->get_result();

    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();

        if ($book['available_copies'] > 0) {
            // Decrease the available copies
            $update_query = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE title = ?");
            $update_query->bind_param("s", $book_title);
            $update_query->execute();

            // Add a transaction entry
            $insert_query = $conn->prepare("INSERT INTO transactions (student_usn, book_title, issue_date, return_date) VALUES (?, ?, NOW(), NULL)");
            $insert_query->bind_param("ss", $student_usn, $book_title);
            $insert_query->execute();

            echo "<p class='success'>Book issued successfully!</p>";
        } else {
            echo "<p class='error'>The book is currently not available!</p>";
        }
    } else {
        echo "<p class='error'>The book title was not found in the database!</p>";
    }
}
?>
