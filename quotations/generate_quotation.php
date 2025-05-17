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

if (!isset($_GET['id'])) {
    die('No quotation ID provided');
}

$quotation_id = intval($_GET['id']);

// Fetch quotation data
$quote_query = "SELECT q.*, 
               DATE_FORMAT(q.quote_date, '%Y-%m-%d') as formatted_date,
               DATE_FORMAT(q.valid_until, '%Y-%m-%d') as formatted_valid_until,
               c.name as customer_name, c.phone as customer_phone, 
               c.email as customer_email, c.address as customer_address
               FROM quotations q 
               LEFT JOIN customers c ON q.customer_id = c.id
               WHERE q.id = :quotation_id";
$stmt = $pdo->prepare($quote_query);
$stmt->execute(['quotation_id' => $quotation_id]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    die('Quotation not found');
}

// Check if expired
$isExpired = strtotime($quotation['valid_until']) < time() && $quotation['status'] === 'pending';
if ($isExpired) {
    // Update status to expired
    $updateStmt = $pdo->prepare("UPDATE quotations SET status = 'expired' WHERE id = ? AND status = 'pending'");
    $updateStmt->execute([$quotation_id]);
    $quotation['status'] = 'expired';
}

// Fetch quotation items
$items_query = "SELECT qi.*, p.name as product_name 
                FROM quotation_items qi 
                JOIN products p ON qi.product_id = p.id 
                WHERE qi.quotation_id = :quotation_id";
$stmt = $pdo->prepare($items_query);
$stmt->execute(['quotation_id' => $quotation_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['total_price'];
}

// Get status label and class
$statusLabels = [
    'pending' => 'Pending',
    'accepted' => 'Accepted',
    'rejected' => 'Rejected',
    'expired' => 'Expired',
    'converted' => 'Converted to Sale'
];

$statusClass = [
    'pending' => 'primary',
    'accepted' => 'success',
    'rejected' => 'danger',
    'expired' => 'secondary',
    'converted' => 'info'
];

$status = $isExpired ? 'expired' : $quotation['status'];
$statusLabel = $statusLabels[$status] ?? 'Unknown';
$statusColorClass = $statusClass[$status] ?? 'secondary';

?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #<?php echo $quotation_id; ?> - <?php echo htmlspecialchars($business['business_name']); ?></title>
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
            .logo-section, .company-title, .quotation-details, .company-contact, .customer-details {
                text-align: left !important;
                margin-bottom: 10px;
            }
        }

        .container {
            max-width: 210mm !important;
            padding: 8mm !important;
        }

        .quotation-header {
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

        .quotation-details {
            font-size: 0.8rem;
            line-height: 1.3;
        }

        .quotation-details p {
            margin: 0;
        }

        .company-logo {
            max-height: 60px; /* Reduced for compactness */
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
            border-radius: 0.25rem;
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

        .validity-note {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        .footer {
            margin-top: 2rem;
            padding-top: 0.75rem;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.8rem;
        }

        .terms-section {
            font-size: 0.8rem;
            margin-top: 2rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
        }

        .customer-info {
            background-color: #f8f9fa;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Button -->
        <div class="row mb-2 no-print">
            <div class="col-12">
                <button onclick="window.print()" class="btn btn-primary btn-sm float-end">
                    Print Quotation
                </button>
                <a href="view_quotations.php" class="btn btn-secondary btn-sm me-2 float-end">Back to Quotations</a>
            </div>
        </div>

        <!-- Quotation Header -->
        <div class="quotation-header">
            <!-- Top Row: Logo + Company Title | Quotation Details -->            <div class="row align-items-center mb-2">
                <div class="col-8">
                    <div class="d-flex align-items-center">
                        <div class="logo-section">
                            <img src="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo" class="company-logo img-fluid">
                        </div>
                        <h1 class="company-title ms-2"><?php echo htmlspecialchars($business['business_name']); ?></h1>
                    </div>
                </div>
                <div class="col-4 text-end">
                    <div class="quotation-details">
                        <h2 class="text-uppercase text-muted" style="font-size: 1.1rem; margin-bottom: 0.2rem;">Quotation</h2>
                        <p><strong>Quotation #:</strong> <?php echo $quotation_id; ?></p>
                        <p><strong>Date:</strong> <?php echo $quotation['formatted_date']; ?></p>
                        <p><strong>Valid Until:</strong> <?php echo $quotation['formatted_valid_until']; ?></p>
                        <p class="mb-0 mt-2">
                            <span class="status-badge bg-<?php echo $statusColorClass; ?> text-white">
                                <?php echo $statusLabel; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            <!-- Bottom Row: Company Contact -->
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
            </div>
            <div class="header-divider"></div>
        </div>

        <!-- Customer Information -->
        <?php if ($quotation['customer_id']): ?>
        <div class="customer-info">
            <h5 class="mb-2">Customer Information</h5>
            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($quotation['customer_name']); ?></p>
            <?php if (!empty($quotation['customer_phone'])): ?>
            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($quotation['customer_phone']); ?></p>
            <?php endif; ?>
            <?php if (!empty($quotation['customer_email'])): ?>
            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($quotation['customer_email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($quotation['customer_address'])): ?>
            <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($quotation['customer_address']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Quotation Items -->
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
                            <td class="text-end"><strong>Sub Total:</strong></td>
                            <td class="text-end">LKR <?php echo number_format($subtotal, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-end"><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong>LKR <?php echo number_format($quotation['total_amount'], 2); ?></strong></td>
                        </tr>
                    </table>
                    <p class="validity-note mb-0">
                        This quotation is valid until <?php echo $quotation['formatted_valid_until']; ?>
                        <?php if ($isExpired || $quotation['status'] === 'expired'): ?>
                        <span class="text-danger">(Expired)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Terms and Conditions -->
        <div class="terms-section">
            <h6>Terms and Conditions</h6>
            <ol class="mb-0">
                <li>This quotation is valid for 14 days from the date of issue.</li>
                <li>Prices are subject to change without prior notice after the validity period.</li>
                <li>Payment terms: 100% payment upon confirmation of order.</li>
                <li>Delivery timeline will be confirmed upon receiving the order.</li>
                <li>Product specifications may vary slightly from the description provided.</li>
                <li>Warranty terms as per manufacturer's policy.</li>
            </ol>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="text-muted mb-0">This is a computer generated document. No signature required.</p>
            <p class="text-muted mb-0">Thank you for your business!</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>