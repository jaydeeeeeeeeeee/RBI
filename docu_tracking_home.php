<?php
/**
 * docu_tracking_home.php — Document Tracking Homepage
 * Place this in your root folder (same level as data_tracking.php)
 * Flow: Home.php (dashboard) → docu_tracking_home.php → data_tracking.php
 */
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin.php"); exit(); }
include 'Residents_DB.php';
include 'role_helper.php';
include 'signatory_helper.php';

$_dt_brgy = defined('BRGY_NAME')     ? htmlspecialchars(BRGY_NAME)     : 'Barangay 410';
$_dt_city = defined('BRGY_CITY')     ? htmlspecialchars(BRGY_CITY)     : 'City of Manila';
$_dt_dist = defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : 'IV';

// ── Stats ─────────────────────────────────────────────────────────────────

// Certificate request counts
$cert_counts = [];
foreach (['Pending','Approved','Released','Rejected'] as $s) {
    $cert_counts[$s] = (int)$conn->query("SELECT COUNT(*) AS c FROM certificate_requests WHERE status='$s'")->fetch_assoc()['c'];
}
$cert_total = array_sum($cert_counts);

// Document request counts
$doc_counts = [];
foreach (['Pending','Processing','Ready','Released','Rejected'] as $s) {
    $doc_counts[$s] = (int)$conn->query("SELECT COUNT(*) AS c FROM document_requests WHERE status='$s'")->fetch_assoc()['c'];
}
$doc_total = array_sum($doc_counts);

// Audit log count (last 30 days)
$audit_count = (int)$conn->query("SELECT COUNT(*) AS c FROM audit_log WHERE performed_at >= NOW() - INTERVAL 30 DAY")->fetch_assoc()['c'];

