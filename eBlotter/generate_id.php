<?php
/**
 * ProjectRBI Resident ID System
 * Format: 04{MMDD}-10{YYYY}-01{NNN}
 * 04   = fixed district prefix (Barangay 410 → "04")
 * MMDD = month+day of registration  (e.g. 0504 for May 4)
 * 10   = fixed barangay suffix code (Barangay 410 → "10")
 * YYYY = 4-digit registration year  (e.g. 2026)
 * 01   = fixed batch prefix
 * NNN  = 3-digit daily counter 001–999
 * Example: 040504-102026-01001 = first registrant on May 4, 2026
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
    $mmdd = date('md');          // e.g. 0504
    $yyyy = date('Y');           // e.g. 2026
    // Segment 1: 04 + MMDD  →  040504
    // Segment 2: 10 + YYYY  →  102026
    // Segment 3: 01 + NNN   →  01001
    $seg1   = '04' . $mmdd;
    $seg2   = '10' . $yyyy;
    $prefix = $seg1 . '-' . $seg2 . '-01';   // e.g. 040504-102026-01
    $esc    = mysqli_real_escape_string($conn, $prefix);
    $r      = mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE resident_code LIKE '{$esc}%'");
    $seq    = $r ? (int)mysqli_fetch_assoc($r)['c'] + 1 : 1;
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    // Result: 040504-102026-01001
}

function isValidResidentCode($code) {
    // Current format: 04MMDD-10YYYY-01NNN  (e.g. 040504-102026-01001)
    if (preg_match('/^04\d{4}-10\d{4}-01\d{3}$/', $code)) return true;
    // Previous format: YY-MMDD-NNN  (e.g. 26-0504-001)
    if (preg_match('/^\d{2}-\d{4}-\d{3}$/', $code)) return true;
    // Legacy format: 14-digit MMDDYYYYSSSSSS
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
