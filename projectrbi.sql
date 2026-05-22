-- ================================================================
-- ProjectRBI v4 – Barangay 410, Manila City
-- Run this ONCE in phpMyAdmin SQL tab
-- Default login: admin / admin123  (role: captain)
-- ================================================================

CREATE DATABASE IF NOT EXISTS `projectrbi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `projectrbi`;

-- ── ADMINS ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('captain','secretary','guest') NOT NULL DEFAULT 'secretary',
  `full_name`  VARCHAR(150) DEFAULT NULL,
  `is_active`  TINYINT(1)   DEFAULT 1,
  `expires_at` DATETIME     DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account (password: admin123) — role is captain
INSERT INTO `admins` (`username`,`password`,`role`,`full_name`,`is_active`) VALUES
('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','captain','Administrator',1)
ON DUPLICATE KEY UPDATE username=username;

-- ── RESIDENTS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `residents` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `resident_code`       VARCHAR(20)  DEFAULT NULL,
  `family_code`         VARCHAR(20)  DEFAULT NULL,
  `first_name`          VARCHAR(100) NOT NULL,
  `middle_name`         VARCHAR(100) DEFAULT '',
  `last_name`           VARCHAR(100) NOT NULL,
  `suffix`              VARCHAR(20)  DEFAULT '',
  `head_of_family`      VARCHAR(10)  DEFAULT '',
  `relationship`        VARCHAR(100) DEFAULT '',
  `head_first_name`     VARCHAR(100) DEFAULT '',
  `head_middle_name`    VARCHAR(100) DEFAULT '',
  `head_last_name`      VARCHAR(100) DEFAULT '',
  `head_suffix`         VARCHAR(20)  DEFAULT '',
  `perm_address`        VARCHAR(255) DEFAULT '',
  `prov_address`        VARCHAR(255) DEFAULT '',
  `house_owner`         VARCHAR(10)  DEFAULT '',
  `house_details`       VARCHAR(255) DEFAULT '',
  `years_in_barangay`   INT          DEFAULT 0,
  `voter`               VARCHAR(10)  DEFAULT '',
  `precinct_no`         VARCHAR(50)  DEFAULT '',
  `mobile`              VARCHAR(20)  DEFAULT '',
  `landline`            VARCHAR(20)  DEFAULT '',
  `email`               VARCHAR(150) DEFAULT '',
  `birthdate`           DATE         DEFAULT NULL,
  `gender`              VARCHAR(30)  DEFAULT '',
  `marital_status`      VARCHAR(30)  DEFAULT '',
  `religion`            VARCHAR(100) DEFAULT '',
  `citizenship`         VARCHAR(100) DEFAULT '',
  `education`           VARCHAR(100) DEFAULT '',
  `employment_status`   VARCHAR(50)  DEFAULT '',
  `occupation`          VARCHAR(150) DEFAULT '',
  `employer`            VARCHAR(150) DEFAULT '',
  `work_hours`          VARCHAR(50)  DEFAULT '',
  `grade_level`         VARCHAR(50)  DEFAULT '',
  `school_name`         VARCHAR(150) DEFAULT '',
  `out_of_school_youth` VARCHAR(10)  DEFAULT '',
  `has_car`             VARCHAR(10)  DEFAULT '',
  `car_brand`           VARCHAR(100) DEFAULT '',
  `car_model`           VARCHAR(100) DEFAULT '',
  `car_color`           VARCHAR(50)  DEFAULT '',
  `car_plate`           VARCHAR(30)  DEFAULT '',
  `has_motorcycle`      VARCHAR(10)  DEFAULT '',
  `motor_brand`         VARCHAR(100) DEFAULT '',
  `motor_model`         VARCHAR(100) DEFAULT '',
  `motor_color`         VARCHAR(50)  DEFAULT '',
  `motor_plate`         VARCHAR(30)  DEFAULT '',
  `is_senior`           VARCHAR(10)  DEFAULT '',
  `osca_id`             VARCHAR(50)  DEFAULT '',
  `pwd_status`          VARCHAR(10)  DEFAULT 'No',
  `pwd_id`              VARCHAR(50)  DEFAULT '',
  `disability_type`     VARCHAR(100) DEFAULT '',
  `solo_parent_status`  VARCHAR(10)  DEFAULT 'No',
  `solo_parent_id`      VARCHAR(50)  DEFAULT '',
  `has_pets`            TINYINT(1)   DEFAULT 0,
  `is_hidden`           TINYINT(1)   DEFAULT 0,
  `created_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_resident_code (`resident_code`),
  INDEX idx_family_code   (`family_code`),
  INDEX idx_name          (`last_name`,`first_name`),
  INDEX idx_hidden        (`is_hidden`)
) ENGINE=InnoDB;

