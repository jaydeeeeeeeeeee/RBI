<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: admin.php");
    exit();
}
include 'role_helper.php';

include 'Residents_DB.php';

// ── Head of Family filter ─────────────────────────────────────────────────────
$selected_head = isset($_GET['head']) ? mysqli_real_escape_string($conn, $_GET['head']) : '';

// Get all unique head of family names for dropdown
$heads_result = mysqli_query($conn, "SELECT DISTINCT CONCAT(head_first_name,' ',head_last_name) AS head_name FROM residents WHERE head_first_name != '' ORDER BY head_last_name");
$heads = [];
while ($h = mysqli_fetch_assoc($heads_result)) {
    $heads[] = $h['head_name'];
}

// ── Age Bracket Query ─────────────────────────────────────────────────────────
// Each bracket: label, min age, max age (null = no upper limit)
$age_brackets = [
    ['Under 5 Years Old',    0,  4],
    ['5 – 9 Years Old',      5,  9],
    ['10 – 14 Years Old',   10, 14],
    ['15 – 19 Years Old',   15, 19],
    ['20 – 24 Years Old',   20, 24],
    ['25 – 29 Years Old',   25, 29],
    ['30 – 34 Years Old',   30, 34],
    ['35 – 39 Years Old',   35, 39],
    ['40 – 45 Years Old',   40, 45],
    ['45 – 49 Years Old',   45, 49],
    ['50 – 54 Years Old',   50, 54],
    ['55 – 59 Years Old',   55, 59],
    ['60 – 64 Years Old',   60, 64],
    ['65 – 69 Years Old',   65, 69],
    ['70 – 74 Years Old',   70, 74],
    ['75 – 79 Years Old',   75, 79],
    ['80 Years Old and Above', 80, null],
];

$head_filter = $selected_head ? "AND CONCAT(head_first_name,' ',head_last_name) = '$selected_head'" : '';

function get_bracket_counts($conn, $min, $max, $head_filter) {
    $age_cond = $max !== null
        ? "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN $min AND $max"
        : "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= $min";

    $male    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE $age_cond AND LOWER(gender)='male' AND is_hidden=0 $head_filter"))['c'];
    $female  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE $age_cond AND LOWER(gender)='female' AND is_hidden=0 $head_filter"))['c'];
    $citizen = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE $age_cond AND LOWER(citizenship)='filipino' AND is_hidden=0 $head_filter"))['c'];
    return [$male, $female, $citizen];
}

// Build bracket data
$bracket_data = [];
$total_male = $total_female = $total_citizen = 0;
foreach ($age_brackets as [$label, $min, $max]) {
    [$m, $f, $c] = get_bracket_counts($conn, $min, $max, $head_filter);
    $bracket_data[] = ['label' => $label, 'male' => $m, 'female' => $f, 'citizen' => $c];
    $total_male    += $m;
    $total_female  += $f;
    $total_citizen += $c;
}

// ── Sector Counts ─────────────────────────────────────────────────────────────
function sector_count($conn, $where, $head_filter) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE $where AND is_hidden=0 $head_filter");
    return mysqli_fetch_assoc($r)['c'];
}

$labor_force  = sector_count($conn, "employment_status = 'Employed'", $head_filter);
$unemployed   = sector_count($conn, "employment_status != 'Employed'", $head_filter);
$osc_children = sector_count($conn, "out_of_school_youth = 'Yes' AND TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 6 AND 14", $head_filter);
$osc_youth    = sector_count($conn, "out_of_school_youth = 'Yes' AND TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 15 AND 24", $head_filter);
$pwd          = sector_count($conn, "pwd_status = 'Yes'", $head_filter);
$solo_parent  = sector_count($conn, "solo_parent_status = 'Yes'", $head_filter);
$ofw          = sector_count($conn, "occupation LIKE '%OFW%' OR employer LIKE '%abroad%'", $head_filter);

$sectors = [
    ['Labor Force',                        $labor_force],
    ['Unemployed',                         $unemployed],
    ['Out of School Children (6–14 yrs)', $osc_children],
    ['Out of School Youth (15–24 yrs)',   $osc_youth],
    ['PWD',                                $pwd],
    ['Solo Parent',                        $solo_parent],
    ['OFW',                                $ofw],
];

// ── Civil Status ──────────────────────────────────────────────────────────────
$civil_statuses = ['Single', 'Married', 'Widowed', 'Divorced', 'Annulled'];
$civil_data = [];
$cs_total_male = $cs_total_female = 0;
foreach ($civil_statuses as $cs) {
    $m = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE marital_status='$cs' AND LOWER(gender)='male' AND is_hidden=0 $head_filter"))['c'];
    $f = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE marital_status='$cs' AND LOWER(gender)='female' AND is_hidden=0 $head_filter"))['c'];
    $civil_data[] = ['label' => $cs, 'male' => $m, 'female' => $f];
    $cs_total_male   += $m;
    $cs_total_female += $f;
}

