<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$_cfg = dirname(__DIR__) . '/config.php';
if (file_exists($_cfg)) require_once $_cfg;

$_host = defined('DB_HOST') ? DB_HOST : 'localhost';
$_port = defined('DB_PORT') ? DB_PORT : 3306;
$_user = defined('DB_USER') ? DB_USER : 'root';
$_pass = defined('DB_PASS') ? DB_PASS : '';
$_name = defined('DB_NAME') ? DB_NAME : 'projectrbi';

try {
    $pdo = new PDO(
        "mysql:host=$_host;port=$_port;dbname=$_name;charset=utf8mb4",
        $_user, $_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Certificate templates table
$pdo->exec("CREATE TABLE IF NOT EXISTS certificate_templates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Certificate requests table (resident_id links to existing residents table)
$pdo->exec("CREATE TABLE IF NOT EXISTS certificate_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    resident_id  INT NOT NULL,
    template_id  INT NOT NULL,
    purpose      TEXT,
    status       ENUM('Pending','Approved','Rejected','Released') DEFAULT 'Pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at  DATETIME DEFAULT NULL,
    released_at  DATETIME DEFAULT NULL,
    INDEX (resident_id),
    INDEX (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default certificate types if table is empty
$_cnt = (int)$pdo->query("SELECT COUNT(*) FROM certificate_templates")->fetchColumn();
if ($_cnt === 0) {
    $pdo->exec("INSERT INTO certificate_templates (template_name) VALUES
        ('Barangay Clearance'),
        ('Certificate of Residency'),
        ('Certificate of Indigency'),
        ('Certificate of Good Moral Character'),
        ('Business Permit')");
}
