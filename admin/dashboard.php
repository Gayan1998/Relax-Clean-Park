<?php
include '../includes/db_connection.php'; 
include '../includes/functions.php';
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get business settings
$business = get_business_settings();

// Fetch existing products
$stmt = $pdo->query("SELECT * FROM products ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing customers
$stmt = $pdo->query("SELECT id, name, phone, email, address FROM customers ORDER BY name");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vehicle statistics
$vehicleQuery = $pdo->query("SELECT COUNT(*) as vehicle_count FROM vehicles");
$vehicleCount = $vehicleQuery->fetch(PDO::FETCH_ASSOC)['vehicle_count'] ?? 0;

// Get job card statistics
$jobTotalQuery = $pdo->query("SELECT COUNT(*) as job_count FROM job_cards");
$jobTotal = $jobTotalQuery->fetch(PDO::FETCH_ASSOC)['job_count'] ?? 0;

$openJobsQuery = $pdo->query("SELECT COUNT(*) as open_count FROM job_cards WHERE status = 'Open'");
$openJobs = $openJobsQuery->fetch(PDO::FETCH_ASSOC)['open_count'] ?? 0;

$inProgressQuery = $pdo->query("SELECT COUNT(*) as progress_count FROM job_cards WHERE status = 'In Progress'");
$inProgress = $inProgressQuery->fetch(PDO::FETCH_ASSOC)['progress_count'] ?? 0;

$completedQuery = $pdo->query("SELECT COUNT(*) as completed_count FROM job_cards WHERE status = 'Completed'");
$completed = $completedQuery->fetch(PDO::FETCH_ASSOC)['completed_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <style>
        /* Additional styles specific to dashboard */
        .btn-remove {
            background-color: transparent;
            border: none;
            color: var(--accent-red);
            padding: 0.5rem;
            cursor: pointer;
            transition: opacity var(--transition-speed);
        }

        .btn-remove:hover {
            opacity: 0.8;
        }

        /* New sidebar styles */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            max-width: 1700px;
            margin: 0 auto;
            gap: 1rem;
        }

        /* For desktop: show sidebar and content side by side */
        @media (min-width: 992px) {
            .dashboard-container {
                flex-direction: row;
            }
            
            .sidebar {
                width: 280px;
                flex-shrink: 0;
            }
            
            .main-content {
                flex: 1;
            }
        }

        /* Hide sidebar on mobile */
        @media (max-width: 991px) {
            .sidebar {
                display: none;
            }
        }

        /* Sidebar styling */
        .sidebar {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            height: calc(100vh - 2rem);
            position: sticky;
            top: 1rem;
            overflow-y: auto;
        }

        /* Content wrapper (original styling) */
        .content-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* For tablets and larger */
        @media (min-width: 768px) {
            .content-wrapper {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1rem;
                min-height: calc(100vh - 2rem);
            }
        }

        /* For desktops */
        @media (min-width: 992px) {
            body {
                overflow-y: auto;
            }
            .content-wrapper {
                display: grid;
                grid-template-columns: 1fr 300px;
                gap: 1rem;
                min-height: calc(100vh - 2rem);
            }
        }
        
        /* Vehicle service widget styles */
        .vehicle-service-widget {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        /* Sidebar stat cards are vertical */
        .sidebar .stats-container {
            grid-template-columns: 1fr;
        }
        
        .stat-card {
            background-color: var(--darker-bg);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--accent-purple);
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .stat-card .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .main-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .search-bar {
            background-color: var(--darker-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            width: 100%;
            margin-bottom: 1rem;
        }

        .search-bar:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .table {
            color: var(--text-primary);
            margin: 0;
        }

        .table th {
            background-color: var(--darker-bg);
            color: var(--text-secondary);
            font-weight: 500;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        /* Make the table responsive */
        @media (max-width: 767px) {
            .table-container {
                overflow-x: auto;
            }
            
            .table th, .table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .table th:nth-child(3), 
            .table td:nth-child(3) {
                width: 60px;
            }
            
            .table th:nth-child(4), 
            .table td:nth-child(4) {
                width: 70px;
            }
            
            .table th:nth-child(5), 
            .table td:nth-child(5) {
                width: 40px;
            }
        }

        .editable-cell {
            background-color: transparent;
            border: none;
            padding: 0.25rem 2.5rem 0.25rem 0.25rem;
            width: 120px;
            color: rgb(0, 0, 0);
            text-align: right;
            font-size: 1rem;
            appearance: textfield;
            position: relative;
        }

        @media (max-width: 767px) {
            .editable-cell {
                width: 80px;
                padding: 0.25rem 2rem 0.25rem 0.25rem;
                font-size: 0.9rem;
            }
        }

        /* Style for Webkit browsers (Chrome, Safari) */
        .editable-cell::-webkit-outer-spin-button,
        .editable-cell::-webkit-inner-spin-button {
            opacity: 1;
            margin-left: 10px;
            height: 24px;
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Style for Firefox */
        .editable-cell[type="number"] {
            -moz-appearance: textfield;
            padding-right: 2.5rem;
        }

        .editable-cell:focus {
            outline: none;
            border-radius: 4px;
        }

        .action-buttons-wrapper {
            width: 100%;
            display: flex;
            justify-content: center; /* center the button grid horizontally */
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); /* or use repeat(3, 1fr) for fixed 3-columns */
            gap: 0.75rem;
            width: 100%;
            max-width: 600px; /* You can tweak this to your liking */
        }

        @media (max-width: 767px) {
            .action-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }

        .btn-pay, .btn-print, .btn-quotation, .btn-cancel, .btn-logout, .btn-settings {
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
            /* Improve touch target size on mobile */
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-pay:hover, .btn-print:hover, .btn-quotation:hover, .btn-cancel:hover, .btn-logout:hover, .btn-settings:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-pay {
            background-color: var(--accent-green);
            color: #000;
        }

        .btn-print {
            background-color: var(--accent-blue);
            color: #000;
        }

        .btn-quotation {
            background-color: var(--accent-yellow);
            color: #000;
        }

        .btn-cancel {
            background-color: var(--accent-red);
            color: #000;
        }

        .btn-logout {
            background-color: var(--accent-red);
            color: #000;
        }
        
        .btn-settings {
            background-color: var(--accent-blue);
            color: #000;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .table-container {
            flex: 1;
            overflow-y: auto;
            min-height: 200px; /* Minimum height on mobile */
            position: relative;
        }

        @media (min-width: 992px) {
            .table-container {
                max-height: calc(100vh - 130px);
            }
        }

        .table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: var(--darker-bg);
        }

        .footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            background-color: var(--darker-bg);
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .subtotal {
            display: flex;
            justify-content: space-between;
            font-weight: 500;
            font-size: 1.1rem;
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

        .search-container {
            position: relative;
            margin-bottom: 1rem;
        }

        .suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .suggestion-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .suggestion-item .price {
            color: #666;
            font-size: 0.9em;
        }

        .suggestion-item .stock {
            color: #28a745;
            font-size: 0.9em;
        }

        .suggestion-item .out-of-stock {
            color: #dc3545;
        }
        
        .suggestion-item .service {
            color: #17a2b8;
            font-weight: 500;
        }
        
        .service-item {
            background-color: rgba(23, 162, 184, 0.05);
        }
        
        .badge.bg-info {
            background-color: #17a2b8 !important;
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }

        /* Select2 customization for dark theme */
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

        .customer-section {
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: var(--darker-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        /* Mobile specific styling for buttons */
        @media (max-width: 767px) {
            .d-flex.justify-content-between.gap-2.mb-3 {
                flex-direction: column;
            }
            
            .d-flex.justify-content-between.gap-2.mb-3 button {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            hr {
                margin: 1rem 0;
            }
            
            /* Larger touch targets for mobile */
            .suggestion-item {
                padding: 12px;
                min-height: 44px;
            }
            
            /* Make search dropdown more mobile-friendly */
            .select2-container {
                width: 100% !important;
            }
        }

        /* Mobile navigation panel for small screens */
        .mobile-nav-panel {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--darker-bg);
            border-top: 1px solid var(--border-color);
            padding: 0.5rem;
            z-index: 1000;
        }

        @media (max-width: 767px) {
            .mobile-nav-panel {
                display: flex;
                justify-content: space-around;
            }
            
            body {
                padding-bottom: 60px; /* Space for the nav panel */
            }
            
            .mobile-nav-btn {
                background: transparent;
                border: none;
                color: var(--text-primary);
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
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Left Sidebar (Vehicle Management) -->
        <div class="sidebar">
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-car"></i></div>
                    <div class="stat-value"><?= $vehicleCount ?></div>
                    <div class="stat-label">Registered Vehicles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-value"><?= $jobTotal ?></div>
                    <div class="stat-label">Total Job Cards</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-start"></i></div>
                    <div class="stat-value"><?= $openJobs ?></div>
                    <div class="stat-label">Open Jobs</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                    <div class="stat-value"><?= $inProgress ?></div>
                    <div class="stat-label">Jobs In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= $completed ?></div>
                    <div class="stat-label">Completed Jobs</div>
                </div>
            </div>
            
            <div class="mt-4">
                <button class="btn-print mb-2" style="background-color: var(--accent-purple);" onclick="window.open('../vehicles/vehicles.php', '_blank')">
                    <i class="fas fa-car"></i> Manage Vehicles
                </button>
                <button class="btn-print mb-2" style="background-color: var(--accent-purple);" onclick="window.open('../job_cards/job_cards.php', '_blank')">
                    <i class="fas fa-clipboard-list"></i> Manage Job Cards
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-wrapper">
                <!-- Main Sale Table Section -->
                <div class="main-card mb-3">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item name</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="saleTableBody">
                                <!-- Table rows will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div class="footer">
                        <div class="subtotal">
                            <span>Sub Total</span>
                            <span id="subtotal">LKR 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons Section -->
                <div class="d-flex flex-column">
                    <div>
                        <div class="search-container">
                            <input type="text" class="search-bar" id="searchInput" placeholder="Search products or services...">
                            <div id="suggestions" class="suggestions"></div>
                        </div>
                        
                        <!-- Customer Selection Section -->
                        <div class="customer-section">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label text-secondary">Customer</label>
                                    <button id="refreshCustomersBtn" class="btn btn-sm btn-outline-secondary" type="button">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <select id="customerSelect" class="form-select">
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>" data-info='<?= json_encode($customer) ?>'>
                                            <?= htmlspecialchars($customer['name']) ?> <?= !empty($customer['phone']) ? '(' . htmlspecialchars($customer['phone']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex justify-content-center">
                                <button class="btn-print" onclick="window.open('../customers/view_customers.php', '_blank')">
                                    <i class="fa-solid fa-users"></i> Manage Customers
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="paymentMethod" class="form-label">Payment Method</label>
                            <select class="form-select" id="paymentMethod">
                                <option value="cash" selected>Cash</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                    
                        <div class="d-flex justify-content-between gap-2 mb-3">
                            <button class="btn-pay" id="btnPay">
                                <i class="fa-solid fa-money-bill"></i> Pay
                            </button>
                            <button class="btn-quotation" id="btnQuotation">
                                <i class="fa-solid fa-file-invoice"></i> Quotation
                            </button>
                        </div>
                        
                        <hr style="border-color: var(--border-color); margin: 0 0 1.5rem;">
                        <div class="d-grid">
                            <button class="btn-cancel" id="btnCancel">
                                <i class="fa-solid fa-ban"></i> Cancel
                            </button>
                        </div>
                        <hr style="border-color: var(--border-color); margin: 0 0 1.5rem;">

                        <!-- On mobile, hide these buttons in the main interface -->
                        <div class="action-buttons-wrapper">
                            <div class="action-buttons d-none d-md-grid">
                                <button class="btn-print" onclick="window.open('../inventory/add_product.php', '_blank')">Item Register</button>
                                <button class="btn-print" onclick="window.open('../inventory/GRN.php', '_blank')">Add New Stock</button>
                                <button class="btn-print" onclick="window.open('../inventory/sales_report.php', '_blank')">Sales Report</button>
                                <button class="btn-print" onclick="window.open('../inventory/stock_report.php', '_blank')">Stock Report</button>
                                <button class="btn-print" onclick="window.open('../quotations/view_quotations.php', '_blank')">Quotations</button>
                                <button class="btn-print" onclick="window.open('../returns/returns.php', '_blank')">Returns/Refunds</button>
                                <button class="btn-print" style="background-color: #dc3545;" onclick="window.open('../credit/credit_management.php', '_blank')">Credit Management</button>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <button class="btn-print" style="background-color: var(--accent-blue);" onclick="window.open('../users/user_management.php', '_blank')">
                                     User Management
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- On desktop, show these buttons normally -->
                        <div class="mt-3 mb-4 d-md-none">
                            <div class="d-grid">
                                <button class="btn-print" type="button" data-bs-toggle="collapse" data-bs-target="#moreOptions" aria-expanded="false" aria-controls="moreOptions">
                                    <i class="fa-solid fa-ellipsis"></i> More Options
                                </button>
                            </div>
                            <div class="collapse mt-2" id="moreOptions">
                                <div class="d-grid gap-2">
                                    <button class="btn-print" onclick="window.open('../inventory/add_product.php', '_blank')">
                                        <i class="fa-solid fa-plus-circle"></i> Item Register
                                    </button>
                                    <button class="btn-print" onclick="window.open('../inventory/add_product.php?type=service', '_blank')">
                                        <i class="fa-solid fa-concierge-bell"></i> Add Service
                                    </button>
                                    <button class="btn-print" onclick="window.open('../inventory/GRN.php', '_blank')">
                                        <i class="fa-solid fa-boxes"></i> Add New Stock
                                    </button>
                                    <button class="btn-print" onclick="window.open('../inventory/sales_report.php', '_blank')">
                                        <i class="fa-solid fa-chart-line"></i> Sales Report
                                    </button>
                                    <button class="btn-print" onclick="window.open('../inventory/stock_report.php', '_blank')">
                                        <i class="fa-solid fa-box"></i> Stock Report
                                    </button>
                                    <button class="btn-print" onclick="window.open('../inventory/barcode_labels.php', '_blank')">
                                        <i class="fa-solid fa-barcode"></i> Barcode Labels
                                    </button>
                                    <button class="btn-print" onclick="window.open('../quotations/view_quotations.php', '_blank')">
                                        <i class="fa-solid fa-file-alt"></i> Quotations
                                    </button>
                                    <button class="btn-print" onclick="window.open('../returns/returns.php', '_blank')">
                                        <i class="fa-solid fa-undo"></i> Returns/Refunds
                                    </button>
                                    <button class="btn-print" style="background-color: #dc3545;" onclick="window.open('../credit/credit_management.php', '_self')">
                                        <i class="fa-solid fa-credit-card"></i> Credit Management
                                    </button>
                                    <button class="btn-print" style="background-color: var(--accent-purple);" onclick="window.open('../vehicles/vehicles.php', '_blank')">
                                        <i class="fas fa-car"></i> Vehicle Management
                                    </button>
                                    <button class="btn-print" style="background-color: var(--accent-purple);" onclick="window.open('../job_cards/job_cards.php', '_blank')">
                                        <i class="fas fa-clipboard-list"></i> Job Cards
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-auto">
                        <div class="d-grid gap-2 mb-3">
                            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <button class="btn-settings" onclick="window.location.href='business_settings.php'">
                                <i class="fas fa-store"></i> Business Settings
                            </button>
                            <?php endif; ?>
                            <button class="btn-logout" onclick="window.location.href='logout.php'">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation Panel -->
    <div class="mobile-nav-panel d-md-none">
        <button class="mobile-nav-btn" id="mobileSearchBtn">
            <i class="fa-solid fa-search"></i>
            <span>Search</span>
        </button>
        <button class="mobile-nav-btn" id="mobileCartBtn">
            <i class="fa-solid fa-shopping-cart"></i>
            <span>Cart</span>
        </button>
        <button class="mobile-nav-btn" id="mobileCustomerBtn">
            <i class="fa-solid fa-user"></i>
            <span>Customer</span>
        </button>
        <button class="mobile-nav-btn" onclick="window.location.href='../vehicles/vehicles.php'">
            <i class="fa-solid fa-car"></i>
            <span>Vehicles</span>
        </button>
        <button class="mobile-nav-btn" onclick="window.open('../credit/credit_management.php', '_self')">
            <i class="fa-solid fa-credit-card"></i>
            <span>Credit</span>
        </button>
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <button class="mobile-nav-btn" onclick="window.location.href='business_settings.php'">
            <i class="fa-solid fa-store"></i>
            <span>Settings</span>
        </button>
        <?php endif; ?>
        <button class="mobile-nav-btn" id="mobilePayBtn">
            <i class="fa-solid fa-money-bill"></i>
            <span>Pay</span>
        </button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
    <script>
        let currentSale = []; // Initialize empty sale
        let selectedCustomer = null;
        const searchInput = document.getElementById('searchInput');
        const suggestionsContainer = document.getElementById('suggestions');
        let debounceTimer;
        let searchResults = []; // Store the full search results
        let isMobile = window.innerWidth < 768;

        $(document).ready(function() {
            // Initialize Select2 for customer dropdown
            $('#customerSelect').select2({
                theme: 'default',
                placeholder: 'Search for a customer...',
                allowClear: true,
                width: '100%'
            });

            // Listen for customer selection change
            $('#customerSelect').on('change', function() {
                const customerId = $(this).val();
                if (customerId) {
                    const selectedOption = $(this).find(':selected');
                    selectedCustomer = JSON.parse(selectedOption.attr('data-info'));
                    console.log("Selected customer:", selectedCustomer);
                } else {
                    selectedCustomer = null;
                }
            });

            // Handle window resize to check if mobile or desktop
            $(window).resize(function() {
                isMobile = window.innerWidth < 768;
            });

                // Set up periodic refresh every 30 seconds
            setInterval(refreshCustomerDropdown, 30000);
            
            // Option to manually refresh
            $('#refreshCustomersBtn').on('click', function() {
                refreshCustomerDropdown();
            });

            // Mobile navigation handlers
            $('#mobileSearchBtn').click(function() {
                $('html, body').animate({
                    scrollTop: $('#searchInput').offset().top - 20
                }, 200);
                $('#searchInput').focus();
            });

            $('#mobileCartBtn').click(function() {
                $('html, body').animate({
                    scrollTop: $('.main-card').offset().top - 20
                }, 200);
            });

            $('#mobileCustomerBtn').click(function() {
                $('html, body').animate({
                    scrollTop: $('.customer-section').offset().top - 20
                }, 200);
                $('#customerSelect').select2('open');
            });

            $('#mobilePayBtn').click(function() {
                $('#btnPay').click();
            });
        });

        function renderTableRows() {
            const tableBody = document.getElementById('saleTableBody');
            tableBody.innerHTML = ''; // Clear existing rows

            currentSale.forEach(item => {
                const isService = item.item_type === 'service';
                const row = document.createElement('tr');
                
                if (isService) {
                    row.classList.add('service-item');
                }
                
                row.innerHTML = `
                    <td>
                        ${item.name}
                        ${isService ? '<span class="badge bg-info ms-1">Service</span>' : ''}
                    </td>
                    <td><input type="number" class="editable-cell" value="${item.price}" step="0.01" data-id="${item.id}" data-field="price"></td>
                    <td><input type="number" class="editable-cell" value="${item.qty}" min="1" data-id="${item.id}" data-field="qty"></td>
                    <td>${(item.price * item.qty).toFixed(2)}</td>
                    <td>
                        <button class="btn-remove" data-id="${item.id}">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            updateTotal();
        }

        function updateTotal() {
            const subtotal = currentSale.reduce((sum, item) => sum + (item.price * item.qty), 0);
            document.getElementById('subtotal').textContent = `LKR ${subtotal.toFixed(2)}`;
        }

        function handleInputChange(event) {
            const input = event.target;
            if (!input.classList.contains('editable-cell')) return;

            const itemId = parseInt(input.dataset.id);
            const field = input.dataset.field;
            const value = parseFloat(input.value) || 0;

            const item = currentSale.find(item => item.id === itemId);
            if (item) {
                item[field] = value;
                // Update the total for this row
                const row = input.closest('tr');
                const totalCell = row.cells[3]; // Index 3 is the total column
                totalCell.textContent = `${(item.price * item.qty).toFixed(2)}`;
                updateTotal();
            }
        }

        async function fetchSuggestions(searchTerm) {
            try {
                const response = await fetch(`search.php?term=${encodeURIComponent(searchTerm)}`);
                if (!response.ok) throw new Error('Network response was not ok');
                const data = await response.json();
                console.log('Search response:', data); // Debug log to see the exact data structure
                return data;
            } catch (error) {
                console.error('Error fetching suggestions:', error);
                return [];
            }
        }

        // Function to refresh customer dropdown
            function refreshCustomerDropdown() {
                // Add timestamp to prevent caching
                const timestamp = new Date().getTime();
                
                fetch(`get_customers.php?timestamp=${timestamp}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.customers) {
                            updateCustomerDropdown(data.customers);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching customers:', error);
                    });
            }

            // Function to update the customer dropdown with new data
            function updateCustomerDropdown(customers) {
                const dropdown = $('#customerSelect');
                
                // Store the currently selected value
                const currentSelection = dropdown.val();
                
                // Clear existing options except for the first one
                dropdown.find('option:not(:first)').remove();
                
                // Add new options
                customers.forEach(customer => {
                    const phoneDisplay = customer.phone ? ` (${customer.phone})` : '';
                    const option = new Option(
                        `${customer.name}${phoneDisplay}`, 
                        customer.id
                    );
                    
                    // Add the data-info attribute with JSON data
                    $(option).attr('data-info', JSON.stringify(customer));
                    
                    // Add to dropdown
                    dropdown.append(option);
                });
                
                // Restore previous selection if it exists
                if (currentSelection) {
                    dropdown.val(currentSelection).trigger('change');
                }
                
                // Refresh the Select2 instance
                dropdown.trigger('select2:destroy').select2({
                    theme: 'default',
                    placeholder: 'Search for a customer...',
                    allowClear: true,
                    width: '100%'
                });
            }

        function displaySuggestions(suggestions) {
            // Store the full results for later use
            searchResults = suggestions;

            if (suggestions.length === 0) {
                suggestionsContainer.style.display = 'none';
                return;
            }

            suggestionsContainer.innerHTML = suggestions.map(item => {
                const isService = item.item_type === 'service';
                const stockDisplay = isService 
                    ? `<div class="stock service">Service</div>` 
                    : `<div class="${parseInt(item.quantity) > 0 ? 'stock' : 'stock out-of-stock'}">
                        ${parseInt(item.quantity) > 0 ? 'In Stock: ' + item.quantity : 'Out of Stock'}
                      </div>`;
                      
                return `
                    <div class="suggestion-item" data-id="${item.id}">
                        <strong style="color:black">${item.name}</strong>
                        <div class="price">Price: LKR ${parseFloat(item.selling_price).toFixed(2)}</div>
                        ${stockDisplay}
                        <small>${item.category || ''}</small>
                    </div>
                `;
            }).join('');

            suggestionsContainer.style.display = 'block';
        }

        function addItemToSale(itemData) {
            console.log('Adding item:', itemData); // Debug log
            
            // Get price and cost, with fallback for different field names
            const price = parseFloat(itemData.selling_price || itemData.price);
            const cost = parseFloat(itemData.purchase_price || itemData.cost);
            const isService = itemData.item_type === 'service';
            
            if (isNaN(price)) {
                console.error('Invalid price:', itemData);
                return;
            }

            // For products (non-services), check if there's enough stock
            if (!isService && parseInt(itemData.quantity) <= 0) {
                alert('This product is out of stock');
                return;
            }

            const existingItem = currentSale.find(saleItem => saleItem.id === parseInt(itemData.id));
            
            if (existingItem) {
                // For products, check stock before incrementing
                if (!isService && existingItem.qty >= parseInt(itemData.quantity)) {
                    alert('Cannot add more of this product. Maximum available quantity reached.');
                    return;
                }
                existingItem.qty += 1;
            } else {
                currentSale.push({
                    id: parseInt(itemData.id),
                    name: itemData.name,
                    price: price,
                    cost: cost || 0, // Fallback to 0 if cost is not available
                    qty: 1,
                    item_type: itemData.item_type || 'product' // Store the item type
                });
            }
            
            console.log('Current sale after add:', currentSale); // Debug log
            renderTableRows();
            
            // For mobile: scroll to the table to show the added item
            if (isMobile) {
                setTimeout(() => {
                    $('html, body').animate({
                        scrollTop: $('.main-card').offset().top - 20
                    }, 200);
                }, 100);
            }
        }

        // Event listener for input changes
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.trim();
            clearTimeout(debounceTimer);
            
            if (searchTerm.length < 2) {
                suggestionsContainer.style.display = 'none';
                return;
            }
            
            debounceTimer = setTimeout(async () => {
                const suggestions = await fetchSuggestions(searchTerm);
                displaySuggestions(suggestions);
            }, 300);
        });

        searchInput.addEventListener('keypress', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchTerm = e.target.value.trim();
                
                if (searchTerm.length < 2) return;
                
                // Check if it's a numeric ID - prioritize exact match search
                if (searchTerm.match(/^\d+$/)) {
                    const suggestions = await fetchSuggestions(searchTerm);
                    
                    // If we found exactly one product with this ID, add it immediately
                    if (suggestions.length === 1) {
                        addItemToSale(suggestions[0]);
                        searchInput.value = '';
                        suggestionsContainer.style.display = 'none';
                        return;
                    }
                }
                
                // If not a direct ID match, show all results
                const suggestions = await fetchSuggestions(searchTerm);
                displaySuggestions(suggestions);
            }
        });

        // Event listener for clicking outside
        document.addEventListener('click', (e) => {
            if (!suggestionsContainer.contains(e.target) && e.target !== searchInput) {
                suggestionsContainer.style.display = 'none';
            }
        });

        // Event listener for suggestion selection
        suggestionsContainer.addEventListener('click', (e) => {
            try {
                const suggestionItem = e.target.closest('.suggestion-item');
                if (suggestionItem) {
                    const productId = parseInt(suggestionItem.dataset.id);
                    console.log('Selected product ID:', productId);
                    
                    // Find the full product data from searchResults
                    const productData = searchResults.find(item => parseInt(item.id) === productId);
                    console.log('Found product data:', productData);
                    
                    if (productData) {
                        addItemToSale(productData);
                        searchInput.value = '';
                        suggestionsContainer.style.display = 'none';
                    } else {
                        console.error('Product not found in searchResults. Available results:', searchResults);
                    }
                }
            } catch (error) {
                console.error('Error in click handler:', error);
            }
        });

        function removeItem(itemId) {
            currentSale = currentSale.filter(item => item.id !== itemId);
            renderTableRows();
        }

        function handleRemoveClick(event) {
            const removeButton = event.target.closest('.btn-remove');
            if (removeButton) {
                const itemId = parseInt(removeButton.dataset.id);
                removeItem(itemId);
            }
        }

        // Save sale function
        async function saveSale() {
            if (currentSale.length === 0) {
                alert('No items in sale');
                return;
            }

            try {
                const paymentMethod = document.getElementById('paymentMethod').value;
                const requestData = {
                    items: currentSale,
                    customer_id: selectedCustomer ? selectedCustomer.id : null,
                    customer_info: selectedCustomer,
                    payment_method: paymentMethod
                };
                console.log('Sending data:', requestData);

                const response = await fetch('save_sale.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });

                const responseText = await response.text();
                console.log('Raw response:', responseText);

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.log('Failed to parse response:', responseText);
                    throw new Error('Invalid response format from server');
                }

                if (result.success) {
                    alert(`Sale completed successfully!\nTotal Amount: LKR ${parseFloat(result.total_amount).toFixed(2)}`);
                    // Open proper invoice page in a new window
                    window.open(`../inventory/generate_invoice.php?id=${result.sale_id}`, '_blank');
                    // Clear the current sale
                    currentSale = [];
                    renderTableRows();
                    // Clear customer selection
                    $('#customerSelect').val('').trigger('change');
                    selectedCustomer = null;
                } else {
                    throw new Error(result.error || 'Failed to save sale');
                }
            } catch (error) {
                console.error('Error saving sale:', error);
                alert('Failed to save sale: ' + error.message);
            }
        }

        // Save quotation function
        async function saveQuotation() {
            if (currentSale.length === 0) {
                alert('No items in quotation');
                return;
            }

            try {
                const requestData = {
                    items: currentSale,
                    customer_id: selectedCustomer ? selectedCustomer.id : null,
                    customer_info: selectedCustomer
                };
                console.log('Sending quotation data:', requestData);

                const response = await fetch('../quotations/save_quotation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });

                const responseText = await response.text();
                console.log('Raw response:', responseText);

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.log('Failed to parse response:', responseText);
                    throw new Error('Invalid response format from server');
                }

                if (result.success) {
                    alert(`Quotation created successfully!\nTotal Amount: LKR ${parseFloat(result.total_amount).toFixed(2)}`);
                    // Open quotation in new window
                    window.open(`../quotations/generate_quotation.php?id=${result.quotation_id}`, '_blank');
                    // Clear the current sale
                    currentSale = [];
                    renderTableRows();
                    // Clear customer selection
                    $('#customerSelect').val('').trigger('change');
                    selectedCustomer = null;
                } else {
                    throw new Error(result.error || 'Failed to create quotation');
                }
            } catch (error) {
                console.error('Error creating quotation:', error);
                alert('Failed to create quotation: ' + error.message);
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('saleTableBody').addEventListener('input', handleInputChange);
            document.getElementById('saleTableBody').addEventListener('click', handleRemoveClick);
        });

        // Add event listener for pay button
        document.getElementById('btnPay').addEventListener('click', saveSale);
        
        // Add event listener for quotation button
        document.getElementById('btnQuotation').addEventListener('click', saveQuotation);
        
        // Add event listener for cancel button
        document.getElementById('btnCancel').addEventListener('click', () => {
            currentSale = [];
            renderTableRows();
            // Clear customer selection
            $('#customerSelect').val('').trigger('change');
            selectedCustomer = null;
        });
    </script>
</body>
</html>