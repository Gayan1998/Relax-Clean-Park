<?php
include '../includes/db_connection.php';
session_start();

// Ensure no output before this point
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Set headers before any output
header('Content-Type: application/json');

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('No items in quotation');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Initialize totals
    $totalAmount = 0;

    // Extract customer data if present
    $customerId = isset($data['customer_id']) ? $data['customer_id'] : null;
    
    // Set validity period (default: 14 days)
    $validUntil = date('Y-m-d H:i:s', strtotime('+14 days'));

    // Create new quotation record
    $stmt = $pdo->prepare("
        INSERT INTO quotations (customer_id, total_amount, quote_date, valid_until, status, notes, created_at, updated_at)
        VALUES (:customer_id, :total_amount, NOW(), :valid_until, 'pending', :notes, NOW(), NOW())
    ");

    // Initially insert with 0 totals
    $stmt->execute([
        'customer_id' => $customerId,
        'total_amount' => 0,
        'valid_until' => $validUntil,
        'notes' => isset($data['notes']) ? $data['notes'] : null
    ]);

    $quotationId = $pdo->lastInsertId();

    // Insert quotation items
    $stmtItems = $pdo->prepare("
        INSERT INTO quotation_items (quotation_id, product_id, quantity, price, total_price, created_at, updated_at)
        VALUES (:quotation_id, :product_id, :quantity, :price, :total_price, NOW(), NOW())
    ");

    // Process each item and calculate totals
    foreach ($data['items'] as $item) {
        $quantity = (int)$item['qty'];
        $sellingPrice = (float)$item['price'];
        
        // Calculate item totals
        $itemTotalPrice = $sellingPrice * $quantity;

        // Insert quotation item
        $stmtItems->execute([
            'quotation_id' => $quotationId,
            'product_id' => $item['id'],
            'quantity' => $quantity,
            'price' => $sellingPrice,
            'total_price' => $itemTotalPrice
        ]);

        // Add to running totals
        $totalAmount += $itemTotalPrice;
    }

    // Update the quotation with final totals
    $stmtUpdate = $pdo->prepare("
        UPDATE quotations 
        SET total_amount = :total_amount
        WHERE id = :quotation_id
    ");

    $stmtUpdate->execute([
        'total_amount' => $totalAmount,
        'quotation_id' => $quotationId
    ]);

    // Commit transaction
    $pdo->commit();

    // Return success response
    $response = [
        'success' => true,
        'quotation_id' => $quotationId,
        'total_amount' => $totalAmount,
        'valid_until' => $validUntil
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    echo json_encode($response);
}
?>