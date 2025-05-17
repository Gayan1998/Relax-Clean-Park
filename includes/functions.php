<?php
/**
 * Get business settings from the database
 * 
 * @return array Business settings or default values if settings don't exist
 */
function get_business_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM business_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            return $settings;
        }
    } catch (PDOException $e) {
        // If the table doesn't exist yet or any other database error
        error_log("Failed to get business settings: " . $e->getMessage());
    }
    
    // Default values if settings don't exist
    return [
        'business_name' => 'KAYEL AUTO PARTS',
        'tagline' => 'Dealer of All Japan, Indian & China Vehicle Parts',
        'address' => 'Kurunegala Road, Vithikuliya, Nikaweratiya',
        'phone' => '077-9632277',
        'email' => '',
        'registration_number' => '',
        'additional_info' => '',
        'logo' => 'logo.png'
    ];
}

/**
 * Get vehicle information by ID
 * 
 * @param int $vehicle_id The vehicle ID
 * @return array|false Vehicle data or false if not found
 */
function get_vehicle_by_id($vehicle_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT v.*, c.name as customer_name, c.phone as customer_phone 
                               FROM vehicles v
                               LEFT JOIN customers c ON v.customer_id = c.id
                               WHERE v.id = ?");
        $stmt->execute([$vehicle_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get vehicle: " . $e->getMessage());
        return false;
    }
}

/**
 * Get job card information by ID
 * 
 * @param int $job_card_id The job card ID
 * @return array|false Job card data or false if not found
 */
function get_job_card_by_id($job_card_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT j.*, 
                               v.registration_number, v.make, v.model, v.year,
                               c.name as customer_name, c.phone as customer_phone 
                               FROM job_cards j
                               LEFT JOIN vehicles v ON j.vehicle_id = v.id
                               LEFT JOIN customers c ON v.customer_id = c.id
                               WHERE j.id = ?");
        $stmt->execute([$job_card_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get job card: " . $e->getMessage());
        return false;
    }
}

/**
 * Get job card items by job card ID
 * 
 * @param int $job_card_id The job card ID
 * @return array List of job card items
 */
function get_job_card_items($job_card_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM job_card_items WHERE job_card_id = ?");
        $stmt->execute([$job_card_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get job card items: " . $e->getMessage());
        return [];
    }
}

/**
 * Get job cards for a specific vehicle
 * 
 * @param int $vehicle_id The vehicle ID
 * @return array List of job cards for the vehicle
 */
function get_vehicle_job_cards($vehicle_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM job_cards WHERE vehicle_id = ? ORDER BY created_at DESC");
        $stmt->execute([$vehicle_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get vehicle job cards: " . $e->getMessage());
        return [];
    }
}

/**
 * Format a job card status with the appropriate color coding
 * 
 * @param string $status Job card status
 * @return string HTML for the formatted status badge
 */
function format_job_card_status($status) {
    switch ($status) {
        case 'Open':
            return '<span class="badge bg-primary">Open</span>';
        case 'In Progress':
            return '<span class="badge bg-warning text-dark">In Progress</span>';
        case 'Completed':
            return '<span class="badge bg-success">Completed</span>';
        case 'Invoiced':
            return '<span class="badge bg-info">Invoiced</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

function is_job_card_invoiced($pdo, $job_card_id) {
    try {
        // First, get the job number for the requested job card ID
        $jobStmt = $pdo->prepare("SELECT job_number FROM job_cards WHERE id = ?");
        $jobStmt->execute([$job_card_id]);
        $jobCard = $jobStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$jobCard) {
            return [
                'is_invoiced' => 0,
                'invoice_id' => null,
                'invoice_number' => null
            ];
        }
        
        // Check if there's a sales record (invoice) for this job number
        $salesStmt = $pdo->prepare("SELECT 
                               s.id as invoice_id,
                               s.id as invoice_number,
                               CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_invoiced
                           FROM job_cards j
                           LEFT JOIN sales s ON s.job_number = j.job_number
                           WHERE j.id = ?");
        $salesStmt->execute([$job_card_id]);
        $invoiceInfo = $salesStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'is_invoiced' => $invoiceInfo['is_invoiced'] ?? 0,
            'invoice_id' => $invoiceInfo['invoice_id'] ?? null,
            'invoice_number' => $invoiceInfo['invoice_number'] ?? null
        ];
    } catch (PDOException $e) {
        // Handle any database errors gracefully
        error_log("Error checking invoice status: " . $e->getMessage());
        return [
            'is_invoiced' => 0,
            'invoice_id' => null,
            'invoice_number' => null
        ];
    }
}