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

// Get all transactions
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
    <title>Transaction History</title>
</head>
<body>
    <h2>Transaction History</h2>
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Student ID</th>
                <th>Student USN</th>
                <th>Book Title</th>
                <th>Issue Date</th>
                <th>Return Date</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($transaction = $transactions_result->fetch_assoc()) : ?>
                <tr>
                    <td><?php echo $transaction['transaction_id']; ?></td>
                    <td><?php echo $transaction['student_id']; ?></td>
                    <td><?php echo $transaction['usn']; ?></td>
                    <td><?php echo $transaction['title']; ?></td>
                    <td><?php echo $transaction['issue_date']; ?></td>
                    <td><?php echo $transaction['return_date'] ? $transaction['return_date'] : 'Not Returned'; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
