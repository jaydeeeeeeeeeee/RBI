<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin.php"); exit(); }
include 'Residents_DB.php';
include 'role_helper.php';
include 'signatory_helper.php';
if ($is_guest) { header("Location: Home.php?denied=tracking"); exit(); }

$admin = $_SESSION['admin'];
$ip    = $_SERVER['REMOTE_ADDR'];

// ── Ensure certificate tables exist ──────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS certificate_templates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS certificate_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    resident_id  INT NOT NULL,
    template_id  INT NOT NULL,
    purpose      TEXT,
    status       ENUM('Pending','Approved','Rejected','Released') DEFAULT 'Pending',
    requested_by VARCHAR(150) DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at  DATETIME DEFAULT NULL,
    released_at  DATETIME DEFAULT NULL,
    INDEX (resident_id),
    INDEX (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add requested_by column if missing (idempotent)
$conn->query("ALTER TABLE certificate_requests ADD COLUMN IF NOT EXISTS requested_by VARCHAR(150) DEFAULT NULL");

// Seed default templates
$tmpl_cnt = (int)$conn->query("SELECT COUNT(*) AS c FROM certificate_templates")->fetch_assoc()['c'];
if ($tmpl_cnt === 0) {
    $conn->query("INSERT INTO certificate_templates (template_name) VALUES
        ('Barangay Clearance'),('Certificate of Residency'),
        ('Certificate of Indigency'),('Certificate of Good Moral Character'),
        ('Business Permit')");
}

// ── Certificate settings ──────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    `key`      VARCHAR(100) PRIMARY KEY,
    `value`    TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
function get_setting($conn, $key, $default = '') {
    $r = $conn->query("SELECT `value` FROM settings WHERE `key`='" . $conn->real_escape_string($key) . "'")->fetch_assoc();
    return $r ? $r['value'] : $default;
}
// Pull signatories from central table
$_dt_sigs  = getSignatories($conn);
$sig_name  = $_dt_sigs['captain']['full_name']   ?? '';
$sig_title = $_dt_sigs['captain']['title']        ?? 'Punong Barangay';
$sec_name_val = $_dt_sigs['secretary']['full_name'] ?? '';

// ── AJAX: resident search ─────────────────────────────────────────────────────
if (isset($_GET['ajax_search'])) {
    $q   = $conn->real_escape_string(trim($_GET['q'] ?? ''));
    $res = $conn->query("SELECT id,first_name,middle_name,last_name,suffix,birthdate,gender,marital_status,perm_address FROM residents WHERE is_hidden=0 AND (first_name LIKE '%$q%' OR last_name LIKE '%$q%' OR CONCAT(first_name,' ',last_name) LIKE '%$q%') ORDER BY last_name LIMIT 15");
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function sanitize($conn, $val) { return $conn->real_escape_string(trim($val)); }
function generate_request_code($conn) {
    $prefix = 'REQ-' . date('Ymd') . '-';
    $row    = $conn->query("SELECT COUNT(*) AS c FROM document_requests WHERE request_code LIKE '$prefix%'")->fetch_assoc();
    return $prefix . str_pad($row['c'] + 1, 4, '0', STR_PAD_LEFT);
}

$msg = ''; $msg_type = '';

// ── Certificate request: GET approve/reject/release ───────────────────────────
if (isset($_GET['cert_approve'])) {
    $id = (int)$_GET['cert_approve'];
    $conn->query("UPDATE certificate_requests SET status='Approved', approved_at=NOW() WHERE id=$id");
    header('Location: data_tracking.php?tab=cert_requests&ok=approved'); exit();
}
if (isset($_GET['cert_reject'])) {
    $id = (int)$_GET['cert_reject'];
    $conn->query("UPDATE certificate_requests SET status='Rejected' WHERE id=$id");
    header('Location: data_tracking.php?tab=cert_requests&ok=rejected'); exit();
}
if (isset($_GET['cert_release'])) {
    $id = (int)$_GET['cert_release'];
    $conn->query("UPDATE certificate_requests SET status='Released', released_at=NOW() WHERE id=$id");
    header('Location: data_tracking.php?tab=cert_requests&ok=released'); exit();
}

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    // Add certificate request
    if ($_POST['action'] === 'add_cert_request') {
        $rid  = (int)($_POST['resident_id'] ?? 0);
        $tid  = (int)($_POST['template_id'] ?? 0);
        $purp = sanitize($conn, $_POST['purpose'] ?? '');
        $by   = sanitize($conn, $admin);
        if ($rid && $tid) {
            $conn->query("INSERT INTO certificate_requests (resident_id,template_id,purpose,requested_by) VALUES ($rid,$tid,'$purp','$by')");
            $msg = 'Certificate request submitted.'; $msg_type = 'success';
        } else {
            $msg = 'Please select a resident and certificate type.'; $msg_type = 'error';
        }
    }

    // Delete certificate request
    if ($_POST['action'] === 'delete_cert_request') {
        $id = (int)$_POST['cert_id'];
        $conn->query("DELETE FROM certificate_requests WHERE id=$id");
        $msg = 'Request deleted.'; $msg_type = 'success';
    }

    // Add document request
    if ($_POST['action'] === 'add_request') {
        $code     = generate_request_code($conn);
        $res_name = sanitize($conn, $_POST['resident_name']);
        $res_id   = !empty($_POST['resident_id']) ? (int)$_POST['resident_id'] : 'NULL';
        $doc_type = sanitize($conn, $_POST['document_type']);
        $other    = sanitize($conn, $_POST['other_document'] ?? '');
        $purpose  = sanitize($conn, $_POST['purpose'] ?? '');
        $req_by   = sanitize($conn, $admin);
        $sql = "INSERT INTO document_requests (request_code,resident_id,resident_name,document_type,other_document,purpose,requested_by)
                VALUES ('$code'," . ($res_id === 'NULL' ? 'NULL' : $res_id) . ",'$res_name','$doc_type','$other','$purpose','$req_by')";
        if ($conn->query($sql)) { $msg = "Request <strong>$code</strong> added."; $msg_type = 'success'; }
        else { $msg = 'Error: ' . $conn->error; $msg_type = 'error'; }
    }

    // Update document request status
    if ($_POST['action'] === 'update_status') {
        $id      = (int)$_POST['req_id'];
        $status  = sanitize($conn, $_POST['status']);
        $remarks = sanitize($conn, $_POST['remarks'] ?? '');
        $released = ($status === 'Released') ? ", released_at=NOW(), released_by='$admin'" : '';
        $conn->query("UPDATE document_requests SET status='$status',remarks='$remarks' $released WHERE id=$id");
        $msg = "Status updated to <strong>$status</strong>."; $msg_type = 'success';
    }

    // Delete document request
    if ($_POST['action'] === 'delete_request') {
        $id = (int)$_POST['req_id'];
        $conn->query("DELETE FROM document_requests WHERE id=$id");
        $msg = 'Request deleted.'; $msg_type = 'success';
    }

    // Add audit log entry
    if ($_POST['action'] === 'add_audit') {
        $action   = sanitize($conn, $_POST['audit_action']);
        $rec_id   = (int)$_POST['record_id'];
        $res_name = sanitize($conn, $_POST['resident_name']);
        $field    = sanitize($conn, $_POST['field_changed'] ?? '');
        $old_val  = sanitize($conn, $_POST['old_value'] ?? '');
        $new_val  = sanitize($conn, $_POST['new_value'] ?? '');
        $notes    = sanitize($conn, $_POST['notes'] ?? '');
        $conn->query("INSERT INTO audit_log (action,record_id,resident_name,field_changed,old_value,new_value,performed_by,ip_address,notes)
                      VALUES ('$action',$rec_id,'$res_name','$field','$old_val','$new_val','$admin','$ip','$notes')");
        $msg = 'Audit entry added.'; $msg_type = 'success';
    }

    // Delete audit entry
    if ($_POST['action'] === 'delete_audit') {
        $id = (int)$_POST['audit_id'];
        $conn->query("DELETE FROM audit_log WHERE id=$id");
        $msg = 'Audit entry deleted.'; $msg_type = 'success';
    }

}

// ── Fetch data ────────────────────────────────────────────────────────────────
$filter_tab    = $_GET['tab']          ?? 'cert_requests';
$search        = sanitize($conn, $_GET['search'] ?? '');
$filter_status = sanitize($conn, $_GET['status'] ?? '');
$filter_action = sanitize($conn, $_GET['audit_action'] ?? '');

// Certificate requests
$cert_where = "WHERE 1=1";
if ($search) $cert_where .= " AND (r.first_name LIKE '%$search%' OR r.last_name LIKE '%$search%' OR ct.template_name LIKE '%$search%')";
if ($filter_status && $filter_tab === 'cert_requests') $cert_where .= " AND cr.status='$filter_status'";
$cert_requests = $conn->query("SELECT cr.*, r.first_name, r.last_name, r.perm_address AS address, ct.template_name
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.id
    JOIN certificate_templates ct ON cr.template_id = ct.id
    $cert_where ORDER BY cr.requested_at DESC");

$cert_counts = [];
foreach (['Pending','Approved','Released','Rejected'] as $s) {
    $cert_counts[$s] = (int)$conn->query("SELECT COUNT(*) AS c FROM certificate_requests WHERE status='$s'")->fetch_assoc()['c'];
}

// Document requests
$req_where = "WHERE 1=1";
if ($search) $req_where .= " AND (resident_name LIKE '%$search%' OR request_code LIKE '%$search%')";
if ($filter_status && $filter_tab === 'doc_requests') $req_where .= " AND status='$filter_status'";
$doc_requests = $conn->query("SELECT * FROM document_requests $req_where ORDER BY requested_at DESC");

$doc_counts = [];
foreach (['Pending','Processing','Ready','Released','Rejected'] as $s) {
    $doc_counts[$s] = (int)$conn->query("SELECT COUNT(*) AS c FROM document_requests WHERE status='$s'")->fetch_assoc()['c'];
}

// Audit log
$audit_where = "WHERE 1=1";
if ($search) $audit_where .= " AND (resident_name LIKE '%$search%' OR performed_by LIKE '%$search%')";
if ($filter_action) $audit_where .= " AND action='$filter_action'";
$audits = $conn->query("SELECT * FROM audit_log $audit_where ORDER BY performed_at DESC LIMIT 200");

// Residents for datalist
$res_list = $conn->query("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM residents WHERE is_hidden=0 ORDER BY last_name");
$residents_arr = [];
while ($r = $res_list->fetch_assoc()) $residents_arr[] = $r;

// Certificate templates
$templates = $conn->query("SELECT id, template_name FROM certificate_templates ORDER BY template_name");
$templates_arr = [];
while ($t = $templates->fetch_assoc()) $templates_arr[] = $t;

// Prefill from URL (e.g. clicked "Request Cert." from Display_List)
$prefill_id   = (int)($_GET['prefill_id'] ?? 0);
$prefill_name = htmlspecialchars($_GET['prefill_name'] ?? '');

// OK messages from redirects
if (isset($_GET['ok'])) {
    $ok_msgs = ['approved' => 'Request approved.', 'rejected' => 'Request rejected.', 'released' => 'Request released.'];
    $msg = $ok_msgs[$_GET['ok']] ?? ''; $msg_type = 'success';
}

// Barangay info
$_dt_brgy = defined('BRGY_NAME')     ? htmlspecialchars(BRGY_NAME)     : 'Barangay 410';
$_dt_city = defined('BRGY_CITY')     ? htmlspecialchars(BRGY_CITY)     : 'City of Manila';
$_dt_dist = defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : 'IV';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Document Tracking – <?= $_dt_brgy ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
  <style>
    :root { --red:#ef4444;--red-lt:#fef2f2;--gold:#f59e0b;--gold-lt:#fffbeb; }

    .page-hero { background:linear-gradient(to right,rgba(15,23,42,.85),rgba(15,23,42,.55)),url('images/Barangay_officials_410.png') center center/cover no-repeat; padding:2.5rem 2rem; min-height:300px; display:flex; align-items:center; }
    .page-hero h1 { font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:#fff;margin:0 0 .25rem; }
    .page-hero p  { color:rgba(255,255,255,.6);font-size:.84rem;margin:0 0 1.25rem; }
    .hero-nav a   { display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none; }
    .hero-nav .ghost  { border:1.5px solid rgba(255,255,255,.3);color:#fff;background:rgba(255,255,255,.08); }
    .hero-nav .active { border:1.5px solid #3b82f6;color:#fff;background:#3b82f6; }

    main { padding:2rem; max-width:1350px; margin:0 auto; }

    /* ALERTS */
    .alert { padding:11px 16px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:10px; }
    .alert.success { background:var(--green-lt);color:#15803d;border:1px solid #bbf7d0; }
    .alert.error   { background:var(--red-lt);color:#b91c1c;border:1px solid #fecaca; }

    /* TABS */
    .tabs { display:flex;gap:4px;margin-bottom:1.25rem;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:4px;width:fit-content; }
    .tab-btn { padding:8px 22px;border-radius:7px;border:none;cursor:pointer;font-size:13px;font-weight:500;background:none;color:var(--muted);transition:all .2s;font-family:'Inter',sans-serif; }
    .tab-btn.active { background:var(--navy);color:#fff; }

    /* STAT CARDS */
    .stats-row { display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:1.5rem; }
    .stat-card { background:var(--card);border:1px solid var(--border);border-radius:12px;padding:.85rem;text-align:center; }
    .stat-card .num { font-size:22px;font-weight:700; }
    .stat-card .lbl { font-size:11px;color:var(--muted);margin-top:2px; }
    .stat-card.pending   .num { color:var(--gold); }
    .stat-card.approved  .num { color:#8b5cf6; }
    .stat-card.processing .num { color:var(--blue); }
    .stat-card.ready     .num { color:#8b5cf6; }
    .stat-card.released  .num { color:var(--green); }
    .stat-card.rejected  .num { color:var(--red); }

    /* TOOLBAR */
    .toolbar { display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:1rem; }
    .toolbar input,.toolbar select { border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;font-family:'Inter',sans-serif;color:var(--text);background:var(--card);outline:none; }
    .toolbar input:focus,.toolbar select:focus { border-color:var(--blue); }
    .toolbar input { min-width:200px; }
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 9px 18px; border-radius: 10px; border: none; cursor: pointer;
      font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;
      transition: all 0.18s ease; text-decoration: none; white-space: nowrap;
      box-shadow: 0 1px 3px rgba(0,0,0,0.10); letter-spacing: 0.01em;
      position: relative; overflow: hidden;
    }
    .btn:hover  { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.14); }
    .btn:active { transform: translateY(0);    box-shadow: 0 1px 4px rgba(0,0,0,0.12); }
    .btn-primary { background: linear-gradient(135deg,#3b82f6 0%,#2563eb 100%); color:#fff; }
    .btn-primary:hover { background: linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%); color:#fff; }
    .btn-success { background: linear-gradient(135deg,#10b981 0%,#059669 100%); color:#fff; }
    .btn-success:hover { background: linear-gradient(135deg,#059669 0%,#047857 100%); color:#fff; }
    .btn-danger  { background: linear-gradient(135deg,#ef4444 0%,#dc2626 100%); color:#fff; border:none; }
    .btn-danger:hover  { background: linear-gradient(135deg,#dc2626 0%,#b91c1c 100%); color:#fff; }
    .btn-warning { background: linear-gradient(135deg,#f59e0b 0%,#d97706 100%); color:#fff; border:none; }
    .btn-warning:hover { background: linear-gradient(135deg,#d97706 0%,#b45309 100%); color:#fff; }
    .btn-outline {
      background: var(--card); color: var(--text);
      border: 1.5px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .btn-outline:hover { background: var(--bg); border-color: var(--blue); color: var(--blue); }
    .btn-sm { padding: 6px 13px; font-size: 12px; border-radius: 8px; }
    .ml-auto { margin-left:auto; }

    /* TABLE */
    .table-card { background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden; }
    .table-wrap { overflow-x:auto; }
    table { width:100%;border-collapse:collapse;font-size:13px; }
    th { background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);font-weight:600;white-space:nowrap; }
    td { padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:#f8fafc; }
    .empty-row td { text-align:center;color:var(--muted);padding:2.5rem;font-size:13px; }

    /* BADGES */
    .badge { display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap; }
    .badge-pending    { background:var(--gold-lt);color:#92400e; }
    .badge-approved   { background:#f5f3ff;color:#6d28d9; }
    .badge-processing { background:var(--blue-lt);color:#1e40af; }
    .badge-ready      { background:#f5f3ff;color:#6d28d9; }
    .badge-released   { background:var(--green-lt);color:#15803d; }
    .badge-rejected   { background:var(--red-lt);color:#b91c1c; }
    .badge-create     { background:var(--green-lt);color:#15803d; }
    .badge-update     { background:var(--blue-lt);color:#1e40af; }
    .badge-delete     { background:var(--red-lt);color:#b91c1c; }

    /* MODAL */
    .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--card);border-radius:14px;padding:1.75rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto; }
    .modal h2 { font-size:16px;font-weight:600;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid var(--border); }
    .form-grid { display:grid;gap:14px; }
    .form-group label { display:block;font-size:12px;font-weight:500;color:var(--muted);margin-bottom:5px; }
    .form-control { width:100%;border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:'Inter',sans-serif;color:var(--text);background:var(--bg);outline:none;box-sizing:border-box; }
    .form-control:focus { border-color:var(--blue); }
    .modal-footer { display:flex;gap:10px;justify-content:flex-end;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border); }

    /* Resident search dropdown */
    .res-search-wrap { position:relative; }
    .res-dropdown { position:absolute;top:calc(100% + 3px);left:0;right:0;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:1100;max-height:200px;overflow-y:auto;display:none; }
    .res-dropdown.open { display:block; }
    .res-opt { padding:9px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9; }
    .res-opt:hover { background:#f0f9ff; }
    .res-opt .ro-name { font-weight:600;color:#0f172a; }
    .res-opt .ro-sub  { font-size:11px;color:#64748b;margin-top:1px; }

    /* Action button strip */
    .actions { display:flex;gap:5px;flex-wrap:wrap; }

    /* Print watermark */
    .print-digital-watermark { display:none !important; }
    @media print {
      header.topbar,.page-hero,.tabs,.toolbar,.btn,.modal-overlay,
      #settingsOverlay,#settingsDrawer { display:none !important; }
      body { background:#fff; }
      .table-card { border:none;box-shadow:none; }
      .print-digital-watermark { display:flex !important;position:fixed;left:0;top:0;bottom:0;width:16mm;align-items:center;justify-content:center;overflow:visible;pointer-events:none;z-index:9999; }
      .print-digital-watermark span { writing-mode:vertical-rl;transform:rotate(180deg);font-size:8mm;font-weight:bold;color:rgba(72,72,72,.12);font-family:'Times New Roman',serif;letter-spacing:.05em;white-space:nowrap; }
    }
    .topbar { position:sticky;top:0;z-index:200; }
  </style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover" alt="">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div>
  </a>
  <div class="topbar-right" style="margin-left:auto">
    <div title="<?= $rbadge['label'] ?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;
      <?php if($is_captain): ?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);
      <?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);
      <?php else: ?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif; ?>">
      <i class="fas <?= $rbadge['icon'] ?>" style="font-size:12px"></i></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar" style="overflow-y:auto">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover" alt="">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div>
    </div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <div style="padding:14px 12px 6px"><button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer"><i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i></button></div>

  <!-- Quick stats -->
  <div style="padding:12px 10px 10px;border-bottom:1px solid rgba(255,255,255,.07)">
    <div class="sidebar-label" style="margin-bottom:7px">Certificate Requests</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px">
      <?php foreach([
        ['fa-clock','245,158,11','#fbbf24',$cert_counts['Pending'],'Pending'],
        ['fa-check-circle','139,92,246','#a78bfa',$cert_counts['Approved'],'Approved'],
        ['fa-paper-plane','20,184,166','#5eead4',$cert_counts['Released'],'Released'],
        ['fa-ban','244,63,94','#fda4af',$cert_counts['Rejected'],'Rejected'],
      ] as [$ico,$rgb,$tc,$val,$lbl]): ?>
      <div style="background:rgba(255,255,255,.05);border-radius:9px;padding:8px;display:flex;align-items:center;gap:7px">
        <div style="width:28px;height:28px;border-radius:7px;background:rgba(<?=$rgb?>,.22);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas <?=$ico?>" style="font-size:11px;color:<?=$tc?>"></i></div>
        <div>
          <div style="font-size:16px;font-weight:800;color:<?=$tc?>;line-height:1"><?=$val?></div>
          <div style="font-size:9.5px;color:rgba(255,255,255,.4);margin-top:1px"><?=$lbl?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register): ?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif; ?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <a href="data_tracking.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<!-- HERO -->
<div class="page-hero">
  <div style="max-width:1200px;width:100%">
    <h1><i class="fas fa-database" style="margin-right:.5rem;opacity:.8"></i>Document Tracking</h1>
    <p>Certificate &amp; Document Request Management</p>
  </div>
</div>

<main>
  <?php if ($msg): ?>
    <div class="alert <?= $msg_type ?>">
      <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab-btn <?= $filter_tab === 'cert_requests' || !in_array($filter_tab,['doc_requests','audit']) ? 'active' : '' ?>" onclick="switchTab('cert_requests')">
      <i class="fas fa-file-certificate"></i> Certificate Requests
      <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:4px"><?= $cert_counts['Pending'] ?></span>
    </button>
    <button class="tab-btn <?= $filter_tab === 'doc_requests' ? 'active' : '' ?>" onclick="switchTab('doc_requests')">
      <i class="fas fa-file-alt"></i> Document Requests
    </button>
    <button class="tab-btn <?= $filter_tab === 'audit' ? 'active' : '' ?>" onclick="switchTab('audit')">
      <i class="fas fa-history"></i> Audit Log
    </button>
  </div>

  <!-- ══ TAB 1: CERTIFICATE REQUESTS ══════════════════════════════════════════ -->
  <div id="tab-cert_requests" class="tab-content" style="display:<?= ($filter_tab === 'cert_requests' || !in_array($filter_tab,['doc_requests','audit'])) ? 'block' : 'none' ?>">

    <!-- Stats -->
    <div class="stats-row">
      <?php foreach(['Pending'=>'pending','Approved'=>'approved','Released'=>'released','Rejected'=>'rejected'] as $s=>$cls): ?>
      <div class="stat-card <?= $cls ?>">
        <div class="num"><?= $cert_counts[$s] ?></div>
        <div class="lbl"><?= $s ?></div>
      </div>
      <?php endforeach; ?>
      <div class="stat-card">
        <div class="num" style="color:var(--text)"><?= array_sum($cert_counts) ?></div>
        <div class="lbl">Total</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="tab" value="cert_requests">
        <input type="text" name="search" placeholder="Search resident or certificate…" value="<?= htmlspecialchars($search) ?>">
        <select name="status">
          <option value="">All Statuses</option>
          <?php foreach(['Pending','Approved','Released','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Filter</button>
      </form>
      <div class="ml-auto" style="display:flex;gap:8px">
        <button class="btn btn-primary" onclick="openCertModal()"><i class="fas fa-plus"></i> New Request</button>
      </div>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>#</th><th>Resident</th><th>Certificate Type</th><th>Purpose</th>
            <th>Status</th><th>Requested</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php if (!$cert_requests || $cert_requests->num_rows === 0): ?>
            <tr class="empty-row"><td colspan="7"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3"></i>No certificate requests found.</td></tr>
          <?php else: while ($cr = $cert_requests->fetch_assoc()): ?>
            <tr>
              <td style="color:var(--muted);font-size:12px"><?= $cr['id'] ?></td>
              <td><strong><?= htmlspecialchars($cr['first_name'] . ' ' . $cr['last_name']) ?></strong><br>
                <small style="color:var(--muted)"><?= htmlspecialchars(substr($cr['address'] ?? '', 0, 40)) ?></small></td>
              <td><?= htmlspecialchars($cr['template_name']) ?></td>
              <td style="max-width:160px;white-space:normal"><?= htmlspecialchars($cr['purpose'] ?? '—') ?></td>
              <td><span class="badge badge-<?= strtolower($cr['status']) ?>"><?= $cr['status'] ?></span></td>
              <td style="white-space:nowrap"><?= date('M d, Y', strtotime($cr['requested_at'])) ?><br>
                <small style="color:var(--muted)">by <?= htmlspecialchars($cr['requested_by'] ?? '—') ?></small></td>
              <td>
                <div class="actions">
                  <a href="Admin Dashboard/certificate_pdf.php?id=<?= $cr['id'] ?>" class="btn btn-outline btn-sm" target="_blank" title="View PDF"><i class="fas fa-file-pdf"></i></a>
                  <?php if ($cr['status'] === 'Pending'): ?>
                    <a href="?cert_approve=<?= $cr['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this request?')"><i class="fas fa-check"></i> Approve</a>
                    <a href="?cert_reject=<?= $cr['id'] ?>"  class="btn btn-danger  btn-sm" onclick="return confirm('Reject this request?')"><i class="fas fa-times"></i> Reject</a>
                  <?php elseif ($cr['status'] === 'Approved'): ?>
                    <a href="?cert_release=<?= $cr['id'] ?>" class="btn btn-primary btn-sm" onclick="return confirm('Mark as released?')"><i class="fas fa-check-double"></i> Release</a>
                  <?php endif; ?>
                  <form method="POST" onsubmit="return confirm('Delete this request?')" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_cert_request">
                    <input type="hidden" name="cert_id" value="<?= $cr['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- end tab-cert_requests -->

  <!-- ══ TAB 2: DOCUMENT REQUESTS ════════════════════════════════════════════ -->
  <div id="tab-doc_requests" class="tab-content" style="display:<?= $filter_tab === 'doc_requests' ? 'block' : 'none' ?>">

    <!-- Stats -->
    <div class="stats-row">
      <?php foreach(['Pending'=>'pending','Processing'=>'processing','Ready'=>'ready','Released'=>'released','Rejected'=>'rejected'] as $s=>$cls): ?>
      <div class="stat-card <?= $cls ?>">
        <div class="num"><?= $doc_counts[$s] ?></div>
        <div class="lbl"><?= $s ?></div>
      </div>
      <?php endforeach; ?>
      <div class="stat-card">
        <div class="num" style="color:var(--text)"><?= array_sum($doc_counts) ?></div>
        <div class="lbl">Total</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="tab" value="doc_requests">
        <input type="text" name="search" placeholder="Search name or code…" value="<?= htmlspecialchars($search) ?>">
        <select name="status">
          <option value="">All Statuses</option>
          <?php foreach(['Pending','Processing','Ready','Released','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Filter</button>
      </form>
      <div class="ml-auto" style="display:flex;gap:8px">
        <button class="btn btn-primary" onclick="openModal('addRequestModal')"><i class="fas fa-plus"></i> New Request</button>
      </div>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Code</th><th>Resident</th><th>Document Type</th>
            <th>Purpose</th><th>Status</th><th>Requested</th><th>Released</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php if (!$doc_requests || $doc_requests->num_rows === 0): ?>
            <tr class="empty-row"><td colspan="8"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3"></i>No document requests found.</td></tr>
          <?php else: while ($req = $doc_requests->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($req['request_code']) ?></strong></td>
              <td><?= htmlspecialchars($req['resident_name']) ?></td>
              <td><?= htmlspecialchars($req['document_type']) ?>
                <?php if ($req['document_type']==='Other' && $req['other_document']): ?>
                  <br><small style="color:var(--muted)"><?= htmlspecialchars($req['other_document']) ?></small>
                <?php endif; ?>
              </td>
              <td style="max-width:160px;white-space:normal"><?= htmlspecialchars($req['purpose'] ?? '—') ?></td>
              <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$req['status'])) ?>"><?= $req['status'] ?></span></td>
              <td><?= date('M d, Y', strtotime($req['requested_at'])) ?><br>
                <small style="color:var(--muted)">by <?= htmlspecialchars($req['requested_by']) ?></small></td>
              <td><?= $req['released_at'] ? date('M d, Y', strtotime($req['released_at'])) . '<br><small style="color:var(--muted)">by ' . htmlspecialchars($req['released_by'] ?? '') . '</small>' : '—' ?></td>
              <td>
                <div class="actions">
                  <button class="btn btn-outline btn-sm" onclick='openUpdateModal(<?= json_encode($req) ?>)'><i class="fas fa-edit"></i></button>
                  <form method="POST" onsubmit="return confirm('Delete this request?')" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_request">
                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- end tab-doc_requests -->

  <!-- ══ TAB 3: AUDIT LOG ════════════════════════════════════════════════════ -->
  <div id="tab-audit" class="tab-content" style="display:<?= $filter_tab === 'audit' ? 'block' : 'none' ?>">
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="tab" value="audit">
        <input type="text" name="search" placeholder="Search resident or admin…" value="<?= htmlspecialchars($search) ?>">
        <select name="audit_action">
          <option value="">All Actions</option>
          <option value="CREATE" <?= $filter_action==='CREATE'?'selected':'' ?>>Create</option>
          <option value="UPDATE" <?= $filter_action==='UPDATE'?'selected':'' ?>>Update</option>
          <option value="DELETE" <?= $filter_action==='DELETE'?'selected':'' ?>>Delete</option>
        </select>
        <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Filter</button>
      </form>
      <div class="ml-auto" style="display:flex;gap:8px">
        <button class="btn btn-primary" onclick="openModal('addAuditModal')"><i class="fas fa-plus"></i> Add Log Entry</button>
      </div>
    </div>

    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>#</th><th>Action</th><th>Resident</th><th>Record ID</th>
            <th>Field</th><th>Old Value</th><th>New Value</th>
            <th>Performed By</th><th>Date &amp; Time</th><th>Notes</th><th></th>
          </tr></thead>
          <tbody>
          <?php
          $audit_count = 0;
          if ($audits) while ($log = $audits->fetch_assoc()):
            $audit_count++;
          ?>
            <tr>
              <td style="color:var(--muted)"><?= $log['id'] ?></td>
              <td><span class="badge badge-<?= strtolower($log['action']) ?>"><?= $log['action'] ?></span></td>
              <td><?= htmlspecialchars($log['resident_name'] ?? '—') ?></td>
              <td><?= $log['record_id'] ?></td>
              <td><?= htmlspecialchars($log['field_changed'] ?? '—') ?></td>
              <td style="color:var(--red);max-width:100px;word-break:break-all"><?= htmlspecialchars($log['old_value'] ?? '—') ?></td>
              <td style="color:var(--green);max-width:100px;word-break:break-all"><?= htmlspecialchars($log['new_value'] ?? '—') ?></td>
              <td><?= htmlspecialchars($log['performed_by']) ?><br><small style="color:var(--muted)"><?= $log['ip_address'] ?></small></td>
              <td style="white-space:nowrap"><?= date('M d, Y', strtotime($log['performed_at'])) ?><br><small style="color:var(--muted)"><?= date('g:i A', strtotime($log['performed_at'])) ?></small></td>
              <td style="max-width:130px;white-space:normal"><?= htmlspecialchars($log['notes'] ?? '—') ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this entry?')" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_audit">
                  <input type="hidden" name="audit_id" value="<?= $log['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
          <?php if ($audit_count === 0): ?>
            <tr class="empty-row"><td colspan="11"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3"></i>No audit entries found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- end tab-audit -->

</main>

<!-- ══ MODAL: New Certificate Request ═══════════════════════════════════════ -->
<div class="modal-overlay" id="certRequestModal">
  <div class="modal">
    <h2><i class="fas fa-file-certificate" style="margin-right:8px;color:var(--blue)"></i>New Certificate Request</h2>
    <form method="POST" class="form-grid" id="certRequestForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_cert_request">
      <input type="hidden" name="resident_id" id="cert_resident_id" value="<?= $prefill_id ?>">
      <div class="form-group">
        <label>Resident *</label>
        <div class="res-search-wrap">
          <input type="text" id="certResInput" class="form-control" placeholder="Type resident name to search…"
            autocomplete="off" value="<?= $prefill_name ?>"
            oninput="certResSearch(this.value)"
            onfocus="if(this.value.length>1)document.getElementById('certResDropdown').classList.add('open')">
          <div class="res-dropdown" id="certResDropdown"></div>
        </div>
        <small style="color:var(--muted);font-size:11px;margin-top:3px;display:block" id="certResSelected">
          <?= $prefill_id ? 'Resident #'.$prefill_id.' pre-selected' : 'No resident selected yet' ?>
        </small>
      </div>
      <div class="form-group">
        <label>Certificate Type *</label>
        <select name="template_id" class="form-control" required>
          <option value="">— Select —</option>
          <?php foreach ($templates_arr as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['template_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Purpose</label>
        <textarea name="purpose" class="form-control" rows="2" placeholder="Purpose of request…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('certRequestModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL: New Document Request ══════════════════════════════════════════ -->
<div class="modal-overlay" id="addRequestModal">
  <div class="modal">
    <h2><i class="fas fa-file-plus" style="margin-right:8px;color:var(--blue)"></i>New Document Request</h2>
    <form method="POST" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_request">
      <div class="form-group">
        <label>Resident Name *</label>
        <input list="residents-list" name="resident_name" class="form-control" required placeholder="Type or search…">
        <datalist id="residents-list">
          <?php foreach ($residents_arr as $r): ?>
            <option value="<?= htmlspecialchars($r['full_name']) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Document Type *</label>
        <select name="document_type" class="form-control" required onchange="toggleOther(this)">
          <option value="">— Select —</option>
          <option>Barangay Clearance</option><option>Certificate of Residency</option>
          <option>Certificate of Indigency</option><option>Business Permit</option>
          <option>Certificate of Good Moral</option><option>Other</option>
        </select>
      </div>
      <div class="form-group" id="otherDocGroup" style="display:none">
        <label>Specify Document</label>
        <input type="text" name="other_document" class="form-control" placeholder="Enter document name…">
      </div>
      <div class="form-group">
        <label>Purpose</label>
        <textarea name="purpose" class="form-control" rows="2" placeholder="Purpose of request…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addRequestModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL: Update Document Request Status ═════════════════════════════════ -->
<div class="modal-overlay" id="updateRequestModal">
  <div class="modal">
    <h2><i class="fas fa-edit" style="margin-right:8px;color:var(--blue)"></i>Update Request Status</h2>
    <form method="POST" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="req_id" id="update_req_id">
      <div class="form-group">
        <label>Request Code</label>
        <input type="text" id="update_req_code" class="form-control" readonly style="opacity:.6">
      </div>
      <div class="form-group">
        <label>Resident</label>
        <input type="text" id="update_req_name" class="form-control" readonly style="opacity:.6">
      </div>
      <div class="form-group">
        <label>New Status *</label>
        <select name="status" class="form-control" required>
          <option>Pending</option><option>Processing</option>
          <option>Ready</option><option>Released</option><option>Rejected</option>
        </select>
      </div>
      <div class="form-group">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('updateRequestModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Update Status</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL: Add Audit Entry ════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addAuditModal">
  <div class="modal">
    <h2><i class="fas fa-history" style="margin-right:8px;color:var(--blue)"></i>Add Audit Log Entry</h2>
    <form method="POST" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_audit">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Action *</label>
          <select name="audit_action" class="form-control" required>
            <option value="CREATE">CREATE</option>
            <option value="UPDATE">UPDATE</option>
            <option value="DELETE">DELETE</option>
          </select>
        </div>
        <div class="form-group">
          <label>Record ID *</label>
          <input type="number" name="record_id" class="form-control" required min="1">
        </div>
      </div>
      <div class="form-group">
        <label>Resident Name *</label>
        <input list="residents-list" name="resident_name" class="form-control" required placeholder="Type or search…">
      </div>
      <div class="form-group">
        <label>Field Changed</label>
        <input type="text" name="field_changed" class="form-control" placeholder="e.g. address">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Old Value</label>
          <input type="text" name="old_value" class="form-control" placeholder="Previous value">
        </div>
        <div class="form-group">
          <label>New Value</label>
          <input type="text" name="new_value" class="form-control" placeholder="New value">
        </div>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addAuditModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Entry</button>
      </div>
    </form>
  </div>
</div>

<!-- Settings drawer -->
<div id="settingsOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100" onclick="closeSettings()"></div>
<div id="settingsDrawer" style="position:fixed;top:0;right:-360px;width:340px;height:100vh;background:#0f172a;z-index:1101;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.08)">
  <div style="padding:20px 20px 14px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#3b82f6,#14b8a6);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-gear" style="color:#fff;font-size:13px"></i></div>
      <div><div style="font-family:Syne,sans-serif;font-size:14px;font-weight:800;color:#fff">Settings</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Document Tracking</div></div>
    </div>
    <button onclick="closeSettings()" style="width:28px;height:28px;background:rgba(255,255,255,.08);border:none;border-radius:7px;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px"><i class="fas fa-times"></i></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:16px">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Document Settings</div>
    <a href="signatory_settings.php" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(139,92,246,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-pen-nib" style="color:#a78bfa;font-size:13px"></i></div>
      <div style="text-align:left">
        <div style="font-size:13px;font-weight:600;color:#fff">Signatory Settings</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4)"><?= $sig_name ? htmlspecialchars($sig_name) : 'Not set' ?> · <?= $sec_name_val ? htmlspecialchars($sec_name_val) : 'Secretary not set' ?></div>
      </div>
    </a>
  </div>
  <div style="padding:12px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <button onclick="printDT()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print Report</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print the current document list</div></div>
    </button>
  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div></div>
    </a>
  </div>
</div>

<div class="print-digital-watermark" style="display:none"><span>DIGITAL COPY - NOT VALID IF UNSIGNED</span></div>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + tab).style.display = 'block';
  event.currentTarget.classList.add('active');
  history.replaceState(null, '', '?tab=' + tab);
}

// ── Modals ─────────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open');    document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) closeModal(o.id); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id)); });

// ── Certificate request modal ──────────────────────────────────────────────
function openCertModal() {
  document.getElementById('certResInput').value = '';
  document.getElementById('cert_resident_id').value = '';
  document.getElementById('certResSelected').textContent = 'No resident selected yet';
  openModal('certRequestModal');
}

<?php if ($prefill_id): ?>
// Auto-open cert modal if prefill params present
window.addEventListener('DOMContentLoaded', () => openModal('certRequestModal'));
<?php endif; ?>

// Resident search for cert modal
let certSearchTimer = null;
function certResSearch(q) {
  clearTimeout(certSearchTimer);
  const dd = document.getElementById('certResDropdown');
  if (q.length < 2) { dd.classList.remove('open'); return; }
  certSearchTimer = setTimeout(() => {
    fetch('data_tracking.php?ajax_search=1&q=' + encodeURIComponent(q))
      .then(r => r.json()).then(data => {
        if (!data.length) { dd.innerHTML='<div class="res-opt" style="color:#94a3b8">No residents found</div>'; dd.classList.add('open'); return; }
        dd.innerHTML = data.map(r => {
          const name = `${r.first_name||''} ${r.middle_name||''} ${r.last_name||''}`.replace(/\s+/g,' ').trim();
          const age  = r.birthdate ? Math.floor((new Date() - new Date(r.birthdate)) / 31557600000) : '—';
          return `<div class="res-opt" onclick='certPickRes(${JSON.stringify({id:r.id,name:name})})'>
            <div class="ro-name">${name}</div>
            <div class="ro-sub">${age} yrs · ${r.gender||''} · ${(r.perm_address||'').substring(0,40)}</div>
          </div>`;
        }).join('');
        dd.classList.add('open');
      });
  }, 250);
}
function certPickRes(r) {
  document.getElementById('certResInput').value = r.name;
  document.getElementById('cert_resident_id').value = r.id;
  document.getElementById('certResSelected').textContent = 'Selected: ' + r.name + ' (#' + r.id + ')';
  document.getElementById('certResDropdown').classList.remove('open');
}
document.addEventListener('click', e => { if(!e.target.closest('.res-search-wrap')) document.getElementById('certResDropdown').classList.remove('open'); });

// ── Update document request modal ──────────────────────────────────────────
function openUpdateModal(req) {
  document.getElementById('update_req_id').value   = req.id;
  document.getElementById('update_req_code').value = req.request_code;
  document.getElementById('update_req_name').value = req.resident_name;
  document.querySelector('#updateRequestModal select[name="status"]').value = req.status;
  document.querySelector('#updateRequestModal textarea[name="remarks"]').value = req.remarks || '';
  openModal('updateRequestModal');
}

// ── Other ──────────────────────────────────────────────────────────────────
function toggleOther(sel) { document.getElementById('otherDocGroup').style.display = sel.value === 'Other' ? 'block' : 'none'; }
function openSidebar()  { document.getElementById('sidebar').classList.add('open');    document.getElementById('sidebarOverlay').classList.add('open');    document.body.style.overflow='hidden'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open'); document.body.style.overflow=''; }
function openSettings() { document.getElementById('settingsOverlay').style.display='block'; document.getElementById('settingsDrawer').style.right='0'; document.body.style.overflow='hidden'; closeSidebar(); }
function closeSettings(){ document.getElementById('settingsOverlay').style.display='none';  document.getElementById('settingsDrawer').style.right='-360px'; document.body.style.overflow=''; }
function printDT(){ closeSettings(); setTimeout(()=>window.print(), 350); }
document.getElementById('menuToggle').addEventListener('click', openSidebar);
document.addEventListener('keydown', e => { if(e.key==='Escape') closeSettings(); });
</script>
</body>
</html>





