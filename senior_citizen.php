<?php
session_start();
if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
$admin = $_SESSION['admin'];
include 'role_helper.php';
include 'Residents_DB.php';


// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS senior_citizens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    last_name      VARCHAR(100) NOT NULL,
    first_name     VARCHAR(100) NOT NULL,
    middle_name    VARCHAR(100) DEFAULT NULL,
    gender         ENUM('Male','Female') DEFAULT NULL,
    birth_month    TINYINT NOT NULL,
    birth_day      TINYINT NOT NULL,
    birth_year     SMALLINT NOT NULL,
    address        VARCHAR(300) DEFAULT NULL,
    contact_number VARCHAR(30) DEFAULT NULL,
    status         ENUM('Active','Deceased') DEFAULT 'Active',
    added_by       VARCHAR(150) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = ''; $err = '';

// ── Handle POST actions ──────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && $can_edit){
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if($action === 'sync_from_residents' && $is_captain){
        $all = $conn->query("SELECT first_name,middle_name,last_name,gender,birthdate,perm_address,mobile FROM residents WHERE birthdate IS NOT NULL AND birthdate != '' AND (is_senior='Yes' OR TIMESTAMPDIFF(YEAR,birthdate,CURDATE())>=60)");
        $added = 0;
        while($r = $all->fetch_assoc()){
            $bd_obj = new DateTime($r['birthdate']);
            $sc_bm  = (int)$bd_obj->format('n');
            $sc_bd  = (int)$bd_obj->format('j');
            $sc_by  = (int)$bd_obj->format('Y');
            $sc_ln  = mysqli_real_escape_string($conn, $r['last_name']);
            $sc_fn  = mysqli_real_escape_string($conn, $r['first_name']);
            $sc_mn  = mysqli_real_escape_string($conn, $r['middle_name'] ?? '');
            $sc_gen = mysqli_real_escape_string($conn, $r['gender']      ?? '');
            $sc_addr= mysqli_real_escape_string($conn, $r['perm_address']?? '');
            $sc_con = mysqli_real_escape_string($conn, $r['mobile']      ?? '');
            $sc_who = mysqli_real_escape_string($conn, $user_fullname);
            $exists = $conn->query("SELECT id FROM senior_citizens WHERE last_name='$sc_ln' AND first_name='$sc_fn' AND birth_month=$sc_bm AND birth_day=$sc_bd AND birth_year=$sc_by LIMIT 1");
            if($exists && $exists->num_rows === 0){
                $conn->query("INSERT INTO senior_citizens (last_name,first_name,middle_name,gender,birth_month,birth_day,birth_year,address,contact_number,status,added_by)
                    VALUES ('$sc_ln','$sc_fn','$sc_mn','$sc_gen',$sc_bm,$sc_bd,$sc_by,'$sc_addr','$sc_con','Active','$sc_who')");
                $added++;
            }
        }
        $msg = "Sync complete — $added new senior citizen(s) imported from the residents list.";
    }

    if($action === 'edit'){
        $pw_attempt = $_POST['confirm_password'] ?? '';
        $adm_esc    = mysqli_real_escape_string($conn, $admin);
        $adm_row    = $conn->query("SELECT password FROM admins WHERE username='$adm_esc' LIMIT 1")->fetch_assoc();
        if(!$adm_row || !password_verify($pw_attempt, $adm_row['password'])){
            $err = 'Incorrect password. Changes were not saved.';
        } else {
            $id  = (int)($_POST['sc_id'] ?? 0);
            $ln  = mysqli_real_escape_string($conn, trim($_POST['last_name']  ?? ''));
            $fn  = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
            $mn  = mysqli_real_escape_string($conn, trim($_POST['middle_name']?? ''));
            $gen = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
            $bm  = (int)($_POST['birth_month'] ?? 0);
            $bd  = (int)($_POST['birth_day']   ?? 0);
            $by  = (int)($_POST['birth_year']  ?? 0);
            $addr= mysqli_real_escape_string($conn, trim($_POST['address']        ?? ''));
            $con = mysqli_real_escape_string($conn, trim($_POST['contact_number'] ?? ''));
            $sta = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');

            if(!$id || !$ln || !$fn){ $err = 'Missing required fields.'; }
            else {
                $conn->query("UPDATE senior_citizens SET
                    last_name='$ln',first_name='$fn',middle_name='$mn',gender='$gen',
                    birth_month=$bm,birth_day=$bd,birth_year=$by,
                    address='$addr',contact_number='$con',status='$sta'
                    WHERE id=$id");
                $msg = 'Record updated.';
            }
        }
    }

    elseif($action === 'delete' && $is_captain){
        $id = (int)($_POST['sc_id'] ?? 0);
        if($id){ $conn->query("DELETE FROM senior_citizens WHERE id=$id"); $msg="Record deleted."; }
    }
}

// ── Stats ────────────────────────────────────────────────────────────────────
$total    = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens")->fetch_assoc()['c'];
$active   = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens WHERE status='Active'")->fetch_assoc()['c'];
$deceased = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens WHERE status='Deceased'")->fetch_assoc()['c'];
$cur_m    = (int)date('n');
$cur_d    = (int)date('j');
$bday_month = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens WHERE birth_month=$cur_m AND status='Active'")->fetch_assoc()['c'];

// ── Search / List ────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$sq = $search ? "WHERE (last_name LIKE '%".mysqli_real_escape_string($conn,$search)."%'
                  OR first_name LIKE '%".mysqli_real_escape_string($conn,$search)."%'
                  OR address LIKE '%".mysqli_real_escape_string($conn,$search)."%')" : "";
