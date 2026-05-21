-- ================================================================
-- ProjectRBI v3 UPGRADE SCRIPT
-- Run this if you already have a projectrbi database from before
-- This SAFELY adds new columns and tables without losing data
-- ================================================================

USE `projectrbi`;

-- Add resident_code column (safe - won't fail if already exists)
ALTER TABLE `residents`
  ADD COLUMN IF NOT EXISTS `resident_code` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `family_code`   VARCHAR(20) DEFAULT NULL;

-- Add index if not exists
ALTER IGNORE TABLE `residents` ADD INDEX idx_resident_code (`resident_code`);

-- Generate codes for existing residents that don't have one yet
SET @seq = 0;
UPDATE residents
SET resident_code = CONCAT(
    LPAD(MONTH(created_at), 2, '0'),
    LPAD(DAY(created_at),   2, '0'),
    YEAR(created_at),
    LPAD((@seq := @seq + 1), 4, '0')
)
WHERE (resident_code IS NULL OR resident_code = '');

-- Create access_log table
CREATE TABLE IF NOT EXISTS `access_log` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `event_type`   VARCHAR(100) NOT NULL,
  `detail`       TEXT,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create failed_attempts table (brute force protection)
CREATE TABLE IF NOT EXISTS `failed_attempts` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempts`     INT          DEFAULT 1,
  `locked_until` DATETIME     DEFAULT NULL,
  `last_attempt` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_ip (`ip_address`)
) ENGINE=InnoDB;

-- Create audit_log if not exists
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `action`        ENUM('CREATE','UPDATE','DELETE') NOT NULL,
  `table_name`    VARCHAR(100) DEFAULT 'residents',
  `record_id`     INT NOT NULL,
  `resident_name` VARCHAR(255) DEFAULT NULL,
  `field_changed` VARCHAR(100) DEFAULT NULL,
  `old_value`     TEXT,
  `new_value`     TEXT,
  `performed_by`  VARCHAR(100) NOT NULL,
  `performed_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_address`    VARCHAR(45) DEFAULT NULL,
  `notes`         TEXT
) ENGINE=InnoDB;

-- Create document_requests if not exists
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
  `requested_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `released_at`    DATETIME DEFAULT NULL,
  `released_by`    VARCHAR(100) DEFAULT NULL,
  `remarks`        TEXT
) ENGINE=InnoDB;

SELECT CONCAT('Done! ', COUNT(*), ' existing residents now have resident codes.') AS result
FROM residents WHERE resident_code IS NOT NULL AND resident_code != '';
