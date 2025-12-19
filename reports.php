<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Reports Page
 * 
 * Generate cost reports and waste/variance reports
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// Require login
Auth::requireLogin();

$user_name = Auth::getFullName();
$user_role = Auth::getRole();

// Get all sites for filtering
$sites = Database::fetchAll("
    SELECT id, site_name
    FROM sites
    ORDER BY site_name ASC
");

// Get all categories for filtering
$categories = Database::fetchAll("
    SELECT DISTINCT category
    FROM materials
    ORDER BY category ASC
");

// Default report parameters
$report_type = get('report_type', 'cost');
$site_filter = get('site_id', 'all');
$date_from = get('date_from', date('Y-m-01')); // First day of current month
$date_to = get('date_to', date('Y-m-d')); // Today

// Generate Cost Report
$cost_report_data = [];
$cost_summary = ['total_cost' => 0, 'by_site' => [], 'by_category' => []];

if ($report_type === 'cost') {
    $sql = "
        SELECT 
            s.id AS site_id,
            s.site_name,
            m.id AS material_id,
            m.material_name,
            m.category,
            m.unit_of_measure,
            m.unit_cost,
            COALESCE(i.quantity, 0) AS quantity,
            (COALESCE(i.quantity, 0) * m.unit_cost) AS total_value
        FROM sites s
        CROSS JOIN materials m
        LEFT JOIN inventory i ON s.id = i.site_id AND m.id = i.material_id
    ";
    
    if ($site_filter !== 'all') {
        $sql .= " WHERE s.id = ?";
        $cost_report_data = Database::fetchAll($sql, [$site_filter], 'i');
    } else {
        $cost_report_data = Database::fetchAll($sql);
    }
    
    // Calculate summaries
    foreach ($cost_report_data as $row) {
        $cost_summary['total_cost'] += $row['total_value'];
        
        if (!isset($cost_summary['by_site'][$row['site_name']])) {
            $cost_summary['by_site'][$row['site_name']] = 0;
        }
        $cost_summary['by_site'][$row['site_name']] += $row['total_value'];
        
        if (!isset($cost_summary['by_category'][$row['category']])) {
            $cost_summary['by_category'][$row['category']] = 0;
        }
        $cost_summary['by_category'][$row['category']] += $row['total_value'];
    }
}

// Generate Waste/Variance Report
$waste_report_data = [];
$waste_summary = ['total_variance' => 0, 'total_variance_value' => 0, 'high_variance_count' => 0];

if ($report_type === 'waste') {
    $sql = "
        SELECT 
            wr.id,
            wr.expected_quantity,
            wr.actual_quantity,
            wr.variance,
            wr.variance_percentage,
            wr.report_date,
            wr.notes,
            s.site_name,
            m.material_name,
            m.unit_of_measure,
            m.unit_cost,
            (wr.variance * m.unit_cost) AS variance_value
        FROM waste_reports wr
        JOIN sites s ON wr.site_id = s.id
        JOIN materials m ON wr.material_id = m.id
        WHERE wr.report_date BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    $types = 'ss';
    
    if ($site_filter !== 'all') {
        $sql .= " AND s.id = ?";
        $params[] = $site_filter;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY wr.report_date DESC, wr.variance_percentage DESC";
    
    $waste_report_data = Database::fetchAll($sql, $params, $types);
    
    // Calculate summaries
    foreach ($waste_report_data as $row) {
        $waste_summary['total_variance'] += abs($row['variance']);
        $waste_summary['total_variance_value'] += abs($row['variance_value']);
        
        if (abs($row['variance_percentage']) > WASTE_VARIANCE_THRESHOLD) {
            $waste_summary['high_variance_count']++;
        }
    }
}

// Generate Predictive Reorder Report
$reorder_report_data = [];

if ($report_type === 'reorder') {
    $sql = "
        SELECT 
            s.site_name,
            m.material_name,
            m.category,
            m.unit_of_measure,
            m.unit_cost,
            m.reorder_threshold,
            COALESCE(i.quantity, 0) AS current_quantity,
            (m.reorder_threshold - COALESCE(i.quantity, 0)) AS shortage,
            ((m.reorder_threshold - COALESCE(i.quantity, 0)) * m.unit_cost) AS reorder_cost
        FROM sites s
        CROSS JOIN materials m
        LEFT JOIN inventory i ON s.id = i.site_id AND m.id = i.material_id
        WHERE COALESCE(i.quantity, 0) < m.reorder_threshold
    ";
    
    if ($site_filter !== 'all') {
        $sql .= " AND s.id = ?";
        $reorder_report_data = Database::fetchAll($sql, [$site_filter], 'i');
    } else {
        $reorder_report_data = Database::fetchAll($sql);
    }
}

// Handle CSV Export
if (get('export') === 'csv') {
    $filename = '';
    $csv_data = [];
    
    if ($report_type === 'cost') {
        $filename = 'cost_report_' . date('Y-m-d') . '.csv';
        $csv_data[] = ['Site', 'Material', 'Category', 'Quantity', 'Unit', 'Unit Cost', 'Total Value'];
        
        foreach ($cost_report_data as $row) {
            $csv_data[] = [
                $row['site_name'],
                $row['material_name'],
                $row['category'],
                number_format($row['quantity'], 2),
                $row['unit_of_measure'],
                number_format($row['unit_cost'], 2),
                number_format($row['total_value'], 2)
            ];
        }
    } elseif ($report_type === 'waste') {
        $filename = 'waste_report_' . date('Y-m-d') . '.csv';
        $csv_data[] = ['Date', 'Site', 'Material', 'Expected Qty', 'Actual Qty', 'Variance', 'Variance %', 'Value Lost', 'Notes'];
        
        foreach ($waste_report_data as $row) {
            $csv_data[] = [
                $row['report_date'],
                $row['site_name'],
                $row['material_name'],
                number_format($row['expected_quantity'], 2),
                number_format($row['actual_quantity'], 2),
                number_format($row['variance'], 2),
                number_format($row['variance_percentage'], 2) . '%',
                number_format(abs($row['variance_value']), 2),
                $row['notes'] ?: 'N/A'
            ];
        }
    } elseif ($report_type === 'reorder') {
        $filename = 'reorder_report_' . date('Y-m-d') . '.csv';
        $csv_data[] = ['Site', 'Material', 'Category', 'Current Qty', 'Reorder Threshold', 'Shortage', 'Reorder Cost'];
        
        foreach ($reorder_report_data as $row) {
            $csv_data[] = [
                $row['site_name'],
                $row['material_name'],
                $row['category'],
                number_format($row['current_quantity'], 2),
                number_format($row['reorder_threshold'], 0),
                number_format($row['shortage'], 2),
                number_format($row['reorder_cost'], 2)
            ];
        }
    }
    
    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    foreach ($csv_data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Cornerstone Inventory Tracker</title>
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
            <a href="dashboard.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <rect x="3" y="3" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="11" y="3" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="3" y="11" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                    <rect x="11" y="11" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/>
                </svg>
                Dashboard
            </a>
            <a href="reports.php" class="nav-link active">
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
                Manage Inventory
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="app-main">
        <div class="container-fluid">
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Reports & Analytics</h1>
                <p>Generate detailed cost, waste, and reorder reports</p>
            </div>

            <!-- Report Filters -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="reports.php" class="report-filters">
                        <div class="filter-row">
                            <div class="form-group">
                                <label for="report_type">Report Type</label>
                                <select name="report_type" id="report_type" class="form-control">
                                    <option value="cost" <?php echo $report_type === 'cost' ? 'selected' : ''; ?>>Total Material Cost</option>
                                    <option value="waste" <?php echo $report_type === 'waste' ? 'selected' : ''; ?>>Waste & Variance</option>
                                    <option value="reorder" <?php echo $report_type === 'reorder' ? 'selected' : ''; ?>>Predictive Reorder</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="site_id">Site</label>
                                <select name="site_id" id="site_id" class="form-control">
                                    <option value="all" <?php echo $site_filter === 'all' ? 'selected' : ''; ?>>All Sites</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo $site['id']; ?>" <?php echo $site_filter == $site['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize_output($site['site_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="date-filter-group" style="<?php echo $report_type !== 'waste' ? 'display: none;' : ''; ?>">
                                <label for="date_from">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>

                            <div class="form-group" id="date-to-group" style="<?php echo $report_type !== 'waste' ? 'display: none;' : ''; ?>">
                                <label for="date_to">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>

                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cost Report -->
            <?php if ($report_type === 'cost'): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>Total Material Cost Report</h2>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 2V10M8 10L5 7M8 10L11 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M2 12V13C2 13.5523 2.44772 14 3 14H13C13.5523 14 14 13.5523 14 13V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            Export CSV
                        </a>
                    </div>

                    <!-- Summary Cards -->
                    <div class="report-summary">
                        <div class="summary-card">
                            <div class="summary-label">Total Inventory Value</div>
                            <div class="summary-value"><?php echo format_currency($cost_summary['total_cost']); ?></div>
                        </div>
                    </div>

                    <!-- By Site -->
                    <h3>Cost by Site</h3>
                    <div class="chart-container">
                        <?php foreach ($cost_summary['by_site'] as $site_name => $cost): ?>
                            <div class="chart-bar">
                                <div class="chart-label"><?php echo sanitize_output($site_name); ?></div>
                                <div class="chart-bar-container">
                                    <div class="chart-bar-fill" style="width: <?php echo ($cost / $cost_summary['total_cost'] * 100); ?>%"></div>
                                </div>
                                <div class="chart-value"><?php echo format_currency($cost); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- By Category -->
                    <h3>Cost by Category</h3>
                    <div class="chart-container">
                        <?php foreach ($cost_summary['by_category'] as $category => $cost): ?>
                            <div class="chart-bar">
                                <div class="chart-label"><?php echo sanitize_output($category); ?></div>
                                <div class="chart-bar-container">
                                    <div class="chart-bar-fill" style="width: <?php echo ($cost / $cost_summary['total_cost'] * 100); ?>%"></div>
                                </div>
                                <div class="chart-value"><?php echo format_currency($cost); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Detailed Table -->
                    <h3>Detailed Breakdown</h3>
                    <div class="table-container">
                        <table class="data-table" id="report-table">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Material</th>
                                    <th>Category</th>
                                    <th class="text-right">Quantity</th>
                                    <th>Unit</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th class="text-right">Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cost_report_data as $row): ?>
                                    <tr>
                                        <td><?php echo sanitize_output($row['site_name']); ?></td>
                                        <td><strong><?php echo sanitize_output($row['material_name']); ?></strong></td>
                                        <td><span class="category-badge"><?php echo sanitize_output($row['category']); ?></span></td>
                                        <td class="text-right"><?php echo number_format($row['quantity'], 2); ?></td>
                                        <td><?php echo sanitize_output($row['unit_of_measure']); ?></td>
                                        <td class="text-right"><?php echo format_currency($row['unit_cost']); ?></td>
                                        <td class="text-right"><strong><?php echo format_currency($row['total_value']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Waste/Variance Report -->
            <?php if ($report_type === 'waste'): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>Waste & Variance Report (Shrinkage Audit)</h2>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 2V10M8 10L5 7M8 10L11 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M2 12V13C2 13.5523 2.44772 14 3 14H13C13.5523 14 14 13.5523 14 13V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            Export CSV
                        </a>
                    </div>

                    <!-- Summary Cards -->
                    <div class="report-summary">
                        <div class="summary-card">
                            <div class="summary-label">Total Variance Value</div>
                            <div class="summary-value summary-value-negative"><?php echo format_currency($waste_summary['total_variance_value']); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">High Variance Items</div>
                            <div class="summary-value"><?php echo $waste_summary['high_variance_count']; ?></div>
                            <div class="summary-detail">&gt;<?php echo WASTE_VARIANCE_THRESHOLD; ?>% variance</div>
                        </div>
                    </div>

                    <!-- Detailed Table -->
                    <div class="table-container">
                        <table class="data-table" id="report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Site</th>
                                    <th>Material</th>
                                    <th class="text-right">Expected</th>
                                    <th class="text-right">Actual</th>
                                    <th class="text-right">Variance</th>
                                    <th class="text-right">Variance %</th>
                                    <th class="text-right">Value Lost</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waste_report_data as $row): ?>
                                    <tr class="<?php echo abs($row['variance_percentage']) > WASTE_VARIANCE_THRESHOLD ? 'row-warning' : ''; ?>">
                                        <td><?php echo format_date($row['report_date']); ?></td>
                                        <td><?php echo sanitize_output($row['site_name']); ?></td>
                                        <td><strong><?php echo sanitize_output($row['material_name']); ?></strong></td>
                                        <td class="text-right"><?php echo number_format($row['expected_quantity'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($row['actual_quantity'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($row['variance'], 2); ?></td>
                                        <td class="text-right">
                                            <span class="<?php echo abs($row['variance_percentage']) > WASTE_VARIANCE_THRESHOLD ? 'text-danger' : ''; ?>">
                                                <?php echo number_format($row['variance_percentage'], 2); ?>%
                                            </span>
                                        </td>
                                        <td class="text-right text-danger"><strong><?php echo format_currency(abs($row['variance_value'])); ?></strong></td>
                                        <td><?php echo sanitize_output($row['notes'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Predictive Reorder Report -->
            <?php if ($report_type === 'reorder'): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>Predictive Reorder Report</h2>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 2V10M8 10L5 7M8 10L11 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M2 12V13C2 13.5523 2.44772 14 3 14H13C13.5523 14 14 13.5523 14 13V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            Export CSV
                        </a>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="report-table">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Material</th>
                                    <th>Category</th>
                                    <th class="text-right">Current Qty</th>
                                    <th class="text-right">Reorder Threshold</th>
                                    <th class="text-right">Shortage</th>
                                    <th class="text-right">Reorder Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reorder_report_data as $row): ?>
                                    <tr>
                                        <td><?php echo sanitize_output($row['site_name']); ?></td>
                                        <td><strong><?php echo sanitize_output($row['material_name']); ?></strong></td>
                                        <td><span class="category-badge"><?php echo sanitize_output($row['category']); ?></span></td>
                                        <td class="text-right text-warning"><?php echo number_format($row['current_quantity'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($row['reorder_threshold'], 0); ?></td>
                                        <td class="text-right text-danger"><?php echo number_format($row['shortage'], 2); ?></td>
                                        <td class="text-right"><strong><?php echo format_currency($row['reorder_cost']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script src="scripts.js"></script>
    <script>
        // Show/hide date filters based on report type
        document.getElementById('report_type').addEventListener('change', function() {
            const isWasteReport = this.value === 'waste';
            document.getElementById('date-filter-group').style.display = isWasteReport ? 'block' : 'none';
            document.getElementById('date-to-group').style.display = isWasteReport ? 'block' : 'none';
        });
    </script>
</body>
</html>
