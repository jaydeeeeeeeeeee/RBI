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

// ── Labor Sector Breakdown ────────────────────────────────────────────────────
$labor_by_employer = [];
$lbe_res = mysqli_query($conn, "SELECT employer, COUNT(*) AS c FROM residents WHERE employment_status='Employed' AND employer IS NOT NULL AND employer!='' AND is_hidden=0 $head_filter GROUP BY employer ORDER BY c DESC");
while ($lbe = mysqli_fetch_assoc($lbe_res)) $labor_by_employer[] = $lbe;
$employed_no_employer = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM residents WHERE employment_status='Employed' AND (employer IS NULL OR employer='') AND is_hidden=0 $head_filter"))['c'];

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
  <link rel="stylesheet" href="assets/css/main.css"/>
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
      display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr));
      gap: 12px; margin-bottom: 1.75rem;
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
    @media print {
      nav, .page-top, .toolbar, .btn { display: none !important; }
      body { background: #fff; font-size: 11px; }
      main { padding: 0; max-width: 100%; }
      .rbi-card { border: 1px solid #ccc; border-radius: 0; box-shadow: none; margin-bottom: 12px; }
      .summary-row { margin-bottom: 12px; }
      .two-col { gap: 10px; margin-bottom: 12px; }
      .print-header { display: block !important; }
    }
    .print-header {
      display: none;
      text-align: center; margin-bottom: 16px;
    }
    .print-header h2 { font-size: 16px; font-weight: 700; }
    .print-header p  { font-size: 12px; color: #555; }
  </style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0"><div class="topbar-logo">410</div><div><div class="topbar-name">Barangay 410</div><div class="topbar-sub">RBI Report</div></div></a>
  <div style="display:flex;align-items:center;border-left:1px solid rgba(255,255,255,.12);padding-left:14px;min-width:0">
    <span style="font-size:13px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;white-space:nowrap"><i class="fas fa-clipboard-list" style="opacity:.8;margin-right:5px"></i> RBI Report</span>
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
    <a href="RBI.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest):?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="../eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif;?>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<div class="page-top">
  <h1><i class="fas fa-clipboard-list" style="margin-right:8px;opacity:0.7"></i>Register of Barangay Inhabitants (RBI)</h1>
  <p>Barangay 410 · Manila City · Official Census Report</p>
</div>

<main>
  <!-- Print header (hidden on screen, shown when printing) -->
  <div class="print-header">
    <h2>BARANGAY 410 REGISTER OF BARANGAY INHABITANTS</h2>
    <p>Manila City &nbsp;·&nbsp; Printed on: <?= date('F d, Y g:i A') ?></p>
    <?php if ($selected_head): ?>
      <p>Head of Family: <?= htmlspecialchars($selected_head) ?></p>
    <?php endif; ?>
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

  <!-- LABOR SECTOR BREAKDOWN -->
  <div class="section-title">Labor Sector Breakdown</div>
  <div class="rbi-card" style="padding:1rem">
    <?php if (!empty($labor_by_employer) || $employed_no_employer > 0): ?>
      <div style="max-height:220px;overflow-y:auto">
        <canvas id="laborSectorChart"></canvas>
      </div>
      <div style="display:flex;gap:2rem;justify-content:center;margin-top:0.75rem;font-size:11px;color:var(--muted);flex-wrap:wrap">
        <span><strong style="color:#0f172a"><?= number_format($labor_force) ?></strong> Employed</span>
        <span><strong style="color:#f59e0b"><?= number_format($unemployed) ?></strong> Unemployed</span>
        <?php $total_pop = $grand_total > 0 ? $grand_total : 1; ?>
        <span><strong style="color:#14b8a6"><?= round(($labor_force/$total_pop)*100,1) ?>%</strong> Employment Rate</span>
      </div>
    <?php else: ?>
      <p style="text-align:center;color:var(--muted);padding:1rem">No employer data available.</p>
    <?php endif; ?>
  </div>

  <!-- FOOTER NOTE -->
  <p style="font-size:11px;color:var(--muted);text-align:center;padding:1rem 0">
    &copy; <?= date('Y') ?> Barangay 410 Census Management System &nbsp;·&nbsp; Manila City &nbsp;·&nbsp;
    Report generated on <?= date('F d, Y \a\t g:i A') ?>
  </p>
</main>
<script>
// ── Labor Sector Chart ────────────────────────────────────────────────────────
(function(){
  var el = document.getElementById('laborSectorChart');
  if(!el) return;
  Chart.register(ChartDataLabels);
  var rowCount = <?= count($labor_by_employer) + ($employed_no_employer > 0 ? 1 : 0) ?>;
  el.height = Math.min(rowCount * 28 + 20, 200);
  var rawLabels = <?= json_encode(array_map(fn($r)=>$r['employer'], $labor_by_employer)) ?>;
  var rawCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'], $labor_by_employer)) ?>;
  <?php if($employed_no_employer > 0): ?>
  rawLabels.push('(no employer listed)');
  rawCounts.push(<?= (int)$employed_no_employer ?>);
  <?php endif; ?>
  var palette=['#3b82f6','#14b8a6','#8b5cf6','#f59e0b','#ef4444','#10b981','#6366f1','#ec4899','#0ea5e9','#f97316','#84cc16','#a78bfa'];
  var colors = rawLabels.map(function(_,i){return palette[i%palette.length];});
  new Chart(el,{
    type:'bar',
    data:{
      labels:rawLabels,
      datasets:[{
        label:'Residents',
        data:rawCounts,
        backgroundColor:colors,
        borderRadius:4,
        borderSkipped:false,
        barThickness:14
      }]
    },
    options:{
      indexAxis:'y',
      responsive:true,
      plugins:{
        legend:{display:false},
        datalabels:{
          anchor:'end',align:'end',
          color:'#0f172a',font:{size:11,weight:600},
          formatter:function(v){return v;}
        },
        tooltip:{callbacks:{label:function(c){return ' '+c.parsed.x+' residents';}}}
      },
      scales:{
        x:{grid:{color:'#f1f5f9'},ticks:{font:{size:11}},beginAtZero:true},
        y:{grid:{display:false},ticks:{font:{size:11,weight:500},color:'#0f172a'}}
      }
    }
  });
})();

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
function openSettings(){}
</script>
</body>
</html>