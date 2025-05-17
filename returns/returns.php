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

// Handle search filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Build the query for sales search
$query = "SELECT s.*, 
          DATE_FORMAT(s.sale_date, '%Y-%m-%d') as formatted_date,
          c.name as customer_name, c.phone as customer_phone 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE 1=1";

$params = [];

if (!empty($search_query)) {
    // Check if search query is numeric (likely an invoice number)
    if (is_numeric($search_query)) {
        $query .= " AND s.id = :exact_id";
        $params[':exact_id'] = $search_query;
        // If searching by exact ID, we don't need date filters
    } else {
        // Text search for customer name or phone
        $query .= " AND (c.name LIKE :search OR c.phone LIKE :search)";
        $params[':search'] = "%$search_query%";
        
        // Apply date filter for text searches
        $query .= " AND DATE(s.sale_date) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    }
} else {
    // No search query, apply date filter
    $query .= " AND DATE(s.sale_date) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

$query .= " ORDER BY s.sale_date DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent returns
$returns_query = "SELECT r.*, 
                 DATE_FORMAT(r.return_date, '%Y-%m-%d') as formatted_date,
                 s.sale_date, 
                 c.name as customer_name, c.phone as customer_phone
                 FROM returns r
                 JOIN sales s ON r.sale_id = s.id
                 LEFT JOIN customers c ON s.customer_id = c.id
                 ORDER BY r.return_date DESC
                 LIMIT 10";
$stmt = $pdo->query($returns_query);
$recent_returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$total_returned_count = 0;
$total_returned_amount = 0;

$summary_query = "SELECT COUNT(*) as count, SUM(total_amount) as total 
                 FROM returns 
                 WHERE DATE(return_date) BETWEEN ? AND ?";
$stmt = $pdo->prepare($summary_query);
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

if ($summary) {
    $total_returned_count = $summary['count'] ?: 0;
    $total_returned_amount = $summary['total'] ?: 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retun Management - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 0.75rem 1rem;
            margin-bottom: -1px;
        }

        .nav-tabs .nav-link:hover {
            color: var(--text-primary);
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--accent-blue);
            background-color: transparent;
            border-bottom: 3px solid var(--accent-blue);
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .summary-card {
            transition: transform 0.2s;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .summary-card .card-body {
            padding: 1.25rem;
        }
        
        .summary-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0;
        }
        
        .summary-card .card-title {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        /* Responsive styles */
        @media (max-width: 767px) {
            body {
                padding: 1rem;
            }
            
            .container {
                padding: 0;
            }
            
            .table th, .table td {
                font-size: 0.875rem;
                padding: 0.5rem;
            }
            
            .btn-action {
                padding: 0.2rem 0.4rem;
                font-size: 0.8rem;
            }
            
            /* Mobile cards */
            .mobile-summary-row {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -7px;
            }
            
            .mobile-summary-col {
                flex: 0 0 50%;
                padding: 0 7px;
                margin-bottom: 14px;
            }
        }
        
        .return-alert {
            background-color: var(--darker-bg);
            border-left: 4px solid var(--accent-yellow);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .return-alert i {
            color: var(--accent-yellow);
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Returns Management</h2>
        
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
        
        <!-- Summary Cards (visible on all tabs) -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-danger text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Returns (This Period)</h5>
                        <h3 class="mb-0"><?php echo $total_returned_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-primary text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Refunded Amount</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($total_returned_amount, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <ul class="nav nav-tabs mb-4" id="returnTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="new-return-tab" data-bs-toggle="tab" data-bs-target="#new-return" type="button" role="tab" aria-controls="new-return" aria-selected="true">New Return</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="recent-returns-tab" data-bs-toggle="tab" data-bs-target="#recent-returns" type="button" role="tab" aria-controls="recent-returns" aria-selected="false">Recent Returns</button>
            </li>
        </ul>
        
        <div class="tab-content" id="returnTabsContent">
            <!-- New Return Tab -->
            <div class="tab-pane fade show active" id="new-return" role="tabpanel" aria-labelledby="new-return-tab">
                <div class="return-alert">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Tip:</strong> Enter the invoice number directly to find a specific sale, or use the filters below to search by customer or date range.
                </div>
                
                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Find Sale for Return</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Invoice # or Customer</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Enter invoice # or customer name">
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Search Results -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Sales</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($sales) > 0): ?>
                                        <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?php echo $sale['id']; ?></td>
                                            <td><?php echo $sale['formatted_date']; ?></td>
                                            <td>
                                                <?php echo $sale['customer_name'] ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer'; ?>
                                                <?php if ($sale['customer_phone']): ?>
                                                <br><small><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>LKR <?php echo number_format($sale['total_amount'], 2); ?></td>
                                            <td>
                                                <a href="process_return.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-danger btn-action">
                                                    <i class="fas fa-undo-alt"></i> Process Return
                                                </a>
                                                <a href="../inventory/generate_invoice.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-secondary btn-action">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No sales found with the specified criteria</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Returns Tab -->
            <div class="tab-pane fade" id="recent-returns" role="tabpanel" aria-labelledby="recent-returns-tab">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Recent Returns</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Return #</th>
                                        <th>Original Invoice #</th>
                                        <th>Return Date</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_returns) > 0): ?>
                                        <?php foreach ($recent_returns as $return): ?>
                                        <tr>
                                            <td><?php echo $return['id']; ?></td>
                                            <td><?php echo $return['sale_id']; ?></td>
                                            <td><?php echo $return['formatted_date']; ?></td>
                                            <td>
                                                <?php echo $return['customer_name'] ? htmlspecialchars($return['customer_name']) : 'Walk-in Customer'; ?>
                                                <?php if ($return['customer_phone']): ?>
                                                <br><small><?php echo htmlspecialchars($return['customer_phone']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>LKR <?php echo number_format($return['total_amount'], 2); ?></td>
                                            <td>
                                                <a href="return_receipt.php?return_id=<?php echo $return['id']; ?>" class="btn btn-primary btn-action">
                                                    <i class="fas fa-receipt"></i> Receipt
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No recent returns found</td>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus search input on page load
        $(document).ready(function() {
            $('#search').focus();
            
            // Highlight the invoice number tip for first-time visitors
            const hasSeenTip = localStorage.getItem('hasSeenReturnTip');
            if (!hasSeenTip) {
                $('.return-alert').addClass('animate__animated animate__pulse');
                localStorage.setItem('hasSeenReturnTip', 'true');
            }
        });
    </script>
</body>
</html>