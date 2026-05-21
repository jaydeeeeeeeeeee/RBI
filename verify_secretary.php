<?php
/**
 * ProjectRBI Secretary Verification
 * Password verify with brute-force protection
 * Max attempts: 5 | Lockout: 10 minutes
 */
session_start();
include 'Admin_DB.php';
header('Content-Type: application/json');

$ip = $_SERVER['REMOTE_ADDR'];
$password = $_POST['password'] ?? null;

// Auto-create security tables if needed
$conn->query("CREATE TABLE IF NOT EXISTS failed_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    locked_until DATETIME DEFAULT NULL,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ip (ip_address)
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    detail TEXT,
    performed_by VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Remove expired lockouts — also catches any corrupted locked_until values
$conn->query("DELETE FROM failed_attempts WHERE
    (locked_until IS NOT NULL AND locked_until < NOW())
    OR last_attempt < NOW() - INTERVAL 10 MINUTE");

// Check if IP is locked
$ip_esc = $conn->real_escape_string($ip);
$lock_r = $conn->query("SELECT attempts, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS secs_left FROM failed_attempts WHERE ip_address='$ip_esc' LIMIT 1");
if ($lock_r && $lock_r->num_rows > 0) {
    $lrow = $lock_r->fetch_assoc();
    if ((int)$lrow['secs_left'] > 0) {
        $mins = ceil((int)$lrow['secs_left'] / 60);
        echo json_encode(['ok'=>false,'reason'=>'locked',
            'message'=>"Too many failed attempts. Try again in {$mins} minute(s)."]);
        exit;
    }
}

if (!$password || !isset($_SESSION['admin'])) {
    echo json_encode(['ok'=>false,'reason'=>'unauthorized','message'=>'Not authorized.']);
    exit;
}

// Verify password
$username = $_SESSION['admin'];
$stmt = $conn->prepare("SELECT password FROM admins WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($hash);
$stmt->fetch();
$stmt->close();

if (!$hash || !password_verify($password, $hash)) {
    // Record failed attempt - 5 attempts, 10 min lockout
    $conn->query("INSERT INTO failed_attempts (ip_address, attempts, last_attempt)
        VALUES ('$ip_esc', 1, NOW())
        ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            last_attempt = NOW(),
            locked_until = IF(attempts + 1 >= 5, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NULL)");
    
    $att_r = $conn->query("SELECT attempts FROM failed_attempts WHERE ip_address='$ip_esc'");
    $att = $att_r ? (int)$att_r->fetch_assoc()['attempts'] : 1;
    $left = max(0, 5 - $att);
    
    echo json_encode(['ok'=>false,'reason'=>'wrong_password',
        'message'=>"Incorrect password. {$left} attempt(s) remaining."]);
    exit;
}

// ✅ Success — clear failed attempts, set session
$conn->query("DELETE FROM failed_attempts WHERE ip_address='$ip_esc'");
$_SESSION['list_unlocked']    = true;
$_SESSION['list_unlock_time'] = time();
$_SESSION['list_unlock_ip']   = $ip;

$adm = $conn->real_escape_string($username);
$conn->query("INSERT INTO access_log (event_type,detail,performed_by,ip_address)
    VALUES ('UNLOCK','Secretary verified password','$adm','$ip_esc')");

echo json_encode(['ok'=>true,'message'=>'Access granted.']);