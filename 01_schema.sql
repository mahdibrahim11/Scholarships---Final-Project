-- Iteration 2: Database Foundation and Site Skeleton
-- Scholarship Finder schema SQL
-- Target DB: MySQL/MariaDB

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS import_rows;
DROP TABLE IF EXISTS import_batches;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS scholarship_applications;
DROP TABLE IF EXISTS saved_scholarships;
DROP TABLE IF EXISTS student_profile_themes;
DROP TABLE IF EXISTS student_profile_fields;
DROP TABLE IF EXISTS student_profile_categories;
DROP TABLE IF EXISTS student_profiles;
DROP TABLE IF EXISTS scholarship_eligibility;
DROP TABLE IF EXISTS scholarship_themes;
DROP TABLE IF EXISTS scholarship_fields;
DROP TABLE IF EXISTS scholarship_categories;
DROP TABLE IF EXISTS scholarship_grades;
DROP TABLE IF EXISTS scholarship_states;
DROP TABLE IF EXISTS scholarships;
DROP TABLE IF EXISTS providers;
DROP TABLE IF EXISTS national_themes;
DROP TABLE IF EXISTS provider_types;
DROP TABLE IF EXISTS fields_of_study;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS grade_levels;
DROP TABLE IF EXISTS states;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(50) NOT NULL UNIQUE,
  role_name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  deleted_by_user_id INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_users_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users(user_id),
  INDEX idx_users_role_id (role_id),
  INDEX idx_users_active (is_active),
  INDEX idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE states (
  state_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  state_code VARCHAR(2) NOT NULL UNIQUE,
  state_name VARCHAR(100) NOT NULL UNIQUE,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grade_levels (
  grade_level_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grade_key VARCHAR(20) NOT NULL UNIQUE,
  grade_label VARCHAR(50) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fields_of_study (
  field_of_study_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  field_name VARCHAR(150) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE provider_types (
  provider_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_type_name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE national_themes (
  national_theme_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  theme_name VARCHAR(150) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE providers (
  provider_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_type_id INT UNSIGNED NOT NULL,
  provider_name VARCHAR(200) NOT NULL,
  website_url VARCHAR(500) NULL,
  contact_email VARCHAR(255) NULL,
  phone VARCHAR(40) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  deleted_by_user_id INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_providers_type FOREIGN KEY (provider_type_id) REFERENCES provider_types(provider_type_id),
  CONSTRAINT fk_providers_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_providers_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_providers_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users(user_id),
  INDEX idx_providers_type (provider_type_id),
  INDEX idx_providers_name (provider_name),
  INDEX idx_providers_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarships (
  scholarship_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  eligibility_summary TEXT NULL,
  award_amount_min DECIMAL(10,2) NULL,
  award_amount_max DECIMAL(10,2) NULL,
  is_renewable TINYINT(1) NOT NULL DEFAULT 0,
  application_url VARCHAR(500) NULL,
  deadline_date DATE NULL,
  opens_at DATE NULL,
  closes_at DATE NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  deleted_by_user_id INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_scholarships_provider FOREIGN KEY (provider_id) REFERENCES providers(provider_id),
  CONSTRAINT fk_scholarships_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_scholarships_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_scholarships_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users(user_id),
  INDEX idx_scholarships_provider (provider_id),
  INDEX idx_scholarships_status (status),
  INDEX idx_scholarships_deadline (deadline_date),
  FULLTEXT INDEX ft_scholarships_title_desc (title, description, eligibility_summary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_states (
  scholarship_id INT UNSIGNED NOT NULL,
  state_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, state_id),
  CONSTRAINT fk_scholarship_states_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  CONSTRAINT fk_scholarship_states_state FOREIGN KEY (state_id) REFERENCES states(state_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_grades (
  scholarship_id INT UNSIGNED NOT NULL,
  grade_level_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, grade_level_id),
  CONSTRAINT fk_scholarship_grades_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  CONSTRAINT fk_scholarship_grades_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_categories (
  scholarship_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, category_id),
  CONSTRAINT fk_scholarship_categories_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  CONSTRAINT fk_scholarship_categories_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_fields (
  scholarship_id INT UNSIGNED NOT NULL,
  field_of_study_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, field_of_study_id),
  CONSTRAINT fk_scholarship_fields_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  CONSTRAINT fk_scholarship_fields_field FOREIGN KEY (field_of_study_id) REFERENCES fields_of_study(field_of_study_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_themes (
  scholarship_id INT UNSIGNED NOT NULL,
  national_theme_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (scholarship_id, national_theme_id),
  CONSTRAINT fk_scholarship_themes_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  CONSTRAINT fk_scholarship_themes_theme FOREIGN KEY (national_theme_id) REFERENCES national_themes(national_theme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_eligibility (
  scholarship_id INT UNSIGNED PRIMARY KEY,
  min_gpa DECIMAL(3,2) NULL,
  max_household_income DECIMAL(12,2) NULL,
  need_based TINYINT(1) NOT NULL DEFAULT 0,
  merit_based TINYINT(1) NOT NULL DEFAULT 0,
  citizenship_required TINYINT(1) NOT NULL DEFAULT 0,
  essay_required TINYINT(1) NOT NULL DEFAULT 0,
  recommendation_required TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_eligibility_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  INDEX idx_eligibility_gpa (min_gpa),
  INDEX idx_eligibility_need_merit (need_based, merit_based)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  state_id INT UNSIGNED NULL,
  grade_level_id INT UNSIGNED NULL,
  graduation_year YEAR NULL,
  gpa DECIMAL(3,2) NULL,
  household_income DECIMAL(12,2) NULL,
  first_generation TINYINT(1) NOT NULL DEFAULT 0,
  military_affiliation TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_student_profiles_state FOREIGN KEY (state_id) REFERENCES states(state_id),
  CONSTRAINT fk_student_profiles_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(grade_level_id),
  INDEX idx_student_profiles_state_grade (state_id, grade_level_id),
  INDEX idx_student_profiles_gpa (gpa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_profile_categories (
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, category_id),
  CONSTRAINT fk_profile_categories_user FOREIGN KEY (user_id) REFERENCES student_profiles(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_categories_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_profile_fields (
  user_id INT UNSIGNED NOT NULL,
  field_of_study_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, field_of_study_id),
  CONSTRAINT fk_profile_fields_user FOREIGN KEY (user_id) REFERENCES student_profiles(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_fields_field FOREIGN KEY (field_of_study_id) REFERENCES fields_of_study(field_of_study_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE student_profile_themes (
  user_id INT UNSIGNED NOT NULL,
  national_theme_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, national_theme_id),
  CONSTRAINT fk_profile_themes_user FOREIGN KEY (user_id) REFERENCES student_profiles(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_themes_theme FOREIGN KEY (national_theme_id) REFERENCES national_themes(national_theme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE saved_scholarships (
  user_id INT UNSIGNED NOT NULL,
  scholarship_id INT UNSIGNED NOT NULL,
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, scholarship_id),
  CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_saved_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  INDEX idx_saved_scholarship (scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_applications (
  application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  scholarship_id INT UNSIGNED NOT NULL,
  application_status ENUM('not_started','in_progress','submitted','awarded','declined') NOT NULL DEFAULT 'not_started',
  notes TEXT NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  acting_user_id INT UNSIGNED NULL,
  CONSTRAINT fk_applications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_applications_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  CONSTRAINT fk_applications_acting_user FOREIGN KEY (acting_user_id) REFERENCES users(user_id),
  UNIQUE KEY uq_application_user_scholarship (user_id, scholarship_id),
  INDEX idx_applications_status (application_status),
  INDEX idx_applications_scholarship (scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contact_messages (
  contact_message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','reviewed','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  CONSTRAINT fk_contact_reviewed_by FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id),
  INDEX idx_contact_status (status),
  INDEX idx_contact_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE import_batches (
  import_batch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_filename VARCHAR(255) NOT NULL,
  uploaded_by_user_id INT UNSIGNED NULL,
  acting_user_id INT UNSIGNED NULL,
  status ENUM('uploaded','validated','imported','failed') NOT NULL DEFAULT 'uploaded',
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
  error_rows INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  CONSTRAINT fk_import_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_import_acting_user FOREIGN KEY (acting_user_id) REFERENCES users(user_id),
  INDEX idx_import_status (status),
  INDEX idx_import_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE import_rows (
  import_row_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_batch_id INT UNSIGNED NOT NULL,
  row_number INT UNSIGNED NOT NULL,
  raw_data JSON NOT NULL,
  validation_status ENUM('pending','valid','invalid','imported') NOT NULL DEFAULT 'pending',
  validation_message TEXT NULL,
  scholarship_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_import_rows_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches(import_batch_id) ON DELETE CASCADE,
  CONSTRAINT fk_import_rows_scholarship FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id),
  UNIQUE KEY uq_import_row_batch_number (import_batch_id, row_number),
  INDEX idx_import_rows_status (validation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  audit_log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_name VARCHAR(100) NOT NULL,
  record_id INT UNSIGNED NOT NULL,
  action VARCHAR(50) NOT NULL,
  acting_user_id INT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_acting_user FOREIGN KEY (acting_user_id) REFERENCES users(user_id),
  INDEX idx_audit_table_record (table_name, record_id),
  INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
