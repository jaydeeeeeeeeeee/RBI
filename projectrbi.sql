-- ================================================================
-- ProjectRBI v3 – Barangay 410, Manila City
-- Run this ONCE in phpMyAdmin SQL tab
-- Default login: admin / admin123
-- ================================================================

CREATE DATABASE IF NOT EXISTS `projectrbi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `projectrbi`;

-- ADMINS
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO `admins` (`username`,`password`) VALUES
('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username=username;

-- RESIDENTS (with resident_code)
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

-- PETS
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

-- AUDIT LOG
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

-- DOCUMENT REQUESTS (Data Tracking)
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

-- ACCESS LOG (security events)
CREATE TABLE IF NOT EXISTS `access_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `event_type`   VARCHAR(100) NOT NULL,
  `detail`       TEXT,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- FAILED ATTEMPTS (brute force protection)
CREATE TABLE IF NOT EXISTS `failed_attempts` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempts`     INT          DEFAULT 1,
  `locked_until` DATETIME     DEFAULT NULL,
  `last_attempt` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_ip (`ip_address`)
) ENGINE=InnoDB;

SELECT 'ProjectRBI v3 database ready!' AS status;
