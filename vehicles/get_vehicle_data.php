<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid vehicle ID']);
    exit;
}

$vehicle_id = $_GET['id'];

try {
    // Get vehicle details
    $stmt = $pdo->prepare("SELECT v.*, 
                           c.name as customer_name, c.phone as customer_phone 
                           FROM vehicles v
                           LEFT JOIN customers c ON v.customer_id = c.id
                           WHERE v.id = ?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Vehicle not found']);
        exit;
    }
    
    // Return vehicle data as JSON
    header('Content-Type: application/json');
    echo json_encode($vehicle);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
