<?php
$_config = __DIR__ . '/config.php';
if (file_exists($_config)) { require_once $_config; }

$host     = defined('DB_HOST') ? DB_HOST : "localhost";
$port     = defined('DB_PORT') ? DB_PORT : 3306;
$user     = defined('DB_USER') ? DB_USER : "root";
$password = defined('DB_PASS') ? DB_PASS : "";
$database = defined('DB_NAME') ? DB_NAME : "projectrbi";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($host, $user, $password, $database, $port);
// OOP alias — lets any file use $conn->query() or $conn->prepare() interchangeably
// mysqli_connect() returns a mysqli object, so both styles work on the same handle.

if (!$conn) {
    $db_errno = mysqli_connect_errno();
    $db_error = mysqli_connect_error();
    $is_refused = (strpos($db_error, 'refused') !== false || $db_errno === 2002 || $db_errno === 2003);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Database Error – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:#fff;border-radius:18px;box-shadow:0 4px 30px rgba(0,0,0,.08);max-width:640px;width:100%;overflow:hidden}
.card-top{background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:2rem;text-align:center}
.err-icon{width:64px;height:64px;background:rgba(244,63,94,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;border:2px solid rgba(244,63,94,.3)}
.card-title{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:6px}
.card-sub{font-size:13px;color:rgba(255,255,255,.5)}
.card-body{padding:1.75rem}
.step{display:flex;gap:12px;margin-bottom:1rem;padding:14px;background:#f8fafc;border-radius:10px;border-left:3px solid #3b82f6}
.step-num{width:26px;height:26px;border-radius:50%;background:#3b82f6;color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.step-text{font-size:13px;color:#374151;line-height:1.6}
.step-text strong{color:#0f172a;display:block;margin-bottom:2px}
.sql-box{background:#0f172a;border-radius:10px;padding:14px 16px;margin-top:1rem;font-family:'Courier New',monospace;font-size:12px;color:#7dd3fc;line-height:1.8;white-space:pre;overflow-x:auto}
.sql-comment{color:#475569}
.section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:1.25rem 0 .75rem;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
.err-detail{background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;padding:10px 14px;font-size:12px;color:#be123c;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px}
.back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#0f172a;color:#fff;border-radius:9px;text-decoration:none;font-size:13px;font-weight:600;margin-top:1.25rem}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="err-icon"><i class="fas fa-database" style="color:#f43f5e;font-size:24px"></i></div>
    <div class="card-title">Database Connection Error</div>
    <div class="card-sub">ProjectRBI · Barangay 410</div>
  </div>
  <div class="card-body">
    <div class="err-detail">
      <i class="fas fa-exclamation-circle"></i>
      <span>Error <?=$db_errno?>: <?=htmlspecialchars($db_error)?></span>
    </div>

    <?php if($is_refused): ?>
    <div class="section-label">How to fix</div>
    <div class="step">
      <div class="step-num">1</div>
      <div class="step-text"><strong>Start MySQL in XAMPP</strong>Open the XAMPP Control Panel and click <strong>Start</strong> next to <em>MySQL</em>. The status should turn green.</div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-text"><strong>Open phpMyAdmin</strong>Go to <a href="http://localhost/phpmyadmin" target="_blank" style="color:#3b82f6">localhost/phpmyadmin</a> and run the setup SQL below if the database doesn't exist yet.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-text"><strong>Reload this page</strong>Once MySQL is running, refresh the page and the system will connect automatically.</div>
    </div>
    <?php endif; ?>

    <div class="section-label">Setup SQL — run this in phpMyAdmin</div>
    <p style="font-size:12px;color:#64748b;margin-bottom:.75rem">Copy the SQL below, paste it in phpMyAdmin → SQL tab, then click <em>Go</em>.</p>
    <div class="sql-box"><span class="sql-comment">-- 1. Create the database</span>
CREATE DATABASE IF NOT EXISTS `projectrbi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `projectrbi`;

<span class="sql-comment">-- 2. Admin accounts table</span>
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) DEFAULT NULL,
  `role` ENUM('captain','secretary','guest') NOT NULL DEFAULT 'secretary',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 3. Default accounts (password: admin123)</span>
INSERT IGNORE INTO `admins` (`username`,`password`,`full_name`,`role`,`is_active`) VALUES
('admin',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator',      'captain',   1),
('secretary', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Default Secretary',  'secretary', 1),
('guest',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guest Viewer',       'guest',     1);

<span class="sql-comment">-- 4. Residents table</span>
CREATE TABLE IF NOT EXISTS `residents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `resident_code` VARCHAR(20) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(20) DEFAULT NULL,
  `birthdate` DATE DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `marital_status` VARCHAR(30) DEFAULT 'Single',
  `citizenship` VARCHAR(50) DEFAULT 'Filipino',
  `religion` VARCHAR(100) DEFAULT NULL,
  `perm_address` VARCHAR(255) DEFAULT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `employment_status` VARCHAR(50) DEFAULT NULL,
  `voter` ENUM('Yes','No') DEFAULT 'No',
  `is_senior` ENUM('Yes','No') DEFAULT 'No',
  `pwd_status` ENUM('Yes','No') DEFAULT 'No',
  `solo_parent_status` ENUM('Yes','No') DEFAULT 'No',
  `head_of_family` ENUM('Yes','No') DEFAULT 'No',
  `head_first_name` VARCHAR(100) DEFAULT NULL,
  `head_last_name` VARCHAR(100) DEFAULT NULL,
  `house_owner` VARCHAR(30) DEFAULT NULL,
  `has_car` VARCHAR(10) DEFAULT 'No',
  `has_motorcycle` VARCHAR(10) DEFAULT 'No',
  `educational_attainment` VARCHAR(100) DEFAULT NULL,
  `occupation` VARCHAR(100) DEFAULT NULL,
  `is_hidden` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 5. Pets table</span>
CREATE TABLE IF NOT EXISTS `pets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `resident_id` INT DEFAULT NULL,
  `pet_name` VARCHAR(100) DEFAULT NULL,
  `pet_type` VARCHAR(50) DEFAULT NULL,
  `breed` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 6. Audit log</span>
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(50) NOT NULL,
  `table_name` VARCHAR(50) DEFAULT NULL,
  `record_id` INT DEFAULT NULL,
  `detail` TEXT,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 7. Access log (logins)</span>
CREATE TABLE IF NOT EXISTS `access_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(50) DEFAULT 'LOGIN',
  `detail` VARCHAR(255) DEFAULT NULL,
  `performed_by` VARCHAR(100) DEFAULT NULL,
  `role` VARCHAR(30) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 8. Failed login attempts (lockout)</span>
CREATE TABLE IF NOT EXISTS `failed_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(150) NOT NULL,
  `attempts` INT DEFAULT 1,
  `locked_until` DATETIME DEFAULT NULL,
  `last_attempt` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 9. Document requests</span>
CREATE TABLE IF NOT EXISTS `document_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_code` VARCHAR(30) NOT NULL UNIQUE,
  `resident_id` INT DEFAULT NULL,
  `resident_name` VARCHAR(200) NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `other_document` VARCHAR(200) DEFAULT NULL,
  `purpose` TEXT,
  `status` ENUM('Pending','Processing','Ready','Released','Cancelled') DEFAULT 'Pending',
  `requested_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 10. Blotter (E-Blotter module)</span>
CREATE TABLE IF NOT EXISTS `blotter` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_no` VARCHAR(30) NOT NULL UNIQUE,
  `complainant` VARCHAR(200) NOT NULL,
  `respondent` VARCHAR(200) NOT NULL,
  `incident_type` ENUM('Noise Complaint','Physical Altercation','Property Dispute','Theft','Threat','Domestic Dispute','Trespassing','Others') DEFAULT 'Others',
  `other_type` VARCHAR(100) DEFAULT NULL,
  `incident_date` DATE NOT NULL,
  `incident_location` VARCHAR(255) DEFAULT NULL,
  `narrative` TEXT,
  `status` ENUM('Filed','Under Mediation','Settled','Escalated','Dismissed') DEFAULT 'Filed',
  `action_taken` TEXT,
  `filed_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 11. Equipment inventory</span>
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(30) NOT NULL UNIQUE,
  `item_name` VARCHAR(200) NOT NULL,
  `category` ENUM('Audio/Visual','Furniture','Cleaning','Sports','Medical','Office','Others') DEFAULT 'Others',
  `description` TEXT,
  `quantity` INT DEFAULT 1,
  `available` INT DEFAULT 1,
  `condition_status` ENUM('Good','Fair','Needs Repair','Retired') DEFAULT 'Good',
  `added_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

<span class="sql-comment">-- 12. Equipment borrowing</span>
CREATE TABLE IF NOT EXISTS `equipment_borrowing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `borrow_code` VARCHAR(30) NOT NULL UNIQUE,
  `equipment_id` INT NOT NULL,
  `borrower_name` VARCHAR(200) NOT NULL,
  `borrower_contact` VARCHAR(50),
  `purpose` TEXT,
  `borrow_date` DATE NOT NULL,
  `return_date` DATE NOT NULL,
  `actual_return` DATE DEFAULT NULL,
  `status` ENUM('Pending','Approved','Returned','Overdue','Rejected') DEFAULT 'Pending',
  `approved_by` VARCHAR(100),
  `remarks` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`)
) ENGINE=InnoDB;</div>

    <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
  </div>
</div>
</body>
</html>
    <?php
    exit();
}
?>