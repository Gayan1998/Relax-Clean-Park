<?php
session_start();
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../includes/db_connection.php';

if (!isset($_GET['sale_id'])) {
    echo '<div class="alert alert-danger">No sale ID provided</div>';
    exit();
}

$sale_id = intval($_GET['sale_id']);

// Fetch returns for this sale
$returns_query = "SELECT r.*, 
                DATE_FORMAT(r.return_date, '%Y-%m-%d') as formatted_date
                FROM returns r
                WHERE r.sale_id = :sale_id
                ORDER BY r.return_date DESC";
$stmt = $pdo->prepare($returns_query);
$stmt->execute(['sale_id' => $sale_id]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($returns)) {
    echo '<div class="alert alert-info">No returns found for this sale</div>';
    exit();
}

// For each return, get the return items
foreach ($returns as &$return) {
    $items_query = "SELECT ri.*, p.name as product_name 
                   FROM return_items ri 
                   JOIN products p ON ri.product_id = p.id 
                   WHERE ri.return_id = :return_id";
    $stmt = $pdo->prepare($items_query);
    $stmt->execute(['return_id' => $return['id']]);
    $return['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($return); // Break the reference

// Display returns
foreach ($returns as $index => $return):
?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Return #<?php echo $return['id']; ?> - <?php echo $return['formatted_date']; ?></h5>
            <span class="badge bg-danger">LKR <?php echo number_format($return['total_amount'], 2); ?></span>
        </div>
        <div class="card-body">
            <?php if (!empty($return['notes'])): ?>
            <div class="alert alert-secondary mb-3">
                <strong>Notes:</strong> <?php echo htmlspecialchars($return['notes']); ?>
            </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($return['items'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end">LKR <?php echo number_format($item['price'], 2); ?></td>
                            <td class="text-end">LKR <?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total Refund:</strong></td>
                            <td class="text-end"><strong>LKR <?php echo number_format($return['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="../returns/return_receipt.php?return_id=<?php echo $return['id']; ?>" target="_blank" class="btn btn-sm btn-primary">
                <i class="fas fa-print"></i> View Receipt
            </a>
        </div>
    </div>
<?php endforeach; ?>