<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Admin Inventory Management
 * 
 * Interface for admins (foremen, contractors) to update inventory
 * Supports IN, OUT, and TRANSFER operations
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// Require admin access
Auth::requireAdmin();

$user_id = Auth::getUserId();
$user_name = Auth::getFullName();

// Get all active sites
$sites = Database::fetchAll("
    SELECT id, site_name, location, status
    FROM sites
    WHERE status IN ('active', 'halted_insufficient_materials')
    ORDER BY site_name ASC
");

// Get all materials
$materials = Database::fetchAll("
    SELECT id, material_name, category, unit_of_measure, unit_cost, reorder_threshold
    FROM materials
    ORDER BY category, material_name ASC
");

// Get selected site (default to first site)
$selected_site_id = get('site_id', $sites[0]['id'] ?? null);

// Get current inventory for selected site
$current_inventory = [];
if ($selected_site_id) {
    $current_inventory = Database::fetchAll("
        SELECT 
            m.id AS material_id,
            m.material_name,
            m.category,
            m.unit_of_measure,
            m.reorder_threshold,
            COALESCE(i.quantity, 0) AS quantity
        FROM materials m
        LEFT JOIN inventory i ON m.id = i.material_id AND i.site_id = ?
        ORDER BY m.category, m.material_name
    ", [$selected_site_id], 'i');
}

// Get recent transactions for selected site
$recent_transactions = [];
if ($selected_site_id) {
    $recent_transactions = Database::fetchAll("
        SELECT 
            t.id,
            t.transaction_type,
            t.quantity,
            t.notes,
            t.transaction_date,
            m.material_name,
            m.unit_of_measure,
            u.full_name AS performed_by,
            s2.site_name AS related_site_name
        FROM transactions t
        JOIN materials m ON t.material_id = m.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN sites s2 ON t.related_site_id = s2.id
        WHERE t.site_id = ?
        ORDER BY t.transaction_date DESC
        LIMIT 20
    ", [$selected_site_id], 'i');
}

// Handle form submission
$success_message = '';
$error_message = '';

if (is_post() && isset($_POST['action'])) {
    Auth::requireCSRF();
    
    $action = post('action');
    $site_id = post('site_id');
    $material_id = post('material_id');
    $quantity = post('quantity');
    $notes = post('notes', '');
    
    // Validate inputs
    if (empty($site_id) || empty($material_id) || empty($quantity) || $quantity <= 0) {
        $error_message = 'Please fill in all required fields with valid values.';
    } else {
        try {
            Database::beginTransaction();
            
            if ($action === 'IN') {
                // Add to inventory
                $sql = "INSERT INTO inventory (site_id, material_id, quantity) 
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE quantity = quantity + ?";
                Database::execute($sql, [$site_id, $material_id, $quantity, $quantity], 'iidi');
                
                // Log transaction
                $sql = "INSERT INTO transactions (site_id, material_id, transaction_type, quantity, user_id, notes)
                        VALUES (?, ?, 'IN', ?, ?, ?)";
                Database::execute($sql, [$site_id, $material_id, $quantity, $user_id, $notes], 'iidis');
                
                $success_message = 'Materials added successfully.';
                
            } elseif ($action === 'OUT') {
                // Check if sufficient quantity exists
                $current = Database::fetchOne("
                    SELECT COALESCE(quantity, 0) AS quantity 
                    FROM inventory 
                    WHERE site_id = ? AND material_id = ?
                ", [$site_id, $material_id], 'ii');
                
                if (!$current || $current['quantity'] < $quantity) {
                    throw new Exception('Insufficient inventory. Cannot remove more than available.');
                }
                
                // Remove from inventory
                $sql = "UPDATE inventory 
                        SET quantity = quantity - ? 
                        WHERE site_id = ? AND material_id = ?";
                Database::execute($sql, [$quantity, $site_id, $material_id], 'dii');
                
                // Log transaction
                $sql = "INSERT INTO transactions (site_id, material_id, transaction_type, quantity, user_id, notes)
                        VALUES (?, ?, 'OUT', ?, ?, ?)";
                Database::execute($sql, [$site_id, $material_id, $quantity, $user_id, $notes], 'iidis');
                
                $success_message = 'Materials removed successfully.';
                
            } elseif ($action === 'TRANSFER') {
                $destination_site_id = post('destination_site_id');
                
                if (empty($destination_site_id) || $destination_site_id == $site_id) {
                    throw new Exception('Please select a valid destination site.');
                }
                
                // Check if sufficient quantity exists at source
                $current = Database::fetchOne("
                    SELECT COALESCE(quantity, 0) AS quantity 
                    FROM inventory 
                    WHERE site_id = ? AND material_id = ?
                ", [$site_id, $material_id], 'ii');
                
                if (!$current || $current['quantity'] < $quantity) {
                    throw new Exception('Insufficient inventory at source site.');
                }
                
                // Remove from source site
                $sql = "UPDATE inventory 
                        SET quantity = quantity - ? 
                        WHERE site_id = ? AND material_id = ?";
                Database::execute($sql, [$quantity, $site_id, $material_id], 'dii');
                
                // Add to destination site
                $sql = "INSERT INTO inventory (site_id, material_id, quantity) 
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE quantity = quantity + ?";
                Database::execute($sql, [$destination_site_id, $material_id, $quantity, $quantity], 'iidi');
                
                // Log TRANSFER_OUT transaction
                $sql = "INSERT INTO transactions (site_id, material_id, transaction_type, quantity, related_site_id, user_id, notes)
                        VALUES (?, ?, 'TRANSFER_OUT', ?, ?, ?, ?)";
                Database::execute($sql, [$site_id, $material_id, $quantity, $destination_site_id, $user_id, $notes], 'iidiis');
                
                // Log TRANSFER_IN transaction
                $sql = "INSERT INTO transactions (site_id, material_id, transaction_type, quantity, related_site_id, user_id, notes)
                        VALUES (?, ?, 'TRANSFER_IN', ?, ?, ?, ?)";
                Database::execute($sql, [$destination_site_id, $material_id, $quantity, $site_id, $user_id, $notes], 'iidiis');
                
                $success_message = 'Materials transferred successfully.';
            }
            
            Database::commit();
            
            // Refresh page to show updated data
            redirect("inventory_admin.php?site_id=$site_id&success=" . urlencode($success_message));
            
        } catch (Exception $e) {
            Database::rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Check for success message from redirect
if (get('success')) {
    $success_message = get('success');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory - Cornerstone Inventory Tracker</title>
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
                    <span class="user-role">Admin</span>
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
            <a href="inventory_admin.php" class="nav-link active">
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
            <a href="reports.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M4 16L8 10L12 14L16 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="8" cy="10" r="1.5" fill="currentColor"/>
                    <circle cx="12" cy="14" r="1.5" fill="currentColor"/>
                </svg>
                Reports
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="app-main">
        <div class="container-fluid">
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Manage Inventory</h1>
                <p>Update stock levels and perform transfers</p>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo sanitize_output($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo sanitize_output($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-grid">
                <!-- Left Column - Transaction Form -->
                <div class="admin-form-section">
                    <div class="card">
                        <div class="card-header">
                            <h2>Record Transaction</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="inventory_admin.php" id="transaction-form">
                                <?php echo Auth::csrfField(); ?>
                                
                                <!-- Site Selection -->
                                <div class="form-group">
                                    <label for="site_id">Site *</label>
                                    <select name="site_id" id="site_id" class="form-control" required onchange="this.form.submit()">
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?php echo $site['id']; ?>" <?php echo $site['id'] == $selected_site_id ? 'selected' : ''; ?>>
                                                <?php echo sanitize_output($site['site_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Material Selection -->
                                <div class="form-group">
                                    <label for="material_id">Material *</label>
                                    <select name="material_id" id="material_id" class="form-control" required>
                                        <option value="">Select material...</option>
                                        <?php 
                                        $current_category = '';
                                        foreach ($materials as $material): 
                                            if ($current_category !== $material['category']):
                                                if ($current_category !== '') echo '</optgroup>';
                                                $current_category = $material['category'];
                                                echo '<optgroup label="' . sanitize_output($current_category) . '">';
                                            endif;
                                        ?>
                                            <option value="<?php echo $material['id']; ?>" 
                                                    data-unit="<?php echo sanitize_output($material['unit_of_measure']); ?>"
                                                    data-cost="<?php echo $material['unit_cost']; ?>">
                                                <?php echo sanitize_output($material['material_name']); ?> (<?php echo sanitize_output($material['unit_of_measure']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($current_category !== '') echo '</optgroup>'; ?>
                                    </select>
                                </div>

                                <!-- Quantity -->
                                <div class="form-group">
                                    <label for="quantity">Quantity *</label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" 
                                           step="0.01" min="0.01" required placeholder="Enter quantity">
                                </div>

                                <!-- Transaction Type Buttons -->
                                <div class="form-group">
                                    <label>Transaction Type *</label>
                                    <div class="transaction-type-buttons">
                                        <button type="button" class="btn-transaction btn-transaction-in" data-action="IN">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                            IN
                                        </button>
                                        <button type="button" class="btn-transaction btn-transaction-out" data-action="OUT">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                            OUT
                                        </button>
                                        <button type="button" class="btn-transaction btn-transaction-transfer" data-action="TRANSFER">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M4 8H14M14 8L11 5M14 8L11 11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                <path d="M16 12H6M6 12L9 9M6 12L9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                            TRANSFER
                                        </button>
                                    </div>
                                    <input type="hidden" name="action" id="action" required>
                                </div>

                                <!-- Destination Site (for transfers) -->
                                <div class="form-group" id="destination-site-group" style="display: none;">
                                    <label for="destination_site_id">Destination Site *</label>
                                    <select name="destination_site_id" id="destination_site_id" class="form-control">
                                        <option value="">Select destination...</option>
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?php echo $site['id']; ?>">
                                                <?php echo sanitize_output($site['site_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Notes -->
                                <div class="form-group">
                                    <label for="notes">Notes (Optional)</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="3" 
                                              placeholder="Add any additional notes about this transaction..."></textarea>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary btn-block" id="submit-btn" disabled>
                                    Submit Transaction
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Current Inventory & Recent Transactions -->
                <div class="admin-info-section">
                    <!-- Current Inventory -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Current Inventory</h3>
                        </div>
                        <div class="card-body">
                            <div class="inventory-list">
                                <?php if (count($current_inventory) > 0): ?>
                                    <?php foreach ($current_inventory as $item): ?>
                                        <div class="inventory-item">
                                            <div class="inventory-item-name">
                                                <?php echo sanitize_output($item['material_name']); ?>
                                                <span class="category-badge-small"><?php echo sanitize_output($item['category']); ?></span>
                                            </div>
                                            <div class="inventory-item-quantity">
                                                <span class="quantity-value <?php echo $item['quantity'] < $item['reorder_threshold'] ? 'quantity-low' : ''; ?>">
                                                    <?php echo number_format($item['quantity'], 2); ?>
                                                </span>
                                                <span class="quantity-unit"><?php echo sanitize_output($item['unit_of_measure']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No inventory data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Transactions</h3>
                        </div>
                        <div class="card-body">
                            <div class="transactions-list">
                                <?php if (count($recent_transactions) > 0): ?>
                                    <?php foreach ($recent_transactions as $trans): ?>
                                        <div class="transaction-item">
                                            <div class="transaction-header">
                                                <span class="transaction-type transaction-type-<?php echo strtolower($trans['transaction_type']); ?>">
                                                    <?php echo sanitize_output($trans['transaction_type']); ?>
                                                </span>
                                                <span class="transaction-date"><?php echo format_datetime($trans['transaction_date']); ?></span>
                                            </div>
                                            <div class="transaction-details">
                                                <strong><?php echo sanitize_output($trans['material_name']); ?></strong>
                                                <span class="transaction-quantity">
                                                    <?php echo number_format($trans['quantity'], 2); ?> <?php echo sanitize_output($trans['unit_of_measure']); ?>
                                                </span>
                                            </div>
                                            <?php if ($trans['related_site_name']): ?>
                                                <div class="transaction-related">
                                                    <?php echo strpos($trans['transaction_type'], 'OUT') !== false ? 'To: ' : 'From: '; ?>
                                                    <?php echo sanitize_output($trans['related_site_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($trans['notes']): ?>
                                                <div class="transaction-notes"><?php echo sanitize_output($trans['notes']); ?></div>
                                            <?php endif; ?>
                                            <div class="transaction-user">By: <?php echo sanitize_output($trans['performed_by']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No recent transactions.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="scripts.js"></script>
    <script>
        // Initialize admin inventory features
        document.addEventListener('DOMContentLoaded', function() {
            initializeTransactionForm();
        });
    </script>
</body>
</html>
