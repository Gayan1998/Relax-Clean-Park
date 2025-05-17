<?php
require_once '../includes/db_connection.php';

$job_card_id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) : 0;

if (!$job_card_id) {
    echo "Invalid job card ID";
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT 
                            j.*, 
                            c.name as customer_name, c.phone as customer_phone, c.email as customer_email, c.address as customer_address, 
                            v.make, v.model, v.year, v.registration_number, v.color, v.vin
                          FROM job_cards j
                          LEFT JOIN customers c ON j.customer_id = c.id
                          LEFT JOIN vehicles v ON j.vehicle_id = v.id
                          WHERE j.id = ?");
    $stmt->execute([$job_card_id]);
    $jobCard = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jobCard) {
        echo "Job card not found";
        exit;
    }

    $stmtItems = $pdo->prepare("SELECT ji.*, p.name as product_name 
                               FROM job_card_items ji
                               LEFT JOIN products p ON ji.product_id = p.id
                               WHERE ji.job_card_id = ?
                               ORDER BY ji.id ASC");
    $stmtItems->execute([$job_card_id]);
    $jobItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $totalAmount = array_reduce($jobItems, fn($carry, $item) => $carry + $item['total_price'], 0);

    include_once '../includes/functions.php';
    $business = get_business_settings();

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Card - <?= htmlspecialchars($jobCard['job_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 40px;
            font-size: 14px;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #000;
            margin-bottom: 30px;
            padding-bottom: 10px;
        }

        .header .logo {
            max-height: 80px;
        }

        .section-title {
            background-color: #f1f1f1;
            padding: 8px 12px;
            font-weight: bold;
            margin-top: 30px;
            border-left: 4px solid #0d6efd;
        }

        .info-table td {
            padding: 4px 8px;
            vertical-align: top;
        }

        .items-table th, .items-table td {
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f8f9fa;
        }

        .items-table tfoot td {
            font-weight: bold;
        }

        .signatures {
            margin-top: 60px;
        }

        .sign-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            width: 45%;
            text-align: center;
            padding-top: 5px;
        }

        .job-status {
            font-size: 13px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 3px;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-open { background-color: #ffc107; color: #000; }
        .status-in_progress { background-color: #0d6efd; color: #fff; }
        .status-completed { background-color: #198754; color: #fff; }
        .status-invoiced { background-color: #0dcaf0; color: #000; }
        .status-cancelled { background-color: #dc3545; color: #fff; }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header d-flex justify-content-between align-items-center">
        <div>
            <?php if (!empty($business['logo'])): ?>
                <img src="../assets/images/<?= htmlspecialchars($business['logo']) ?>" alt="Logo" class="logo">
            <?php endif; ?>
        </div>
        <div class="text-end">
            <h4 class="mb-0"><?= htmlspecialchars($business['business_name']) ?></h4>
            <small><?= htmlspecialchars($business['address']) ?><br>
            <?= htmlspecialchars($business['phone']) ?> | <?= htmlspecialchars($business['email']) ?></small>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <h5>Job Card #<?= htmlspecialchars($jobCard['job_number']) ?></h5>
        <span class="job-status status-<?= $jobCard['status'] ?>">
            <?= ucfirst(str_replace('_', ' ', $jobCard['status'])) ?>
        </span>
    </div>

    <div class="text-muted mb-4">Created: <?= date('M d, Y', strtotime($jobCard['created_at'])) ?>
        <?php if ($jobCard['completion_date']): ?>
            | Completed: <?= date('M d, Y', strtotime($jobCard['completion_date'])) ?>
        <?php endif; ?>
    </div>

    <div class="section-title">Customer Information</div>
    <table class="info-table mb-3">
        <tr><td><strong>Name:</strong></td><td><?= htmlspecialchars($jobCard['customer_name']) ?></td></tr>
        <tr><td><strong>Phone:</strong></td><td><?= htmlspecialchars($jobCard['customer_phone']) ?></td></tr>
        <?php if ($jobCard['customer_email']): ?>
            <tr><td><strong>Email:</strong></td><td><?= htmlspecialchars($jobCard['customer_email']) ?></td></tr>
        <?php endif; ?>
        <?php if ($jobCard['customer_address']): ?>
            <tr><td><strong>Address:</strong></td><td><?= htmlspecialchars($jobCard['customer_address']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="section-title">Vehicle Information</div>
    <table class="info-table mb-3">
        <tr><td><strong>Make & Model:</strong></td><td><?= "{$jobCard['make']} {$jobCard['model']}" ?> <?= $jobCard['year'] ? "({$jobCard['year']})" : '' ?></td></tr>
        <tr><td><strong>Registration:</strong></td><td><?= htmlspecialchars($jobCard['registration_number']) ?></td></tr>
        <?php if ($jobCard['color']): ?><tr><td><strong>Color:</strong></td><td><?= htmlspecialchars($jobCard['color']) ?></td></tr><?php endif; ?>
        <?php if ($jobCard['vin']): ?><tr><td><strong>VIN:</strong></td><td><?= htmlspecialchars($jobCard['vin']) ?></td></tr><?php endif; ?>
        <tr><td><strong>Mileage:</strong></td><td><?= $jobCard['current_mileage'] ? number_format($jobCard['current_mileage']) . ' km' : 'N/A' ?></td></tr>
        <?php if ($jobCard['next_service_mileage']): ?>
            <tr><td><strong>Next Service:</strong></td><td><?= number_format($jobCard['next_service_mileage']) ?> km</td></tr>
        <?php endif; ?>
    </table>

    <?php if ($jobCard['reported_issues'] || $jobCard['diagnosis'] || $jobCard['technician_notes']): ?>
        <div class="section-title">Service Details</div>
        <?php if ($jobCard['reported_issues']): ?>
            <p><strong>Reported Issues:</strong><br><?= nl2br(htmlspecialchars($jobCard['reported_issues'])) ?></p>
        <?php endif; ?>
        <?php if ($jobCard['diagnosis']): ?>
            <p><strong>Diagnosis:</strong><br><?= nl2br(htmlspecialchars($jobCard['diagnosis'])) ?></p>
        <?php endif; ?>
        <?php if ($jobCard['technician_notes']): ?>
            <p><strong>Technician Notes:</strong><br><?= nl2br(htmlspecialchars($jobCard['technician_notes'])) ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="section-title">Items & Services</div>
    <?php if (empty($jobItems)): ?>
        <p>No items or services listed.</p>
    <?php else: ?>
        <table class="table table-bordered items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobItems as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['product_name'] ?: $item['description']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= number_format($item['unit_price'], 2) ?></td>
                        <td><?= number_format($item['total_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end">Total</td>
                    <td><?= number_format($totalAmount, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <div class="signatures d-flex justify-content-between">
        <div class="sign-line">Customer Signature</div>
        <div class="sign-line">Technician Signature</div>
    </div>

    <div class="mt-4 text-center no-print">
        <button onclick="window.print()" class="btn btn-outline-primary">Print Job Card</button>
    </div>
</body>
</html>
