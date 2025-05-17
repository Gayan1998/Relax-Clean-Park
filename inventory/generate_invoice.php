<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin/login.php");
    exit();
}
include '../includes/db_connection.php';
include '../includes/functions.php';

// Get business settings
$business = get_business_settings();

if (!isset($_GET['id'])) {
    die('No invoice ID provided');
}

$sale_id = intval($_GET['id']);

// Fetch sale data
$sale_query = "SELECT s.*, 
               DATE_FORMAT(s.sale_date, '%Y-%m-%d') as formatted_date,
               s.customer_id,
               s.job_number
               FROM sales s 
               WHERE s.id = :sale_id";
$stmt = $pdo->prepare($sale_query);
$stmt->execute(['sale_id' => $sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('Invoice not found');
}

// Fetch customer data if customer_id exists
$customer = null;
if (!empty($sale['customer_id'])) {
    $customer_query = "SELECT name, phone, email, address 
                      FROM customers 
                      WHERE id = :customer_id";
    $stmt = $pdo->prepare($customer_query);
    $stmt->execute(['customer_id' => $sale['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch vehicle and job card information if this sale was created from a job card
$vehicle_info = null;
$job_card = null;
if (!empty($sale['job_number'])) {
    $job_query = "SELECT j.*, 
                  v.make, v.model, v.year, v.registration_number, v.color, v.vin,
                  j.current_mileage,
                  j.next_service_mileage
                  FROM job_cards j
                  LEFT JOIN vehicles v ON j.vehicle_id = v.id
                  WHERE j.job_number = :job_number";
    $stmt = $pdo->prepare($job_query);
    $stmt->execute(['job_number' => $sale['job_number']]);
    $job_card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($job_card && !empty($job_card['vehicle_id'])) {
        // Create the vehicle_info array with job card data
        $vehicle_info = $job_card;
        
        // Use the mileage values from sales table if available (they were stored during job card conversion)
        if (!empty($sale['current_mileage'])) {
            $vehicle_info['current_mileage'] = $sale['current_mileage'];
        }
        
        if (!empty($sale['next_service_mileage'])) {
            $vehicle_info['next_service_mileage'] = $sale['next_service_mileage'];
        }
    }
}

// Fetch sale items
$items_query = "SELECT si.*, p.name as product_name, p.item_type 
                FROM sale_items si 
                JOIN products p ON si.product_id = p.id 
                WHERE si.sale_id = :sale_id";
$stmt = $pdo->prepare($items_query);
$stmt->execute(['sale_id' => $sale_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for any returns associated with this sale
$returns_query = "SELECT r.*, DATE_FORMAT(r.return_date, '%Y-%m-%d') as formatted_date 
                 FROM returns r 
                 WHERE r.sale_id = :sale_id 
                 ORDER BY r.return_date DESC";
$stmt = $pdo->prepare($returns_query);
$stmt->execute(['sale_id' => $sale_id]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$has_returns = !empty($returns);
$total_returned_amount = 0;

// Get all returned items for this sale
$returned_items = [];

if ($has_returns) {
    $return_ids = array_column($returns, 'id');
    $return_ids_str = implode(',', $return_ids);
    
    $returned_items_query = "SELECT ri.*, r.return_date, p.name as product_name 
                           FROM return_items ri 
                           JOIN returns r ON ri.return_id = r.id
                           JOIN products p ON ri.product_id = p.id
                           WHERE ri.return_id IN ($return_ids_str)";
    $stmt = $pdo->query($returned_items_query);
    $all_returned_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize returned items by product_id
    foreach ($all_returned_items as $item) {
        if (!isset($returned_items[$item['product_id']])) {
            $returned_items[$item['product_id']] = 0;
        }
        
        $returned_items[$item['product_id']] += $item['quantity'];
        $total_returned_amount += $item['total_price'];
    }
}

// Calculate net amount
$net_amount = $sale['total_amount'] - $total_returned_amount;

// Filter and adjust items to remove returns
$adjusted_items = [];
$displayable_total = 0;

foreach ($items as $item) {
    $product_id = $item['product_id'];
    $original_qty = $item['quantity'];
    $returned_qty = isset($returned_items[$product_id]) ? $returned_items[$product_id] : 0;
    $remaining_qty = $original_qty - $returned_qty;
    
    // Only include this item if there are remaining quantities
    if ($remaining_qty > 0) {
        // Create an adjusted item with the remaining quantity
        $adjusted_item = $item;
        $adjusted_item['quantity'] = $remaining_qty;
        $adjusted_item['total_price'] = $remaining_qty * $item['price'];
        
        $adjusted_items[] = $adjusted_item;
        $displayable_total += $adjusted_item['total_price'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $sale_id; ?> - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* A4 page size settings */
        body {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 0;
            font-size: 0.85rem;
        }
        
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
                width: 100%;
                height: auto;
            }
            .no-print {
                display: none !important;
            }
            .container {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 8mm !important;
            }
        }

        /* Responsive styles for mobile */
        @media screen and (max-width: 767px) {
            body {
                width: 100%;
                height: auto;
            }
            .container {
                padding: 10px !important;
            }
            .logo-section, .company-title, .invoice-details, .company-contact, .customer-details {
                text-align: left !important;
                margin-bottom: 10px;
            }
        }

        .container {
            max-width: 210mm !important;
            padding: 8mm !important;
        }

        .invoice-header {
            padding: 0.5rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .header-divider {
            height: 4px;
            background-color: #8B0000;
            margin-top: 0.5rem;
        }

        .logo-section {
            padding-right: 5px;
        }

        .company-title {
            color: #8B0000;
            font-weight: 700;
            font-size: 1.5rem;
            text-transform: uppercase;
            margin: 0;
            line-height: 1.2;
        }

        .company-contact, .customer-details {
            font-size: 0.8rem;
            line-height: 1.3;
        }

        .company-contact p, .customer-details p {
            margin: 0;
        }

        .invoice-details {
            font-size: 0.8rem;
            line-height: 1.3;
        }

        .invoice-details p {
            margin: 0;
        }

        .company-logo {
            max-height: 60px; /* Reduced for compactness */
        }

        .table th {
            background-color: #f8f9fa;
            font-size: 0.85rem;
        }

        .table td {
            font-size: 0.85rem;
        }

        .total-section {
            background-color: #f8f9fa;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-top: 1.5rem;
        }

        .total-section table {
            font-size: 0.85rem;
        }

        .footer {
            margin-top: 2rem;
            padding-top: 0.75rem;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.8rem;
        }
        
        .return-note {
            font-size: 0.8rem;
            color: #6c757d;
            font-style: italic;
            text-align: right;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Button -->
        <div class="row mb-2 no-print">
            <div class="col-12">
                <button onclick="window.print()" class="btn btn-primary btn-sm float-end">
                    Print Invoice
                </button>
                <a href="javascript:history.back()" class="btn btn-secondary btn-sm me-2 float-end">Back</a>
            </div>
        </div>

        <!-- Invoice Header -->
        <div class="invoice-header">
            <!-- Top Row: Logo + Company Title | Invoice Details -->
            <div class="row align-items-center mb-2">
                <div class="col-8">
                    <div class="d-flex align-items-center">
                        <div class="logo-section">
                            <img src="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo" class="company-logo img-fluid">
                        </div>
                        <h1 class="company-title ms-2"><?php echo htmlspecialchars($business['business_name']); ?></h1>
                    </div>
                </div>
                <div class="col-4 text-end">
                    <div class="invoice-details">
                        <p><strong>Invoice #:</strong> <?php echo $sale_id; ?></p>
                        <p><strong>Date:</strong> <?php echo $sale['formatted_date']; ?></p>
                        <p><strong>Payment:</strong> <?php echo ucfirst(htmlspecialchars($sale['payment_method'] ?? 'Cash')); ?></p>
                        <?php if (isset($sale['payment_method']) && $sale['payment_method'] === 'credit'): ?>
                        <p>
                            <strong>Status:</strong> 
                            <span class="<?php echo $sale['payment_status'] === 'paid' ? 'text-success' : ($sale['payment_status'] === 'partial' ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo ucfirst(htmlspecialchars($sale['payment_status'] ?? 'Unpaid')); ?>
                            </span>
                        </p>
                        <?php if ($sale['payment_status'] === 'partial' && isset($sale['amount_paid']) && isset($sale['total_amount'])): ?>
                            <p>
                                <strong>Paid:</strong> <?php echo number_format($sale['amount_paid'], 2); ?> of <?php echo number_format($sale['total_amount'], 2); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($sale['payment_status'] === 'paid' && isset($sale['payment_date']) && !empty($sale['payment_date'])): ?>
                            <p><strong>Paid on:</strong> <?php echo date('Y-m-d', strtotime($sale['payment_date'])); ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Bottom Row: Company Contact | Customer Details -->
            <div class="row">
                <div class="col-6">
                    <div class="company-contact">
                        <?php if (!empty($business['tagline'])): ?>
                            <p><?php echo htmlspecialchars($business['tagline']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['address'])): ?>
                            <p><?php echo htmlspecialchars($business['address']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['phone'])): ?>
                            <p>Hot Line: <?php echo htmlspecialchars($business['phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['email'])): ?>
                            <p>Email: <?php echo htmlspecialchars($business['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['registration_number'])): ?>
                            <p>Reg No: <?php echo htmlspecialchars($business['registration_number']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6 text-end">
                    <div class="customer-details">
                        <?php if ($customer): ?>
                            <p><strong><?php echo htmlspecialchars($customer['name']); ?></strong><?php echo !empty($customer['phone']) ? ' | ' . htmlspecialchars($customer['phone']) : ''; ?></p>
                            <?php if (!empty($customer['email'])): ?>
                                <p><?php echo htmlspecialchars($customer['email']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($customer['address'])): ?>
                                <p><?php echo htmlspecialchars($customer['address']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Walk-in Customer</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="header-divider"></div>
        </div>

        <?php if ($vehicle_info): ?>
        <!-- Vehicle Information (if from job card) -->
        <div class="row mt-3 mb-3">
            <div class="col-12">
                <div class="card p-3 bg-light">
                    <h6 class="mb-2"><strong>Vehicle Information</strong></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Registration:</strong> <?php echo htmlspecialchars($vehicle_info['registration_number']); ?></p>
                            <p class="mb-1"><strong>Make/Model:</strong> <?php echo htmlspecialchars($vehicle_info['make'] . ' ' . $vehicle_info['model'] . ' ' . $vehicle_info['year']); ?></p>
                            <?php if (!empty($vehicle_info['vin'])): ?>
                            <p class="mb-1"><strong>VIN/Chassis:</strong> <?php echo htmlspecialchars($vehicle_info['vin']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Current Mileage:</strong> <?php echo number_format($vehicle_info['current_mileage']); ?> km</p>
                            <?php if (!empty($vehicle_info['next_service_mileage'])): ?>
                            <p class="mb-1"><strong>Next Service:</strong> <?php echo number_format($vehicle_info['next_service_mileage']); ?> km</p>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Invoice Items (Already adjusted for returns) -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($adjusted_items) > 0): ?>
                        <?php foreach ($adjusted_items as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end">LKR <?php echo number_format($item['price'], 2); ?></td>
                            <td class="text-end">LKR <?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">All items have been returned</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Section -->
        <div class="row">
            <div class="col-md-6 offset-md-6">
                <div class="total-section">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-end"><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong>LKR <?php echo number_format($displayable_total, 2); ?></strong></td>
                        </tr>
                        <?php if (isset($sale['payment_method']) && $sale['payment_method'] === 'credit'): ?>
                        <tr>
                            <td class="text-end <?php echo $sale['payment_status'] === 'paid' ? 'text-success' : ($sale['payment_status'] === 'partial' ? 'text-warning' : 'text-danger'); ?>">
                                <strong>Payment Status:</strong>
                            </td>
                            <td class="text-end <?php echo $sale['payment_status'] === 'paid' ? 'text-success' : ($sale['payment_status'] === 'partial' ? 'text-warning' : 'text-danger'); ?>">
                                <strong>
                                    <?php if ($sale['payment_status'] === 'paid' && !empty($sale['payment_date'])): ?>
                                        Paid in Full
                                    <?php elseif ($sale['payment_status'] === 'partial'): ?>
                                        Partially Paid (<?php echo number_format($sale['amount_paid'], 2); ?>)
                                    <?php else: ?>
                                        Credit (Payment Due)
                                    <?php endif; ?>
                                </strong>
                            </td>
                        </tr>
                        <?php if ($sale['payment_status'] === 'partial'): ?>
                        <tr>
                            <td class="text-end text-warning"><strong>Balance Due:</strong></td>
                            <td class="text-end text-warning"><strong>LKR <?php echo number_format($displayable_total - $sale['amount_paid'], 2); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                    </table>
                    <?php if ($has_returns): ?>
                    <div class="return-note">
                        Note: This invoice reflects adjustments for returned items.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($sale['payment_method']) && $sale['payment_method'] === 'credit'): ?>
                    <div class="credit-note" style="margin-top: 0.5rem; <?php echo $sale['payment_status'] === 'paid' ? 'color: #198754;' : 'color: #dc3545;'; ?> font-weight: bold; text-align: right; font-size: 0.9rem;">
                        <?php if($sale['payment_status'] === 'paid' && !empty($sale['payment_date'])): ?>
                            This invoice was issued on credit and has been paid in full on <?php echo date('F j, Y', strtotime($sale['payment_date'])); ?>.
                        <?php elseif($sale['payment_status'] === 'partial'): ?>
                            This invoice was issued on credit. A partial payment of LKR <?php echo number_format($sale['amount_paid'], 2); ?> has been received. 
                            Remaining balance: LKR <?php echo number_format($displayable_total - $sale['amount_paid'], 2); ?>.
                        <?php else: ?>
                            This invoice has been issued on credit. Payment is due upon receipt.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="text-muted mb-0">Thank you for your business!</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>