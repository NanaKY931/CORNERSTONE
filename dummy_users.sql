-- ============================================
-- DUMMY USER ACCOUNTS FOR WALKTHROUGH
-- ============================================
-- These are test accounts for demonstration purposes
-- Password for all: 'Demo2025!'

-- End-user account (read-only access)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES
('demo_user', 'demo.user@cornerstone.com', '$2y$10$GTlBrhUo4VB/X5sJqsT1s.iUH.j.sr5y.cLiGiD54MHPDCMKMV9mG', 'end_user', 'Demo User');

-- Admin account (full access)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES
('demo_admin', 'demo.admin@cornerstone.com', '$2y$10$GTlBrhUo4VB/X5sJqsT1s.iUH.j.sr5y.cLiGiD54MHPDCMKMV9mG', 'admin', 'Demo Administrator');

-- ============================================
-- NOTES:
-- ============================================
-- demo_user credentials:
--   Username: demo_user
--   Email: demo.user@cornerstone.com
--   Password: Demo2025!
--   Role: end_user (read-only)
--
-- demo_admin credentials:
--   Username: demo_admin
--   Email: demo.admin@cornerstone.com
--   Password: Demo2025!
--   Role: admin (full access)
