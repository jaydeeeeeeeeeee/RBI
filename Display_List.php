<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin.php"); exit(); }
include 'Residents_DB.php';
include 'role_helper.php';
if($is_guest){ header("Location: Home.php?denied=residents"); exit(); }
include 'generate_id.php';

$ip     = $_SERVER['REMOTE_ADDR'];
$ip_esc = $conn->real_escape_string($ip);

// Remove expired lockouts — also catches any corrupted locked_until values
$conn->query("DELETE FROM failed_attempts WHERE
    (locked_until IS NOT NULL AND locked_until < NOW())
    OR last_attempt < NOW() - INTERVAL 10 MINUTE");

// ── Check gate lockout status — let MySQL compute seconds to avoid timezone mismatch ──
$gateLocked    = false;
$gateSecsLeft  = 0;
$lr = $conn->query("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS secs_left FROM failed_attempts WHERE ip_address='$ip_esc' LIMIT 1");
if($lr && $lr->num_rows > 0){
    $secs = (int)$lr->fetch_assoc()['secs_left'];
    if($secs > 0){
        $gateLocked   = true;
        $gateSecsLeft = $secs;
    }
}

// ── Check list unlock (expires in 30 mins) ────────────────────────────────────
$list_unlocked = isset($_SESSION['list_unlocked'])
    && isset($_SESSION['list_unlock_time'])
    && (time() - $_SESSION['list_unlock_time']) < 1800  // 30 min
    && ($_SESSION['list_unlock_ip'] ?? '') === $ip;

// Lock out if IP changed (session hijack attempt)
if (isset($_SESSION['list_unlock_ip']) && $_SESSION['list_unlock_ip'] !== $ip) {
    logSecurityEvent($conn, 'IP_MISMATCH', "IP changed from {$_SESSION['list_unlock_ip']} to $ip during active session", $ip);
    session_destroy();
    header("Location: admin.php?alert=security");
    exit();
}

// ── Only process data if unlocked ─────────────────────────────────────────────
$families     = [];
$total        = 0;
$total_all    = 0;
$map_residents= [];
$view_mode    = $_GET['view'] ?? 'family';
$search       = $_GET['search'] ?? '';
$f_gender     = $_GET['gender'] ?? '';
$f_marital    = $_GET['marital_status'] ?? '';
$show_hidden  = isset($_GET['show_hidden']) && $_GET['show_hidden'] == '1';

if ($list_unlocked || $is_guest) {
    // Ensure resident_code column exists (safe migration)
    ensureResidentCodeColumn($conn);
    // Ensure geocoding columns exist
    $conn->query("ALTER TABLE residents ADD COLUMN IF NOT EXISTS lat DECIMAL(10,7) DEFAULT NULL");
    $conn->query("ALTER TABLE residents ADD COLUMN IF NOT EXISTS lng DECIMAL(10,7) DEFAULT NULL");

    // Remove duplicates
    $columns = [];
    $result  = mysqli_query($conn, "SHOW COLUMNS FROM residents");
    while ($row = mysqli_fetch_assoc($result)) {
        if (!in_array($row['Field'], ['id','created_at','resident_code','family_code'])) $columns[] = $row['Field'];
    }
    if (!empty($columns)) {
        $conds  = array_map(fn($c) => "t1.$c<=>t2.$c", $columns);
        $del    = "DELETE t1 FROM residents t1 INNER JOIN residents t2 WHERE t1.id>t2.id AND ".implode(" AND ",$conds);
        mysqli_query($conn, $del);
    }

    // Build WHERE
    $where = [];
    if ($f_gender)  $where[] = "gender='".mysqli_real_escape_string($conn,$f_gender)."'";
    if ($f_marital) $where[] = "marital_status='".mysqli_real_escape_string($conn,$f_marital)."'";
    if (!$show_hidden) $where[] = "is_hidden=0";
    if (!empty($search)) {
        $se = mysqli_real_escape_string($conn, $search);
        $where[] = "(first_name LIKE '%$se%' OR middle_name LIKE '%$se%' OR last_name LIKE '%$se%' OR perm_address LIKE '%$se%' OR resident_code LIKE '%$se%')";
    }
    $wsql = $where ? "WHERE ".implode(" AND ",$where) : "";

    // Fetch all residents
    $rr = mysqli_query($conn, "SELECT * FROM residents $wsql ORDER BY head_last_name, head_first_name, head_of_family DESC, last_name, first_name");
    $total_all = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM residents WHERE is_hidden=0"))['c'];

    // Group into families
    $all_rows = [];
    while ($r = mysqli_fetch_assoc($rr)) $all_rows[] = $r;
    $total = count($all_rows);

    // Family grouping: heads first, then members under their head
    foreach ($all_rows as $r) {
        if ($r['head_of_family'] === 'Yes' || $r['head_of_family'] === '') {
            $fkey = strtolower(trim($r['first_name'].' '.$r['last_name']));
            if (!isset($families[$fkey])) $families[$fkey] = ['head'=>null,'members'=>[]];
            $families[$fkey]['head'] = $r;
        }
    }
    foreach ($all_rows as $r) {
        if ($r['head_of_family'] === 'No') {
            $hkey = strtolower(trim($r['head_first_name'].' '.$r['head_last_name']));
            if (isset($families[$hkey])) {
                $families[$hkey]['members'][] = $r;
            } else {
                // No head found — show as standalone
                $fkey = 'orphan_'.$r['id'];
                $families[$fkey] = ['head'=>$r,'members'=>[]];
            }
        }
    }

    // Map data
    foreach ($all_rows as $r) {
        $map_residents[] = [
            'id'      => $r['id'],
            'name'    => $r['first_name'].' '.$r['last_name'],
            'gender'  => $r['gender'],
            'address' => $r['perm_address'],
            'marital' => $r['marital_status'],
            'code'    => ($r['resident_code'] ?? ''),
            'age'     => $r['birthdate'] ? (new DateTime())->diff(new DateTime($r['birthdate']))->y : 'N/A',
            'lat'     => (!empty($r['lat']))  ? (float)$r['lat'] : null,
            'lng'     => (!empty($r['lng']))  ? (float)$r['lng'] : null,
        ];
    }

    // Pets
    $pets_by = [];
    $pr = mysqli_query($conn,"SELECT * FROM pets");
    while ($p = mysqli_fetch_assoc($pr)) $pets_by[$p['resident_id']][] = $p;
}

function calcAge($bd){if(!$bd)return'N/A';return(new DateTime())->diff(new DateTime($bd))->y;}
function v2($r,$k){return htmlspecialchars($r[$k]??'');}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Residents – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<style>
main{padding:1.5rem;max-width:1400px;margin:0 auto}

/* ── PASSWORD GATE ── */
.gate-wrap{display:flex;align-items:center;justify-content:center;min-height:60vh}
.gate-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:2.5rem;max-width:420px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.06)}
.gate-icon{width:70px;height:70px;background:linear-gradient(135deg,#0f172a,#1e40af);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:26px;color:#fff}
.gate-title{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:#0f172a;margin-bottom:6px}
.gate-sub{font-size:13px;color:#64748b;margin-bottom:1.5rem;line-height:1.6}
.gate-input-wrap{position:relative;margin-bottom:12px}
.gate-input-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px}
.gate-input{width:100%;padding:12px 14px 12px 40px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:'Inter',sans-serif;color:#0f172a;outline:none;transition:border .2s}
.gate-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.gate-error{background:#fff1f2;border:1px solid #fecdd3;color:#be123c;padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:12px;display:none;text-align:left}
.gate-hint{font-size:11px;color:#94a3b8;margin-top:14px}

/* ── VIEW TOGGLE ── */
.view-toggle{display:flex;background:#e2e8f0;border:1px solid #cbd5e1;border-radius:10px;padding:3px;gap:2px}
.vt-btn{padding:7px 18px;border-radius:7px;border:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;color:#64748b;background:none;display:flex;align-items:center;gap:7px;transition:all .2s;white-space:nowrap}
.vt-btn:hover:not(.active){color:#0f172a;background:rgba(255,255,255,.6)}
.vt-btn.active{background:#0f172a;color:#fff !important;box-shadow:0 2px 6px rgba(0,0,0,.18)}
.unlock-info{display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.6);background:rgba(255,255,255,.08);padding:5px 12px;border-radius:20px;border:1px solid rgba(255,255,255,.12)}
.unlock-dot{width:6px;height:6px;border-radius:50%;background:#22c55e}

/* ── TOOLBAR ── */
.toolbar-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem}
.search-wrap{position:relative}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px}
.search-input{width:100%;min-width:180px;padding:9px 12px 9px 36px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;color:#0f172a;outline:none;transition:border .2s}
.search-input:focus{border-color:#3b82f6}
.filter-sel{padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;color:#0f172a;outline:none}
.filter-sel:focus{border-color:#3b82f6}
.admin-tools-wrap{margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;display:none}
.stats-bar{display:flex;gap:8px;flex-wrap:wrap}
.stat-pill{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:6px 14px;font-size:12px;display:flex;align-items:center;gap:7px}
.stat-pill .n{font-weight:700;color:#0f172a}

/* ── FAMILY TABLE ── */
.family-block{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:10px;overflow:hidden}
.family-head-row{display:flex;align-items:center;gap:14px;padding:13px 18px;cursor:pointer;transition:background .15s;user-select:none}
.family-head-row:hover{background:#f8fafc}
.family-head-row.is-hidden-row{opacity:.65;background:#f8fafc}
.fav{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.av-m{background:#eff6ff;color:#1d4ed8}.av-f{background:#fdf4ff;color:#7e22ce}.av-o{background:#f0fdf4;color:#15803d}
.f-name{font-size:14px;font-weight:700;color:#0f172a}
.f-code{font-size:11px;font-family:monospace;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:20px;margin-left:8px}
.f-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:3px}
.f-meta span{font-size:11px;color:#64748b;display:flex;align-items:center;gap:4px}
.f-badge{font-size:10px;font-weight:700;background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:20px}
.f-chevron{margin-left:auto;color:#94a3b8;font-size:12px;transition:transform .3s;flex-shrink:0}
.family-block.expanded .f-chevron{transform:rotate(180deg)}

/* Member rows (inside family) */
.family-body{display:none;border-top:1px solid #f1f5f9}
.member-row{display:flex;align-items:center;gap:12px;padding:10px 18px 10px 52px;border-bottom:1px solid #f9fafb;cursor:pointer;background:#fafbfc;transition:background .15s}
.member-row:last-of-type{border-bottom:none}
.member-row:hover{background:#f1f5f9}
.m-av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;border:2px solid #e2e8f0}
.m-name{font-size:13px;font-weight:600;color:#0f172a}
.m-rel{font-size:11px;color:#94a3b8;margin-left:6px}
.m-meta{display:flex;gap:8px;margin-top:2px;flex-wrap:wrap}
.m-meta span{font-size:11px;color:#64748b;display:flex;align-items:center;gap:3px}

/* Detail panel (shared for head and members) */
.detail-panel{display:none;padding:1.25rem 1.5rem;background:#f8fafc;border-top:1px solid #e2e8f0}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:1rem}
.dg label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;display:block;margin-bottom:3px}
.dg p{font-size:13px;color:#0f172a;font-weight:500}
.dhr{border:none;border-top:1px solid #e2e8f0;margin:.75rem 0}
.pet-tag{display:inline-flex;align-items:center;gap:5px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;margin:2px}
.card-actions{display:flex;gap:8px;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #e2e8f0}
.hidden-badge{background:#f1f5f9;color:#64748b;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:6px}

/* ── MAP ── */
#mapView{display:none}
#residentMap{height:520px;border-radius:12px;border:1px solid #e2e8f0}
.map-legend{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem 1rem;margin-top:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;font-size:12px}
.leg-dot{width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.15);display:inline-block;margin-right:5px}

/* ── MODALS ── */
.modal{display:flex;align-items:center;justify-content:center;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;visibility:hidden;opacity:0;transition:opacity .3s,visibility .3s}
.modal.show{visibility:visible;opacity:1}
.modal-inner{background:#fff;border-radius:14px;padding:2rem;width:100%;max-width:400px}
.modal-inner h3{font-size:16px;font-weight:700;margin-bottom:1rem;color:#0f172a}
.modal-pw{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:9px;font-size:14px;font-family:'Inter',sans-serif;margin:.75rem 0;outline:none}
.modal-pw:focus{border-color:#3b82f6}
.modal-err{color:#f43f5e;font-size:12px;display:none}

footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem 2rem;letter-spacing:.02em}

@media print{
  /* Hide all UI chrome */
  .topbar,.sidebar,.sidebar-overlay,#settingsOverlay,#settingsDrawer,
  .modal,.gate-wrap,.toolbar-card,.stats-bar,.view-toggle,
  button,footer,
  .card-actions,.admin-tools-wrap,#mapView{display:none!important}

  /* Remove page margin/padding */
  body{margin:0;padding:0;background:#fff!important;color:#000!important}
  main{padding:.5rem!important;max-width:100%!important}

  /* Print header */
  #familyView::before{
    content:"Barangay 410 — Registered Residents";
    display:block;font-size:16px;font-weight:700;
    border-bottom:2px solid #0f172a;padding-bottom:6px;margin-bottom:12px;
  }

  /* Keep family cards readable */
  .family-block{border:1px solid #ccc!important;border-radius:4px!important;break-inside:avoid;margin-bottom:6px!important}
  .family-head-row{background:#f3f4f6!important}
  .f-name{font-size:13px!important}
  .f-meta span,.m-meta span{font-size:11px!important}
  .family-body{display:block!important}
  .member-row{border-bottom:1px solid #eee!important}
  .detail-panel{display:none!important}

  /* Force all family blocks to expand */
  .family-block{page-break-inside:avoid}
}

/* ── PASSWORD EYE TOGGLE ── */
.pw-wrap{position:relative;display:flex;align-items:center}
.pw-wrap input{padding-right:40px !important;flex:1}
.pw-eye{position:absolute;right:12px;background:none;border:none;cursor:pointer;
  color:#94a3b8;font-size:14px;padding:4px;transition:color .2s;z-index:2}
.pw-eye:hover{color:#3b82f6}
.topbar{position:sticky;top:0;z-index:200}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div>
  </a>
  <div class="topbar-right" style="margin-left:auto">
    <?php if($is_guest): ?>
    <div class="unlock-info" style="background:rgba(251,191,36,.1);border-color:rgba(251,191,36,.2)">
      <span class="unlock-dot" style="background:#fbbf24"></span> Guest Mode
    </div>
    <div title="Kagawad Viewer" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(148,163,184,.15);border:1px solid rgba(148,163,184,.3)">
      <i class="fas fa-eye" style="font-size:12px;color:#94a3b8"></i>
    </div>
    <?php elseif($list_unlocked): ?>
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain): ?>background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#fbbf24;<?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:#93c5fd;<?php else: ?>background:rgba(148,163,184,.15);border:1px solid rgba(148,163,184,.3);color:#94a3b8;<?php endif; ?>">
      <i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i>
    </div>
    <?php if($can_register): ?>
    <a href="Register.php" class="btn btn-primary" style="font-size:13px"><i class="fas fa-user-plus"></i> Register</a>
    <?php endif; ?>
    <?php else: ?>
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain): ?>background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#fbbf24;<?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:#93c5fd;<?php else: ?>background:rgba(148,163,184,.15);border:1px solid rgba(148,163,184,.3);color:#94a3b8;<?php endif; ?>">
      <i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i>
    </div>
    <?php endif; ?>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<!-- SIDEBAR -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div></div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <div style="padding:14px 12px 6px">
    <button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s">
      <i class="fas fa-gear"></i> Settings & More
      <i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i>
    </button>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($list_unlocked):?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment Borrowing</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<!-- ══ SETTINGS DRAWER ══ -->
<div id="settingsOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100" onclick="closeSettings()"></div>
<div id="settingsDrawer" style="position:fixed;top:0;right:-360px;width:340px;height:100vh;background:#0f172a;z-index:1101;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.08)">
  <div style="padding:20px 20px 14px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#3b82f6,#14b8a6);border-radius:8px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-gear" style="color:#fff;font-size:13px"></i>
      </div>
      <div>
        <div style="font-family:Syne,sans-serif;font-size:14px;font-weight:800;color:#fff">Settings</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4)">ProjectRBI Barangay 410</div>
      </div>
    </div>
    <button onclick="closeSettings()" style="width:28px;height:28px;background:rgba(255,255,255,.08);border:none;border-radius:7px;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:16px">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Appearance</div>
    <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-circle-half-stroke" style="color:#94a3b8;font-size:13px"></i></div>
        <div><div style="font-size:13px;font-weight:600;color:#fff">Dark Mode</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Toggle dark/light theme</div></div>
      </div>
      <button id="darkToggle" onclick="toggleDarkMode()" style="width:42px;height:24px;border-radius:12px;background:#475569;border:none;cursor:pointer;position:relative;transition:background .25s;flex-shrink:0">
        <span id="darkThumb" style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;transition:left .25s"></span>
      </button>
    </div>
    <?php if(!$is_guest): ?>
    <!-- DATA SECTION -->
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Residents Data</div>

    <!-- Show Archived toggle -->
    <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-eye" style="color:#94a3b8;font-size:13px"></i>
        </div>
        <div>
          <div style="font-size:13px;font-weight:600;color:#fff">Show Archived</div>
          <div style="font-size:11px;color:rgba(255,255,255,.4)">Display hidden residents</div>
        </div>
      </div>
      <a href="Display_List.php?<?=http_build_query(array_merge($_GET,['show_hidden'=>$show_hidden?'0':'1']))?>"
        style="width:42px;height:24px;border-radius:12px;background:<?=$show_hidden?'#3b82f6':'#475569'?>;display:flex;align-items:center;justify-content:flex-<?=$show_hidden?'end':'start'?>;padding:3px;text-decoration:none;transition:all .2s;flex-shrink:0">
        <span style="width:18px;height:18px;border-radius:50%;display:block"></span>
      </a>
    </div>

    <!-- Import CSV -->
    <div style="background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:30px;height:30px;background:rgba(59,130,246,.15);border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-upload" style="color:#60a5fa;font-size:13px"></i>
        </div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600;color:#fff">Import CSV</div>
          <div style="font-size:11px;color:rgba(255,255,255,.4)">Upload resident data file</div>
        </div>
        <a href="download_template.php" title="Download CSV template" style="color:#60a5fa;font-size:11px;text-decoration:none;background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);padding:3px 8px;border-radius:6px;white-space:nowrap;flex-shrink:0">
          <i class="fas fa-file-csv"></i> Template
        </a>
      </div>
      <form id="settingsImportForm" action="Import_excel.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label id="importFileLabel" style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.05);border:1px dashed rgba(255,255,255,.2);border-radius:7px;padding:8px 10px;margin-bottom:8px;cursor:pointer">
          <i class="fas fa-file-csv" style="color:#60a5fa;font-size:14px;flex-shrink:0"></i>
          <span id="importFileName" style="font-size:12px;color:rgba(255,255,255,.4);flex:1">No file chosen</span>
          <input type="file" name="csv_file" accept=".csv" id="importFileInput" style="display:none" onchange="onImportFileChange(this)">
        </label>
        <button type="button" onclick="openSettingsImportModal()" id="importBtn"
          style="width:100%;padding:8px;background:rgba(59,130,246,.2);border:1px solid rgba(59,130,246,.35);border-radius:7px;color:#60a5fa;font-family:Inter,sans-serif;font-size:12px;font-weight:600;cursor:pointer">
          <i class="fas fa-upload"></i> Import
        </button>
      </form>
    </div>

    <!-- Export Excel -->
    <div style="background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:30px;height:30px;background:rgba(34,197,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-download" style="color:#4ade80;font-size:13px"></i>
        </div>
        <div>
          <div style="font-size:13px;font-weight:600;color:#fff">Export CSV</div>
          <div style="font-size:11px;color:rgba(255,255,255,.4)"><?=$total_all?> resident<?=$total_all!=1?'s':''?> will be exported</div>
        </div>
      </div>
      <button onclick="openSettingsExportModal()" id="exportBtn"
        style="width:100%;padding:8px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);border-radius:7px;color:#4ade80;font-family:Inter,sans-serif;font-size:12px;font-weight:600;cursor:pointer">
        <i class="fas fa-download"></i> Export
      </button>
    </div>
    <?php endif; // !$is_guest data section ?>

    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Actions</div>
    <button onclick="window.print()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .2s" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='rgba(255,255,255,.05)'">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print List</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print current residents view</div></div>
    </button>
    <a href="signatory_settings.php" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(139,92,246,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-pen-nib" style="color:#a78bfa;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Signatory Settings</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Edit names on printed documents</div></div>
    </a>

  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div><div style="font-size:11px;color:rgba(244,63,94,.6)">End your session</div></div>
    </a>
  </div>
</div>

<!-- HERO -->
<div style="background:linear-gradient(to right,rgba(15,23,42,.85),rgba(15,23,42,.55)),url('images/Barangay_officials_410.png') center center/cover no-repeat;padding:2.5rem 2rem;min-height:300px;display:flex;align-items:center">
  <div style="max-width:1200px;width:100%">
    <h1 style="font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:#fff;margin:0 0 .35rem"><i class="fas fa-users" style="margin-right:.5rem;opacity:.8"></i>Residents List</h1>
    <?php if($is_guest || !$list_unlocked):?>
    <p style="color:rgba(255,255,255,.6);font-size:.84rem;margin:0">
      <?php if($is_guest):?>
        <i class="fas fa-eye" style="margin-right:5px;opacity:.7"></i>Guest mode — Kagaway viewer (limited access)
      <?php else:?>
        <i class="fas fa-lock" style="margin-right:5px;opacity:.7"></i>Password required to view resident data
      <?php endif;?>
    </p>
    <?php endif;?>
  </div>
</div>


<main>
<?php if(!$list_unlocked && !$is_guest): ?>
  <!-- ── PASSWORD GATE ── -->
  <div class="gate-wrap">
    <div class="gate-card">
      <div class="gate-icon"><i class="fas fa-shield-halved"></i></div>
      <div class="gate-title">Protected Resident Data</div>
      <div class="gate-sub">
        This section is restricted to authorized secretaries only.<br>
        Enter your admin password to access resident records.
      </div>
      <?php if($gateLocked): ?>
      <div style="display:flex;align-items:center;gap:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 14px;margin-bottom:14px;text-align:left">
        <div style="width:36px;height:36px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,#f97316,#ea580c);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px"><i class="fas fa-shield-halved"></i></div>
        <div>
          <div style="font-size:12px;font-weight:700;color:#9a3412;margin-bottom:2px">Too many failed attempts</div>
          <div style="font-size:11px;color:#c2410c">Please wait <span id="gateCountdown"><?=gmdate('i:s',$gateSecsLeft)?></span> before trying again.</div>
        </div>
      </div>
      <div class="gate-input-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" id="gatePw" class="gate-input" placeholder="Enter admin password" autocomplete="off" disabled style="background:#f1f5f9;color:#94a3b8;cursor:not-allowed">
      </div>
      <button class="btn btn-primary" style="width:100%;padding:12px;opacity:.45;cursor:not-allowed" disabled>
        <i class="fas fa-unlock"></i> &nbsp;Unlock Resident List
      </button>
      <?php else: ?>
      <div class="gate-error" id="gateErr"></div>
      <div class="gate-input-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" id="gatePw" class="gate-input" placeholder="Enter admin password" autocomplete="off">
      </div>
      <button class="btn btn-primary" style="width:100%;padding:12px" onclick="unlockList()">
        <i class="fas fa-unlock"></i> &nbsp;Unlock Resident List
      </button>
      <?php endif; ?>
      <div class="gate-hint"><i class="fas fa-info-circle"></i> Session expires after 30 minutes of inactivity.</div>
    </div>
  </div>

<?php else: ?>
  <?php if($is_guest): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 18px;margin-bottom:1rem;display:flex;align-items:flex-start;gap:12px;font-size:13px;color:#92400e">
    <i class="fas fa-eye" style="font-size:18px;flex-shrink:0;margin-top:1px"></i>
    <div>
      <strong>Guest Mode Active — Kagawad View</strong><br>
      <span style="font-size:12px;opacity:.85">You are viewing summarized resident information. Only resident names and IDs are displayed. For full resident details, please contact the <strong>Barangay Secretary</strong>.</span>
    </div>
  </div>
  <?php endif; ?>
  <!-- ── STATS + VIEW TOGGLE ROW ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:1rem">
    <div class="stats-bar" style="margin-bottom:0">
      <div class="stat-pill"><span class="n"><?=$total?></span><span style="color:#64748b">shown</span></div>
      <div class="stat-pill"><span class="n"><?=$total_all?></span><span style="color:#64748b">total residents</span></div>
      <div class="stat-pill"><span class="n"><?=count($families)?></span><span style="color:#64748b">households</span></div>
      <?php if($show_hidden):?><div class="stat-pill"><i class="fas fa-eye-slash" style="color:#94a3b8;font-size:11px"></i><span style="color:#64748b">Showing archived</span></div><?php endif;?>
    </div>
    <div class="view-toggle" style="display:inline-flex;flex-shrink:0">
      <button class="vt-btn <?=$view_mode!=='map'?'active':''?>" onclick="switchView('family')"><i class="fas fa-users"></i> Family</button>
      <button class="vt-btn <?=$view_mode==='map'?'active':''?>" onclick="switchView('map')"><i class="fas fa-map-marker-alt"></i> Map</button>
    </div>
  </div>

  <!-- ── ALERTS ── -->
  <?php if(isset($_GET['registered'])):?>
  <div class="alert alert-success" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> Resident registered successfully!</div>
  <?php endif;?>

  <?php if(isset($_GET['import'])): $_ir=$_GET['import']; $_ic=(int)($_GET['count']??0); $_if=(int)($_GET['failed']??0); ?>
    <?php if($_ir==='success'): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;display:flex;align-items:flex-start;gap:10px">
      <i class="fas fa-check-circle" style="font-size:16px;flex-shrink:0;margin-top:1px"></i>
      <div>
        <strong>Import successful</strong> — <strong><?=$_ic?></strong> resident<?=($_ic!=1?'s':'')?> added.
        <?php if($_if>0):?><br><span style="font-size:12px;color:#166534;opacity:.8"><?=$_if?> row<?=($_if!=1?'s':'')?> skipped (missing name or invalid data).</span><?php endif;?>
        <?php if(isset($_GET['duplicates_removed'])):?><br><span style="font-size:12px;opacity:.8">Duplicate entries were automatically removed.</span><?php endif;?>
      </div>
    </div>
    <?php elseif($_ir==='error'): $_reason=$_GET['reason']??''; ?>
    <div class="alert" style="margin-bottom:1rem;background:#fff1f2;border:1px solid #fecdd3;color:#be123c;display:flex;align-items:flex-start;gap:10px;border-radius:10px;padding:12px 16px">
      <i class="fas fa-circle-exclamation" style="font-size:16px;flex-shrink:0;margin-top:1px"></i>
      <div>
        <strong>Import failed</strong> —
        <?php if($_reason==='wrongtype'): ?>The file must be a <strong>.csv</strong> file. Please export your spreadsheet as CSV first.
        <?php elseif($_reason==='toobig'): ?>File is too large (max 5 MB). Try splitting the data into smaller batches.
        <?php elseif($_reason==='nofile'): ?>No file was received. Please select a CSV file and try again.
        <?php elseif($_reason==='allskipped'): ?>All <?=(int)($_GET['failed']??0)?> rows were skipped. Make sure your CSV has <strong>First Name</strong> and <strong>Last Name</strong> columns. <a href="download_template.php" style="color:#be123c;font-weight:700">Download the template</a> for the correct format.
        <?php else: ?>Something went wrong. Please try again or <a href="download_template.php" style="color:#be123c;font-weight:700">download the template</a> to verify your file format.
        <?php endif;?>
      </div>
    </div>
    <?php endif;?>
  <?php endif;?>

  <!-- ── TOOLBAR ── -->
  <div class="toolbar-card">
    <form method="GET" action="Display_List.php" id="filterForm">
      <input type="hidden" name="view" id="viewInput" value="<?=htmlspecialchars($view_mode)?>">
      <!-- Row 1: Search bar -->
      <div style="display:flex;gap:8px;margin-bottom:10px">
        <div class="search-wrap" style="flex:1">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="search-input" placeholder="Search name, address, resident ID..." value="<?=htmlspecialchars($search)?>">
        </div>
      </div>
      <!-- Row 2: Filters + buttons -->
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <select name="gender" class="filter-sel" onchange="this.form.submit()" style="flex:1;min-width:130px">
          <option value="">All Genders</option>
          <option value="Male"   <?=$f_gender==='Male'  ?'selected':''?>>Male</option>
          <option value="Female" <?=$f_gender==='Female'?'selected':''?>>Female</option>
        </select>
        <select name="marital_status" class="filter-sel" onchange="this.form.submit()" style="flex:1;min-width:150px">
          <option value="">All Marital Status</option>
          <option value="Single"  <?=$f_marital==='Single' ?'selected':''?>>Single</option>
          <option value="Married" <?=$f_marital==='Married'?'selected':''?>>Married</option>
          <option value="Widowed" <?=$f_marital==='Widowed'?'selected':''?>>Widowed</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
        <a href="Display_List.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left"></i> Reset</a>
      </div>
      </form>
  </div>

  <!-- ── FAMILY VIEW ── -->
  <div id="familyView" style="display:<?=$view_mode==='map'?'none':'block'?>">
    <?php if(empty($families)):?>
    <div style="text-align:center;padding:3rem;color:#94a3b8">
      <i class="fas fa-users" style="font-size:48px;opacity:.25;display:block;margin-bottom:12px"></i>
      <p>No residents found.</p>
      <a href="Display_List.php" class="btn btn-outline btn-sm" style="margin-top:12px">Clear Filters</a>
    </div>
    <?php else: foreach($families as $fkey => $family):
      $head    = $family['head'];
      $members = $family['members'];
      if(!$head) continue;
      $hInit   = strtoupper(substr($head['first_name'],0,1).substr($head['last_name'],0,1));
      $hAv     = strtolower($head['gender']??'')==='female'?'av-f':(strtolower($head['gender']??'')==='male'?'av-m':'av-o');
      $hAge    = calcAge($head['birthdate']);
      $hCode   = ($head['resident_code'] ?? '') ?: '—';
      $famCount= count($members);
      // Safe ID: use head's DB id instead of name string (no spaces/special chars)
      $safe_id = 'f'.$head['id'];
    ?>
    <div class="family-block <?=(!$is_guest&&$head['is_hidden'])?'expanded':''?>" id="fam-<?=$safe_id?>">
      <!-- HEAD ROW -->
      <?php if($is_guest): ?>
      <div class="family-head-row <?=$head['is_hidden']?'is-hidden-row':''?>" style="cursor:default" title="Contact the Barangay Secretary for full details">
        <div class="fav <?=$hAv?>"><?=$hInit?></div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;flex-wrap:wrap;gap:4px">
            <span class="f-name"><?=v2($head,'first_name').' '.v2($head,'last_name')?></span>
            <span class="f-code"><?=$hCode?></span>
            <span class="f-badge">Head of Family</span>
            <?php if($famCount>0):?><span style="font-size:10px;background:#f0fdf4;color:#15803d;padding:2px 8px;border-radius:20px;font-weight:700"><?=$famCount?> member<?=$famCount>1?'s':''?></span><?php endif;?>
          </div>
        </div>
        <i class="fas fa-lock" style="color:#cbd5e1;font-size:12px;flex-shrink:0" title="Full details restricted"></i>
      </div>
      <!-- Guest: show members name+code only, no body -->
      <?php if($famCount>0): ?>
      <div style="border-top:1px solid #f1f5f9">
        <?php foreach($members as $mem):
          $mInit = strtoupper(substr($mem['first_name'],0,1).substr($mem['last_name'],0,1));
          $mAv   = strtolower($mem['gender']??'')==='female'?'av-f':(strtolower($mem['gender']??'')==='male'?'av-m':'av-o');
          $mCode = ($mem['resident_code'] ?? '') ?: '—';
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:8px 18px 8px 52px;border-bottom:1px solid #f9fafb;background:#fafbfc">
          <div class="m-av <?=$mAv?>"><?=$mInit?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:4px">
              <span class="m-name"><?=v2($mem,'first_name').' '.v2($mem,'last_name')?></span>
              <span style="font-size:10px;font-family:monospace;background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:20px"><?=$mCode?></span>
            </div>
          </div>
          <i class="fas fa-lock" style="color:#cbd5e1;font-size:11px;flex-shrink:0"></i>
        </div>
        <?php endforeach; ?>
        <div style="padding:8px 18px 8px 52px;font-size:11px;color:#b45309;background:#fffbeb;border-top:1px dashed #fde68a;display:flex;align-items:center;gap:6px">
          <i class="fas fa-circle-info"></i> For full details, please contact the <strong style="margin-left:3px">Barangay Secretary</strong>.
        </div>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="family-head-row <?=$head['is_hidden']?'is-hidden-row':''?>" onclick="toggleFamily('<?=$safe_id?>',<?=$head['id']?>)">
        <div class="fav <?=$hAv?>"><?=$hInit?></div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;flex-wrap:wrap;gap:4px">
            <span class="f-name"><?=v2($head,'first_name').' '.v2($head,'middle_name').' '.v2($head,'last_name').($head['suffix']?' '.v2($head,'suffix'):'')?></span>
            <span class="f-code"><?=$hCode?></span>
            <span class="f-badge">Head of Family</span>
            <?php if($famCount>0):?><span style="font-size:10px;background:#f0fdf4;color:#15803d;padding:2px 8px;border-radius:20px;font-weight:700"><?=$famCount?> member<?=$famCount>1?'s':''?></span><?php endif;?>
            <?php if($head['is_hidden']):?><span class="hidden-badge">Archived</span><?php endif;?>
          </div>
          <div class="f-meta">
            <span><i class="fas fa-birthday-cake"></i> <?=v2($head,'birthdate')?> (<?=$hAge?> yrs)</span>
            <span><i class="fas fa-venus-mars"></i> <?=v2($head,'gender')?></span>
            <span><i class="fas fa-ring"></i> <?=v2($head,'marital_status')?></span>
            <span><i class="fas fa-flag"></i> <?=v2($head,'citizenship')?></span>
            <span><i class="fas fa-calendar"></i> <?=date('M d, Y',strtotime($head['created_at']))?></span>
          </div>
        </div>
        <i class="fas fa-chevron-down f-chevron"></i>
      </div>
      <?php endif; // end guest/non-guest head row ?>

      <!-- FAMILY BODY (head details + members) — hidden from guests -->
      <?php if(!$is_guest): ?>
      <div class="family-body" id="fb-<?=$safe_id?>">

        <!-- Head Detail Panel -->
        <div class="detail-panel" id="dp-h-<?=$head['id']?>" style="background:#eff6ff;border-left:3px solid #3b82f6">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#1d4ed8;margin-bottom:1rem;display:flex;align-items:center;gap:7px">
            <i class="fas fa-house-user"></i> Head of Family Details
          </div>
          <?php ob_start(); ?>
          <div class="detail-grid">
            <div class="dg"><label>Resident ID</label><p style="font-family:monospace;font-size:12px;color:#3b82f6"><?=$hCode?></p></div>
            <div class="dg"><label>Contact</label><p><?=v2($head,'mobile')?><?=$head['landline']?' | '.v2($head,'landline'):''?></p></div>
            <div class="dg"><label>Email</label><p><?=v2($head,'email')?:'-'?></p></div>
            <div class="dg"><label>Permanent Address</label><p><?=v2($head,'perm_address')?></p></div>
            <div class="dg"><label>House Owner</label><p><?=v2($head,'house_owner')?><?=$head['house_owner']==='No'?' ('.v2($head,'house_details').')':''?></p></div>
            <div class="dg"><label>Years in Barangay</label><p><?=v2($head,'years_in_barangay')?> years</p></div>
            <div class="dg"><label>Voter</label><p><?=v2($head,'voter')?><?=$head['voter']==='Yes'?' – Precinct '.v2($head,'precinct_no'):''?></p></div>
            <div class="dg"><label>Religion</label><p><?=v2($head,'religion')?></p></div>
          </div>
          <hr class="dhr">
          <div class="detail-grid">
            <div class="dg"><label>Education</label><p><?=v2($head,'education')?:'-'?></p></div>
            <div class="dg"><label>Employment</label><p><?=v2($head,'employment_status')?><?=$head['employment_status']==='Employed'?' – '.v2($head,'occupation'):''?></p></div>
            <div class="dg"><label>Senior</label><p><?=v2($head,'is_senior')?></p></div>
            <div class="dg"><label>PWD</label><p><?=v2($head,'pwd_status')?><?=$head['pwd_status']==='Yes'?' – '.v2($head,'disability_type'):''?></p></div>
            <div class="dg"><label>Solo Parent</label><p><?=v2($head,'solo_parent_status')?></p></div>
          </div>
          <?php if(!empty($pets_by[$head['id']])):?>
          <hr class="dhr">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:6px">Pets</div>
          <?php foreach($pets_by[$head['id']] as $p):?><span class="pet-tag"><i class="fas fa-paw"></i> <?=htmlspecialchars($p['pet_name'])?> (<?=$p['pet_type']?>)</span><?php endforeach;?>
          <?php endif;?>
          <div class="card-actions">
            <?php if($head['is_hidden']):?>
              <button class="btn btn-success btn-sm" onclick="location.href='unhide.php?id=<?=$head['id']?>'"><i class="fas fa-eye"></i> Unarchive</button>
              <button class="btn btn-danger btn-sm" onclick="openDelModal(<?=$head['id']?>)"><i class="fas fa-trash"></i> Delete</button>
            <?php else:?>
              <?php if($can_edit): ?>
              <button class="btn btn-primary btn-sm" onclick="location.href='Edit.php?id=<?=$head['id']?>'"><i class="fas fa-pen"></i> Edit Head</button>
              <?php endif; ?>
              <button class="btn btn-outline btn-sm" onclick="location.href='data_tracking.php?tab=cert_requests&prefill_id=<?=$head['id']?>&prefill_name=<?=urlencode(trim($head['first_name'].' '.$head['last_name']))?>'"><i class="fas fa-file-alt"></i> Request Cert.</button>
              <button class="btn btn-outline btn-sm" onclick="printRes(<?=$head['id']?>)"><i class="fas fa-print"></i> Print</button>
              <?php if($can_edit): ?>
              <button class="btn btn-danger btn-sm" style="color:#92400e;background:#fffbeb;border-color:#fde68a" onclick="if(confirm('Archive this head of family?'))location.href='Delete.php?id=<?=$head['id']?>'"><i class="fas fa-archive"></i> Archive</button>
              <button class="btn btn-danger btn-sm" onclick="openDelModal(<?=$head['id']?>)"><i class="fas fa-trash"></i> Delete</button>
              <?php endif; ?>
            <?php endif;?>
          </div>
          <?php $headDetailHtml = ob_get_clean(); echo $headDetailHtml; ?>
        </div><!-- end head detail -->

        <!-- MEMBER ROWS -->
        <?php foreach($members as $mem):
          $mInit = strtoupper(substr($mem['first_name'],0,1).substr($mem['last_name'],0,1));
          $mAv   = strtolower($mem['gender']??'')==='female'?'av-f':(strtolower($mem['gender']??'')==='male'?'av-m':'av-o');
          $mAge  = calcAge($mem['birthdate']);
          $mCode = ($mem['resident_code'] ?? '') ?? '—';
        ?>
        <div class="member-row" onclick="toggleMemberDetail('dp-m-<?=$mem['id']?>')">
          <div class="m-av <?=$mAv?>"><?=$mInit?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:4px">
              <span class="m-name"><?=v2($mem,'first_name').' '.v2($mem,'middle_name').' '.v2($mem,'last_name')?></span>
              <span class="m-rel">(<?=v2($mem,'relationship')?>)</span>
              <span style="font-size:10px;font-family:monospace;background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:20px"><?=$mCode?></span>
              <?php if($mem['is_hidden']):?><span class="hidden-badge">Archived</span><?php endif;?>
            </div>
            <div class="m-meta">
              <span><i class="fas fa-birthday-cake"></i> <?=v2($mem,'birthdate')?> (<?=$mAge?> yrs)</span>
              <span><i class="fas fa-venus-mars"></i> <?=v2($mem,'gender')?></span>
              <span><i class="fas fa-ring"></i> <?=v2($mem,'marital_status')?></span>
            </div>
          </div>
          <i class="fas fa-chevron-right" style="color:#94a3b8;font-size:11px;flex-shrink:0"></i>
        </div>
        <!-- Member Detail Panel -->
        <div class="detail-panel" id="dp-m-<?=$mem['id']?>" style="padding-left:52px">
          <div class="detail-grid">
            <div class="dg"><label>Resident ID</label><p style="font-family:monospace;font-size:12px;color:#3b82f6"><?=$mCode?></p></div>
            <div class="dg"><label>Contact</label><p><?=v2($mem,'mobile')?><?=$mem['landline']?' | '.v2($mem,'landline'):''?></p></div>
            <div class="dg"><label>Email</label><p><?=v2($mem,'email')?:'-'?></p></div>
            <div class="dg"><label>Address</label><p><?=v2($mem,'perm_address')?></p></div>
            <div class="dg"><label>Education</label><p><?=v2($mem,'education')?:'-'?></p></div>
            <div class="dg"><label>Employment</label><p><?=v2($mem,'employment_status')?></p></div>
            <div class="dg"><label>Voter</label><p><?=v2($mem,'voter')?></p></div>
            <div class="dg"><label>PWD</label><p><?=v2($mem,'pwd_status')?></p></div>
          </div>
          <?php if(!empty($pets_by[$mem['id']])):?>
          <hr class="dhr">
          <?php foreach($pets_by[$mem['id']] as $p):?><span class="pet-tag"><i class="fas fa-paw"></i> <?=htmlspecialchars($p['pet_name'])?></span><?php endforeach;?>
          <?php endif;?>
          <div class="card-actions">
            <?php if($mem['is_hidden']):?>
              <button class="btn btn-success btn-sm" onclick="location.href='unhide.php?id=<?=$mem['id']?>'"><i class="fas fa-eye"></i> Unarchive</button>
            <?php else:?>
              <?php if($can_edit): ?>
              <button class="btn btn-outline btn-sm" onclick="location.href='Edit.php?id=<?=$mem['id']?>'"><i class="fas fa-pen"></i> Edit</button>
              <?php endif; ?>
              <button class="btn btn-outline btn-sm" onclick="location.href='data_tracking.php?tab=cert_requests&prefill_id=<?=$mem['id']?>&prefill_name=<?=urlencode(trim($mem['first_name'].' '.$mem['last_name']))?>'"><i class="fas fa-file-alt"></i> Request Cert.</button>
              <button class="btn btn-outline btn-sm" onclick="printRes(<?=$mem['id']?>)"><i class="fas fa-print"></i> Print</button>
              <?php if($can_edit): ?>
              <button class="btn btn-danger btn-sm" style="color:#92400e;background:#fffbeb;border-color:#fde68a" onclick="if(confirm('Archive?'))location.href='Delete.php?id=<?=$mem['id']?>'"><i class="fas fa-archive"></i> Archive</button>
              <button class="btn btn-danger btn-sm" onclick="openDelModal(<?=$mem['id']?>)"><i class="fas fa-trash"></i> Delete</button>
              <?php endif; ?>
            <?php endif;?>
          </div>
        </div>
        <?php endforeach;?><!-- end members -->

      </div><!-- end family-body -->
      <?php endif; // end !$is_guest family-body ?>
    </div><!-- end family-block -->
    <?php endforeach; endif; ?>
  </div><!-- end familyView -->

  <!-- ── MAP VIEW ── -->
  <div id="mapView" style="display:<?=$view_mode==='map'?'block':'none'?>">

    <?php if(!$is_guest): ?>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;margin-bottom:10px;display:flex;align-items:center;flex-wrap:wrap;gap:10px">
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#475569">
        <i class="fas fa-location-dot" style="color:#3b82f6"></i>
        <span id="geocodeStats">Loading…</span>
      </div>
      <button id="geocodeBtn" onclick="startGeocode()" style="margin-left:auto;padding:7px 14px;background:#3b82f6;border:none;border-radius:8px;color:#fff;font-family:Inter,sans-serif;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap">
        <i class="fas fa-map-pin"></i> Geocode Addresses
      </button>
    </div>
    <div id="geocodeProgress" style="display:none;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;margin-bottom:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <span id="geocodeProgressText" style="font-size:12px;color:#475569">Starting…</span>
        <button onclick="stopGeocode()" style="background:none;border:none;color:#f43f5e;cursor:pointer;font-size:11px;font-weight:600"><i class="fas fa-stop"></i> Stop</button>
      </div>
      <div style="background:#f1f5f9;border-radius:4px;height:6px;overflow:hidden">
        <div id="geocodeBar" style="height:100%;background:#3b82f6;border-radius:4px;transition:width .3s;width:0%"></div>
      </div>
    </div>
    <?php endif; ?>

    <div id="residentMap"></div>

    <div class="map-legend">
      <strong style="color:#475569;margin-right:8px">Legend:</strong>
      <span><span class="leg-dot" style="background:#3b82f6"></span>Male</span>
      <span><span class="leg-dot" style="background:#ec4899"></span>Female</span>
      <span><span class="leg-dot" style="background:#94a3b8"></span>Other</span>
      <span><span class="leg-dot" style="background:#e2e8f0;border:1.5px dashed #94a3b8;box-shadow:none"></span>Not yet geocoded</span>
      <span style="margin-left:auto;color:#94a3b8"><?=count($map_residents)?> residents · Click any pin for details</span>
    </div>
  </div>

<?php endif; // end list_unlocked ?>
</main>

<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City · All rights reserved.</footer>

<!-- Export Modal -->
<div class="modal" id="exportModal">
  <div class="modal-inner">
    <h3><i class="fas fa-download" style="color:#22c55e;margin-right:8px"></i>Export Residents</h3>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#166534">
      <i class="fas fa-circle-info" style="margin-right:6px"></i>
      This will download a CSV file with <strong><?=$total_all?></strong> resident<?=($total_all!=1?'s':'')?> — all active records.
    </div>
    <p style="font-size:13px;color:#64748b;margin-bottom:.5rem">Enter your admin password to confirm.</p>
    <div class="pw-wrap"><input type="password" class="modal-pw" id="exportPw" placeholder="Admin password"><button type="button" class="pw-eye" onclick="togglePw('exportPw',this)"><i class="fas fa-eye"></i></button></div>
    <div class="modal-err" id="exportErr"></div>
    <div style="display:flex;gap:10px;margin-top:12px">
      <button class="btn btn-outline" style="flex:1" onclick="closeModal('exportModal')">Cancel</button>
      <button class="btn btn-primary" style="flex:1" id="exportConfirmBtn" onclick="doExport()"><i class="fas fa-download"></i> Download CSV</button>
    </div>
  </div>
</div>
<!-- Import Modal -->
<div class="modal" id="importModal">
  <div class="modal-inner">
    <h3><i class="fas fa-upload" style="color:#3b82f6;margin-right:8px"></i>Import CSV</h3>
    <div id="importFileInfo" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#1e40af">
      <i class="fas fa-file-csv" style="margin-right:6px"></i>
      <span id="importFileInfoText">No file selected</span>
    </div>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#92400e">
      <i class="fas fa-triangle-exclamation" style="margin-right:6px"></i>
      <strong>Note:</strong> Existing residents will not be overwritten. Only new rows are added. Rows missing First Name or Last Name will be skipped.
    </div>
    <p style="font-size:13px;color:#64748b;margin-bottom:.5rem">Enter your admin password to confirm.</p>
    <div class="pw-wrap"><input type="password" class="modal-pw" id="importPw" placeholder="Admin password"><button type="button" class="pw-eye" onclick="togglePw('importPw',this)"><i class="fas fa-eye"></i></button></div>
    <div class="modal-err" id="importErr"></div>
    <div style="display:flex;gap:10px;margin-top:12px">
      <button class="btn btn-outline" style="flex:1" onclick="closeModal('importModal')">Cancel</button>
      <button class="btn btn-primary" style="flex:1" id="importConfirmBtn" onclick="doImport()"><i class="fas fa-upload"></i> Confirm Import</button>
    </div>
  </div>
</div>
<!-- Delete Modal -->
<div class="modal" id="deleteModal">
  <div class="modal-inner">
    <h3 style="color:#f43f5e"><i class="fas fa-trash" style="margin-right:8px"></i>Permanently Delete</h3>
    <p style="font-size:13px;color:#64748b;margin-bottom:.5rem">This cannot be undone. Enter admin password.</p>
    <input type="hidden" id="delId">
    <input type="password" class="modal-pw" id="deletePw" placeholder="Admin password">
    <div class="modal-err" id="deleteErr">Incorrect password.</div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-outline" style="flex:1" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="btn btn-danger" style="flex:1;background:#f43f5e;color:#fff" onclick="doDelete()"><i class="fas fa-trash"></i> Delete</button>
    </div>
  </div>
</div>

<script>
const mapResidents=<?=json_encode($map_residents??[])?>;
const IS_GUEST=<?=$is_guest?'true':'false'?>;

// Sidebar
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSidebar();document.querySelectorAll('.modal.show').forEach(m=>m.classList.remove('show'));}});

// Password gate
function unlockList(){
  const pw=document.getElementById('gatePw')?.value;
  if(!pw)return;
  const fd=new FormData();fd.append('password',pw);fd.append('action','unlock');
  fetch('verify_secretary.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    if(res.ok){
      location.reload();
    } else if(res.reason==='locked'){
      // Disable the form and start countdown
      document.getElementById('gatePw').disabled=true;
      document.getElementById('gatePw').style.cssText='background:#f1f5f9;color:#94a3b8;cursor:not-allowed';
      const btn=document.querySelector('.gate-card .btn');
      btn.disabled=true;btn.style.opacity='.45';btn.style.cursor='not-allowed';
      const errEl=document.getElementById('gateErr');
      errEl.style.display='block';
      errEl.style.background='#fff7ed';errEl.style.borderColor='#fed7aa';errEl.style.color='#9a3412';
      var secs=5*60; // match verify_secretary.php 5 min lockout
      function fmt(s){var m=Math.floor(s/60),r=s%60;return(m<10?'0':'')+m+':'+(r<10?'0':'')+r;}
      errEl.innerHTML='<i class="fas fa-shield-halved"></i> Too many failed attempts. Try again in <strong id="gateCountdown">'+fmt(secs)+'</strong>.';
      var t=setInterval(function(){
        secs--;
        if(secs<=0){clearInterval(t);location.reload();}
        else{var cd=document.getElementById('gateCountdown');if(cd)cd.textContent=fmt(secs);}
      },1000);
    } else {
      const e=document.getElementById('gateErr');
      e.textContent=res.message;e.style.display='block';
      document.getElementById('gatePw').value='';
    }
  });
}
document.getElementById('gatePw')?.addEventListener('keydown',e=>{if(e.key==='Enter')unlockList();});

<?php if($gateLocked && $gateSecsLeft > 0): ?>
// Page loaded already locked — run countdown
(function(){
  var secs=<?=(int)$gateSecsLeft?>;
  function fmt(s){var m=Math.floor(s/60),r=s%60;return(m<10?'0':'')+m+':'+(r<10?'0':'')+r;}
  var t=setInterval(function(){
    secs--;
    if(secs<=0){clearInterval(t);location.reload();}
    else{var cd=document.getElementById('gateCountdown');if(cd)cd.textContent=fmt(secs);}
  },1000);
})();
<?php endif; ?>

// Family toggle
function toggleFamily(key, headId){
  const block=document.getElementById('fam-'+key);
  const body=document.getElementById('fb-'+key);
  if(!body)return;
  const open=body.style.display==='block';
  body.style.display=open?'none':'block';
  block.classList.toggle('expanded',!open);
  // Also open/close the head's detail panel together
  if(headId){
    const hdp=document.getElementById('dp-h-'+headId);
    if(hdp) hdp.style.display=open?'none':'block';
  }
}

// Member detail toggle
function toggleMemberDetail(id){
  const panel=document.getElementById(id);
  if(!panel)return;
  const open=panel.style.display==='block';
  panel.style.display=open?'none':'block';
}


// View switch
let mapInit=false;
function switchView(v){
  document.getElementById('viewInput').value=v;
  document.getElementById('familyView').style.display=v==='map'?'none':'block';
  document.getElementById('mapView').style.display=v==='map'?'block':'none';
  document.querySelectorAll('.vt-btn').forEach((b,i)=>b.classList.toggle('active',i===(v==='map'?1:0)));
  if(v==='map'&&!mapInit){initMap();mapInit=true;}
  history.replaceState(null,'','?view='+v);
}

// ── LEAFLET MAP WITH GEOCODING ──────────────────────────────────────────────
const BRGY_LAT=<?= defined('BRGY_LAT') ? BRGY_LAT : 14.6012182 ?>, BRGY_LNG=<?= defined('BRGY_LNG') ? BRGY_LNG : 120.9960098 ?>;
let mapInstance=null, markerLayer={}, clusterGroup=null;
let satelliteMode=false;

function initMap(){
  try {
  mapInstance=L.map('residentMap',{preferCanvas:true,maxZoom:19,minZoom:12}).setView([BRGY_LAT,BRGY_LNG],17);

  if(typeof L.markerClusterGroup === 'function'){
    clusterGroup=L.markerClusterGroup({
      maxClusterRadius:40,
      showCoverageOnHover:false,
      iconCreateFunction:function(cluster){
        const c=cluster.getChildCount();
        return L.divIcon({className:'',html:`<div style="background:#0f172a;color:#fff;font-size:11px;font-weight:700;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3)">${c}</div>`,iconSize:[36,36],iconAnchor:[18,18]});
      }
    });
  } else {
    clusterGroup=L.layerGroup();
  }
  mapInstance.addLayer(clusterGroup);

  // OpenStreetMap — reliable, no API key required
  const osmStreet = L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',maxZoom:19,subdomains:['a','b','c']}
  );

  // ESRI World Imagery — satellite/aerial photography
  const esriSat = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    {attribution:'Tiles &copy; Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',maxZoom:19}
  );

  // OSM labels overlay on satellite
  const esriLabels = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
    {attribution:'',maxZoom:19,opacity:.85}
  );

  osmStreet.addTo(mapInstance);

  // Satellite toggle button
  const satBtn=L.control({position:'topright'});
  satBtn.onAdd=function(){
    const div=L.DomUtil.create('div');
    div.innerHTML='<button id="satToggle" title="Toggle satellite view" style="background:#fff;border:2px solid rgba(0,0,0,.2);border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;font-family:Inter,sans-serif;display:flex;align-items:center;gap:5px;color:#374151"><i class="fas fa-satellite"></i> Satellite</button>';
    L.DomEvent.disableClickPropagation(div);
    return div;
  };
  satBtn.addTo(mapInstance);
  document.addEventListener('click',function(e){
    if(e.target.closest('#satToggle')){
      satelliteMode=!satelliteMode;
      if(satelliteMode){
        mapInstance.removeLayer(osmStreet);
        esriSat.addTo(mapInstance);
        esriLabels.addTo(mapInstance);
        document.getElementById('satToggle').style.background='#0f172a';
        document.getElementById('satToggle').style.color='#fff';
      } else {
        mapInstance.removeLayer(esriSat);
        mapInstance.removeLayer(esriLabels);
        osmStreet.addTo(mapInstance);
        document.getElementById('satToggle').style.background='#fff';
        document.getElementById('satToggle').style.color='#374151';
      }
    }
  });

  // Barangay label
  L.marker([BRGY_LAT,BRGY_LNG],{icon:L.divIcon({className:'',
    html:'<div style="background:#0f172a;color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.3)">📍 Barangay 410</div>',
    iconAnchor:[65,16]
  })}).addTo(mapInstance);

  const noCoords=mapResidents.filter(r=>!r.lat||!r.lng);
  const n=noCoords.length;

  mapResidents.forEach((res,idx)=>{
    const hasCoords=res.lat&&res.lng;
    if(!hasCoords){
      // Fallback: spread ungeocoded residents in a small ring
      const i=noCoords.indexOf(res);
      const angle=(2*Math.PI*i)/Math.max(n,1);
      const radius=0.0006+((i%3)*0.0002);
      res._fbLat=BRGY_LAT+radius*Math.sin(angle);
      res._fbLng=BRGY_LNG+radius*Math.cos(angle);
    }
    addMarker(res);
  });

  updateGeocodeStats();
  setTimeout(()=>mapInstance.invalidateSize(),300);
  } catch(err){
    console.error('Map init error:',err);
    const el=document.getElementById('geocodeStats');
    if(el) el.innerHTML='<span style="color:#be123c"><b>Map failed to load.</b> Check browser console.</span>';
  }
}

function addMarker(res){
  const hasCoords=res.lat&&res.lng;
  const lat=hasCoords?res.lat:res._fbLat;
  const lng=hasCoords?res.lng:res._fbLng;
  const g=(res.gender||'').toLowerCase();
  const color=hasCoords?(g==='female'?'#ec4899':(g==='male'?'#3b82f6':'#94a3b8')):'#e2e8f0';
  const border=hasCoords?'2px solid #fff':'1.5px dashed #94a3b8';
  const size=hasCoords?14:11;

  const icon=L.divIcon({className:'',
    html:`<div style="width:${size}px;height:${size}px;border-radius:50%;background:${color};border:${border};box-shadow:${hasCoords?'0 1px 4px rgba(0,0,0,.3)':'none'};cursor:pointer"></div>`,
    iconAnchor:[Math.round(size/2),Math.round(size/2)]
  });

  const statusBadge=hasCoords
    ?'<span style="font-size:10px;color:#16a34a;background:#f0fdf4;padding:2px 6px;border-radius:10px;border:1px solid #bbf7d0">✓ Exact location</span>'
    :'<span style="font-size:10px;color:#b45309;background:#fffbeb;padding:2px 6px;border-radius:10px;border:1px solid #fde68a">⚠ Approximate — click Geocode</span>';

  const detailHtml = IS_GUEST
    ? `<div style="font-size:11px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:5px 9px;border-radius:6px;margin-top:6px"><i class="fas fa-circle-info"></i> Contact the Barangay Secretary for full details</div>`
    : `<div style="font-size:12px;color:#475569;line-height:1.8;margin-bottom:6px"><b>Age:</b> ${res.age} yrs &nbsp;·&nbsp; <b>Gender:</b> ${res.gender||'—'}<br><b>Address:</b> ${res.address||'—'}</div>${statusBadge}<br><a href="Edit.php?id=${res.id}" style="display:inline-block;margin-top:8px;background:#3b82f6;color:#fff;padding:4px 12px;border-radius:6px;text-decoration:none;font-size:11px;font-weight:600">Edit Record</a>`;

  const popup=`<div style="font-family:Inter,sans-serif;min-width:190px;line-height:1">
    <b style="font-size:14px;color:#0f172a">${res.name}</b>
    <div style="font-family:monospace;font-size:11px;color:#3b82f6;margin:4px 0">${res.code||'—'}</div>
    ${detailHtml}
  </div>`;

  const marker=L.marker([lat,lng],{icon}).bindPopup(popup);
  clusterGroup.addLayer(marker);
  markerLayer[res.id]={marker,res};
}

function updateGeocodeStats(){
  const el=document.getElementById('geocodeStats');
  if(!el) return;
  const total=mapResidents.length;
  const done=mapResidents.filter(r=>r.lat&&r.lng).length;
  if(done===total&&total>0){
    el.innerHTML=`<b style="color:#16a34a">✓ All ${total} residents geocoded to exact addresses</b>`;
  } else {
    el.innerHTML=`<b>${done}</b> of <b>${total}</b> residents pinned to exact address &nbsp;·&nbsp; <span style="color:#b45309">${total-done} still approximate</span>`;
  }
}

function updateMarker(id,lat,lng){
  if(!markerLayer[id]) return;
  const {marker,res}=markerLayer[id];
  clusterGroup.removeLayer(marker);
  res.lat=lat; res.lng=lng;
  delete markerLayer[id];
  addMarker(res);
}

// ── BATCH GEOCODING ──────────────────────────────────────────────────────────
let geoQueue=[],geoRunning=false,geoTimer=null,geoDone=0,geoTotal=0;

function startGeocode(){
  if(geoRunning) return;
  geoQueue=mapResidents.filter(r=>!r.lat||!r.lng).slice();
  if(!geoQueue.length){alert('All addresses are already geocoded!');return;}
  geoTotal=geoQueue.length; geoDone=0; geoRunning=true;
  document.getElementById('geocodeBtn').disabled=true;
  document.getElementById('geocodeBtn').innerHTML='<i class="fas fa-spinner fa-spin"></i> Geocoding…';
  document.getElementById('geocodeProgress').style.display='block';
  processGeocode();
}

function stopGeocode(){
  geoRunning=false;
  if(geoTimer) clearTimeout(geoTimer);
  const btn=document.getElementById('geocodeBtn');
  btn.disabled=false; btn.innerHTML='<i class="fas fa-map-pin"></i> Geocode Addresses';
  document.getElementById('geocodeProgress').style.display='none';
  updateGeocodeStats();
}

function processGeocode(){
  if(!geoRunning||!geoQueue.length){stopGeocode();return;}
  const item=geoQueue.shift();
  geoDone++;
  const remaining=geoQueue.length;
  document.getElementById('geocodeProgressText').textContent=
    `Geocoding ${geoDone} of ${geoTotal}: "${item.address||'(no address)'}"`;
  document.getElementById('geocodeBar').style.width=`${(geoDone/geoTotal)*100}%`;

  const fd=new FormData();
  fd.append('id',item.id); fd.append('address',item.address||'');

  fetch('geocode_resident.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.ok) updateMarker(d.id,d.lat,d.lng);
      geoTimer=setTimeout(processGeocode,1250); // respect Nominatim 1 req/s limit
    })
    .catch(()=>{ geoTimer=setTimeout(processGeocode,1250); });
}
<?php if(($list_unlocked||$is_guest)&&$view_mode==='map'):?>window.addEventListener('load',()=>{initMap();mapInit=true;});<?php endif;?>

// Modals
function openModal(id){document.getElementById(id).classList.add('show');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('show');document.body.style.overflow='';}
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('show');}));
function openDelModal(id){document.getElementById('delId').value=id;document.getElementById('deletePw').value='';document.getElementById('deleteErr').style.display='none';openModal('deleteModal');}
function openExportModal(){
  document.getElementById('exportPw').value='';
  document.getElementById('exportErr').style.display='none';
  document.getElementById('exportConfirmBtn').disabled=false;
  document.getElementById('exportConfirmBtn').innerHTML='<i class="fas fa-download"></i> Download CSV';
  openModal('exportModal');
}
function openImportModal(){
  const f=document.getElementById('importFileInput');
  if(!f||!f.files||!f.files.length){alert('Please select a CSV file first.');return;}
  const fname=f.files[0].name;
  if(!fname.toLowerCase().endsWith('.csv')){alert('Only .csv files are accepted. Please export your spreadsheet as CSV first.');return;}
  document.getElementById('importFileInfoText').textContent=fname+' ('+formatBytes(f.files[0].size)+')';
  document.getElementById('importPw').value='';
  document.getElementById('importErr').style.display='none';
  document.getElementById('importConfirmBtn').disabled=false;
  document.getElementById('importConfirmBtn').innerHTML='<i class="fas fa-upload"></i> Confirm Import';
  openModal('importModal');
}

function verifyAndDo(pw, onSuccess, errId, onFail){
  const fd=new FormData();fd.append('password',pw);
  fetch('verify_secretary.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    if(res.ok){onSuccess();}
    else{
      const el=document.getElementById(errId);
      el.textContent=res.message; el.style.display='block';
      if(onFail) onFail();
    }
  }).catch(()=>{
    const el=document.getElementById(errId);
    el.textContent='Network error. Please try again.'; el.style.display='block';
    if(onFail) onFail();
  });
}
function formatBytes(b){if(b<1024)return b+'B';if(b<1048576)return(b/1024).toFixed(1)+'KB';return(b/1048576).toFixed(1)+'MB';}
function onImportFileChange(input){
  const label=document.getElementById('importFileName');
  if(input.files&&input.files.length){
    const f=input.files[0];
    label.textContent=f.name+' ('+formatBytes(f.size)+')';
    label.style.color='rgba(255,255,255,.85)';
  } else {
    label.textContent='No file chosen';
    label.style.color='rgba(255,255,255,.4)';
  }
}
function doExport(){
  const btn=document.getElementById('exportConfirmBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Preparing…';
  verifyAndDo(document.getElementById('exportPw').value,()=>{
    btn.innerHTML='<i class="fas fa-check"></i> Downloading…';
    setTimeout(()=>{
      window.location.href='Export_excel.php';
      setTimeout(()=>closeModal('exportModal'),1500);
    },300);
  },'exportErr',()=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-download"></i> Download CSV';
  });
}
function doImport(){
  const btn=document.getElementById('importConfirmBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Importing…';
  verifyAndDo(document.getElementById('importPw').value,()=>{
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Uploading file…';
    document.getElementById('settingsImportForm').submit();
  },'importErr',()=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-upload"></i> Confirm Import';
  });
}
function doDelete(){verifyAndDo(document.getElementById('deletePw').value,()=>{window.location.href='delete_permanent.php?id='+document.getElementById('delId').value;},'deleteErr');}

// Print
function printRes(id){
  const el=document.getElementById('dp-h-'+id)||document.getElementById('dp-m-'+id);
  if(!el){alert('Please expand the record first.');return;}
  const w=window.open('','','height=800,width=800');
  w.document.write('<html><head><title>Resident Record</title><style>body{font-family:Inter,sans-serif;font-size:13px;padding:20px}label{font-size:10px;text-transform:uppercase;color:#64748b;display:block;margin-top:12px}p{margin:2px 0;color:#0f172a;font-weight:500}hr{border:none;border-top:1px solid #e2e8f0;margin:12px 0}.card-actions{display:none}</style></head><body>');
  w.document.write('<h2 style="font-size:15px">Barangay 410 – Resident Record</h2>');
  w.document.write('<p style="color:#94a3b8;font-size:11px">Printed: '+new Date().toLocaleString()+'</p><hr>');
  const clone=el.cloneNode(true);clone.style.display='block';
  w.document.write(clone.innerHTML);
  w.document.write('</body></html>');w.document.close();w.print();
}
</script>
<script>
function openSettingsExportModal(){ closeSettings(); setTimeout(()=>openExportModal(), 300); }
function openSettingsImportModal(){ closeSettings(); setTimeout(()=>openImportModal(), 300); }
function openSettings(){document.getElementById('settingsOverlay').style.display='block';document.getElementById('settingsDrawer').style.right='0';document.body.style.overflow='hidden';if(typeof closeSidebar==='function')closeSidebar();}
function closeSettings(){document.getElementById('settingsOverlay').style.display='none';document.getElementById('settingsDrawer').style.right='-360px';document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSettings();});
let darkMode=localStorage.getItem('rbi_dark')==='1';
function applyDark(on){document.body.classList.toggle('dark-mode',on);const th=document.getElementById('darkThumb'),tg=document.getElementById('darkToggle');if(th)th.style.left=on?'21px':'3px';if(tg)tg.style.background=on?'#3b82f6':'#475569';document.documentElement.style.setProperty('--bg',on?'#0f172a':'#f8fafc');document.documentElement.style.setProperty('--card',on?'#1e293b':'#ffffff');document.documentElement.style.setProperty('--border',on?'#334155':'#e2e8f0');document.documentElement.style.setProperty('--text',on?'#e2e8f0':'#0f172a');}
applyDark(darkMode);
function toggleDarkMode(){darkMode=!darkMode;localStorage.setItem('rbi_dark',darkMode?'1':'0');applyDark(darkMode);}
</script>
</body>
</html>