// ── Grand totals ──────────────────────────────────────────────────────────────
$grand_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM residents WHERE is_hidden=0 $head_filter"))['c'];
$total_households = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT CONCAT(head_first_name,' ',head_last_name)) AS c FROM residents WHERE head_first_name != '' AND is_hidden=0 $head_filter"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>RBI Report – Barangay 410</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy:   #0f172a;
      --blue:   #3b82f6;
      --teal:   #14b8a6;
      --bg:     #f8fafc;
      --card:   #ffffff;
      --border: #e2e8f0;
      --text:   #0f172a;
      --muted:  #64748b;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* NAV */
    nav {
      background: var(--navy); height: 60px;
      display: flex; align-items: center; padding: 0 1.5rem;
      justify-content: space-between; position: sticky; top: 0; z-index: 100;
    }
    .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .nav-logo {
      width: 34px; height: 34px; border-radius: 8px;
      background: linear-gradient(135deg, var(--blue), var(--teal));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 800; color: #fff;
    }
    .nav-title { font-size: 14px; font-weight: 600; color: #fff; }
    .nav-back {
      display: flex; align-items: center; gap: 7px;
      color: rgba(255,255,255,0.7); text-decoration: none; font-size: 13px;
      padding: 6px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);
      transition: all 0.2s;
    }
    .nav-back:hover { background: rgba(255,255,255,0.1); color: #fff; }

    /* PAGE HEADER */
    .page-top {
      background: var(--navy); padding: 1.75rem 2rem 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .page-top h1 { color: #fff; font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; }
    .page-top p  { color: rgba(255,255,255,0.5); font-size: 13px; margin-top: 4px; }

    /* MAIN */
    main { padding: 1.75rem 2rem; max-width: 1200px; margin: 0 auto; }

    /* TOOLBAR */
    .toolbar {
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }
    .toolbar select, .toolbar input {
      border: 1px solid var(--border); border-radius: 8px;
      padding: 8px 12px; font-size: 13px; font-family: 'Inter', sans-serif;
      color: var(--text); background: var(--card); outline: none;
    }
    .toolbar select:focus { border-color: var(--blue); }
    .btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 8px 18px; border-radius: 8px; border: none;
      font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 500;
      cursor: pointer; transition: all 0.2s; text-decoration: none;
    }
    .btn-primary { background: var(--blue); color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-outline { background: var(--card); color: var(--text); border: 1px solid var(--border); }
    .btn-outline:hover { background: var(--bg); }
    .ml-auto { margin-left: auto; }

    /* SUMMARY CARDS */
    .summary-row {
      display: grid; grid-template-columns: repeat(5, 1fr);
      gap: 10px; margin-bottom: 1.75rem;
    }
    .sum-card {
      background: var(--card); border: 1px solid var(--border);
      border-radius: 12px; padding: 1rem; text-align: center;
    }
    .sum-num { font-size: 28px; font-weight: 700; }
    .sum-num.blue   { color: var(--blue); }
    .sum-num.teal   { color: var(--teal); }
    .sum-num.navy   { color: var(--navy); }
    .sum-num.purple { color: #8b5cf6; }
    .sum-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; }

    /* SECTION */
    .section-title {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.09em; color: var(--muted); margin-bottom: 10px;
      display: flex; align-items: center; gap: 8px;
    }
    .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

    /* RBI TABLE */
    .rbi-card {
      background: var(--card); border: 1px solid var(--border);
      border-radius: 14px; overflow: hidden; margin-bottom: 1.5rem;
    }
    .rbi-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rbi-table th {
      background: var(--navy); color: #fff; padding: 10px 16px;
      text-align: center; font-size: 11px; text-transform: uppercase;
      letter-spacing: 0.07em; font-weight: 600;
    }
    .rbi-table th.left { text-align: left; }
    .rbi-table td {
      padding: 9px 16px; border-bottom: 1px solid var(--border);
      text-align: center; color: var(--text);
    }
    .rbi-table td.label { text-align: left; color: var(--text); font-weight: 500; }
    .rbi-table td.group-label {
      text-align: left; font-weight: 700; font-size: 11px;
      text-transform: uppercase; letter-spacing: 0.06em;
      background: #f1f5f9; color: var(--muted); padding: 7px 16px;
    }
    .rbi-table tr:last-child td { border-bottom: none; }
    .rbi-table tr:hover td:not(.group-label) { background: #f8fafc; }
    .rbi-table .total-row td {
      font-weight: 700; background: #eff6ff; color: var(--navy);
      border-top: 2px solid var(--blue);
    }
    .num-cell { font-variant-numeric: tabular-nums; }

    /* SECTOR TABLE */
    .sector-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .sector-table th {
      background: var(--navy); color: #fff; padding: 10px 16px;
      text-align: left; font-size: 11px; text-transform: uppercase;
      letter-spacing: 0.07em; font-weight: 600;
    }
    .sector-table th.right { text-align: right; }
    .sector-table td { padding: 10px 16px; border-bottom: 1px solid var(--border); }
    .sector-table tr:last-child td { border-bottom: none; }
    .sector-table tr:hover td { background: #f8fafc; }
    .bar-wrap { display: flex; align-items: center; gap: 10px; }
    .bar-bg { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 3px; background: var(--blue); transition: width 0.6s ease; }
    .bar-num { font-size: 13px; font-weight: 600; color: var(--text); min-width: 28px; text-align: right; }

    /* 2-col grid for sector + civil */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 1.5rem; }
    @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }

    /* PRINT STYLES */
    .print-digital-watermark { display: none !important; }
    @media print {
      nav, .topbar, .sidebar, .sidebar-overlay, .page-hero, .toolbar, .btn { display: none !important; }
      body { background: #fff; font-size: 11px; }
      main { padding: 0; max-width: 100%; }
      .rbi-card { border: 1px solid #ccc; border-radius: 0; box-shadow: none; margin-bottom: 12px; }
      .summary-row { margin-bottom: 12px; }
      .two-col { gap: 10px; margin-bottom: 12px; }

      .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 16px;
        border-bottom: 2px solid #000;
        padding-bottom: 12px;
      }

      .print-seal-watermark {
        display: block !important;
        position: fixed;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        width: 90mm; height: 90mm;
        opacity: 0.15;
        z-index: 0;
        pointer-events: none;
      }

      .print-digital-watermark {
        display: flex !important;
        position: fixed;
        left: 0; top: 0; bottom: 0;
        width: 16mm;
        align-items: center; justify-content: center;
        overflow: visible;
        pointer-events: none; z-index: 9999;
      }
      .print-digital-watermark span {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        font-size: 8mm;
        font-weight: bold;
        color: rgba(72, 72, 72, 0.69);
        font-family: 'Times New Roman', Times, serif;
        letter-spacing: .05em;
        white-space: nowrap;
      }
    }
    .print-header { display: none; }
    .print-seal-watermark { display: none; }
    /* centered text-only header, like eBlotter PDF */
    .print-header-text { text-align: center; }
    .print-header-text p  { font-size: 10px; margin: 1px 0; color: #222; font-family: 'Times New Roman', serif; }
    .print-header-text .brgy-name { font-size: 13px; font-weight: 700; font-family: 'Times New Roman', serif; }
    .print-header-text .doc-title { font-size: 14px; font-weight: 700; margin-top: 6px; letter-spacing: 1px; font-family: 'Times New Roman', serif; }
    .print-header-text .doc-sub   { font-size: 10px; color: #444; font-family: 'Times New Roman', serif; }
    .topbar{position:sticky;top:0;z-index:200}
  </style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div><div class="topbar-sub">RBI Report</div></div></a>
  <div class="topbar-right" style="margin-left:auto">
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain):?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);<?php elseif($is_secretary):?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);<?php else:?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif;?>"><i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head"><div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div></div><button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button></div>
  <div style="padding:14px 12px 6px"><button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer"><i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i></button></div>
  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register):?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest):?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif;?>
  </div>
  <div class="sidebar-footer"></div>
</aside>



<?php
$_rbi_brgy = defined('BRGY_NAME')     ? htmlspecialchars(BRGY_NAME)     : 'Barangay 410';
$_rbi_city = defined('BRGY_CITY')     ? htmlspecialchars(BRGY_CITY)     : 'City of Manila';
$_rbi_dist = defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : 'IV';
$_btn_ghost = 'display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.1);backdrop-filter:blur(4px)';
$_btn_active = 'display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid #3b82f6;color:#fff;background:#3b82f6';
?>
<div class="page-hero" style="background:linear-gradient(to right,rgba(15,23,42,.9),rgba(15,23,42,.65)),url('images/Barangay_officials_410.png') center 60%/cover no-repeat;padding:2.5rem 2rem">
  <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:1.5rem">
  <div style="width:90px;height:90px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.2)"><img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover"></div>
  <div>
    <h1 style="font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:#fff;margin:0 0 .25rem"><i class="fas fa-clipboard-list" style="margin-right:.5rem;opacity:.8"></i>RBI Report</h1>
    <p style="color:rgba(255,255,255,.65);font-size:.84rem;margin:0 0 1.25rem"><?= $_rbi_brgy ?> Census Management &mdash; <?= $_rbi_city ?>, District <?= $_rbi_dist ?></p>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <a href="Home.php" style="<?= $_btn_ghost ?>"><i class="fas fa-house"></i> Home</a>
      <a href="Display_List.php" style="<?= $_btn_ghost ?>"><i class="fas fa-users"></i> Residents</a>
      <a href="RBI.php" style="<?= $_btn_active ?>"><i class="fas fa-clipboard-list"></i> RBI Report</a>
    </div>
  </div></div>
</div>

<main>
  <!-- Seal watermark — centered on page like eBlotter PDF (print only) -->
  <img src="images/brgy410_logo.png" alt="" class="print-seal-watermark">

  <!-- Print header — centered text only, no side logos (print only) -->
  <div class="print-header">
    <div class="print-header-text">
      <p>Republic of the Philippines</p>
      <p><?= defined('BRGY_CITY') ? htmlspecialchars(BRGY_CITY) : 'City of Manila' ?></p>
      <p class="brgy-name"><?= defined('BRGY_FULLNAME') ? htmlspecialchars(BRGY_FULLNAME) : 'Barangay 410 Zone 42' ?></p>
      <p>District <?= defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : 'IV' ?></p>
      <div class="doc-title">REGISTER OF BARANGAY INHABITANTS</div>
      <div class="doc-sub">Official Census Report &nbsp;&middot;&nbsp; Printed: <?= date('F d, Y g:i A') ?></div>
      <?php if ($selected_head): ?>
      <div class="doc-sub">Head of Family: <?= htmlspecialchars($selected_head) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <form method="GET" style="display:contents">
      <select name="head" onchange="this.form.submit()">
        <option value="">All Households</option>
        <?php foreach ($heads as $h): ?>
          <option value="<?= htmlspecialchars($h) ?>" <?= $selected_head === $h ? 'selected' : '' ?>>
            <?= htmlspecialchars($h) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="ml-auto" style="display:flex;gap:10px">
      <a href="?" class="btn btn-outline"><i class="fas fa-rotate-left"></i> Reset</a>
      <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print RBI</button>
    </div>
  </div>

  <!-- SUMMARY CARDS -->
  <div class="summary-row">
    <div class="sum-card">
      <div class="sum-num blue"><?= $grand_total > 0 ? number_format($grand_total) : "–" ?></div>
      <div class="sum-lbl">Total Inhabitants</div>
    </div>
    <div class="sum-card">
      <div class="sum-num teal"><?= number_format($total_male) ?></div>
      <div class="sum-lbl">Male</div>
    </div>
    <div class="sum-card">
      <div class="sum-num purple"><?= number_format($total_female) ?></div>
      <div class="sum-lbl">Female</div>
    </div>
    <div class="sum-card">
      <div class="sum-num navy"><?= number_format($total_citizen) ?></div>
      <div class="sum-lbl">Filipino Citizens</div>
    </div>
    <div class="sum-card">
      <div class="sum-num" style="color:#f59e0b"><?= number_format($total_households) ?></div>
      <div class="sum-lbl">Households</div>
    </div>
  </div>

  <!-- AGE BRACKET TABLE -->
  <div class="section-title">Age Bracket Category</div>
  <div class="rbi-card">
    <table class="rbi-table">
      <thead>
        <tr>
          <th class="left" style="width:50%">Age Bracket</th>
          <th>Male</th>
          <th>Female</th>
          <th>Filipino Citizen</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bracket_data as $row): ?>
        <tr>
          <td class="label"><?= $row['label'] ?></td>
          <td class="num-cell"><?= $row['male'] > 0 ? $row['male'] : '' ?></td>
          <td class="num-cell"><?= $row['female'] > 0 ? $row['female'] : '' ?></td>
          <td class="num-cell"><?= $row['citizen'] > 0 ? $row['citizen'] : '' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td class="label">TOTAL</td>
          <td class="num-cell"><?= $total_male ?></td>
          <td class="num-cell"><?= $total_female ?></td>
          <td class="num-cell"><?= $total_citizen ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- SECTOR + CIVIL STATUS side by side -->
  <div class="two-col">

    <!-- BY SECTOR -->
    <div>
      <div class="section-title">By Sector</div>
      <div class="rbi-card">
        <?php $max_sector = max(array_column($sectors, 1)) ?: 1; ?>
        <table class="sector-table">
          <thead>
            <tr>
              <th>Sector</th>
              <th class="right">Count</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sectors as [$label, $count]): ?>
            <tr>
              <td>
                <div style="font-weight:500;margin-bottom:4px"><?= $label ?></div>
                <div class="bar-wrap">
                  <div class="bar-bg">
                    <div class="bar-fill" style="width:<?= $max_sector > 0 ? round(($count/$max_sector)*100) : 0 ?>%"></div>
                  </div>
                </div>
              </td>
              <td style="text-align:right">
                <span class="bar-num"><?= $count > 0 ? $count : '' ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- CIVIL STATUS -->
    <div>
      <div class="section-title">Civil Status</div>
      <div class="rbi-card">
        <table class="rbi-table">
          <thead>
            <tr>
              <th class="left">Status</th>
              <th>Male</th>
              <th>Female</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($civil_data as $row): ?>
            <tr>
              <td class="label"><?= $row['label'] ?></td>
              <td class="num-cell"><?= $row['male'] > 0 ? $row['male'] : '' ?></td>
              <td class="num-cell"><?= $row['female'] > 0 ? $row['female'] : '' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td class="label">TOTAL</td>
              <td class="num-cell"><?= $cs_total_male ?></td>
              <td class="num-cell"><?= $cs_total_female ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- FOOTER NOTE -->
  <p style="font-size:11px;color:var(--muted);text-align:center;padding:1rem 0">
    &copy; <?= date('Y') ?> Barangay 410 Census Management System &nbsp;·&nbsp; Manila City &nbsp;·&nbsp;
    Report generated on <?= date('F d, Y \a\t g:i A') ?>
  </p>
