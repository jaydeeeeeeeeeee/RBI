<?php
$_config = dirname(__DIR__) . '/config.php';
if (file_exists($_config)) { require_once $_config; }

$host     = defined('DB_HOST') ? DB_HOST : 'localhost';
$port     = defined('DB_PORT') ? DB_PORT : 3306;
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';
$database = defined('DB_NAME') ? DB_NAME : 'projectrbi';

$conn = new mysqli($host, $username, $password, $database, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ── Core cases table ──────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS blotter_cases (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    case_id             VARCHAR(50)  NOT NULL UNIQUE,
    complainant_first   VARCHAR(100) NOT NULL,
    complainant_middle  VARCHAR(100) DEFAULT NULL,
    complainant_last    VARCHAR(100) NOT NULL,
    complainant_age     INT          DEFAULT NULL,
    complainant_address VARCHAR(255) DEFAULT NULL,
    respondent_first    VARCHAR(100) NOT NULL,
    respondent_middle   VARCHAR(100) DEFAULT NULL,
    respondent_last     VARCHAR(100) NOT NULL,
    respondent_address  VARCHAR(255) DEFAULT NULL,
    when_incident       DATE         DEFAULT NULL,
    where_incident      VARCHAR(255) DEFAULT NULL,
    brief_case          TEXT         DEFAULT NULL,
    disposition         VARCHAR(50)  DEFAULT NULL,
    status              VARCHAR(50)  DEFAULT 'Pending',
    created_by          VARCHAR(150) DEFAULT NULL,
    summons_done        TINYINT(1)   DEFAULT 0,
    notice_done         TINYINT(1)   DEFAULT 0,
    mediation_done      TINYINT(1)   DEFAULT 0,
    hearing_date        DATE         DEFAULT NULL,
    mediation_outcome   VARCHAR(100) DEFAULT NULL,
    mediation_reason    TEXT         DEFAULT NULL,
    last_updated_by     VARCHAR(150) DEFAULT NULL,
    last_updated_at     DATETIME     DEFAULT NULL,
    last_exported_by    VARCHAR(150) DEFAULT NULL,
    last_exported_at    DATETIME     DEFAULT NULL,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add missing columns to existing blotter_cases tables (idempotent)
foreach ([
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS summons_done      TINYINT(1)   DEFAULT 0   AFTER status",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS notice_done        TINYINT(1)   DEFAULT 0   AFTER summons_done",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS mediation_done     TINYINT(1)   DEFAULT 0   AFTER notice_done",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS hearing_date       DATE         DEFAULT NULL AFTER mediation_done",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS mediation_outcome  VARCHAR(100) DEFAULT NULL AFTER hearing_date",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS mediation_reason   TEXT         DEFAULT NULL AFTER mediation_outcome",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS last_updated_by    VARCHAR(150) DEFAULT NULL AFTER mediation_reason",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS last_updated_at    DATETIME     DEFAULT NULL AFTER last_updated_by",
    "ALTER TABLE blotter_cases ADD COLUMN IF NOT EXISTS complainant_address VARCHAR(255) DEFAULT NULL AFTER complainant_age",
] as $sql) { $conn->query($sql); }

// Add missing columns to case_summons (idempotent)
foreach ([
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS to_name       VARCHAR(255) DEFAULT NULL AFTER case_id",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS hearing_day   VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS hearing_mo    VARCHAR(30)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS hearing_yr    VARCHAR(10)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS hearing_time  VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS this_day      VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS this_mo       VARCHAR(30)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS this_yr       VARCHAR(10)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_respondent VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_day        VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_mo         VARCHAR(30)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_yr         VARCHAR(10)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_opt1       VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_opt2       VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_opt3       VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_name3      VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_opt4       VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS or_name4      VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS saved_by      VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS saved_at      DATETIME     DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE case_summons ADD COLUMN IF NOT EXISTS updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // case_notice — add all columns that may be missing from old table
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS hear_day   VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS hear_mo    VARCHAR(30)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS hear_yr    VARCHAR(10)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS hear_time  VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS notif_day  VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS notif_mo   VARCHAR(30)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS notif_yr   VARCHAR(10)  DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS saved_by   VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS saved_at   DATETIME     DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE case_notice ADD COLUMN IF NOT EXISTS updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // case_mediation — add all columns that may be missing from old table
    "ALTER TABLE case_mediation ADD COLUMN IF NOT EXISTS agreement_text TEXT         DEFAULT NULL",
    "ALTER TABLE case_mediation ADD COLUMN IF NOT EXISTS saved_by       VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE case_mediation ADD COLUMN IF NOT EXISTS saved_at       DATETIME     DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE case_mediation ADD COLUMN IF NOT EXISTS updated_at     DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
] as $sql) { $conn->query($sql); }

// ── Audit log ─────────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS eb_audit_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          DEFAULT NULL,
    username   VARCHAR(100) DEFAULT NULL,
    full_name  VARCHAR(150) DEFAULT NULL,
    action     VARCHAR(100) NOT NULL,
    case_id    VARCHAR(50)  DEFAULT NULL,
    details    TEXT         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Case workflow document tables (full schemas) ──────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS case_summons (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    case_id       VARCHAR(50)  NOT NULL UNIQUE,
    to_name       VARCHAR(255) DEFAULT NULL,
    hearing_day   VARCHAR(20)  DEFAULT NULL,
    hearing_mo    VARCHAR(30)  DEFAULT NULL,
    hearing_yr    VARCHAR(10)  DEFAULT NULL,
    hearing_time  VARCHAR(20)  DEFAULT NULL,
    this_day      VARCHAR(20)  DEFAULT NULL,
    this_mo       VARCHAR(30)  DEFAULT NULL,
    this_yr       VARCHAR(10)  DEFAULT NULL,
    or_respondent VARCHAR(255) DEFAULT NULL,
    or_day        VARCHAR(20)  DEFAULT NULL,
    or_mo         VARCHAR(30)  DEFAULT NULL,
    or_yr         VARCHAR(10)  DEFAULT NULL,
    or_opt1       VARCHAR(20)  DEFAULT NULL,
    or_opt2       VARCHAR(20)  DEFAULT NULL,
    or_opt3       VARCHAR(20)  DEFAULT NULL,
    or_name3      VARCHAR(255) DEFAULT NULL,
    or_opt4       VARCHAR(20)  DEFAULT NULL,
    or_name4      VARCHAR(255) DEFAULT NULL,
    saved_by      VARCHAR(150) DEFAULT NULL,
    saved_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS case_notice (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    case_id    VARCHAR(50)  NOT NULL UNIQUE,
    hear_day   VARCHAR(20)  DEFAULT NULL,
    hear_mo    VARCHAR(30)  DEFAULT NULL,
    hear_yr    VARCHAR(10)  DEFAULT NULL,
    hear_time  VARCHAR(20)  DEFAULT NULL,
    notif_day  VARCHAR(20)  DEFAULT NULL,
    notif_mo   VARCHAR(30)  DEFAULT NULL,
    notif_yr   VARCHAR(10)  DEFAULT NULL,
    saved_by   VARCHAR(150) DEFAULT NULL,
    saved_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS case_mediation (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    case_id        VARCHAR(50)  NOT NULL UNIQUE,
    agreement_text TEXT         DEFAULT NULL,
    saved_by       VARCHAR(150) DEFAULT NULL,
    saved_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Case ID sequence (prevents race-condition duplicates) ─────────────────
$conn->query("CREATE TABLE IF NOT EXISTS eb_case_sequence (
    seq_year CHAR(4)          NOT NULL,
    seq_next INT UNSIGNED     NOT NULL DEFAULT 1,
    PRIMARY KEY (seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Signer settings ───────────────────────────────────────────────────────
$_default_signer = defined('BLOTTER_DEFAULT_SIGNER') ? BLOTTER_DEFAULT_SIGNER : 'BRENDA S. PUERTOLLANO';
$conn->query("CREATE TABLE IF NOT EXISTS eb_signer_settings (
    id          INT          NOT NULL DEFAULT 1,
    signer_name VARCHAR(200) NOT NULL DEFAULT '" . $conn->real_escape_string($_default_signer) . "',
    updated_by  VARCHAR(120) DEFAULT NULL,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default signer only if table is empty — never overwrite a saved value
$conn->query("INSERT IGNORE INTO eb_signer_settings (id, signer_name) VALUES (1, '" . $conn->real_escape_string($_default_signer) . "')");