-- ── PETS ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pets` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `resident_id`    INT NOT NULL,
  `pet_name`       VARCHAR(100) DEFAULT '',
  `pet_age`        VARCHAR(20)  DEFAULT '',
  `pet_sex`        VARCHAR(20)  DEFAULT '',
  `pet_color`      VARCHAR(50)  DEFAULT '',
  `pet_type`       VARCHAR(50)  DEFAULT '',
  `breeder_status` VARCHAR(10)  DEFAULT '',
  `other_pets`     VARCHAR(255) DEFAULT '',
  FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── AUDIT LOG ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `action`        ENUM('CREATE','UPDATE','DELETE') NOT NULL,
  `table_name`    VARCHAR(100) DEFAULT 'residents',
  `record_id`     INT          NOT NULL,
  `resident_name` VARCHAR(255) DEFAULT NULL,
  `field_changed` VARCHAR(100) DEFAULT NULL,
  `old_value`     TEXT,
  `new_value`     TEXT,
  `performed_by`  VARCHAR(100) NOT NULL,
  `performed_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `ip_address`    VARCHAR(45)  DEFAULT NULL,
  `notes`         TEXT
) ENGINE=InnoDB;

-- ── DOCUMENT REQUESTS (Data Tracking) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_requests` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `request_code`   VARCHAR(30)  NOT NULL UNIQUE,
  `resident_id`    INT          DEFAULT NULL,
  `resident_name`  VARCHAR(255) NOT NULL,
  `document_type`  ENUM('Barangay Clearance','Certificate of Residency','Certificate of Indigency','Business Permit','Certificate of Good Moral','Other') NOT NULL,
  `other_document` VARCHAR(255) DEFAULT NULL,
  `purpose`        TEXT,
  `status`         ENUM('Pending','Processing','Ready','Released','Rejected') DEFAULT 'Pending',
  `requested_by`   VARCHAR(100) NOT NULL,
  `requested_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `released_at`    DATETIME     DEFAULT NULL,
  `released_by`    VARCHAR(100) DEFAULT NULL,
  `remarks`        TEXT
) ENGINE=InnoDB;

-- ── CERTIFICATE TEMPLATES ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `certificate_templates` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `template_name` VARCHAR(100) NOT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `certificate_templates` (`template_name`) VALUES
  ('Barangay Clearance'),
  ('Certificate of Residency'),
  ('Certificate of Indigency'),
  ('Certificate of Good Moral Character'),
  ('Business Permit');

