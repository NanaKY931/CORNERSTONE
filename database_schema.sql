-- ============================================
-- CORNERSTONE INVENTORY TRACKER - DATABASE SCHEMA
-- ============================================
-- Construction Materials Inventory Management System
-- Created: 2025-12-13
-- Database: cornerstone_inventory
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS cornerstone_inventory;
USE cornerstone_inventory;

-- ============================================
-- TABLE 1: users
-- Purpose: Authentication and role management
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'end_user') NOT NULL DEFAULT 'end_user',
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 2: sites
-- Purpose: Building project sites
-- ============================================
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'finished', 'halted_insufficient_materials') NOT NULL DEFAULT 'active',
    completion_percentage DECIMAL(5,2) DEFAULT 0.00,
    start_date DATE NOT NULL,
    estimated_completion DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_site_name (site_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 3: materials
-- Purpose: Master list of material types
-- ============================================
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(100) NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    reorder_threshold INT NOT NULL DEFAULT 50,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_material_name (material_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 4: inventory
-- Purpose: Current stock levels per site
-- ============================================
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_material (site_id, material_id),
    INDEX idx_site_id (site_id),
    INDEX idx_material_id (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 5: transactions
-- Purpose: All inventory movements
-- ============================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    material_id INT NOT NULL,
    transaction_type ENUM('IN', 'OUT', 'TRANSFER_IN', 'TRANSFER_OUT') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    related_site_id INT NULL,
    user_id INT NOT NULL,
    notes TEXT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (related_site_id) REFERENCES sites(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_site_id (site_id),
    INDEX idx_material_id (material_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 6: alerts
-- Purpose: System-generated alerts
-- ============================================
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    material_id INT NOT NULL,
    alert_type ENUM('low_stock', 'predictive_reorder', 'excess_stock') NOT NULL,
    message TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    INDEX idx_is_resolved (is_resolved),
    INDEX idx_alert_type (alert_type),
    INDEX idx_site_id (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 7: waste_reports
-- Purpose: Variance tracking (shrinkage audit)
-- ============================================
CREATE TABLE IF NOT EXISTS waste_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    material_id INT NOT NULL,
    expected_quantity DECIMAL(10,2) NOT NULL,
    actual_quantity DECIMAL(10,2) NOT NULL,
    variance DECIMAL(10,2) GENERATED ALWAYS AS (expected_quantity - actual_quantity) STORED,
    variance_percentage DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN expected_quantity > 0 THEN ((expected_quantity - actual_quantity) / expected_quantity * 100)
            ELSE 0
        END
    ) STORED,
    report_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    INDEX idx_site_id (site_id),
    INDEX idx_report_date (report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 8: verification_codes
-- Purpose: Email verification codes
-- ============================================
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    user_data TEXT NOT NULL COMMENT 'JSON: {username, full_name, password_hash}',
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_code (code),
    INDEX idx_expires (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SAMPLE DATA INSERTION
-- ============================================

-- Insert sample users
-- Password for both: 'password123' (hashed with bcrypt)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES
('admin', 'admin@cornerstone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'John Anderson'),
('manager', 'manager@cornerstone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'end_user', 'Sarah Chen'),
('foreman1', 'foreman@cornerstone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Mike Rodriguez'),
('finance', 'finance@cornerstone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'end_user', 'Marcus Williams');

-- Insert sample sites (matching case scenario)
INSERT INTO sites (site_name, location, status, completion_percentage, start_date, estimated_completion) VALUES
('Sunset Ridge Apartments', '1234 West Valley Rd, Phoenix, AZ', 'active', 60.00, '2024-08-01', '2025-06-30'),
('Downtown Lofts', '567 Central Ave, Phoenix, AZ', 'active', 30.00, '2024-11-15', '2025-09-15'),
('Meadowbrook Townhomes', '890 Meadow Lane, Phoenix, AZ', 'active', 85.00, '2024-05-01', '2025-02-28');

-- Insert sample materials
INSERT INTO materials (material_name, unit_of_measure, unit_cost, reorder_threshold, category) VALUES
-- Concrete & Masonry
('Portland Cement', 'bags (94 lbs)', 12.50, 100, 'Concrete'),
('Ready-Mix Concrete', 'cubic yards', 125.00, 20, 'Concrete'),
('Concrete Blocks', 'units', 2.75, 200, 'Masonry'),
('Mortar Mix', 'bags (80 lbs)', 8.50, 50, 'Masonry'),

-- Steel & Rebar
('Rebar #4 (1/2")', 'units (20 ft)', 8.25, 150, 'Steel'),
('Rebar #5 (5/8")', 'units (20 ft)', 12.00, 100, 'Steel'),
('Steel I-Beams', 'units', 450.00, 10, 'Steel'),

-- Lumber
('2x4 Studs (8 ft)', 'boards', 4.50, 300, 'Lumber'),
('2x6 Studs (8 ft)', 'boards', 7.25, 200, 'Lumber'),
('Plywood 4x8 (3/4")', 'sheets', 45.00, 100, 'Lumber'),
('OSB 4x8 (7/16")', 'sheets', 28.00, 150, 'Lumber'),

-- Roofing
('Asphalt Shingles', 'bundles', 32.00, 80, 'Roofing'),
('Roofing Felt', 'rolls', 18.50, 40, 'Roofing'),
('Roofing Nails', 'boxes (5 lbs)', 12.00, 50, 'Roofing'),

-- Electrical
('Romex Wire 12/2', 'rolls (250 ft)', 85.00, 30, 'Electrical'),
('Electrical Boxes', 'units', 1.25, 200, 'Electrical'),
('Circuit Breakers', 'units', 15.00, 50, 'Electrical'),

-- Plumbing
('PVC Pipe 3/4"', 'units (10 ft)', 3.50, 100, 'Plumbing'),
('Copper Pipe 1/2"', 'units (10 ft)', 18.00, 80, 'Plumbing'),
('PEX Tubing', 'rolls (100 ft)', 45.00, 40, 'Plumbing');

-- Insert sample inventory (current stock levels)
-- Sunset Ridge Apartments
INSERT INTO inventory (site_id, material_id, quantity) VALUES
(1, 1, 250),  -- Portland Cement: 250 bags
(1, 2, 45),   -- Ready-Mix Concrete: 45 cubic yards
(1, 5, 320),  -- Rebar #4: 320 units (EXCESS - for transfer scenario)
(1, 8, 450),  -- 2x4 Studs: 450 boards
(1, 10, 120), -- Plywood: 120 sheets
(1, 12, 95),  -- Asphalt Shingles: 95 bundles
(1, 15, 42),  -- Romex Wire: 42 rolls
(1, 18, 65);  -- PVC Pipe: 65 units

-- Downtown Lofts
INSERT INTO inventory (site_id, material_id, quantity) VALUES
(2, 1, 180),  -- Portland Cement: 180 bags
(2, 2, 28),   -- Ready-Mix Concrete: 28 cubic yards
(2, 5, 45),   -- Rebar #4: 45 units (LOW - needs transfer)
(2, 8, 380),  -- 2x4 Studs: 380 boards
(2, 10, 85),  -- Plywood: 85 sheets
(2, 15, 25),  -- Romex Wire: 25 units (LOW STOCK)
(2, 16, 220), -- Electrical Boxes: 220 units
(2, 19, 55);  -- Copper Pipe: 55 units

-- Meadowbrook Townhomes
INSERT INTO inventory (site_id, material_id, quantity) VALUES
(3, 1, 95),   -- Portland Cement: 95 bags
(3, 5, 140),  -- Rebar #4: 140 units
(3, 8, 280),  -- 2x4 Studs: 280 boards
(3, 9, 190),  -- 2x6 Studs: 190 boards
(3, 10, 75),  -- Plywood: 75 sheets
(3, 12, 35),  -- Asphalt Shingles: 35 bundles (LOW STOCK)
(3, 13, 28),  -- Roofing Felt: 28 rolls (LOW STOCK)
(3, 18, 88);  -- PVC Pipe: 88 units

-- Insert sample transactions (recent activity)
INSERT INTO transactions (site_id, material_id, transaction_type, quantity, related_site_id, user_id, notes) VALUES
-- Recent deliveries
(1, 1, 'IN', 100, NULL, 3, 'Delivery from Phoenix Cement Supply'),
(2, 8, 'IN', 200, NULL, 3, 'Lumber delivery - Invoice #45821'),
(3, 12, 'IN', 50, NULL, 3, 'Roofing materials for final phase'),

-- Recent usage
(1, 2, 'OUT', 15, NULL, 3, 'Foundation pour - Building C'),
(2, 15, 'OUT', 8, NULL, 3, 'Electrical rough-in - Units 5-8'),
(3, 8, 'OUT', 45, NULL, 3, 'Framing - Units 14-16'),

-- Sample transfer (this would be the scenario from case study)
(1, 5, 'TRANSFER_OUT', 200, 2, 1, 'Transfer to Downtown Lofts - excess rebar'),
(2, 5, 'TRANSFER_IN', 200, 1, 1, 'Received from Sunset Ridge');

-- Insert sample alerts (low stock warnings)
INSERT INTO alerts (site_id, material_id, alert_type, message, is_resolved) VALUES
(2, 15, 'low_stock', 'Romex Wire 12/2 at Downtown Lofts is below reorder threshold (25 units remaining)', FALSE),
(3, 12, 'low_stock', 'Asphalt Shingles at Meadowbrook Townhomes is below reorder threshold (35 bundles remaining)', FALSE),
(3, 13, 'low_stock', 'Roofing Felt at Meadowbrook Townhomes is below reorder threshold (28 rolls remaining)', FALSE),
(2, 5, 'predictive_reorder', 'Rebar #4 at Downtown Lofts projected to run out in 5 days based on usage trends', FALSE);

-- Insert sample waste reports (variance tracking)
INSERT INTO waste_reports (site_id, material_id, expected_quantity, actual_quantity, report_date, notes) VALUES
(1, 1, 300, 250, '2025-12-01', 'Monthly audit - December'),
(1, 8, 500, 450, '2025-12-01', 'Some boards damaged during storm'),
(2, 15, 35, 25, '2025-12-01', 'Higher than expected usage - investigate'),
(3, 12, 50, 35, '2025-12-01', 'Possible theft or damage - security review needed');

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Uncomment these to verify data after import

-- SELECT 'Users Created:' AS Info, COUNT(*) AS Count FROM users;
-- SELECT 'Sites Created:' AS Info, COUNT(*) AS Count FROM sites;
-- SELECT 'Materials Created:' AS Info, COUNT(*) AS Count FROM materials;
-- SELECT 'Inventory Records:' AS Info, COUNT(*) AS Count FROM inventory;
-- SELECT 'Transactions Logged:' AS Info, COUNT(*) AS Count FROM transactions;
-- SELECT 'Active Alerts:' AS Info, COUNT(*) AS Count FROM alerts WHERE is_resolved = FALSE;
-- SELECT 'Waste Reports:' AS Info, COUNT(*) AS Count FROM waste_reports;

-- ============================================
-- USEFUL QUERIES FOR TESTING
-- ============================================

-- View all inventory with site and material names
-- SELECT 
--     s.site_name,
--     m.material_name,
--     i.quantity,
--     m.unit_of_measure,
--     m.unit_cost,
--     (i.quantity * m.unit_cost) AS total_value
-- FROM inventory i
-- JOIN sites s ON i.site_id = s.id
-- JOIN materials m ON i.material_id = m.id
-- ORDER BY s.site_name, m.category;

-- View low stock alerts
-- SELECT 
--     s.site_name,
--     m.material_name,
--     i.quantity,
--     m.reorder_threshold,
--     a.message
-- FROM alerts a
-- JOIN sites s ON a.site_id = s.id
-- JOIN materials m ON a.material_id = m.id
-- JOIN inventory i ON i.site_id = a.site_id AND i.material_id = a.material_id
-- WHERE a.is_resolved = FALSE;

-- View recent transactions
-- SELECT 
--     t.transaction_date,
--     s.site_name,
--     m.material_name,
--     t.transaction_type,
--     t.quantity,
--     u.full_name AS performed_by
-- FROM transactions t
-- JOIN sites s ON t.site_id = s.id
-- JOIN materials m ON t.material_id = m.id
-- JOIN users u ON t.user_id = u.id
-- ORDER BY t.transaction_date DESC
-- LIMIT 20;

-- ============================================
-- END OF SCHEMA
-- ============================================