</main>
<script>
function openSettings(){document.getElementById('settingsOverlay').style.display='block';document.getElementById('settingsDrawer').style.right='0';document.body.style.overflow='hidden';if(typeof closeSidebar==='function')closeSidebar();}
function closeSettings(){document.getElementById('settingsOverlay').style.display='none';document.getElementById('settingsDrawer').style.right='-360px';document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSettings();});
let darkMode=localStorage.getItem('rbi_dark')==='1';
function applyDark(on){document.body.classList.toggle('dark-mode',on);const th=document.getElementById('darkThumb'),tg=document.getElementById('darkToggle');if(th)th.style.left=on?'21px':'3px';if(tg)tg.style.background=on?'#3b82f6':'#475569';document.documentElement.style.setProperty('--bg',on?'#0f172a':'#f8fafc');document.documentElement.style.setProperty('--card',on?'#1e293b':'#ffffff');document.documentElement.style.setProperty('--border',on?'#334155':'#e2e8f0');document.documentElement.style.setProperty('--text',on?'#e2e8f0':'#0f172a');}
applyDark(darkMode);
function toggleDarkMode(){darkMode=!darkMode;localStorage.setItem('rbi_dark',darkMode?'1':'0');applyDark(darkMode);}
let zoomLvl=parseFloat(localStorage.getItem('rbi_zoom')||'1');
function applyZoom(){document.body.style.zoom=zoomLvl;const l=document.getElementById('zoomLabel');if(l)l.textContent=Math.round(zoomLvl*100)+'%';}
applyZoom();
function pageZoom(f){zoomLvl=parseFloat(Math.min(Math.max(zoomLvl*f,0.6),1.5).toFixed(2));localStorage.setItem('rbi_zoom',zoomLvl);applyZoom();}
function resetZoom(){zoomLvl=1;localStorage.setItem('rbi_zoom','1');applyZoom();}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
</script>

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
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Appearance</div>
    <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px"><div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-circle-half-stroke" style="color:#94a3b8;font-size:13px"></i></div><div><div style="font-size:13px;font-weight:600;color:#fff">Dark Mode</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Toggle dark/light theme</div></div></div>
      <button id="darkToggle" onclick="toggleDarkMode()" style="width:42px;height:24px;border-radius:12px;background:#475569;border:none;cursor:pointer;position:relative;transition:background .25s;flex-shrink:0"><span id="darkThumb" style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;transition:left .25s"></span></button>
    </div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Actions</div>
    <button onclick="window.print()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print Page</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print current view</div></div>
    </button>
  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div><div style="font-size:11px;color:rgba(244,63,94,.6)">End your session</div></div>
    </a>
  </div>
</div>
<div class="print-digital-watermark" style="display:none"><span>DIGITAL COPY - NOT VALID IF UNSIGNED</span></div>
</body>
</html>