-- ── CERTIFICATE REQUESTS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `certificate_requests` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `resident_id`  INT NOT NULL,
  `template_id`  INT NOT NULL,
  `purpose`      TEXT,
  `status`       ENUM('Pending','Approved','Rejected','Released') DEFAULT 'Pending',
  `requested_by` VARCHAR(150) DEFAULT NULL,
  `requested_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `approved_at`  DATETIME     DEFAULT NULL,
  `released_at`  DATETIME     DEFAULT NULL,
  INDEX (`resident_id`),
  INDEX (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SETTINGS (signatories, config) ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`       VARCHAR(100) PRIMARY KEY,
  `value`     TEXT,
  `updated_at` DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── ACCESS LOG (security events) ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `event_type`   VARCHAR(100) NOT NULL,
  `detail`       TEXT,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `role`         VARCHAR(50)  DEFAULT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── FAILED ATTEMPTS (brute force protection) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `failed_attempts` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempts`     INT          DEFAULT 1,
  `locked_until` DATETIME     DEFAULT NULL,
  `last_attempt` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_ip (`ip_address`)
) ENGINE=InnoDB;

-- ── SENIOR CITIZENS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `senior_citizens` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `last_name`      VARCHAR(100) NOT NULL,
  `first_name`     VARCHAR(100) NOT NULL,
  `middle_name`    VARCHAR(100) DEFAULT NULL,
  `gender`         ENUM('Male','Female') DEFAULT NULL,
  `birth_month`    TINYINT      NOT NULL,
  `birth_day`      TINYINT      NOT NULL,
  `birth_year`     SMALLINT     NOT NULL,
  `address`        VARCHAR(300) DEFAULT NULL,
  `contact_number` VARCHAR(30)  DEFAULT NULL,
  `status`         ENUM('Active','Deceased') DEFAULT 'Active',
  `added_by`       VARCHAR(150) DEFAULT NULL,
  `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EQUIPMENT ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `equipment` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `item_code`        VARCHAR(30)  NOT NULL UNIQUE,
  `item_name`        VARCHAR(200) NOT NULL,
  `category`         ENUM('Audio/Visual','Furniture','Cleaning','Sports','Medical','Office','Others') DEFAULT 'Others',
  `description`      TEXT,
  `quantity`         INT          DEFAULT 1,
  `available`        INT          DEFAULT 1,
  `condition_status` ENUM('Good','Fair','Needs Repair','Retired') DEFAULT 'Good',
  `added_by`         VARCHAR(100) DEFAULT NULL,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── EQUIPMENT BORROWING ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `equipment_borrowing` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `borrow_code`       VARCHAR(30)  NOT NULL UNIQUE,
  `equipment_id`      INT          NOT NULL,
  `senior_citizen_id` INT          DEFAULT NULL,
  `borrower_name`     VARCHAR(200) NOT NULL,
  `borrower_contact`  VARCHAR(50)  DEFAULT NULL,
  `purpose`           TEXT,
  `borrow_date`       DATE         NOT NULL,
  `return_date`       DATE         NOT NULL,
  `actual_return`     DATE         DEFAULT NULL,
  `status`            ENUM('Pending','Approved','Returned','Overdue','Rejected') DEFAULT 'Pending',
  `approved_by`       VARCHAR(100) DEFAULT NULL,
  `remarks`           TEXT,
  `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`)
) ENGINE=InnoDB;

