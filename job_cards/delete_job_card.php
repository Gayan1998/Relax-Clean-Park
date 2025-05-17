<?php
require_once '../includes/db_connection.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_card_id'])) {
    $job_card_id = filter_input(INPUT_POST, 'job_card_id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($job_card_id) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete the job card items first
            $stmt = $pdo->prepare("DELETE FROM job_card_items WHERE job_card_id = ?");
            $stmt->execute([$job_card_id]);
            
            // Then delete the job card
            $stmt = $pdo->prepare("DELETE FROM job_cards WHERE id = ?");
            $stmt->execute([$job_card_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect back with success
            header("Location: job_cards.php?deleted=1");
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            header("Location: job_cards.php?error=" . urlencode("Error deleting job card: " . $e->getMessage()));
            exit;
        }
    } else {
        // Invalid job card ID
        header("Location: job_cards.php");
        exit;
    }
} else {
    // Not submitted via POST
    header("Location: job_cards.php");
    exit;
}
?>
