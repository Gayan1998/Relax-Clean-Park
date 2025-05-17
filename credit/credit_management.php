<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin/login.php");
    exit();
}
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
$business = get_business_settings();

// Default filter values - expanded date range to ensure records are found
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'unpaid'; // Default to only showing active (not fully paid) credit sales
$customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01'); // First day of current year
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today's date

// Build query for credit sales with filters - using case-insensitive comparison
$query = "SELECT s.*, 
          c.name AS customer_name, c.phone AS customer_phone,
          DATE_FORMAT(s.sale_date, '%Y-%m-%d') AS formatted_sale_date,
          DATE_FORMAT(s.payment_date, '%Y-%m-%d') AS formatted_payment_date,
          (s.total_amount - COALESCE(s.amount_paid, 0)) AS balance_due
          FROM sales s
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE LOWER(s.payment_method) = 'credit'";

// Apply filters
if ($status_filter !== 'all') {
    if ($status_filter === 'unpaid') {
        // For unpaid filter, include 'unpaid', 'pending', and NULL status records
        $query .= " AND (s.payment_status = 'unpaid' OR s.payment_status = 'pending' OR s.payment_status IS NULL)";
    } else if ($status_filter === 'active') {
        // For active filter, show all credit sales that are not fully paid
        $query .= " AND (s.payment_status != 'paid' OR s.payment_status IS NULL)";
    } else {
        $query .= " AND s.payment_status = :status";
    }
}

if ($customer_filter > 0) {
    $query .= " AND s.customer_id = :customer_id";
}

// Use DATE() function for proper date comparison without time components
$query .= " AND DATE(s.sale_date) BETWEEN :start_date AND :end_date";
$query .= " ORDER BY s.sale_date DESC";

$stmt = $pdo->prepare($query);

// Bind parameters
if ($status_filter !== 'all' && $status_filter !== 'unpaid' && $status_filter !== 'active') {
    $stmt->bindParam(':status', $status_filter);
}

if ($customer_filter > 0) {
    $stmt->bindParam(':customer_id', $customer_filter);
}

$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);

// Execute query with error handling
try {
    $stmt->execute();
    $credit_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $credit_sales = [];
}

// Get all customers for the filter dropdown
$customers_query = "SELECT id, name, phone FROM customers ORDER BY name";
$customers_stmt = $pdo->prepare($customers_query);
$customers_stmt->execute();
$customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total credit and balance amounts - FIXED to exclude paid sales
$total_credit_amount = 0;
$total_paid_amount = 0;
$total_balance_due = 0;

foreach ($credit_sales as $sale) {
    // Only include in totals if the sale is not fully paid
    if ($sale['payment_status'] !== 'paid') {
        $total_credit_amount += $sale['total_amount'];
        $total_paid_amount += $sale['amount_paid'];
        $total_balance_due += $sale['balance_due'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Management - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/theme.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <?php 
    $page_title = "Credit Sales Management";
    include '../includes/page_header.php';
    ?>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <?php if ($status_filter === 'active'): ?>
            <p class="text-muted">Showing active credit sales only (excluding fully paid invoices)</p>
            <?php elseif ($status_filter === 'paid'): ?>
            <p class="text-muted">Showing fully paid credit invoices</p>
            <?php endif; ?>
        </div>        
        <div class="col-md-6 text-end">
            <a href="../admin/dashboard.php" class="btn btn-secondary btn-sm me-2">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="credit_report.php" class="btn btn-info btn-sm">
                <i class="fas fa-chart-bar"></i> Credit Analysis
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter Credit Sales</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="credit_management.php">
                <div class="row">
                    <div class="col-md-3 mb-3">                        <label for="status" class="form-label">Payment Status</label>                        
                        <select name="status" id="status" class="form-select">
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Credits Only</option>
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partially Paid</option>
                            <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Fully Paid</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="0">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>" <?= $customer_filter == $customer['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['name']) ?> (<?= htmlspecialchars($customer['phone']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>    </div>

    <!-- Summary Section -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Credit Amount</h5>
                    <h3 class="card-text" style = "color: black">LKR <?= number_format($total_credit_amount, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Paid Amount</h5>
                    <h3 class="card-text">LKR <?= number_format($total_paid_amount, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Balance Due</h5>
                    <h3 class="card-text">LKR <?= number_format($total_balance_due, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Credit Sales List -->
    <div class="card">
        <div class="card-header">
            <h5>Credit Sales List</h5>
        </div>
        <div class="card-body">
            <?php if (empty($credit_sales)): ?>                <div class="alert alert-info">
                    <p>No credit sales found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Total Amount</th>
                                <th>Amount Paid</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($credit_sales as $sale): ?>
                                <tr>
                                    <td><?= $sale['id'] ?></td>
                                    <td><?= $sale['formatted_sale_date'] ?></td>
                                    <td>
                                        <?= !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer' ?>
                                        <?= !empty($sale['customer_phone']) ? '<br><small class="text-muted">' . htmlspecialchars($sale['customer_phone']) . '</small>' : '' ?>
                                    </td>
                                    <td class="text-end">LKR <?= number_format($sale['total_amount'], 2) ?></td>
                                    <td class="text-end">LKR <?= number_format($sale['amount_paid'], 2) ?></td>
                                    <td class="text-end">LKR <?= number_format($sale['balance_due'], 2) ?></td>
                                    <td>
                                        <span class="badge <?= 
                                            $sale['payment_status'] === 'paid' ? 'bg-success' : 
                                            ($sale['payment_status'] === 'partial' ? 'bg-warning' : 
                                            'bg-danger') ?>">
                                            <?php 
                                            if (empty($sale['payment_status'])) {
                                                echo 'Unpaid';
                                            } elseif ($sale['payment_status'] === 'pending') {
                                                echo 'Unpaid'; // Display "pending" as "Unpaid"
                                            } else {
                                                echo ucfirst($sale['payment_status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?= $sale['payment_status'] === 'paid' && !empty($sale['formatted_payment_date']) ? $sale['formatted_payment_date'] : 'N/A' ?></td>
                                    <td>
                                        <a href="../inventory/generate_invoice.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-info mb-1" title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($sale['payment_status'] !== 'paid'): ?>
                                            <a href="record_payment.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-success mb-1" title="Record Payment">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>