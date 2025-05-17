<?php
session_start();
include '../includes/db_connection.php'; // Include database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Debug: Print submitted username and password
    echo "Submitted Username: $username<br>";
    echo "Submitted Password: $password<br>";

    // Fetch user from the database
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // Debug: Print fetched user data
    echo "Fetched User Data: ";
    print_r($user);

    if ($user && password_verify($password, $user['password'])) {
        // Login successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        // Login failed
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: ../index.php");
        exit();
    }
}
?>