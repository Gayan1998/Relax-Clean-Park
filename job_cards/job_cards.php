<?php

require_once '../includes/db_connection.php';

require_once '../includes/functions.php';

session_start();



$business = get_business_settings();

// Check if there's a form submission for adding/updating job cards

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];

      // Handle job card creation

    if ($action === 'create') {

        // Process form data for new job card

        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_SANITIZE_NUMBER_INT);

        $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_SANITIZE_NUMBER_INT);

        $current_mileage = filter_input(INPUT_POST, 'current_mileage', FILTER_SANITIZE_NUMBER_INT);

        $next_service_mileage = filter_input(INPUT_POST, 'next_service_mileage', FILTER_SANITIZE_NUMBER_INT);       

        $reported_issues = filter_input(INPUT_POST, 'reported_issues', FILTER_SANITIZE_STRING);

        $technician_notes = filter_input(INPUT_POST, 'technician_notes', FILTER_SANITIZE_STRING);

        

        try {

            // Generate a job number (e.g., JC-YearMonthDay-Random)

            $job_number = 'JC-' . date('Ymd') . '-' . rand(100, 999);

            

            // Insert job card - using the columns that actually exist in the database

            $stmt = $pdo->prepare("INSERT INTO job_cards (job_number, customer_id, vehicle_id, 

                                current_mileage, next_service_mileage, reported_issues, technician_notes, status) 

                                VALUES (?, ?, ?, ?, ?, ?, ?, 'Open')");

            

            $stmt->execute([$job_number, $customer_id, $vehicle_id, 

                           $current_mileage, $next_service_mileage, $reported_issues, $technician_notes]);

            

            $_SESSION['message'] = "Job card created successfully.";

            header("Location: job_cards.php");

            exit();

        } catch (PDOException $e) {

            $_SESSION['error'] = "Failed to create job card: " . $e->getMessage();

        }

    }

      // Handle job card update

    else if ($action === 'update') {

        // Process form data for updating job card

        $job_card_id = filter_input(INPUT_POST, 'job_card_id', FILTER_SANITIZE_NUMBER_INT);

        $current_mileage = filter_input(INPUT_POST, 'current_mileage', FILTER_SANITIZE_NUMBER_INT);

        $next_service_mileage = filter_input(INPUT_POST, 'next_service_mileage', FILTER_SANITIZE_NUMBER_INT);        

        $reported_issues = filter_input(INPUT_POST, 'reported_issues', FILTER_SANITIZE_STRING);

        $technician_notes = filter_input(INPUT_POST, 'technician_notes', FILTER_SANITIZE_STRING);

        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        

        try {

            $stmt = $pdo->prepare("UPDATE job_cards SET current_mileage = ?, 

                               next_service_mileage = ?, reported_issues = ?, technician_notes = ?, status = ? 

                               WHERE id = ?");

            

            $stmt->execute([$current_mileage, $next_service_mileage, 

                          $reported_issues, $technician_notes, $status, $job_card_id]);

            

            // If status is changed to 'Completed', update completion date

            if ($status === 'Completed') {

                $stmt = $pdo->prepare("UPDATE job_cards SET completion_date = CURRENT_DATE WHERE id = ? AND completion_date IS NULL");

                $stmt->execute([$job_card_id]);

            }

            

            $_SESSION['message'] = "Job card updated successfully.";

            header("Location: job_cards.php");

            exit();

        } catch (PDOException $e) {

            $_SESSION['error'] = "Failed to update job card: " . $e->getMessage();

        }

    }

    

        // Handle job card deletion

    else if ($action === 'delete') {

        $job_card_id = filter_input(INPUT_POST, 'job_card_id', FILTER_SANITIZE_NUMBER_INT);

        

        try {

            // First delete all associated job items

            $stmt = $pdo->prepare("DELETE FROM job_card_items WHERE job_card_id = ?");

            $stmt->execute([$job_card_id]);

            

            // Then delete the job card

            $stmt = $pdo->prepare("DELETE FROM job_cards WHERE id = ?");

            $stmt->execute([$job_card_id]);

            

            $_SESSION['message'] = "Job card deleted successfully.";

            header("Location: job_cards.php");

            exit();

        } catch (PDOException $e) {

            $_SESSION['error'] = "Failed to delete job card: " . $e->getMessage();

        }    }

    // Handle job item creation

    else if ($action === 'add_item') {

        $job_card_id = filter_input(INPUT_POST, 'job_card_id', FILTER_SANITIZE_NUMBER_INT);

        $item_type = filter_input(INPUT_POST, 'item_type', FILTER_SANITIZE_STRING);

        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $unit_price = filter_input(INPUT_POST, 'unit_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT) ?: null;

          // Validate required fields and prepare specific error message

        $missing_fields = [];

        if (!$job_card_id) $missing_fields[] = 'job_card_id';

        if (!$item_type) $missing_fields[] = 'item type';

        if (!$description) $missing_fields[] = 'description';

        if (!$quantity) $missing_fields[] = 'quantity';

        if (!$unit_price) $missing_fields[] = 'unit price';

        

        if (!empty($missing_fields)) {

            header('Content-Type: application/json');

            echo json_encode([

                'success' => false,

                'message' => 'Missing required fields: ' . implode(', ', $missing_fields),

                'debug' => [

                    'job_card_id' => $job_card_id,

                    'job_card_id_raw' => $_POST['job_card_id'] ?? 'not set',

                    'item_type' => $item_type,

                    'description' => $description,

                    'quantity' => $quantity,

                    'unit_price' => $unit_price,

                    'post_data' => $_POST,

                    'viewing' => isset($viewing) ? 'true' : 'false',

                    'jobDetails' => isset($jobDetails) ? 'set' : 'not set'

                ]

            ]);

            exit();

        }

        

        try {

            // Calculate total price

            $total_price = $quantity * $unit_price;

            

            // If product_id is provided, check inventory for parts

            if ($product_id && $item_type === 'Part') {

                $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");

                $stmt->execute([$product_id]);

                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                

                if ($product && $product['quantity'] < $quantity) {

                    $_SESSION['error'] = "Insufficient inventory. Available: " . $product['quantity'];

                    

                    // Determine where the request came from

                    $referer = $_SERVER['HTTP_REFERER'] ?? '';

                    if (strpos($referer, 'view_job_card.php') !== false) {

                        header("Location: view_job_card.php?id=" . $job_card_id);

                    } else {

                        header("Location: job_cards.php?view=" . $job_card_id);

                    }

                    exit();

                }

                

                // Update product inventory

                $newQuantity = $product['quantity'] - $quantity;

                $stmt = $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");

                $stmt->execute([$newQuantity, $product_id]);

            }

              // Insert job item with product_id if selected            

            $stmt = $pdo->prepare("INSERT INTO job_card_items (job_card_id, item_type, description, quantity, unit_price, total_price, product_id) 

                                VALUES (?, ?, ?, ?, ?, ?, ?)");

            

            $stmt->execute([$job_card_id, $item_type, $description, $quantity, $unit_price, $total_price, $product_id]);

            $_SESSION['message'] = "Item added successfully.";

            

            // Return JSON response for any request - we'll handle all requests via AJAX

            header('Content-Type: application/json');

            echo json_encode([

                'success' => true, 

                'message' => 'Item added successfully',

                'job_card_id' => $job_card_id

            ]);

            exit();

        } catch (PDOException $e) {

            $_SESSION['error'] = "Failed to add item: " . $e->getMessage();

            

            // Determine where the request came from

            $referer = $_SERVER['HTTP_REFERER'] ?? '';

            if (strpos($referer, 'view_job_card.php') !== false) {

                header("Location: view_job_card.php?id=" . $job_card_id);

            } else {

                header("Location: job_cards.php?view=" . $job_card_id);

            }

            exit();

        }

    }

      // Handle job item update

    else if ($action === 'update_item') {

        $job_item_id = filter_input(INPUT_POST, 'job_item_id', FILTER_SANITIZE_NUMBER_INT);

        $job_card_id = filter_input(INPUT_POST, 'job_card_id', FILTER_SANITIZE_NUMBER_INT);

        $item_type = filter_input(INPUT_POST, 'item_type', FILTER_SANITIZE_STRING);

        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $unit_price = filter_input(INPUT_POST, 'unit_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $original_quantity = filter_input(INPUT_POST, 'original_quantity', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT) ?: null;

        

        try {

            // Calculate total price

            $total_price = $quantity * $unit_price;

            

            // Begin transaction

            $pdo->beginTransaction();

            

            // If product is linked and is a part, adjust inventory

            if ($product_id && strtolower($item_type) === 'part') {

                // Get current product quantity

                $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");

                $stmt->execute([$product_id]);

                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                

                if ($product) {

                    // Calculate quantity difference

                    $quantityDifference = $quantity - $original_quantity;

                    

                    // If quantity increased, check if we have enough inventory

                    if ($quantityDifference > 0 && $product['quantity'] < $quantityDifference) {

                        $pdo->rollBack();

                        $_SESSION['error'] = "Insufficient inventory. Available: " . $product['quantity'];

                        

                        // Determine where the request came from

                        $referer = $_SERVER['HTTP_REFERER'] ?? '';

                        if (strpos($referer, 'view_job_card.php') !== false) {

                            header("Location: view_job_card.php?id=" . $job_card_id);

                        } else {

                            header("Location: job_cards.php?view=" . $job_card_id);

                        }

                        exit();

                    }

                    

                    // Update product inventory

                    $newQuantity = $product['quantity'] - $quantityDifference;

                    $stmt = $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");

                    $stmt->execute([$newQuantity, $product_id]);

                }

            }

              // Update job item

            $stmt = $pdo->prepare("UPDATE job_card_items SET item_type = ?, description = ?, quantity = ?, 

                                unit_price = ?, total_price = ? WHERE id = ?");

            

            $stmt->execute([$item_type, $description, $quantity, $unit_price, $total_price, $job_item_id]);

            

            // Commit transaction

            $pdo->commit();

            

            // Return JSON response

            header('Content-Type: application/json');

            echo json_encode([

                'success' => true, 

                'message' => 'Item updated successfully',

                'job_card_id' => $job_card_id

            ]);

            exit();

        } catch (PDOException $e) {

            // Make sure to roll back the transaction if there's an error

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            

            $_SESSION['error'] = "Failed to update item: " . $e->getMessage();

            

            // Determine where the request came from

            $referer = $_SERVER['HTTP_REFERER'] ?? '';

            if (strpos($referer, 'view_job_card.php') !== false) {

                header("Location: view_job_card.php?id=" . $job_card_id);

            } else {

                header("Location: job_cards.php?view=" . $job_card_id);

            }

            exit();

        }

    }

      // Handle job item deletion

    else if ($action === 'delete_item') {

        $job_item_id = filter_input(INPUT_POST, 'job_item_id', FILTER_SANITIZE_NUMBER_INT);

        $job_card_id = filter_input(INPUT_POST, 'job_card_id', FILTER_SANITIZE_NUMBER_INT);

        

        // Debugging - log what we received

        error_log("Delete item request - job_item_id: $job_item_id, job_card_id: $job_card_id");

        

        if (empty($job_item_id) || empty($job_card_id)) {

            // Return error for missing params

            header('Content-Type: application/json');

            echo json_encode([

                'success' => false, 

                'message' => 'Missing required parameters',

                'received' => [

                    'job_item_id' => $job_item_id,

                    'job_card_id' => $job_card_id

                ]

            ]);

            exit();

        }

        

        try {

            // Begin transaction

            $pdo->beginTransaction();

              

            // Check if item is linked to a product

            $stmt = $pdo->prepare("SELECT item_type, quantity, product_id FROM job_card_items WHERE id = ?");

            $stmt->execute([$job_item_id]);

            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            

            if (!$item) {

                // Item not found

                $pdo->rollBack();

                header('Content-Type: application/json');

                echo json_encode([

                    'success' => false, 

                    'message' => 'Item not found'

                ]);

                exit();

            }

            

            // If item is a part with a product ID, return quantity to inventory

            if ($item && $item['product_id'] && strtolower($item['item_type']) === 'part') {

                $stmt = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");

                $stmt->execute([$item['quantity'], $item['product_id']]);

            }

              

            // Delete the job item

            $stmt = $pdo->prepare("DELETE FROM job_card_items WHERE id = ?");

            $stmt->execute([$job_item_id]);

            

            // Commit transaction

            $pdo->commit();

            

            // Return success JSON response

            header('Content-Type: application/json');

            echo json_encode([

                'success' => true, 

                'message' => 'Item deleted successfully',

                'job_card_id' => $job_card_id

            ]);

            exit();

            

        } catch (PDOException $e) {

            // Make sure to roll back the transaction if there's an error

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            

            // Return error response

            header('Content-Type: application/json');

            echo json_encode([

                'success' => false, 

                'message' => 'Failed to delete item: ' . $e->getMessage()

            ]);

            exit();

        }

    } 

} 



// Handle search/filter

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$status = isset($_GET['status']) ? $_GET['status'] : '';

$customer_id = isset($_GET['customer_id']) ? filter_input(INPUT_GET, 'customer_id', FILTER_SANITIZE_NUMBER_INT) : '';

$vehicle_id = isset($_GET['vehicle_id']) ? filter_input(INPUT_GET, 'vehicle_id', FILTER_SANITIZE_NUMBER_INT) : '';



// Get all job cards with related info

try {

    $query = "SELECT j.*, 

                    c.name as customer_name, 

                    v.make, v.model, v.registration_number

              FROM job_cards j

              LEFT JOIN customers c ON j.customer_id = c.id

              LEFT JOIN vehicles v ON j.vehicle_id = v.id

              WHERE 1=1";

    

    $params = [];

    

    if (!empty($search)) {

        $query .= " AND (j.job_number LIKE ? OR c.name LIKE ? OR v.registration_number LIKE ?)";

        $searchParam = "%$search%";

        $params = [$searchParam, $searchParam, $searchParam];

    }

    

    if (!empty($status)) {

        $query .= " AND j.status = ?";

        $params[] = $status;

    }

    

    if (!empty($customer_id)) {

        $query .= " AND j.customer_id = ?";

        $params[] = $customer_id;

    }

    

    if (!empty($vehicle_id)) {

        $query .= " AND j.vehicle_id = ?";

        $params[] = $vehicle_id;

    }

    

    $query .= " ORDER BY j.id DESC";

    

    $stmt = $pdo->prepare($query);

    $stmt->execute($params);

    $jobCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    

    // Get customers for filter dropdown

    $stmtCustomers = $pdo->query("SELECT id, name FROM customers ORDER BY name");

    $customers = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);

    

    // Get vehicles for filter dropdown

    $stmtVehicles = $pdo->query("SELECT id, registration_number, make, model, year FROM vehicles ORDER BY registration_number");

    $vehicles = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

    

    // If viewing a specific job card

    $viewing = false;

    $jobDetails = null;

    $jobItems = [];

    $jobTotal = 0;

    

    if (isset($_GET['view']) && is_numeric($_GET['view'])) {

        $viewing = true;

        $job_card_id = $_GET['view'];

        

        // Get job card details

        $stmt = $pdo->prepare("SELECT 

                                j.*, 

                                c.name as customer_name, c.phone as customer_phone, c.email as customer_email, c.address as customer_address, 

                                v.make, v.model, v.year, v.registration_number, v.color, v.vin

                              FROM job_cards j

                              LEFT JOIN customers c ON j.customer_id = c.id

                              LEFT JOIN vehicles v ON j.vehicle_id = v.id

                              WHERE j.id = ?");

        $stmt->execute([$job_card_id]);

        $jobDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if job card is invoiced and get invoice info
        $isInvoiced = false;
        $invoiceId = null;
        $invoiceNumber = null;
        if ($jobDetails) {
            require_once __DIR__ . '/../includes/functions.php';
            $invoiceInfo = is_job_card_invoiced($pdo, $job_card_id);
            $isInvoiced = $invoiceInfo['is_invoiced'] ?? 0;
            $invoiceId = $invoiceInfo['invoice_id'] ?? null;
            $invoiceNumber = $invoiceInfo['invoice_number'] ?? null;
        }

        

        if ($jobDetails) {            

            // Get job items

            $stmt = $pdo->prepare("SELECT * FROM job_card_items WHERE job_card_id = ?");

            $stmt->execute([$job_card_id]);

            $jobItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            

            // Calculate total

            foreach ($jobItems as $item) {

                $jobTotal += $item['total_price'];

            }

        }

    }

    

} catch (PDOException $e) {

    $error = "Database error: " . $e->getMessage();

    $jobCards = [];

    $customers = [];

    $vehicles = [];

}

?>

<!DOCTYPE html>

<html lang="en">

<head>    

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Job Card Management - <?php echo htmlspecialchars($business['business_name']); ?></title>

    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">

    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Select2 CSS -->

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- Theme CSS -->

    <link href="../assets/css/theme.css" rel="stylesheet" />    <!-- jQuery -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

    <!-- Select2 JS -->

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Common JS -->

    <script src="../assets/js/common.js"></script>

    <style>

        :root {

            --dark-bg: #1a1a1a;

            --darker-bg: #141414;

            --card-bg: #242424;

            --border-color: #333;

            --text-primary: #fff;

            --text-secondary:rgb(255, 255, 255);

            --accent-green: #4ade80;

            --accent-blue: #60a5fa;

            --accent-red: #f87171;

            --accent-yellow: #b6e134;

            --accent-purple: #a78bfa;

        }



        body {

            background-color: var(--dark-bg);

            color: var(--text-primary);

            min-height: 100vh;

            padding: 1rem;

        }



        .card {

            background-color: var(--card-bg);

            border: 1px solid var(--border-color);

            border-radius: 12px;

            margin-bottom: 2rem;

        }



        .card-header {

            color: var(--text-primary);

            background-color: var(--darker-bg);

            border-bottom: 1px solid var(--border-color);

            padding: 1rem;

        }        



        .form-control, .form-select {

            background-color: var(--darker-bg);

            border: 1px solid var(--border-color);

            color: var(--text-primary);

            padding: 0.5rem 1rem;

            height: auto;

        }



        .form-control:focus, .form-select:focus {

            background-color: var(--darker-bg);

            border-color: var(--accent-purple);

            color: var(--text-primary);

            box-shadow: 0 0 0 0.25rem rgba(167, 139, 250, 0.25);

        }

        

        /* Enhanced dropdown styling */

        .form-select {

            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23a78bfa' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");

            background-position: right 0.75rem center;

            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;

        }

          

        .form-select:hover {

            border-color: var(--accent-blue);

        }

          

        /* Enhanced dropdown option styling for dark mode */

        .form-select option {

            background-color: var(--darker-bg);

            color: var(--text-primary);

            padding: 10px;

            border-bottom: 1px solid var(--border-color);

            font-weight: 500;

        }

        

        /* Add proper contrast on hover */

        .form-select option:hover,

        .form-select option:focus,

        .form-select option:checked {

            background-color: var(--accent-purple);

            color: white;

        }

        

        /* Additional select enhancements for dark mode */

        select:-internal-list-box option:checked {

            background-color: var(--accent-purple) !important;

            color: white !important;

            font-weight: bold !important;

        }

        

        /* Firefox select styling */

        @-moz-document url-prefix() {

            .form-select {

                color: var(--text-primary);

                background-color: var(--darker-bg);

                padding-right: 25px;

            }

            .form-select option {

                background-color: var(--darker-bg);

            }

        }



        .table {

            color: var(--text-primary);

        }



        .table thead th {

            background-color: var(--darker-bg);

            color: var(--text-secondary);

            border-bottom: 2px solid var(--border-color);

        }



        .table td {

            border-color: var(--border-color);

        }

        

        .btn-primary {

            background-color: var(--accent-purple);

            border-color: var(--accent-purple);

        }

        

        .btn-primary:hover, .btn-primary:focus {

            background-color: #9061f9;

            border-color: #9061f9;

        }

        

        .modal-content {

            background-color: var(--card-bg);

            color: var(--text-primary);

        }

        

        .modal-header {

            border-bottom-color: var(--border-color);

        }

          

        .modal-footer {

            border-top-color: var(--border-color);

        }

        

        /* Custom dropdown styling has been simplified 

           Now using standard Bootstrap form-select elements */

        

        .form-check-input {

            margin-top: 3px;

        }

        

        .form-label {

            color: var(--text-primary);

            font-weight: 500;

        }

        

        .status-badge {

            color: black;

            font-size: 0.85rem;

            padding: 0.35rem 0.65rem;

        }

        

        .badge-open {

            background-color: var(--accent-blue);

        }

        

        .badge-in-progress {

            background-color: var(--accent-yellow);

            color: #000;

        }

        

        .badge-completed {

            background-color: var(--accent-green);

            color: #000;

        }

        

        .badge-cancelled {

            background-color: var(--accent-red);

        }

        

        .badge-invoiced {

            background-color: #17a2b8;

            color: white;

        }



        /* Custom Scrollbar */

        ::-webkit-scrollbar {

            width: 8px;

            height: 8px;

        }



        ::-webkit-scrollbar-track {

            background: var(--darker-bg);

        }



        ::-webkit-scrollbar-thumb {

            background: var(--border-color);

            border-radius: 4px;

        }



        ::-webkit-scrollbar-thumb:hover {

            background: #444;

        }

        

        .print-link {

            color: var(--accent-purple);

            text-decoration: none;

        }

        

        .print-link:hover {

            text-decoration: underline;

        }

        

        .job-detail-header {

            background-color: var(--darker-bg);

            border-radius: 10px;

            padding: 1.5rem;

            margin-bottom: 1.5rem;

            border-left: 5px solid var(--accent-purple);

        }

        

        .job-info-section {

            background-color: rgba(0,0,0,0.1);

            border-radius: 10px;

            padding: 1.5rem;

            margin-bottom: 1.5rem;

        }

        

        #jobDetailTabs .nav-link {

            color: var(--text-primary);

            border: none;

            background-color: transparent;

        }

        

        #jobDetailTabs .nav-link.active {

            color: var(--accent-purple);

            background-color: var(--darker-bg);

            border-radius: 5px;

        }

        

        #jobDetailTabs {

            border-bottom: 1px solid var(--border-color);

            margin-bottom: 1.5rem;

        }

        

        /* Mobile responsive styling */

        @media (max-width: 767px) {

            .table td, .table th {

                padding: 0.5rem;

                font-size: 0.9rem;

            }

            

            .btn-sm {

                font-size: 0.75rem;

                padding: 0.25rem 0.4rem;

            }

            

            .d-flex.justify-content-between {

                flex-direction: column;

                gap: 1rem;

            }

            

            .d-flex.justify-content-between .btn {

                width: 100%;

            }

        }

    </style>

</head>

<body>

    <div class="container">

        <?php 

        $page_title = "Job Card Management";

        include '../includes/vehicle_service_header.php'; 

        ?>

        

        <div class="row">

            <div class="col-md-12">

                <?php if (!$viewing): ?>

                    <!-- Job Cards List View -->                    

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div></div> <!-- Empty div to maintain flex spacing -->

                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobCardModal">

                            <i class="fas fa-plus"></i> Create New Job Card

                        </button>

                    </div>

                    

                    <?php if (isset($_SESSION['message'])): ?>

                        <div class="alert alert-success">

                            <?= $_SESSION['message'] ?>

                            <?php unset($_SESSION['message']); ?>

                        </div>

                    <?php endif; ?>

                    

                    <?php if (isset($_SESSION['error'])): ?>

                        <div class="alert alert-danger">

                            <?= $_SESSION['error'] ?>

                            <?php unset($_SESSION['error']); ?>

                        </div>

                    <?php endif; ?>

                    

                    <?php if (isset($error)): ?>

                        <div class="alert alert-danger"><?= $error ?></div>

                    <?php endif; ?>

                    

                    <div class="card mb-4">

                        <div class="card-header">

                            <h5 class="card-title">Search & Filter</h5>

                        </div>

                        <div class="card-body">

                            <form method="GET" action="">

                                <div class="row g-3">

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label for="search" class="form-label">Search</label>

                                            <input type="text" name="search" id="search" class="form-control" placeholder="Job #, Customer, Vehicle" value="<?= htmlspecialchars($search) ?>">

                                        </div>

                                    </div>

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label for="status" class="form-label">Status</label>

                                            <select name="status" id="status" class="form-select">

                                                <option value="">All Statuses</option>

                                                <option value="Open" <?= $status == 'Open' ? 'selected' : '' ?>>Open</option>

                                                <option value="In Progress" <?= $status == 'In Progress' ? 'selected' : '' ?>>In Progress</option>

                                                <option value="Completed" <?= $status == 'Completed' ? 'selected' : '' ?>>Completed</option>

                                                <option value="Cancelled" <?= $status == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="col-md-3">                                        

                                        <div class="form-group">

                                            <label for="customer_id" class="form-label">Customer</label>

                                            <select name="customer_id" id="customer_id" class="form-select">

                                                <option value="">All Customers</option>

                                                <?php foreach ($customers as $customer): ?>

                                                    <option value="<?= $customer['id'] ?>" <?= $customer_id == $customer['id'] ? 'selected' : '' ?>>

                                                        <?= htmlspecialchars($customer['name']) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="col-md-3">

                                        <div class="form-group">

                                            <label for="vehicle_id" class="form-label">Vehicle</label>

                                            <select name="vehicle_id" id="vehicle_id" class="form-select">

                                                <option value="">All Vehicles</option>

                                                <?php foreach ($vehicles as $vehicle): ?>

                                                    <option value="<?= $vehicle['id'] ?>" <?= $vehicle_id == $vehicle['id'] ? 'selected' : '' ?>>

                                                        <?= htmlspecialchars($vehicle['registration_number']) ?> 

                                                        (<?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?>)

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="col-md-12 mt-4">

                                        <div class="d-flex justify-content-end">

                                            <button type="submit" class="btn btn-primary">

                                                <i class="fas fa-search me-1"></i> Search

                                            </button>

                                            <a href="job_cards.php" class="btn btn-secondary ms-2">

                                                <i class="fas fa-undo me-1"></i> Reset

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </form>

                        </div>

                    </div>

                    

                    <div class="card">

                        <div class="card-header">

                            <h5 class="card-title">Job Cards</h5>

                        </div>

                        <div class="card-body">

                            <?php if (count($jobCards) > 0): ?>

                                <div class="table-responsive">

                                    <table class="table table-striped table-hover">

                                        <thead>

                                            <tr>

                                                <th>Job Number</th>

                                                <th>Customer</th>

                                                <th>Vehicle</th>

                                                <th>Created Date</th>

                                                <th>Status</th>

                                                <th>Actions</th>

                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php foreach ($jobCards as $jobCard): ?>

                                                <tr>

                                                    <td><?= htmlspecialchars($jobCard['job_number']) ?></td>

                                                    <td><?= htmlspecialchars($jobCard['customer_name'] ?? 'Unknown') ?></td>

                                                    <td>

                                                        <?php if (!empty($jobCard['registration_number'])): ?>

                                                            <?= htmlspecialchars($jobCard['registration_number']) ?>

                                                            <small class="d-block text-secondary">

                                                                <?= htmlspecialchars($jobCard['make'] . ' ' . $jobCard['model']) ?>

                                                            </small>

                                                        <?php else: ?>

                                                            <span class="text-secondary">Not specified</span>

                                                        <?php endif; ?>

                                                    </td>

                                                    <td><?= date('d/m/Y', strtotime($jobCard['created_at'])) ?></td>

                                                    <td>

                                                        <?php 

                                                            $statusClass = '';

                                                            switch ($jobCard['status']) {

                                                                case 'Open':

                                                                    $statusClass = 'badge-open';

                                                                    break;

                                                                case 'In Progress':

                                                                    $statusClass = 'badge-in-progress';

                                                                    break;

                                                                case 'Completed':

                                                                    $statusClass = 'badge-completed';

                                                                    break;

                                                                case 'Cancelled':

                                                                    $statusClass = 'badge-cancelled';

                                                                    break;

                                                            }

                                                        ?>

                                                        <span class="badge status-badge <?= $statusClass ?>">

                                                            <?= htmlspecialchars($jobCard['status']) ?>

                                                        </span>

                                                    </td>

                                                    <td>

                                                        <div class="btn-group btn-group-sm">                                                             

                                                            <a href="job_cards.php?view=<?= $jobCard['id'] ?>" class="btn btn-info">

                                                                <i class="fas fa-eye"></i>

                                                            </a>

                                                            <?php
                                                            $invoiceStmt = $pdo->prepare("SELECT i.id FROM sales i WHERE i.job_number = ?");
                                                            $invoiceStmt->execute([$jobCard['id']]);
                                                            $invoiceInfo = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
                                                            $isInvoiced = !empty($invoiceInfo);
                                                            ?>
                                                            <?php if (!$isInvoiced): ?>
                                                                <button type="button" class="btn btn-primary edit-job-card-btn" data-id="<?= $jobCard['id'] ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-danger delete-job-card" 
                                                                        data-id="<?= $jobCard['id'] ?>" 
                                                                        data-number="<?= htmlspecialchars($jobCard['job_number']) ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <a href="../inventory/generate_invoice.php?id=<?= $invoiceInfo['id'] ?>" target="_blank" class="btn btn-secondary">
                                                                    <i class="fas fa-file-invoice"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php else: ?>

                                <div class="alert alert-info">                                    

                                    No job cards found. <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#createJobCardModal">Create a new job card</a>.

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php else: ?>

                    <!-- Job Card Detail View -->

                    <?php if ($jobDetails): ?>

                        <div class="mb-3">

                            <nav aria-label="breadcrumb">

                                <ol class="breadcrumb">

                                    <li class="breadcrumb-item"><a href="job_cards.php" class="text-decoration-none">Job Cards</a></li>

                                    <li class="breadcrumb-item active"><?= htmlspecialchars($jobDetails['job_number']) ?></li>

                                </ol>

                            </nav>

                        </div>

                        

                        <?php if (isset($_SESSION['message'])): ?>

                            <div class="alert alert-success">

                                <?= $_SESSION['message'] ?>

                                <?php unset($_SESSION['message']); ?>

                            </div>

                        <?php endif; ?>

                        

                        <?php if (isset($_SESSION['error'])): ?>

                            <div class="alert alert-danger">

                                <?= $_SESSION['error'] ?>

                                <?php unset($_SESSION['error']); ?>

                            </div>

                        <?php endif; ?>

                        

                        <div class="job-detail-header">

                            <div class="d-flex justify-content-between align-items-start mb-3">

                                <div>

                                    <h2 class="mb-1"><?= htmlspecialchars($jobDetails['job_number']) ?></h2>

                                    <p class="mb-0 text-secondary">

                                        Created: <?= date('d/m/Y', strtotime($jobDetails['created_at'])) ?>

                                    </p>

                                </div>                                

                                <div class="d-flex gap-2">
    <a href="print_job_card.php?id=<?= $jobDetails['id'] ?>" target="_blank" class="btn btn-outline-light btn-sm">
        <i class="fas fa-print me-1"></i> Print
    </a>
    <?php if (!$isInvoiced): ?>
        <button type="button" class="btn btn-primary btn-sm edit-job-card-btn" data-id="<?= $jobDetails['id'] ?>">
            <i class="fas fa-edit me-1"></i> Edit
        </button>
        <?php if (strtolower($jobDetails['status']) == 'completed'): ?>
            <a href="convert_to_invoice.php?id=<?= $jobDetails['id'] ?>" class="btn btn-success btn-sm">
                <i class="fas fa-file-invoice-dollar me-1"></i> Convert to Invoice
            </a>
        <?php endif; ?>
    <?php else: ?>
        <a href="../inventory/generate_invoice.php?id=<?= $invoiceId ?>" target="_blank" class="btn btn-info btn-sm">
            <i class="fas fa-file-invoice me-1"></i> View Invoice
        </a>
    <?php endif; ?>
</div>

                            </div>

                            

                            <div class="row">

                                <div class="col-md-3 col-6 mb-3">

                                    <span class="d-block text-secondary">Status</span>

                                    <?php 
    $statusClass = '';
    if ($isInvoiced) {
        $statusClass = 'badge-invoiced';
        $displayStatus = 'Invoiced';
    } else {
        switch ($jobDetails['status']) {
            case 'Open':
                $statusClass = 'badge-open';
                break;
            case 'In Progress':
                $statusClass = 'badge-in-progress';
                break;
            case 'Completed':
                $statusClass = 'badge-completed';
                break;
            case 'Cancelled':
                $statusClass = 'badge-cancelled';
                break;
        }
        $displayStatus = $jobDetails['status'];
    }
?>
<span class="badge status-badge <?= $statusClass ?>">
    <?= htmlspecialchars($displayStatus) ?>
</span>
<?php if ($jobDetails['status'] == 'Completed' && !empty($jobDetails['completion_date']) && !$isInvoiced): ?>
    <small class="d-block mt-1 text-secondary">
        Completed on <?= date('d/m/Y', strtotime($jobDetails['completion_date'])) ?>
    </small>
<?php endif; ?>

                                </div>                                

                                <div class="col-md-3 col-6 mb-3">

                                    <span class="d-block text-secondary">Created Date</span>

                                    <strong><?= date('d/m/Y', strtotime($jobDetails['created_at'])) ?></strong>

                                </div>

                                <div class="col-md-3 col-6 mb-3">

                                    <span class="d-block text-secondary">Total Amount</span>

                                    <strong>LKR <?= number_format($jobTotal, 2) ?></strong>

                                </div>

                                <div class="col-md-3 col-6 mb-3">

                                    <span class="d-block text-secondary">Number of Items</span>

                                    <strong><?= count($jobItems) ?></strong>

                                </div>

                            </div>

                        </div>

                        

                        <ul class="nav nav-tabs" id="jobDetailTabs" role="tablist">

                            <li class="nav-item" role="presentation">

                                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">

                                    <i class="fas fa-info-circle me-1"></i> Details

                                </button>

                            </li>

                            <li class="nav-item" role="presentation">

                                <button class="nav-link" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab">

                                    <i class="fas fa-list me-1"></i> Items & Services

                                </button>

                            </li>

                        </ul>

                        

                        <div class="tab-content" id="jobDetailTabsContent">

                            <div class="tab-pane fade show active" id="details" role="tabpanel">

                                <div class="row mt-4">

                                    <div class="col-md-6 mb-4">

                                        <div class="job-info-section">

                                            <h4 class="mb-3">Customer Information</h4>

                                            <?php if (!empty($jobDetails['customer_name'])): ?>

                                                <p class="mb-1"><strong><?= htmlspecialchars($jobDetails['customer_name']) ?></strong></p>

                                                <?php if (!empty($jobDetails['customer_phone'])): ?>

                                                    <p class="mb-1">Phone: <?= htmlspecialchars($jobDetails['customer_phone']) ?></p>

                                                <?php endif; ?>

                                                <?php if (!empty($jobDetails['customer_email'])): ?>

                                                    <p class="mb-1">Email: <?= htmlspecialchars($jobDetails['customer_email']) ?></p>

                                                <?php endif; ?>

                                                <?php if (!empty($jobDetails['customer_address'])): ?>

                                                    <p class="mb-0">Address: <?= htmlspecialchars($jobDetails['customer_address']) ?></p>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p class="text-secondary">No customer information</p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                    

                                    <div class="col-md-6 mb-4">

                                        <div class="job-info-section">

                                            <h4 class="mb-3">Vehicle Information</h4>

                                            <?php if (!empty($jobDetails['registration_number'])): ?>

                                                <p class="mb-1"><strong>Registration: <?= htmlspecialchars($jobDetails['registration_number']) ?></strong></p>

                                                <p class="mb-1">

                                                    <?= htmlspecialchars($jobDetails['year']) ?> 

                                                    <?= htmlspecialchars($jobDetails['make']) ?> 

                                                    <?= htmlspecialchars($jobDetails['model']) ?>

                                                    <?php if (!empty($jobDetails['color'])): ?>

                                                        (<?= htmlspecialchars($jobDetails['color']) ?>)

                                                    <?php endif; ?>

                                                </p>

                                                <?php if (!empty($jobDetails['vin'])): ?>

                                                    <p class="mb-1">VIN: <?= htmlspecialchars($jobDetails['vin']) ?></p>

                                                <?php endif; ?>

                                                <p class="mb-0">

                                                    <span class="d-inline-block me-3">Current Mileage: <?= number_format($jobDetails['current_mileage']) ?> km</span>

                                                    <?php if (!empty($jobDetails['next_service_mileage'])): ?>

                                                        <span class="d-inline-block">Next Service: <?= number_format($jobDetails['next_service_mileage']) ?> km</span>

                                                    <?php endif; ?>

                                                </p>

                                            <?php else: ?>

                                                <p class="text-secondary">No vehicle information</p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>

                                

                                <div class="row">

                                    <div class="col-md-6 mb-4">

                                        <div class="job-info-section">

                                            <h4 class="mb-3">Reported Issues</h4>

                                            <?php if (!empty($jobDetails['reported_issues'])): ?>

                                                <p><?= nl2br(htmlspecialchars($jobDetails['reported_issues'])) ?></p>

                                            <?php else: ?>

                                                <p class="text-secondary">No reported issues</p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                    

                                    <div class="col-md-6 mb-4">                                        

                                        <div class="job-info-section">

                                            <h4 class="mb-3">Technician Notes</h4>

                                            <?php if (!empty($jobDetails['technician_notes'])): ?>

                                                <p><?= nl2br(htmlspecialchars($jobDetails['technician_notes'])) ?></p>

                                            <?php else: ?>

                                                <p class="text-secondary">No technician notes</p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            

                            <div class="tab-pane fade" id="items" role="tabpanel">

                                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">

                                    <h4>Items & Services</h4>

                                    <?php if (!$isInvoiced): ?>

                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">

                                            <i class="fas fa-plus me-1"></i> Add Item/Service

                                        </button>

                                    <?php endif; ?>

                                </div>

                                

                                <?php if (count($jobItems) > 0): ?>

                                    <div class="table-responsive">

                                        <table class="table table-striped">

                                            <thead>

                                                <tr>

                                                    <th>Type</th>

                                                    <th>Description</th>

                                                    <th>Quantity</th>

                                                    <th>Unit Price</th>

                                                    <th>Total</th>

                                                    <?php if (!$isInvoiced): ?>
                                                        <th>Actions</th>
                                                    <?php endif; ?>
                                                </tr>

                                            </thead>

                                            <tbody>

                                                <?php foreach ($jobItems as $item): ?>

                                                    <tr>

                                                        <td>

                                                            <?php if ($item['item_type'] == 'Part'): ?>

                                                                <span class="badge bg-primary">Part</span>

                                                            <?php elseif ($item['item_type'] == 'Service'): ?>

                                                                <span class="badge bg-info">Service</span>

                                                            <?php else: ?>

                                                                <span class="badge bg-secondary"><?= htmlspecialchars($item['item_type']) ?></span>

                                                            <?php endif; ?>

                                                        </td>

                                                        <td><?= htmlspecialchars($item['description']) ?></td>

                                                        <td><?= $item['quantity'] ?></td>

                                                        <td>LKR <?= number_format($item['unit_price'], 2) ?></td>

                                                        <td>LKR <?= number_format($item['total_price'], 2) ?></td>

                                                        <?php if (!$isInvoiced): ?>
                                                        <td>

                                                            <div class="btn-group btn-group-sm">

                                                                <button type="button" class="btn btn-primary edit-item" 

                                                                        data-id="<?= $item['id'] ?>"

                                                                        data-job-id="<?= $jobDetails['id'] ?>">

                                                                    <i class="fas fa-edit"></i>

                                                                </button>

                                                                <button type="button" class="btn btn-danger delete-item" 

                                                                        data-id="<?= $item['id'] ?>" 

                                                                        data-desc="<?= htmlspecialchars($item['description']) ?>">

                                                                    <i class="fas fa-trash"></i>

                                                                </button>

                                                            </div>

                                                        </td>
                                                        <?php endif; ?>

                                                    </tr>

                                                <?php endforeach; ?>

                                                <tr>

                                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>

                                                    <td><strong>LKR <?= number_format($jobTotal, 2) ?></strong></td>

                                                    <td></td>

                                                </tr>

                                            </tbody>

                                        </table>

                                    </div>

                                <?php else: ?>

                                    <div class="alert alert-info">

                                        No items or services added to this job card yet.

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="alert alert-danger">

                            Job card not found. <a href="job_cards.php" class="alert-link">Return to job card list</a>.

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>



    <!-- Delete Job Card Confirmation Modal -->

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">

        <div class="modal-dialog">

            <div class="modal-content">

                <div class="modal-header bg-danger text-white">

                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

                </div>

                <div class="modal-body">

                    Are you sure you want to delete the job card <span id="jobNumber"></span>?

                    <p class="text-danger">This action cannot be undone.</p>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                    <form id="deleteForm" method="POST" action="">

                        <input type="hidden" name="action" value="delete">

                        <input type="hidden" name="job_card_id" id="deleteJobCardId">

                        <button type="submit" class="btn btn-danger">Delete</button>

                    </form>

                </div>

            </div>

        </div>

    </div>

    

    <?php if ($viewing && $jobDetails): ?>        

        <!-- Add Item Modal -->

        <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">

            <div class="modal-dialog">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">

                        <h5 class="modal-title" id="addItemModalLabel">Add Item/Service</h5>

                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>

                    <div class="modal-body">                        

                        <form id="addItemForm" method="POST" action="">                            <input type="hidden" name="action" value="add_item">

                            <input type="hidden" name="job_card_id" id="add_job_card_id" value="<?= $jobDetails['id'] ?>">

                            <input type="hidden" name="product_id" id="product_id" value="">

                            

                            <!-- Add direct search box before dropdown -->

                            <div class="mb-3">

                                <label for="directSearch" class="form-label">Quick Search by Item ID/Name</label>

                                <div class="input-group">

                                    <input type="text" id="directSearch" class="form-control" placeholder="Enter item ID or scan barcode">

                                    <button class="btn btn-outline-secondary" type="button" id="directSearchBtn">

                                        <i class="fas fa-search"></i>

                                    </button>

                                </div>

                                <div id="searchSuggestions" class="list-group mt-1" style="display:none; position:absolute; z-index:1000; width:96%; max-height:200px; overflow-y:auto;"></div>

                                <small class="form-text text-muted">Enter item ID and press Enter, or search by name</small>

                            </div>

                            

                            <div class="mb-3">

                                <label for="item_type" class="form-label">Type</label>                                <select name="item_type" id="item_type" class="form-select" required>

                                    <option value="Part" selected>Part</option>

                                    <option value="Service">Service</option>

                                    <option value="Labor">Labor</option>

                                    <option value="Other">Other</option>

                                </select>

                            </div>

                            

                            <div class="mb-3" id="product_select_container">

                                <label for="product_select" class="form-label">Select from Inventory</label>

                                <select name="product_select" id="product_select" class="form-select" style="width: 100%;">

                                    <option value="">-- Select a Product/Service --</option>

                                    <?php

                                    // Get products and services from database

                                    try {

                                        $stmt = $pdo->prepare("SELECT id, name, item_type, description, selling_price, quantity FROM products ORDER BY name");

                                        $stmt->execute();

                                        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        

                                        $parts = [];

                                        $services = [];

                                        

                                        foreach ($products as $product) {

                                            if (strtolower($product['item_type']) === 'service') {

                                                $services[] = $product;

                                            } else {

                                                $parts[] = $product;

                                            }

                                        }

                                        

                                        // Output parts group

                                        if (!empty($parts)) {

                                            echo '<optgroup label="Parts">';

                                            foreach ($parts as $part) {

                                                echo '<option value="' . $part['id'] . '" 

                                                    data-type="Part" 

                                                    data-description="' . htmlspecialchars($part['description']) . '" 

                                                    data-price="' . $part['selling_price'] . '"

                                                    data-quantity="' . $part['quantity'] . '">

                                                    ' . htmlspecialchars($part['name']) . ' - LKR ' . number_format($part['selling_price'], 2) . '

                                                    </option>';

                                            }

                                            echo '</optgroup>';

                                        }

                                        

                                        // Output services group

                                        if (!empty($services)) {

                                            echo '<optgroup label="Services">';

                                            foreach ($services as $service) {

                                                echo '<option value="' . $service['id'] . '" 

                                                    data-type="Service" 

                                                    data-description="' . htmlspecialchars($service['description']) . '" 

                                                    data-price="' . $service['selling_price'] . '">

                                                    ' . htmlspecialchars($service['name']) . ' - LKR ' . number_format($service['selling_price'], 2) . '

                                                    </option>';

                                            }

                                            echo '</optgroup>';

                                        }

                                    } catch (PDOException $e) {

                                        echo '<option value="">Error loading products: ' . $e->getMessage() . '</option>';                                    

                                    }

                                    ?>

                                </select>

                                <div class="form-text">Select a product/service from inventory or enter details manually below</div>                            

                            </div>

                            

                            <!-- Changed from hidden to a visible field -->                            <div class="mb-3">

                                <label for="description" class="form-label">Description</label>

                                <input type="text" name="description" id="description" class="form-control" required placeholder="Enter item description">

                            </div>

                            

                            <div class="row mb-3">

                                <div class="col-md-6">

                                    <label for="quantity" class="form-label">Quantity</label>

                                    <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="0.01" step="0.01" required>

                                    <div id="quantity_warning" class="form-text text-danger" style="display:none;">

                                        Warning: Exceeds available inventory!

                                    </div>

                                </div>                                <div class="col-md-6">

                                    <label for="unit_price" class="form-label">Unit Price (LKR)</label>

                                    <input type="number" name="unit_price" id="unit_price" class="form-control" value="0.00" min="0" step="0.01" required>

                                </div>

                            </div>

                            

                            <div class="d-flex justify-content-end">

                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>

                                <button type="button" id="addItemButton" class="btn btn-primary">Add Item</button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

          

        <!-- Edit Item Modal -->

        <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">

            <div class="modal-dialog">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">

                        <h5 class="modal-title" id="editItemModalLabel">Edit Item/Service</h5>

                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>

                    <div class="modal-body">

                        <form id="editItemForm" method="POST" action="">

                            <input type="hidden" name="action" value="update_item">

                            <input type="hidden" name="job_card_id" value="<?= $jobDetails['id'] ?>">

                            <input type="hidden" name="job_item_id" id="edit_job_item_id">

                            <input type="hidden" name="original_quantity" id="edit_original_quantity">

                            <input type="hidden" name="product_id" id="edit_product_id">

                            

                            <div class="mb-3">

                                <label for="edit_item_type" class="form-label">Type</label>

                                <select name="item_type" id="edit_item_type" class="form-select" required>

                                    <option value="Part">Part</option>

                                    <option value="Service">Service</option>

                                    <option value="Labor">Labor</option>

                                    <option value="Other">Other</option>

                                </select>

                            </div>

                            

                            <div class="mb-3" id="edit_product_info">

                                <label class="form-label">Product Information</label>

                                <div id="edit_product_details" class="form-control bg-light" style="min-height: 38px;"></div>

                                <div class="form-text" id="edit_inventory_status"></div>

                            </div>

                            

                            <div class="mb-3">

                                <label for="edit_description" class="form-label">Description</label>

                                <input type="text" name="description" id="edit_description" class="form-control" required>

                            </div>

                            

                            <div class="row mb-3">

                                <div class="col-md-6">

                                    <label for="edit_quantity" class="form-label">Quantity</label>

                                    <input type="number" name="quantity" id="edit_quantity" class="form-control" min="0.01" step="0.01" required>

                                    <div id="edit_quantity_warning" class="form-text text-danger" style="display:none;">

                                        Warning: Change exceeds available inventory!

                                    </div>

                                </div>

                                <div class="col-md-6">

                                    <label for="edit_unit_price" class="form-label">Unit Price (LKR)</label>

                                    <input type="number" name="unit_price" id="edit_unit_price" class="form-control" min="0" step="0.01" required>

                                </div>

                            </div>

                            

                            <div class="d-flex justify-content-end">

                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>

                                <button type="button" id="updateItemButton" class="btn btn-primary">Update Item</button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

        

        <!-- Delete Item Confirmation Modal -->

        <div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemModalLabel" aria-hidden="true">

            <div class="modal-dialog">

                <div class="modal-content">

                    <div class="modal-header bg-danger text-white">

                        <h5 class="modal-title" id="deleteItemModalLabel">Confirm Delete Item</h5>

                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>

                    <div class="modal-body">

                        Are you sure you want to delete the item "<span id="itemDesc"></span>"?

                        <p class="text-danger">This action cannot be undone.</p>

                        

                        <!-- Hidden fields to store the item data -->

                        <input type="hidden" id="deleteItemId" name="job_item_id" value="">

                    </div>

                    <div class="modal-footer">

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                        <button type="button" id="deleteItemButton" class="btn btn-danger">Delete</button>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>        

    

    <!-- Edit Job Card Modal -->

    <div class="modal fade" id="editJobCardModal" tabindex="-1" aria-labelledby="editJobCardModalLabel" aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title" id="editJobCardModalLabel">Edit Job Card</h5>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

                </div>

                <div class="modal-body">

                    <form id="editJobCardForm" method="POST" action="job_cards.php">

                        <input type="hidden" name="action" value="update">

                        <input type="hidden" name="job_card_id" id="edit_job_card_id">

                        

                        <div class="row">

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="edit_customer_name" class="form-label">Customer</label>

                                    <input type="text" id="edit_customer_name" class="form-control" readonly>

                                </div>

                                

                                <div class="mb-3">

                                    <label for="edit_vehicle_info" class="form-label">Vehicle</label>

                                    <input type="text" id="edit_vehicle_info" class="form-control" readonly>

                                </div>

                                

                                <div class="mb-3">

                                    <label for="edit_status" class="form-label">Status</label>

                                    <select name="status" id="edit_status" class="form-select" required>

                                        <option value="Open">Open</option>

                                        <option value="In Progress">In Progress</option>

                                        <option value="Completed">Completed</option>

                                        <option value="Cancelled">Cancelled</option>

                                    </select>

                                </div>

                            </div>

                            

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="edit_current_mileage" class="form-label">Current Mileage</label>

                                    <input type="number" name="current_mileage" id="edit_current_mileage" class="form-control" min="0">

                                </div>

                                

                                <div class="mb-3">

                                    <label for="edit_next_service_mileage" class="form-label">Next Service Mileage</label>

                                    <input type="number" name="next_service_mileage" id="edit_next_service_mileage" class="form-control" min="0">

                                </div>

                            </div>

                        </div>

                        

                        <div class="row">

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="edit_reported_issues" class="form-label">Reported Issues</label>

                                    <textarea name="reported_issues" id="edit_reported_issues" class="form-control" rows="3"></textarea>

                                </div>

                            </div>

                            

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="edit_technician_notes" class="form-label">Technician Notes</label>

                                    <textarea name="technician_notes" id="edit_technician_notes" class="form-control" rows="3"></textarea>

                                </div>

                            </div>

                        </div>

                        

                        <div class="d-flex justify-content-end">

                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-primary">Update Job Card</button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>



    <!-- Create Job Card Modal -->

    <div class="modal fade" id="createJobCardModal" tabindex="-1" aria-labelledby="createJobCardModalLabel" aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title" id="createJobCardModalLabel">Create New Job Card</h5>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

                </div>

                <div class="modal-body">

                    <form id="createJobCardForm" method="POST" action="job_cards.php">

                        <input type="hidden" name="action" value="create">

                        

                        <div class="row">

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="customer_id" class="form-label">Customer*</label>

                                    <select name="customer_id" id="create_customer_id" class="form-select" required>

                                        <option value="">Select Customer</option>

                                        <?php 

                                        // Get customers list for dropdown

                                        try {

                                            $customerStmt = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name");

                                            $customersList = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

                                            

                                            foreach ($customersList as $customer): 

                                        ?>

                                            <option value="<?= $customer['id'] ?>">

                                                <?= htmlspecialchars($customer['name']) ?> (<?= htmlspecialchars($customer['phone']) ?>)

                                            </option>

                                        <?php 

                                            endforeach;

                                        } catch (PDOException $e) {

                                            // Handle error silently

                                        }

                                        ?>

                                    </select>

                                </div>

                                

                                <div class="mb-3">

                                    <label for="vehicle_id" class="form-label">Vehicle*</label>

                                    <select name="vehicle_id" id="create_vehicle_id" class="form-select" required>

                                        <option value="">Select Vehicle</option>

                                        <?php 

                                        // Get vehicles list for dropdown - don't filter yet, we'll do that with JavaScript

                                        try {

                                            $vehicleStmt = $pdo->query("SELECT v.*, c.name as customer_name 

                                                                    FROM vehicles v 

                                                                    LEFT JOIN customers c ON v.customer_id = c.id

                                                                    ORDER BY v.make, v.model");

                                            $vehiclesList = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);

                                            

                                            foreach ($vehiclesList as $vehicle): 

                                                // Add data-customer attribute for filtering

                                        ?>

                                            <option value="<?= $vehicle['id'] ?>" 

                                                    data-customer="<?= $vehicle['customer_id'] ?>"

                                                    data-make="<?= htmlspecialchars($vehicle['make']) ?>"

                                                    data-model="<?= htmlspecialchars($vehicle['model']) ?>"

                                                    data-registration="<?= htmlspecialchars($vehicle['registration_number']) ?>"

                                                    data-customer-name="<?= htmlspecialchars($vehicle['customer_name']) ?>">

                                                <?= htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) ?> 

                                                (<?= htmlspecialchars($vehicle['registration_number']) ?>)

                                            </option>

                                        <?php 

                                            endforeach;

                                        } catch (PDOException $e) {

                                            // Handle error silently

                                        }

                                        ?>

                                    </select>                                

                                </div>

                            </div>

                            

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="current_mileage" class="form-label">Current Mileage</label>

                                    <input type="number" name="current_mileage" id="current_mileage" class="form-control" min="0">

                                </div>

                                

                                <div class="mb-3">

                                    <label for="next_service_mileage" class="form-label">Next Service Mileage</label>

                                    <input type="number" name="next_service_mileage" id="next_service_mileage" class="form-control" min="0">

                                </div>

                            </div>

                        </div>

                        

                        <div class="row">                            

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="reported_issues" class="form-label">Reported Issues</label>

                                    <textarea name="reported_issues" id="reported_issues" class="form-control" rows="3"></textarea>

                                </div>

                            </div>

                            

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="technician_notes" class="form-label">Technician Notes</label>

                                    <textarea name="technician_notes" id="technician_notes" class="form-control" rows="3"></textarea>

                                </div>

                            </div>

                        </div>

                        

                        <div class="d-flex justify-content-end">

                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-primary">Create Job Card</button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>    <script>

        $(document).ready(function() {

            // Clean up any leftover modal backdrops on page load

            $('.modal-backdrop').remove();

            $('body').removeClass('modal-open').css('padding-right', '');

            // Initialize Select2 for product dropdown

            if ($('#product_select').length) {

                $('#product_select').select2({

                    theme: 'bootstrap-5',

                    width: '100%',

                    placeholder: '-- Select a Product/Service --',

                    allowClear: true,

                    dropdownParent: $('#addItemModal')

                });

                

                // Direct product ID search for Add Item modal

                // Make sure we unbind any existing handlers first to avoid conflicts

                $(document).off('keydown', '.select2-search__field');

                

                // More direct approach to capture the Enter key in the Select2 search field

                $(document).on('keydown', '.select2-search__field', function(e) {

                    if (e.key === 'Enter' && $(this).closest('.select2-dropdown').length) {

                        const searchVal = $(this).val().trim();

                        console.log('Search entered:', searchVal);

                        

                        // If it looks like a product ID (numeric only)

                        if (searchVal.match(/^\d+$/)) {

                            e.preventDefault();

                            e.stopPropagation();

                            

                            // AJAX call to find product by ID

                            $.ajax({

                                url: '../admin/search.php', // Make sure this path is correct

                                type: 'GET',

                                data: { term: searchVal },

                                dataType: 'json',

                                success: function(data) {

                                    console.log('Product search result:', data);

                                    

                                    // If exactly one matching product found by ID

                                    if (data && data.length === 1) {

                                        const product = data[0];

                                        console.log('Found product to add:', product);

                                        

                                        // Get the dropdown element

                                        let select2Element = $('#product_select');

                                        

                                        // Check if product already exists in dropdown

                                        if (select2Element.find('option[value="' + product.id + '"]').length) {

                                            // Product exists in dropdown, select it

                                            select2Element.val(product.id).trigger('change');

                                        } else {

                                            // Product not in dropdown, add it

                                            const type = product.item_type === 'service' ? 'Service' : 'Part';

                                            const newOption = new Option(

                                                product.name + ' - LKR ' + parseFloat(product.selling_price).toFixed(2),

                                                product.id,

                                                true,

                                                true

                                            );

                                            

                                            // Set data attributes

                                            $(newOption).attr({

                                                'data-type': type,

                                                'data-description': product.description || '',

                                                'data-price': product.selling_price,

                                                'data-quantity': product.quantity || 0

                                            });

                                            

                                            // Add option to dropdown

                                            select2Element.append(newOption).trigger('change');

                                        }

                                        

                                        // Now set all the other fields manually

                                        const type = product.item_type === 'service' ? 'Service' : 'Part';

                                        $('#item_type').val(type);

                                        $('#description').val(product.name);

                                        $('#unit_price').val(product.selling_price);

                                        $('#product_id').val(product.id);

                                        

                                        // Close the dropdown

                                        select2Element.select2('close');

                                        

                                        // If it's a part, check inventory

                                        if (type === 'Part' && product.quantity !== undefined) {

                                            // Remove any old listeners

                                            $('#quantity').off('input');

                                            

                                            // Add new listener

                                            $('#quantity').on('input', function() {

                                                if (parseFloat($(this).val()) > parseFloat(product.quantity)) {

                                                    $('#quantity_warning').show();

                                                } else {

                                                    $('#quantity_warning').hide();

                                                }

                                            });

                                            

                                            // Initial check

                                            if (parseFloat($('#quantity').val()) > parseFloat(product.quantity)) {

                                                $('#quantity_warning').show();

                                            } else {

                                                $('#quantity_warning').hide();

                                            }

                                        }

                                    }

                                },

                                error: function(xhr, status, error) {

                                    console.error('Error searching for product:', error);

                                    console.log('Response:', xhr.responseText);

                                }

                            });

                        }

                    }

                });

            }

            

            // Direct search functionality for Add Item modal

            if ($('#directSearch').length) {

                const directSearch = $('#directSearch');

                const suggestionsContainer = $('#searchSuggestions');

                let searchResults = [];

                let debounceTimer;

                

                // Setup direct search input handler

                directSearch.on('input', function() {

                    const searchTerm = $(this).val().trim();

                    clearTimeout(debounceTimer);

                    

                    if (searchTerm.length < 2) {

                        suggestionsContainer.hide();

                        return;

                    }

                    

                    debounceTimer = setTimeout(function() {

                        // Fetch suggestions from search.php

                        $.ajax({

                            url: '../admin/search.php',

                            type: 'GET',

                            data: { term: searchTerm },

                            dataType: 'json',

                            success: function(data) {

                                searchResults = data;

                                

                                if (searchResults.length === 0) {

                                    suggestionsContainer.hide();

                                    return;

                                }

                                

                                // Display suggestions

                                let suggestionHtml = '';

                                searchResults.forEach(function(item) {

                                    const isService = item.item_type === 'service';

                                    const stockLabel = isService ? 

                                        '<span class="badge bg-info">Service</span>' : 

                                        '<span class="badge ' + (parseInt(item.quantity) > 0 ? 'bg-success' : 'bg-danger') + '">' + 

                                            (parseInt(item.quantity) > 0 ? 'In Stock: ' + item.quantity : 'Out of Stock') + 

                                        '</span>';

                                    

                                    suggestionHtml += `

                                        <a href="#" class="list-group-item list-group-item-action search-suggestion" 

                                           data-id="${item.id}" data-item-type="${item.item_type}">

                                            <div class="d-flex justify-content-between align-items-center">

                                                <strong>${item.name}</strong>

                                                ${stockLabel}

                                            </div>

                                            <div class="small">Price: LKR ${parseFloat(item.selling_price).toFixed(2)}</div>

                                        </a>

                                    `;

                                });

                                

                                suggestionsContainer.html(suggestionHtml).show();

                            },

                            error: function(xhr, status, error) {

                                console.error('Error searching for products:', error);

                            }

                        });

                    }, 300);

                });

                

                // Direct search enter key handler

                directSearch.on('keypress', function(e) {

                    if (e.key === 'Enter') {

                        e.preventDefault();

                        const searchTerm = $(this).val().trim();

                        

                        if (searchTerm.length < 2) return;

                        

                        // If it's a numeric ID, try to find an exact match

                        if (searchTerm.match(/^\d+$/)) {

                            $.ajax({

                                url: '../admin/search.php',

                                type: 'GET',

                                data: { term: searchTerm },

                                dataType: 'json',

                                success: function(data) {

                                    // If exactly one match found, use it

                                    if (data && data.length === 1) {

                                        selectProduct(data[0]);

                                        suggestionsContainer.hide();

                                        directSearch.val('');

                                    } else if (data && data.length > 0) {

                                        // If multiple results, show them

                                        searchResults = data;

                                        let suggestionHtml = '';

                                        

                                        searchResults.forEach(function(item) {

                                            const isService = item.item_type === 'service';

                                            const stockLabel = isService ? 

                                                '<span class="badge bg-info">Service</span>' : 

                                                '<span class="badge ' + (parseInt(item.quantity) > 0 ? 'bg-success' : 'bg-danger') + '">' + 

                                                    (parseInt(item.quantity) > 0 ? 'In Stock: ' + item.quantity : 'Out of Stock') + 

                                                '</span>';

                                            

                                            suggestionHtml += `

                                                <a href="#" class="list-group-item list-group-item-action search-suggestion" 

                                                  data-id="${item.id}" data-item-type="${item.item_type}">

                                                    <div class="d-flex justify-content-between align-items-center">

                                                        <strong>${item.name}</strong>

                                                        ${stockLabel}

                                                    </div>

                                                    <div class="small">Price: LKR ${parseFloat(item.selling_price).toFixed(2)}</div>

                                                </a>

                                            `;

                                        });

                                        

                                        suggestionsContainer.html(suggestionHtml).show();

                                    } else {

                                        // No results

                                        suggestionsContainer.hide();

                                    }

                                }

                            });

                        }

                    }

                });

                

                // Direct search button click handler

                $('#directSearchBtn').on('click', function() {

                    const searchTerm = directSearch.val().trim();

                    if (searchTerm.length < 2) return;

                    

                    // Trigger the search

                    $.ajax({

                        url: '../admin/search.php',

                        type: 'GET',

                        data: { term: searchTerm },

                        dataType: 'json',

                        success: function(data) {

                            searchResults = data;

                            

                            if (searchResults.length === 0) {

                                suggestionsContainer.hide();

                                return;

                            }

                            

                            // Display suggestions

                            let suggestionHtml = '';

                            searchResults.forEach(function(item) {

                                const isService = item.item_type === 'service';

                                const stockLabel = isService ? 

                                    '<span class="badge bg-info">Service</span>' : 

                                    '<span class="badge ' + (parseInt(item.quantity) > 0 ? 'bg-success' : 'bg-danger') + '">' + 

                                        (parseInt(item.quantity) > 0 ? 'In Stock: ' + item.quantity : 'Out of Stock') + 

                                    '</span>';

                                

                                suggestionHtml += `

                                    <a href="#" class="list-group-item list-group-item-action search-suggestion" 

                                       data-id="${item.id}" data-item-type="${item.item_type}">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <strong>${item.name}</strong>

                                            ${stockLabel}

                                        </div>

                                        <div class="small">Price: LKR ${parseFloat(item.selling_price).toFixed(2)}</div>

                                    </a>

                                `;

                            });

                            

                            suggestionsContainer.html(suggestionHtml).show();

                        }

                    });

                });

                

                // Handle suggestion click

                $(document).on('click', '.search-suggestion', function(e) {

                    e.preventDefault();

                    const productId = $(this).data('id');

                    const product = searchResults.find(item => parseInt(item.id) === parseInt(productId));

                    

                    if (product) {

                        selectProduct(product);

                        suggestionsContainer.hide();

                        directSearch.val('');

                    }

                });

                

                // Handle click outside suggestions

                $(document).on('click', function(e) {

                    if (!suggestionsContainer.is(e.target) && suggestionsContainer.has(e.target).length === 0 && 

                        !directSearch.is(e.target) && !$('#directSearchBtn').is(e.target)) {

                        suggestionsContainer.hide();

                    }

                });

                  // Function to select a product from search

                function selectProduct(product) {

                    // Set the type first based on item_type with better case handling

                    let type = 'Part'; // Default to Part

                    if (product.item_type) {

                        const itemType = product.item_type.toLowerCase();

                        if (itemType === 'service') {

                            type = 'Service';

                        } else if (itemType === 'labor') {

                            type = 'Labor'; 

                        } else if (itemType === 'other') {

                            type = 'Other';

                        } else {

                            type = 'Part'; // Default for products

                        }

                    }

                    $('#item_type').val(type);

                    

                    // Check if the product is in the dropdown

                    if ($('#product_select option[value="' + product.id + '"]').length) {

                        // Product exists in dropdown, select it

                        $('#product_select').val(product.id).trigger('change');

                    } else {

                        // Product not in dropdown, reset dropdown selection

                        $('#product_select').val('').trigger('change');

                        

                        // Manually fill in the fields

                        $('#description').val(product.name);

                        $('#unit_price').val(product.selling_price);

                        $('#product_id').val(product.id);

                        

                        // Handle inventory check for parts

                        if (type === 'Part' && product.quantity !== undefined) {

                            // Remove previous listeners

                            $('#quantity').off('input');

                            

                            // Add new listener

                            $('#quantity').on('input', function() {

                                if (parseFloat($(this).val()) > parseFloat(product.quantity)) {

                                    $('#quantity_warning').show();

                                } else {

                                    $('#quantity_warning').hide();

                                }

                            });

                            

                            // Initial check

                            if (parseFloat($('#quantity').val()) > parseFloat(product.quantity)) {

                                $('#quantity_warning').show();

                            }

                        } else {

                            $('#quantity_warning').hide();

                        }

                    }

                }

            }



            // Better Select2 initialization with proper defaults and templates

            if ($('#create_customer_id').length) {

                $('#create_customer_id').select2({

                    theme: 'bootstrap-5',

                    width: '100%',

                    placeholder: 'Select Customer',

                    allowClear: true,

                    dropdownParent: $('#createJobCardModal')

                });

            }

            

            if ($('#create_vehicle_id').length) {

                $('#create_vehicle_id').select2({

                    theme: 'bootstrap-5',

                    width: '100%',

                    placeholder: 'Select Vehicle',

                    allowClear: true,

                    dropdownParent: $('#createJobCardModal'),

                    templateResult: formatVehicle,

                    templateSelection: formatVehicleSelection

                });

                

                // Call the filter immediately to hide vehicles that don't have customers

                filterVehiclesByCustomer($('#create_customer_id').val());

            }

            

            // Format the vehicle dropdown options

            function formatVehicle(vehicle) {

                if (!vehicle.id) {

                    return vehicle.text;

                }

                

                // Get data from the data attributes

                var $vehicle = $(vehicle.element);

                var make = $vehicle.data('make') || '';

                var model = $vehicle.data('model') || '';

                var registration = $vehicle.data('registration') || '';

                var customerName = $vehicle.data('customer-name') || '';

                

                // Create a nicely formatted option

                var $formatted = $(`

                    <div>

                        <strong>${make} ${model}</strong> 

                        <span class="text-secondary">(${registration})</span>

                        <div><small>Customer: ${customerName}</small></div>

                    </div>

                `);

                

                return $formatted;

            }

            

            // Format the selected vehicle display

            function formatVehicleSelection(vehicle) {

                if (!vehicle.id) {

                    return vehicle.text;

                }

                

                // Get data from the data attributes

                var $vehicle = $(vehicle.element);

                var make = $vehicle.data('make') || '';

                var model = $vehicle.data('model') || '';

                var registration = $vehicle.data('registration') || '';

                

                // Return a simpler format for the selection

                return `${make} ${model} (${registration})`;

            }

            

            // Filter vehicles by customer function

            function filterVehiclesByCustomer(customerId) {

                // First detach select2

                $('#create_vehicle_id').select2('destroy');

                

                // Reset vehicle selection

                $('#create_vehicle_id').val('');

                

                // Hide/show options based on customer

                $('#create_vehicle_id option').each(function() {

                    const $option = $(this);

                    if ($option.val() === '' || !customerId || $option.data('customer') == customerId) {

                        $option.prop('disabled', false);

                    } else {

                        $option.prop('disabled', true);

                    }

                });

                

                // Re-init select2

                $('#create_vehicle_id').select2({

                    theme: 'bootstrap-5',

                    width: '100%',

                    placeholder: 'Select Vehicle',

                    allowClear: true,

                    dropdownParent: $('#createJobCardModal'),

                    templateResult: formatVehicle,

                    templateSelection: formatVehicleSelection

                });

            }

            

            // Handle tab selection from URL hash

            var hash = window.location.hash;

            if (hash) {

                $('.nav-tabs a[href="' + hash + '"]').tab('show');

            }

            

            // Setup delete confirmation for job cards

            $('.delete-job-card').on('click', function(e) {

                e.preventDefault();

                const id = $(this).data('id');

                const number = $(this).data('number');

                

                $('#deleteJobCardId').val(id);

                $('#jobNumber').text(number);

                $('#deleteModal').modal('show');

            });

            

            // Setup edit job card handling

            $('.edit-job-card-btn').on('click', function() {

                const id = $(this).data('id');

                

                // Fetch job card details via AJAX

                $.ajax({

                    url: 'get_job_card_ajax.php',

                    type: 'GET',

                    data: { id: id },

                    dataType: 'json',

                    success: function(response) {

                        if (response.success) {

                            const jobCard = response.job_card;

                            

                            // Populate the form fields

                            $('#edit_job_card_id').val(jobCard.id);

                            $('#edit_customer_name').val(jobCard.customerName);

                            $('#edit_vehicle_info').val(jobCard.vehicleInfo);

                            $('#edit_status').val(jobCard.status);

                            $('#edit_current_mileage').val(jobCard.currentMileage);

                            $('#edit_next_service_mileage').val(jobCard.nextServiceMileage);

                            $('#edit_reported_issues').val(jobCard.reportedIssues);

                            $('#edit_technician_notes').val(jobCard.technicianNotes);

                            

                            // Show the modal

                            $('#editJobCardModal').modal('show');

                        } else {

                            alert('Failed to load job card details: ' + response.message);

                        }

                    },

                    error: function() {

                        alert('Error loading job card details. Please try again.');

                    }

                });

            });

            

            // Setup edit item handling

            $('.edit-item').on('click', function() {

                const id = $(this).data('id');

                const jobCardId = $(this).data('job-id');

                

                // Fetch job item details via AJAX

                $.ajax({

                    url: 'get_job_item_ajax.php',

                    type: 'GET',

                    data: { id: id },

                    dataType: 'json',

                    success: function(response) {

                        if (response.success) {

                            const jobItem = response.job_item;

                            

                            // Populate the form fields

                            $('#edit_job_item_id').val(jobItem.id);

                            $('#edit_item_type').val(jobItem.itemType);

                            $('#edit_description').val(jobItem.description);

                            $('#edit_quantity').val(jobItem.quantity);

                            $('#edit_original_quantity').val(jobItem.quantity);

                            $('#edit_unit_price').val(jobItem.unitPrice);

                            $('#edit_product_id').val(jobItem.productId || '');

                            

                            // If product is linked, show product details

                            if (jobItem.productId && jobItem.productDetails) {

                                const product = jobItem.productDetails;

                                $('#edit_product_details').text(`${product.name} (${product.item_type})`);

                                $('#edit_product_info').show();

                                

                                // For parts, monitor quantity for inventory checks

                                if (jobItem.itemType.toLowerCase() === 'part') {

                                    const availableStock = parseFloat(product.quantity) + parseFloat(jobItem.quantity);

                                    $('#edit_inventory_status').text(`Current Stock: ${availableStock}`);

                                    

                                    // Setup quantity change event

                                    $('#edit_quantity').off('input').on('input', function() {

                                        const newQty = parseFloat($(this).val()) || 0;

                                        const originalQty = parseFloat(jobItem.quantity) || 0;

                                        

                                        // Only warn if increasing quantity beyond available stock

                                        if (newQty > originalQty && (newQty - originalQty) > availableStock) {

                                            $('#edit_quantity_warning').show();

                                        } else {

                                            $('#edit_quantity_warning').hide();

                                        }

                                    });

                                } else {

                                    $('#edit_inventory_status').text('');

                                    $('#edit_quantity_warning').hide();

                                }

                            } else {

                                $('#edit_product_details').text('No product linked');

                                $('#edit_inventory_status').text('');

                                $('#edit_quantity_warning').hide();

                            }

                            

                            // Show the modal

                            $('#editItemModal').modal('show');

                        } else {

                            alert('Failed to load job item details: ' + response.message);

                        }

                    },

                    error: function() {

                        alert('Error loading job item details. Please try again.');

                    }

                });

            });

            

            // Setup delete item confirmation

            $('.delete-item').on('click', function() {

                const id = $(this).data('id');

                const desc = $(this).data('desc');

                

                $('#deleteItemId').val(id);

                $('#itemDesc').text(desc);

                $('#deleteItemModal').modal('show');

            });

            

            // Set up create job card customer/vehicle relationship

            $('#create_customer_id').on('change', function() {

                const customerId = $(this).val();

                

                // Filter vehicles to show only those belonging to the selected customer

                filterVehiclesByCustomer(customerId);

            });

            

            // When selecting a vehicle, set the corresponding customer

            $('#create_vehicle_id').on('change', function() {

                const $selectedOption = $(this).find('option:selected');

                const customerId = $selectedOption.data('customer');

                

                if (customerId && $('#create_customer_id').val() !== customerId) {

                    // We need to update the customer selection, but don't want to trigger

                    // the customer change event again to avoid a loop

                    $('#create_customer_id').val(customerId).trigger('change.select2');

                }

            });

            

            // Handle product selection in Add Item modal

            $('#product_select').on('change', function() {

                const selectedOption = $(this).find('option:selected');

                const productId = selectedOption.val();

                

                // Reset warning and remove any previous event listeners

                $('#quantity_warning').hide();

                $('#quantity').off('input');

                

                if (productId) {

                    // Get data attributes from the selected option

                    const type = selectedOption.data('type');

                    const description = selectedOption.data('description');

                    const price = selectedOption.data('price');

                    const availableQuantity = selectedOption.data('quantity');

                    const productName = selectedOption.text().trim().split(' - ')[0]; // Extract product name from option text

                    

                    // Update form fields with selected product data

                    $('#item_type').val(type);

                    $('#description').val(productName); // Use product name as description instead of description text

                    $('#unit_price').val(price);

                    $('#product_id').val(productId);

                    

                    // If it's a part, check inventory quantity

                    if (type === 'Part' && availableQuantity !== undefined) {

                        // Add a single event listener for quantity input

                        $('#quantity').on('input', function() {

                            if (parseFloat($(this).val()) > parseFloat(availableQuantity)) {

                                $('#quantity_warning').show();

                            } else {

                                $('#quantity_warning').hide();

                            }

                        });

                        

                        // Initial check

                        if (parseFloat($('#quantity').val()) > parseFloat(availableQuantity)) {

                            $('#quantity_warning').show();

                        }

                    } else {

                        $('#quantity_warning').hide();

                    }

                } else {

                    // Clear fields if no product selected

                    $('#product_id').val('');

                    // Don't clear description and unit_price to allow manual entry

                }

            });

            

            // Handle item type change

            $('#item_type').on('change', function() {

                const selectedType = $(this).val();

                

                // Reset product selection when type changes

                $('#product_select').val('').trigger('change');

                $('#product_id').val('');

                $('#quantity_warning').hide();

                $('#quantity').off('input');

            });            // Helper function to extract jobCardId from URL if needed

            function getJobCardIdFromUrl() {

                // Extract job_card_id from URL if available

                const urlParams = new URLSearchParams(window.location.search);

                const viewParam = urlParams.get('view');

                if (viewParam && !isNaN(parseInt(viewParam))) {

                    return parseInt(viewParam);

                }

                return 0;

            }

            

            // AJAX form handling for Add Item

            $('#addItemButton').on('click', function() {

                // Get item type for validation

                const itemType = $('#item_type').val();

                const description = $('#description').val();

                const quantity = $('#quantity').val();

                const unitPrice = $('#unit_price').val();

                

                // Try multiple sources to get the job card ID

                let jobCardId = $('#add_job_card_id').val() || 

                                $('input[name="job_card_id"]').val() || 

                                <?= $jobDetails['id'] ?? 0 ?> || 

                                getJobCardIdFromUrl();

                

                // Log for debugging

                console.log('Job Card ID:', jobCardId, 'Element value:', $('#add_job_card_id').val(), 'Current view ID:', <?= $jobDetails['id'] ?? 0 ?>);

                

                // Check required fields

                if (!itemType) {

                    alert('Please select an item type');

                    $('#item_type').focus();

                    return;

                }

                if (!description) {

                    alert('Please enter a description');

                    $('#description').focus();

                    return;

                }

                if (!quantity) {

                    alert('Please enter a quantity');

                    $('#quantity').focus();

                    return;

                }

                if (!unitPrice) {

                    alert('Please enter a unit price');

                    $('#unit_price').focus();

                    return;

                }

                if (!jobCardId) {

                    alert('Missing job card ID. Please reload the page and try again.');

                    console.error('Job card ID is missing in the form:', {

                        'fromFormSelector': $('#addItemForm input[name="job_card_id"]').val(),

                        'fromGenericSelector': $('input[name="job_card_id"]').val(),

                        'numberOfInputs': $('input[name="job_card_id"]').length,

                        'jobCardInputValue': document.querySelector(formId + ' input[name="job_card_id"]')?.value,

                        'formExists': $('#addItemForm').length > 0

                    });

                    return;

                }

                

                // Create form data object explicitly

                const formData = {

                    action: 'add_item',

                    job_card_id: jobCardId,

                    item_type: itemType,

                    description: description,

                    quantity: quantity,

                    unit_price: unitPrice,

                    product_id: $('#product_id').val() || ''

                };

                

                // Log data being sent

                console.log('Submitting form data:', formData);

                

                // Send AJAX request

                $.ajax({

                    url: 'job_cards.php',

                    type: 'POST',

                    data: formData,

                    dataType: 'json',

                    success: function(response) {

                        if (response.success) {

                            console.log('Item added successfully:', response);

                            

                            // First hide the modal properly

                            cleanupModal('#addItemModal');

                            

                            // Reset form

                            $('#addItemForm')[0].reset();

                            $('#product_select').val('').trigger('change');

                            

                            // Reload items list without refreshing entire page

                            reloadJobItems(response.job_card_id);

                            

                            // Show success message

                            const alertHtml = `<div class="alert alert-success alert-dismissible fade show">

                                ${response.message}

                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                            </div>`;

                            $('.tab-content').before(alertHtml);

                        } else {

                            // Use our custom error handler

                            handleFormSubmissionError(response, '#addItemForm');

                        }

                    },

                    error: function(xhr, status, error) {

                        console.error('AJAX error:', status, error);

                        console.log('Response text:', xhr.responseText);

                        alert('Error processing request. Please try again.');

                    }

                });

            });            // AJAX form handling for Update Item

            $('#updateItemButton').on('click', function() {

                // Validate required fields first

                const itemType = $('#edit_item_type').val();

                const description = $('#edit_description').val();

                const quantity = $('#edit_quantity').val();

                const unitPrice = $('#edit_unit_price').val();

                

                if (!itemType) {

                    alert('Please select an item type');

                    $('#edit_item_type').focus();

                    return;

                }

                if (!description) {

                    alert('Please enter a description');

                    $('#edit_description').focus();

                    return;

                }

                if (!quantity) {

                    alert('Please enter a quantity');

                    $('#edit_quantity').focus();

                    return;

                }

                if (!unitPrice) {

                    alert('Please enter a unit price');

                    $('#edit_unit_price').focus();

                    return;

                }

                

                // Get form data

                const formData = $('#editItemForm').serialize();

                

                // Send AJAX request

                $.ajax({

                    url: 'job_cards.php',

                    type: 'POST',

                    data: formData,

                    dataType: 'json',

                    success: function(response) {

                        if (response.success) {                            // Use the cleanup utility to properly hide the modal

                            cleanupModal('#editItemModal');

                            

                            // Reload items list without refreshing entire page

                            reloadJobItems(response.job_card_id);

                            

                            // Show success message

                            const alertHtml = `<div class="alert alert-success alert-dismissible fade show">

                                ${response.message}

                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                            </div>`;

                            $('.tab-content').before(alertHtml);

                        } else {

                            alert('Error: ' + response.message);

                        }

                    },

                    error: function() {

                        alert('Error processing request. Please try again.');

                    }

                });

            });            // AJAX handling for Delete Item

            $('#deleteItemButton').on('click', function() {

                const job_card_id = <?= $jobDetails['id'] ?? 0 ?>;

                const job_item_id = $('#deleteItemId').val();

                

                console.log('Delete item - job_card_id:', job_card_id, 'job_item_id:', job_item_id);

                

                if (!job_item_id) {

                    alert('No item selected for deletion');

                    return;

                }

                

                if (!job_card_id) {

                    alert('Missing job card ID');

                    return;

                }

                

                // Send AJAX request

                $.ajax({

                    url: 'job_cards.php',

                    type: 'POST',

                    data: {

                        action: 'delete_item',

                        job_card_id: job_card_id,

                        job_item_id: job_item_id

                    },

                    dataType: 'json',

                    success: function(response) {

                        if (response.success) {                            // Use the cleanup utility to properly hide the modal

                            cleanupModal('#deleteItemModal');

                            

                            // Reload items list without refreshing entire page

                            reloadJobItems(response.job_card_id);

                        } else {

                            alert('Error: ' + response.message);

                        }

                    },

                    error: function() {

                        alert('Error processing request. Please try again.');

                    }

                });

            });



            // Function to reload job items without page refresh

            function reloadJobItems(job_card_id) {

                $.ajax({

                    url: 'get_job_items_ajax.php',

                    type: 'GET',

                    data: { job_card_id: job_card_id },

                    dataType: 'json',

                    success: function(response) {

                        if (response.success) {

                            // Update the items table with new data

                            const items = response.items;

                            const total = response.total;

                            

                            let tableHtml = '';

                            if (items.length > 0) {

                                tableHtml = `

                                <div class="table-responsive">

                                    <table class="table table-striped">

                                        <thead>

                                            <tr>

                                                <th>Type</th>

                                                <th>Description</th>

                                                <th>Quantity</th>

                                                <th>Unit Price</th>

                                                <th>Total</th>

                                                ${response.is_invoiced !== 1 ? '<th>Actions</th>' : ''}

                                            </tr>

                                        </thead>

                                        <tbody>`;

                                

                                items.forEach(function(item) {

                                    let badgeClass = 'bg-secondary';

                                    if (item.item_type === 'Part') {

                                        badgeClass = 'bg-primary';

                                    } else if (item.item_type === 'Service') {

                                        badgeClass = 'bg-info';

                                    }

                                    

                                    tableHtml += `

                                        <tr>

                                            <td>

                                                <span class="badge ${badgeClass}">${item.item_type}</span>

                                            </td>

                                            <td>${item.description}</td>

                                            <td>${item.quantity}</td>

                                            <td>LKR ${parseFloat(item.unit_price).toFixed(2)}</td>

                                            <td>LKR ${parseFloat(item.total_price).toFixed(2)}</td>

                                            ${response.is_invoiced !== 1 ? `<td><div class="btn-group btn-group-sm"><button type="button" class="btn btn-primary edit-item" data-id="${item.id}" data-job-id="${job_card_id}"><i class="fas fa-edit"></i></button><button type="button" class="btn btn-danger delete-item" data-id="${item.id}" data-desc="${item.description}"><i class="fas fa-trash"></i></button></div></td>` : ''}

                                        </tr>`;

                                });

                                

                                tableHtml += `

                                        <tr>

                                            <td colspan="4" class="text-end"><strong>Total:</strong></td>

                                            <td><strong>LKR ${parseFloat(total).toFixed(2)}</strong></td>

                                            ${response.is_invoiced !== 1 ? '<td></td>' : ''}

                                        </tr>

                                    </tbody>

                                </table>

                            </div>`;

                            } else {

                                tableHtml = `

                                <div class="alert alert-info">

                                    No items or services added to this job card yet.

                                </div>`;

                            }

                            

                            // Replace the current items container with the new HTML

                            $('#items').html(`

                                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">

                                    <h4>Items & Services</h4>

                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">

                                        <i class="fas fa-plus me-1"></i> Add Item/Service

                                    </button>

                                </div>

                                ${tableHtml}

                            `);

                            

                            // Reattach event handlers to the new elements

                            $('.edit-item').on('click', function() {

                                const id = $(this).data('id');

                                const jobCardId = $(this).data('job-id');

                                

                                // Fetch job item details via AJAX

                                $.ajax({

                                    url: 'get_job_item_ajax.php',

                                    type: 'GET',

                                    data: { id: id },

                                    dataType: 'json',

                                    success: function(response) {

                                        if (response.success) {

                                            const jobItem = response.job_item;

                                            

                                            // Populate the form fields

                                            $('#edit_job_item_id').val(jobItem.id);

                                            $('#edit_item_type').val(jobItem.itemType);

                                            $('#edit_description').val(jobItem.description);

                                            $('#edit_quantity').val(jobItem.quantity);

                                            $('#edit_original_quantity').val(jobItem.quantity);

                                            $('#edit_unit_price').val(jobItem.unitPrice);

                                            $('#edit_product_id').val(jobItem.productId || '');

                                            

                                            // If product is linked, show product details

                                            if (jobItem.productId && jobItem.productDetails) {

                                                const product = jobItem.productDetails;

                                                $('#edit_product_details').text(`${product.name} (${product.item_type})`);

                                                $('#edit_product_info').show();

                                                

                                                // For parts, monitor quantity for inventory checks

                                                if (jobItem.itemType.toLowerCase() === 'part') {

                                                    const availableStock = parseFloat(product.quantity) + parseFloat(jobItem.quantity);

                                                    $('#edit_inventory_status').text(`Current Stock: ${availableStock}`);

                                                    

                                                    // Setup quantity change event

                                                    $('#edit_quantity').off('input').on('input', function() {

                                                        const newQty = parseFloat($(this).val()) || 0;

                                                        const originalQty = parseFloat(jobItem.quantity) || 0;

                                                        

                                                        // Only warn if increasing quantity beyond available stock

                                                        if (newQty > originalQty && (newQty - originalQty) > availableStock) {

                                                            $('#edit_quantity_warning').show();

                                                        } else {

                                                            $('#edit_quantity_warning').hide();

                                                        }

                                                    });

                                                } else {

                                                    $('#edit_inventory_status').text('');

                                                    $('#edit_quantity_warning').hide();

                                                }

                                            } else {

                                                $('#edit_product_details').text('No product linked');

                                                $('#edit_inventory_status').text('');

                                                $('#edit_quantity_warning').hide();

                                            }

                                            

                                            // Show the modal

                                            $('#editItemModal').modal('show');

                                        } else {

                                            alert('Failed to load job item details: ' + response.message);

                                        }

                                    },

                                    error: function() {

                                        alert('Error loading job item details. Please try again.');

                                    }

                                });

                            });

                            

                            $('.delete-item').on('click', function() {

                                const id = $(this).data('id');

                                const desc = $(this).data('desc');

                                

                                $('#deleteItemId').val(id);

                                $('#itemDesc').text(desc);

                                $('#deleteItemModal').modal('show');

                            });

                            

                            // Update summary information if it exists

                            if ($('.job-detail-header').length) {

                                // Update total amount in header

                                $('.job-detail-header .col-md-3.col-6.mb-3:nth-child(3) strong').text('LKR ' + parseFloat(total).toFixed(2));

                                

                                // Update number of items

                                $('.job-detail-header .col-md-3.col-6.mb-3:nth-child(4) strong').text(items.length);

                            }

                            

                            // Show success message

                            if (!$('.alert-success').length) {

                                $('.tab-content').before(`

                                    <div class="alert alert-success alert-dismissible fade show">

                                        Operation completed successfully.

                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                                    </div>

                                `);

                                

                                // Auto-dismiss the alert after 3 seconds

                                setTimeout(function() {

                                    $('.alert-success').fadeOut('slow', function() {

                                        $(this).remove();

                                    });

                                }, 3000);

                            }



                        } else {

                            alert('Error loading job items: ' + response.message);

                        }

                    },

                    error: function() {

                        alert('Error loading job items. Please try again.');

                    }                });

            }                  // Improved error handling function for form submissions

            function handleFormSubmissionError(response, formId) {

                console.error('Error response from server:', response);

                

                // If there's debug info, log it to console

                if (response.debug) {

                    console.log('Debug info from server:', response.debug);

                }

                

                // Show specific message for job_card_id errors

                if (response.message && response.message.includes('job_card_id')) {

                    alert('Error: Job card ID is missing. Please reload the page and try again.');

                    

                    // Log detailed diagnostic info

                    console.error('Job card ID issue detected. Form values:', {

                        'fromFormSelector': $(formId + ' input[name="job_card_id"]').val(),

                        'fromGenericSelector': $('input[name="job_card_id"]').val(),

                        'numberOfInputs': $('input[name="job_card_id"]').length,

                        'jobCardInputValue': document.querySelector(formId + ' input[name="job_card_id"]')?.value,

                        'formExists': $(formId).length > 0

                    });

                } else {

                    alert('Error: ' + response.message);

                }

            }

              // Global modal cleanup utility function

            function cleanupModal(modalId) {

                const modal = $(modalId);

                

                // Hide the modal with Bootstrap's method first

                modal.modal('hide');

                

                // Ensure modal is hidden

                modal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');

                

                // Remove backdrop

                $('.modal-backdrop').remove();

                

                // Reset body styles and classes

                $('body').removeClass('modal-open').css('padding-right', '').css('overflow', '');

                

                // Restore scrolling explicitly

                document.body.style.overflow = '';

                document.body.style.paddingRight = '';

                

                // Fix any z-index stacking issues and ensure cleanup after animation ends

                setTimeout(function() {

                    if ($('.modal.show').length === 0) {

                        // Make sure all modals that aren't showing are properly hidden

                        $('.modal:not(.show)').css('display', 'none');

                        

                        // Double-check backdrop removal

                        $('.modal-backdrop').remove();

                        

                        // Ensure all modal-related classes and styles are removed from body

                        $('body').removeClass('modal-open').css('padding-right', '');

                    }

                }, 300);

            }

              // Handle modal hidden event for all modals to ensure backdrop cleanup

            $('.modal').on('hidden.bs.modal', function () {

                if ($('.modal:visible').length) {

                    // If another modal is still open, keep body modal-open

                    $('body').addClass('modal-open');

                } else {

                    // Otherwise, make sure backdrop is removed and body is cleaned up

                    $('.modal-backdrop').remove();

                    $('body').removeClass('modal-open').css('padding-right', '');

                }

            });

              // Handle all modal cancel/close buttons to ensure proper cleanup

            $('button[data-bs-dismiss="modal"]').on('click', function() {

                // Get the closest modal to this button

                const modal = $(this).closest('.modal');

                

                // Use timeout to let Bootstrap's dismissal happen first

                setTimeout(function() {

                    cleanupModal('#' + modal.attr('id'));

                }, 100);

            });

            

            // Global handler for any modal that gets hidden for any reason

            // (escape key, clicking outside, programmatic hide)

            $(document).on('hidden.bs.modal', '.modal', function() {

                // Make sure body doesn't have modal-open class when all modals are closed

                if ($('.modal.show').length === 0) {

                    $('body').removeClass('modal-open');

                    $('.modal-backdrop').remove();

                    $('body').css('padding-right', '');

                }

            });



            // Ensure job_card_id is set correctly when the add item modal is opened

            $('#addItemModal').on('show.bs.modal', function (e) {

                // Get job card ID from various sources

                const jobCardId = <?= $jobDetails['id'] ?? 0 ?> || getJobCardIdFromUrl();

                console.log('Modal opening with job card ID:', jobCardId);

                

                // Set the job card ID in the form

                $('#add_job_card_id').val(jobCardId);

                

                // Double check it's been set

                setTimeout(function() {

                    const checkId = $('#add_job_card_id').val();

                    console.log('Job card ID after setting:', checkId);

                    

                    if (!checkId && jobCardId) {

                        // If still not set but we have a value, create the field if needed

                        if (!$('#add_job_card_id').length) {

                            $('#addItemForm').prepend(`<input type="hidden" id="add_job_card_id" name="job_card_id" value="${jobCardId}">`);

                            console.log('Created missing job_card_id field with value:', jobCardId);

                        } else {

                            $('#add_job_card_id').val(jobCardId);

                        }

                    }

                }, 100);

            });

        });

    </script>

