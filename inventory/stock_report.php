<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
require_once '../includes/db_connection.php';
include_once '../includes/functions.php';
$business = get_business_settings();

// Filter variables
$category_filter = isset($_POST['category']) ? $_POST['category'] : '';
$search_query = isset($_POST['search']) ? $_POST['search'] : '';
$stock_status = isset($_POST['stock_status']) ? $_POST['stock_status'] : '';

// Get all categories for the filter dropdown
$categories_query = "SELECT DISTINCT category FROM products ORDER BY category";
$categories_stmt = $pdo->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build the query with filters
$query = "SELECT id, name, description, purchase_price, selling_price, quantity, 
          category, created_at, updated_at 
          FROM products 
          WHERE 1=1";

$params = [];

if (!empty($category_filter)) {
    $query .= " AND category = :category";
    $params[':category'] = $category_filter;
}

if (!empty($search_query)) {
    $query .= " AND (name LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$search_query%";
}

if (!empty($stock_status)) {
    if ($stock_status == 'low') {
        $query .= " AND quantity <= 5 AND quantity > 0";
    } elseif ($stock_status == 'out') {
        $query .= " AND quantity = 0";
    } elseif ($stock_status == 'available') {
        $query .= " AND quantity > 5";
    }
}

$query .= " ORDER BY category, name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_products = count($products);
$total_value = 0;
$total_retail_value = 0;
$out_of_stock = 0;
$low_stock = 0;

foreach ($products as $product) {
    $total_value += $product['purchase_price'] * $product['quantity'];
    $total_retail_value += $product['selling_price'] * $product['quantity'];
    
    if ($product['quantity'] == 0) {
        $out_of_stock++;
    } elseif ($product['quantity'] <= 5) {
        $low_stock++;
    }
}

