<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../includes/db_connection.php';
include '../includes/functions.php';

// Get business settings
$business = get_business_settings();

if (!isset($_GET['return_id'])) {
    die('No return ID provided');
}

$return_id = intval($_GET['return_id']);

// Fetch return data
$return_query = "SELECT r.*, 
                DATE_FORMAT(r.return_date, '%Y-%m-%d') as formatted_date,
                s.id as sale_id, 
                DATE_FORMAT(s.sale_date, '%Y-%m-%d') as sale_date,
                c.name as customer_name, c.phone as customer_phone, 
                c.email as customer_email, c.address as customer_address
                FROM returns r 
                JOIN sales s ON r.sale_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE r.id = :return_id";
$stmt = $pdo->prepare($return_query);
$stmt->execute(['return_id' => $return_id]);
$return = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$return) {
    die('Return not found');
}

// Fetch return items
$items_query = "SELECT ri.*, p.name as product_name 
                FROM return_items ri 
                JOIN products p ON ri.product_id = p.id 
                WHERE ri.return_id = :return_id";
$stmt = $pdo->prepare($items_query);
$stmt->execute(['return_id' => $return_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Receipt #<?php echo $return_id; ?> - <?php echo htmlspecialchars($business['business_name']); ?></title>
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
            .logo-section, .company-title, .receipt-details, .company-contact, .customer-details {
                text-align: left !important;
                margin-bottom: 10px;
            }
        }

        .container {
            max-width: 210mm !important;
            padding: 8mm !important;
        }

        .receipt-header {
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

        .receipt-details {
            font-size: 0.8rem;
            line-height: 1.3;
        }

        .receipt-details p {
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
        
        .return-title {
            color: #dc3545;
        }
        
        .original-sale-info {
            background-color: #f8f9fa;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        
        .signature-section {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px dashed #dee2e6;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Button -->
        <div class="row mb-2 no-print">
            <div class="col-12">
                <button onclick="window.print()" class="btn btn-primary btn-sm float-end">
                    Print Receipt
                </button>
                <a href="returns.php" class="btn btn-secondary btn-sm me-2 float-end">Back to Returns</a>
            </div>
        </div>

        <!-- Receipt Header -->
        <div class="receipt-header">
            <!-- Top Row: Logo + Company Title | Receipt Details -->            <div class="row align-items-center mb-2">
                <div class="col-8">
                    <div class="d-flex align-items-center">
                        <div class="logo-section">
                            <img src="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo" class="company-logo img-fluid">
                        </div>
                        <h1 class="company-title ms-2"><?php echo htmlspecialchars($business['business_name']); ?></h1>
                    </div>
                </div>
                <div class="col-4 text-end">
                    <div class="receipt-details">
                        <h2 class="text-uppercase return-title" style="font-size: 1.1rem; margin-bottom: 0.2rem;">Return Receipt</h2>
                        <p><strong>Return #:</strong> <?php echo $return_id; ?></p>
                        <p><strong>Date:</strong> <?php echo $return['formatted_date']; ?></p>
                        <p><strong>Original Sale #:</strong> <?php echo $return['sale_id']; ?></p>
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
                        <?php if ($return['customer_name']): ?>
                            <p><strong><?php echo htmlspecialchars($return['customer_name']); ?></strong><?php echo !empty($return['customer_phone']) ? ' | ' . htmlspecialchars($return['customer_phone']) : ''; ?></p>
                            <?php if (!empty($return['customer_email'])): ?>
                                <p><?php echo htmlspecialchars($return['customer_email']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($return['customer_address'])): ?>
                                <p><?php echo htmlspecialchars($return['customer_address']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Walk-in Customer</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="header-divider"></div>
        </div>
        
        <!-- Original Sale Information -->
        <div class="original-sale-info">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Original Sale #:</strong> <?php echo $return['sale_id']; ?></p>
                    <p><strong>Sale Date:</strong> <?php echo $return['sale_date']; ?></p>
                </div>
                <div class="col-md-6">
                    <?php if (!empty($return['notes'])): ?>
                    <p><strong>Return Notes:</strong> <?php echo htmlspecialchars($return['notes']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Return Items -->
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
                    <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end">LKR <?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-end">LKR <?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Section -->
        <div class="row">
            <div class="col-md-6 offset-md-6">
                <div class="total-section">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-end"><strong>Total Refund Amount:</strong></td>
                            <td class="text-end"><strong>LKR <?php echo number_format($return['total_amount'], 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="row justify-content-between">
                <div class="col-5 text-center">
                    <span class="signature-line"></span>
                    <p>Customer Signature</p>
                </div>
                <div class="col-5 text-center">
                    <span class="signature-line"></span>
                    <p>Authorized Signature</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="text-muted mb-0">This is an official return receipt. Thank you for your business!</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>