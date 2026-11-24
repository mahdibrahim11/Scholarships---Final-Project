-- =====================================================================
-- Pathways to Scholarships  |  Schema v1  |  MySQL 8.x / MariaDB 10.5+
-- Run order: 001_schema.sql, 002_seed_reference.sql, 003_seed_assignments.sql
-- TODO(Jasthi): `users` below is a working draft. Align it with the official
-- users + registrations schema once it is provided.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts, audit_log, search_log, contact_messages, import_staging_rows,
  saved_scholarships, profile_interests, student_profiles, scholarship_fields,
  scholarship_categories, scholarship_grades, scholarships, import_batches,
  student_assignments, national_themes, fields_of_study, categories, providers,
  provider_types, grade_levels, states, users, roles;

-- ---------- Access control ----------
CREATE TABLE roles (
  id TINYINT UNSIGNED PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE          -- "visitor" (id 5) = not logged in; no user account uses it
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id TINYINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,       -- bumped when a password is reset: ends that user's other sessions
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Reference data (managed by Super-admin, FR-SA-04) ----------
CREATE TABLE states (
  code CHAR(2) PRIMARY KEY,                 -- 'US' = National
  name VARCHAR(40) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grade_levels (
  grade TINYINT UNSIGNED PRIMARY KEY,       -- 6..12
  label VARCHAR(20) NOT NULL,
  school_level ENUM('middle','high') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE provider_types (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE providers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  name_key VARCHAR(255) NOT NULL UNIQUE,    -- lowercase, trimmed, no leading "the": used to find/merge spellings
  provider_type_id TINYINT UNSIGNED NULL,
  website VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (provider_type_id) REFERENCES provider_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fields_of_study (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE national_themes (
  id TINYINT UNSIGNED PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who collects what (from the Data Science assignment sheet). Used to read the
-- LastName_FirstName file name and to check theme + sponsor-letter range.
CREATE TABLE student_assignments (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  last_name VARCHAR(60) NOT NULL,
  first_name VARCHAR(60) NOT NULL,
  state_code CHAR(2) NOT NULL,
  state_range VARCHAR(10) NOT NULL DEFAULT 'All',      -- All, A-M, N-Z, A-H, I-P, Q-Z
  national_theme_id TINYINT UNSIGNED NOT NULL,
  national_range VARCHAR(10) NOT NULL,                  -- A-M or N-Z
  UNIQUE KEY uq_student (last_name, first_name),
  FOREIGN KEY (state_code) REFERENCES states(code),
  FOREIGN KEY (national_theme_id) REFERENCES national_themes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Import ----------
CREATE TABLE import_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_file_name VARCHAR(255) NOT NULL,
  student_assignment_id SMALLINT UNSIGNED NULL,
  uploaded_by INT UNSIGNED NULL,                        -- nullable until Iteration 7
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('staged','committed','rolled_back','discarded') NOT NULL DEFAULT 'staged',
  committed_at DATETIME NULL,
  rolled_back_at DATETIME NULL,
  rows_total INT UNSIGNED NOT NULL DEFAULT 0,
  rows_ready INT UNSIGNED NOT NULL DEFAULT 0,
  rows_warning INT UNSIGNED NOT NULL DEFAULT 0,
  rows_duplicate INT UNSIGNED NOT NULL DEFAULT 0,
  rows_error INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (student_assignment_id) REFERENCES student_assignments(id),
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE import_staging_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  source_row INT UNSIGNED NOT NULL,                        -- spreadsheet row number (ROW_NUMBER is a reserved word)
  raw_json JSON NOT NULL,
  cleaned_json JSON NULL,
  status ENUM('ready','warning','duplicate','error') NOT NULL,
  messages_json JSON NULL,
  url_key VARCHAR(500) NULL,
  KEY idx_batch_status (batch_id, status),
  KEY idx_url_key (url_key),
  FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Scholarships ----------
CREATE TABLE scholarships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  provider_id INT UNSIGNED NOT NULL,
  official_url VARCHAR(2048) NOT NULL,
  url_key VARCHAR(500) NOT NULL,                        -- normalized copy (see includes/import/cleaner.php)
  -- MySQL has no partial unique index, so uniqueness among ACTIVE rows uses a generated column.
  -- NULLs are ignored by UNIQUE, so archived rows never conflict.
  active_url_key VARCHAR(500) GENERATED ALWAYS AS (IF(status = 'active', url_key, NULL)) STORED,
  discovery_source VARCHAR(255) NULL,
  state_code CHAR(2) NOT NULL,                          -- 'US' = National (including multi-state / regional)
  scope ENUM('national','state_local') NOT NULL,
  national_theme_id TINYINT UNSIGNED NULL,
  school_level ENUM('middle','high','both') NULL,       -- derived from scholarship_grades on every save
  award_min DECIMAL(10,2) NULL,
  award_max DECIMAL(10,2) NULL,
  num_awards INT UNSIGNED NULL,
  renewable ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  max_potential_value DECIMAL(12,2) NULL,
  open_date DATE NULL,
  deadline DATE NULL,
  is_rolling TINYINT(1) NOT NULL DEFAULT 0,
  residency_requirement TEXT NULL,
  min_gpa DECIMAL(3,2) NULL,
  need_required ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  essay_required ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  recommendation_required ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  transcript_required ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  community_service_required ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  leadership_required ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  other_requirements TEXT NULL,
  application_method VARCHAR(100) NULL,
  verification_date DATE NULL,
  verified_by INT UNSIGNED NULL,
  notes TEXT NULL,
  contributing_student_id SMALLINT UNSIGNED NULL,
  import_batch_id INT UNSIGNED NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_active_url (active_url_key),
  KEY idx_state (state_code),
  KEY idx_deadline (deadline),
  KEY idx_url_key (url_key),
  KEY idx_status_scope (status, scope),
  KEY idx_batch (import_batch_id),
  FOREIGN KEY (provider_id) REFERENCES providers(id),
  FOREIGN KEY (state_code) REFERENCES states(code),
  FOREIGN KEY (national_theme_id) REFERENCES national_themes(id),
  FOREIGN KEY (contributing_student_id) REFERENCES student_assignments(id),
  FOREIGN KEY (import_batch_id) REFERENCES import_batches(id),
  FOREIGN KEY (verified_by) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_grades (
  scholarship_id INT UNSIGNED NOT NULL,
  grade TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, grade),
  KEY idx_grade (grade),
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  FOREIGN KEY (grade) REFERENCES grade_levels(grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_categories (
  scholarship_id INT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, category_id),
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_fields (
  scholarship_id INT UNSIGNED NOT NULL,
  field_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, field_id),
  KEY idx_field (field_id),
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields_of_study(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Student / parent profiles (minimum data only: grade, state, interests) ----------
CREATE TABLE student_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,                  -- the student, or the parent who manages the child
  display_name VARCHAR(60) NOT NULL,                    -- a nickname is fine; never DOB/school/address/phone
  grade TINYINT UNSIGNED NOT NULL,
  state_code CHAR(2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_owner_name (owner_user_id, display_name),   -- one person cannot have two profiles with the same name
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (grade) REFERENCES grade_levels(grade),
  FOREIGN KEY (state_code) REFERENCES states(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE profile_interests (
  profile_id INT UNSIGNED NOT NULL,
  field_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (profile_id, field_id),
  FOREIGN KEY (profile_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields_of_study(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE saved_scholarships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id INT UNSIGNED NOT NULL,
  scholarship_id INT UNSIGNED NOT NULL,
  app_status ENUM('Interested','In Progress','Submitted','Awarded','Not Awarded') NOT NULL DEFAULT 'Interested',
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_profile_scholarship (profile_id, scholarship_id),
  FOREIGN KEY (profile_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Misc ----------
CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE search_log (                               -- anonymous: no user id, no IP
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  state_code CHAR(2) NULL,
  grade TINYINT UNSIGNED NULL,
  field_id SMALLINT UNSIGNED NULL,
  searched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_key VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email_key, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,                          -- login, scholarship.edit, import.commit, import.rollback, export ...
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_action (action),
  KEY idx_entity (entity_type, entity_id),
  KEY idx_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
