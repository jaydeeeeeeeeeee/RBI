<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: admin.php");
    exit();
}
include 'role_helper.php';
if($is_guest){ header("Location: Home.php?denied=tracking"); exit(); }

require_once 'residents_DB.php';

$admin = $_SESSION['admin'];
$ip    = $_SERVER['REMOTE_ADDR'];

// ── Certificate settings & signatory ─────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
function get_setting($conn,$key,$default=''){
  $r=$conn->query("SELECT `value` FROM settings WHERE `key`='".mysqli_real_escape_string($conn,$key)."'")->fetch_assoc();
  return $r?$r['value']:$default;
}
$sig_raw   = get_setting($conn,'cert_signatory',get_setting($conn,'blotter_signatory','{}'));
$cert_sig  = json_decode($sig_raw,true)??[];
$sig_name  = $cert_sig['name']  ?? '';
$sig_title = $cert_sig['title'] ?? 'Punong Barangay';

// ── AJAX: resident search for certificate generator ───────────────────────────
if(isset($_GET['ajax_search'])){
  $q=mysqli_real_escape_string($conn,trim($_GET['q']??''));
  $res=$conn->query("SELECT id,first_name,middle_name,last_name,suffix,birthdate,gender,marital_status,perm_address,years_in_barangay FROM residents WHERE is_hidden=0 AND (first_name LIKE '%$q%' OR last_name LIKE '%$q%' OR CONCAT(first_name,' ',last_name) LIKE '%$q%') ORDER BY last_name LIMIT 15");
  $out=[];
  while($r=$res->fetch_assoc()) $out[]=$r;
  header('Content-Type: application/json');
  echo json_encode($out);
  exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function generate_request_code($conn) {
    $prefix = 'REQ-' . date('Ymd') . '-';
    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM document_requests WHERE request_code LIKE '$prefix%'");
    $row    = mysqli_fetch_assoc($result);
    return $prefix . str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
}

function sanitize($conn, $val) {
    return mysqli_real_escape_string($conn, trim($val));
}

$msg = '';
$msg_type = '';

// ── DOCUMENT REQUEST: Add ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    if ($_POST['action'] === 'add_request') {
        $code      = generate_request_code($conn);
        $res_name  = sanitize($conn, $_POST['resident_name']);
        $res_id    = !empty($_POST['resident_id']) ? (int)$_POST['resident_id'] : 'NULL';
        $doc_type  = sanitize($conn, $_POST['document_type']);
        $other_doc = sanitize($conn, $_POST['other_document'] ?? '');
        $purpose   = sanitize($conn, $_POST['purpose'] ?? '');
        $req_by    = sanitize($conn, $admin);

        $sql = "INSERT INTO document_requests
                (request_code, resident_id, resident_name, document_type, other_document, purpose, requested_by)
                VALUES ('$code', " . ($res_id === 'NULL' ? 'NULL' : $res_id) . ", '$res_name', '$doc_type', '$other_doc', '$purpose', '$req_by')";

        if (mysqli_query($conn, $sql)) {
            $msg = "Document request <strong>$code</strong> added successfully.";
            $msg_type = 'success';
        } else {
            $msg = "Error: " . mysqli_error($conn);
            $msg_type = 'error';
        }
    }

    // ── DOCUMENT REQUEST: Update Status ───────────────────────────────────────
    if ($_POST['action'] === 'update_status') {
        $id       = (int)$_POST['req_id'];
        $status   = sanitize($conn, $_POST['status']);
        $remarks  = sanitize($conn, $_POST['remarks'] ?? '');
        $released = ($status === 'Released') ? ", released_at = NOW(), released_by = '$admin'" : '';

        $sql = "UPDATE document_requests SET status='$status', remarks='$remarks' $released WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            $msg = "Request status updated to <strong>$status</strong>.";
            $msg_type = 'success';
        } else {
            $msg = "Error: " . mysqli_error($conn);
            $msg_type = 'error';
        }
    }

    // ── DOCUMENT REQUEST: Delete ──────────────────────────────────────────────
    if ($_POST['action'] === 'delete_request') {
        $id = (int)$_POST['req_id'];
        if (mysqli_query($conn, "DELETE FROM document_requests WHERE id=$id")) {
            $msg = "Request deleted.";
            $msg_type = 'success';
        } else {
            $msg = "Error: " . mysqli_error($conn);
            $msg_type = 'error';
        }
    }

    // ── AUDIT LOG: Add manual entry ───────────────────────────────────────────
    if ($_POST['action'] === 'add_audit') {
        $action       = sanitize($conn, $_POST['audit_action']);
        $rec_id       = (int)$_POST['record_id'];
        $res_name     = sanitize($conn, $_POST['resident_name']);
        $field        = sanitize($conn, $_POST['field_changed'] ?? '');
        $old_val      = sanitize($conn, $_POST['old_value'] ?? '');
        $new_val      = sanitize($conn, $_POST['new_value'] ?? '');
        $notes        = sanitize($conn, $_POST['notes'] ?? '');

        $sql = "INSERT INTO audit_log
                (action, record_id, resident_name, field_changed, old_value, new_value, performed_by, ip_address, notes)
                VALUES ('$action', $rec_id, '$res_name', '$field', '$old_val', '$new_val', '$admin', '$ip', '$notes')";

        if (mysqli_query($conn, $sql)) {
            $msg = "Audit log entry added.";
            $msg_type = 'success';
        } else {
            $msg = "Error: " . mysqli_error($conn);
            $msg_type = 'error';
        }
    }

    // ── AUDIT LOG: Delete entry ───────────────────────────────────────────────
    if ($_POST['action'] === 'delete_audit') {
        $id = (int)$_POST['audit_id'];
        if (mysqli_query($conn, "DELETE FROM audit_log WHERE id=$id")) {
            $msg = "Audit entry deleted.";
            $msg_type = 'success';
        } else {
            $msg = "Error: " . mysqli_error($conn);
            $msg_type = 'error';
        }
    }

    // ── CERTIFICATES: Save signatory ─────────────────────────────────────────
    if ($_POST['action'] === 'save_signatory' && $is_captain) {
        $sn  = trim($_POST['sig_name']  ?? '');
        $st  = trim($_POST['sig_title'] ?? 'Punong Barangay');
        $val = mysqli_real_escape_string($conn, json_encode(['name'=>$sn,'title'=>$st]));
        $conn->query("INSERT INTO settings (`key`,`value`) VALUES ('cert_signatory','$val') ON DUPLICATE KEY UPDATE `value`='$val'");
        $conn->query("INSERT INTO settings (`key`,`value`) VALUES ('blotter_signatory','$val') ON DUPLICATE KEY UPDATE `value`='$val'");
        $sig_name=$sn; $sig_title=$st;
        $msg = "Certificate signatory saved.";
        $msg_type = 'success';
    }
}

