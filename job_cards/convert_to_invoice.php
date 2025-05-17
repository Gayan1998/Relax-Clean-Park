<?php
require_once '../includes/header.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Get job card ID from URL
$job_card_id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) : 0;

if (!$job_card_id) {
    // Invalid job card ID
    header("Location: job_cards.php");
    exit;
}

// Check if job card exists and can be invoiced (must be completed)
try {
    $stmt = $pdo->prepare("SELECT j.*, 
                            c.name as customer_name, c.phone as customer_phone, 
                            v.make, v.model, v.registration_number,
                            j.current_mileage,
                            j.next_service_mileage
                          FROM job_cards j
                          LEFT JOIN customers c ON j.customer_id = c.id
                          LEFT JOIN vehicles v ON j.vehicle_id = v.id
                          WHERE j.id = ?");
    $stmt->execute([$job_card_id]);
    $jobCard = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$jobCard) {
        // Job card not found
        header("Location: job_cards.php");
        exit;
    }
    
    // Check if job card is already invoiced
    if ($jobCard['status'] === 'invoiced') {
        header("Location: job_cards.php?view=$job_card_id&error=This job card has already been invoiced");
        exit;
    }
    
    // Get job card items
    $stmtItems = $pdo->prepare("SELECT ji.*, p.name as product_name 
                               FROM job_card_items ji
                               LEFT JOIN products p ON ji.product_id = p.id
                               WHERE ji.job_card_id = ?");
    $stmtItems->execute([$job_card_id]);
    $jobItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
      // Calculate total and profit
    $totalAmount = 0;
    $totalProfit = 0;
      foreach ($jobItems as $item) {
        $totalAmount += $item['total_price'];
        
        // Get product purchase price for profit calculation
        if (!empty($item['product_id'])) {
            $costStmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
            $costStmt->execute([$item['product_id']]);
            $product = $costStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $costPrice = $product['purchase_price'];
                $itemProfit = ($item['unit_price'] - $costPrice) * $item['quantity'];
                $totalProfit += $itemProfit;
            }
        }
    }
    
    // Handle form submission - convert to invoice
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_invoice'])) {
        
        // Check if job card has items
        if (empty($jobItems)) {
            $error = "Cannot create an invoice with no items. Please add items to the job card first.";
        } else {            try {                // Start transaction
                $pdo->beginTransaction();
                  // Get payment method (default to cash if not set)
                $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
                  // Set payment status based on payment method
                $paymentStatus = $paymentMethod === 'cash' ? 'paid' : 'unpaid';
                $amountPaid = $paymentMethod === 'cash' ? $totalAmount : 0;
                $paymentDate = $paymentMethod === 'cash' ? date('Y-m-d') : null;
                
                // Create a new sale - store job_number, profit, payment method and credit details
                $stmt = $pdo->prepare("INSERT INTO sales (customer_id, total_amount, profit, payment_method, 
                                        payment_status, amount_paid, payment_date, sale_date, job_number) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $jobCard['customer_id'], 
                    $totalAmount, 
                    $totalProfit,
                    $paymentMethod,
                    $paymentStatus,
                    $amountPaid,
                    $paymentDate,
                    $jobCard['job_number']
                ]);
                
                $sale_id = $pdo->lastInsertId();
                
                // Add all items to the sale
                foreach ($jobItems as $item) {
                    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, total_price)
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$sale_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['total_price']]);
                }
                
                // Update the job card status to invoiced
                $stmt = $pdo->prepare("UPDATE job_cards SET status = 'invoiced' WHERE id = ?");
                $stmt->execute([$job_card_id]);
                
                // Commit transaction
                $pdo->commit();
                
                // Success message and redirect
                header("Location: ../inventory/generate_invoice.php?id=$sale_id");
                exit;
                
            } catch (PDOException $e) {
                // Rollback on error
                $pdo->rollBack();
                $error = "Error creating invoice: " . $e->getMessage();
            }
        }
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2>Convert Job Card to Invoice</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>                    <li class="breadcrumb-item"><a href="job_cards.php">Job Cards</a></li>
                    <li class="breadcrumb-item"><a href="job_cards.php?view=<?= $job_card_id ?>">Job Card Details</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Convert to Invoice</li>
                </ol>
            </nav>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Job Card Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Job Number:</th>
                                    <td><?= htmlspecialchars($jobCard['job_number']) ?></td>
                                </tr>
                                <tr>
                                    <th>Customer:</th>
                                    <td><?= htmlspecialchars($jobCard['customer_name']) ?> (<?= htmlspecialchars($jobCard['customer_phone']) ?>)</td>
                                </tr>
                                <tr>
                                    <th>Vehicle:</th>
                                    <td>
                                        <?= htmlspecialchars($jobCard['make'] . ' ' . $jobCard['model']) ?>
                                        (<?= htmlspecialchars($jobCard['registration_number']) ?>)
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Date Created:</th>
                                    <td><?= date('M d, Y', strtotime($jobCard['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Completion Date:</th>
                                    <td><?= !empty($jobCard['completion_date']) ? date('M d, Y', strtotime($jobCard['completion_date'])) : 'N/A' ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge <?php 
                                            switch($jobCard['status']) {
                                                case 'open': echo 'bg-warning'; break;
                                                case 'in_progress': echo 'bg-primary'; break;
                                                case 'completed': echo 'bg-success'; break;
                                                case 'invoiced': echo 'bg-info'; break;
                                                case 'cancelled': echo 'bg-danger'; break;
                                                default: echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?= ucfirst($jobCard['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5>Items to be Invoiced</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($jobItems)): ?>
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> No items have been added to this job card. 
                            You cannot create an invoice without items.
                        </div>
                        <div class="text-end">
                            <a href="add_job_item.php?job_id=<?= $job_card_id ?>" class="btn btn-primary">Add Items</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="45%">Description</th>
                                        <th width="10%">Quantity</th>
                                        <th width="20%">Unit Price</th>
                                        <th width="20%">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($jobItems as $item): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td>
                                                <?= htmlspecialchars($item['description']) ?>
                                                <?php if (!empty($item['product_name']) && $item['product_name'] !== $item['description']): ?>
                                                    <br><small class="text-muted">Product: <?= htmlspecialchars($item['product_name']) ?></small>
                                                <?php endif; ?>
                                                <small class="badge <?= ($item['item_type'] === 'service') ? 'bg-info' : 'bg-primary' ?>">
                                                    <?= ucfirst($item['item_type']) ?>
                                                </small>
                                            </td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                                            <td class="text-end"><?= number_format($item['total_price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total Amount:</th>
                                        <th class="text-end"><?= number_format($totalAmount, 2) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> Converting this job card to an invoice will:
                            <ul>
                                <li>Create a new invoice with all the items listed above</li>
                                <li>Mark the job card as "Invoiced"</li>
                                <li>Job card details will no longer be editable after conversion</li>
                            </ul>
                        </div>
                          <form method="POST" class="mt-3">                            <div class="row mb-3 justify-content-center">
                                <div class="col-md-4">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select name="payment_method" id="payment_method" class="form-select" onchange="toggleCreditOptions(this.value)">
                                        <option value="cash">Cash</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row credit-options" id="creditOptions" style="display:none;">
                                <div class="col-md-8 mx-auto">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Credit Payment Selected:</strong> This invoice will be marked as pending payment and can be managed from the Credit Management section.
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                function toggleCreditOptions(value) {
                                    const creditOptions = document.getElementById('creditOptions');
                                    creditOptions.style.display = value === 'credit' ? 'block' : 'none';
                                }
                            </script>
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                                <button type="submit" name="convert_invoice" class="btn btn-primary">Convert to Invoice</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
