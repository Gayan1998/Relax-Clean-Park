<?php
// Get business settings
include_once '../includes/functions.php';
$business = get_business_settings();

// Set default page title if not provided
if (!isset($page_title)) {
    $page_title = "Vehicle Service Management";
}
?>
<div class="page-header">
    <img src="../assets/images/<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo" class="company-logo">
    <div>
        <h1 class="company-title"><?php echo htmlspecialchars($business['business_name']); ?></h1>
        <?php if (!empty($business['tagline'])): ?>
        <p class="company-tagline text-secondary mb-0"><?php echo htmlspecialchars($business['tagline']); ?></p>
        <?php endif; ?>
    </div>
</div>
<div class="header-divider"></div>

<!-- Page Title -->
<?php if (!empty($page_title)): ?>
<h2 class="mb-4"><?php echo htmlspecialchars($page_title); ?></h2>
<?php endif; ?>

<!-- Navigation -->
<nav class="mb-4 no-print">
    <div class="d-flex flex-wrap gap-2">
        <a href="../admin/dashboard.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="../vehicles/vehicles.php" class="btn btn-sm btn-secondary <?php echo strpos($_SERVER['PHP_SELF'], '/vehicles/') !== false ? 'active' : ''; ?>">
            <i class="fas fa-car"></i> Vehicles
        </a>
        <a href="../job_cards/job_cards.php" class="btn btn-sm btn-secondary <?php echo strpos($_SERVER['PHP_SELF'], '/job_cards/') !== false ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i> Job Cards
        </a>
        <a href="../customers/view_customers.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-users"></i> Customers
        </a>
    </div>
</nav>

<style>
    /* Vehicle service specific styles */    .vehicle-card {
        transition: transform var(--transition-speed);
        border-radius: var(--border-radius);
        overflow: hidden;
    }
    
    .vehicle-card:hover {
        transform: translateY(-5px);
    }
    
    .service-status {
        font-weight: 500;
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: var(--border-radius-sm);
        font-size: 0.85rem;
    }
    
    .status-open {
        background-color: rgba(96, 165, 250, 0.2);
        color: var(--accent-blue);
    }
    
    .status-in-progress {
        background-color: rgba(251, 191, 36, 0.2);
        color: var(--accent-yellow);
    }
    
    .status-completed {
        background-color: rgba(74, 222, 128, 0.2);
        color: var(--accent-green);
    }
    
    .status-cancelled {
        background-color: rgba(248, 113, 113, 0.2);
        color: var(--accent-red);
    }
    
    .job-timeline {
        position: relative;
        margin-left: 1rem;
        padding-left: 2rem;
        border-left: 2px solid var(--border-color);
    }
    
    .job-timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
    }
    
    .job-timeline-item:before {
        content: '';
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--accent-blue);
        position: absolute;
        left: -2.065rem;
        top: 0.5rem;
    }
    
    .job-timeline-date {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    /* Print styles for job cards */
    @media print {
        .no-print {
            display: none !important;
        }
        
        .job-card-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .job-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        
        .job-card-table th,
        .job-card-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .signature-section {
            margin-top: 3rem;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-line {
            width: 200px;
            border-bottom: 1px solid #000;
            margin-bottom: 0.5rem;
        }
    }
        border-color: var(--accent-purple);
    }
    
    @media (max-width: 768px) {
        .vehicle-service-nav {
            justify-content: center;
        }
        
        .btn-nav {
            flex: 1 1 auto;
            text-align: center;
            justify-content: center;
            min-width: 120px;
        }
        
        .company-title {
            font-size: 1.4rem;
        }
        
        .company-logo {
            max-height: 40px;
        }
    }
</style>
