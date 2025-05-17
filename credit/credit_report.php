<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin/login.php");
    exit();
}
require_once '../includes/header.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
$business = get_business_settings();

// Default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today's date

try {    // Get summary of credit sales
    $summary_query = "SELECT 
                        COUNT(*) as total_credit_sales,
                        SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                        SUM(CASE WHEN payment_status IN ('unpaid','partial','paid') THEN total_amount ELSE 0 END) as total_credit_amount,
                        SUM(CASE WHEN payment_status IN ('partial','paid') THEN amount_paid ELSE 0 END) as total_paid_amount,
                        SUM(CASE WHEN payment_status IN ('unpaid','partial') THEN (total_amount - amount_paid) ELSE 0 END) as total_pending_amount
                    FROM sales 
                    WHERE payment_method = 'credit'
                    AND sale_date BETWEEN :start_date AND :end_date";
                    
    $stmt = $pdo->prepare($summary_query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get monthly trend data for credit sales
    $trend_query = "SELECT 
                    DATE_FORMAT(sale_date, '%Y-%m') as month,
                    COUNT(*) as count,
                    SUM(total_amount) as total_amount,
                    SUM(amount_paid) as paid_amount,
                    SUM(total_amount - amount_paid) as pending_amount
                FROM sales 
                WHERE payment_method = 'credit'
                GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12";
                
    $stmt = $pdo->prepare($trend_query);
    $stmt->execute();
    $trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top customers with credit sales
    $customers_query = "SELECT 
                        c.id, c.name, c.phone,
                        COUNT(s.id) as sale_count,
                        SUM(s.total_amount) as total_amount,
                        SUM(s.amount_paid) as paid_amount,
                        SUM(s.total_amount - s.amount_paid) as pending_amount
                    FROM sales s
                    JOIN customers c ON s.customer_id = c.id
                    WHERE s.payment_method = 'credit'
                    GROUP BY c.id
                    ORDER BY pending_amount DESC
                    LIMIT 10";
                    
    $stmt = $pdo->prepare($customers_query);
    $stmt->execute();
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Get aging of credit sales
    $aging_query = "SELECT 
                    CASE 
                        WHEN DATEDIFF(CURRENT_DATE, sale_date) <= 30 THEN '0-30 days'
                        WHEN DATEDIFF(CURRENT_DATE, sale_date) <= 60 THEN '31-60 days'
                        WHEN DATEDIFF(CURRENT_DATE, sale_date) <= 90 THEN '61-90 days'
                        ELSE 'Over 90 days'
                    END as aging_period,
                    COUNT(*) as count,
                    SUM(total_amount - amount_paid) as pending_amount
                FROM sales
                WHERE payment_method = 'credit'
                AND payment_status IN ('unpaid', 'partial')
                GROUP BY aging_period
                ORDER BY CASE 
                    WHEN aging_period = '0-30 days' THEN 1
                    WHEN aging_period = '31-60 days' THEN 2
                    WHEN aging_period = '61-90 days' THEN 3
                    WHEN aging_period = 'Over 90 days' THEN 4
                    ELSE 5
                END ASC";
                
    $stmt = $pdo->prepare($aging_query);
    $stmt->execute();
    $aging_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Format the trend data for the chart
$chart_labels = [];
$chart_sales = [];
$chart_paid = [];
$chart_pending = [];

foreach (array_reverse($trend_data) as $data) {
    $date = DateTime::createFromFormat('Y-m', $data['month']);
    $chart_labels[] = $date->format('M Y');
    $chart_sales[] = floatval($data['total_amount']);
    $chart_paid[] = floatval($data['paid_amount']);
    $chart_pending[] = floatval($data['pending_amount']);
}

// Prepare data for pie chart
$pie_labels = ['Paid', 'Partial', 'Pending'];
$pie_data = [
    $summary['paid_count'], 
    $summary['partial_count'], 
    $summary['pending_count']
];

// Prepare data for aging chart
$aging_labels = [];
$aging_amounts = [];
foreach ($aging_data as $aging) {
    $aging_labels[] = $aging['aging_period'];
    $aging_amounts[] = floatval($aging['pending_amount']);
}
?>
<head>
    <title>Credit Sales Report</title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
</head>
<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Credit Sales Analysis</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="../index.php" class="btn btn-secondary btn-sm me-2">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="credit_management.php" class="btn btn-primary btn-sm">
                <i class="fas fa-credit-card"></i> Credit Management
            </a>
        </div>
    </div>

    <!-- Date Filter Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter Date Range</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="credit_report.php" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards Section -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Credit Sales</h6>
                    <h2 class="card-title"><?= $summary['total_credit_sales'] ?></h2>
                    <p class="card-text">Invoices</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-white-50">Total Credit Amount</h6>
                    <h2 class="card-title">LKR <?= number_format($summary['total_credit_amount'], 2) ?></h2>
                    <p class="card-text">Value of credit sales</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-dark">Received Amount</h6>
                    <h2 class="card-title text-dark">LKR <?= number_format($summary['total_paid_amount'], 2) ?></h2>
                    <p class="card-text text-dark"><?= round(($summary['total_paid_amount'] / $summary['total_credit_amount']) * 100, 1) ?>% of total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-danger text-white">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-white-50">Outstanding Amount</h6>
                    <h2 class="card-title">LKR <?= number_format($summary['total_pending_amount'], 2) ?></h2>
                    <p class="card-text"><?= round(($summary['total_pending_amount'] / $summary['total_credit_amount']) * 100, 1) ?>% of total</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 style = "color : black">Monthly Credit Sales Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="creditTrendChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 style = "color : black">Credit Sales Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="creditStatusChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 style = "color : black">Aging of Receivables</h5>
                </div>
                <div class="card-body">
                    <canvas id="agingChart" width="400" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 style = "color : black">Top Customers with Outstanding Credit</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th class="text-center">Sales</th>
                                    <th class="text-end">Outstanding</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_customers as $customer): ?>
                                <?php if ($customer['pending_amount'] > 0): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($customer['name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($customer['phone']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $customer['sale_count'] ?></td>
                                    <td class="text-end">LKR <?= number_format($customer['pending_amount'], 2) ?></td>
                                    <td>
                                        <a href="credit_management.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <?php if (count($top_customers) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No customers with outstanding credit</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Credit Sales Trend Chart
    const trendCtx = document.getElementById('creditTrendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Total Credit Sales',
                data: <?= json_encode($chart_sales) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'Paid Amount',
                data: <?= json_encode($chart_paid) ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }, {
                label: 'Outstanding',
                data: <?= json_encode($chart_pending) ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Credit Status Pie Chart
    const statusCtx = document.getElementById('creditStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_data) ?>,
                backgroundColor: [
                    'rgba(75, 192, 192, 0.5)',  // Paid
                    'rgba(255, 205, 86, 0.5)',  // Partial
                    'rgba(255, 99, 132, 0.5)'   // Pending
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Aging Chart
    const agingCtx = document.getElementById('agingChart').getContext('2d');
    const agingChart = new Chart(agingCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($aging_labels) ?>,
            datasets: [{
                label: 'Outstanding Amount',
                data: <?= json_encode($aging_amounts) ?>,
                backgroundColor: [
                    'rgba(75, 192, 192, 0.5)',  // 0-30 days
                    'rgba(255, 205, 86, 0.5)',  // 31-60 days
                    'rgba(255, 159, 64, 0.5)',  // 61-90 days
                    'rgba(255, 99, 132, 0.5)'   // Over 90 days
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>