$potential_profit = $total_retail_value - $total_value;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .summary-card {
            transition: transform 0.2s;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            cursor: pointer;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .low-stock {
            background-color: #fff3cd;
        }
        
        .out-of-stock {
            background-color: #f8d7da;
        }
        
        /* Mobile optimizations */
        @media (max-width: 767px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            h1.h3 {
                font-size: 1.5rem;
                text-align: center;
                margin-bottom: 15px;
            }
            
            .card-title {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
            
            .card h3 {
                font-size: 1.3rem;
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
            
            .mobile-summary-col.full-width {
                flex: 0 0 100%;
            }
            
            /* Filter form on mobile */
            .filter-form .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.25rem;
            }
            
            .filter-form .form-control,
            .filter-form .form-select,
            .filter-form .btn {
                font-size: 0.9rem;
                padding: 0.375rem 0.5rem;
                min-height: 40px;
            }
            
            .action-buttons {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
            }
            
            .action-buttons .btn {
                flex: 1;
                margin: 0 5px;
                padding: 8px 10px;
                font-size: 0.85rem;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .action-buttons .btn i {
                margin-right: 5px;
            }
            
            /* Table on mobile */
            .table-responsive {
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                margin-bottom: 60px; /* Space for mobile nav */
            }
            
            .table th, 
            .table td {
                vertical-align: middle;
                padding: 0.5rem;
                font-size: 0.85rem;
            }
            
            /* Mobile-optimized badges */
            .badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
                margin-left: 5px;
            }
            
            /* Mobile navigation */
            .mobile-back-btn {
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1000;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            
            .mobile-nav-panel {
                display: none;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background-color: #fff;
                border-top: 1px solid #dee2e6;
                padding: 0.5rem;
                z-index: 1000;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            }
            
            @media (max-width: 767px) {
                .mobile-nav-panel {
                    display: flex;
                    justify-content: space-around;
                }
                
                body {
                    padding-bottom: 60px; /* Space for the nav panel */
                }
            }
            
            .mobile-nav-btn {
                background: transparent;
                border: none;
                color: #212529;
                font-size: 0.8rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0.5rem;
            }
            
            .mobile-nav-btn i {
                font-size: 1.2rem;
                margin-bottom: 0.25rem;
            }
            
            /* Mobile list view option */
            .mobile-card-view {
                display: none;
            }
            
            @media (max-width: 575px) {
                .mobile-card-view {
                    display: block;
                }
                
                .mobile-card {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    padding: 12px;
                    margin-bottom: 10px;
                    border-left: 5px solid #6c757d;
                }
                
                .mobile-card.out-of-stock {
                    border-left-color: #dc3545;
                }
                
                .mobile-card.low-stock {
                    border-left-color: #ffc107;
                }
                
                .mobile-card-header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }
                
                .mobile-card-title {
                    font-weight: bold;
                    font-size: 1rem;
                    margin: 0;
                }
                
                .mobile-card-content {
                    display: flex;
                    flex-wrap: wrap;
                }
                
                .mobile-card-item {
                    flex: 0 0 50%;
                    margin-bottom: 5px;
                }
                
                .mobile-card-label {
                    font-size: 0.75rem;
                    color: #6c757d;
                    margin-bottom: 0;
                }
                
                .mobile-card-value {
                    font-size: 0.9rem;
                    margin-bottom: 0;
                }
            }
            
            /* Filter toggle button */
            .filter-toggle {
                display: block;
                width: 100%;
                padding: 10px;
                margin-bottom: 15px;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                text-align: center;
                font-weight: 500;
                cursor: pointer;
            }
            
            .filter-toggle i {
                margin-right: 5px;
                transition: transform 0.2s;
            }
            
            .filter-toggle.collapsed i {
                transform: rotate(180deg);
            }
        }
        
        /* Print optimizations */
        @media print {
            .mobile-nav-panel, 
            .mobile-back-btn,
            .filter-form,
            .action-buttons {
                display: none !important;
            }
            
            .container-fluid {
                width: 100%;
                max-width: none;
                padding: 0;
                margin: 0;
            }
            
            .table-responsive {
                overflow: visible;
                margin-bottom: 0;
            }
            
            .table th, 
            .table td {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .summary-card {
                transform: none !important;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile back button -->
    <a href="../pos/index.php" class="mobile-back-btn d-md-none">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container-fluid py-4">
        <h1 class="h3 mb-4">Stock Report</h1>
        
        <!-- Mobile toggle for filters -->
        <button class="filter-toggle d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
            <i class="bi bi-chevron-up"></i> Filter Options
        </button>
        
        <!-- Filters -->
        <div class="collapse d-md-block" id="filterCollapse">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" class="row g-2 filter-form">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter == $category) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Stock Status</label>
                            <select class="form-select" name="stock_status">
                                <option value="">All Status</option>
                                <option value="out" <?php echo ($stock_status == 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                                <option value="low" <?php echo ($stock_status == 'low') ? 'selected' : ''; ?>>Low Stock (≤ 5)</option>
                                <option value="available" <?php echo ($stock_status == 'available') ? 'selected' : ''; ?>>Available (> 5)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by name or description">
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="stock_report.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Summary Cards - Desktop -->
        <div class="row mb-4 d-none d-md-flex">
            <div class="col-md-3">
                <div class="card bg-primary text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <h3 class="mb-0"><?php echo $total_products; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Out of Stock</h5>
                        <h3 class="mb-0"><?php echo $out_of_stock; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock</h5>
                        <h3 class="mb-0"><?php echo $low_stock; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Inventory Value</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($total_value, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="row mb-4 d-none d-md-flex">
            <div class="col-md-6">
                <div class="card bg-info text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Retail Value</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($total_retail_value, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-secondary text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Potential Profit</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($potential_profit, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Cards - Mobile -->
        <div class="mobile-summary-row d-md-none">
            <div class="mobile-summary-col">
                <div class="card bg-primary text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <h3 class="mb-0"><?php echo $total_products; ?></h3>
                    </div>
                </div>
            </div>
            <div class="mobile-summary-col">
                <div class="card bg-success text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Inventory Value</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($total_value, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="mobile-summary-col">
                <div class="card bg-danger text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Out of Stock</h5>
                        <h3 class="mb-0"><?php echo $out_of_stock; ?></h3>
                    </div>
                </div>
            </div>
            <div class="mobile-summary-col">
                <div class="card bg-warning text-dark summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock</h5>
                        <h3 class="mb-0"><?php echo $low_stock; ?></h3>
                    </div>
                </div>
            </div>
            
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="mobile-summary-col">
                <div class="card bg-info text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Retail Value</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($total_retail_value, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="mobile-summary-col">
                <div class="card bg-secondary text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Potential Profit</h5>
                        <h3 class="mb-0">LKR <?php echo number_format($potential_profit, 2); ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>        <!-- Export and Print Buttons - Desktop -->
        <div class="mb-3 d-none d-md-block">
            <a href="barcode_labels.php" class="btn btn-primary me-2">
                <i class="bi bi-upc-scan"></i> Generate Barcode Labels
            </a>
            <button class="btn btn-success me-2" onclick="exportToCSV()">
                <i class="bi bi-file-earmark-excel"></i> Export to CSV
            </button>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>

        <!-- Export and Print Buttons - Mobile -->
        <div class="action-buttons d-md-none">
            <a href="barcode_labels.php" class="btn btn-primary">
                <i class="bi bi-upc-scan"></i> Barcodes
            </a>
            <button class="btn btn-success" onclick="exportToCSV()">
                <i class="bi bi-file-earmark-excel"></i> Export
            </button>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>

        <!-- Toggle view buttons - Mobile only -->
        <div class="btn-group d-flex mb-3 d-md-none">
            <button type="button" class="btn btn-outline-primary active" id="tableViewBtn">Table View</button>
            <button type="button" class="btn btn-outline-primary" id="cardViewBtn">Card View</button>
        </div>

        <!-- Products Table -->
        <div class="card table-view">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <th>Cost</th>
                                <th>Margin</th>
                                <?php endif; ?>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($products) > 0): ?>
                                <?php foreach ($products as $product): 
                                    $row_class = '';
                                    if ($product['quantity'] == 0) {
                                        $row_class = 'out-of-stock';
                                    } elseif ($product['quantity'] <= 5) {
                                        $row_class = 'low-stock';
                                    }
                                    
                                    $profit_margin = 0;
                                    if ($product['purchase_price'] > 0) {
                                        $profit_margin = (($product['selling_price'] - $product['purchase_price']) / $product['purchase_price']) * 100;
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td>
                                        <?php echo $product['quantity']; ?>
                                        <?php if ($product['quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out</span>
                                        <?php elseif ($product['quantity'] <= 5): ?>
                                            <span class="badge bg-warning text-dark">Low</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>LKR <?php echo number_format($product['selling_price'], 2); ?></td>
                                    <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <td>LKR <?php echo number_format($product['purchase_price'], 2); ?></td>
                                    <td><?php echo number_format($profit_margin, 2); ?>%</td>
                                    <?php endif; ?>
                                    <td><?php echo date('Y-m-d', strtotime($product['updated_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? '8' : '6'; ?>" class="text-center py-3">No products found matching your filters</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Mobile Card View -->
        <div class="mobile-card-view d-none">
            <?php if(count($products) > 0): ?>
                <?php foreach ($products as $product): 
                    $card_class = '';
                    if ($product['quantity'] == 0) {
                        $card_class = 'out-of-stock';
                    } elseif ($product['quantity'] <= 5) {
                        $card_class = 'low-stock';
                    }
                    
                    $profit_margin = 0;
                    if ($product['purchase_price'] > 0) {
                        $profit_margin = (($product['selling_price'] - $product['purchase_price']) / $product['purchase_price']) * 100;
                    }
                ?>
                <div class="mobile-card <?php echo $card_class; ?>">
                    <div class="mobile-card-header">
                        <h5 class="mobile-card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <div>
                            <?php if ($product['quantity'] == 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($product['quantity'] <= 5): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mobile-card-content">
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">ID</p>
                            <p class="mobile-card-value"><?php echo $product['id']; ?></p>
                        </div>
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">Category</p>
                            <p class="mobile-card-value"><?php echo htmlspecialchars($product['category']); ?></p>
                        </div>
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">Quantity</p>
                            <p class="mobile-card-value"><?php echo $product['quantity']; ?></p>
                        </div>
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">Selling Price</p>
                            <p class="mobile-card-value">LKR <?php echo number_format($product['selling_price'], 2); ?></p>
                        </div>
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">Purchase Price</p>
                            <p class="mobile-card-value">LKR <?php echo number_format($product['purchase_price'], 2); ?></p>
                        </div>
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">Profit Margin</p>
                            <p class="mobile-card-value"><?php echo number_format($profit_margin, 2); ?>%</p>
                        </div>
                        <?php endif; ?>
                        <div class="mobile-card-item">
                            <p class="mobile-card-label">Last Updated</p>
                            <p class="mobile-card-value"><?php echo date('Y-m-d', strtotime($product['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-3">
                    <p>No products found matching your filters</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Navigation Panel -->
    <div class="mobile-nav-panel d-md-none">
        <a href="../pos/index.php" class="mobile-nav-btn">
            <i class="fas fa-home"></i>
            <span>POS</span>
        </a>
        <a href="../inventory/sales_report.php" class="mobile-nav-btn">
            <i class="fas fa-chart-line"></i>
            <span>Sales</span>
        </a>
        <button class="mobile-nav-btn" onclick="$('#filterCollapse').collapse('toggle')">
            <i class="fas fa-filter"></i>
            <span>Filter</span>
        </button>
        <button class="mobile-nav-btn" onclick="exportToCSV()">
            <i class="fas fa-download"></i>
            <span>Export</span>
        </button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToCSV() {
            // Prepare data for CSV export
            let rows = [];
            const table = document.querySelector('table');
            const headerRow = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            rows.push(headerRow);
            
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = Array.from(tr.querySelectorAll('td')).map(td => {
                    // Remove any HTML tags and trim the content
                    return td.textContent.replace(/(\r\n|\n|\r)/gm, "").trim();
                });
                rows.push(row);
            });
            
            // Convert to CSV format
            const csvContent = rows.map(row => row.join(',')).join('\n');
            
            // Create download link
            const encodedUri = encodeURI('data:text/csv;charset=utf-8,' + csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'stock_report_' + new Date().toISOString().slice(0,10) + '.csv');
            document.body.appendChild(link);
            
            // Trigger download and remove link
            link.click();
            document.body.removeChild(link);
        }
        
        // Mobile view switching
        document.addEventListener('DOMContentLoaded', function() {
            const tableViewBtn = document.getElementById('tableViewBtn');
            const cardViewBtn = document.getElementById('cardViewBtn');
            const tableView = document.querySelector('.table-view');
            const cardView = document.querySelector('.mobile-card-view');
            
            // Initial view setup
            if(window.innerWidth <= 575) {
                // Default to card view on very small screens
                tableView.classList.add('d-none');
                cardView.classList.remove('d-none');
                tableViewBtn.classList.remove('active');
                cardViewBtn.classList.add('active');
            }
            
            // View toggle handlers
            tableViewBtn.addEventListener('click', function() {
                tableView.classList.remove('d-none');
                cardView.classList.add('d-none');
                tableViewBtn.classList.add('active');
                cardViewBtn.classList.remove('active');
            });
            
            cardViewBtn.addEventListener('click', function() {
                cardView.classList.remove('d-none');
                tableView.classList.add('d-none');
                cardViewBtn.classList.add('active');
                tableViewBtn.classList.remove('active');
            });
            
            // Mobile touch feedback
            const touchTargets = document.querySelectorAll('.mobile-card, .btn, .summary-card');
            touchTargets.forEach(target => {
                target.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                target.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        });
    </script>
</body>
</html>