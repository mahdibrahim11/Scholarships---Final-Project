-- Iteration 7 schema/seed change: authentication, roles and user management.
-- Run this ONCE if you created the database before this file existed (a fresh install from 001 + 002 already has it).
-- In phpMyAdmin: click the `scholarships` database FIRST, then open the SQL tab.
SET NAMES utf8mb4;

-- 1. Sessions can be ended server-side (password reset bumps this number).
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'session_version');
SET @sql := IF(@has = 0, 'ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Failed and successful sign-in attempts, used to slow down password guessing.
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_key VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email_key, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Seeded Super-admin. Temporary password: Pathways!2026Temp  (the account must change it at first sign-in).
INSERT INTO users (role_id, email, password_hash, display_name, is_active, must_change_password)
SELECT 4, 'superadmin@pathways.local', '$2y$10$PPYGWrgpnPSer5rE5210n.7O9Xe0O33WIkNy5kv1sMxNG6W5biISq', 'Super Admin', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'superadmin@pathways.local');

-- 4. Reassign everything the placeholder development user did to the real Super-admin, then switch the placeholder off.
SET @dev := (SELECT id FROM users WHERE email = 'dev-placeholder@localhost.invalid' LIMIT 1);
SET @real := (SELECT id FROM users WHERE email = 'superadmin@pathways.local' LIMIT 1);
UPDATE audit_log SET user_id = @real WHERE user_id = @dev AND @dev IS NOT NULL;
UPDATE scholarships SET created_by = @real WHERE created_by = @dev AND @dev IS NOT NULL;
UPDATE scholarships SET updated_by = @real WHERE updated_by = @dev AND @dev IS NOT NULL;
UPDATE scholarships SET verified_by = @real WHERE verified_by = @dev AND @dev IS NOT NULL;
UPDATE import_batches SET uploaded_by = @real WHERE uploaded_by = @dev AND @dev IS NOT NULL;
UPDATE users SET is_active = 0, password_hash = '!' WHERE id = @dev AND @dev IS NOT NULL;
