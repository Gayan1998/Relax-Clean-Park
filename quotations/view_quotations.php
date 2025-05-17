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

// Filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query - include sale payment method for converted quotes
$query = "SELECT q.*, c.name as customer_name, c.phone as customer_phone,
          s.payment_method, s.id as sale_id
          FROM quotations q
          LEFT JOIN customers c ON q.customer_id = c.id
          LEFT JOIN sales s ON s.quotation_id = q.id
          WHERE 1=1";
$params = [];

if (!empty($status)) {
    $query .= " AND q.status = :status";
    $params[':status'] = $status;
}

$query .= " AND DATE(q.quote_date) BETWEEN :start_date AND :end_date";
$params[':start_date'] = $startDate;
$params[':end_date'] = $endDate;

if (!empty($search)) {
    $query .= " AND (c.name LIKE :search OR c.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY q.quote_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle quotation status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'convert':
            // Convert quotation to sale
            if (!isset($_POST['quotation_id'])) {
                $_SESSION['error'] = "Quotation ID is required";
                break;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Fetch quotation data
                $quoteStmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
                $quoteStmt->execute([$_POST['quotation_id']]);
                $quotation = $quoteStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$quotation) {
                    throw new Exception("Quotation not found");
                }
                
                // Fetch quotation items
                $itemsStmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
                $itemsStmt->execute([$_POST['quotation_id']]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);                // Prompt for payment method or add a default
                $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
                  // Set payment status based on payment method
                $paymentStatus = $payment_method === 'cash' ? 'paid' : 'unpaid';
                $amountPaid = $payment_method === 'cash' ? $quotation['total_amount'] : 0;
                $paymentDate = $payment_method === 'cash' ? date('Y-m-d') : null;
                
                // Create sale with payment method and status
                $saleStmt = $pdo->prepare("INSERT INTO sales 
                    (customer_id, quotation_id, total_amount, profit, payment_method, payment_status, amount_paid, payment_date, sale_date, created_at, updated_at) 
                    VALUES (?, ?, ?, 0, ?, ?, ?, ?, NOW(), NOW(), NOW())");
                $saleStmt->execute([
                    $quotation['customer_id'],
                    $quotation['id'],
                    $quotation['total_amount'],
                    $payment_method,
                    $paymentStatus,
                    $amountPaid,
                    $paymentDate
                ]);
                
                $saleId = $pdo->lastInsertId();
                $totalProfit = 0;
                
                // Insert sale items and calculate profit
                foreach ($items as $item) {
                    // Get product cost price
                    $prodStmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
                    $prodStmt->execute([$item['product_id']]);
                    $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
                    $costPrice = $product['purchase_price'] ?? 0;
                    
                    // Calculate profit
                    $itemProfit = ($item['price'] - $costPrice) * $item['quantity'];
                    $totalProfit += $itemProfit;
                    
                    // Insert sale item
                    $saleItemStmt = $pdo->prepare("INSERT INTO sale_items 
                        (sale_id, product_id, quantity, price, total_price, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                    $saleItemStmt->execute([
                        $saleId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['total_price']
                    ]);
                    
                    // Update product stock
                    $stockStmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $stockStmt->execute([$item['quantity'], $item['product_id']]);
                }
                
                // Update sale with profit
                $updateSaleStmt = $pdo->prepare("UPDATE sales SET profit = ? WHERE id = ?");
                $updateSaleStmt->execute([$totalProfit, $saleId]);
                
                // Update quotation status
                $updateQuoteStmt = $pdo->prepare("UPDATE quotations SET status = 'converted' WHERE id = ?");
                $updateQuoteStmt->execute([$_POST['quotation_id']]);
                  $pdo->commit();
                
                $_SESSION['message'] = "Quotation #{$_POST['quotation_id']} successfully converted to Sale #$saleId";
                
                // Redirect to the invoice page after conversion
                header('Location: ../inventory/generate_invoice.php?id=' . $saleId);
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error converting quotation: " . $e->getMessage();
            }
            break;
            
        case 'update_status':
            if (!isset($_POST['quotation_id']) || !isset($_POST['status'])) {
                $_SESSION['error'] = "Quotation ID and status are required";
                break;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE quotations SET status = ? WHERE id = ?");
                $stmt->execute([$_POST['status'], $_POST['quotation_id']]);
                $_SESSION['message'] = "Quotation status updated successfully!";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } catch (Exception $e) {
                $_SESSION['error'] = "Error updating status: " . $e->getMessage();
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        /* Additional styles specific to quotations page */

        .company-title {
            color: var(--accent-primary);
            font-weight: 700;
            font-size: 1.8rem;
            text-transform: uppercase;
            margin: 0 0 0 1rem;
        }

        .header-divider {
            height: 3px;
            background-color: var(--accent-primary);
            margin: 0.5rem 0 2rem 0;
        }

        .company-logo {
            max-height: 50px;
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
            border-color: var(--accent-primary);
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

        .badge-pending {
            background-color: var(--accent-blue);
            color: var(--darker-bg);
        }

        .badge-accepted {
            background-color: var(--accent-green);
            color: var(--darker-bg);
        }

        .badge-rejected {
            background-color: var(--accent-red);
            color: var(--darker-bg);
        }

        .badge-expired {
            background-color: var(--text-secondary);
            color: var(--darker-bg);
        }

        .badge-converted {
            background-color: var(--accent-purple);
            color: var(--darker-bg);
        }

        .btn-action {
            background-color: var(--accent-blue);
            color: var(--darker-bg);
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            transition: opacity 0.2s;
        }

        .btn-action:hover {
            opacity: 0.8;
            color: var(--darker-bg);
        }

        .btn-convert {
            background-color: var(--accent-green);
        }

        .btn-print {
            background-color: var(--accent-yellow);
        }

        .btn-primary {
            background-color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .btn-primary:hover, .btn-primary:focus {
            background-color: #6d0000;
            border-color: #6d0000;
        }

        .dropdown-menu {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }

        .dropdown-item {
            color: var(--text-primary);
        }

        .dropdown-item:hover {
            background-color: var(--darker-bg);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="container-fluid">        <div class="page-header">
            <img src="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo" class="company-logo">
            <h1 class="company-title"><?php echo htmlspecialchars($business['business_name']); ?></h1>
        </div>
        <div class="header-divider"></div>
        
        <h2 class="mb-4">Quotations Management</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                            <option value="converted" <?= $status === 'converted' ? 'selected' : '' ?>>Converted to Sale</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Search Customer</label>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Customer name or phone">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quotations Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Quote #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Total Amount</th>
                                <th>Valid Until</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($quotations) > 0): ?>
                                <?php foreach ($quotations as $quote): 
                                    $statusClass = 'badge-' . $quote['status'];
                                    $isExpired = strtotime($quote['valid_until']) < time() && $quote['status'] === 'pending';
                                    if ($isExpired) {
                                        $statusClass = 'badge-expired';
                                    }
                                ?>
                                <tr>
                                    <td><?= $quote['id'] ?></td>
                                    <td><?= date('Y-m-d', strtotime($quote['quote_date'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($quote['customer_name'] ?? 'Walk-in Customer') ?>
                                        <?= !empty($quote['customer_phone']) ? '<br><small>' . htmlspecialchars($quote['customer_phone']) . '</small>' : '' ?>
                                    </td>
                                    <td>LKR <?= number_format($quote['total_amount'], 2) ?></td>
                                    <td>
                                        <?= date('Y-m-d', strtotime($quote['valid_until'])) ?>
                                        <?= $isExpired ? '<span class="badge bg-danger">Expired</span>' : '' ?>
                                    </td>                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= ucfirst($isExpired ? 'expired' : $quote['status']) ?>
                                        </span>
                                        <?php if ($quote['status'] === 'converted' && !empty($quote['payment_method'])): ?>
                                            <span class="badge bg-<?= $quote['payment_method'] === 'credit' ? 'danger' : 'success' ?>">
                                                <?= ucfirst($quote['payment_method']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">                                            <a href="generate_quotation.php?id=<?= $quote['id'] ?>" target="_blank" class="btn btn-action btn-print me-1">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                            <?php if ($quote['status'] === 'converted' && !empty($quote['sale_id'])): ?>
                                            <a href="../inventory/generate_invoice.php?id=<?= $quote['sale_id'] ?>" target="_blank" class="btn btn-action btn-success me-1">
                                                <i class="fas fa-file-invoice"></i> View Invoice
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($quote['status'] === 'pending' && !$isExpired): ?>
                                            <div class="dropdown d-inline-block">
                                                <button class="btn btn-action dropdown-toggle" type="button" id="statusDropdown<?= $quote['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-cog"></i> Status
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="statusDropdown<?= $quote['id'] ?>">
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="quotation_id" value="<?= $quote['id'] ?>">
                                                            <input type="hidden" name="status" value="accepted">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="fas fa-check text-success"></i> Mark as Accepted
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="quotation_id" value="<?= $quote['id'] ?>">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="fas fa-times text-danger"></i> Mark as Rejected
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                              <?php if (($quote['status'] === 'pending' || $quote['status'] === 'accepted') && !$isExpired): ?>
                                            <button type="button" class="btn btn-action btn-convert" data-bs-toggle="modal" data-bs-target="#convertModal<?= $quote['id'] ?>">
                                                <i class="fas fa-exchange-alt"></i> Convert to Sale
                                            </button>
                                            
                                            <!-- Modal for payment method -->
                                            <div class="modal fade" id="convertModal<?= $quote['id'] ?>" tabindex="-1" aria-labelledby="convertModalLabel<?= $quote['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="convertModalLabel<?= $quote['id'] ?>">Convert Quotation to Sale</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <p>Converting this quotation will create a new sale and reduce stock levels. Please select a payment method:</p>
                                                                <input type="hidden" name="action" value="convert">
                                                                <input type="hidden" name="quotation_id" value="<?= $quote['id'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="payment_method<?= $quote['id'] ?>" class="form-label">Payment Method</label>
                                                                    <select name="payment_method" id="payment_method<?= $quote['id'] ?>" class="form-select">
                                                                        <option value="cash">Cash</option>
                                                                        <option value="credit">Credit</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Convert to Sale</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No quotations found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>