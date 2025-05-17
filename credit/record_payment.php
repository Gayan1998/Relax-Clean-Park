<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin/login.php");
    exit;
}
require_once '../includes/header.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

$error = null;
$success = null;

// Get sale ID from URL
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate sale ID
if (!$sale_id) {
    header("Location: credit_management.php");
    exit;
}

// Fetch sale data
try {
    $stmt = $pdo->prepare("SELECT s.*, 
                          DATE_FORMAT(s.sale_date, '%Y-%m-%d') AS formatted_sale_date,
                          c.name AS customer_name, c.phone AS customer_phone
                          FROM sales s
                          LEFT JOIN customers c ON s.customer_id = c.id
                          WHERE s.id = ? AND s.payment_method = 'credit'");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if sale exists and is a credit sale
    if (!$sale) {
        header("Location: credit_management.php?error=Invalid sale or not a credit sale");
        exit;
    }

    // Calculate balance due
    $balance_due = $sale['total_amount'] - $sale['amount_paid'];

    // Process payment form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
        $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $payment_notes = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : '';

        // Validate payment amount
        if ($payment_amount <= 0) {
            $error = "Payment amount must be greater than zero.";
        } elseif ($payment_amount > $balance_due) {
            $error = "Payment amount cannot exceed balance due (LKR " . number_format($balance_due, 2) . ").";
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Calculate new total paid amount
                $new_amount_paid = $sale['amount_paid'] + $payment_amount;
                
                // Determine payment status
                $payment_status = 'partial';
                if ($new_amount_paid >= $sale['total_amount']) {
                    $payment_status = 'paid';
                }

                // Update sale record
                $update_stmt = $pdo->prepare("UPDATE sales SET 
                                             amount_paid = ?, 
                                             payment_status = ?, 
                                             payment_date = ? 
                                             WHERE id = ?");
                $update_stmt->execute([
                    $new_amount_paid, 
                    $payment_status, 
                    $payment_date, 
                    $sale_id
                ]);

                // Record payment in payment history table (this table would need to be created)
                // This is optional and can be implemented in the future
                /*
                $history_stmt = $pdo->prepare("INSERT INTO payment_history 
                                             (sale_id, payment_amount, payment_date, notes, created_at)
                                             VALUES (?, ?, ?, ?, NOW())");
                $history_stmt->execute([
                    $sale_id,
                    $payment_amount,
                    $payment_date,
                    $payment_notes,
                ]);
                */

                // Commit transaction
                $pdo->commit();

                // Set success message
                $success = "Payment of LKR " . number_format($payment_amount, 2) . " recorded successfully!";

                // Refresh sale data
                $stmt->execute([$sale_id]);
                $sale = $stmt->fetch(PDO::FETCH_ASSOC);
                $balance_due = $sale['total_amount'] - $sale['amount_paid'];
            } catch (PDOException $e) {
                // Rollback on error
                $pdo->rollBack();
                $error = "Error recording payment: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Record Payment</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="credit_management.php">Credit Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Record Payment</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Invoice Information Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Invoice Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Invoice #:</th>
                            <td><?= $sale['id'] ?></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td><?= $sale['formatted_sale_date'] ?></td>
                        </tr>
                        <tr>
                            <th>Customer:</th>
                            <td>
                                <?= !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer' ?>
                                <?= !empty($sale['customer_phone']) ? '<br><small class="text-muted">' . htmlspecialchars($sale['customer_phone']) . '</small>' : '' ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Total Amount:</th>
                            <td class="fw-bold">LKR <?= number_format($sale['total_amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Amount Paid:</th>
                            <td>LKR <?= number_format($sale['amount_paid'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Balance Due:</th>
                            <td class="fw-bold text-danger">LKR <?= number_format($balance_due, 2) ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge <?= 
                                    $sale['payment_status'] === 'paid' ? 'bg-success' : 
                                    ($sale['payment_status'] === 'partial' ? 'bg-warning' : 'bg-danger') ?>">
                                    <?= ucfirst($sale['payment_status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($sale['payment_status'] !== 'paid'): ?>
    <!-- Payment Form -->
    <div class="card">
        <div class="card-header">
            <h5>Record Payment</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="payment_amount" class="form-label">Payment Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">LKR</span>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount" 
                                   class="form-control" value="<?= number_format($balance_due, 2, '.', '') ?>" 
                                   min="0.01" max="<?= $balance_due ?>" required>
                        </div>
                        <div class="form-text">Maximum payable: LKR <?= number_format($balance_due, 2) ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="payment_type" class="form-label">Payment Type</label>
                        <select name="payment_type" id="payment_type" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="payment_notes" class="form-label">Payment Notes (Optional)</label>
                    <textarea name="payment_notes" id="payment_notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="credit_management.php" class="btn btn-secondary">Back to Credit Management</a>
                    <button type="submit" name="record_payment" class="btn btn-primary">
                        Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <strong>This invoice has been fully paid.</strong> No further payments are required.
        <div class="mt-2">
            <a href="credit_management.php" class="btn btn-secondary">Back to Credit Management</a>
            <a href="../inventory/generate_invoice.php?id=<?= $sale_id ?>" class="btn btn-primary ms-2">View Invoice</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
