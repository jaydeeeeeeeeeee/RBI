<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

date_default_timezone_set('Asia/Manila');

// Bridge ProjectRBI session → eBlotter session format
// ProjectRBI roles: captain → chairperson, secretary → secretary, guest → kagawad
if (isset($_SESSION['admin']) && !isset($_SESSION['eb_user'])) {
    $roleMap = [
        'captain'   => 'chairperson',
        'secretary' => 'secretary',
        'guest'     => 'kagawad',
    ];
    $rbi_role = $_SESSION['role'] ?? 'guest';
    $eb_role  = $roleMap[$rbi_role] ?? 'kagawad';
    $_SESSION['eb_user'] = [
        'id'        => $_SESSION['admin_id'] ?? 0,
        'username'  => $_SESSION['admin'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['admin'],
        'role'      => $eb_role,
    ];
}

function currentUser(): ?array { return $_SESSION['eb_user'] ?? null; }
function currentRole(): string { return $_SESSION['eb_user']['role'] ?? ''; }
function isChairperson(): bool  { return currentRole() === 'chairperson'; }
function isSecretary():  bool   { return currentRole() === 'secretary';  }
function isKagawad():    bool   { return currentRole() === 'kagawad';    }
function canEdit():      bool   { return in_array(currentRole(), ['chairperson','secretary']); }

function requireRole(array $allowed = ['chairperson','secretary','kagawad']): void {
    $u = currentUser();
    if (!$u) { header('Location: ../admin.php'); exit(); }
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

// db.php already loaded config.php, so BLOTTER_DEFAULT_SIGNER may be defined
define('DEFAULT_SIGNER_NAME', defined('BLOTTER_DEFAULT_SIGNER') ? BLOTTER_DEFAULT_SIGNER : 'BRENDA S. PUERTOLLANO');

function ensureSignerTable(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true; // table already created in db.php
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
    $stmt = $conn->prepare("INSERT INTO eb_signer_settings (id, signer_name) VALUES (1, ?) ON DUPLICATE KEY UPDATE signer_name = ?, updated_at = NOW()");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $name, $name);
    return $stmt->execute();
}

function resetSignerToDefault(mysqli $conn): bool {
    if (!isChairperson()) return false;
    ensureSignerTable($conn);
    $default = DEFAULT_SIGNER_NAME;
    $stmt = $conn->prepare("INSERT INTO eb_signer_settings (id, signer_name) VALUES (1, ?) ON DUPLICATE KEY UPDATE signer_name = ?, updated_at = NOW()");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $default, $default);
    return $stmt->execute();
}

// Verify the captain's password from the ProjectRBI admins table
function verifyChairpersonPassword(mysqli $conn, string $pw): bool {
    $stmt = $conn->prepare("SELECT password FROM admins WHERE role='captain' AND is_active=1 LIMIT 1");
    if (!$stmt) return false;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && password_verify($pw, $row['password']);
}

// Chairperson verifies own password; secretary/kagawad must enter captain's password
function verifyCurrentUserOrChairPassword(mysqli $conn, string $attempt): bool {
    if (isChairperson()) {
        $uid = currentUser()['id'];
        $s = $conn->prepare("SELECT password FROM admins WHERE id=? LIMIT 1");
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
