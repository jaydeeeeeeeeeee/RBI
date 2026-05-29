<?php
/**
 * ProjectRBI Resident ID System
 * Format: 04-MMYY-1NNN-00  (12 digits, displayed with dashes)
 * 04   = fixed barangay prefix (Barangay 410)
 * MMYY = 2-digit month + 2-digit year of registration (e.g. 0526 = May 2026)
 * 1NNN = fixed "1" + 3-digit sequential counter (001–999)
 * 00   = fixed suffix
 * Example: 04-0526-1001-00 = first registrant in May 2026
 */

function residentCodeColumnExists($conn) {
    $r = mysqli_query($conn, "SHOW COLUMNS FROM residents LIKE 'resident_code'");
    return $r && mysqli_num_rows($r) > 0;
}

function ensureResidentCodeColumn($conn) {
    if (!residentCodeColumnExists($conn)) {
        mysqli_query($conn, "ALTER TABLE residents ADD COLUMN resident_code VARCHAR(25) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE residents ADD COLUMN IF NOT EXISTS family_code VARCHAR(25) DEFAULT NULL");
    }
}

function generateResidentCode($conn) {
    ensureResidentCodeColumn($conn);
    $mmyy   = date('my');        // e.g. 0526 for May 2026
    $prefix = '04-' . $mmyy . '-1';
    $esc    = mysqli_real_escape_string($conn, $prefix);
    $r      = mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE resident_code LIKE '{$esc}%'");
    $seq    = $r ? (int)mysqli_fetch_assoc($r)['c'] + 1 : 1;
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT) . '-00';
    // Result: 04-0526-1001-00
}

function isValidResidentCode($code) {
    // Current format: 04-MMYY-1NNN-00  (e.g. 04-0526-1001-00)
    if (preg_match('/^04-\d{4}-1\d{3}-00$/', $code)) return true;
    // Previous format: 04MMDD-10YYYY-01NNN
    if (preg_match('/^04\d{4}-10\d{4}-01\d{3}$/', $code)) return true;
    // Older format: YY-MMDD-NNN
    if (preg_match('/^\d{2}-\d{4}-\d{3}$/', $code)) return true;
    // Legacy: 14-digit
    if (preg_match('/^\d{14}$/', $code)) return true;
    return false;
}

function isForeignCode($code) {
    return !empty($code) && !isValidResidentCode($code);
}

function logSecurityEvent($conn, $event, $detail, $ip) {
    $t = mysqli_query($conn, "SHOW TABLES LIKE 'access_log'");
    if (!$t || mysqli_num_rows($t) === 0) {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS access_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            detail TEXT,
            performed_by VARCHAR(100),
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    }
    $evt = mysqli_real_escape_string($conn, $event);
    $det = mysqli_real_escape_string($conn, $detail);
    $ip2 = mysqli_real_escape_string($conn, $ip);
    $adm = mysqli_real_escape_string($conn, $_SESSION['admin'] ?? 'unknown');
    mysqli_query($conn, "INSERT INTO access_log (event_type,detail,performed_by,ip_address)
        VALUES ('$evt','$det','$adm','$ip2')");
}
