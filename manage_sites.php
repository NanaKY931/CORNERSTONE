<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Site Management
 * 
 * Admin interface for managing construction sites
 * Allows adding, editing, and viewing site details
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// Require admin access
Auth::requireLogin();
if (!Auth::isAdmin()) {
    set_flash('error', 'Access denied. Admin privileges required.');
    redirect('dashboard.php');
}

$user_name = Auth::getFullName();
$success_message = '';
$error_message = '';

// Handle form submission
if (is_post() && isset($_POST['action'])) {
    Auth::requireCSRF();
    
    $action = post('action');
    
    if ($action === 'add_site') {
        $site_name = post('site_name');
        $location = post('location');
        $status = post('status', 'active');
        $completion_percentage = post('completion_percentage', 0);
        $start_date = post('start_date');
        $estimated_completion = post('estimated_completion');
        
        // Validate inputs
        if (empty($site_name) || empty($location) || empty($start_date)) {
            $error_message = 'Please fill in all required fields (Site Name, Location, Start Date).';
        } else {
            try {
                $sql = "INSERT INTO sites (site_name, location, status, completion_percentage, start_date, estimated_completion) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                Database::execute($sql, [
                    $site_name, 
                    $location, 
                    $status, 
                    $completion_percentage, 
                    $start_date, 
                    $estimated_completion
                ], 'sssdss');
                
                $success_message = "Site '$site_name' added successfully!";
                
                // Redirect to prevent form resubmission
                redirect("manage_sites.php?success=" . urlencode($success_message));
            } catch (Exception $e) {
                $error_message = 'Error adding site: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_site') {
        $site_id = post('site_id');
        $site_name = post('site_name');
        $location = post('location');
        $status = post('status');
        $completion_percentage = post('completion_percentage', 0);
        $start_date = post('start_date');
        $estimated_completion = post('estimated_completion');
        
        // Validate inputs
        if (empty($site_id) || empty($site_name) || empty($location) || empty($start_date)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            try {
                $sql = "UPDATE sites 
                        SET site_name = ?, location = ?, status = ?, completion_percentage = ?, 
                            start_date = ?, estimated_completion = ?
                        WHERE id = ?";
                Database::execute($sql, [
                    $site_name, 
                    $location, 
                    $status, 
                    $completion_percentage, 
                    $start_date, 
                    $estimated_completion,
                    $site_id
                ], 'sssdssi');
                
                $success_message = "Site '$site_name' updated successfully!";
                redirect("manage_sites.php?success=" . urlencode($success_message));
            } catch (Exception $e) {
                $error_message = 'Error updating site: ' . $e->getMessage();
            }
        }
    }
}

// Check for success message from redirect
if (get('success')) {
    $success_message = get('success');
}

