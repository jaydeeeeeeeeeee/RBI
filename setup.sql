-- ============================================================
--  ProjectRBI – Barangay 410 Manila  |  Full Setup Script
--  Run this in phpMyAdmin → SQL tab, then click Go
-- ============================================================

-- 1. Create & select the database
CREATE DATABASE IF NOT EXISTS `projectrbi`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `projectrbi`;

-- ──────────────────────────────────────────────────────────
-- 2. Admin accounts
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100)  NOT NULL UNIQUE,
  `password`   VARCHAR(255)  NOT NULL,
  `full_name`  VARCHAR(150)  DEFAULT NULL,
  `role`       ENUM('captain','secretary','guest') NOT NULL DEFAULT 'secretary',
  `is_active`  TINYINT(1)    DEFAULT 1,
  `created_at` DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default accounts (all use password: admin123)
INSERT IGNORE INTO `admins` (`username`,`password`,`full_name`,`role`,`is_active`) VALUES
('admin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Administrator', 'captain', 1),
('secretary',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Default Secretary', 'secretary', 1),
('guest',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Guest Viewer', 'guest', 1);

-- ──────────────────────────────────────────────────────────
-- 3. Residents
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `residents` (
  `id`                   INT AUTO_INCREMENT PRIMARY KEY,
  `resident_code`        VARCHAR(20)   DEFAULT NULL,
  `first_name`           VARCHAR(100)  NOT NULL,
  `middle_name`          VARCHAR(100)  DEFAULT NULL,
  `last_name`            VARCHAR(100)  NOT NULL,
  `suffix`               VARCHAR(20)   DEFAULT NULL,
  `birthdate`            DATE          DEFAULT NULL,
  `gender`               VARCHAR(20)   DEFAULT NULL,
  `marital_status`       VARCHAR(30)   DEFAULT 'Single',
  `citizenship`          VARCHAR(50)   DEFAULT 'Filipino',
  `religion`             VARCHAR(100)  DEFAULT NULL,
  `perm_address`         VARCHAR(255)  DEFAULT NULL,
  `mobile`               VARCHAR(20)   DEFAULT NULL,
  `employment_status`    VARCHAR(50)   DEFAULT NULL,
  `occupation`           VARCHAR(100)  DEFAULT NULL,
  `educational_attainment` VARCHAR(100) DEFAULT NULL,
  `voter`                ENUM('Yes','No') DEFAULT 'No',
  `is_senior`            ENUM('Yes','No') DEFAULT 'No',
  `pwd_status`           ENUM('Yes','No') DEFAULT 'No',
  `solo_parent_status`   ENUM('Yes','No') DEFAULT 'No',
  `head_of_family`       ENUM('Yes','No') DEFAULT 'No',
  `head_first_name`      VARCHAR(100)  DEFAULT NULL,
  `head_last_name`       VARCHAR(100)  DEFAULT NULL,
  `house_owner`          VARCHAR(30)   DEFAULT NULL,
  `has_car`              VARCHAR(10)   DEFAULT 'No',
  `has_motorcycle`       VARCHAR(10)   DEFAULT 'No',
  `is_hidden`            TINYINT(1)    DEFAULT 0,
  `created_at`           DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 4. Pets
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pets` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `resident_id` INT          DEFAULT NULL,
  `pet_name`    VARCHAR(100) DEFAULT NULL,
  `pet_type`    VARCHAR(50)  DEFAULT NULL,
  `breed`       VARCHAR(100) DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 5. Audit log
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `event_type`   VARCHAR(50)  NOT NULL,
  `table_name`   VARCHAR(50)  DEFAULT NULL,
  `record_id`    INT          DEFAULT NULL,
  `detail`       TEXT,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 6. Access log (logins / security events)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `event_type`   VARCHAR(50)  DEFAULT 'LOGIN',
  `detail`       VARCHAR(255) DEFAULT NULL,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `role`         VARCHAR(30)  DEFAULT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 7. Failed login attempts (brute-force lockout)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `failed_attempts` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `identifier`   VARCHAR(150) NOT NULL,
  `attempts`     INT          DEFAULT 1,
  `locked_until` DATETIME     DEFAULT NULL,
  `last_attempt` DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 8. Document requests
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_requests` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `request_code`   VARCHAR(30)  NOT NULL UNIQUE,
  `resident_id`    INT          DEFAULT NULL,
  `resident_name`  VARCHAR(200) NOT NULL,
  `document_type`  VARCHAR(100) NOT NULL,
  `other_document` VARCHAR(200) DEFAULT NULL,
  `purpose`        TEXT,
  `status`         ENUM('Pending','Processing','Ready','Released','Cancelled') DEFAULT 'Pending',
  `requested_by`   VARCHAR(100) DEFAULT NULL,
  `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 9. E-Blotter
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blotter` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `case_no`           VARCHAR(30)  NOT NULL UNIQUE,
  `complainant`       VARCHAR(200) NOT NULL,
  `respondent`        VARCHAR(200) NOT NULL,
  `incident_type`     ENUM('Noise Complaint','Physical Altercation','Property Dispute','Theft','Threat','Domestic Dispute','Trespassing','Others') DEFAULT 'Others',
  `other_type`        VARCHAR(100) DEFAULT NULL,
  `incident_date`     DATE         NOT NULL,
  `incident_location` VARCHAR(255) DEFAULT NULL,
  `narrative`         TEXT,
  `status`            ENUM('Filed','Under Mediation','Settled','Escalated','Dismissed') DEFAULT 'Filed',
  `action_taken`      TEXT,
  `filed_by`          VARCHAR(100),
  `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 10. Equipment inventory
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `equipment` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `item_code`        VARCHAR(30)  NOT NULL UNIQUE,
  `item_name`        VARCHAR(200) NOT NULL,
  `category`         ENUM('Audio/Visual','Furniture','Cleaning','Sports','Medical','Office','Others') DEFAULT 'Others',
  `description`      TEXT,
  `quantity`         INT          DEFAULT 1,
  `available`        INT          DEFAULT 1,
  `condition_status` ENUM('Good','Fair','Needs Repair','Retired') DEFAULT 'Good',
  `added_by`         VARCHAR(100),
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
-- 11. Equipment borrowing
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `equipment_borrowing` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `borrow_code`      VARCHAR(30)  NOT NULL UNIQUE,
  `equipment_id`     INT          NOT NULL,
  `borrower_name`    VARCHAR(200) NOT NULL,
  `borrower_contact` VARCHAR(50)  DEFAULT NULL,
  `purpose`          TEXT,
  `borrow_date`      DATE         NOT NULL,
  `return_date`      DATE         NOT NULL,
  `actual_return`    DATE         DEFAULT NULL,
  `status`           ENUM('Pending','Approved','Returned','Overdue','Rejected') DEFAULT 'Pending',
  `approved_by`      VARCHAR(100),
  `remarks`          TEXT,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`)
) ENGINE=InnoDB;

-- ============================================================
--  Done! Default login credentials:
--    Captain   → username: admin      / password: admin123
--    Secretary → username: secretary  / password: admin123
--    Guest     → username: guest      / password: admin123
-- ============================================================
