<?php
/**
 * dashboard.php — eBlotter Dynamic Dashboard
 * Drop this file into your /eblotter/ folder alongside eblotter_home.php
 * Requires: auth.php, db.php, _eb_topbar.php, _eb_footer.php, eblotter.css
 */
require_once __DIR__.'/auth.php';
requireRole(); // all roles can view

// ── Month filter ──────────────────────────────────────────────────────────
$filter_month = trim($_GET['month'] ?? 'all');
$filter_status = trim($_GET['status'] ?? 'all');
$valid_months = ['all','01','02','03','04','05','06','07','08','09','10','11','12'];
$valid_statuses = ['all','Pending','Ongoing','Resolved'];
if (!in_array($filter_month, $valid_months))  $filter_month  = 'all';
if (!in_array($filter_status, $valid_statuses)) $filter_status = 'all';

$current_year = date('Y');

// ── Build WHERE clause ────────────────────────────────────────────────────
$where = "WHERE 1=1";
$bind_types = '';
$bind_params = [];

if ($filter_month !== 'all') {
    $where .= " AND MONTH(created_at) = ? AND YEAR(created_at) = ?";
    $bind_types .= 'si';
    $bind_params[] = (int)$filter_month;
    $bind_params[] = (int)$current_year;
}
if ($filter_status !== 'all') {
    $where .= " AND status = ?";
    $bind_types .= 's';
    $bind_params[] = $filter_status;
}

// ── Summary counts (total, pending, ongoing, resolved) ───────────────────
$stmt = $conn->prepare("SELECT status, COUNT(*) AS c FROM blotter_cases $where GROUP BY status");
if ($bind_params) $stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$res = $stmt->get_result();
$counts = ['Pending' => 0, 'Ongoing' => 0, 'Resolved' => 0];
$total  = 0;
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['c'];
    $total += (int)$row['c'];
}

// ── Mediation outcomes ────────────────────────────────────────────────────
$settled    = (int)$conn->query("SELECT COUNT(*) AS c FROM blotter_cases WHERE mediation_outcome='Settled'")->fetch_assoc()['c'];
$to_court   = (int)$conn->query("SELECT COUNT(*) AS c FROM blotter_cases WHERE mediation_outcome='Referred to Court'")->fetch_assoc()['c'];

// Resolution rate
$res_rate = $total > 0 ? round(($counts['Resolved'] / $total) * 100) : 0;

// ── Monthly trend data (last 6 months) ───────────────────────────────────
$monthly_labels  = [];
$monthly_pending  = [];
$monthly_ongoing  = [];
$monthly_resolved = [];
$monthly_total    = [];