// Get all sites
$sites = Database::fetchAll("
    SELECT 
        s.*,
        COUNT(DISTINCT i.material_id) AS material_count,
        COALESCE(SUM(i.quantity * m.unit_cost), 0) AS total_value
    FROM sites s
    LEFT JOIN inventory i ON s.id = i.site_id
    LEFT JOIN materials m ON i.material_id = m.id
    GROUP BY s.id
    ORDER BY s.created_at DESC
");

// Get site for editing if edit_id is provided
$edit_site = null;
if (get('edit_id')) {
    $edit_id = get('edit_id');
    $edit_site = Database::fetchOne("SELECT * FROM sites WHERE id = ?", [$edit_id], 'i');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sites - Cornerstone Inventory Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-page">
    
    <!-- Header -->
    <header class="app-header">
        <div class="header-content">
            <div class="header-left">
                <h1>Manage Construction Sites</h1>
                <p>Add and manage building project locations</p>
            </div>
            <div class="header-right">
                <span class="user-info">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 18C4 14.6863 6.68629 12 10 12C13.3137 12 16 14.6863 16 18" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <?php echo sanitize_output($user_name); ?> (Admin)
                </span>
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
            <a href="inventory_admin.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                    <path d="M7 9H13M7 13H11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Inventory
            </a>
            <a href="manage_sites.php" class="nav-link active">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M10 3L3 8V17H7V12H13V17H17V8L10 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                Sites
            </a>
            <a href="reports.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M4 16L8 12L11 15L16 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="8" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="11" cy="15" r="1.5" fill="currentColor"/>
                </svg>
                Reports
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="app-main">
        <div class="container">
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                        <path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <?php echo sanitize_output($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                        <path d="M10 6V11M10 14V14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <?php echo sanitize_output($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Site Form -->
            <div class="card">
                <div class="card-header">
                    <h2><?php echo $edit_site ? 'Edit Site' : 'Add New Site'; ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="manage_sites.php" class="form-grid">
                        <?php echo Auth::csrfField(); ?>
                        <input type="hidden" name="action" value="<?php echo $edit_site ? 'update_site' : 'add_site'; ?>">
                        <?php if ($edit_site): ?>
                            <input type="hidden" name="site_id" value="<?php echo $edit_site['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="site_name">Site Name *</label>
                            <input 
                                type="text" 
                                id="site_name" 
                                name="site_name" 
                                class="form-control" 
                                placeholder="e.g., Sunset Ridge Apartments"
                                value="<?php echo $edit_site ? sanitize_output($edit_site['site_name']) : ''; ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="location">Location *</label>
                            <input 
                                type="text" 
                                id="location" 
                                name="location" 
                                class="form-control" 
                                placeholder="e.g., 1234 West Valley Rd, Phoenix, AZ"
                                value="<?php echo $edit_site ? sanitize_output($edit_site['location']) : ''; ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?php echo ($edit_site && $edit_site['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_site && $edit_site['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="finished" <?php echo ($edit_site && $edit_site['status'] === 'finished') ? 'selected' : ''; ?>>Finished</option>
                                <option value="halted_insufficient_materials" <?php echo ($edit_site && $edit_site['status'] === 'halted_insufficient_materials') ? 'selected' : ''; ?>>Halted - Insufficient Materials</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="completion_percentage">Completion Percentage (%)</label>
                            <input 
                                type="number" 
                                id="completion_percentage" 
                                name="completion_percentage" 
                                class="form-control" 
                                min="0" 
                                max="100" 
                                step="0.01"
                                placeholder="0.00"
                                value="<?php echo $edit_site ? $edit_site['completion_percentage'] : '0'; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input 
                                type="date" 
                                id="start_date" 
                                name="start_date" 
                                class="form-control"
                                value="<?php echo $edit_site ? $edit_site['start_date'] : ''; ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="estimated_completion">Estimated Completion</label>
                            <input 
                                type="date" 
                                id="estimated_completion" 
                                name="estimated_completion" 
                                class="form-control"
                                value="<?php echo $edit_site ? $edit_site['estimated_completion'] : ''; ?>"
                            >
                        </div>

                        <div class="form-actions" style="grid-column: 1 / -1;">
                            <button type="submit" class="btn btn-primary">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M10 5V15M5 10H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <?php echo $edit_site ? 'Update Site' : 'Add Site'; ?>
                            </button>
                            <?php if ($edit_site): ?>
                                <a href="manage_sites.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sites List -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2>All Construction Sites (<?php echo count($sites); ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($sites)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">
                            No sites found. Add your first construction site above.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Site Name</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Completion</th>
                                        <th>Start Date</th>
                                        <th>Est. Completion</th>
                                        <th>Materials</th>
                                        <th>Total Value</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sites as $site): ?>
                                        <tr>
                                            <td><strong><?php echo sanitize_output($site['site_name']); ?></strong></td>
                                            <td><?php echo sanitize_output($site['location']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $site['status'] === 'active' ? 'success' : 
                                                        ($site['status'] === 'finished' ? 'info' : 
                                                        ($site['status'] === 'halted_insufficient_materials' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $site['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar" style="width: <?php echo $site['completion_percentage']; ?>%"></div>
                                                </div>
                                                <span style="font-size: 0.875rem; color: #6b7280;">
                                                    <?php echo number_format($site['completion_percentage'], 1); ?>%
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($site['start_date'])); ?></td>
                                            <td>
                                                <?php echo $site['estimated_completion'] ? date('M d, Y', strtotime($site['estimated_completion'])) : 'N/A'; ?>
                                            </td>
                                            <td><?php echo $site['material_count']; ?> types</td>
                                            <td>$<?php echo number_format($site['total_value'], 2); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="manage_sites.php?edit_id=<?php echo $site['id']; ?>" class="btn btn-small btn-secondary" title="Edit">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                            <path d="M11 2L14 5L5 14H2V11L11 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        </svg>
                                                    </a>
                                                    <a href="inventory_admin.php?site_id=<?php echo $site['id']; ?>" class="btn btn-small btn-primary" title="Manage Inventory">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                            <rect x="2" y="2" width="12" height="12" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                                                            <path d="M5 7H11M5 10H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <script src="scripts.js"></script>
</body>
</html>