$list = $conn->query("SELECT * FROM senior_citizens $sq ORDER BY last_name, first_name");

// Prepare printable data
$print_seniors = $conn->query("SELECT sc.*, COALESCE(b.cnt,0) AS borrowed_count, COALESCE(b.items,'') AS borrowed_items, b.last_borrow_date
  FROM senior_citizens sc
  LEFT JOIN (
    SELECT senior_citizen_id, COUNT(*) AS cnt, GROUP_CONCAT(CONCAT(e.item_code,' - ',e.item_name) SEPARATOR '; ') AS items, MAX(borrow_date) AS last_borrow_date
    FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id
    WHERE eb.senior_citizen_id IS NOT NULL
    GROUP BY senior_citizen_id
  ) b ON b.senior_citizen_id = sc.id
  WHERE sc.status='Active' ORDER BY sc.last_name, sc.first_name");

// ── Birthday lists ───────────────────────────────────────────────────────────
$bday_today = []; $bday_this_month = [];
$br = $conn->query("SELECT last_name,first_name,birth_month,birth_day,birth_year FROM senior_citizens WHERE status='Active' ORDER BY birth_month,birth_day");
while($row = $br->fetch_assoc()){
    if($row['birth_month']==$cur_m && $row['birth_day']==$cur_d)
        $bday_today[] = $row;
    elseif($row['birth_month']==$cur_m)
        $bday_this_month[] = $row;
}

function sc_age($m,$d,$y){
    $today = new DateTime(); $bd = new DateTime("$y-$m-$d");
    return $today->diff($bd)->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Senior Citizens – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<style>
.page-top{background:#0f172a;padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.page-top h1{color:#fff;font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800}
.page-top p{color:rgba(255,255,255,.45);font-size:12px;margin-top:2px}
main{padding:1.5rem;max-width:1200px;margin:0 auto}

/* ═══════════════════════════════════════════════════
   PRINT STYLES — Senior Citizens Report
   ═══════════════════════════════════════════════════ */
@media print {

  /* Hide everything on the page */
  body * {
    display: none !important;
    visibility: hidden !important;
  }

  /* Show ONLY the print report container */
  .sc-print,
  .sc-print * {
    display: revert !important;
    visibility: visible !important;
  }

  /* Root container */
  .sc-print {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    background: #fff !important;
    color: #000 !important;
    font-family: 'Times New Roman', Times, serif !important;
    font-size: 9pt !important;
    line-height: 1.4 !important;
  }

  /* ── Page setup ── */
  @page {
    size: A4 portrait;
    margin: 12mm 12mm 14mm 12mm;
  }

  /* ── Header band ── */
  .sc-print .header {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
    padding: 8px 0 10px !important;
    margin-bottom: 14px !important;
    border-top: 2.5px solid #000 !important;
    border-bottom: 2.5px solid #000 !important;
  }

  .sc-print .print-header-logos {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
  }

  .sc-print .hdr-img {
    display: inline-block !important;
    width: 44px !important;
    height: 44px !important;
    object-fit: contain !important;
  }

  .sc-print .print-header-text {
    display: flex !important;
    flex-direction: column !important;
    gap: 1px !important;
    flex: 1 !important;
    padding-left: 8px !important;
    border-left: 1.5px solid #ccc !important;
  }

  .sc-print .print-header-text p {
    margin: 0 !important;
    font-size: 8.5pt !important;
    color: #333 !important;
  }

  .sc-print .brgy-name {
    font-size: 11.5pt !important;
    font-weight: 700 !important;
    color: #000 !important;
    margin-bottom: 2px !important;
  }

  /* Decorative title line with rules */
  .sc-print .doc-title-line {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 5px 0 3px !important;
  }

  .sc-print .doc-title-line::before,
  .sc-print .doc-title-line::after {
    content: '' !important;
    display: block !important;
    flex: 1 !important;
    height: 1px !important;
    background: #000 !important;
  }

  .sc-print .doc-title {
    font-size: 13pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .07em !important;
    white-space: nowrap !important;
    color: #000 !important;
  }

  .sc-print .doc-sub {
    font-size: 8pt !important;
    color: #555 !important;
  }

  /* ── Summary section ── */
  .sc-print .print-body {
    display: block !important;
    margin-bottom: 14px !important;
    width: 100% !important;
  }

  .sc-print .print-col-left {
    display: block !important;
    width: 260px !important;
  }

  .sc-print .rbi-card {
    display: block !important;
    border: 1px solid #000 !important;
    border-radius: 0 !important;
    padding: 10px 12px !important;
    margin-bottom: 14px !important;
    page-break-inside: avoid !important;
    box-shadow: none !important;
  }

  .sc-print .section-title {
    font-size: 9pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .05em !important;
    color: #000 !important;
    border-bottom: 1.5px solid #000 !important;
    padding-bottom: 4px !important;
    margin: 0 0 10px !important;
  }

  /* Summary table (small) */
  .sc-print .print-col-left table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 8.5pt !important;
    table-layout: auto !important;
  }

  .sc-print .print-col-left td {
    border: 1px solid #999 !important;
    padding: 4px 8px !important;
    vertical-align: middle !important;
  }

  .sc-print .print-col-left td:last-child {
    text-align: center !important;
    font-weight: 700 !important;
    width: 50px !important;
  }

  /* ── Main details table ── */
  .sc-print .rbi-card > table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 8pt !important;
    /* Fixed layout is KEY — prevents columns from overflowing */
    table-layout: fixed !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
  }

  /* Column width distribution (must total 100%) */
  .sc-print .rbi-card > table colgroup col:nth-child(1) { width: 4% !important; }
  .sc-print .rbi-card > table colgroup col:nth-child(2) { width: 22% !important; }
  .sc-print .rbi-card > table colgroup col:nth-child(3) { width: 6% !important; }
  .sc-print .rbi-card > table colgroup col:nth-child(4) { width: 13% !important; }
  .sc-print .rbi-card > table colgroup col:nth-child(5) { width: 7% !important; }
  .sc-print .rbi-card > table colgroup col:nth-child(6) { width: 34% !important; }
  .sc-print .rbi-card > table colgroup col:nth-child(7) { width: 14% !important; }

  .sc-print thead {
    display: table-header-group !important;
  }

  .sc-print thead th {
    background: #1e293b !important;
    color: #fff !important;
    font-size: 7.5pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .04em !important;
    padding: 5px 6px !important;
    border: 1px solid #000 !important;
    text-align: left !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .sc-print tbody td {
    border: 1px solid #ccc !important;
    padding: 5px 6px !important;
    vertical-align: top !important;
    font-size: 8pt !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    white-space: normal !important;
  }

  .sc-print tbody tr:nth-child(even) td {
    background: #f8f8f8 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .sc-print td.center,
  .sc-print th.center {
    text-align: center !important;
  }

  /* ── Footer ── */
  .sc-print .foot {
    display: block !important;
    text-align: center !important;
    font-size: 7.5pt !important;
    font-style: italic !important;
    color: #555 !important;
    border-top: 1px solid #000 !important;
    padding-top: 5px !important;
    margin-top: 18px !important;
  }

  /* Hide screen-only elements even if they leak in */
  header.topbar,
  .sidebar,
  .sidebar-overlay,
  footer,
  main,
  #settingsOverlay,
  #settingsDrawer,
  .modal-overlay {
    display: none !important;
  }
}
/* end @media print */

.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.25rem}
@media(max-width:600px){.stat-row{grid-template-columns:1fr 1fr}}
.scard{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem;text-align:center}
.scard-num{font-size:22px;font-weight:700;line-height:1}
.scard-lbl{font-size:11px;color:#64748b;margin-top:3px}
.tabs{display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:1.25rem;gap:4px}
.tab{padding:8px 16px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s;background:none;border-top:none;border-left:none;border-right:none;font-family:'Inter',sans-serif}
.tab.active{color:#10b981;border-bottom-color:#10b981}
.tab-content{display:none}.tab-content.active{display:block}
.search-row{display:flex;gap:10px;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
.search-row input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:Inter,sans-serif;outline:none}
.search-row input:focus{border-color:#10b981}
.sc-table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:860px;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
thead th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1.5px solid #e2e8f0}
tbody td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#1e293b;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:#f8fafc}
tbody tr.birthday-today{background:#f0fdf4}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700}
.badge-alive{background:#dcfce7;color:#15803d}
.badge-deceased{background:#fee2e2;color:#dc2626}
.badge-bday{background:#fef9c3;color:#a16207;margin-left:6px}
.badge-today{background:#fde68a;color:#92400e;margin-left:6px}
.age-pill{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#eff6ff;color:#1d4ed8}
.bday-section{margin-bottom:1.5rem}
.bday-section-title{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
.bday-card{display:flex;align-items:center;gap:12px;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:8px}
.bday-card.today{border-color:#fbbf24;background:#fffbeb}
.bday-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.bday-avatar.today-av{background:#fef3c7}
.bday-avatar.month-av{background:#f0fdf4}
.bday-name{font-size:13px;font-weight:600;color:#0f172a}
.bday-info{font-size:11px;color:#64748b;margin-top:2px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;padding:2rem;width:100%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-box h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:1.25rem}
.fg{margin-bottom:12px}
.fg label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px}
.fg input,.fg select,.fg textarea{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#10b981}
.fgrow{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.fgrow2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem;margin-top:2rem}
@keyframes modalIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.empty-state{text-align:center;padding:3rem;color:#94a3b8}
.empty-state i{font-size:48px;opacity:.2;display:block;margin-bottom:12px}
.topbar{position:sticky;top:0;z-index:200}
</style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div>
  </a>
  <div class="topbar-right" style="margin-left:auto">
    <?php if($is_captain):?>
    <button class="btn btn-primary" style="font-size:12px;padding:6px 12px" onclick="document.getElementById('syncModal').classList.add('open')"><i class="fas fa-rotate"></i> Sync Residents</button>
    <?php endif;?>
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain):?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);<?php elseif($is_secretary):?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);<?php else:?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif;?>"><i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<!-- ═══════════════════════════════════════════════════════════════
     PRINTABLE SENIOR CITIZENS REPORT  (hidden on screen, visible on print)
     ═══════════════════════════════════════════════════════════════ -->
<div class="sc-print" style="display:none">

  <!-- Header -->
  <div class="header">
    <div class="print-header-logos">
      <img src="images/logo_bagong_pilipinas.png" class="hdr-img" alt="Bagong Pilipinas">
      <img src="images/lungsod_ng_manila_logo.png" class="hdr-img" alt="City of Manila">
      <img src="images/brgy410_logo.png"           class="hdr-img" alt="Barangay 410">
    </div>
    <div class="print-header-text">
      <p class="doc-sub">Republic of the Philippines &nbsp;·&nbsp; City of Manila</p>
      <p class="brgy-name"><?= htmlspecialchars(defined('BRGY_NAME') ? BRGY_NAME : 'Barangay 410') ?></p>
      <div class="doc-title-line"><span class="doc-title">Senior Citizens Register</span></div>
      <p class="doc-sub">Generated: <?= date('F j, Y \a\t g:i A') ?></p>
    </div>
  </div>

  <!-- Summary card -->
  <div class="print-body">
    <div class="print-col-left">
      <div class="rbi-card">
        <div class="section-title">Summary</div>
        <table>
          <tbody>
            <tr><td>Total Registered</td><td><?= $total ?></td></tr>
            <tr><td>Active / Alive</td><td><?= $active ?></td></tr>
            <tr><td>Deceased</td><td><?= $deceased ?></td></tr>
            <tr><td>Birthdays this month</td><td><?= count($bday_this_month) + count($bday_today) ?></td></tr>
            <tr><td>Birthdays today</td><td><?= count($bday_today) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Details table -->
  <div class="rbi-card">
    <div class="section-title">Senior Citizen Details (Active)</div>
    <table>
      <!-- colgroup drives the fixed column widths -->
      <colgroup>
        <col><col><col><col><col><col><col>
      </colgroup>
      <thead>
        <tr>
          <th class="center">#</th>
          <th>Name</th>
          <th class="center">Age</th>
          <th>Contact</th>
          <th class="center">Borrowed</th>
          <th>Items Borrowed</th>
          <th class="center">Last Borrow</th>
        </tr>
      </thead>
      <tbody>
      <?php if($print_seniors && $print_seniors->num_rows): $i=1; while($s = $print_seniors->fetch_assoc()): ?>
        <tr>
          <td class="center"><?= $i ?></td>
          <td><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . (trim($s['middle_name']) ? ' ' . $s['middle_name'] : '')) ?></td>
          <td class="center"><?= sc_age($s['birth_month'], $s['birth_day'], $s['birth_year']) ?></td>
          <td><?= htmlspecialchars($s['contact_number'] ?? '') ?></td>
          <td class="center"><?= (int)$s['borrowed_count'] ?></td>
          <td><?= htmlspecialchars($s['borrowed_items']) ?></td>
          <td class="center"><?= htmlspecialchars($s['last_borrow_date'] ?? '') ?></td>
        </tr>
      <?php $i++; endwhile; else: ?>
        <tr><td colspan="7" style="text-align:center;padding:12px;font-style:italic;color:#666">No active senior citizen records found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="foot">
    ProjectRBI &mdash; <?= htmlspecialchars(defined('BRGY_NAME') ? BRGY_NAME : 'Barangay 410') ?> &nbsp;&middot;&nbsp; Printed <?= date('F j, Y') ?>
  </div>
</div>
<!-- end .sc-print -->

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar" style="overflow-y:auto;overflow-x:hidden">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div>
    </div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <div style="padding:12px 10px 10px;border-bottom:1px solid rgba(255,255,255,.07)">
    <div class="sidebar-label" style="margin-bottom:7px">Senior Citizens Summary</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px">
      <?php
      $sc_cards=[
        ['fa-person-cane',     '99,102,241','#a5b4fc',$total,              'Total'],
        ['fa-heart-pulse',     '34,197,94', '#86efac',$active,             'Active'],
        ['fa-cross',           '244,63,94', '#fda4af',$deceased,           'Deceased'],
        ['fa-cake-candles',    '245,158,11','#fbbf24',$bday_month,         'Bday This Month'],
        ['fa-bell',            '168,85,247','#d8b4fe',count($bday_today),  'Bday Today'],
      ];
      foreach($sc_cards as [$ico,$rgb,$tc,$val,$lbl]):?>
      <div style="background:rgba(255,255,255,.05);border-radius:9px;padding:8px;display:flex;align-items:center;gap:7px">
        <div style="width:28px;height:28px;border-radius:7px;background:rgba(<?=$rgb?>,.22);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas <?=$ico?>" style="font-size:11px;color:<?=$tc?>"></i>
        </div>
        <div>
          <div style="font-size:16px;font-weight:800;color:<?=$tc?>;line-height:1"><?=$val?></div>
          <div style="font-size:9.5px;color:rgba(255,255,255,.4);margin-top:1px"><?=$lbl?></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <div style="padding:14px 12px 6px">
    <button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer">
      <i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i>
    </button>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register):?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest):?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif;?>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<div style="background:linear-gradient(to right,rgba(15,23,42,.85),rgba(15,23,42,.55)),url('images/Barangay_officials_410.png') center center/cover no-repeat;padding:2rem 2rem;min-height:300px;display:flex;align-items:center">
  <div style="max-width:1200px;width:100%">
    <h1 style="font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:#fff;margin:0 0 .25rem"><i class="fas fa-person-cane" style="margin-right:.5rem;opacity:.8"></i>Senior Citizens Registry</h1>
    <p style="color:rgba(255,255,255,.6);font-size:.84rem;margin:0">Senior Citizen Records &amp; Birthday Tracking</p>
  </div>
</div>

<main style="padding-top:1.75rem">
  <?php if($msg):?><div class="alert alert-success" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?=$msg?></div><?php endif;?>
  <?php if($err):?><div class="alert alert-error" style="margin-bottom:1rem"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($err)?></div><?php endif;?>

  <div class="stat-row">
    <div class="scard"><div class="scard-num" style="color:#0f172a"><?=$total?></div><div class="scard-lbl">Total Registered</div></div>
    <div class="scard"><div class="scard-num" style="color:#10b981"><?=$active?></div><div class="scard-lbl">Active / Alive</div></div>
    <div class="scard"><div class="scard-num" style="color:#f43f5e"><?=$deceased?></div><div class="scard-lbl">Deceased</div></div>
    <div class="scard"><div class="scard-num" style="color:#f59e0b"><?=$bday_month?></div><div class="scard-lbl">Birthdays This Month</div></div>
  </div>

  <div class="tabs">
    <button class="tab active" onclick="switchTab('masterlist',this)"><i class="fas fa-list"></i> Masterlist</button>
    <button class="tab" onclick="switchTab('birthdays',this)">
      <i class="fas fa-cake-candles"></i> Birthday Reminders
      <?php if(count($bday_today)>0):?>
        <span style="background:#f43f5e;color:#fff;border-radius:20px;font-size:10px;padding:1px 7px;margin-left:5px"><?=count($bday_today)?> today</span>
      <?php endif;?>
    </button>
  </div>

  <!-- MASTERLIST TAB -->
  <div id="tab-masterlist" class="tab-content active">
    <form method="GET" class="search-row">
      <input type="text" name="search" placeholder="Search by name or address..." value="<?=htmlspecialchars($search)?>">
      <button type="submit" class="btn btn-primary" style="white-space:nowrap"><i class="fas fa-search"></i> Search</button>
      <?php if($search):?><a href="senior_citizen.php" class="btn btn-outline" style="white-space:nowrap"><i class="fas fa-times"></i> Clear</a><?php endif;?>
    </form>

    <?php if($list && $list->num_rows > 0): ?>
    <div class="sc-table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Full Name</th><th>Age</th><th>Birthdate</th>
            <th>Gender</th><th>Address</th><th>Contact</th><th>Status</th>
            <?php if($can_edit):?><th>Actions</th><?php endif;?>
          </tr>
        </thead>
        <tbody>
        <?php $n=1; while($row=$list->fetch_assoc()):
            $full  = htmlspecialchars($row['last_name'].', '.$row['first_name'].($row['middle_name']?' '.$row['middle_name']:''));
            $age   = sc_age($row['birth_month'],$row['birth_day'],$row['birth_year']);
            $bdate = sprintf('%02d/%02d/%04d',$row['birth_month'],$row['birth_day'],$row['birth_year']);
            $is_today = ($row['birth_month']==$cur_m && $row['birth_day']==$cur_d);
            $is_month = ($row['birth_month']==$cur_m);
        ?>
        <tr class="<?=$is_today?'birthday-today':''?>">
          <td style="color:#94a3b8;font-size:11px"><?=$n++?></td>
          <td>
            <?=$full?>
            <?php if($is_today):?><span class="badge badge-today">🎉 Today!</span>
            <?php elseif($is_month):?><span class="badge badge-bday">🎂 This month</span><?php endif;?>
          </td>
          <td><span class="age-pill"><?=$age?></span></td>
          <td style="font-size:12px;color:#64748b"><?=$bdate?></td>
          <td style="font-size:12px"><?=htmlspecialchars($row['gender']??'—')?></td>
          <td style="font-size:12px;max-width:180px"><?=htmlspecialchars($row['address']??'—')?></td>
          <td style="font-size:12px;color:#64748b"><?=htmlspecialchars($row['contact_number']??'—')?></td>
          <td><span class="badge <?=$row['status']==='Active'?'badge-alive':'badge-deceased'?>"><?=$row['status']==='Active'?'Alive':'Deceased'?></span></td>
          <?php if($can_edit):?>
          <td style="white-space:nowrap">
            <button class="btn btn-outline btn-sm" onclick="openEdit(<?=htmlspecialchars(json_encode($row))?>)"><i class="fas fa-pen"></i> Edit</button>
            <?php if($is_captain):?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record permanently?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="sc_id" value="<?=$row['id']?>">
              <button type="submit" class="btn btn-sm" style="background:#f43f5e;border-color:#f43f5e;color:#fff"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif;?>
          </td>
          <?php endif;?>
        </tr>
        <?php endwhile;?>
        </tbody>
      </table>
    </div>
    <?php else:?>
    <div class="empty-state">
      <i class="fas fa-user-slash"></i>
      <p><?=$search?'No results for "'.htmlspecialchars($search).'"':'No senior citizens registered yet.'?></p>
    </div>
    <?php endif;?>
  </div>

  <!-- BIRTHDAY TAB -->
  <div id="tab-birthdays" class="tab-content">
    <div class="bday-section">
      <div class="bday-section-title">
        <i class="fas fa-star" style="color:#f59e0b"></i> Today's Birthdays
        <?php if(count($bday_today)>0):?>
          <span style="background:#fef9c3;color:#a16207;padding:1px 8px;border-radius:20px;font-size:10px"><?=count($bday_today)?> celebrant<?=count($bday_today)>1?'s':''?></span>
        <?php endif;?>
      </div>
      <?php if(count($bday_today)===0):?>
        <p style="color:#94a3b8;font-size:13px;padding:.5rem 0">No birthdays today.</p>
      <?php else: foreach($bday_today as $r): $age = sc_age($r['birth_month'],$r['birth_day'],$r['birth_year']); ?>
      <div class="bday-card today">
        <div class="bday-avatar today-av">🎉</div>
        <div style="flex:1">
          <div class="bday-name"><?=htmlspecialchars($r['last_name'].', '.$r['first_name'])?></div>
          <div class="bday-info">Turns <?=$age?> today · <?=date('F j')?></div>
        </div>
        <span class="badge" style="background:#fef3c7;color:#92400e">Prepare benefits</span>
      </div>
      <?php endforeach; endif;?>
    </div>

    <div class="bday-section">
      <div class="bday-section-title">
        <i class="fas fa-calendar-days" style="color:#10b981"></i> Other Birthdays This Month (<?=date('F')?>)
        <?php if(count($bday_this_month)>0):?>
          <span style="background:#dcfce7;color:#15803d;padding:1px 8px;border-radius:20px;font-size:10px"><?=count($bday_this_month)?></span>
        <?php endif;?>
      </div>
      <?php if(count($bday_this_month)===0):?>
        <p style="color:#94a3b8;font-size:13px;padding:.5rem 0">No other birthdays this month.</p>
      <?php else: foreach($bday_this_month as $r): $age = sc_age($r['birth_month'],$r['birth_day'],$r['birth_year']); ?>
      <div class="bday-card">
        <div class="bday-avatar month-av">🎂</div>
        <div style="flex:1">
          <div class="bday-name"><?=htmlspecialchars($r['last_name'].', '.$r['first_name'])?></div>
          <div class="bday-info"><?=date('F j', mktime(0,0,0,$r['birth_month'],$r['birth_day'],date('Y')))?> · Turns <?=$age?></div>
        </div>
      </div>
      <?php endforeach; endif;?>
    </div>

    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;font-size:13px;color:#15803d">
      <i class="fas fa-circle-info" style="margin-right:6px"></i>
      Don't forget to prepare birthday gifts and benefits for our senior citizens!
    </div>
  </div>
</main>

<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City</footer>

<?php if($can_edit):?>
<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <h3><i class="fas fa-pen" style="color:#3b82f6;margin-right:8px"></i>Edit Senior Citizen</h3>
    <form method="POST" id="editForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="sc_id" id="edit_id">
      <input type="hidden" name="confirm_password" id="edit_confirm_pw">
      <div class="fgrow">
        <div class="fg"><label>Last Name *</label><input type="text" name="last_name" id="edit_ln" required></div>
        <div class="fg"><label>First Name *</label><input type="text" name="first_name" id="edit_fn" required></div>
        <div class="fg"><label>Middle Name</label><input type="text" name="middle_name" id="edit_mn"></div>
      </div>
      <div class="fgrow2">
        <div class="fg"><label>Gender</label>
          <select name="gender" id="edit_gen">
            <option value="">— Select —</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div class="fg"><label>Status</label>
          <select name="status" id="edit_status">
            <option value="Active">Active / Alive</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
      </div>
      <div class="fgrow">
        <div class="fg"><label>Birth Month</label><input type="number" name="birth_month" id="edit_bm" min="1" max="12"></div>
        <div class="fg"><label>Birth Day</label><input type="number" name="birth_day" id="edit_bd" min="1" max="31"></div>
        <div class="fg"><label>Birth Year</label><input type="number" name="birth_year" id="edit_by" min="1900"></div>
      </div>
      <div class="fg"><label>Contact Number</label><input type="text" name="contact_number" id="edit_con"></div>
      <div class="fg"><label>Address</label><textarea name="address" id="edit_addr" rows="2"></textarea></div>
      <div style="display:flex;gap:10px;margin-top:.5rem">
        <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
        <button type="button" class="btn btn-primary" style="flex:1" onclick="openEditPwModal()"><i class="fas fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>

<!-- PASSWORD CONFIRM MODAL -->
<?php if($can_edit):?>
<div class="modal-overlay" id="editPwModal" onclick="if(event.target===this)closeEditPwModal()">
  <div class="modal-box" style="max-width:380px;animation:modalIn .22s ease">
    <h3 style="display:flex;align-items:center;gap:10px">
      <span style="width:34px;height:34px;background:#eff6ff;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas fa-lock" style="color:#3b82f6;font-size:14px"></i>
      </span>
      Confirm Changes
    </h3>
    <p style="font-size:13px;color:#475569;margin-bottom:1.1rem;line-height:1.6">Enter your password to save the changes to this senior citizen record.</p>
    <div class="fg">
      <label>Password</label>
      <div style="position:relative">
        <input type="password" id="editPwInput" placeholder="Enter your password"
               style="width:100%;padding:9px 44px 9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:Inter,sans-serif;outline:none;box-sizing:border-box;transition:border .2s"
               onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e2e8f0'"
               onkeydown="if(event.key==='Enter')confirmEditSave()">
        <button type="button" onclick="toggleEditPw()" tabindex="-1"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;padding:4px;font-size:14px">
          <i class="fas fa-eye" id="editPwEyeIcon"></i>
        </button>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:1.1rem">
      <button type="button" class="btn btn-outline" style="flex:1" onclick="closeEditPwModal()">Cancel</button>
      <button type="button" class="btn btn-primary" style="flex:1" onclick="confirmEditSave()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>
</div>
<?php endif;?>

<?php if($is_captain):?>
<!-- SYNC MODAL -->
<div class="modal-overlay" id="syncModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:420px;text-align:center">
    <h3><i class="fas fa-rotate" style="color:#10b981;margin-right:8px"></i>Sync from Residents</h3>
    <p style="font-size:13px;color:#475569;margin-bottom:1.25rem;line-height:1.6">
      This will scan all registered residents and automatically add those aged <strong>60+</strong> or marked as senior citizen to this list.<br><br>
      Already-existing records will not be duplicated.
    </p>
    <form method="POST" style="display:flex;gap:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="sync_from_residents">
      <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('syncModal').classList.remove('open')">Cancel</button>
      <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-rotate"></i> Run Sync</button>
    </form>
  </div>
</div>
<?php endif;?>

<div id="settingsOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100" onclick="closeSettings()"></div>
<div id="settingsDrawer" style="position:fixed;top:0;right:-360px;width:340px;height:100vh;background:#0f172a;z-index:1101;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.08)">
  <div style="padding:20px 20px 14px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#3b82f6,#14b8a6);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-gear" style="color:#fff;font-size:13px"></i></div>
      <div><div style="font-family:Syne,sans-serif;font-size:14px;font-weight:800;color:#fff">Settings</div><div style="font-size:11px;color:rgba(255,255,255,.4)">ProjectRBI Barangay 410</div></div>
    </div>
    <button onclick="closeSettings()" style="width:28px;height:28px;background:rgba(255,255,255,.08);border:none;border-radius:7px;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-times"></i></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:16px">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Actions</div>
    <button onclick="printSC()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print List</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print the senior citizens list</div></div>
    </button>
  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div><div style="font-size:11px;color:rgba(244,63,94,.6)">End your session</div></div>
    </a>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
function openSettings(){document.getElementById('settingsOverlay').style.display='block';document.getElementById('settingsDrawer').style.right='0';document.body.style.overflow='hidden';if(typeof closeSidebar==='function')closeSidebar();}
function closeSettings(){document.getElementById('settingsOverlay').style.display='none';document.getElementById('settingsDrawer').style.right='-360px';document.body.style.overflow='';}

function printSC(){
  closeSettings();
  // Make the print div visible so the browser captures it
  var el = document.querySelector('.sc-print');
  el.style.display = 'block';
  setTimeout(function(){
    window.print();
    // Hide again after print dialog closes
    setTimeout(function(){ el.style.display = 'none'; }, 500);
  }, 350);
}

document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSettings();closeEditPwModal();}});

function switchTab(name,el){
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  el.classList.add('active');
}

function openEditPwModal(){
  document.getElementById('editPwInput').value='';
  var inp=document.getElementById('editPwInput');
  inp.type='password';
  document.getElementById('editPwEyeIcon').className='fas fa-eye';
  document.getElementById('editPwModal').classList.add('open');
  setTimeout(()=>inp.focus(),120);
}
function closeEditPwModal(){document.getElementById('editPwModal').classList.remove('open');}
function toggleEditPw(){
  var inp=document.getElementById('editPwInput'),ico=document.getElementById('editPwEyeIcon');
  if(inp.type==='password'){inp.type='text';ico.className='fas fa-eye-slash';}
  else{inp.type='password';ico.className='fas fa-eye';}
}
function confirmEditSave(){
  var pw=document.getElementById('editPwInput').value;
  if(!pw){document.getElementById('editPwInput').style.borderColor='#f43f5e';return;}
  document.getElementById('edit_confirm_pw').value=pw;
  document.getElementById('editPwModal').classList.remove('open');
  document.getElementById('editForm').submit();
}

function openEdit(row){
  document.getElementById('edit_id').value   = row.id;
  document.getElementById('edit_ln').value   = row.last_name;
  document.getElementById('edit_fn').value   = row.first_name;
  document.getElementById('edit_mn').value   = row.middle_name  || '';
  document.getElementById('edit_gen').value  = row.gender       || '';
  document.getElementById('edit_bm').value   = row.birth_month;
  document.getElementById('edit_bd').value   = row.birth_day;
  document.getElementById('edit_by').value   = row.birth_year;
  document.getElementById('edit_con').value  = row.contact_number || '';
  document.getElementById('edit_addr').value = row.address       || '';
  document.getElementById('edit_status').value = row.status;
  document.getElementById('editModal').classList.add('open');
}
</script>
</body>
</html>