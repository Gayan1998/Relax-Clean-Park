<?php
// Must be included after including functions.php and getting $business
if (!isset($business)) {
    include_once 'functions.php';
    $business = get_business_settings();
}

$current_year = date('Y');
?>
<footer class="mt-5 pt-4 pb-3 border-top">
    <div class="container">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0">&copy; <?php echo $current_year; ?> <?php echo htmlspecialchars($business['business_name']); ?>. All rights reserved.</p>
                <?php if (!empty($business['registration_number'])): ?>
                <p class="small text-muted mb-0">Registration: <?php echo htmlspecialchars($business['registration_number']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0">
                    <?php if (!empty($business['phone'])): ?>
                    <a href="tel:<?php echo htmlspecialchars($business['phone']); ?>" class="text-decoration-none me-3">
                        <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($business['phone']); ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($business['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($business['email']); ?>" class="text-decoration-none">
                        <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($business['email']); ?>
                    </a>
                    <?php endif; ?>
                </p>
                <?php if (!empty($business['address'])): ?>
                <p class="small text-muted mb-0"><?php echo htmlspecialchars($business['address']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Common JS -->
<script src="<?php echo isset($is_root) && $is_root ? '' : '../'; ?>assets/js/common.js"></script>

<!-- Flash Messages -->
<?php if (isset($_SESSION['message'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo htmlspecialchars($_SESSION['message']); ?>', 'success');
    });
</script>
<?php unset($_SESSION['message']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo htmlspecialchars($_SESSION['error']); ?>', 'danger');
    });
</script>
<?php unset($_SESSION['error']); endif; ?>