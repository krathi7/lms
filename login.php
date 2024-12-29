<?php
// Database connection
$host = 'localhost';
$db = 'dbms';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Table selection based on role
    $table = ($role === 'student') ? 'students' : 'staff';
    $id_column = ($role === 'student') ? 'usn' : 'staff_id';

    // Query to check login credentials
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $id_column = ? AND password = ?");
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Redirect to appropriate dashboard
        session_start();
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        if ($role === 'student') {
            header('Location: student_dashboard.php');
        } else {
            header('Location: staff_dashboard.php');
        }
    } else {
        echo "<h2>Invalid Credentials. Please try again.</h2>";
    }

    $stmt->close();
    $conn->close();
}
?>
