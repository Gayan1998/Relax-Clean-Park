<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// This file is used to fetch job card details via AJAX for the edit modal
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $job_card_id = $_GET['id'];
    
    try {
        // Get job card details
        $stmt = $pdo->prepare("SELECT 
                            j.*, 
                            c.name as customer_name,
                            v.make, v.model, v.registration_number
                          FROM job_cards j
                          LEFT JOIN customers c ON j.customer_id = c.id
                          LEFT JOIN vehicles v ON j.vehicle_id = v.id
                          WHERE j.id = ?");
        $stmt->execute([$job_card_id]);
        $jobCard = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare vehicle info display
        $vehicleInfo = '';
        if (!empty($jobCard['registration_number'])) {
            $vehicleInfo = $jobCard['registration_number'] . ' - ' . 
                           $jobCard['make'] . ' ' . $jobCard['model'];
        }
        
        // Return as JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'job_card' => [
                'id' => $jobCard['id'],
                'customerName' => $jobCard['customer_name'],
                'vehicleInfo' => $vehicleInfo,
                'status' => $jobCard['status'],
                'currentMileage' => $jobCard['current_mileage'],
                'nextServiceMileage' => $jobCard['next_service_mileage'],
                'reportedIssues' => $jobCard['reported_issues'],
                'technicianNotes' => $jobCard['technician_notes']
            ]
        ]);
        
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get job card details: ' . $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid job card ID'
    ]);
}
?>
