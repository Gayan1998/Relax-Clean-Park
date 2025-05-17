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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $completion_date = null;
    
    // If status is being set to 'completed', set completion date
    if ($new_status === 'completed') {
        $completion_date = date('Y-m-d');
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE job_cards SET status = ?, completion_date = ? WHERE id = ?");
        $stmt->execute([$new_status, $completion_date, $job_card_id]);
        
        $status_updated = true;
    } catch (PDOException $e) {
        $errors[] = "Error updating status: " . $e->getMessage();
    }
}

// Get job card details
try {
    // Get job card and related info
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
        // Job card not found
        header("Location: job_cards.php");
        exit;
    }
      // Get job card items
    $stmtItems = $pdo->prepare("SELECT ji.*, p.name as product_name 
                               FROM job_card_items ji
                               LEFT JOIN products p ON ji.product_id = p.id
                               WHERE ji.job_card_id = ?
                               ORDER BY ji.id ASC");
    $stmtItems->execute([$job_card_id]);
    $jobItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total
    $totalAmount = 0;
    foreach ($jobItems as $item) {
        $totalAmount += $item['total_price'];
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="job_cards.php">Job Cards</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Job Card Details</li>
                </ol>
            </nav>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (isset($status_updated)): ?>
                <div class="alert alert-success">Job card status updated successfully.</div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Job Card: <?= htmlspecialchars($jobCard['job_number']) ?></h4>
                    <div>                        <?php if ($jobCard['status'] !== 'invoiced' && $jobCard['status'] !== 'cancelled'): ?>
                            <button type="button" class="btn btn-primary edit-job-card-btn" data-id="<?= $job_card_id ?>">
                                <i class="fas fa-edit"></i> Edit Job Card
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($jobCard['status'] === 'completed'): ?>
                            <a href="convert_to_invoice.php?id=<?= $job_card_id ?>" class="btn btn-success">
                                <i class="fas fa-file-invoice"></i> Convert to Invoice
                            </a>
                        <?php endif; ?>
                        
                        <a href="print_job_card.php?id=<?= $job_card_id ?>" class="btn btn-info" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Customer Information</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Name</th>
                                    <td><?= htmlspecialchars($jobCard['customer_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>Phone</th>
                                    <td><?= htmlspecialchars($jobCard['customer_phone']) ?></td>
                                </tr>
                                <?php if (!empty($jobCard['customer_email'])): ?>
                                <tr>
                                    <th>Email</th>
                                    <td><?= htmlspecialchars($jobCard['customer_email']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($jobCard['customer_address'])): ?>
                                <tr>
                                    <th>Address</th>
                                    <td><?= htmlspecialchars($jobCard['customer_address']) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Vehicle Information</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Make & Model</th>
                                    <td>
                                        <?= htmlspecialchars($jobCard['make'] . ' ' . $jobCard['model']) ?>
                                        <?= $jobCard['year'] ? ' (' . $jobCard['year'] . ')' : '' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Registration</th>
                                    <td><?= htmlspecialchars($jobCard['registration_number']) ?></td>
                                </tr>
                                <?php if (!empty($jobCard['color'])): ?>
                                <tr>
                                    <th>Color</th>
                                    <td><?= htmlspecialchars($jobCard['color']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($jobCard['vin'])): ?>
                                <tr>
                                    <th>VIN/Chassis</th>
                                    <td><?= htmlspecialchars($jobCard['vin']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Mileage</th>
                                    <td><?= $jobCard['current_mileage'] ? number_format($jobCard['current_mileage']) . ' km' : 'N/A' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h5>Job Card Details</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Job Number</th>
                                    <td><?= htmlspecialchars($jobCard['job_number']) ?></td>
                                </tr>
                                <tr>
                                    <th>Create Date</th>
                                    <td><?= date('M d, Y', strtotime($jobCard['created_at'])) ?></td>
                                </tr>
                                <?php if (!empty($jobCard['completion_date'])): ?>
                                <tr>
                                    <th>Completion Date</th>
                                    <td><?= date('M d, Y', strtotime($jobCard['completion_date'])) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Status</th>
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
                                        
                                        <?php if ($jobCard['status'] !== 'invoiced' && $jobCard['status'] !== 'cancelled'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#statusModal">
                                                Change Status
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title">Service Information</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($jobCard['reported_issues'])): ?>
                                        <h6>Reported Issues:</h6>
                                        <p><?= nl2br(htmlspecialchars($jobCard['reported_issues'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($jobCard['diagnosis'])): ?>
                                        <h6>Diagnosis:</h6>
                                        <p><?= nl2br(htmlspecialchars($jobCard['diagnosis'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($jobCard['technician_notes'])): ?>
                                        <h6>Technician Notes:</h6>
                                        <p><?= nl2br(htmlspecialchars($jobCard['technician_notes'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($jobCard['reported_issues']) && empty($jobCard['diagnosis']) && empty($jobCard['technician_notes'])): ?>
                                        <p class="text-muted">No service information recorded.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5>Items & Services</h5>
                            
                            <?php if ($jobCard['status'] !== 'invoiced' && $jobCard['status'] !== 'cancelled'): ?>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-sm btn-success add-item-btn" data-job-id="<?= $job_card_id ?>">
                                        <i class="fas fa-plus"></i> Add Item/Service
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (empty($jobItems)): ?>
                                <div class="alert alert-info">No items or services added to this job card.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Description</th>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>Unit Price</th>
                                                <th>Total</th>
                                                <?php if ($jobCard['status'] !== 'invoiced' && $jobCard['status'] !== 'cancelled'): ?>
                                                    <th>Actions</th>
                                                <?php endif; ?>
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
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= ($item['item_type'] === 'service') ? 'bg-info' : 'bg-primary' ?>">
                                                            <?= ucfirst($item['item_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $item['quantity'] ?></td>
                                                    <td><?= number_format($item['unit_price'], 2) ?></td>
                                                    <td><?= number_format($item['total_price'], 2) ?></td>
                                                    <?php if ($jobCard['status'] !== 'invoiced' && $jobCard['status'] !== 'cancelled'): ?>
                                                        <td>                                                            <button type="button" class="btn btn-sm btn-primary edit-item" 
                                                               data-id="<?= $item['id'] ?>"
                                                               data-job-id="<?= $job_card_id ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <a href="#" class="btn btn-sm btn-danger delete-item" 
                                                               data-id="<?= $item['id'] ?>" 
                                                               data-desc="<?= htmlspecialchars($item['description']) ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="<?= ($jobCard['status'] === 'invoiced' || $jobCard['status'] === 'cancelled') ? 5 : 5 ?>" class="text-end">Total:</th>
                                                <th><?= number_format($totalAmount, 2) ?></th>
                                                <?php if ($jobCard['status'] !== 'invoiced' && $jobCard['status'] !== 'cancelled'): ?>
                                                    <th></th>
                                                <?php endif; ?>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Update Job Card Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="status">Select New Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="open" <?= $jobCard['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="in_progress" <?= $jobCard['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $jobCard['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $jobCard['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <small class="text-muted">
                            Note: Setting status to 'Completed' will set the completion date to today.
                            <br>
                            Once a job card is converted to an invoice, its status cannot be changed.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Item Modal -->
<div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteItemModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete item: <span id="itemDesc"></span>?
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>                <form id="deleteItemForm" method="POST" action="job_cards.php">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="job_card_id" value="<?= $job_card_id ?>">
                    <input type="hidden" name="job_item_id" id="deleteItemId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editItemModalLabel">Edit Item/Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm" method="POST" action="job_cards.php">
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="job_card_id" value="<?= $job_card_id ?>">
                    <input type="hidden" name="job_item_id" id="edit_job_item_id">
                    <input type="hidden" name="original_quantity" id="edit_original_quantity">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="mb-3">
                        <label for="edit_item_type" class="form-label">Type</label>
                        <select name="item_type" id="edit_item_type" class="form-select" required>
                            <option value="Part">Part</option>
                            <option value="Service">Service</option>
                            <option value="Labor">Labor</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="edit_product_info">
                        <label class="form-label">Product Information</label>
                        <div id="edit_product_details" class="form-control bg-light" style="min-height: 38px;"></div>
                        <div class="form-text" id="edit_inventory_status"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <input type="text" name="description" id="edit_description" class="form-control" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_quantity" class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="0.01" step="0.01" required>
                            <div id="edit_quantity_warning" class="form-text text-danger" style="display:none;">
                                Warning: Change exceeds available inventory!
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_unit_price" class="form-label">Unit Price (LKR)</label>
                            <input type="number" name="unit_price" id="edit_unit_price" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Job Card Modal -->
<div class="modal fade" id="editJobCardModal" tabindex="-1" aria-labelledby="editJobCardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editJobCardModalLabel">Edit Job Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editJobCardForm" method="POST" action="job_cards.php">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="job_card_id" id="edit_job_card_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_customer_name" class="form-label">Customer</label>
                                <input type="text" id="edit_customer_name" class="form-control" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_vehicle_info" class="form-label">Vehicle</label>
                                <input type="text" id="edit_vehicle_info" class="form-control" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="Open">Open</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_current_mileage" class="form-label">Current Mileage</label>
                                <input type="number" name="current_mileage" id="edit_current_mileage" class="form-control" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_next_service_mileage" class="form-label">Next Service Mileage</label>
                                <input type="number" name="next_service_mileage" id="edit_next_service_mileage" class="form-control" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_reported_issues" class="form-label">Reported Issues</label>
                                <textarea name="reported_issues" id="edit_reported_issues" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_technician_notes" class="form-label">Technician Notes</label>
                                <textarea name="technician_notes" id="edit_technician_notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Job Card</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2 for product dropdown
    $('#product_select').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '-- Select a Product/Service --',
        allowClear: true,
        dropdownParent: $('#addItemModal')
    });
    
    // Setup delete item confirmation
    $('.delete-item').click(function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const desc = $(this).data('desc');
        
        $('#deleteItemId').val(id);
        $('#itemDesc').text(desc);
        $('#deleteItemModal').modal('show');
    });
      // Setup edit item handling
    $('.edit-item').click(function() {
        const id = $(this).data('id');
        const jobCardId = $(this).data('job-id');
        
        // Fetch job item details via AJAX
        $.ajax({
            url: 'get_job_item_ajax.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const jobItem = response.job_item;
                    
                    // Populate the form fields
                    $('#edit_job_item_id').val(jobItem.id);
                    $('#edit_item_type').val(jobItem.itemType);
                    $('#edit_description').val(jobItem.description);
                    $('#edit_quantity').val(jobItem.quantity);
                    $('#edit_original_quantity').val(jobItem.quantity);
                    $('#edit_unit_price').val(jobItem.unitPrice);
                    $('#edit_product_id').val(jobItem.productId || '');
                    
                    // If product is linked, show product details
                    if (jobItem.productId && jobItem.productDetails) {
                        const product = jobItem.productDetails;
                        $('#edit_product_details').text(`${product.name} (${product.item_type})`);
                        $('#edit_product_info').show();
                        
                        // For parts, monitor quantity for inventory checks
                        if (jobItem.itemType.toLowerCase() === 'part') {
                            const availableStock = parseFloat(product.quantity) + parseFloat(jobItem.quantity);
                            $('#edit_inventory_status').text(`Current Stock: ${availableStock}`);
                            
                            // Setup quantity change event
                            $('#edit_quantity').off('input').on('input', function() {
                                const newQty = parseFloat($(this).val()) || 0;
                                const originalQty = parseFloat(jobItem.quantity) || 0;
                                
                                // Only warn if increasing quantity beyond available stock
                                if (newQty > originalQty && (newQty - originalQty) > availableStock) {
                                    $('#edit_quantity_warning').show();
                                } else {
                                    $('#edit_quantity_warning').hide();
                                }
                            });
                        } else {
                            $('#edit_inventory_status').text('');
                            $('#edit_quantity_warning').hide();
                        }
                    } else {
                        $('#edit_product_details').text('No product linked');
                        $('#edit_inventory_status').text('');
                        $('#edit_quantity_warning').hide();
                    }
                    
                    // Show the modal
                    $('#editItemModal').modal('show');
                } else {
                    alert('Failed to load job item details: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading job item details. Please try again.');
            }
        });
    });
    
    // Setup edit job card handling
    $('.edit-job-card-btn').click(function() {
        const id = $(this).data('id');
        
        // Fetch job card details via AJAX
        $.ajax({
            url: 'get_job_card_ajax.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const jobCard = response.job_card;
                    
                    // Populate the form fields
                    $('#edit_job_card_id').val(jobCard.id);
                    $('#edit_customer_name').val(jobCard.customerName);
                    $('#edit_vehicle_info').val(jobCard.vehicleInfo);
                    $('#edit_status').val(jobCard.status);
                    $('#edit_current_mileage').val(jobCard.currentMileage);
                    $('#edit_next_service_mileage').val(jobCard.nextServiceMileage);
                    $('#edit_reported_issues').val(jobCard.reportedIssues);
                    $('#edit_technician_notes').val(jobCard.technicianNotes);
                    
                    // Show the modal
                    $('#editJobCardModal').modal('show');
                } else {
                    alert('Failed to load job card details: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading job card details. Please try again.');
            }
        });
    });
    
    // Setup add item button handling
    $('.add-item-btn').click(function() {
        const jobCardId = $(this).data('job-id');
        
        // Reset the form
        $('#add_item_form')[0].reset();
        $('#add_job_card_id').val(jobCardId);
        $('#product_id').val('');
        $('#quantity_warning').hide();
        
        // Show the modal
        $('#addItemModal').modal('show');
    });
    
    // Handle product selection change
    $('#product_select').change(function() {
        const selectedOption = $(this).find('option:selected');
        const productId = selectedOption.val();
        
        if (productId) {
            // Get data attributes from the selected option
            const type = selectedOption.data('type');
            const description = selectedOption.data('description');
            const price = selectedOption.data('price');
            const availableQuantity = selectedOption.data('quantity');
            
            // Update form fields with selected product data
            $('#item_type').val(type);
            $('#description').val(description);
            $('#unit_price').val(price);
            $('#product_id').val(productId);
            
            // If it's a part, check inventory quantity
            if (type === 'Part' && availableQuantity !== undefined) {
                // Warn if quantity exceeds available inventory
                $('#quantity').on('input', function() {
                    if (parseFloat($(this).val()) > parseFloat(availableQuantity)) {
                        $('#quantity_warning').show();
                    } else {
                        $('#quantity_warning').hide();
                    }
                });
                
                // Initial check
                if (parseFloat($('#quantity').val()) > parseFloat(availableQuantity)) {
                    $('#quantity_warning').show();
                }
            } else {
                $('#quantity_warning').hide();
            }
        } else {
            // Clear fields if no product selected
            $('#product_id').val('');
            $('#quantity_warning').hide();
        }
    });    // Handle item type change
    $('#item_type').change(function() {
        const selectedType = $(this).val();
        
        // Reset product selection when type changes
        $('#product_select').val('').trigger('change');
        $('#product_id').val('');
    });
});
</script>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addItemModalLabel">Add Item/Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add_item_form" method="POST" action="job_cards.php">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="job_card_id" id="add_job_card_id" value="<?= $job_card_id ?>">
                    <input type="hidden" name="product_id" id="product_id" value="">
                    
                    <div class="mb-3">
                        <label for="item_type" class="form-label">Type</label>
                        <select name="item_type" id="item_type" class="form-select" required>
                            <option value="Part">Part</option>
                            <option value="Service">Service</option>
                            <option value="Labor">Labor</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="product_select_container">
                        <label for="product_select" class="form-label">Select from Inventory</label>                        <select name="product_select" id="product_select" class="form-select" style="width: 100%;">
                            <option value="">-- Select a Product/Service --</option>
                            <?php
                            // Get products and services from database
                            try {
                                $stmt = $pdo->prepare("SELECT id, name, item_type, description, selling_price, quantity FROM products ORDER BY name");
                                $stmt->execute();
                                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                $parts = [];
                                $services = [];
                                
                                foreach ($products as $product) {
                                    if (strtolower($product['item_type']) === 'service') {
                                        $services[] = $product;
                                    } else {
                                        $parts[] = $product;
                                    }
                                }
                                
                                // Output parts group
                                if (!empty($parts)) {
                                    echo '<optgroup label="Parts">';
                                    foreach ($parts as $part) {
                                        echo '<option value="' . $part['id'] . '" 
                                            data-type="Part" 
                                            data-description="' . htmlspecialchars($part['description']) . '" 
                                            data-price="' . $part['selling_price'] . '"
                                            data-quantity="' . $part['quantity'] . '">
                                            ' . htmlspecialchars($part['name']) . ' - LKR ' . number_format($part['selling_price'], 2) . '
                                            </option>';
                                    }
                                    echo '</optgroup>';
                                }
                                
                                // Output services group
                                if (!empty($services)) {
                                    echo '<optgroup label="Services">';
                                    foreach ($services as $service) {
                                        echo '<option value="' . $service['id'] . '" 
                                            data-type="Service" 
                                            data-description="' . htmlspecialchars($service['description']) . '" 
                                            data-price="' . $service['selling_price'] . '">
                                            ' . htmlspecialchars($service['name']) . ' - LKR ' . number_format($service['selling_price'], 2) . '
                                            </option>';
                                    }
                                    echo '</optgroup>';
                                }
                            } catch (PDOException $e) {
                                echo '<option value="">Error loading products: ' . $e->getMessage() . '</option>';
                            }
                            ?>
                        </select>
                        <div class="form-text">Select a product/service from inventory or enter details manually below</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" name="description" id="description" class="form-control" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="0.01" step="0.01" value="1" required>
                            <div id="quantity_warning" class="form-text text-danger" style="display:none;">
                                Warning: Exceeds available inventory!
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="unit_price" class="form-label">Unit Price (LKR)</label>
                            <input type="number" name="unit_price" id="unit_price" class="form-control" min="0" step="0.01" value="0" required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
