<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../includes/db_connection.php';
include_once '../includes/functions.php';

$business = get_business_settings();
// Handle form submission for adding stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_stock') {
        // First get the current quantity
        $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        $current_qty = $stmt->fetchColumn();
        
        // Calculate new quantity
        $new_qty = $current_qty + $_POST['quantity'];
        
        // Update the product quantity
        $stmt = $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");
        $stmt->execute([$new_qty, $_POST['product_id']]);
        
        // Record the stock addition in stock_history table
        $stmt = $pdo->prepare("INSERT INTO stock_history (product_id, quantity_added, date_added, notes) 
                             VALUES (?, ?, NOW(), ?)");
        $stmt->execute([
            $_POST['product_id'],
            $_POST['quantity'],
            $_POST['notes']
        ]);
        
        $_SESSION['message'] = 'Stock updated successfully!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch all products
$stmt = $pdo->query("SELECT id, name, category, quantity FROM products ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent stock history
$stmt = $pdo->query("SELECT sh.*, p.name as product_name 
                     FROM stock_history sh 
                     JOIN products p ON sh.product_id = p.id 
                     ORDER BY date_added DESC 
                     LIMIT 10");
$stock_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GRN</title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
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

        .message {
            background-color: var(--accent-green);
            color: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .form-label {
            color: var(--text-secondary);
        }

        .history-container {
            max-height: 400px;
            overflow-y: auto;
        }

        /* Select2 Dark Theme Customization */
        .select2-container--default .select2-selection--single {
            background-color: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            height: 38px;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-primary);
            line-height: 38px;
            padding-left: 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .select2-dropdown {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: var(--darker-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .select2-container--default .select2-results__option {
            color: var(--text-primary);
            padding: 8px 12px;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--accent-blue);
            color: white;
        }

        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: var(--darker-bg);
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
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Add Stock Form -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0 text-white">Add New Stock</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_stock">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product_id" class="form-label">Select Product</label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">Choose a product...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> 
                                        (<?= htmlspecialchars($product['category']) ?>) - 
                                        Current Stock: <?= $product['quantity'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Quantity to Add</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                placeholder="Enter any notes about this stock addition..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Stock</button>
                </form>
            </div>
        </div>

        <!-- Stock History -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0 text-white">Recent Stock Updates</h4>
            </div>
            <div class="card-body">
                <div class="history-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Quantity Added</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stock_history as $history): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i', strtotime($history['date_added'])) ?></td>
                                <td><?= htmlspecialchars($history['product_name']) ?></td>
                                <td><?= $history['quantity_added'] ?></td>
                                <td><?= htmlspecialchars($history['notes']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#product_id').select2({
                theme: 'default',
                placeholder: 'Search for a product...',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</body>
</html>