<?php include_once '../includes/footer.php'; ?>

    

    <script>

        $(document).ready(function() {

            // Utility function to properly clean up modals

            function cleanupModal(modalId) {

                const modal = $(modalId);

                

                // Hide modal with Bootstrap's method

                modal.modal('hide');

                

                // Ensure modal is hidden

                modal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');

                

                // Remove backdrop and fix body styling

                $('.modal-backdrop').remove();

                $('body').removeClass('modal-open').css('padding-right', '').css('overflow', '');

                document.body.style.overflow = '';

                document.body.style.paddingRight = '';

            }

            

            // Function to handle form submission errors

            function handleFormSubmissionError(response, formId) {

                console.error('Error response from server:', response);

                

                if (response.debug) {

                    console.log('Debug info from server:', response.debug);

                }

                

                if (response.message && response.message.includes('job_card_id')) {

                    alert('Error: Job card ID is missing. Please reload the page and try again.');

                    console.error('Job card ID issue detected. Form values:', {

                        'form_id': formId,

                        'form_exists': $(formId).length > 0,

                        'job_card_id_field': $(formId + ' input[name="job_card_id"]').length > 0,

                        'job_card_id_value': $(formId + ' input[name="job_card_id"]').val(),

                        'add_job_card_id_value': $('#add_job_card_id').val(),

                        'url_job_card_id': getJobCardIdFromUrl()

                    });

                } else {

                    alert('Error: ' + response.message);

                }

            }

        });

    </script>

</body>

</html>