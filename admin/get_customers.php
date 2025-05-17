<?php
include '../includes/db_connection.php';
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Fetch customers with address field included
$query = "SELECT id, name, phone, email, address FROM customers ORDER BY name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'customers' => $customers,
    'timestamp' => time()
]);
?>