// ── Fetch Data ────────────────────────────────────────────────────────────────

// Filters
$search      = isset($_GET['search'])      ? sanitize($conn, $_GET['search'])      : '';
$filter_status = isset($_GET['status'])    ? sanitize($conn, $_GET['status'])      : '';
$filter_tab  = isset($_GET['tab'])         ? $_GET['tab']                          : 'requests';
$filter_action = isset($_GET['audit_action']) ? sanitize($conn, $_GET['audit_action']) : '';

// Document requests
$req_where = "WHERE 1=1";
if ($search)        $req_where .= " AND (resident_name LIKE '%$search%' OR request_code LIKE '%$search%')";
if ($filter_status) $req_where .= " AND status = '$filter_status'";
$requests = mysqli_query($conn, "SELECT * FROM document_requests $req_where ORDER BY requested_at DESC");
$total_requests = mysqli_num_rows($requests);

// Count by status
$counts = [];
foreach (['Pending','Processing','Ready','Released','Rejected'] as $s) {
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM document_requests WHERE status='$s'"));
    $counts[$s] = $r['c'];
}

// Audit log
$audit_where = "WHERE 1=1";
if ($search)        $audit_where .= " AND (resident_name LIKE '%$search%' OR performed_by LIKE '%$search%')";
if ($filter_action) $audit_where .= " AND action = '$filter_action'";
$audits = mysqli_query($conn, "SELECT * FROM audit_log $audit_where ORDER BY performed_at DESC LIMIT 200");

