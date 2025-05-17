<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

$item_id = $_GET['id'];
$response = ['success' => false];

try {    // Get job item details
    $stmt = $pdo->prepare("SELECT * FROM job_card_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Job item not found']);
        exit;
    }
      // Get product details if associated
    $productDetails = null;
    if (!empty($item['product_id'])) {
        $stmt = $pdo->prepare("SELECT name, item_type, quantity FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $productDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Format response data
    $response = [
        'success' => true,
        'job_item' => [
            'id' => $item['id'],
            'jobCardId' => $item['job_card_id'],
            'itemType' => $item['item_type'],
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unitPrice' => $item['unit_price'],
            'totalPrice' => $item['total_price'],
            'productId' => $item['product_id'] ?? null,
            'productDetails' => $productDetails
        ]
    ];
    
} catch (PDOException $e) {
    $response = [
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
