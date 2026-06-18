<?php
require_once __DIR__.'/auth.php';
requireRole();

$search        = trim($_GET['q'] ?? '');
$status_filter  = trim($_GET['status'] ?? '');
$outcome_filter = trim($_GET['outcome'] ?? '');

$sql    = "SELECT * FROM blotter_cases WHERE 1=1";
$params = []; $types = '';

if ($search !== '') {
    $sql .= " AND CONCAT(complainant_last,' ',complainant_first,' ',respondent_last,' ',respondent_first,' ',case_id) LIKE ?";
    $params[] = "%$search%"; $types .= 's';
}
if ($outcome_filter !== '') {
    $sql .= " AND mediation_outcome = ?";
    $params[] = $outcome_filter; $types .= 's';
} elseif ($status_filter !== '') {
    $sql .= " AND status = ?";
    $params[] = $status_filter; $types .= 's';
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$active_page = 'view';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Records - eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css?v=<?=filemtime(dirname(__DIR__).'/assets/css/main.css')?>">
  <link rel="stylesheet" href="eblotter.css?v=<?=filemtime(__DIR__.'/eblotter.css')?>">
  <style>
    .toolbar {
      display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;
      background: var(--white); border-radius: var(--radius);
      padding: 1rem 1.25rem; box-shadow: var(--shadow); margin-bottom: 1.25rem;
    }
    .search-wrap { display: flex; gap: .5rem; align-items: center; flex: 1; min-width: 220px; }
    .search-wrap input {
      border: 1.5px solid var(--gray200); border-radius: 999px;
      padding: .5rem 1rem; font-family: inherit; font-size: .88rem;
      flex: 1; outline: none; transition: border-color .2s;
    }
    .search-wrap input:focus { border-color: var(--accent); }
    .status-filters { display: flex; gap: .4rem; flex-wrap: wrap; }
    .sf-btn {
      padding: .3rem .8rem; border-radius: 999px; font-size: .78rem;
      font-weight: 600; border: 1.5px solid var(--gray200); background: var(--white);
      cursor: pointer; color: var(--gray600); text-decoration: none;
      transition: all .15s;
    }
    .sf-btn:hover, .sf-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); }

    .export-bar { display: flex; gap: .75rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; }
    .count-info { font-size: .82rem; color: var(--gray400); }

    tr.eb-detail-row { display: none; }
    tr.eb-detail-row.open { display: table-row; }
    tr.eb-detail-row td { background: var(--gray50); padding: 0; }
    .detail-box {
      background: var(--white); margin: .5rem 1rem .75rem;
      border-radius: 12px; padding: 1.25rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.07);
      display: grid; grid-template-columns: 1fr 1fr 1fr;
      gap: 0 2rem; font-size: .85rem;
      align-items: start;
    }
    @media(max-width:900px) { .detail-box { grid-template-columns: 1fr 1fr; } }
    @media(max-width:640px) { .detail-box { grid-template-columns: 1fr; } }
    .detail-box .dl { grid-column: 1 / -1; }
    .detail-box .detail-col { display: flex; flex-direction: column; gap: .75rem; padding: .25rem 0; }
    .detail-box .detail-col-3 { border-left: 2px solid var(--gray100); padding-left: 1.5rem; }
    .detail-box .label { font-weight: 700; color: var(--gray600); font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: .15rem; }
    .toggle-icon { transition: transform .2s; display: inline-block; }
    .summary-row { cursor: pointer; }
    .summary-row:hover td { background: #eff6ff !important; }
    .audit-chip { display:inline-flex;align-items:center;gap:4px;font-size:.7rem;color:#64748b;background:#f1f5f9;border-radius:6px;padding:2px 7px; }
    .audit-row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-top:.35rem; }

    /* Workflow badge */
    .workflow-stage {
      display:inline-flex; align-items:center; gap:.3rem;
      padding:.2rem .65rem; border-radius:999px;
      font-size:.69rem; font-weight:700; margin-bottom:.35rem;
    }

    /* ── Inline step tracker ── */
    .case-steps {
      display: flex; align-items: center; gap: 0;
      margin-bottom: .75rem; flex-wrap: wrap;
    }
    .cs-step {
      display: flex; align-items: center; gap: .35rem;
      padding: .35rem .75rem; border-radius: 999px;
      font-size: .74rem; font-weight: 700;
      border: 1.5px solid transparent;
      white-space: nowrap;
    }
    .cs-step.done   { background: #dcfce7; color: #15803d; border-color: #86efac; }
    .cs-step.active { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
    .cs-step.locked { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
    .cs-arrow { width: 18px; height: 1.5px; background: #cbd5e1; margin: 0 2px; flex-shrink: 0; }
    .cs-step .cs-num {
      width: 18px; height: 18px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: .65rem; font-weight: 800; flex-shrink: 0;
    }
    .cs-step.done   .cs-num { background: #16a34a; color: #fff; }
    .cs-step.active .cs-num { background: #2563eb; color: #fff; }
    .cs-step.locked .cs-num { background: #cbd5e1; color: #fff; }
    .next-step-hint {
      display: inline-flex; align-items: center; gap: .3rem;
      font-size: .74rem; font-weight: 600; color: #d97706;
      background: #fef3c7; border: 1px solid #fde68a;
      border-radius: 6px; padding: .25rem .65rem; margin-bottom: .75rem;
    }
  </style>
</head>
<body>


<?php
$hero_mode   = true;
$hero_title  = 'View Records';
$hero_active = 'view';
include '_eb_topbar.php';
include '_eb_hero.php';
?>
<main style="padding:1.5rem;max-width:1200px;margin:0 auto">
<?php if(isset($_GET['denied'])): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 18px;margin-bottom:1rem;display:flex;align-items:center;gap:10px;font-size:13px;color:#92400e">
  <i class="fas fa-star" style="color:#f59e0b;font-size:16px;flex-shrink:0"></i>
  <span>That action is restricted to the <strong>Barangay Secretary</strong>. You are in monitoring mode.</span>
</div>
<?php endif; ?>

  <form method="get" id="filterForm">
    <div class="toolbar">
      <div class="search-wrap">
        <i class="fas fa-search" style="color:var(--gray400)"></i>
        <input type="search" name="q" placeholder="Search name or reference number…" value="<?= htmlspecialchars($search) ?>">
        <input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($status_filter) ?>">
      </div>
      <div class="status-filters">
        <a href="?q=<?= urlencode($search) ?>"                          class="sf-btn <?= $status_filter==='' ? 'active' : '' ?>">All</a>
        <a href="?q=<?= urlencode($search) ?>&status=Pending"  class="sf-btn <?= $status_filter==='Pending'  ? 'active' : '' ?>">Pending</a>
        <a href="?q=<?= urlencode($search) ?>&status=Ongoing"  class="sf-btn <?= $status_filter==='Ongoing'  ? 'active' : '' ?>">Ongoing</a>
        <a href="?q=<?= urlencode($search) ?>&status=Resolved" class="sf-btn <?= $status_filter==='Resolved' ? 'active' : '' ?>">Resolved</a>
        <a href="?q=<?= urlencode($search) ?>&outcome=Settled" class="sf-btn <?= ($_GET['outcome'] ?? '')==='Settled' ? 'active' : '' ?>"><i class="fas fa-check-circle" style="margin-right:3px;font-size:.7rem;"></i>Settled</a>
        <a href="?q=<?= urlencode($search) ?>&outcome=Referred+to+Court" class="sf-btn <?= ($_GET['outcome'] ?? '')==='Referred to Court' ? 'active' : '' ?>"><i class="fas fa-balance-scale" style="margin-right:3px;font-size:.7rem;"></i>Referred to Court</a>
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    </div>
  </form>

  <div>
    <div class="export-bar">
      <span class="count-info">
        <?= $result->num_rows ?> record<?= $result->num_rows !== 1 ? 's' : '' ?> found
      </span>
    </div>

    <?php $rows = $result->fetch_all(MYSQLI_ASSOC); ?>

    <div class="eb-table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:30px;"></th>
          <th>ID</th>
          <th>Complainant</th>
          <th>Respondent</th>
          <th>Status</th>
          <th>Incident Date</th>
          <th>Hearing Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--gray400);">
            <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            No records found.
          </td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row):
            $cls   = match($row['status']) { 'Ongoing'=>'chip-ongoing','Resolved'=>'chip-resolved',default=>'chip-pending' };
            $cFull = htmlspecialchars(trim($row['complainant_last'].', '.$row['complainant_first'].' '.$row['complainant_middle']));
            $rFull = htmlspecialchars(trim($row['respondent_last'].', '.$row['respondent_first'].' '.$row['respondent_middle']));
            $rid   = 'dr_'.md5($row['case_id']);
            $st    = $row['status'];
          ?>
          <tr class="summary-row" data-rid="<?= $rid ?>">
            <td>
              <button type="button" class="toggle-row-btn" style="background:none;border:none;cursor:pointer;color:var(--gray400);padding:.2rem .4rem;" data-rid="<?= $rid ?>">
                <i class="fas fa-chevron-right toggle-icon"></i>
              </button>
            </td>
            <td style="font-weight:600;font-size:.82rem;"><?= htmlspecialchars($row['case_id']) ?></td>
            <td><?= $cFull ?></td>
            <td><?= $rFull ?></td>
            <td><span class="chip <?= $cls ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= !empty($row['when_incident']) ? htmlspecialchars(date('M d, Y', strtotime($row['when_incident']))) : '—' ?></td>
            <td><?= !empty($row['hearing_date']) ? htmlspecialchars(date('M d, Y', strtotime($row['hearing_date']))) : '—' ?></td>
          </tr>
          <tr class="eb-detail-row" id="<?= $rid ?>">
            <td colspan="8">
              <div class="detail-box">

                <!-- 1: Complainant, When/Kailan, Type -->
                <div class="detail-col">
                  <div>
                    <span class="label">Complainant</span>
                    <?= $cFull ?><br>
                    <small style="color:var(--gray400)"><?= htmlspecialchars($row['complainant_address']) ?></small>
                  </div>
                  <div>
                    <span class="label">When / Kailan</span>
                    <?= htmlspecialchars($row['when_incident'] ?? '—') ?>
                  </div>
                  <div>
                    <span class="label">Type</span>
                    <?= htmlspecialchars($row['disposition'] ?? '—') ?>
                  </div>
                </div>

                <!-- 2: Respondent, Where/Saan, Description -->
                <div class="detail-col">
                  <div>
                    <span class="label">Respondent</span>
                    <?= $rFull ?><br>
                    <small style="color:var(--gray400)"><?= htmlspecialchars($row['respondent_address']) ?></small>
                  </div>
                  <div>
                    <span class="label">Where / Saan</span>
                    <?= htmlspecialchars($row['where_incident'] ?? '—') ?>
                  </div>
                  <div>
                    <span class="label">Description</span>
                    <?= nl2br(htmlspecialchars($row['brief_case'] ?? '')) ?>
                  </div>
                </div>

                <!-- 3: Status, Mediation Outcome, Mediation Details -->
                <?php
                  $outcomeColors = [
                    'Settled'           => ['bg'=>'#dcfce7','color'=>'#15803d','icon'=>'fa-check-circle'],
                    'Referred to Court' => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-balance-scale'],
                  ];
                  $oc = $outcomeColors[$row['mediation_outcome'] ?? ''] ?? ['bg'=>'#f1f5f9','color'=>'#475569','icon'=>'fa-circle'];
                ?>
                <div class="detail-col detail-col-3">
                  <div>
                    <span class="label">Status</span>
                    <span class="chip <?= $cls ?>" style="font-size:1rem;padding:.4rem 1.1rem;"><?= htmlspecialchars($row['status']) ?></span>
                  </div>
                  <?php if (!empty($row['mediation_outcome'])): ?>
                  <div>
                    <span class="label"><i class="fas fa-handshake" style="margin-right:3px"></i>Mediation Outcome</span>
                    <span style="display:inline-flex;align-items:center;gap:.4rem;background:<?= $oc['bg'] ?>;color:<?= $oc['color'] ?>;border-radius:999px;padding:.4rem 1.1rem;font-size:1rem;font-weight:700;">
                      <i class="fas <?= $oc['icon'] ?>"></i>
                      <?= htmlspecialchars($row['mediation_outcome']) ?>
                    </span>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($row['mediation_reason'])): ?>
                  <div>
                    <span class="label">Mediation Details</span>
                    <?= nl2br(htmlspecialchars($row['mediation_reason'])) ?>
                  </div>
                  <?php endif; ?>
                </div>

                <!-- AUDIT INFO: Recorded + Last Updated + Last Exported ── -->
                <div class="dl">
                  <div class="audit-row">
                    <small style="color:var(--gray400);font-size:.73rem;">
                      <i class="fas fa-calendar-plus" style="margin-right:3px;"></i>
                      Recorded: <?= htmlspecialchars($row['created_at'] ?? '—') ?>
                    </small>
                    <?php if(!empty($row['last_updated_by'])): ?>
                      <span class="audit-chip"><i class="fas fa-pen"></i> Updated by <?= htmlspecialchars($row['last_updated_by']) ?> on <?= htmlspecialchars($row['last_updated_at'] ?? '') ?></span>
                    <?php endif; ?>
                    <?php if(!empty($row['last_exported_by'])): ?>
                      <span class="audit-chip"><i class="fas fa-file-pdf"></i> Last exported by <?= htmlspecialchars($row['last_exported_by']) ?> on <?= htmlspecialchars($row['last_exported_at'] ?? '') ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- WORKFLOW ACTIONS -->
                <?php if(canEdit()): ?>
                <div class="dl" style="margin-top:.25rem;">

                  <?php
                  // KATARUNGANG PAMBARANGAY WORKFLOW 

                  $summonsDone   = !empty($row['summons_done']);
                  $noticeDone    = !empty($row['notice_done']);
                  $mediationDone = !empty($row['mediation_done']);

                  $step1Done   = $summonsDone;
                  $step2Done   = $noticeDone;
                  $step3Done   = $mediationDone;
                  $step1Active = !$summonsDone;
                  $step2Active = $summonsDone && !$noticeDone;
                  $step3Active = $noticeDone && !$mediationDone;

                  $s1cls = $step1Done ? 'done' : ($step1Active ? 'active' : 'locked');
                  $s2cls = $step2Done ? 'done' : ($step2Active ? 'active' : 'locked');
                  $s3cls = $step3Done ? 'done' : ($step3Active ? 'active' : 'locked');
                  ?>

                  <!-- visual step tracker -->
                  <div class="case-steps">
                    <div class="cs-step <?= $s1cls ?>">
                      <span class="cs-num"><?= $step1Done ? '✓' : '1' ?></span>
                      Summons
                    </div>
                    <div class="cs-arrow"></div>
                    <div class="cs-step <?= $s2cls ?>">
                      <span class="cs-num"><?= $step2Done ? '✓' : '2' ?></span>
                      Notice of Hearing
                    </div>
                    <div class="cs-arrow"></div>
                    <div class="cs-step <?= $s3cls ?>">
                      <span class="cs-num"><?= $step3Done ? '✓' : '3' ?></span>
                      Mediation Minutes
                    </div>
                    <div class="cs-arrow"></div>
                    <div class="cs-step <?= $st==='Resolved' ? 'done' : 'locked' ?>">
                      <span class="cs-num"><?= $st==='Resolved' ? '✓' : '4' ?></span>
                      Resolved
                    </div>
                  </div>

                  <?php if (!$summonsDone): ?>
                    <div class="next-step-hint"><i class="fas fa-arrow-right"></i> <strong>Next step:</strong> Issue a Summons to the respondent.</div>
                  <?php elseif (!$noticeDone): ?>
                    <div class="next-step-hint"><i class="fas fa-arrow-right"></i> <strong>Next step:</strong> Issue Notice of Hearing. Mediation Minutes will unlock after.</div>
                  <?php elseif (!$mediationDone): ?>
                    <div class="next-step-hint"><i class="fas fa-arrow-right"></i> <strong>Next step:</strong> Conduct Mediation and record the minutes.</div>
                  <?php elseif (empty($row['mediation_outcome'])): ?>
                    <div class="next-step-hint" style="background:#fef9c3;border-color:#fde68a;color:#713f12;"><i class="fas fa-handshake"></i> Mediation done — record the <strong>outcome</strong> in Update Record.</div>
                  <?php elseif ($st !== 'Resolved'): ?>
                    <div class="next-step-hint" style="background:#dcfce7;border-color:#86efac;color:#166534;"><i class="fas fa-check-circle"></i> All documents done. Mark case as <strong>Resolved</strong>.</div>
                  <?php else: ?>
                    <div class="next-step-hint" style="background:#dcfce7;border-color:#86efac;color:#166534;"><i class="fas fa-check-circle"></i> <strong>Case closed.</strong> All documents are available for re-export.</div>
                  <?php endif; ?>

                  <!-- action buttons row -->
                  <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:.1rem;">

                    <a href="update_case.php?case_id=<?= urlencode($row['case_id']) ?>" class="btn btn-accent btn-sm">
                      <i class="fas fa-edit"></i> Update Status
                    </a>

                    <?php /* Step 1 - Summons */ ?>
                    <a href="summons.php?case_id=<?= urlencode($row['case_id']) ?>"
                       class="btn btn-sm <?= !$summonsDone ? 'btn-accent' : 'btn-ghost' ?>"
                       title="Step 1 — Issue Summons to respondent">
                      <i class="fas fa-gavel"></i> Summons
                      <?php if ($summonsDone): ?><span style="font-size:.65rem;background:rgba(0,0,0,.08);border-radius:4px;padding:1px 5px;margin-left:3px;">✓</span><?php endif; ?>
                    </a>

                    <?php /* Step 2 - Notice of Hearing: locked until summons_done */ ?>
                    <?php if ($summonsDone): ?>
                    <a href="notice_hearing.php?case_id=<?= urlencode($row['case_id']) ?>"
                       class="btn btn-sm <?= !$noticeDone ? 'btn-accent' : 'btn-ghost' ?>"
                       title="Step 2 — Notice of Hearing">
                      <i class="fas fa-bell"></i> Notice of Hearing
                      <?php if ($noticeDone): ?><span style="font-size:.65rem;background:rgba(0,0,0,.08);border-radius:4px;padding:1px 5px;margin-left:3px;">✓</span><?php endif; ?>
                    </a>
                    <?php else: ?>
                    <span class="btn btn-sm btn-ghost" style="opacity:.4;cursor:not-allowed;" title="Locked — issue Summons first">
                      <i class="fas fa-lock" style="font-size:.7rem;"></i> Notice of Hearing
                    </span>
                    <?php endif; ?>

                    <?php /* Step 3 - Mediation Minutes: locked until notice_done */ ?>
                    <?php if ($noticeDone): ?>
                    <a href="mediation_minutes.php?case_id=<?= urlencode($row['case_id']) ?>"
                       class="btn btn-sm <?= !$mediationDone ? 'btn-accent' : 'btn-ghost' ?>"
                       title="Step 3 — Mediation Minutes">
                      <i class="fas fa-file-alt"></i> Mediation Minutes
                      <?php if ($mediationDone): ?><span style="font-size:.65rem;background:rgba(0,0,0,.08);border-radius:4px;padding:1px 5px;margin-left:3px;">✓</span><?php endif; ?>
                    </a>
                    <?php else: ?>
                    <span class="btn btn-sm btn-ghost" style="opacity:.4;cursor:not-allowed;" title="Locked — issue Notice of Hearing first">
                      <i class="fas fa-lock" style="font-size:.7rem;"></i> Mediation Minutes
                    </span>
                    <?php endif; ?>

                  </div>
                </div>
                <?php endif; // canEdit ?>

                <?php if(isChairperson()): ?>
                <!-- CHAIRMAN: workflow progress view only — no action buttons -->
                <div class="dl" style="margin-top:.25rem;">
                  <?php
                  $summonsDone   = !empty($row['summons_done']);
                  $noticeDone    = !empty($row['notice_done']);
                  $mediationDone = !empty($row['mediation_done']);
                  $s1cls = $summonsDone ? 'done' : 'locked';
                  $s2cls = $noticeDone  ? 'done' : 'locked';
                  $s3cls = $mediationDone ? 'done' : 'locked';
                  ?>
                  <div class="case-steps">
                    <div class="cs-step <?= $s1cls ?>"><span class="cs-num"><?= $summonsDone?'✓':'1'?></span>Summons</div>
                    <div class="cs-arrow"></div>
                    <div class="cs-step <?= $s2cls ?>"><span class="cs-num"><?= $noticeDone?'✓':'2'?></span>Notice of Hearing</div>
                    <div class="cs-arrow"></div>
                    <div class="cs-step <?= $s3cls ?>"><span class="cs-num"><?= $mediationDone?'✓':'3'?></span>Mediation Minutes</div>
                    <div class="cs-arrow"></div>
                    <div class="cs-step <?= $st==='Resolved'?'done':'locked'?>"><span class="cs-num"><?= $st==='Resolved'?'✓':'4'?></span>Resolved</div>
                  </div>
                  <div style="font-size:11px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:6px 10px;margin-top:.5rem;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-star" style="color:#f59e0b"></i>
                    Monitoring mode — case actions are managed by the <strong style="margin-left:2px">Barangay Secretary</strong>.
                  </div>
                </div>
                <?php endif; // isChairperson ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</main>

<script>

document.querySelectorAll('.toggle-row-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const rid  = btn.dataset.rid;
    const dr   = document.getElementById(rid);
    const icon = btn.querySelector('.toggle-icon');
    const open = dr.classList.toggle('open');
    icon.style.transform = open ? 'rotate(90deg)' : '';
  });
});
document.querySelectorAll('.summary-row').forEach(row => {
  row.addEventListener('click', (e) => {
    if (e.target.closest('input[type=checkbox]') || e.target.closest('a') || e.target.closest('button')) return;
    const rid  = row.dataset.rid;
    const dr   = document.getElementById(rid);
    const icon = row.querySelector('.toggle-icon');
    const open = dr.classList.toggle('open');
    if(icon) icon.style.transform = open ? 'rotate(90deg)' : '';
  });
});


</script>
<?php include '_eb_footer.php'; ?>
</body>
</html>