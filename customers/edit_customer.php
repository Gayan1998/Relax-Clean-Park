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

// Verify we have an ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_customers.php");
    exit();
}

$customer_id = (int)$_GET['id'];

// Fetch customer data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['message'] = "Customer not found!";
    header("Location: view_customers.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE (email = ? OR phone = ?) AND id != ?");
            $stmt->execute([$email, $phone, $customer_id]);
            $exists = $stmt->fetchColumn() > 0;

            if ($exists && !empty($email) && !empty($phone)) {
                $error = "A customer with this email or phone already exists";
            } else {
                // Update the customer
                $stmt = $pdo->prepare("UPDATE customers 
                                     SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() 
                                     WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $address, $customer_id]);
                
                $_SESSION['message'] = "Customer updated successfully!";
                header("Location: view_customers.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --dark-bg: #1a1a1a;
            --darker-bg: #141414;
            --card-bg: #242424;
            --border-color: #333;
            --text-primary: #fff;
            --text-secondary: #a0a0a0;
            --accent-blue: #60a5fa;
            --accent-green: #4ade80;
            --accent-red: #f87171;
        }

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

        .form-label {
            color: var(--text-secondary);
        }

        .btn-primary {
            background-color: var(--accent-blue);
            border: none;
        }

        .btn-secondary {
            background-color: var(--border-color);
            border: none;
        }

        .alert-danger {
            background-color: rgba(220, 38, 38, 0.2);
            color: var(--accent-red);
            border-color: rgba(220, 38, 38, 0.3);
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
            border-color: rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Edit Customer</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Customer Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($customer['name']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($customer['email']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($customer['phone']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="view_customers.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Customer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>