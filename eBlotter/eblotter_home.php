<?php
require_once __DIR__.'/auth.php';
requireRole(); // all roles can view home

$countRes = $conn->query("SELECT status, COUNT(*) AS c FROM blotter_cases GROUP BY status");
$statusCounts = ['Pending' => 0, 'Ongoing' => 0, 'Resolved' => 0];
$totalCases = 0;
while ($cr = $countRes->fetch_assoc()) {
    $statusCounts[$cr['status']] = (int)$cr['c'];
    $totalCases += (int)$cr['c'];
}
$pendingCases  = $statusCounts['Pending'];
$ongoingCases  = $statusCounts['Ongoing'];
$resolvedCases = $statusCounts['Resolved'];

$recent = $conn->query("SELECT case_id, complainant_first, complainant_last, respondent_first, respondent_last, status, when_incident FROM blotter_cases ORDER BY created_at DESC LIMIT 6");

$u    = currentUser();
$role = $u['role'] ?? 'kagawad';
$is_chairperson = ($role === 'chairperson');
$is_secretary   = ($role === 'secretary');

$rbadge = match($role) {
    'chairperson' => ['icon'=>'fa-crown',   'label'=>'Punong Barangay'],
    'secretary'   => ['icon'=>'fa-user-tie','label'=>'Secretary'],
    default       => ['icon'=>'fa-user',    'label'=>'Kagawad'],
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>eBlotter – <?= defined('BRGY_NAME') ? htmlspecialchars(BRGY_NAME) : 'Barangay' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css?v=<?=filemtime(dirname(__DIR__).'/assets/css/main.css')?>">
  <link rel="stylesheet" href="eblotter.css?v=<?=filemtime(__DIR__.'/eblotter.css')?>">
  <style>
    main{padding:1.5rem;max-width:1100px;margin:0 auto}
    .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.25rem}
    @media(max-width:600px){.stat-row{grid-template-columns:1fr 1fr}}
    .scard{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem;text-align:center}
    .scard-num{font-size:22px;font-weight:700;line-height:1}
    .scard-lbl{font-size:11px;color:#64748b;margin-top:3px}
    footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem;margin-top:2rem}

    /* KP Process card */
    .kp-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:1.75rem 1.75rem 1.25rem;margin-bottom:1.5rem}
    .kp-card-title{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:.6rem;margin-bottom:1.75rem}
    .section-title{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:800;color:#0f172a;margin-bottom:.85rem;display:flex;align-items:center;gap:.5rem}
    .section-title::after{content:'';flex:1;height:1px;background:#e2e8f0}

    /* Process flow */
    .kp-flow{display:flex;align-items:flex-start;gap:0;overflow-x:auto;padding-bottom:1rem}
    .kp-phase{display:flex;flex-direction:column;align-items:center;min-width:160px;flex:1}
    .kp-phase-header{width:100%;text-align:center;padding:.4rem .5rem;border-radius:8px 8px 0 0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.75rem}
    .kp-step{background:#fff;border-radius:10px;padding:.85rem .75rem;text-align:center;width:140px;position:relative;border:2px solid #e2e8f0;transition:transform .15s,box-shadow .15s}
    .kp-step:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1)}
    .kp-step-num{width:26px;height:26px;border-radius:50%;font-size:.72rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto .5rem;color:#fff}
    .kp-step-icon{font-size:1.3rem;margin-bottom:.4rem;display:block}
    .kp-step h5{font-size:.78rem;font-weight:700;color:#0f172a;margin-bottom:.2rem;line-height:1.3}
    .kp-step p{font-size:.68rem;color:#64748b;line-height:1.4;margin:0}
    .kp-tag{display:inline-block;margin-top:.45rem;padding:.15rem .55rem;border-radius:999px;font-size:.62rem;font-weight:700}
    .kp-arrow{display:flex;align-items:center;justify-content:center;padding:0 .4rem;margin-top:3.5rem;color:#94a3b8;font-size:1.1rem;flex-shrink:0}
    .kp-steps-col{display:flex;flex-direction:column;align-items:center;gap:.5rem}
    .kp-down-arrow{color:#94a3b8;font-size:.8rem;text-align:center}
    .kp-legend{display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f1f5f9}
    .kp-legend-item{display:flex;align-items:center;gap:.4rem;font-size:.73rem;color:#64748b}
    .kp-legend-dot{width:10px;height:10px;border-radius:50%}

    .phase-filing .kp-phase-header{background:#dcfce7;color:#15803d}
    .phase-filing .kp-step{border-color:#bbf7d0}
    .phase-filing .kp-step-num{background:#16a34a}
    .phase-filing .kp-tag{background:#dcfce7;color:#15803d}
    .phase-mediation .kp-phase-header{background:#dbeafe;color:#1d4ed8}
    .phase-mediation .kp-step{border-color:#bfdbfe}
    .phase-mediation .kp-step-num{background:#2563eb}
    .phase-mediation .kp-tag{background:#dbeafe;color:#1d4ed8}
    .phase-pangkat .kp-phase-header{background:#fef3c7;color:#92400e}
    .phase-pangkat .kp-step{border-color:#fde68a}
    .phase-pangkat .kp-step-num{background:#d97706}
    .phase-pangkat .kp-tag{background:#fef3c7;color:#92400e}
    .phase-resolution .kp-phase-header{background:#f3e8ff;color:#7e22ce}
    .phase-resolution .kp-step{border-color:#e9d5ff}
    .phase-resolution .kp-step-num{background:#7c3aed}
    .phase-resolution .kp-tag{background:#f3e8ff;color:#7e22ce}

    /* Recent records table */
    .eb-table-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}
    .eb-table-wrap table{width:100%;border-collapse:collapse;font-size:13px}
    .eb-table-wrap th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0}
    .eb-table-wrap td{padding:10px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a}
    .eb-table-wrap tr:last-child td{border-bottom:none}
    .eb-table-wrap tr:hover td{background:#f8fafc}
    .chip{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700}
    .chip-pending{background:#fef3c7;color:#92400e}
    .chip-ongoing{background:#dbeafe;color:#1d4ed8}
    .chip-resolved{background:#dcfce7;color:#15803d}

    /* Deny alert */
    .deny-alert{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:.75rem 1.25rem;font-size:.88rem;text-align:center;margin-bottom:1rem;border-radius:8px}

  </style>
</head>
<body>

<?php
$active_page  = 'home';
$hero_mode    = true;
$hero_title   = 'eBlotter';
$hero_active  = 'home';
$page_title   = '<i class="fas fa-shield-halved" style="opacity:.8;margin-right:5px"></i> eBlotter Dashboard';
$page_actions = null;
include '_eb_topbar.php';
include '_eb_hero.php';
?>

<main>
  <?php if(isset($_GET['denied'])): ?>
  <div class="deny-alert"><i class="fas fa-lock"></i> You do not have permission to access that page.</div>
  <?php endif; ?>

  <div class="stat-row">
    <div class="scard"><div class="scard-num" style="color:#0f172a"><?= $totalCases ?></div><div class="scard-lbl">Total Records</div></div>
    <div class="scard"><div class="scard-num" style="color:#f59e0b"><?= $pendingCases ?></div><div class="scard-lbl">Pending</div></div>
    <div class="scard"><div class="scard-num" style="color:#3b82f6"><?= $ongoingCases ?></div><div class="scard-lbl">Ongoing</div></div>
    <div class="scard"><div class="scard-num" style="color:#22c55e"><?= $resolvedCases ?></div><div class="scard-lbl">Resolved</div></div>
  </div>

  <!-- Katarungang Pambarangay Process -->
  <div class="kp-card">
    <div class="kp-card-title">
      <i class="fas fa-balance-scale" style="color:#3b82f6"></i>
      Katarungang Pambarangay Process
    </div>

    <div class="kp-flow">

      <!-- PHASE 1: FILING -->
      <div class="kp-phase phase-filing">
        <div class="kp-phase-header" style="width:140px;">Phase 1: Filing</div>
        <div class="kp-steps-col">
          <div class="kp-step">
            <div class="kp-step-num">1</div>
            <span class="kp-step-icon">📋</span>
            <h5>Filing of Complaint</h5>
            <p>Complainant files at the Barangay Hall with filing fee</p>
            <span class="kp-tag">Start</span>
          </div>
          <div class="kp-down-arrow"><i class="fas fa-arrow-down"></i><br><span style="font-size:.6rem;">Next working day</span></div>
          <div class="kp-step">
            <div class="kp-step-num">2</div>
            <span class="kp-step-icon">📜</span>
            <h5>Summons Issued</h5>
            <p>Punong Barangay issues summons to respondent within 5 days</p>
            <span class="kp-tag" style="background:#f0fdf4;color:#15803d;">eBlotter: Pending</span>
          </div>
        </div>
      </div>

      <div class="kp-arrow"><i class="fas fa-chevron-right"></i></div>

      <!-- PHASE 2: MEDIATION -->
      <div class="kp-phase phase-mediation">
        <div class="kp-phase-header" style="width:140px;">Phase 2: Mediation</div>
        <div class="kp-steps-col">
          <div class="kp-step">
            <div class="kp-step-num">3</div>
            <span class="kp-step-icon">🤝</span>
            <h5>Mediation</h5>
            <p>Punong Barangay mediates within 15 days. Minutes recorded.</p>
            <span class="kp-tag" style="background:#dbeafe;color:#1d4ed8;">eBlotter: Ongoing</span>
          </div>
          <div class="kp-down-arrow"><i class="fas fa-arrow-down"></i><br><span style="font-size:.6rem;">If unresolved → Pangkat</span></div>
          <div class="kp-step" style="border-color:#bfdbfe;background:#eff6ff;">
            <div class="kp-step-num" style="background:#1d4ed8;">✓</div>
            <span class="kp-step-icon">📄</span>
            <h5>Notice of Hearing</h5>
            <p>Issued to parties for scheduled mediation hearing</p>
            <span class="kp-tag">Document</span>
          </div>
        </div>
      </div>

      <div class="kp-arrow"><i class="fas fa-chevron-right"></i></div>

      <!-- PHASE 3: PANGKAT -->
      <div class="kp-phase phase-pangkat">
        <div class="kp-phase-header" style="width:140px;">Phase 3: Pangkat</div>
        <div class="kp-steps-col">
          <div class="kp-step">
            <div class="kp-step-num">4</div>
            <span class="kp-step-icon">👥</span>
            <h5>Pangkat Constituted</h5>
            <p>3-member panel formed within 3 days if mediation fails</p>
          </div>
          <div class="kp-down-arrow"><i class="fas fa-arrow-down"></i></div>
          <div class="kp-step">
            <div class="kp-step-num">5</div>
            <span class="kp-step-icon">⚖️</span>
            <h5>Conciliation</h5>
            <p>Pangkat conciliates within 15–30 days</p>
          </div>
          <div class="kp-down-arrow"><i class="fas fa-arrow-down"></i></div>
          <div class="kp-step">
            <div class="kp-step-num">6</div>
            <span class="kp-step-icon">🔔</span>
            <h5>Summons by Pangkat</h5>
            <p>Formal summons to respondent for conciliation hearing</p>
            <span class="kp-tag">Document</span>
          </div>
        </div>
      </div>

      <div class="kp-arrow"><i class="fas fa-chevron-right"></i></div>

      <!-- PHASE 4: RESOLUTION -->
      <div class="kp-phase phase-resolution">
        <div class="kp-phase-header" style="width:140px;">Phase 4: Resolution</div>
        <div class="kp-steps-col">
          <div class="kp-step" style="border-color:#c4b5fd;background:#faf5ff;">
            <div class="kp-step-num" style="background:#16a34a;">✓</div>
            <span class="kp-step-icon">🎉</span>
            <h5>Settlement / Award</h5>
            <p>Agreement signed. Enforceable within 6 months, executed in 10 days.</p>
            <span class="kp-tag" style="background:#dcfce7;color:#15803d;">eBlotter: Resolved</span>
          </div>
          <div class="kp-down-arrow"><i class="fas fa-arrows-alt-v"></i></div>
          <div class="kp-step" style="border-color:#fca5a5;background:#fff5f5;">
            <div class="kp-step-num" style="background:#dc2626;">✗</div>
            <span class="kp-step-icon">🏛️</span>
            <h5>Certificate to File Action</h5>
            <p>If unresolved after 30 days, certificate issued for court filing</p>
            <span class="kp-tag" style="background:#fee2e2;color:#dc2626;">Escalated</span>
          </div>
        </div>
      </div>

    </div><!-- /kp-flow -->

    <div class="kp-legend">
      <div class="kp-legend-item"><div class="kp-legend-dot" style="background:#16a34a;"></div><span>Pending — Filing &amp; Summons stage</span></div>
      <div class="kp-legend-item"><div class="kp-legend-dot" style="background:#2563eb;"></div><span>Ongoing — Mediation / Conciliation stage</span></div>
      <div class="kp-legend-item"><div class="kp-legend-dot" style="background:#7c3aed;"></div><span>Resolved — Settlement or Award executed</span></div>
      <div class="kp-legend-item"><div class="kp-legend-dot" style="background:#dc2626;"></div><span>Escalated — Referred to court</span></div>
    </div>
  </div><!-- /kp-card -->

  <!-- Recent Records -->
  <h2 class="section-title"><i class="fas fa-history" style="color:#3b82f6"></i> Recent Records</h2>
  <div class="eb-table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Complainant</th>
          <th>Respondent</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent->num_rows === 0): ?>
          <tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">No records yet.</td></tr>
        <?php else: ?>
          <?php while ($r = $recent->fetch_assoc()):
            $cls = match($r['status']) {
              'Ongoing'  => 'chip-ongoing',
              'Resolved' => 'chip-resolved',
              default    => 'chip-pending',
            };
          ?>
          <tr>
            <td style="font-weight:600;font-size:.82rem;"><?= htmlspecialchars($r['case_id']) ?></td>
            <td><?= htmlspecialchars($r['complainant_last'].', '.$r['complainant_first']) ?></td>
            <td><?= htmlspecialchars($r['respondent_last'].', '.$r['respondent_first']) ?></td>
            <td><span class="chip <?= $cls ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td><?= $r['when_incident'] ? htmlspecialchars(date('M d, Y', strtotime($r['when_incident']))) : '—' ?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<footer>
  &copy; <?= date('Y') ?> <?= defined('BRGY_NAME') ? htmlspecialchars(BRGY_NAME) : 'Barangay' ?>, <?= defined('BRGY_CITY') ? htmlspecialchars(BRGY_CITY) : 'Manila' ?> &mdash; ProjectRBI &amp; eBlotter System
</footer>

</body>
</html>
