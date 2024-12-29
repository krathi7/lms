<?php
session_start();

// Check if the user is logged in as a student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.html');
    exit();
}

echo "<h2>Welcome, Student: " . $_SESSION['username'] . "</h2>";
echo "<p>This is your student dashboard.</p>";

// You can add more features here like viewing books, borrowing history, etc.
?>
<a href="logout.php">Logout</a>