// Residents for autocomplete
$residents_list = mysqli_query($conn, "SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM residents ORDER BY last_name");
$residents_arr  = [];
while ($r = mysqli_fetch_assoc($residents_list)) {
    $residents_arr[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Document Tracking System – Barangay 410</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&family=IM+Fell+English:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="assets/css/main.css"/>
  <style>
    :root {
      --red:     #ef4444;
      --red-lt:  #fef2f2;
      --gold:    #f59e0b;
      --gold-lt: #fffbeb;
    }

    /* PAGE */
    .page-header { background: var(--navy); padding: 2rem 2rem 1.5rem; }
    .page-header h1 { color: #fff; font-size: 1.5rem; font-weight: 600; }
    .page-header p  { color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 4px; }

    main { padding: 2rem; max-width: 1300px; margin: 0 auto; }

    /* ALERT */
    .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; }
    .alert.success { background: var(--green-lt); color: #15803d; border: 1px solid #bbf7d0; }
    .alert.error   { background: var(--red-lt);   color: #b91c1c; border: 1px solid #fecaca; }

    /* STAT CARDS */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 1.5rem; }
    .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; text-align: center; }
    .stat-card .num { font-size: 24px; font-weight: 600; }
    .stat-card .lbl { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .stat-card.pending   .num { color: var(--gold); }
    .stat-card.processing .num { color: var(--blue); }
    .stat-card.ready     .num { color: #8b5cf6; }
    .stat-card.released  .num { color: var(--green); }
    .stat-card.rejected  .num { color: var(--red); }

    /* TABS */
    .tabs { display: flex; gap: 4px; margin-bottom: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 4px; width: fit-content; }
    .tab-btn { padding: 7px 20px; border-radius: 7px; border: none; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 500; background: none; color: var(--muted); transition: all 0.2s; }
    .tab-btn.active { background: var(--navy); color: #fff; }

    /* TOOLBAR */
    .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
    .toolbar input, .toolbar select {
      border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px;
      font-size: 13px; font-family: 'Poppins', sans-serif; color: var(--text);
      background: var(--card); outline: none; transition: border 0.2s;
    }
    .toolbar input:focus, .toolbar select:focus { border-color: var(--blue); }
    .toolbar input { min-width: 220px; }
    .btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 500; transition: all 0.2s; }
    .btn-primary { background: var(--blue); color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-success { background: var(--green); color: #fff; }
    .btn-success:hover { background: #16a34a; }
    .btn-danger  { background: var(--red-lt); color: var(--red); border: 1px solid #fecaca; }
    .btn-danger:hover  { background: #fee2e2; }
    .btn-outline { background: var(--card); color: var(--text); border: 1px solid var(--border); }
    .btn-outline:hover { background: var(--bg); }
    .btn-sm { padding: 5px 11px; font-size: 12px; }
    .ml-auto { margin-left: auto; }

    /* TABLE */
    .table-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { background: #f8fafc; padding: 10px 14px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); border-bottom: 1px solid var(--border); font-weight: 600; white-space: nowrap; }
    td { padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #f8fafc; }
    .empty-row td { text-align: center; color: var(--muted); padding: 2.5rem; font-size: 13px; }

    /* BADGES */
    .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; padding: 3px 9px; border-radius: 20px; white-space: nowrap; }
    .badge-pending    { background: var(--gold-lt);  color: #92400e; }
    .badge-processing { background: var(--blue-lt);  color: #1e40af; }
    .badge-ready      { background: #f5f3ff;         color: #6d28d9; }
    .badge-released   { background: var(--green-lt); color: #15803d; }
    .badge-rejected   { background: var(--red-lt);   color: #b91c1c; }
    .badge-create     { background: var(--green-lt); color: #15803d; }
    .badge-update     { background: var(--blue-lt);  color: #1e40af; }
    .badge-delete     { background: var(--red-lt);   color: #b91c1c; }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 999; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--card); border-radius: 14px; padding: 1.75rem; width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; }
    .modal h2 { font-size: 16px; font-weight: 600; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
    .form-grid { display: grid; gap: 14px; }
    .form-group label { display: block; font-size: 12px; font-weight: 500; color: var(--muted); margin-bottom: 5px; }
    .form-control {
      width: 100%; border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; font-size: 13px; font-family: 'Poppins', sans-serif;
      color: var(--text); background: var(--bg); outline: none; transition: border 0.2s;
    }
    .form-control:focus { border-color: var(--blue); background: #fff; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border); }

    /* PRINT for non-cert tabs */
    @media print {
      header.topbar, .page-header, .tabs, .toolbar, .btn, .modal-overlay { display: none !important; }
      body { background: #fff; }
      .table-card { border: none; box-shadow: none; }
    }

    /* ── CERTIFICATES TAB ─────────────────────────────────────────── */
    .cert-layout{display:grid;grid-template-columns:360px 1fr;gap:1.25rem;margin-top:.5rem}
    @media(max-width:900px){.cert-layout{grid-template-columns:1fr}}
    .cert-form-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem}
    .cert-form-panel h2{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:800;color:#0f172a;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px}
    .cert-fg{margin-bottom:12px}
    .cert-fg label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px}
    .cert-fg input,.cert-fg select{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border .2s}
    .cert-fg input:focus,.cert-fg select:focus{border-color:#3b82f6}
    .cert-type-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
    .cert-type-opt{border:1.5px solid #e2e8f0;border-radius:9px;padding:10px;cursor:pointer;transition:all .2s;text-align:center;position:relative}
    .cert-type-opt input{position:absolute;opacity:0;width:0;height:0}
    .cert-type-opt.selected{border-color:var(--sel-c,#3b82f6);background:var(--sel-bg,#eff6ff)}
    .cert-type-opt .ct-icon{font-size:18px;margin-bottom:4px}
    .cert-type-opt .ct-label{font-size:11px;font-weight:700;color:#0f172a;line-height:1.3}
    .cert-res-search{position:relative}
    .cert-res-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:100;max-height:220px;overflow-y:auto;display:none}
    .cert-res-dropdown.open{display:block}
    .cert-res-opt{padding:9px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f8fafc;transition:background .15s}
    .cert-res-opt:hover{background:#f0f9ff}
    .cert-res-opt .ro-name{font-weight:600;color:#0f172a}
    .cert-res-opt .ro-sub{font-size:11px;color:#64748b;margin-top:1px}
    .cert-sig-panel{background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:.75rem 1rem;margin-bottom:14px}
    .cert-preview-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem;min-height:400px}
    .cert-preview-panel h2{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:800;color:#0f172a;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px}
    .cert-preview-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:320px;color:#94a3b8;gap:10px}
    #cert-output{display:none}
    .cert-page{width:100%;max-width:680px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:4px;padding:2.5rem 3rem;position:relative;overflow:hidden;font-family:'Times New Roman',serif}
    .cert-page::before{content:'BARANGAY 410';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-40deg);font-size:72px;font-weight:900;color:rgba(0,0,0,.04);white-space:nowrap;z-index:0;pointer-events:none;font-family:'Syne',sans-serif}
    .cert-page *{position:relative;z-index:1}
    .cert-inner-border{position:absolute;inset:12px;border:2px solid rgba(15,23,42,.08);border-radius:2px;pointer-events:none;z-index:0}
    .cert-header{text-align:center;border-bottom:2px solid #0f172a;padding-bottom:14px;margin-bottom:18px}
    .cert-republic{font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:#475569;margin-bottom:2px}
    .cert-brgy-name{font-size:18px;font-weight:900;font-family:'Syne',sans-serif;color:#0f172a;margin-bottom:2px}
    .cert-addr{font-size:11px;color:#475569}
    .cert-ctrl-no{position:absolute;top:2.5rem;right:3rem;text-align:right}
    .cert-ctrl-no .cc-label{font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}
    .cert-ctrl-no .cc-no{font-size:11px;font-weight:700;color:#0f172a;font-family:monospace}
    .cert-main-title{text-align:center;font-size:22px;font-weight:900;font-family:'Syne',sans-serif;color:#0f172a;letter-spacing:.05em;margin-bottom:20px;text-decoration:underline;text-underline-offset:4px}
    .cert-body-text{font-size:14px;line-height:1.9;color:#0f172a;text-align:justify;margin-bottom:18px}
    .cert-body-text .res-name{font-size:15px;font-weight:900;text-transform:uppercase}
    .cert-issued-line{font-size:13px;color:#0f172a;margin-bottom:28px}
    .cert-validity-note{font-size:11px;color:#64748b;font-style:italic;text-align:center;margin-bottom:20px}
    .cert-sig-block{margin-top:32px;display:flex;justify-content:flex-end}
    .cert-sig{text-align:center;min-width:240px}
    .cert-sig .cs-name{font-size:14px;font-weight:900;font-family:'Syne',sans-serif;border-top:1.5px solid #0f172a;padding-top:6px}
    .cert-sig .cs-title{font-size:12px;color:#475569}
    .cert-not-valid{text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px;margin-top:18px}
    @media print{
      body::before{content:'BARANGAY 410';position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-40deg);font-size:90px;font-weight:900;color:rgba(0,0,0,.05);white-space:nowrap;z-index:9999;pointer-events:none;font-family:'Syne',sans-serif}
      header.topbar,.page-header,.tabs,.stats-row,#tab-requests,#tab-audit,.modal-overlay,.no-print{display:none!important}
      #tab-certificates .cert-form-panel{display:none!important}
      #tab-certificates{display:block!important}
      .cert-layout{grid-template-columns:1fr!important}
      .cert-preview-panel{border:none!important;padding:0!important}
      #cert-output{display:block!important}
      .cert-page{border:none!important;max-width:100%!important;padding:2rem!important}
    }
  </style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0"><div class="topbar-logo">410</div><div><div class="topbar-name">Barangay 410</div><div class="topbar-sub">Document Tracking</div></div></a>
  <div style="display:flex;align-items:center;border-left:1px solid rgba(255,255,255,.12);padding-left:14px;min-width:0">
    <span style="font-size:13px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;white-space:nowrap"><i class="fas fa-database" style="opacity:.8;margin-right:5px"></i> Document Tracking</span>
  </div>
  <div class="topbar-right" style="margin-left:auto">
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain):?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);<?php elseif($is_secretary):?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);<?php else:?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif;?>"><i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head"><div class="sidebar-head-brand"><div class="sidebar-head-logo">410</div><div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div></div><button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button></div>
  <div style="padding:14px 12px 6px"><button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer"><i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i></button></div>
  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register):?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <a href="data_tracking.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="../eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<div class="page-header">
  <h1><i class="fas fa-database" style="margin-right:8px;opacity:0.8"></i>Document Tracking System</h1>
  <p>Monitor resident record changes and manage document requests</p>
</div>

<main>

  <?php if ($msg): ?>
    <div class="alert <?= $msg_type ?>">
      <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <!-- STAT CARDS (document requests) — hidden on certificates tab -->
  <?php if($filter_tab !== 'certificates'): ?>
  <div class="stats-row">
    <?php foreach (['Pending'=>'gold','Processing'=>'processing','Ready'=>'ready','Released'=>'released','Rejected'=>'rejected'] as $s => $cls): ?>
    <div class="stat-card <?= strtolower($s) === 'processing' ? 'processing' : strtolower($s) ?>">
      <div class="num"><?= $counts[$s] ?></div>
      <div class="lbl"><?= $s ?></div>
    </div>
    <?php endforeach; ?>
    <div class="stat-card" style="border-color:var(--border)">
      <div class="num" style="color:var(--text)"><?= array_sum($counts) ?></div>
      <div class="lbl">Total Requests</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab-btn <?= ($filter_tab !== 'audit' && $filter_tab !== 'certificates') ? 'active' : '' ?>" onclick="switchTab('requests')">
      <i class="fas fa-file-alt"></i> Document Requests
    </button>
    <button class="tab-btn <?= $filter_tab === 'audit' ? 'active' : '' ?>" onclick="switchTab('audit')">
      <i class="fas fa-history"></i> Audit Log
    </button>
    <button class="tab-btn <?= $filter_tab === 'certificates' ? 'active' : '' ?>" onclick="switchTab('certificates')">
      <i class="fas fa-file-certificate"></i> Certificates
    </button>
  </div>

  <!-- ── DOCUMENT REQUESTS TAB ──────────────────────────────────────────── -->
  <div id="tab-requests" class="tab-content" style="display:<?= ($filter_tab !== 'audit' && $filter_tab !== 'certificates') ? 'block' : 'none' ?>">
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="tab" value="requests">
        <input type="text" name="search" placeholder="Search name or code…" value="<?= htmlspecialchars($search) ?>">
        <select name="status">
          <option value="">All Statuses</option>
          <?php foreach (['Pending','Processing','Ready','Released','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Filter</button>
      </form>
      <div class="ml-auto" style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-primary" onclick="openModal('addRequestModal')"><i class="fas fa-plus"></i> New Request</button>
      </div>
    </div>

    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Resident</th>
              <th>Document Type</th>
              <th>Purpose</th>
              <th>Status</th>
              <th>Requested</th>
              <th>Released</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (mysqli_num_rows($requests) === 0): ?>
              <tr class="empty-row"><td colspan="8"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.3"></i>No document requests found.</td></tr>
            <?php else: while ($req = mysqli_fetch_assoc($requests)): ?>
            <tr>
              <td><strong><?= htmlspecialchars($req['request_code']) ?></strong></td>
              <td><?= htmlspecialchars($req['resident_name']) ?></td>
              <td>
                <?= htmlspecialchars($req['document_type']) ?>
                <?php if ($req['document_type'] === 'Other' && $req['other_document']): ?>
                  <br><small style="color:var(--muted)"><?= htmlspecialchars($req['other_document']) ?></small>
                <?php endif; ?>
              </td>
              <td style="max-width:180px;white-space:normal"><?= htmlspecialchars($req['purpose'] ?? '—') ?></td>
              <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$req['status'])) ?>"><?= $req['status'] ?></span></td>
              <td><?= date('M d, Y g:i A', strtotime($req['requested_at'])) ?><br><small style="color:var(--muted)">by <?= htmlspecialchars($req['requested_by']) ?></small></td>
              <td><?= $req['released_at'] ? date('M d, Y', strtotime($req['released_at'])) . '<br><small style="color:var(--muted)">by ' . htmlspecialchars($req['released_by']) . '</small>' : '—' ?></td>
              <td>
                <div style="display:flex;gap:6px">
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
  </div>

  <!-- ── AUDIT LOG TAB ──────────────────────────────────────────────────── -->
  <div id="tab-audit" class="tab-content" style="display:<?= $filter_tab === 'audit' ? 'block' : 'none' ?>">
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="tab" value="audit">
        <input type="text" name="search" placeholder="Search resident or admin…" value="<?= htmlspecialchars($search) ?>">
        <select name="audit_action">
          <option value="">All Actions</option>
          <option value="CREATE" <?= $filter_action === 'CREATE' ? 'selected' : '' ?>>Create</option>
          <option value="UPDATE" <?= $filter_action === 'UPDATE' ? 'selected' : '' ?>>Update</option>
          <option value="DELETE" <?= $filter_action === 'DELETE' ? 'selected' : '' ?>>Delete</option>
        </select>
        <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Filter</button>
      </form>
      <div class="ml-auto" style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-primary" onclick="openModal('addAuditModal')"><i class="fas fa-plus"></i> Add Log Entry</button>
      </div>
    </div>

    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Action</th>
              <th>Resident</th>
              <th>Record ID</th>
              <th>Field Changed</th>
              <th>Old Value</th>
              <th>New Value</th>
              <th>Performed By</th>
              <th>Date &amp; Time</th>
              <th>Notes</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $audit_count = 0;
            mysqli_data_seek($audits, 0);
            while ($log = mysqli_fetch_assoc($audits)):
              $audit_count++;
            ?>
            <tr>
              <td style="color:var(--muted)"><?= $log['id'] ?></td>
              <td><span class="badge badge-<?= strtolower($log['action']) ?>"><?= $log['action'] ?></span></td>
              <td><?= htmlspecialchars($log['resident_name'] ?? '—') ?></td>
              <td><?= $log['record_id'] ?></td>
              <td><?= htmlspecialchars($log['field_changed'] ?? '—') ?></td>
              <td style="color:var(--red);max-width:120px;word-break:break-all"><?= htmlspecialchars($log['old_value'] ?? '—') ?></td>
              <td style="color:var(--green);max-width:120px;word-break:break-all"><?= htmlspecialchars($log['new_value'] ?? '—') ?></td>
              <td><?= htmlspecialchars($log['performed_by']) ?><br><small style="color:var(--muted)"><?= $log['ip_address'] ?></small></td>
              <td style="white-space:nowrap"><?= date('M d, Y', strtotime($log['performed_at'])) ?><br><small style="color:var(--muted)"><?= date('g:i A', strtotime($log['performed_at'])) ?></small></td>
              <td style="max-width:140px;white-space:normal"><?= htmlspecialchars($log['notes'] ?? '—') ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this log entry?')" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_audit">
                  <input type="hidden" name="audit_id" value="<?= $log['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($audit_count === 0): ?>
              <tr class="empty-row"><td colspan="11"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.3"></i>No audit log entries found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── CERTIFICATES TAB ──────────────────────────────────────────────────── -->
  <div id="tab-certificates" class="tab-content" style="display:<?= $filter_tab === 'certificates' ? 'block' : 'none' ?>">
    <div class="cert-layout">

      <!-- FORM PANEL -->
      <div class="cert-form-panel no-print">
        <h2><i class="fas fa-sliders" style="color:#3b82f6"></i> Certificate Details</h2>

        <!-- Type selector -->
        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px">Certificate Type *</div>
        <div class="cert-type-grid">
          <label class="cert-type-opt" id="copt-clearance" onclick="certSelectType('clearance')" style="--sel-c:#3b82f6;--sel-bg:#eff6ff">
            <input type="radio" name="cert_type" value="clearance" checked>
            <div class="ct-icon" style="color:#3b82f6"><i class="fas fa-file-circle-check"></i></div>
            <div class="ct-label">Barangay Clearance</div>
          </label>
          <label class="cert-type-opt" id="copt-indigency" onclick="certSelectType('indigency')" style="--sel-c:#8b5cf6;--sel-bg:#f5f3ff">
            <input type="radio" name="cert_type" value="indigency">
            <div class="ct-icon" style="color:#8b5cf6"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="ct-label">Cert. of Indigency</div>
          </label>
          <label class="cert-type-opt" id="copt-residency" onclick="certSelectType('residency')" style="--sel-c:#f59e0b;--sel-bg:#fffbeb">
            <input type="radio" name="cert_type" value="residency">
            <div class="ct-icon" style="color:#f59e0b"><i class="fas fa-house-chimney"></i></div>
            <div class="ct-label">Cert. of Residency</div>
          </label>
          <label class="cert-type-opt" id="copt-good_moral" onclick="certSelectType('good_moral')" style="--sel-c:#22c55e;--sel-bg:#f0fdf4">
            <input type="radio" name="cert_type" value="good_moral">
            <div class="ct-icon" style="color:#22c55e"><i class="fas fa-user-shield"></i></div>
            <div class="ct-label">Good Moral Character</div>
          </label>
        </div>

        <!-- Resident search -->
        <div class="cert-fg">
          <label>Resident Name *</label>
          <div class="cert-res-search">
            <input type="text" id="certResSearch" placeholder="Type name to search…" autocomplete="off"
              oninput="certSearchRes(this.value)"
              onfocus="if(this.value.length>1)document.getElementById('certResDropdown').classList.add('open')">
            <div class="cert-res-dropdown" id="certResDropdown"></div>
          </div>
          <input type="hidden" id="certResId">
        </div>
        <div id="certResInfoBox" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-top:-8px;margin-bottom:12px;font-size:12px"></div>

        <!-- Purpose -->
        <div class="cert-fg">
          <label>Purpose *</label>
          <select id="certPurpose" onchange="certUpdateCustomPurpose()">
            <option value="employment purposes">Employment Purposes</option>
            <option value="loan application">Loan Application</option>
            <option value="scholarship application">Scholarship Application</option>
            <option value="school requirements">School Requirements</option>
            <option value="travel purposes">Travel Purposes</option>
            <option value="legal purposes">Legal Purposes</option>
            <option value="government transaction">Government Transaction</option>
            <option value="other purposes">Other Purposes</option>
          </select>
        </div>
        <div class="cert-fg" id="certCustomPurposeWrap" style="display:none">
          <label>Specify Purpose</label>
          <input type="text" id="certCustomPurpose" placeholder="Describe the purpose…">
        </div>

        <!-- Issue date -->
        <div class="cert-fg">
          <label>Date of Issue</label>
          <input type="date" id="certIssueDate" value="<?=date('Y-m-d')?>" onwheel="this.blur()" ondragstart="return false">
        </div>

        <!-- Signatory -->
        <div class="cert-sig-panel">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#92400e;margin-bottom:6px">Signatory</div>
          <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div style="flex:1;min-width:140px">
              <label style="font-size:11px;color:#92400e;display:block;margin-bottom:3px">Name</label>
              <input type="text" id="certSigName" value="<?=htmlspecialchars($sig_name)?>" placeholder="Signatory full name"
                style="padding:7px 10px;border:1px solid #fde68a;border-radius:7px;font-size:12px;font-family:Inter,sans-serif;outline:none;background:#fff;width:100%;box-sizing:border-box">
            </div>
            <div style="flex:1;min-width:120px">
              <label style="font-size:11px;color:#92400e;display:block;margin-bottom:3px">Title</label>
              <input type="text" id="certSigTitle" value="<?=htmlspecialchars($sig_title)?>" placeholder="Punong Barangay"
                style="padding:7px 10px;border:1px solid #fde68a;border-radius:7px;font-size:12px;font-family:Inter,sans-serif;outline:none;background:#fff;width:100%;box-sizing:border-box">
            </div>
          </div>
          <?php if($is_captain):?>
          <form method="POST" action="?tab=certificates" style="margin-top:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_signatory">
            <input type="hidden" name="sig_name" id="saveCertSigName">
            <input type="hidden" name="sig_title" id="saveCertSigTitle">
            <button type="button" class="btn btn-outline btn-sm no-print" onclick="certSaveSignatory()" style="font-size:11px"><i class="fas fa-save"></i> Save as default</button>
          </form>
          <?php endif;?>
        </div>

        <button class="btn btn-primary no-print" style="width:100%;padding:11px" onclick="certGeneratePreview()">
          <i class="fas fa-eye"></i> Generate Preview
        </button>
      </div>

      <!-- PREVIEW PANEL -->
      <div class="cert-preview-panel">
        <h2 class="no-print"><i class="fas fa-file-alt" style="color:#3b82f6"></i> Preview</h2>

        <div id="certPreviewEmpty" class="cert-preview-empty">
          <i class="fas fa-file-certificate" style="font-size:52px;opacity:.15"></i>
          <div style="font-size:13px;text-align:center">Select a resident and click<br><strong>Generate Preview</strong> to see the certificate.</div>
        </div>

        <div id="cert-output">
          <div class="cert-page" id="certPage">
            <div class="cert-inner-border"></div>
            <div class="cert-ctrl-no">
              <div class="cc-label">Control No.</div>
              <div class="cc-no" id="certControlNo"></div>
            </div>
            <div class="cert-header">
              <div class="cert-republic">Republic of the Philippines · City of Manila</div>
              <div class="cert-brgy-name">Barangay 410</div>
              <div class="cert-addr">Tondo, Manila City · 1012</div>
            </div>
            <div class="cert-main-title" id="certTitle"></div>
            <div class="cert-body-text" id="certBody"></div>
            <div class="cert-issued-line" id="certIssued"></div>
            <div class="cert-validity-note">This certificate is valid for <strong>six (6) months</strong> from date of issue.</div>
            <div class="cert-sig-block">
              <div class="cert-sig">
                <div style="height:40px"></div>
                <div class="cs-name" id="certSigNameDisp"></div>
                <div class="cs-title" id="certSigTitleDisp"></div>
              </div>
            </div>
            <div class="cert-not-valid">Not valid without official seal · Barangay 410, Tondo, Manila</div>
          </div>

          <div style="display:flex;justify-content:flex-end;margin-top:1rem;gap:8px" class="no-print">
            <button class="btn btn-outline" onclick="document.getElementById('cert-output').style.display='none';document.getElementById('certPreviewEmpty').style.display='flex'">
              <i class="fas fa-times"></i> Clear
            </button>
            <button class="btn btn-primary" onclick="window.print()">
              <i class="fas fa-print"></i> Print Certificate
            </button>
          </div>
        </div>
      </div>

    </div><!-- end cert-layout -->
  </div><!-- end tab-certificates -->

</main>

<!-- ── MODAL: New Document Request ────────────────────────────────────────── -->
<div class="modal-overlay" id="addRequestModal">
  <div class="modal">
    <h2><i class="fas fa-file-plus" style="margin-right:8px;color:var(--blue)"></i>New Document Request</h2>
    <form method="POST" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_request">
      <div class="form-group">
        <label>Resident Name *</label>
        <input list="residents-list" name="resident_name" class="form-control" required placeholder="Type or search resident…">
        <datalist id="residents-list">
          <?php foreach ($residents_arr as $r): ?>
            <option value="<?= htmlspecialchars($r['full_name']) ?>" data-id="<?= $r['id'] ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Document Type *</label>
        <select name="document_type" class="form-control" required onchange="toggleOther(this)">
          <option value="">— Select —</option>
          <option>Barangay Clearance</option>
          <option>Certificate of Residency</option>
          <option>Certificate of Indigency</option>
          <option>Business Permit</option>
          <option>Certificate of Good Moral</option>
          <option>Other</option>
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

<!-- ── MODAL: Update Request Status ──────────────────────────────────────── -->
<div class="modal-overlay" id="updateRequestModal">
  <div class="modal">
    <h2><i class="fas fa-edit" style="margin-right:8px;color:var(--blue)"></i>Update Request Status</h2>
    <form method="POST" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="req_id" id="update_req_id">
      <div class="form-group">
        <label>Request Code</label>
        <input type="text" id="update_req_code" class="form-control" readonly style="opacity:0.6">
      </div>
      <div class="form-group">
        <label>Resident</label>
        <input type="text" id="update_req_name" class="form-control" readonly style="opacity:0.6">
      </div>
      <div class="form-group">
        <label>New Status *</label>
        <select name="status" class="form-control" required>
          <option>Pending</option>
          <option>Processing</option>
          <option>Ready</option>
          <option>Released</option>
          <option>Rejected</option>
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

<!-- ── MODAL: Add Audit Log Entry ─────────────────────────────────────────── -->
<div class="modal-overlay" id="addAuditModal">
  <div class="modal">
    <h2><i class="fas fa-history" style="margin-right:8px;color:var(--blue)"></i>Add Audit Log Entry</h2>
    <form method="POST" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_audit">
      <div class="form-row">
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
        <input list="residents-list2" name="resident_name" class="form-control" required placeholder="Type or search…">
        <datalist id="residents-list2">
          <?php foreach ($residents_arr as $r): ?>
            <option value="<?= htmlspecialchars($r['full_name']) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Field Changed</label>
        <input type="text" name="field_changed" class="form-control" placeholder="e.g. address, contact_number…">
      </div>
      <div class="form-row">
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
        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addAuditModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Log Entry</button>
      </div>
    </form>
  </div>
</div>

<script>
  function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    event.currentTarget.classList.add('active');
    history.replaceState(null, '', '?tab=' + tab);
  }

  function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) closeModal(this.id);
    });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
  });

  function toggleOther(sel) {
    document.getElementById('otherDocGroup').style.display = sel.value === 'Other' ? 'block' : 'none';
  }

  function openUpdateModal(req) {
    document.getElementById('update_req_id').value   = req.id;
    document.getElementById('update_req_code').value = req.request_code;
    document.getElementById('update_req_name').value = req.resident_name;
    const sel = document.querySelector('#updateRequestModal select[name="status"]');
    sel.value = req.status;
    const rem = document.querySelector('#updateRequestModal textarea[name="remarks"]');
    rem.value = req.remarks || '';
    openModal('updateRequestModal');
  }

  // ── CERTIFICATE GENERATOR ─────────────────────────────────────────────────
  const certDefs={
    clearance:  {title:'BARANGAY CLEARANCE',color:'#3b82f6'},
    indigency:  {title:'CERTIFICATE OF INDIGENCY',color:'#8b5cf6'},
    residency:  {title:'CERTIFICATE OF RESIDENCY',color:'#f59e0b'},
    good_moral: {title:'CERTIFICATE OF GOOD MORAL CHARACTER',color:'#22c55e'},
  };
  let certSelType='clearance', certSelResident=null;

  function certSelectType(t){
    certSelType=t;
    document.querySelectorAll('.cert-type-opt').forEach(el=>el.classList.remove('selected'));
    document.getElementById('copt-'+t).classList.add('selected');
    document.getElementById('copt-'+t).querySelector('input').checked=true;
  }
  certSelectType('clearance');

  let certSearchTimer=null;
  function certSearchRes(q){
    clearTimeout(certSearchTimer);
    if(q.length<2){document.getElementById('certResDropdown').classList.remove('open');return;}
    certSearchTimer=setTimeout(()=>{
      fetch('data_tracking.php?ajax_search=1&q='+encodeURIComponent(q))
        .then(r=>r.json()).then(data=>{
          const dd=document.getElementById('certResDropdown');
          if(!data.length){dd.innerHTML='<div class="cert-res-opt" style="color:#94a3b8">No residents found</div>';dd.classList.add('open');return;}
          dd.innerHTML=data.map(r=>{
            const age=r.birthdate?certCalcAge(r.birthdate):'—';
            const name=[r.last_name,r.first_name,r.middle_name].filter(Boolean).join(', ');
            return `<div class="cert-res-opt" onclick='certPickRes(${JSON.stringify(r)})'>
              <div class="ro-name">${name}${r.suffix?' '+r.suffix:''}</div>
              <div class="ro-sub">${age} yrs · ${r.gender||''} · ${r.perm_address||'—'}</div>
            </div>`;
          }).join('');
          dd.classList.add('open');
        });
    },280);
  }

  function certCalcAge(bd){
    const b=new Date(bd),n=new Date();
    let a=n.getFullYear()-b.getFullYear();
    const m=n.getMonth()-b.getMonth();
    if(m<0||(m===0&&n.getDate()<b.getDate())) a--;
    return a;
  }

  function certPickRes(r){
    certSelResident=r;
    const age=r.birthdate?certCalcAge(r.birthdate):'—';
    const name=`${r.first_name||''} ${r.middle_name?r.middle_name+' ':''} ${r.last_name||''}${r.suffix?' '+r.suffix:''}`.replace(/\s+/g,' ').trim();
    document.getElementById('certResSearch').value=name;
    document.getElementById('certResId').value=r.id;
    document.getElementById('certResDropdown').classList.remove('open');
    document.getElementById('certResInfoBox').style.display='block';
    document.getElementById('certResInfoBox').innerHTML=
      `<span style="font-weight:600;color:#0f172a">${name}</span> &nbsp;·&nbsp; `+
      `${age} yrs old &nbsp;·&nbsp; ${r.gender||''} &nbsp;·&nbsp; ${r.marital_status||''}<br>`+
      `<span style="color:#64748b">${r.perm_address||'—'}, Barangay 410, Tondo, Manila</span>`;
  }

  document.addEventListener('click',e=>{
    if(!e.target.closest('.cert-res-search')) document.getElementById('certResDropdown').classList.remove('open');
  });

  function certUpdateCustomPurpose(){
    document.getElementById('certCustomPurposeWrap').style.display=
      document.getElementById('certPurpose').value==='other purposes'?'block':'none';
  }

  function certGeneratePreview(){
    if(!certSelResident){alert('Please select a resident first.');return;}
    const r=certSelResident;
    const sigName=document.getElementById('certSigName').value||'[Signatory Name]';
    const sigTitle=document.getElementById('certSigTitle').value||'Punong Barangay';
    const issueDate=document.getElementById('certIssueDate').value;
    const purposeEl=document.getElementById('certPurpose');
    const purpose=purposeEl.value==='other purposes'
      ?(document.getElementById('certCustomPurpose').value||'other purposes')
      :purposeEl.value;
    const ct=certDefs[certSelType];

    const fname=r.first_name||'',mname=r.middle_name?r.middle_name+' ':'',lname=r.last_name||'',sfx=r.suffix?' '+r.suffix:'';
    const fullName=`${fname} ${mname}${lname}${sfx}`.replace(/\s+/g,' ').trim();
    const fullNameUC=fullName.toUpperCase();
    const age=r.birthdate?certCalcAge(r.birthdate):'—';
    const address=r.perm_address?`${r.perm_address}, Barangay 410, Tondo, Manila City`:'Barangay 410, Tondo, Manila City';
    const years=r.years_in_barangay||'';
    const civil=r.marital_status||'', gender=r.gender||'';

    const months=['January','February','March','April','May','June','July','August','September','October','November','December'];
    const d=new Date(issueDate);
    const dateStr=`${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
    const now=new Date();
    const ctrl=`CERT-${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}-${String(now.getHours()).padStart(2,'0')}${String(now.getMinutes()).padStart(2,'0')}`;

    const bodies={
      clearance:`To Whom It May Concern:<br><br>This is to certify that <span class="res-name">${fullNameUC}</span>, ${age} years old, ${civil}, ${gender}, residing at <strong>${address}</strong>, is a bonafide resident of this barangay and is known to be a person of good moral character and has no pending case or derogatory record on file in this office.<br><br>This certification is issued upon the request of the above-named person for <strong>${purpose}</strong> and for whatever legal purpose it may serve.`,
      indigency:`To Whom It May Concern:<br><br>This is to certify that <span class="res-name">${fullNameUC}</span>, ${age} years old, ${civil}, residing at <strong>${address}</strong>, is personally known to this office and is certified to be a member of an <strong>indigent family</strong> who is in need of financial assistance.<br><br>This certification is issued upon the request of the above-named person for <strong>${purpose}</strong> and for whatever legal purpose it may serve.`,
      residency:`To Whom It May Concern:<br><br>This is to certify that <span class="res-name">${fullNameUC}</span>, ${age} years old, ${civil}, ${gender}, has been a bonafide resident of <strong>${address}</strong>${years?' for '+years+' year(s)':''} and is duly recorded in the Registry of Barangay Residents of this office.<br><br>This certification is issued upon the request of the above-named person for <strong>${purpose}</strong> and for whatever legal purpose it may serve.`,
      good_moral:`To Whom It May Concern:<br><br>This is to certify that <span class="res-name">${fullNameUC}</span>, ${age} years old, ${civil}, residing at <strong>${address}</strong>, is personally known to us and is a person of <strong>good moral character and good standing</strong> in the community, and has no derogatory record on file in this office as of this date.<br><br>This certification is issued upon the request of the above-named person for <strong>${purpose}</strong> and for whatever legal purpose it may serve.`,
    };

    document.getElementById('certTitle').textContent=ct.title;
    document.getElementById('certBody').innerHTML=bodies[certSelType];
    document.getElementById('certIssued').innerHTML=`Issued this <strong>${dateStr}</strong> at the Office of Barangay 410, Tondo, Manila City.`;
    document.getElementById('certSigNameDisp').textContent=sigName;
    document.getElementById('certSigTitleDisp').textContent=sigTitle;
    document.getElementById('certControlNo').textContent=ctrl;
    document.getElementById('certPreviewEmpty').style.display='none';
    document.getElementById('cert-output').style.display='block';
    document.getElementById('cert-output').scrollIntoView({behavior:'smooth'});
  }

  function certSaveSignatory(){
    const name=document.getElementById('certSigName').value;
    const title=document.getElementById('certSigTitle').value;
    if(!name){alert('Enter a signatory name first.');return;}
    document.getElementById('saveCertSigName').value=name;
    document.getElementById('saveCertSigTitle').value=title;
    document.querySelector('form[action="?tab=certificates"]').submit();
  }
  function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
  document.getElementById('menuToggle').addEventListener('click',openSidebar);
  function openSettings(){}
</script>
</body>
</html>