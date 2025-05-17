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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($business['business_name']); ?> - Invoice</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
        }
        .company-name {
            color: #2c3e50;
            font-weight: 700;
        }
        .invoice-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .invoice-items {
            margin-top: 30px;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .contact-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .reg-number {
            font-weight: 600;
            color: #2c3e50;
        }
        .total-section {
            border-top: 2px solid #e9ecef;
            padding-top: 20px;
            margin-top: 30px;
        }
    </style>
</head>
<body>    <div class="container mt-5 mb-5">
        <div class="row invoice-header">
            <div class="col-md-8">
                <h1 class="company-name"><?php echo htmlspecialchars($business['business_name']); ?></h1>
                <?php if (!empty($business['tagline'])): ?>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($business['tagline']); ?></p>
                <?php endif; ?>
                <div class="contact-info mt-3">
                    <?php if (!empty($business['address'])): ?>
                        <p class="mb-1"><?php echo htmlspecialchars($business['address']); ?></p>
                    <?php endif; ?>
                    <p class="mb-1">
                        <?php if (!empty($business['phone'])): ?>
                            Tel: <?php echo htmlspecialchars($business['phone']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($business['email'])): ?>
                            Email: <?php echo htmlspecialchars($business['email']); ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($business['registration_number'])): ?>
                        <p class="reg-number mb-0">Reg No: <?php echo htmlspecialchars($business['registration_number']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($business['additional_info'])): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($business['additional_info']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <h2 class="text-uppercase text-muted">Invoice</h2>
                <p class="mb-1">Invoice #: <span class="invoice-number"></span></p>
                <p class="mb-1">Date: <span class="invoice-date"></span></p>
            </div>
        </div>

        <div class="invoice-items">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="invoiceItems">
                </tbody>
            </table>
        </div>

        <div class="row total-section">
            <div class="col-md-6 offset-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td class="text-end"><strong>Total Amount:</strong></td>
                        <td class="text-end"><span id="invoiceTotal"></span></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-12 text-center">
                <p class="text-muted">Thank you for your business!</p>
            </div>
        </div>
    </div>
</body>
</html>