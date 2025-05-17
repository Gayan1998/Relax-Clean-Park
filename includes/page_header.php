<?php
// Must be included after including functions.php and getting $business
if (!isset($business)) {
    include_once 'functions.php';
    $business = get_business_settings();
}

// Set the page title to the default if not provided
if (!isset($page_title)) {
    $page_title = "Auto Service System";
}
?>
<!-- Company Header Section -->
<div class="page-header">
    <img src="<?php echo isset($is_root) && $is_root ? '' : '../'; ?>assets/images/<?php echo htmlspecialchars($business['logo']); ?>" 
         alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo" 
         class="company-logo">
    <div>
        <h1 class="company-title"><?php echo htmlspecialchars($business['business_name']); ?></h1>
        <?php if (!empty($business['tagline'])): ?>
        <p class="company-tagline"><?php echo htmlspecialchars($business['tagline']); ?></p>
        <?php endif; ?>
    </div>
</div>
<div class="header-divider"></div>

<?php if (!empty($page_title)): ?>
<h2 class="mb-4"><?php echo htmlspecialchars($page_title); ?></h2>
<?php endif; ?>
