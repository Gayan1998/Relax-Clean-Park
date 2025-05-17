<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../includes/db_connection.php';

$error = "";
$success = "";

// Handle form submissions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle delete customer
        if ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $_SESSION['message'] = 'Customer deleted successfully!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        
        // Handle add new customer
        else if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);

            // Basic validation
            if (empty($name)) {
                $error = "Name is required";
            } else {
                try {
                    // Check if customer with the same email or phone already exists
                    // Only check when either email or phone is provided
                    if (!empty($email) || !empty($phone)) {
                        $query = "SELECT COUNT(*) FROM customers WHERE ";
                        $params = [];
                        
                        if (!empty($email)) {
                            $query .= "email = ?";
                            $params[] = $email;
                            
                            if (!empty($phone)) {
                                $query .= " OR phone = ?";
                                $params[] = $phone;
                            }
                        } else if (!empty($phone)) {
                            $query .= "phone = ?";
                            $params[] = $phone;
                        }
                        
                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        $exists = $stmt->fetchColumn() > 0;

                        if ($exists) {
                            $error = "A customer with this email or phone already exists";
                        }
                    }
                    
                    if (empty($error)) {
                        // Insert the new customer - use NULL for empty email instead of empty string
                        $email = empty($email) ? null : $email;
                        $phone = empty($phone) ? null : $phone;
                        $address = empty($address) ? null : $address;
                        
                        $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, NOW(), NOW())");
                        $stmt->execute([$name, $email, $phone, $address]);
                        
                        $_SESSION['message'] = "Customer added successfully!";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        
        // Handle edit existing customer
        else if ($_POST['action'] === 'edit') {
            $customer_id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);

            // Basic validation
            if (empty($name)) {
                $error = "Name is required";
            } else {
                try {
                    // Check if customer with the same email or phone already exists, excluding current customer
                    // Only check when either email or phone is provided
                    if (!empty($email) || !empty($phone)) {
                        $query = "SELECT COUNT(*) FROM customers WHERE (";
                        $params = [];
                        
                        if (!empty($email)) {
                            $query .= "email = ?";
                            $params[] = $email;
                            
                            if (!empty($phone)) {
                                $query .= " OR phone = ?";
                                $params[] = $phone;
                            }
                        } else if (!empty($phone)) {
                            $query .= "phone = ?";
                            $params[] = $phone;
                        }
                        
                        $query .= ") AND id != ?";
                        $params[] = $customer_id;
                        
                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        $exists = $stmt->fetchColumn() > 0;

                        if ($exists) {
                            $error = "A customer with this email or phone already exists";
                        }
                    }
                    
                    if (empty($error)) {
                        // Update the customer - use NULL for empty email instead of empty string
                        $email = empty($email) ? null : $email;
                        $phone = empty($phone) ? null : $phone;
                        $address = empty($address) ? null : $address;
                        
                        $stmt = $pdo->prepare("UPDATE customers 
                                             SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() 
                                             WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $address, $customer_id]);
                        
                        $_SESSION['message'] = "Customer updated successfully!";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch customers for display
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT * FROM customers WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch single customer for edit
$edit_customer = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_customer) {
        $_SESSION['error'] = "Customer not found!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        /* Additional customer management specific styles */

        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .form-control, .form-select {
            background-color: var(--darker-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--darker-bg);
            border-color: var(--accent-blue);
            color: var(--text-primary);
            box-shadow: none;
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

        .btn-edit {
            color: var(--accent-blue);
            background: transparent;
            border: 1px solid var(--accent-blue);
        }

        .btn-edit:hover {
            background: var(--accent-blue);
            color: var(--darker-bg);
        }

        .message {
            background-color: var(--accent-green);
            color: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .error {
            background-color: var(--accent-red);
            color: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .form-label {
            color: var(--text-secondary);
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
        
        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        .btn-close {
            background-color: var(--accent-red);
            opacity: 1;
            color: var(--dark-bg);
            border-radius: 50%;
            padding: 0.5rem;
            font-size: 0.75rem;
        }
        
        .optional-field {
            color: var(--accent-blue);
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php 
        include_once '../includes/functions.php';
        $business = get_business_settings();
        $page_title = "Customer Management";
        include '../includes/vehicle_service_header.php'; 
        ?>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div></div> <!-- Spacer -->
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                        <i class="fas fa-plus"></i> Add New Customer
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="message"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control" name="search" 
                              value="<?php echo htmlspecialchars($search); ?>" 
                              placeholder="Search by name, email or phone...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo $customer['id']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($customer['address'] ?? ''); ?></td>
                                    <td>
                                        <button class="btn btn-edit btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCustomerModal" 
                                                data-customer='<?php echo json_encode($customer); ?>'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="confirmDelete(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No customers found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCustomerForm" method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="optional-field">(Optional)</span></label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone <span class="optional-field">(Optional)</span></label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="optional-field">(Optional)</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addCustomerForm" class="btn btn-primary">Add Customer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCustomerModalLabel">Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCustomerForm" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email <span class="optional-field">(Optional)</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone <span class="optional-field">(Optional)</span></label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address <span class="optional-field">(Optional)</span></label>
                            <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editCustomerForm" class="btn btn-primary">Update Customer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle Edit Customer
        document.getElementById('editCustomerModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const customerData = JSON.parse(button.getAttribute('data-customer'));
            
            document.getElementById('edit_id').value = customerData.id;
            document.getElementById('edit_name').value = customerData.name;
            document.getElementById('edit_email').value = customerData.email || '';
            document.getElementById('edit_phone').value = customerData.phone || '';
            document.getElementById('edit_address').value = customerData.address || '';
        });
        
        // Handle Delete Customer
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete customer "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;

                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Show Add Modal if there was an error
        <?php if (!empty($error) && isset($_POST['action']) && $_POST['action'] === 'add'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const addModal = new bootstrap.Modal(document.getElementById('addCustomerModal'));
            addModal.show();
            
            // Populate the form with the values that were submitted
            document.getElementById('name').value = "<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>";
            document.getElementById('email').value = "<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>";
            document.getElementById('phone').value = "<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>";
            document.getElementById('address').value = "<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>";
        });
        <?php endif; ?>
        
        // Show Edit Modal if there was an error
        <?php if (!empty($error) && isset($_POST['action']) && $_POST['action'] === 'edit'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
            editModal.show();
            
            // Populate the form with the values that were submitted
            document.getElementById('edit_id').value = "<?php echo htmlspecialchars($_POST['id'] ?? ''); ?>";
            document.getElementById('edit_name').value = "<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>";
            document.getElementById('edit_email').value = "<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>";
            document.getElementById('edit_phone').value = "<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>";
            document.getElementById('edit_address').value = "<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>";
        });
        <?php endif; ?>
    </script>
</body>
</html>