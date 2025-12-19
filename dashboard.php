<?php
/**
 * CORNERSTONE INVENTORY TRACKER - End-User Dashboard
 * 
 * Multi-site inventory overview for project managers, finance, and executives
 * READ-ONLY access
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// Require login (both admin and end_user can access)
Auth::requireLogin();

// Get current user info
$user_name = Auth::getFullName();
$user_role = Auth::getRole();

// Get all sites with their status
$sites = Database::fetchAll("
    SELECT id, site_name, location, status, completion_percentage, estimated_completion
    FROM sites
    ORDER BY site_name ASC
");

// Get inventory summary across all sites
$inventory_summary = Database::fetchAll("
    SELECT 
        s.id AS site_id,
        s.site_name,
        s.status AS site_status,
        m.id AS material_id,
        m.material_name,
        m.category,
        m.unit_of_measure,
        m.unit_cost,
        m.reorder_threshold,
        COALESCE(i.quantity, 0) AS quantity,
        i.last_updated,
        (COALESCE(i.quantity, 0) * m.unit_cost) AS total_value
    FROM sites s
    CROSS JOIN materials m
    LEFT JOIN inventory i ON s.id = i.site_id AND m.id = i.material_id
    ORDER BY s.site_name, m.category, m.material_name
");

// Get active alerts
$alerts = Database::fetchAll("
    SELECT 
        a.id,
        a.alert_type,
        a.message,
        a.created_at,
        s.site_name,
        m.material_name,
        COALESCE(i.quantity, 0) AS current_quantity,
        m.reorder_threshold
    FROM alerts a
    JOIN sites s ON a.site_id = s.id
    JOIN materials m ON a.material_id = m.id
    LEFT JOIN inventory i ON a.site_id = i.site_id AND a.material_id = i.material_id
    WHERE a.is_resolved = FALSE
    ORDER BY a.created_at DESC
");

// Calculate summary statistics
$total_sites = count($sites);
$active_sites = count(array_filter($sites, function($site) {
    return $site['status'] === 'active';
}));
$total_alerts = count($alerts);
$total_inventory_value = array_sum(array_column($inventory_summary, 'total_value'));

// Group inventory by site for easier display
$inventory_by_site = [];
foreach ($inventory_summary as $item) {
    $site_id = $item['site_id'];
    if (!isset($inventory_by_site[$site_id])) {
        $inventory_by_site[$site_id] = [
            'site_name' => $item['site_name'],
            'site_status' => $item['site_status'],
            'materials' => []
        ];
    }
    $inventory_by_site[$site_id]['materials'][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cornerstone Inventory Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-page">
    
    <!-- Header -->
    <header class="app-header">
        <div class="header-content">
            <div class="header-left">
                <a href="dashboard.php" class="app-logo" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <rect x="2" y="2" width="28" height="28" rx="4" fill="currentColor" opacity="0.2"/>
                        <path d="M16 8L8 14V24H12V18H20V24H24V14L16 8Z" fill="currentColor"/>
                    </svg>
                    <span>Cornerstone</span>
                </a>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span class="user-name"><?php echo sanitize_output($user_name); ?></span>
                    <span class="user-role"><?php echo sanitize_output(ucfirst(str_replace('_', ' ', $user_role))); ?></span>
                </div>
                <a href="delete_account.php" class="btn btn-danger btn-small" style="margin-right: 0.5rem;">Delete Account</a>
                <a href="logout.php?redirect=landing" class="btn btn-secondary btn-small">Logout</a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="app-nav">
        <div class="nav-content">
            <a href="dashboard.php" class="nav-link active">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <rect x="3" y="3" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="11" y="3" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="3" y="11" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="11" y="11" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                </svg>
                Dashboard
            </a>
            <a href="reports.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M4 16L8 10L12 14L16 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="8" cy="10" r="1.5" fill="currentColor"/>
                    <circle cx="12" cy="14" r="1.5" fill="currentColor"/>
                </svg>
                Reports
            </a>
            <?php if (Auth::isAdmin()): ?>
            <a href="inventory_admin.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <rect x="3" y="5" width="14" height="12" rx="1" stroke="currentColor" stroke-width="2"/>
                    <path d="M7 9H13M7 13H11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Inventory
            </a>
            <a href="manage_sites.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M10 3L3 8V17H7V12H13V17H17V8L10 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                Sites
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="app-main">
        <div class="container-fluid">
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Inventory Dashboard</h1>
                <p>Multi-site inventory overview and alerts</p>
            </div>

            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 4L4 8V16H8V12H16V16H20V8L12 4Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $total_sites; ?></div>
                        <div class="stat-label">Total Sites</div>
                        <div class="stat-detail"><?php echo $active_sites; ?> active</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-icon-green">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo format_currency($total_inventory_value); ?></div>
                        <div class="stat-label">Total Inventory Value</div>
                        <div class="stat-detail">Across all sites</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-icon-orange">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 8V12M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $total_alerts; ?></div>
                        <div class="stat-label">Active Alerts</div>
                        <div class="stat-detail">Requires attention</div>
                    </div>
                </div>
            </div>

            <!-- Alerts Panel -->
            <?php if (count($alerts) > 0): ?>
            <div class="section">
                <div class="section-header">
                    <h2>Active Alerts</h2>
                    <span class="badge badge-warning"><?php echo count($alerts); ?> alerts</span>
                </div>
                <div class="alerts-container">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert-card alert-<?php echo sanitize_output($alert['alert_type']); ?>">
                            <div class="alert-icon">
                                <?php if ($alert['alert_type'] === 'low_stock'): ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M12 8V12M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                <?php elseif ($alert['alert_type'] === 'predictive_reorder'): ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                                        <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                <?php else: ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M12 4L4 8V16H8V12H16V16H20V8L12 4Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="alert-content">
                                <div class="alert-header">
                                    <span class="alert-site"><?php echo sanitize_output($alert['site_name']); ?></span>
                                    <span class="alert-time"><?php echo format_datetime($alert['created_at']); ?></span>
                                </div>
                                <div class="alert-message"><?php echo sanitize_output($alert['message']); ?></div>
                                <div class="alert-details">
                                    <span class="alert-material"><?php echo sanitize_output($alert['material_name']); ?></span>
                                    <span class="alert-quantity">Current: <?php echo number_format($alert['current_quantity'], 2); ?> | Threshold: <?php echo $alert['reorder_threshold']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sites Overview -->
            <div class="section">
                <div class="section-header">
                    <h2>Sites Overview</h2>
                </div>
                <div class="sites-grid">
                    <?php foreach ($sites as $site): ?>
                        <div class="site-card">
                            <div class="site-card-header">
                                <h3><?php echo sanitize_output($site['site_name']); ?></h3>
                                <span class="status-badge status-<?php echo sanitize_output($site['status']); ?>">
                                    <?php echo sanitize_output(ucfirst(str_replace('_', ' ', $site['status']))); ?>
                                </span>
                            </div>
                            <div class="site-card-body">
                                <div class="site-info">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 14C11.3137 14 14 11.3137 14 8C14 4.68629 11.3137 2 8 2C4.68629 2 2 4.68629 2 8C2 11.3137 4.68629 14 8 14Z" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M8 4V8L11 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                    <span><?php echo sanitize_output($site['location']); ?></span>
                                </div>
                                <div class="site-progress">
                                    <div class="progress-label">
                                        <span>Completion</span>
                                        <span><?php echo number_format($site['completion_percentage'], 0); ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $site['completion_percentage']; ?>%"></div>
                                    </div>
                                </div>
                                <?php if ($site['estimated_completion']): ?>
                                <div class="site-date">
                                    Est. Completion: <?php echo format_date($site['estimated_completion']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="section">
                <div class="section-header">
                    <h2>Multi-Site Inventory</h2>
                    <div class="section-actions">
                        <input type="text" id="search-inventory" class="form-control form-control-small" placeholder="Search materials...">
                        <select id="filter-site" class="form-control form-control-small">
                            <option value="">All Sites</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo $site['id']; ?>"><?php echo sanitize_output($site['site_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filter-category" class="form-control form-control-small">
                            <option value="">All Categories</option>
                            <?php
                            $categories = array_unique(array_column($inventory_summary, 'category'));
                            sort($categories);
                            foreach ($categories as $category):
                            ?>
                                <option value="<?php echo sanitize_output($category); ?>"><?php echo sanitize_output($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table" id="inventory-table">
                        <thead>
                            <tr>
                                <th>Site</th>
                                <th>Material</th>
                                <th>Category</th>
                                <th class="text-right">Quantity</th>
                                <th>Unit</th>
                                <th class="text-right">Unit Cost</th>
                                <th class="text-right">Total Value</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_summary as $item): ?>
                                <tr data-site-id="<?php echo $item['site_id']; ?>" data-category="<?php echo sanitize_output($item['category']); ?>">
                                    <td>
                                        <div class="table-cell-site">
                                            <?php echo sanitize_output($item['site_name']); ?>
                                            <span class="status-badge status-<?php echo sanitize_output($item['site_status']); ?> status-small">
                                                <?php echo sanitize_output(substr($item['site_status'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo sanitize_output($item['material_name']); ?></strong></td>
                                    <td><span class="category-badge"><?php echo sanitize_output($item['category']); ?></span></td>
                                    <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                                    <td><?php echo sanitize_output($item['unit_of_measure']); ?></td>
                                    <td class="text-right"><?php echo format_currency($item['unit_cost']); ?></td>
                                    <td class="text-right"><strong><?php echo format_currency($item['total_value']); ?></strong></td>
                                    <td>
                                        <?php if ($item['quantity'] <= 0): ?>
                                            <span class="stock-status stock-out">Out of Stock</span>
                                        <?php elseif ($item['quantity'] < $item['reorder_threshold']): ?>
                                            <span class="stock-status stock-low">Low Stock</span>
                                        <?php else: ?>
                                            <span class="stock-status stock-ok">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $item['last_updated'] ? format_datetime($item['last_updated']) : 'Never'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="scripts.js"></script>
    <script>
        // Initialize dashboard features
        document.addEventListener('DOMContentLoaded', function() {
            initializeInventoryFilters();
        });
    </script>
</body>
</html>