// Recent certificate requests (5)
$recent_certs = $conn->query("
    SELECT cr.id, cr.status, cr.requested_at, cr.purpose,
           r.first_name, r.last_name,
           ct.template_name
    FROM certificate_requests cr
    JOIN residents r  ON cr.resident_id  = r.id
    JOIN certificate_templates ct ON cr.template_id = ct.id
    ORDER BY cr.requested_at DESC LIMIT 5
");

// Recent document requests (5)
$recent_docs = $conn->query("
    SELECT id, request_code, resident_name, document_type, status, requested_at
    FROM document_requests
    ORDER BY requested_at DESC LIMIT 5
");

// Monthly cert requests (last 6 months)
$monthly_cert  = [];
$monthly_doc   = [];
$monthly_labels = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $m  = date('m', $ts);
    $y  = date('Y', $ts);
    $monthly_labels[] = date('M', $ts);
    $monthly_cert[] = (int)$conn->query("SELECT COUNT(*) AS c FROM certificate_requests WHERE MONTH(requested_at)=$m AND YEAR(requested_at)=$y")->fetch_assoc()['c'];
    $monthly_doc[]  = (int)$conn->query("SELECT COUNT(*) AS c FROM document_requests WHERE MONTH(requested_at)=$m AND YEAR(requested_at)=$y")->fetch_assoc()['c'];
}

// Most requested certificate types
$top_certs = $conn->query("
    SELECT ct.template_name, COUNT(*) AS c
    FROM certificate_requests cr
    JOIN certificate_templates ct ON cr.template_id = ct.id
    GROUP BY ct.template_name ORDER BY c DESC LIMIT 5
");

// Resolution rate for cert requests
$cert_res_rate = $cert_total > 0 ? round(($cert_counts['Released'] / $cert_total) * 100) : 0;
$doc_res_rate  = $doc_total  > 0 ? round(($doc_counts['Released']  / $doc_total)  * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Document Tracking — <?= $_dt_brgy ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="assets/css/main.css?v=<?= filemtime(__DIR__.'/assets/css/main.css') ?>"/>
  <style>
    :root {
      --blue:#3b82f6; --blue-lt:#eff6ff; --blue-dk:#1d4ed8;
      --green:#22c55e; --green-lt:#f0fdf4; --green-dk:#15803d;
      --amber:#f59e0b; --amber-lt:#fffbeb; --amber-dk:#92400e;
      --purple:#8b5cf6; --purple-lt:#f5f3ff;
      --red:#ef4444; --red-lt:#fef2f2; --red-dk:#b91c1c;
      --teal:#14b8a6; --teal-lt:#f0fdfa;
      --navy:#1b263b;
    }

    /* ── Hero ── */
    .page-hero {
      background: linear-gradient(to right,rgba(15,23,42,.88),rgba(15,23,42,.55)),
        url('images/Barangay_officials_410.png') center center/cover no-repeat;
      padding: 2.5rem 2rem 2rem;
    }
    .hero-inner { max-width: 1200px; margin: 0 auto; }
    .hero-inner h1 { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:#fff; margin:0 0 .2rem; }
    .hero-inner p  { color:rgba(255,255,255,.6); font-size:.85rem; margin:0 0 1.25rem; }
    .hero-nav { display:flex; gap:.5rem; flex-wrap:wrap; }
    .hero-nav a {
      display:inline-flex; align-items:center; gap:6px;
      padding:7px 16px; border-radius:8px; font-size:.8rem; font-weight:600; text-decoration:none;
    }
    .hn-ghost  { border:1.5px solid rgba(255,255,255,.3); color:#fff; background:rgba(255,255,255,.08); }
    .hn-active { border:1.5px solid var(--blue); color:#fff; background:var(--blue); }
    .hn-ghost:hover { background:rgba(255,255,255,.18); }

    main { padding: 1.75rem 2rem; max-width: 1200px; margin: 0 auto; }

    /* ── Section title ── */
    .sec-title {
      font-family:'Syne',sans-serif; font-size:.95rem; font-weight:800;
      color:#0f172a; margin-bottom:.85rem;
      display:flex; align-items:center; gap:.6rem;
    }
    .sec-title::after { content:''; flex:1; height:1px; background:#e2e8f0; }

    /* ── Module cards ── */
    .module-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:1.75rem; }
    @media(max-width:900px) { .module-grid { grid-template-columns:1fr 1fr; } }
    @media(max-width:560px) { .module-grid { grid-template-columns:1fr; } }

    .module-card {
      background:#fff; border:1px solid #e2e8f0; border-radius:12px;
      padding:1.25rem; display:flex; flex-direction:column; gap:.6rem;
      text-decoration:none; transition:all .2s; position:relative; overflow:hidden;
    }
    .module-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.1); border-color:var(--blue); }
    .module-card-icon {
      width:40px; height:40px; border-radius:10px;
      display:flex; align-items:center; justify-content:center; font-size:18px;
    }
    .module-card-title { font-family:'Syne',sans-serif; font-size:1rem; font-weight:800; color:#0f172a; }
    .module-card-desc  { font-size:12px; color:#64748b; line-height:1.5; }
    .module-card-meta  { display:flex; align-items:center; justify-content:space-between; margin-top:.25rem; }
    .module-card-count { font-size:22px; font-weight:800; font-family:'Syne',sans-serif; }
    .module-card-badge {
      font-size:11px; font-weight:700; padding:3px 10px;
      border-radius:99px; display:inline-flex; align-items:center; gap:4px;
    }
    .module-card-arrow { position:absolute; bottom:1rem; right:1rem; font-size:13px; color:#cbd5e1; }
    .module-card:hover .module-card-arrow { color:var(--blue); }

    .mc-blue   .module-card-icon { background:var(--blue-lt); color:var(--blue); }
    .mc-blue   .module-card-count { color:var(--blue-dk); }
    .mc-purple .module-card-icon { background:var(--purple-lt); color:var(--purple); }
    .mc-purple .module-card-count { color:#6d28d9; }
    .mc-teal   .module-card-icon { background:var(--teal-lt); color:var(--teal); }
    .mc-teal   .module-card-count { color:#0f766e; }

    /* ── Quick stat chips ── */
    .stat-chips { display:flex; gap:6px; flex-wrap:wrap; margin-top:.25rem; }
    .stat-chip {
      font-size:11px; font-weight:600; padding:2px 8px;
      border-radius:99px; display:inline-flex; align-items:center; gap:3px;
    }
    .sc-amber  { background:var(--amber-lt); color:var(--amber-dk); }
    .sc-purple { background:var(--purple-lt); color:#6d28d9; }
    .sc-green  { background:var(--green-lt); color:var(--green-dk); }
    .sc-red    { background:var(--red-lt); color:var(--red-dk); }
    .sc-blue   { background:var(--blue-lt); color:var(--blue-dk); }

    /* ── Content grid (chart + recent) ── */
    .content-grid { display:grid; grid-template-columns:1.2fr 1fr; gap:14px; margin-bottom:1.75rem; }
    @media(max-width:800px) { .content-grid { grid-template-columns:1fr; } }

    /* ── Cards ── */
    .card {
      background:#fff; border:1px solid #e2e8f0; border-radius:12px;
      padding:1.1rem 1.25rem;
    }
    .card-title { font-family:'Syne',sans-serif; font-size:.88rem; font-weight:800; color:#0f172a; margin-bottom:2px; }
    .card-sub   { font-size:11px; color:#94a3b8; margin-bottom:.85rem; }

    /* ── Recent table ── */
    .mini-table { width:100%; border-collapse:collapse; font-size:12.5px; }
    .mini-table th {
      text-align:left; font-size:10px; font-weight:700; color:#94a3b8;
      text-transform:uppercase; letter-spacing:.05em;
      padding:6px 8px; border-bottom:1px solid #f1f5f9;
    }
    .mini-table td { padding:8px 8px; border-bottom:1px solid #f8fafc; color:#0f172a; }
    .mini-table tr:last-child td { border-bottom:none; }
    .mini-table tr:hover td { background:#f8fafc; }
    .badge {
      display:inline-flex; align-items:center; font-size:10px; font-weight:700;
      padding:2px 8px; border-radius:99px;
    }
    .badge-pending    { background:var(--amber-lt); color:var(--amber-dk); }
    .badge-approved   { background:var(--purple-lt); color:#6d28d9; }
    .badge-released   { background:var(--green-lt); color:var(--green-dk); }
    .badge-rejected   { background:var(--red-lt); color:var(--red-dk); }
    .badge-processing { background:var(--blue-lt); color:var(--blue-dk); }
    .badge-ready      { background:var(--purple-lt); color:#6d28d9; }

    /* ── Top certs ── */
    .top-cert-row { display:flex; align-items:center; gap:.6rem; margin-bottom:.65rem; }
    .top-cert-row:last-child { margin-bottom:0; }
    .top-cert-bar-wrap { flex:1; height:8px; background:#f1f5f9; border-radius:99px; overflow:hidden; }
    .top-cert-bar { height:100%; border-radius:99px; background:var(--blue); }
    .top-cert-name { font-size:12px; color:#0f172a; width:165px; flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .top-cert-num  { font-size:12px; font-weight:700; color:#64748b; width:28px; text-align:right; flex-shrink:0; }

    /* ── Chart legend ── */
    .chart-legend { display:flex; gap:12px; margin-bottom:10px; flex-wrap:wrap; }
    .chart-legend span { display:flex; align-items:center; gap:4px; font-size:11px; color:#64748b; }
    .legend-dot { width:9px; height:9px; border-radius:2px; flex-shrink:0; }

    /* ── View all link ── */
    .view-all-link { display:block; text-align:center; font-size:12px; font-weight:600; color:var(--blue); text-decoration:none; padding:.6rem; border-top:1px solid #f1f5f9; margin-top:.5rem; }
    .view-all-link:hover { background:#f8fafc; }

    /* ── Topbar fixes ── */
    .topbar { position:sticky; top:0; z-index:200; }
  </style>
</head>
<body>

<!-- ── Topbar (same as data_tracking.php) ── -->
<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover" alt="">
    </div>
    <div><div class="topbar-name"><?= $_dt_brgy ?></div></div>
  </a>
  <div class="topbar-right" style="margin-left:auto">
    <div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;
      <?php if($is_captain): ?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);
      <?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);
      <?php else: ?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif; ?>">
      <i class="fas <?= $rbadge['icon'] ?>" style="font-size:12px"></i>
    </div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<!-- Sidebar overlay + sidebar (copy from data_tracking.php) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar" style="overflow-y:auto">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover" alt="">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub"><?= $_dt_brgy ?> · <?= $_dt_city ?></div></div>
    </div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>

  <!-- Stats in sidebar -->
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
    <a href="RBI.php"               class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <a href="docu_tracking_home.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php"         class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php"    class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<!-- ── Hero ── -->
<div class="page-hero">
  <div class="hero-inner">
    <h1><i class="fas fa-database" style="margin-right:.5rem;opacity:.8"></i>Document Tracking</h1>
    <p><?= $_dt_brgy ?> &mdash; Certificate &amp; Document Request Management &mdash; <?= $_dt_city ?>, District <?= $_dt_dist ?></p>

  </div>
</div>

<main>

  <!-- ── Module entry cards ── -->
  <h2 class="sec-title"><i class="fas fa-th-large" style="color:var(--blue)"></i> Modules</h2>
  <div class="module-grid">

    <!-- Certificate Requests -->
    <a href="data_tracking.php?tab=cert_requests" class="module-card mc-blue">
      <div class="module-card-icon"><i class="fas fa-file-contract"></i></div>
      <div class="module-card-title">Certificate Requests</div>
      <div class="module-card-desc">Manage barangay clearances, certificates of residency, indigency, and more.</div>
      <div class="module-card-meta">
        <div class="module-card-count"><?= $cert_total ?></div>
        <?php if ($cert_counts['Pending'] > 0): ?>
        <span class="module-card-badge sc-amber"><i class="fas fa-clock"></i> <?= $cert_counts['Pending'] ?> pending</span>
        <?php else: ?>
        <span class="module-card-badge sc-green"><i class="fas fa-check"></i> All cleared</span>
        <?php endif; ?>
      </div>
      <div class="stat-chips">
        <span class="stat-chip sc-purple"><?= $cert_counts['Approved'] ?> approved</span>
        <span class="stat-chip sc-green"><?= $cert_counts['Released'] ?> released</span>
        <span class="stat-chip sc-red"><?= $cert_counts['Rejected'] ?> rejected</span>
      </div>
      <i class="fas fa-arrow-right module-card-arrow"></i>
    </a>

    <!-- Document Requests -->
    <a href="data_tracking.php?tab=doc_requests" class="module-card mc-purple">
      <div class="module-card-icon"><i class="fas fa-file-alt"></i></div>
      <div class="module-card-title">Document Requests</div>
      <div class="module-card-desc">Track all document requests with request codes, statuses, and release records.</div>
      <div class="module-card-meta">
        <div class="module-card-count"><?= $doc_total ?></div>
        <?php if ($doc_counts['Pending'] > 0): ?>
        <span class="module-card-badge sc-amber"><i class="fas fa-clock"></i> <?= $doc_counts['Pending'] ?> pending</span>
        <?php else: ?>
        <span class="module-card-badge sc-green"><i class="fas fa-check"></i> All cleared</span>
        <?php endif; ?>
      </div>
      <div class="stat-chips">
        <span class="stat-chip sc-blue"><?= $doc_counts['Processing'] ?> processing</span>
        <span class="stat-chip sc-purple"><?= $doc_counts['Ready'] ?> ready</span>
        <span class="stat-chip sc-green"><?= $doc_counts['Released'] ?> released</span>
      </div>
      <i class="fas fa-arrow-right module-card-arrow"></i>
    </a>

    <!-- Audit Log -->
    <a href="data_tracking.php?tab=audit" class="module-card mc-teal">
      <div class="module-card-icon"><i class="fas fa-history"></i></div>
      <div class="module-card-title">Audit Log</div>
      <div class="module-card-desc">View all CREATE, UPDATE, and DELETE actions performed on resident records.</div>
      <div class="module-card-meta">
        <div class="module-card-count"><?= $audit_count ?></div>
        <span class="module-card-badge sc-blue"><i class="fas fa-calendar"></i> Last 30 days</span>
      </div>
      <div class="stat-chips">
        <span class="stat-chip sc-blue">Full history available</span>
      </div>
      <i class="fas fa-arrow-right module-card-arrow"></i>
    </a>

  </div>

  <!-- ── Chart + Top Certs ── -->
  <h2 class="sec-title"><i class="fas fa-chart-bar" style="color:var(--blue)"></i> Analytics</h2>
  <div class="content-grid">

    <!-- Monthly requests chart -->
    <div class="card">
      <div class="card-title">Monthly requests</div>
      <div class="card-sub">Certificate &amp; document requests — last 6 months</div>
      <div class="chart-legend">
        <span><span class="legend-dot" style="background:#3b82f6"></span>Certificates</span>
        <span><span class="legend-dot" style="background:#8b5cf6"></span>Documents</span>
      </div>
      <div style="position:relative;height:200px;">
        <canvas id="monthlyChart" role="img" aria-label="Bar chart showing monthly certificate and document requests over the last 6 months">Monthly requests data.</canvas>
      </div>
    </div>

    <!-- Top certificate types -->
    <div class="card">
      <div class="card-title">Top certificate types</div>
      <div class="card-sub">Most requested certificates</div>
      <?php
      $top_arr = [];
      while ($row = $top_certs->fetch_assoc()) $top_arr[] = $row;
      $max_c = !empty($top_arr) ? max(array_column($top_arr, 'c')) : 1;
      $bar_colors = ['#3b82f6','#8b5cf6','#14b8a6','#f59e0b','#ef4444'];
      if (empty($top_arr)): ?>
        <div style="text-align:center;padding:2rem;color:#94a3b8;font-size:13px">
          <i class="fas fa-chart-bar" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3"></i>No data yet
        </div>
      <?php else: ?>
        <div style="margin-top:.5rem">
        <?php foreach ($top_arr as $i => $row): ?>
          <div class="top-cert-row">
            <div class="top-cert-name" title="<?= htmlspecialchars($row['template_name']) ?>"><?= htmlspecialchars($row['template_name']) ?></div>
            <div class="top-cert-bar-wrap">
              <div class="top-cert-bar" style="width:<?= round(($row['c'] / $max_c) * 100) ?>%;background:<?= $bar_colors[$i % 5] ?>"></div>
            </div>
            <div class="top-cert-num"><?= $row['c'] ?></div>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ── Recent activity ── -->
  <h2 class="sec-title"><i class="fas fa-clock-rotate-left" style="color:var(--blue)"></i> Recent Activity</h2>
  <div class="content-grid">

    <!-- Recent certificate requests -->
    <div class="card" style="padding-bottom:0">
      <div class="card-title">Recent certificate requests</div>
      <div class="card-sub">Latest 5 entries</div>
      <table class="mini-table">
        <thead>
          <tr>
            <th>Resident</th>
            <th>Certificate</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_certs->num_rows === 0): ?>
          <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:#94a3b8;font-size:12px">No records yet</td></tr>
          <?php else: while ($row = $recent_certs->fetch_assoc()):
            $sc = strtolower($row['status']);
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong></td>
            <td style="color:#64748b"><?= htmlspecialchars($row['template_name']) ?></td>
            <td><span class="badge badge-<?= $sc ?>"><?= $row['status'] ?></span></td>
            <td style="color:#94a3b8;font-size:11px;white-space:nowrap"><?= date('M d', strtotime($row['requested_at'])) ?></td>
          </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
      <a href="data_tracking.php?tab=cert_requests" class="view-all-link">View all certificate requests <i class="fas fa-arrow-right"></i></a>
    </div>

    <!-- Recent document requests -->
    <div class="card" style="padding-bottom:0">
      <div class="card-title">Recent document requests</div>
      <div class="card-sub">Latest 5 entries</div>
      <table class="mini-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Resident</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_docs->num_rows === 0): ?>
          <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:#94a3b8;font-size:12px">No records yet</td></tr>
          <?php else: while ($row = $recent_docs->fetch_assoc()):
            $sc = strtolower(str_replace(' ','-',$row['status']));
          ?>
          <tr>
            <td style="font-family:monospace;font-size:11px;font-weight:700"><?= htmlspecialchars($row['request_code']) ?></td>
            <td><?= htmlspecialchars($row['resident_name']) ?></td>
            <td><span class="badge badge-<?= $sc ?>"><?= $row['status'] ?></span></td>
            <td style="color:#94a3b8;font-size:11px;white-space:nowrap"><?= date('M d', strtotime($row['requested_at'])) ?></td>
          </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
      <a href="data_tracking.php?tab=doc_requests" class="view-all-link">View all document requests <i class="fas fa-arrow-right"></i></a>
    </div>

  </div>

  <!-- ── Quick actions ── -->
  <h2 class="sec-title"><i class="fas fa-bolt" style="color:var(--amber)"></i> Quick Actions</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:2rem">
    <a href="data_tracking.php?tab=cert_requests" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;text-decoration:none;font-size:13px;font-weight:600;border:none">
      <i class="fas fa-plus"></i> New Certificate Request
    </a>
    <a href="data_tracking.php?tab=doc_requests" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;text-decoration:none;font-size:13px;font-weight:600;border:none">
      <i class="fas fa-plus"></i> New Document Request
    </a>
    <a href="data_tracking.php?tab=cert_requests&status=Pending" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:#fff;border:1.5px solid #e2e8f0;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600">
      <i class="fas fa-clock" style="color:var(--amber)"></i> View Pending Certificates
      <?php if ($cert_counts['Pending'] > 0): ?>
      <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px"><?= $cert_counts['Pending'] ?></span>
      <?php endif; ?>
    </a>
    <a href="data_tracking.php?tab=audit" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:#fff;border:1.5px solid #e2e8f0;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600">
      <i class="fas fa-history" style="color:var(--teal)"></i> View Audit Log
    </a>
  </div>

</main>

<footer style="background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem;margin-top:2rem">
  &copy; <?= date('Y') ?> <?= $_dt_brgy ?>, <?= $_dt_city ?> &mdash; ProjectRBI Document Tracking
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── Monthly chart ────────────────────────────────────────────────────────
new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthly_labels) ?>,
    datasets: [
      {
        label: 'Certificates',
        data: <?= json_encode($monthly_cert) ?>,
        backgroundColor: '#3b82f6',
        borderRadius: 5,
        borderSkipped: false
      },
      {
        label: 'Documents',
        data: <?= json_encode($monthly_doc) ?>,
        backgroundColor: '#8b5cf6',
        borderRadius: 5,
        borderSkipped: false
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11, family: 'Inter' } } },
      y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { color: '#94a3b8', font: { size: 11, family: 'Inter' }, stepSize: 1 }, beginAtZero: true }
    }
  }
});

// ── Sidebar ───────────────────────────────────────────────────────────────
function openSidebar()  { document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('open'); document.body.style.overflow='hidden'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open'); document.body.style.overflow=''; }
document.getElementById('menuToggle').addEventListener('click', openSidebar);
document.addEventListener('keydown', e => { if(e.key==='Escape') closeSidebar(); });
</script>
</body>
</html>