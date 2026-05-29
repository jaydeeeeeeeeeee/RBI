<?php
/**
 * Central signatory helper — used by RBI, eBlotter exports, and any
 * module that prints official documents.
 *
 * Reads from `barangay_officials` table. Falls back to defaults if empty.
 */

function ensureSignatoryTable(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS barangay_officials (
        id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        role_key   VARCHAR(60)  NOT NULL UNIQUE,
        role_label VARCHAR(120) NOT NULL,
        full_name  VARCHAR(200) NOT NULL DEFAULT '',
        title      VARCHAR(200) NOT NULL DEFAULT '',
        sort_order INT          NOT NULL DEFAULT 0,
        updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default rows
    $conn->query("INSERT IGNORE INTO barangay_officials (role_key, role_label, full_name, title, sort_order) VALUES
        ('captain',   'Punong Barangay',   '', 'Punong Barangay',   1),
        ('secretary', 'Barangay Secretary','', 'Barangay Secretary', 2)
    ");
    // Remove treasurer if it was previously seeded
    $conn->query("DELETE FROM barangay_officials WHERE role_key='treasurer'");
}

function getSignatories(mysqli $conn): array {
    ensureSignatoryTable($conn);
    $res = $conn->query("SELECT * FROM barangay_officials ORDER BY sort_order, id");
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['role_key']] = $r;
    return $out;
}

function getSignatory(mysqli $conn, string $role_key): array {
    $sigs = getSignatories($conn);
    return $sigs[$role_key] ?? ['full_name' => '', 'title' => '', 'role_label' => $role_key];
}

/**
 * Sync the Punong Barangay name into eb_signer_settings so eBlotter
 * documents stay consistent without a separate setting.
 */
function syncEBlotterSigner(mysqli $conn, string $name): void {
    $n = $conn->real_escape_string($name);
    $conn->query("UPDATE eb_signer_settings SET signer_name='$n' WHERE id=1");
}
