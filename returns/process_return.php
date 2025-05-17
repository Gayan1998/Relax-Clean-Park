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

// Check if sale ID is provided
if (!isset($_GET['sale_id'])) {
    $_SESSION['error'] = "Sale ID is required";
    header("Location: returns.php");
    exit();
}

$sale_id = intval($_GET['sale_id']);

// Fetch sale data
$sale_query = "SELECT s.*, 
               DATE_FORMAT(s.sale_date, '%Y-%m-%d') as formatted_date,
               c.name as customer_name, c.phone as customer_phone 
               FROM sales s 
               LEFT JOIN customers c ON s.customer_id = c.id
               WHERE s.id = :sale_id";
$stmt = $pdo->prepare($sale_query);
$stmt->execute(['sale_id' => $sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    $_SESSION['error'] = "Sale not found";
    header("Location: returns.php");
    exit();
}

// Fetch sale items
$items_query = "SELECT si.*, p.name as product_name, p.purchase_price 
                FROM sale_items si 
                JOIN products p ON si.product_id = p.id 
                WHERE si.sale_id = :sale_id";
$stmt = $pdo->prepare($items_query);
$stmt->execute(['sale_id' => $sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for existing returns for this sale
$existing_returns_query = "SELECT r.id, r.return_date, r.total_amount 
                          FROM returns r 
                          WHERE r.sale_id = :sale_id";
$stmt = $pdo->prepare($existing_returns_query);
$stmt->execute(['sale_id' => $sale_id]);
$existing_returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get returned quantities for each item
$returned_quantities = [];
if (!empty($existing_returns)) {
    $return_ids = array_column($existing_returns, 'id');
    $return_ids_str = implode(',', $return_ids);
    
    $returned_items_query = "SELECT ri.sale_item_id, SUM(ri.quantity) as total_returned 
                            FROM return_items ri 
                            WHERE ri.return_id IN ($return_ids_str) 
                            GROUP BY ri.sale_item_id";
    $stmt = $pdo->query($returned_items_query);
    $returned_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($returned_items as $item) {
        $returned_quantities[$item['sale_item_id']] = $item['total_returned'];
    }
}

// Process the return form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $return_items = isset($_POST['return_items']) ? $_POST['return_items'] : [];
    $return_quantities = isset($_POST['return_quantity']) ? $_POST['return_quantity'] : [];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate if any items are selected for return
    $has_items = false;
    foreach ($return_items as $item_id => $selected) {
        if ($selected && isset($return_quantities[$item_id]) && $return_quantities[$item_id] > 0) {
            $has_items = true;
            break;
        }
    }
    
    if (!$has_items) {
        $_SESSION['error'] = "Please select at least one item to return";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Create return record
            $total_refund = 0;
            $total_profit_reduction = 0; // NEW: Track profit reduction
            $return_date = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("INSERT INTO returns (sale_id, return_date, total_amount, notes, created_at, updated_at) 
                                 VALUES (:sale_id, :return_date, :total_amount, :notes, NOW(), NOW())");
            $stmt->execute([
                'sale_id' => $sale_id,
                'return_date' => $return_date,
                'total_amount' => 0, // Will update after calculating all items
                'notes' => $notes
            ]);
            
            $return_id = $pdo->lastInsertId();
            
            // Process each returned item
            foreach ($return_items as $item_id => $selected) {
                if ($selected && isset($return_quantities[$item_id]) && $return_quantities[$item_id] > 0) {
                    // Find the item details
                    $item_key = array_search($item_id, array_column($items, 'id'));
                    if ($item_key !== false) {
                        $item = $items[$item_key];
                        $return_quantity = intval($return_quantities[$item_id]);
                        
                        // Check if return quantity is valid
                        $already_returned = isset($returned_quantities[$item_id]) ? $returned_quantities[$item_id] : 0;
                        $max_returnable = $item['quantity'] - $already_returned;
                        
                        if ($return_quantity <= 0 || $return_quantity > $max_returnable) {
                            throw new Exception("Invalid return quantity for item: " . $item['product_name']);
                        }
                        
                        // Calculate item total
                        $item_total = $return_quantity * $item['price'];
                        $total_refund += $item_total;
                        
                        // NEW: Calculate profit reduction
                        $cost_price = $item['purchase_price'] ?? 0;
                        $item_profit = ($item['price'] - $cost_price) * $return_quantity;
                        $total_profit_reduction += $item_profit;
                        
                        // Insert return item
                        $stmt = $pdo->prepare("INSERT INTO return_items (return_id, product_id, sale_item_id, quantity, price, total_price, created_at, updated_at) 
                                             VALUES (:return_id, :product_id, :sale_item_id, :quantity, :price, :total_price, NOW(), NOW())");
                        $stmt->execute([
                            'return_id' => $return_id,
                            'product_id' => $item['product_id'],
                            'sale_item_id' => $item_id,
                            'quantity' => $return_quantity,
                            'price' => $item['price'],
                            'total_price' => $item_total
                        ]);
                        
                        // Update inventory (add back to stock)
                        $stmt = $pdo->prepare("UPDATE products SET quantity = quantity + :return_quantity WHERE id = :product_id");
                        $stmt->execute([
                            'return_quantity' => $return_quantity,
                            'product_id' => $item['product_id']
                        ]);
                    }
                }
            }
            
            // Update return record with total amount
            $stmt = $pdo->prepare("UPDATE returns SET total_amount = :total_amount WHERE id = :return_id");
            $stmt->execute([
                'total_amount' => $total_refund,
                'return_id' => $return_id
            ]);
            
            // NEW: Update the original sale profit
            $stmt = $pdo->prepare("UPDATE sales SET profit = profit - :profit_reduction WHERE id = :sale_id");
            $stmt->execute([
                'profit_reduction' => $total_profit_reduction,
                'sale_id' => $sale_id
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['message'] = "Return processed successfully. Refund amount: LKR " . number_format($total_refund, 2);
            
            // Redirect to the return receipt
            header("Location: return_receipt.php?return_id=" . $return_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing return: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Return - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --dark-bg: #1a1a1a;
            --darker-bg: #141414;
            --card-bg: #242424;
            --border-color: #333;
            --text-primary:rgb(255, 255, 255);
            --text-secondary:rgb(255, 255, 255);
            --accent-blue: #60a5fa;
            --accent-green: #4ade80;
            --accent-red: #f87171;
            --accent-yellow: #b6e134;
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

        .error {
            background-color: var(--accent-red);
            color: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        .btn-success {
            background-color: var(--accent-green);
            border-color: var(--accent-green);
            color: var(--darker-bg);
        }

        .btn-secondary {
            background-color: var(--border-color);
            border-color: var(--border-color);
        }

        .btn-danger {
            background-color: var(--accent-red);
            border-color: var(--accent-red);
            color: var(--darker-bg);
        }

        .form-label {
            color: var(--text-secondary);
        }

        .sale-info {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: var(--darker-bg);
            border-radius: 8px;
        }

        .sale-info p {
            margin-bottom: 0.5rem;
        }

        .quantity-input {
            width: 80px;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.5rem;
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

        /* Responsive styles */
        @media (max-width: 767px) {
            body {
                padding: 1rem;
            }
            
            .quantity-input {
                width: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Process Return</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Sale Information -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Sale Details</h4>
            </div>
            <div class="card-body">
                <div class="sale-info">
                    <div class="row">
                        <div class="col-md-6">
                            <p style="color: white;"><strong>Sale #:</strong> <?php echo $sale['id']; ?></p>
                            <p style="color: white;"><strong>Date:</strong> <?php echo $sale['formatted_date']; ?></p>
                            <p style="color: white;"><strong>Total Amount:</strong> LKR <?php echo number_format($sale['total_amount'], 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p style="color: white;"><strong>Customer:</strong> <?php echo $sale['customer_name'] ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer'; ?></p>
                            <?php if ($sale['customer_phone']): ?>
                            <p style="color: white;"><strong>Phone:</strong> <?php echo htmlspecialchars($sale['customer_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($existing_returns)): ?>
                <div class="alert alert-warning">
                    <strong>Note:</strong> This sale already has returns processed.
                    <ul class="mb-0 mt-2">
                        <?php foreach ($existing_returns as $ret): ?>
                        <li>Return #<?php echo $ret['id']; ?> on <?php echo date('Y-m-d', strtotime($ret['return_date'])); ?> - LKR <?php echo number_format($ret['total_amount'], 2); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Return Form -->
                <form method="POST" id="returnForm">
                    <input type="hidden" name="process_return" value="1">
                    
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Return</th>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Original Qty</th>
                                <th>Already Returned</th>
                                <th>Return Qty</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $already_returned = isset($returned_quantities[$item['id']]) ? $returned_quantities[$item['id']] : 0;
                                $max_returnable = $item['quantity'] - $already_returned;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input return-checkbox" 
                                        name="return_items[<?php echo $item['id']; ?>]" 
                                        value="1" 
                                        <?php echo $max_returnable <= 0 ? 'disabled' : ''; ?>>
                                </td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td>LKR <?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>
                                    <?php echo $already_returned; ?>
                                    <?php if ($already_returned > 0): ?>
                                    <span class="badge bg-info">Partial Return</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" class="form-control quantity-input" 
                                        name="return_quantity[<?php echo $item['id']; ?>]" 
                                        min="1" max="<?php echo $max_returnable; ?>" 
                                        value="<?php echo $max_returnable > 0 ? 1 : 0; ?>"
                                        <?php echo $max_returnable <= 0 ? 'disabled' : ''; ?>>
                                </td>
                                <td class="item-subtotal">
                                    LKR <?php echo $max_returnable > 0 ? number_format($item['price'], 2) : '0.00'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Total Refund:</strong></td>
                                <td id="totalRefund">LKR 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Return Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any notes about this return..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="returns.php" class="btn btn-secondary">Back to Returns</a>
                        <button type="submit" class="btn btn-danger" id="processReturnBtn">Process Return & Issue Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            updateTotals();
            
            // Handle checkbox changes
            $('.return-checkbox').change(function() {
                const row = $(this).closest('tr');
                const quantityInput = row.find('.quantity-input');
                
                if ($(this).is(':checked')) {
                    quantityInput.prop('disabled', false);
                } else {
                    quantityInput.prop('disabled', true);
                }
                
                updateTotals();
            });
            
            // Handle quantity changes
            $('.quantity-input').on('input', function() {
                updateTotals();
            });
            
            // Submit form validation
            $('#returnForm').submit(function(e) {
                let hasItems = false;
                
                $('.return-checkbox:checked').each(function() {
                    const itemId = $(this).attr('name').match(/\d+/)[0];
                    const quantity = parseInt($(`input[name="return_quantity[${itemId}]"]`).val());
                    
                    if (quantity > 0) {
                        hasItems = true;
                    }
                });
                
                if (!hasItems) {
                    e.preventDefault();
                    alert('Please select at least one item to return');
                    return false;
                }
                
                if (!confirm('Are you sure you want to process this return and issue a cash refund?')) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Update row and total calculations
            function updateTotals() {
                let totalRefund = 0;
                
                $('.return-checkbox').each(function() {
                    const row = $(this).closest('tr');
                    const itemId = $(this).attr('name').match(/\d+/)[0];
                    const priceText = row.find('td:nth-child(3)').text().replace('LKR ', '').replace(',', '');
                    const price = parseFloat(priceText);
                    const quantity = parseInt($(`input[name="return_quantity[${itemId}]"]`).val()) || 0;
                    const subtotal = $(this).is(':checked') ? price * quantity : 0;
                    
                    row.find('.item-subtotal').text(`LKR ${subtotal.toFixed(2)}`);
                    
                    if ($(this).is(':checked')) {
                        totalRefund += subtotal;
                    }
                });
                
                $('#totalRefund').text(`LKR ${totalRefund.toFixed(2)}`);
                
                // Enable/disable submit button
                if (totalRefund > 0) {
                    $('#processReturnBtn').prop('disabled', false);
                } else {
                    $('#processReturnBtn').prop('disabled', true);
                }
            }
        });
    </script>
</body>
</html>