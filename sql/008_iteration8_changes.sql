-- Iteration 8 schema change: student and parent features (profiles, saved scholarships, application status).
-- Run this ONCE if you created the database before this file existed (a fresh install from 001 + 002 already has it).
-- In phpMyAdmin: click the `scholarships` database FIRST, then open the SQL tab. Safe to run twice.
SET NAMES utf8mb4;

-- When the status of a saved scholarship last changed.
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saved_scholarships' AND COLUMN_NAME = 'status_updated_at');
SET @sql := IF(@has = 0, 'ALTER TABLE saved_scholarships ADD COLUMN status_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- One person cannot have two profiles with the same name. (Before Iteration 8 there was no way to create profiles, so this
-- normally changes nothing; if duplicates exist, the later ones are renamed "Name (2)", "Name (3)" so the rule can be added.)
UPDATE student_profiles p JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY owner_user_id, display_name ORDER BY id) AS rn FROM student_profiles) d ON d.id = p.id
   SET p.display_name = CONCAT(LEFT(p.display_name, 52), ' (', d.rn, ')') WHERE d.rn > 1;
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND INDEX_NAME = 'uq_owner_name');
SET @sql := IF(@has = 0, 'ALTER TABLE student_profiles ADD UNIQUE KEY uq_owner_name (owner_user_id, display_name)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
