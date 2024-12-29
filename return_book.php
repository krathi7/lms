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

// Return book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $transaction_id = $_POST['transaction_id'];
    $return_date = date('Y-m-d');

    // Update transaction with return date
    $stmt = $conn->prepare("UPDATE transactions SET return_date = ? WHERE transaction_id = ?");
    $stmt->bind_param('si', $return_date, $transaction_id);
    $stmt->execute();
    $stmt->close();

    // Update available copies of the book
    $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = (SELECT book_id FROM transactions WHERE transaction_id = ?)");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $stmt->close();

    echo "Book returned successfully!";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book</title>
</head>
<body>
    <h2>Return Book</h2>
    <form action="return_book.php" method="POST">
        <label for="transaction_id">Transaction ID</label>
        <input type="text" name="transaction_id" required><br>

        <button type="submit" name="return_book">Return Book</button>
    </form>
</body>
</html>
