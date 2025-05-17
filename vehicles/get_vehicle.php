<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid vehicle ID.</div>';
    exit;
}

$vehicle_id = $_GET['id'];

try {
    // Get vehicle details with customer information
    $stmt = $pdo->prepare("SELECT v.*, 
                           c.name as customer_name, c.phone as customer_phone, c.email as customer_email
                           FROM vehicles v
                           LEFT JOIN customers c ON v.customer_id = c.id
                           WHERE v.id = ?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        echo '<div class="alert alert-danger">Vehicle not found.</div>';
        exit;
    }
    
    // Get job card history (if any)
    $stmt = $pdo->prepare("SELECT id, job_number, status, created_at 
                           FROM job_cards 
                           WHERE vehicle_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 5");
    $stmt->execute([$vehicle_id]);
    $job_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="vehicle-details">
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-secondary">Vehicle Information</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <th>Registration:</th>
                    <td><?= htmlspecialchars($vehicle['registration_number']) ?></td>
                </tr>
                <tr>
                    <th>Make & Model:</th>
                    <td><?= htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) ?></td>
                </tr>
                <tr>
                    <th>Year:</th>
                    <td><?= htmlspecialchars($vehicle['year']) ?></td>
                </tr>
                <tr>
                    <th>Color:</th>
                    <td><?= htmlspecialchars($vehicle['color']) ?></td>
                </tr>
                <tr>
                    <th>Current Mileage:</th>
                    <td><?= $vehicle['current_mileage'] ? number_format($vehicle['current_mileage']) . ' km' : 'N/A' ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-secondary">Owner Information</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <th>Name:</th>
                    <td><?= htmlspecialchars($vehicle['customer_name']) ?></td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td><?= htmlspecialchars($vehicle['customer_phone']) ?></td>
                </tr>
                <?php if (!empty($vehicle['customer_email'])): ?>
                <tr>
                    <th>Email:</th>
                    <td><?= htmlspecialchars($vehicle['customer_email']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-secondary">Technical Details</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <th>VIN (Chassis):</th>
                        <td><?= htmlspecialchars($vehicle['vin'] ?: 'Not recorded') ?></td>
                    </tr>
                    <tr>
                        <th>Engine Number:</th>
                        <td><?= htmlspecialchars($vehicle['engine_number'] ?? 'Not recorded') ?></td>
                    </tr>
                    <tr>
                        <th>Fuel Type:</th>
                        <td><?= htmlspecialchars($vehicle['fuel_type'] ?? 'Not recorded') ?></td>
                    </tr>
                    <tr>
                        <th>Transmission:</th>
                        <td><?= htmlspecialchars($vehicle['transmission'] ?? 'Not recorded') ?></td>
                    </tr>
                </table>
        </div>
        <div class="col-md-6">
            <?php if (!empty($vehicle['notes'])): ?>
            <h6 class="text-secondary">Notes</h6>
            <div class="p-2 bg-dark rounded border border-secondary">
                <?= nl2br(htmlspecialchars($vehicle['notes'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <h6 class="text-secondary mt-4">Service History</h6>
    <?php if (empty($job_history)): ?>
        <div class="alert alert-info">No service history found for this vehicle.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Job #</th>
                        <th>Service Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($job_history as $job): ?>
                        <tr>
                            <td><?= htmlspecialchars($job['job_number']) ?></td>
                            <td><?= date('d M Y', strtotime($job['created_at'])) ?></td>
                            <td>
                                <span class="badge <?php 
                                    switch($job['status']) {
                                        case 'Open': echo 'bg-warning'; break;
                                        case 'In Progress': echo 'bg-primary'; break;
                                        case 'Completed': echo 'bg-success'; break;
                                        case 'Invoiced': echo 'bg-info'; break;
                                        case 'Cancelled': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                ?>">
                                    <?= $job['status'] ?>
                                </span>
                            </td>
                            <td>                                <a href="../job_cards/job_cards.php?view=<?= $job['id'] ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error loading vehicle data: ' . $e->getMessage() . '</div>';
}
?>
