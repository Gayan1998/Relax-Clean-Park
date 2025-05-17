<?php
// This is a simple AJAX handler to check invoice status
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Get the job card ID from the request
$job_card_id = isset($_GET['job_card_id']) ? intval($_GET['job_card_id']) : 0;

if (!$job_card_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing job card ID'
    ]);
    exit;
}

try {
    // Get all job items
    $stmt = $pdo->prepare("SELECT * FROM job_card_items WHERE job_card_id = ? ORDER BY id ASC");
    $stmt->execute([$job_card_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total
    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['total_price']);
    }
    
    // Check if job card is invoiced
    $invoiceInfo = is_job_card_invoiced($pdo, $job_card_id);
    
    // Return success response with items, total, and invoice status
    echo json_encode([
        'success' => true,
        'items' => $items,
        'total' => $total,
        'is_invoiced' => $invoiceInfo['is_invoiced'] ? 1 : 0,
        'invoice_id' => $invoiceInfo['invoice_id']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}