-- ── E-BLOTTER: CASES ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blotter_cases` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `case_id`            VARCHAR(50)  NOT NULL UNIQUE,
  `complainant_first`  VARCHAR(100) NOT NULL,
  `complainant_middle` VARCHAR(100) DEFAULT NULL,
  `complainant_last`   VARCHAR(100) NOT NULL,
  `complainant_age`    INT          DEFAULT NULL,
  `complainant_address` VARCHAR(255) DEFAULT NULL,
  `respondent_first`   VARCHAR(100) NOT NULL,
  `respondent_middle`  VARCHAR(100) DEFAULT NULL,
  `respondent_last`    VARCHAR(100) NOT NULL,
  `respondent_address` VARCHAR(255) DEFAULT NULL,
  `when_incident`      DATE         DEFAULT NULL,
  `where_incident`     VARCHAR(255) DEFAULT NULL,
  `brief_case`         TEXT         DEFAULT NULL,
  `disposition`        VARCHAR(50)  DEFAULT NULL,
  `status`             VARCHAR(50)  DEFAULT 'Pending',
  `created_by`         VARCHAR(150) DEFAULT NULL,
  `summons_done`       TINYINT(1)   DEFAULT 0,
  `notice_done`        TINYINT(1)   DEFAULT 0,
  `mediation_done`     TINYINT(1)   DEFAULT 0,
  `hearing_date`       DATE         DEFAULT NULL,
  `mediation_outcome`  VARCHAR(100) DEFAULT NULL,
  `mediation_reason`   TEXT         DEFAULT NULL,
  `last_updated_by`    VARCHAR(150) DEFAULT NULL,
  `last_updated_at`    DATETIME     DEFAULT NULL,
  `last_exported_by`   VARCHAR(150) DEFAULT NULL,
  `last_exported_at`   DATETIME     DEFAULT NULL,
  `created_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── E-BLOTTER: AUDIT LOG ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eb_audit_log` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT          DEFAULT NULL,
  `username`   VARCHAR(100) DEFAULT NULL,
  `full_name`  VARCHAR(150) DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `case_id`    VARCHAR(50)  DEFAULT NULL,
  `details`    TEXT         DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── E-BLOTTER: CASE SUMMONS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `case_summons` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `case_id`       VARCHAR(50)  NOT NULL UNIQUE,
  `to_name`       VARCHAR(255) DEFAULT NULL,
  `hearing_day`   VARCHAR(20)  DEFAULT NULL,
  `hearing_mo`    VARCHAR(30)  DEFAULT NULL,
  `hearing_yr`    VARCHAR(10)  DEFAULT NULL,
  `hearing_time`  VARCHAR(20)  DEFAULT NULL,
  `this_day`      VARCHAR(20)  DEFAULT NULL,
  `this_mo`       VARCHAR(30)  DEFAULT NULL,
  `this_yr`       VARCHAR(10)  DEFAULT NULL,
  `or_respondent` VARCHAR(255) DEFAULT NULL,
  `or_day`        VARCHAR(20)  DEFAULT NULL,
  `or_mo`         VARCHAR(30)  DEFAULT NULL,
  `or_yr`         VARCHAR(10)  DEFAULT NULL,
  `or_opt1`       VARCHAR(20)  DEFAULT NULL,
  `or_opt2`       VARCHAR(20)  DEFAULT NULL,
  `or_opt3`       VARCHAR(20)  DEFAULT NULL,
  `or_name3`      VARCHAR(255) DEFAULT NULL,
  `or_opt4`       VARCHAR(20)  DEFAULT NULL,
  `or_name4`      VARCHAR(255) DEFAULT NULL,
  `saved_by`      VARCHAR(150) DEFAULT NULL,
  `saved_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── E-BLOTTER: CASE NOTICE ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `case_notice` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `case_id`    VARCHAR(50)  NOT NULL UNIQUE,
  `hear_day`   VARCHAR(20)  DEFAULT NULL,
  `hear_mo`    VARCHAR(30)  DEFAULT NULL,
  `hear_yr`    VARCHAR(10)  DEFAULT NULL,
  `hear_time`  VARCHAR(20)  DEFAULT NULL,
  `notif_day`  VARCHAR(20)  DEFAULT NULL,
  `notif_mo`   VARCHAR(30)  DEFAULT NULL,
  `notif_yr`   VARCHAR(10)  DEFAULT NULL,
  `saved_by`   VARCHAR(150) DEFAULT NULL,
  `saved_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── E-BLOTTER: CASE MEDIATION ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `case_mediation` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `case_id`        VARCHAR(50)  NOT NULL UNIQUE,
  `agreement_text` TEXT         DEFAULT NULL,
  `saved_by`       VARCHAR(150) DEFAULT NULL,
  `saved_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── E-BLOTTER: CASE SEQUENCE ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eb_case_sequence` (
  `seq_year` CHAR(4)      NOT NULL,
  `seq_next` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── E-BLOTTER: SIGNER SETTINGS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eb_signer_settings` (
  `id`          INT          NOT NULL DEFAULT 1,
  `signer_name` VARCHAR(200) NOT NULL DEFAULT 'PUNONG BARANGAY',
  `updated_by`  VARCHAR(120) DEFAULT NULL,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `eb_signer_settings` (`id`,`signer_name`) VALUES (1,'PUNONG BARANGAY');

-- ── PASSWORD RESET REQUESTS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(100) NOT NULL,
  `requested_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `status`       ENUM('pending','resolved') DEFAULT 'pending'
) ENGINE=InnoDB;

SELECT 'ProjectRBI v4 database ready! Login: admin / admin123' AS status;
