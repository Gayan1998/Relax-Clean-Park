<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
session_start();

$business = get_business_settings();
// Check if there's a form submission for adding/updating vehicles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Handle vehicle creation
    if ($action === 'create') {
        // Process form data for new vehicle
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_SANITIZE_NUMBER_INT);
        $registration_number = filter_input(INPUT_POST, 'registration_number', FILTER_SANITIZE_STRING);
        $make = filter_input(INPUT_POST, 'make', FILTER_SANITIZE_STRING);
        $model = filter_input(INPUT_POST, 'model', FILTER_SANITIZE_STRING);
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_STRING);
        $vin = filter_input(INPUT_POST, 'vin', FILTER_SANITIZE_STRING);
        $current_mileage = filter_input(INPUT_POST, 'current_mileage', FILTER_SANITIZE_NUMBER_INT);
        
        try {
            // Check if registration number already exists
            $check = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number = ?");
            $check->execute([$registration_number]);
            if ($check->fetch()) {
                $_SESSION['error'] = "A vehicle with this registration number already exists.";
                header("Location: vehicles.php");
                exit();
            }
            
            // Insert vehicle
            $stmt = $pdo->prepare("INSERT INTO vehicles (customer_id, registration_number, make, model, year, color, 
                                vin, current_mileage) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([$customer_id, $registration_number, $make, $model, $year, $color, 
                           $vin, $current_mileage]);
            
            $_SESSION['message'] = "Vehicle added successfully.";
            header("Location: vehicles.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to add vehicle: " . $e->getMessage();
        }
    }
    
    // Handle vehicle update
    else if ($action === 'update') {
        // Process form data for updating vehicle
        $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_SANITIZE_NUMBER_INT);
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_SANITIZE_NUMBER_INT);
        $registration_number = filter_input(INPUT_POST, 'registration_number', FILTER_SANITIZE_STRING);
        $make = filter_input(INPUT_POST, 'make', FILTER_SANITIZE_STRING);
        $model = filter_input(INPUT_POST, 'model', FILTER_SANITIZE_STRING);
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_STRING);
        $vin = filter_input(INPUT_POST, 'vin', FILTER_SANITIZE_STRING);
        $current_mileage = filter_input(INPUT_POST, 'current_mileage', FILTER_SANITIZE_NUMBER_INT);
        
        try {
            // Check if registration number already exists for other vehicles
            $check = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number = ? AND id != ?");
            $check->execute([$registration_number, $vehicle_id]);
            if ($check->fetch()) {
                $_SESSION['error'] = "Another vehicle with this registration number already exists.";
                header("Location: vehicles.php");
                exit();
            }
            
            // Update vehicle
            $stmt = $pdo->prepare("UPDATE vehicles SET customer_id = ?, registration_number = ?, 
                                make = ?, model = ?, year = ?, color = ?, vin = ?, 
                                current_mileage = ? 
                                WHERE id = ?");
            
            $stmt->execute([$customer_id, $registration_number, $make, $model, $year, $color, 
                           $vin, $current_mileage, $vehicle_id]);
            
            $_SESSION['message'] = "Vehicle updated successfully.";
            header("Location: vehicles.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to update vehicle: " . $e->getMessage();
        }
    }
    
    // Handle vehicle deletion
    else if ($action === 'delete') {
        $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_SANITIZE_NUMBER_INT);
        
        try {
            // Check if vehicle is linked to any job cards
            $check = $pdo->prepare("SELECT id FROM job_cards WHERE vehicle_id = ?");
            $check->execute([$vehicle_id]);
            if ($check->fetch()) {
                $_SESSION['error'] = "Cannot delete this vehicle because it is linked to one or more job cards.";
                header("Location: vehicles.php");
                exit();
            }
            
            // Delete vehicle
            $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
            $stmt->execute([$vehicle_id]);
            
            $_SESSION['message'] = "Vehicle deleted successfully.";
            header("Location: vehicles.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to delete vehicle: " . $e->getMessage();
        }
    }
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$customer_id = isset($_GET['customer_id']) ? filter_input(INPUT_GET, 'customer_id', FILTER_SANITIZE_NUMBER_INT) : '';

// Get vehicles
try {
    $query = "SELECT v.*, c.name as customer_name 
              FROM vehicles v
              LEFT JOIN customers c ON v.customer_id = c.id
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (v.registration_number LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR c.name LIKE ?)";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    if (!empty($customer_id)) {
        $query .= " AND v.customer_id = ?";
        $params[] = $customer_id;
    }
    
    $query .= " ORDER BY v.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customers for filter dropdown
    $stmtCustomers = $pdo->query("SELECT id, name FROM customers ORDER BY name");
    $customers = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $vehicles = [];
    $customers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        /* Additional vehicle-specific styles */

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: var(--darker-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
        }        .form-control, .form-select {
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

        .card-title {
            color: var(--text-primary);
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
        
        .form-label {
            color: var(--text-primary);
            font-weight: 500;
        }
          /* Using standard Bootstrap dropdowns instead of Select2 */
        
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
    </style>
</head>
<body>
    <div class="container">
        <?php 
        $page_title = "Vehicle Management";
        include '../includes/vehicle_service_header.php'; 
        ?>
          <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div></div> <!-- Empty div to maintain flex spacing -->
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVehicleModal">
                        <i class="fas fa-plus"></i> Add New Vehicle
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
                            <div class="row">                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" name="search" id="search" class="form-control" 
                                               placeholder="Registration, Make, Model or Customer" 
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>                                <div class="col-md-5">
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
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Registration</th>
                                        <th>Make & Model</th>
                                        <th>Customer</th>
                                        <th>Year</th>
                                        <th>Mileage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vehicles)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No vehicles found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td><?= $vehicle['id'] ?></td>
                                                <td><?= htmlspecialchars($vehicle['registration_number']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) ?>
                                                    <?php if (!empty($vehicle['color'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($vehicle['color']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($vehicle['customer_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($vehicle['year'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?= $vehicle['current_mileage'] ? number_format($vehicle['current_mileage']) . ' km' : 'N/A' ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info view-vehicle" 
                                                            data-id="<?= $vehicle['id'] ?>" 
                                                            data-reg="<?= htmlspecialchars($vehicle['registration_number']) ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary edit-vehicle" 
                                                            data-id="<?= $vehicle['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-vehicle" 
                                                            data-id="<?= $vehicle['id'] ?>" 
                                                            data-reg="<?= htmlspecialchars($vehicle['registration_number']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Vehicle Modal -->
    <div class="modal fade" id="createVehicleModal" tabindex="-1" aria-labelledby="createVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="createVehicleModalLabel">Add New Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createVehicleForm" method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">                                    <label for="create_customer_id" class="form-label">Customer</label>
                                    <select name="customer_id" id="create_customer_id" class="form-select" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= $customer['id'] ?>">
                                                <?= htmlspecialchars($customer['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="create_registration_number" class="form-label">Registration Number</label>
                                    <input type="text" name="registration_number" id="create_registration_number" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="create_make" class="form-label">Make</label>
                                    <input type="text" name="make" id="create_make" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="create_model" class="form-label">Model</label>
                                    <input type="text" name="model" id="create_model" class="form-control" required>
                                </div>
                            </div>                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="create_year" class="form-label">Year (Optional)</label>
                                    <input type="number" name="year" id="create_year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="create_color" class="form-label">Color (Optional)</label>
                                    <input type="text" name="color" id="create_color" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="create_current_mileage" class="form-label">Current Mileage (km)</label>
                                    <input type="number" name="current_mileage" id="create_current_mileage" class="form-control" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="create_vin" class="form-label">VIN (Chassis Number) (Optional)</label>
                                    <input type="text" name="vin" id="create_vin" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Vehicle Modal -->
    <div class="modal fade" id="viewVehicleModal" tabindex="-1" aria-labelledby="viewVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewVehicleModalLabel">Vehicle Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewVehicleBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading vehicle details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnEditFromView">Edit</button>
                    <button type="button" class="btn btn-success" id="btnCreateJobCardFromView">Create Job Card</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editVehicleForm" method="POST" action="">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">                                    <label for="edit_customer_id" class="form-label">Customer</label>
                                    <select name="customer_id" id="edit_customer_id" class="form-select" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= $customer['id'] ?>">
                                                <?= htmlspecialchars($customer['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">                                    <label for="edit_registration_number" class="form-label">Registration Number</label>
                                    <input type="text" name="registration_number" id="edit_registration_number" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">                                    <label for="edit_make" class="form-label">Make</label>
                                    <input type="text" name="make" id="edit_make" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">                                    <label for="edit_model" class="form-label">Model</label>
                                    <input type="text" name="model" id="edit_model" class="form-control" required>
                                </div>
                            </div>                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_year" class="form-label">Year (Optional)</label>
                                    <input type="number" name="year" id="edit_year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">                                    <label for="edit_color" class="form-label">Color (Optional)</label>
                                    <input type="text" name="color" id="edit_color" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">                                    <label for="edit_current_mileage" class="form-label">Current Mileage (km)</label>
                                    <input type="number" name="current_mileage" id="edit_current_mileage" class="form-control" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">                                    <label for="edit_vin" class="form-label">VIN (Chassis Number) (Optional)</label>
                                    <input type="text" name="vin" id="edit_vin" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">                                    <label for="edit_vin" class="form-label">VIN (Chassis Number) (Optional)</label>
                                    <input type="text" name="vin" id="edit_vin" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the vehicle with registration number <span id="vehicleReg"></span>?
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="vehicle_id" id="deleteVehicleId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>    <script>
    $(document).ready(function() {
        // Standard Bootstrap dropdowns are being used
        // No Select2 initialization needed
        
        // Setup delete confirmation
        $('.delete-vehicle').click(function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const reg = $(this).data('reg');
            
            $('#deleteVehicleId').val(id);
            $('#vehicleReg').text(reg);
            $('#deleteModal').modal('show');
        });
        
        // View vehicle
        $('.view-vehicle').click(function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const reg = $(this).data('reg');
            
            $('#viewVehicleModalLabel').text('Vehicle: ' + reg);
            
            // Load vehicle details with AJAX
            $.ajax({
                url: 'get_vehicle.php',
                type: 'GET',
                data: { id: id },
                success: function(data) {
                    $('#viewVehicleBody').html(data);
                    $('#btnEditFromView').data('id', id);
                    $('#btnCreateJobCardFromView').data('id', id);
                    $('#viewVehicleModal').modal('show');
                },
                error: function() {
                    $('#viewVehicleBody').html('<div class="alert alert-danger">Failed to load vehicle details.</div>');
                }
            });
        });
        
        // Edit vehicle
        $('.edit-vehicle').click(function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            
            // Load vehicle details with AJAX
            $.ajax({
                url: 'get_vehicle_data.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    // Populate the edit form
                    $('#edit_vehicle_id').val(data.id);
                    $('#edit_customer_id').val(data.customer_id).trigger('change');
                    $('#edit_registration_number').val(data.registration_number);
                    $('#edit_make').val(data.make);
                    $('#edit_model').val(data.model);
                    $('#edit_year').val(data.year);
                    $('#edit_color').val(data.color);
                    $('#edit_vin').val(data.vin);
                    $('#edit_current_mileage').val(data.current_mileage);
                    
                    $('#editVehicleModal').modal('show');
                },
                error: function() {
                    alert('Failed to load vehicle details for editing.');
                }
            });
        });
        
        // Handle buttons in view modal
        $('#btnEditFromView').click(function() {
            const id = $(this).data('id');
            $('#viewVehicleModal').modal('hide');
            $('.edit-vehicle[data-id="' + id + '"]').click();
        });
        
        $('#btnCreateJobCardFromView').click(function() {
            const id = $(this).data('id');
            window.location.href = '../job_cards/create_job_card.php?vehicle_id=' + id;
        });
          // Set current year as default for new vehicle
        $('#create_year').val(new Date().getFullYear());
    });
    </script>

</body>
</html>