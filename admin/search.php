<?php
include '../includes/db_connection.php';
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

try {
    $searchTerm = isset($_GET['term']) ? $_GET['term'] : '';
    
    if (strlen($searchTerm) >= 2) {
        $results = [];
        
        // Check if search term is numeric (could be an ID)
        if (is_numeric($searchTerm)) {
            // First try exact ID match
            $exactStmt = $pdo->prepare("
                SELECT * 
                FROM products 
                WHERE id = :id
                LIMIT 1
            ");
            
            $exactStmt->execute(['id' => $searchTerm]);
            $exactMatch = $exactStmt->fetch(PDO::FETCH_ASSOC);
            
            // If we found an exact match, return just that
            if ($exactMatch) {
                $results = [$exactMatch];
                header('Content-Type: application/json');
                echo json_encode($results);
                exit();
            }
        }
        
        // If no exact ID match or search term isn't numeric, perform regular name search
        $stmt = $pdo->prepare("
            SELECT * 
            FROM products 
            WHERE name LIKE :term OR id LIKE :term
            ORDER BY item_type ASC, name ASC
            LIMIT 20
        ");
        
        $stmt->execute(['term' => '%' . $searchTerm . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    } else {
        echo json_encode([]);
    }
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error occurred']);
}
?>