for ($i = 5; $i >= 0; $i--) {
    $ts    = strtotime("-$i months");
    $m     = date('m', $ts);
    $y     = date('Y', $ts);
    $label = date('M', $ts);
    $monthly_labels[] = $label;

    $r = $conn->query("SELECT status, COUNT(*) AS c FROM blotter_cases
        WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y GROUP BY status");
    $mc = ['Pending'=>0,'Ongoing'=>0,'Resolved'=>0];
    while ($row = $r->fetch_assoc()) $mc[$row['status']] = (int)$row['c'];
    $monthly_pending[]  = $mc['Pending'];
    $monthly_ongoing[]  = $mc['Ongoing'];
    $monthly_resolved[] = $mc['Resolved'];
    $monthly_total[]    = $mc['Pending'] + $mc['Ongoing'] + $mc['Resolved'];
}

// ── Incident type / disposition breakdown ────────────────────────────────
$type_res = $conn->query("SELECT disposition, COUNT(*) AS c FROM blotter_cases
    WHERE disposition IS NOT NULL AND disposition != ''
    GROUP BY disposition ORDER BY c DESC");
$type_labels = [];
$type_values = [];
while ($row = $type_res->fetch_assoc()) {
    $type_labels[] = $row['disposition'];
    $type_values[] = (int)$row['c'];
}

// ── Recent records (last 8, filtered) ────────────────────────────────────
$recent_stmt = $conn->prepare("SELECT case_id, complainant_first, complainant_last,
    respondent_first, respondent_last, status, disposition, when_incident, created_at
    FROM blotter_cases $where ORDER BY created_at DESC LIMIT 8");
if ($bind_params) $recent_stmt->bind_param($bind_types, ...$bind_params);
$recent_stmt->execute();
$recent_rows = $recent_stmt->get_result();

// ── Month name helper ─────────────────────────────────────────────────────
$month_names = [
    '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
    '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
    '09'=>'September','10'=>'October','11'=>'November','12'=>'December',
];

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard — eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css?v=<?= filemtime(dirname(__DIR__).'/assets/css/main.css') ?>">
  <link rel="stylesheet" href="eblotter.css?v=<?= filemtime(__DIR__.'/eblotter.css') ?>">
  <style>
    main { padding: 1.55rem 1.5rem 3rem; max-width: none; margin: 0 auto; }
    .dashboard-shell { max-width: 1788px; margin: 0 auto; }
    /* ── Filter bar ── */
    .dash-filters {
      display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
      padding: .75rem 1rem; margin-bottom: 1.25rem;
    }
    .dash-filters label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
    .dash-filters select {
      font-size: 13px; padding: .35rem .75rem;
      border: 1.5px solid #e2e8f0; border-radius: 8px;
      background: #f8fafc; color: #0f172a; outline: none;
      font-family: inherit; cursor: pointer;
    }
    .dash-filters select:focus { border-color: #2563eb; }
    .filter-spacer { flex: 1; }
    .live-dot {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11px; font-weight: 600; color: #16a34a;
      background: #dcfce7; padding: 3px 10px; border-radius: 999px;
    }
    .live-dot::before {
      content: ''; width: 6px; height: 6px; border-radius: 50%;
      background: #16a34a; animation: pulse 1.5s infinite;
    }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

    /* ── Metric cards ── */
    .metric-row {
      display: grid;
      grid-template-columns: repeat(6, minmax(150px, 1fr));
      gap: 16px; margin-bottom: 2rem;
    }
    @media(max-width:900px) { .metric-row { grid-template-columns: repeat(3,1fr); } }
    @media(max-width:540px) { .metric-row { grid-template-columns: repeat(2,1fr); } }

    .mcard {
      background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
      padding: 1.3rem 1rem 1.1rem; position: relative; overflow: hidden;
      min-height: 106px; text-align: center;
    }
    .mcard-icon {
      width: 30px; height: 30px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; margin: 0 auto .45rem;
    }
    .mcard-num { font-size: 31px; font-weight: 800; font-family: 'Inter', sans-serif; line-height: 1; }
    .mcard-lbl { font-size: 13px; color: #475569; margin-top: 5px; }
    .mcard-sub { font-size: 10px; margin-top: 4px; font-weight: 600; }

    .mc-blue   .mcard-icon { background: #dbeafe; color: #2563eb; }
    .mc-blue   .mcard-num  { color: #1e40af; }
    .mc-amber  .mcard-icon { background: #fef3c7; color: #d97706; }
    .mc-amber  .mcard-num  { color: #92400e; }
    .mc-navy   .mcard-icon { background: #e0e7ff; color: #4338ca; }
    .mc-navy   .mcard-num  { color: #3730a3; }
    .mc-green  .mcard-icon { background: #dcfce7; color: #16a34a; }
    .mc-green  .mcard-num  { color: #15803d; }
    .mc-teal   .mcard-icon { background: #ccfbf1; color: #0d9488; }
    .mc-teal   .mcard-num  { color: #0f766e; }
    .mc-red    .mcard-icon { background: #fee2e2; color: #dc2626; }
    .mc-red    .mcard-num  { color: #991b1b; }

    /* ── Chart cards ── */
    .chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 1.25rem; }
    .chart-row.triple { grid-template-columns: 2fr 1fr; }
    @media(max-width:700px) { .chart-row, .chart-row.triple { grid-template-columns: 1fr; } }

    .ccard {
      background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
      padding: 1.1rem 1.25rem;
    }
    .ccard-title { font-family: 'Syne', sans-serif; font-size: .92rem; font-weight: 800; color: #0f172a; margin-bottom: 2px; }
    .ccard-sub   { font-size: 11px; color: #94a3b8; margin-bottom: .85rem; }
    .chart-legend { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
    .chart-legend span { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #64748b; }
    .legend-dot { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }

    /* ── Recent table ── */
    .section-title {
      font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 800;
      color: #0f172a; margin-bottom: .85rem;
      display: flex; align-items: center; gap: .5rem;
    }
    .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
    .eb-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
    .eb-table-wrap table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .eb-table-wrap th {
      background: #f8fafc; padding: 10px 14px; text-align: left;
      font-size: 11px; font-weight: 700; color: #64748b;
      text-transform: uppercase; letter-spacing: .05em;
      border-bottom: 1px solid #e2e8f0;
    }
    .eb-table-wrap td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
    .eb-table-wrap tr:last-child td { border-bottom: none; }
    .eb-table-wrap tr:hover td { background: #f8fafc; }
    .chip { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .chip-pending  { background: #fef3c7; color: #92400e; }
    .chip-ongoing  { background: #dbeafe; color: #1d4ed8; }
    .chip-resolved { background: #dcfce7; color: #15803d; }
    .disp-tag {
      display: inline-block; padding: 1px 7px; border-radius: 4px;
      font-size: 11px; background: #f1f5f9; color: #475569;
    }
    .view-link { font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 600; }
    .view-link:hover { text-decoration: underline; }

    /* ── No data state ── */
    .no-data { text-align: center; padding: 2rem; color: #94a3b8; font-size: 13px; }
    @media(max-width:700px) {
      main { padding: 1rem; }
      .dash-filters { align-items: stretch; }
      .dash-filters label, .dash-filters select { width: 100%; }
    }
  </style>
</head>
<body>

<?php
$hero_mode = true;
$hero_title = 'Dashboard';
$hero_subtitle = 'Live overview of all blotter activity';
$hero_active = 'dashboard';
$page_title    = '';
$page_subtitle = 'Live overview of all blotter activity';
$page_actions  = '';
include '_eb_topbar.php';
include '_eb_hero.php';
?>

<main>
<div class="dashboard-shell">

  <!-- ── Filter bar ── -->
  <form method="GET" class="dash-filters" id="filterForm">
    <label>Month</label>
    <select name="month" onchange="this.form.submit()">
      <option value="all" <?= $filter_month === 'all' ? 'selected' : '' ?>>All months</option>
      <?php foreach ($month_names as $val => $name): ?>
      <option value="<?= $val ?>" <?= $filter_month === $val ? 'selected' : '' ?>><?= $name ?></option>
      <?php endforeach; ?>
    </select>

    <label>Status</label>
    <select name="status" onchange="this.form.submit()">
      <option value="all"     <?= $filter_status === 'all'      ? 'selected' : '' ?>>All statuses</option>
      <option value="Pending" <?= $filter_status === 'Pending'  ? 'selected' : '' ?>>Pending</option>
      <option value="Ongoing" <?= $filter_status === 'Ongoing'  ? 'selected' : '' ?>>Ongoing</option>
      <option value="Resolved"<?= $filter_status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
    </select>

    <?php if ($filter_month !== 'all' || $filter_status !== 'all'): ?>
    <a href="dashboard.php" style="font-size:12px;color:#dc2626;text-decoration:none;font-weight:600;">
      <i class="fas fa-times"></i> Clear
    </a>
    <?php endif; ?>

    <div class="filter-spacer"></div>
    <span class="live-dot">Live</span>
    <span style="font-size:11px;color:#94a3b8">Updated: <?= date('M d, Y h:i A') ?></span>
  </form>

  <!-- ── Metric cards ── -->
  <div class="metric-row">
    <div class="mcard mc-blue">
      <div class="mcard-icon"><i class="fas fa-folder-open"></i></div>
      <div class="mcard-num"><?= $total ?></div>
      <div class="mcard-lbl">Total Records</div>
    </div>
    <div class="mcard mc-amber">
      <div class="mcard-icon"><i class="fas fa-clock"></i></div>
      <div class="mcard-num"><?= $counts['Pending'] ?></div>
      <div class="mcard-lbl">Pending</div>
    </div>
    <div class="mcard mc-navy">
      <div class="mcard-icon"><i class="fas fa-spinner"></i></div>
      <div class="mcard-num"><?= $counts['Ongoing'] ?></div>
      <div class="mcard-lbl">Ongoing</div>
    </div>
    <div class="mcard mc-green">
      <div class="mcard-icon"><i class="fas fa-check-circle"></i></div>
      <div class="mcard-num"><?= $counts['Resolved'] ?></div>
      <div class="mcard-lbl">Resolved</div>
      <div class="mcard-sub" style="color:#16a34a"><?= $res_rate ?>% rate</div>
    </div>
    <div class="mcard mc-teal">
      <div class="mcard-icon"><i class="fas fa-handshake"></i></div>
      <div class="mcard-num"><?= $settled ?></div>
      <div class="mcard-lbl">Settled</div>
    </div>
    <div class="mcard mc-red">
      <div class="mcard-icon"><i class="fas fa-balance-scale"></i></div>
      <div class="mcard-num"><?= $to_court ?></div>
      <div class="mcard-lbl">To Court</div>
    </div>
  </div>

  <!-- ── Charts row 1: trend + type ── -->
  <div class="chart-row triple">

    <!-- Monthly trend -->
    <div class="ccard">
      <div class="ccard-title">Monthly trend</div>
      <div class="ccard-sub">Last 6 months — <?= $current_year ?></div>
      <div class="chart-legend">
        <span><span class="legend-dot" style="background:#f59e0b"></span>Pending</span>
        <span><span class="legend-dot" style="background:#3b82f6"></span>Ongoing</span>
        <span><span class="legend-dot" style="background:#22c55e"></span>Resolved</span>
      </div>
      <div style="position:relative;height:210px;">
        <canvas id="trendChart" role="img" aria-label="Line chart showing monthly pending, ongoing, and resolved blotter cases over the last 6 months">Monthly blotter trend data.</canvas>
      </div>
    </div>

    <!-- Type breakdown -->
    <div class="ccard">
      <div class="ccard-title">Record type</div>
      <div class="ccard-sub">By disposition</div>
      <?php if (empty($type_labels)): ?>
        <div class="no-data"><i class="fas fa-chart-pie" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No data yet</div>
      <?php else: ?>
      <div class="chart-legend" id="typeLegend"></div>
      <div style="position:relative;height:175px;">
        <canvas id="typeChart" role="img" aria-label="Doughnut chart of blotter record types">Record type breakdown.</canvas>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ── Charts row 2: monthly bar ── -->
  <div class="chart-row" style="grid-template-columns:1fr;margin-bottom:1.25rem;">
    <div class="ccard">
      <div class="ccard-title">Total records per month</div>
      <div class="ccard-sub">Filed cases — last 6 months</div>
      <div style="position:relative;height:170px;">
        <canvas id="barChart" role="img" aria-label="Bar chart of total blotter records per month for the last 6 months">Monthly total records.</canvas>
      </div>
    </div>
  </div>

  <!-- ── Recent records table ── -->
  <h2 class="section-title"><i class="fas fa-history" style="color:#3b82f6"></i> Recent records</h2>
  <div class="eb-table-wrap">
    <table>
      <thead>
        <tr>
          <th>Case ID</th>
          <th>Complainant</th>
          <th>Respondent</th>
          <th>Type</th>
          <th>Status</th>
          <th>Incident date</th>
          <th>Filed</th>
          <?php if (canEdit()): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent_rows->num_rows === 0): ?>
        <tr><td colspan="8" class="no-data">No records found for the selected filter.</td></tr>
        <?php else: ?>
        <?php while ($row = $recent_rows->fetch_assoc()):
          $cls = match($row['status']) {
            'Ongoing'  => 'chip-ongoing',
            'Resolved' => 'chip-resolved',
            default    => 'chip-pending',
          };
          $case_id_enc = urlencode($row['case_id']);
        ?>
        <tr>
          <td style="font-weight:700;font-size:.82rem;font-family:monospace"><?= htmlspecialchars($row['case_id']) ?></td>
          <td><?= htmlspecialchars($row['complainant_last'].', '.$row['complainant_first']) ?></td>
          <td><?= htmlspecialchars($row['respondent_last'].', '.$row['respondent_first']) ?></td>
          <td><span class="disp-tag"><?= htmlspecialchars($row['disposition'] ?: '—') ?></span></td>
          <td><span class="chip <?= $cls ?>"><?= htmlspecialchars($row['status']) ?></span></td>
          <td><?= $row['when_incident'] ? htmlspecialchars(date('M d, Y', strtotime($row['when_incident']))) : '—' ?></td>
          <td style="color:#94a3b8;font-size:.8rem"><?= htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) ?></td>
          <?php if (canEdit()): ?>
          <td><a href="view_cases.php?q=<?= $case_id_enc ?>" class="view-link"><i class="fas fa-eye"></i> View</a></td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="text-align:center;margin-top:.85rem;">
    <a href="view_cases.php" class="btn btn-ghost btn-sm" style="text-decoration:none;font-size:13px;">
      <i class="fas fa-list"></i> View all records
    </a>
  </div>

</div>
</main>

<?php include '_eb_footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── PHP data → JS ──────────────────────────────────────────────────────────
const labels   = <?= json_encode($monthly_labels) ?>;
const pending  = <?= json_encode($monthly_pending) ?>;
const ongoing  = <?= json_encode($monthly_ongoing) ?>;
const resolved = <?= json_encode($monthly_resolved) ?>;
const totals   = <?= json_encode($monthly_total) ?>;

const typeLabels = <?= json_encode($type_labels) ?>;
const typeValues = <?= json_encode($type_values) ?>;
const typeColors = ['#3b82f6','#f59e0b','#22c55e','#ef4444','#8b5cf6','#14b8a6','#f97316','#ec4899'];

const gridColor  = 'rgba(0,0,0,0.05)';
const tickColor  = '#94a3b8';
const tickFont   = { size: 11, family: 'Inter' };

// ── Trend line chart ───────────────────────────────────────────────────────
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [
      {
        label: 'Pending',
        data: pending,
        borderColor: '#f59e0b',
        backgroundColor: 'rgba(245,158,11,.08)',
        tension: 0.4, fill: true, pointRadius: 4,
        pointBackgroundColor: '#f59e0b',
        borderDash: [4, 2]
      },
      {
        label: 'Ongoing',
        data: ongoing,
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,.08)',
        tension: 0.4, fill: true, pointRadius: 4,
        pointBackgroundColor: '#3b82f6'
      },
      {
        label: 'Resolved',
        data: resolved,
        borderColor: '#22c55e',
        backgroundColor: 'rgba(34,197,94,.08)',
        tension: 0.4, fill: true, pointRadius: 4,
        pointBackgroundColor: '#22c55e',
        borderDash: [6, 3]
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { color: tickColor, font: tickFont } },
      y: { grid: { color: gridColor }, ticks: { color: tickColor, font: tickFont, stepSize: 1 },
           beginAtZero: true }
    }
  }
});

// ── Bar chart ──────────────────────────────────────────────────────────────
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Total records',
      data: totals,
      backgroundColor: '#1b263b',
      borderRadius: 5,
      borderSkipped: false
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { color: tickColor, font: tickFont } },
      y: { grid: { color: gridColor }, ticks: { color: tickColor, font: tickFont, stepSize: 1 },
           beginAtZero: true }
    }
  }
});

// ── Doughnut chart ─────────────────────────────────────────────────────────
if (typeLabels.length > 0) {
  const legendEl = document.getElementById('typeLegend');
  typeLabels.forEach((l, i) => {
    const s = document.createElement('span');
    s.innerHTML = `<span class="legend-dot" style="background:${typeColors[i % typeColors.length]};display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:4px"></span>${l} (${typeValues[i]})`;
    s.style.cssText = 'display:flex;align-items:center;gap:2px;font-size:11px;color:#64748b;';
    legendEl.appendChild(s);
  });

  new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
      labels: typeLabels,
      datasets: [{
        data: typeValues,
        backgroundColor: typeColors.slice(0, typeLabels.length),
        borderWidth: 3,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '65%',
      plugins: { legend: { display: false } }
    }
  });
}
</script>
</body>
</html>
