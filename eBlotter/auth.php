<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

// Ph Timezone 
date_default_timezone_set('Asia/Manila');

function currentUser(): ?array { return $_SESSION['eb_user'] ?? null; }
function currentRole(): string { return $_SESSION['eb_user']['role'] ?? ''; }
function isChairperson(): bool  { return currentRole() === 'chairperson'; }
function isSecretary():  bool  { return currentRole() === 'secretary';  }
function isKagawad():    bool  { return currentRole() === 'kagawad';    }
function canEdit():      bool  { return in_array(currentRole(), ['chairperson','secretary']); }

function requireRole(array $allowed = ['chairperson','secretary','kagawad']): void {
    $u = currentUser();
    if (!$u) { header('Location: login.php'); exit(); }
    if (!in_array($u['role'], $allowed)) { header('Location: eblotter_home.php?denied=1'); exit(); }
}

function auditLog(mysqli $conn, string $action, ?string $case_id = null, ?string $details = null): void {
    $u = currentUser(); if (!$u) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO eb_audit_log (user_id,username,full_name,action,case_id,details,ip_address) VALUES (?,?,?,?,?,?,?)");
    if ($stmt) { $stmt->bind_param('issssss',$u['id'],$u['username'],$u['full_name'],$action,$case_id,$details,$ip); $stmt->execute(); }
}

function markExported(mysqli $conn, string $case_id, string $docType = 'PDF'): void {
    $u = currentUser(); if (!$u) return;
    $name = $u['full_name']; $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE blotter_cases SET last_exported_by=?,last_exported_at=? WHERE case_id=?");
    if ($stmt) { $stmt->bind_param('sss',$name,$now,$case_id); $stmt->execute(); }
}

// ── signer Name 

define('DEFAULT_SIGNER_NAME', 'BRENDA S. PUERTOLLANO');

function ensureSignerTable(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $conn->query("
        CREATE TABLE IF NOT EXISTS eb_signer_settings (
            id          INT          NOT NULL DEFAULT 1,
            signer_name VARCHAR(200) NOT NULL,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $done = true;
}

function getSignerName(mysqli $conn): string {
    ensureSignerTable($conn);
    $stmt = $conn->prepare("SELECT signer_name FROM eb_signer_settings WHERE id=1 LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty(trim($row['signer_name']))) {
            return strtoupper(trim($row['signer_name']));
        }
    }
    return DEFAULT_SIGNER_NAME;
}

function setSignerOverride(mysqli $conn, string $name, string $chapw = ''): bool {
    if (!isChairperson() && !isSecretary()) return false;
    if (isSecretary()) {
        if (!$chapw || !verifyChairpersonPassword($conn, $chapw)) return false;
    }

    $name = strtoupper(trim($name));
    if ($name === '') return false;

    ensureSignerTable($conn);
    $stmt = $conn->prepare("
        INSERT INTO eb_signer_settings (id, signer_name)
        VALUES (1, ?)
        ON DUPLICATE KEY UPDATE signer_name = VALUES(signer_name), updated_at = NOW()
    ");
    if (!$stmt) return false;
    $stmt->bind_param('s', $name);
    return $stmt->execute();
}

/**
 * Resets the signer name back to the system default
 */
function resetSignerToDefault(mysqli $conn): bool {
    if (!isChairperson()) return false;
    ensureSignerTable($conn);
    $default = DEFAULT_SIGNER_NAME;
    $stmt = $conn->prepare("
        INSERT INTO eb_signer_settings (id, signer_name)
        VALUES (1, ?)
        ON DUPLICATE KEY UPDATE signer_name = VALUES(signer_name), updated_at = NOW()
    ");
    if (!$stmt) return false;
    $stmt->bind_param('s', $default);
    return $stmt->execute();
}

function verifyChairpersonPassword(mysqli $conn, string $pw): bool {
    $stmt = $conn->prepare("SELECT password FROM eb_users WHERE role='chairperson' AND is_active=1 LIMIT 1");
    if (!$stmt) return false;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && password_verify($pw, $row['password']);
}

function verifyCurrentUserOrChairPassword(mysqli $conn, string $attempt): bool {
    if (isChairperson()) {
        $uid = currentUser()['id'];
        $s = $conn->prepare("SELECT password FROM eb_users WHERE id=? LIMIT 1");
        if (!$s) return false;
        $s->bind_param('i', $uid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        return $row && password_verify($attempt, $row['password']);
    }
    return verifyChairpersonPassword($conn, $attempt);
}

// ── CSRF Protection ──

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch. Please go back and try again.');
    }
}
// ── Shared saved-value helper (used by summons, notice, mediation pages) ──
if (!function_exists('sv_saved')) {
    function sv_saved(?array $saved, string $key, string $fallback = ''): string {
        return htmlspecialchars($saved[$key] ?? $fallback);
    }
}