<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']); // documents: secretary only

$case_id = trim($_GET['case_id'] ?? '');
$noticeExported = isset($_GET['exported']) && $_GET['exported'] === '1';

// auth.php; fallback for safety
if (!function_exists("verifyCurrentUserOrChairPassword")) {
    function verifyCurrentUserOrChairPassword(mysqli $conn, string $attempt): bool {
        if (isChairperson()) {
            $uid = currentUser()['id'];
            $s = $conn->prepare("SELECT password FROM admins WHERE id=? LIMIT 1");
            if (!$s) return false;
            $s->bind_param('i', $uid); $s->execute();
            $row = $s->get_result()->fetch_assoc();
            return $row && password_verify($attempt, $row['password']);
        }
        return verifyChairpersonPassword($conn, $attempt);
    }
}
// Password gate
$_pwGateOk = false;
$docPwError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_pw'])) {
    $docPwAttempt = $_POST['doc_pw'] ?? '';
    if (verifyCurrentUserOrChairPassword($conn, $docPwAttempt)) {
        $_pwGateOk = true;
    } else {
        $docPwError = isSecretary() ? "Incorrect. Secretary must enter the Chairperson's password." : "Incorrect password.";
    }
}
$case = null;
if ($case_id) {
    $stmt = $conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
    $stmt->bind_param('s',$case_id); $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
}
$allCases = $conn->query("SELECT case_id, complainant_first, complainant_last FROM blotter_cases ORDER BY created_at DESC");
$cFull = $case ? trim($case['complainant_first'].' '.$case['complainant_middle'].' '.$case['complainant_last']) : '';
$signerName = getSignerName($conn);
$active_page = 'notice';

// load previously saved notice data
$savedNotice = null;
if ($case_id) {
    $stmtSN = $conn->prepare("SELECT * FROM case_notice WHERE case_id=? LIMIT 1");
    $stmtSN->bind_param('s', $case_id);
    $stmtSN->execute();
    $savedNotice = $stmtSN->get_result()->fetch_assoc();
}
function sv_n($saved, $key, $fallback='') {
    return htmlspecialchars($saved[$key] ?? $fallback);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notice of Hearing — eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css?v=<?=filemtime(dirname(__DIR__).'/assets/css/main.css')?>">
  <link rel="stylesheet" href="eblotter.css?v=<?=filemtime(__DIR__.'/eblotter.css')?>">
  <style>
    .doc-container{max-width:680px;margin:2rem auto;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
    .doc-toolbar{background:var(--navy);padding:.85rem 1.25rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;}
    .doc-toolbar select{border-radius:6px;border:none;padding:.4rem .7rem;font-family:inherit;font-size:.84rem;flex:1;min-width:180px;}

    .doc-body{font-family:'Times New Roman',Times,serif;}

    /* One half-page copy — matching physical form */
    .notice-copy{padding:1cm 1.6cm 0.6cm;}

    .notice-head{text-align:center;margin-bottom:0.3cm;}
    .notice-head p{font-size:9pt;line-height:1.4;margin:0;font-style:italic;}
    .notice-head .office-t{font-size:9.5pt;font-weight:700;text-transform:uppercase;margin:0.15cm 0 0;letter-spacing:0.01em;}
    .notice-head .main-t{font-size:15pt;font-weight:900;margin:0.2cm 0 0;}
    .notice-head .sub-t{font-size:12pt;font-weight:900;margin:0;}

    .to-block{margin:0.3cm 0 0.05cm;font-size:10pt;display:flex;flex-direction:column;}
    .to-row{display:flex;align-items:baseline;gap:0.2cm;}
    .to-line{border:none;border-bottom:1px solid #000;font-family:inherit;font-size:10pt;outline:none;padding:0 0.1cm;min-width:5cm;}
    .to-sub{font-size:8.5pt;margin-top:0.05cm;}

    .notice-body{font-size:10pt;line-height:1.8;margin:0.25cm 0;text-align:justify;}
    .il{border:none;border-bottom:1px solid #000;font-family:inherit;font-size:10pt;outline:none;padding:0 0.1cm;text-align:center;}

    .sig-right{text-align:center;margin-left:auto;width:6.5cm;margin-top:0.25cm;}
    .sig-right .sig-line{border-top:1px solid #000;margin-bottom:0.05cm;}
    .sig-right .sig-name{font-size:9.5pt;font-weight:700;text-decoration:underline;}
    .sig-right .sig-role{font-size:8.5pt;}

    .notif-line{font-size:10pt;margin-top:0.4cm;}

    .copy-sep{border:none;border-top:1px dashed #888;margin:0.2cm 0;}

   
  
    .pw-gate-overlay {
      position: fixed; inset: 0; background: rgba(15,23,42,.65);
      z-index: 9000; display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(3px);
    }
    .pw-gate-modal {
      background: #fff; border-radius: 16px; padding: 2rem;
      width: 90%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.3);
      text-align: center;
    }
    .pw-gate-modal .lock-icon { font-size: 2.5rem; color: var(--navy); margin-bottom: 1rem; }
    .pw-gate-modal h3 { font-family: 'Syne',sans-serif; font-size: 1.25rem; color: var(--navy); margin-bottom: .35rem; }
    .pw-gate-modal p { font-size: .85rem; color: var(--gray400); margin-bottom: 1.25rem; }
    .pw-gate-modal input {
      width: 100%; border: 1.5px solid var(--gray200); border-radius: 9px;
      padding: .65rem 1rem; font-family: inherit; font-size: .92rem;
      outline: none; margin-bottom: .85rem;
    }
    .pw-gate-modal input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    .pw-gate-err { background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:.55rem .85rem;font-size:.82rem;margin-bottom:.75rem;display:none; }
  </style>
</head>
<body>
<?php
$hero_mode   = true;
$hero_title  = 'Notice of Hearing';
$hero_active = 'view';
include '_eb_topbar.php';
include '_eb_hero.php';
?>

<!-- PASSWORD GATE OVERLAY -->
<?php if (!$_pwGateOk): ?>
<div class="pw-gate-overlay" id="pwGateOverlay">
  <div class="pw-gate-modal">
    <div class="lock-icon"><i class="fas fa-lock"></i></div>
    <h3>Authorization Required</h3>
    <p><?= isSecretary() ? "Secretary must enter the <strong>Chairperson's password</strong> to access this document." : "Enter your password to continue." ?></p>
    <form method="post" id="pwGateForm">
      <?php if($docPwError): ?><div class="pw-gate-err" id="pwErr" style="display:block;"><?= htmlspecialchars($docPwError) ?></div><?php endif; ?>
      <input type="password" name="doc_pw" id="docPwInput" placeholder="Enter password" autocomplete="off" autofocus>
      <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;">
        <i class="fas fa-unlock"></i> Unlock
      </button>
    </form>
    <div style="margin-top:.85rem;">
      <a href="view_cases.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Cancel</a>
    </div>
  </div>
</div>
<style>body { overflow: hidden; }</style>
<?php endif; ?>

<main style="padding:1.5rem;max-width:1100px;margin:0 auto">

<?php if ($noticeExported): ?>
<div id="noticeSuccessBanner" style="
  background:linear-gradient(135deg,#16a34a,#15803d);
  color:#fff;border-radius:14px;padding:1.25rem 1.5rem;
  margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;
  box-shadow:0 4px 20px rgba(22,163,74,.3);
  animation:slideFadeIn .4s ease;">
  <div style="width:46px;height:46px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <i class="fas fa-check-circle" style="font-size:1.5rem;"></i>
  </div>
  <div style="flex:1;">
    <div style="font-weight:700;font-size:1rem;margin-bottom:.2rem;">✅ Notice of Hearing exported successfully!</div>
    <div style="font-size:.84rem;opacity:.9;">
      The PDF has been generated and downloaded. Make sure to deliver the notice to the concerned parties.
      <?php if ($case_id): ?>
        <strong>Case: <?= htmlspecialchars($case_id) ?></strong>
      <?php endif; ?>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:.5rem;flex-shrink:0;">
    <a href="mediation_minutes.php?case_id=<?= urlencode($case_id) ?>" class="btn btn-ghost btn-sm" style="border-color:rgba(255,255,255,.4);color:#fff;white-space:nowrap;">
      <i class="fas fa-file-alt"></i> Next: Mediation Minutes
    </a>
    <button onclick="document.getElementById('noticeSuccessBanner').style.display='none'" style="background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:.75rem;">
      <i class="fas fa-times"></i> Dismiss
    </button>
  </div>
</div>
<style>
@keyframes slideFadeIn { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
</style>
<?php endif; ?>

<div class="doc-container">
  <div class="doc-toolbar no-print">
    <select onchange="if(this.value) window.location.href='notice_hearing.php?case_id='+encodeURIComponent(this.value)">
      <option value="">— Select a Record —</option>
      <?php while($r=$allCases->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($r['case_id']) ?>" <?= $r['case_id']===$case_id?'selected':'' ?>>
          <?= htmlspecialchars($r['case_id']) ?> — <?= htmlspecialchars($r['complainant_last'].', '.$r['complainant_first']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <button type="button" class="btn btn-accent btn-sm" onclick="openExportPwModal()">
      <i class="fas fa-file-pdf"></i> Export PDF
    </button>
    <button type="button" class="btn btn-ghost btn-sm" id="saveDraftBtn" onclick="saveDraft()">
      <i class="fas fa-save"></i> Save Draft
    </button>
    <a href="view_cases.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <?php if (!empty($savedNotice)): ?>
    <span style="font-size:.73rem;color:#34d399;display:flex;align-items:center;gap:.35rem;margin-left:.25rem;" id="savedLabel">
      <i class="fas fa-check-circle"></i>
      Last saved by <?= htmlspecialchars($savedNotice['saved_by']) ?>
      &mdash; <?= date('M d, Y g:i A', strtotime($savedNotice['updated_at'])) ?>
    </span>
    <?php else: ?>
    <span style="font-size:.73rem;color:#94a3b8;display:none;align-items:center;gap:.35rem;margin-left:.25rem;" id="savedLabel">
      <i class="fas fa-check-circle" style="color:#34d399"></i> Draft saved
    </span>
    <?php endif; ?>
  </div>

  <form id="draftForm" method="post" action="save_draft.php" style="display:none;">
    <input type="hidden" name="doc_type"  value="notice">
    <input type="hidden" name="case_id"   value="<?= htmlspecialchars($case_id) ?>">
    <input type="hidden" name="hear_day"  id="draftHDay">
    <input type="hidden" name="hear_mo"   id="draftHMo">
    <input type="hidden" name="hear_yr"   id="draftHYr">
    <input type="hidden" name="hear_time" id="draftHTime">
    <input type="hidden" name="notif_day" id="draftNDay">
    <input type="hidden" name="notif_mo"  id="draftNMo">
    <input type="hidden" name="notif_yr"  id="draftNYr">
  </form>

  <form id="pdfForm" method="post" action="export_notice.php" target="_blank" style="display:none;">
    <input type="hidden" name="case_id"   value="<?= htmlspecialchars($case_id) ?>">
    <input type="hidden" name="to_name"   id="toName">
    <input type="hidden" name="hear_day"  id="hDay">
    <input type="hidden" name="hear_mo"   id="hMo">
    <input type="hidden" name="hear_yr"   id="hYr">
    <input type="hidden" name="hear_time" id="hTime">
    <input type="hidden" name="notif_day" id="nDay">
    <input type="hidden" name="notif_mo"  id="nMo">
    <input type="hidden" name="notif_yr"  id="nYr">
  </form>

  <div class="doc-body">

    <?php for($copy=1;$copy<=2;$copy++): ?>
    <div class="notice-copy">
      <div class="notice-head">
        <p>Republic of the Philippines</p>
        <p>Province of Tondo</p>
        <p>City of Manila</p>
        <p><?= defined('BRGY_FULLNAME') ? htmlspecialchars(BRGY_FULLNAME) : 'Barangay 410 Zone 42' ?></p>
        <div class="office-t">Office of the Lupong Tagapamayapa</div>
        <div class="main-t">NOTICE OF HEARING</div>
        <div class="sub-t">(MEDIATION PROCEEDINGS)</div>
      </div>

      <div class="to-block">
        <div class="to-row">
          To:&nbsp;<div style="display:inline-flex;flex-direction:column;align-items:center;">
            <input type="text" class="to-line" id="toField<?=$copy?>" value="<?= sv_n($savedNotice,'to_name',$cFull) ?>" style="text-align:center;">
            <span class="to-sub">Complainant/s</span>
          </div>
        </div>
      </div>

      <div class="notice-body">
        &nbsp;&nbsp;&nbsp;&nbsp;You are hereby required to appear before me on the
        <input class="il" id="dayIn<?=$copy?>" placeholder="________" style="min-width:1.8cm;" value="<?= sv_n($savedNotice,'hear_day') ?>"> day of
        <input class="il" id="moIn<?=$copy?>"  placeholder="____________" style="min-width:3.5cm;" value="<?= sv_n($savedNotice,'hear_mo') ?>">,
        <strong>20</strong><input class="il" id="yrIn<?=$copy?>" placeholder="__" style="min-width:0.9cm;" value="<?= sv_n($savedNotice,'hear_yr') ?>">
        at <input class="il" id="timeIn<?=$copy?>" placeholder="______" style="min-width:1.5cm;" value="<?= sv_n($savedNotice,'hear_time') ?>"> am/pm for the hearing of your complaint.
      </div>

      <div class="sig-right">
        <div class="sig-line"></div>
        <div class="sig-name"><?= htmlspecialchars($signerName) ?></div>
        <div class="sig-role">Barangay Chairman</div>
      </div>

      <div class="notif-line">
        Notified this <input class="il" id="notifDay<?=$copy?>" placeholder="________" style="min-width:1.8cm;" value="<?= sv_n($savedNotice,'notif_day') ?>"> day of
        <input class="il" id="notifMo<?=$copy?>" placeholder="____________" style="min-width:3.5cm;" value="<?= sv_n($savedNotice,'notif_mo') ?>">,
        20<input class="il" id="notifYr<?=$copy?>" placeholder="__" style="min-width:0.9cm;" value="<?= sv_n($savedNotice,'notif_yr') ?>">.
      </div>
    </div>
    <?php if($copy===1): ?><hr class="copy-sep"><?php endif; ?>
    <?php endfor; ?>
  </div>
</div>
</main>

<script>
function saveDraft() {
  document.getElementById('draftHDay').value   = document.getElementById('dayIn1')?.value   || '';
  document.getElementById('draftHMo').value    = document.getElementById('moIn1')?.value    || '';
  document.getElementById('draftHYr').value    = document.getElementById('yrIn1')?.value    || '';
  document.getElementById('draftHTime').value  = document.getElementById('timeIn1')?.value  || '';
  document.getElementById('draftNDay').value   = document.getElementById('notifDay1')?.value || '';
  document.getElementById('draftNMo').value    = document.getElementById('notifMo1')?.value  || '';
  document.getElementById('draftNYr').value    = document.getElementById('notifYr1')?.value  || '';

  const btn = document.getElementById('saveDraftBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

  fetch('save_draft.php', {
    method: 'POST',
    body: new FormData(document.getElementById('draftForm'))
  }).then(r => r.json()).then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
    const lbl = document.getElementById('savedLabel');
    lbl.style.display = 'flex';
    lbl.innerHTML = '<i class="fas fa-check-circle" style="color:#34d399"></i> Draft saved — ' + data.saved_at;
  }).catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
    alert('Save failed. Please try again.');
  });
}

const pairs=[['toField','toField'],['dayIn','dayIn'],['moIn','moIn'],['yrIn','yrIn'],['timeIn','timeIn'],['notifDay','notifDay'],['notifMo','notifMo'],['notifYr','notifYr']];
pairs.forEach(([p])=>{
  const e1=document.getElementById(p+'1'), e2=document.getElementById(p+'2');
  if(e1&&e2) e1.addEventListener('input',()=>e2.value=e1.value);
});
function populateExportFields(){
  const g=id=>document.getElementById(id+'1')?.value||'';
  document.getElementById('toName').value = g('toField')||'<?= addslashes($cFull) ?>';
  document.getElementById('hDay').value   = g('dayIn')    ||'______';
  document.getElementById('hMo').value    = g('moIn')     ||'____________';
  document.getElementById('hYr').value    = g('yrIn')     ||'__';
  document.getElementById('hTime').value  = g('timeIn')   ||'____';
  document.getElementById('nDay').value   = g('notifDay') ||'______';
  document.getElementById('nMo').value    = g('notifMo')  ||'____________';
  document.getElementById('nYr').value    = g('notifYr')  ||'__';
}

function openExportPwModal() {
  populateExportFields();
  document.getElementById('exportPwOverlay').classList.add('open');
  setTimeout(function(){
    var inp = document.getElementById('exportPwInput');
    inp.focus();
    inp.onkeydown = function(e){ if(e.key==='Enter'){ e.preventDefault(); confirmExportPw(); } };
  }, 100);
}
function closeExportPwModal() {
  document.getElementById('exportPwOverlay').classList.remove('open');
  document.getElementById('exportPwInput').value = '';
  document.getElementById('exportPwErr').style.display = 'none';
}
function confirmExportPw() {
  const pw = document.getElementById('exportPwInput').value;
  if (!pw) {
    document.getElementById('exportPwErr').textContent = 'Please enter your password.';
    document.getElementById('exportPwErr').style.display = 'block';
    return;
  }
  document.getElementById('exportPwErr').style.display = 'none';
  // Inject pw into the hidden form and submit
  let inp = document.getElementById('exportPwHidden');
  if (!inp) { inp = document.createElement('input'); inp.type='hidden'; inp.id='exportPwHidden'; inp.name='export_pw'; document.getElementById('pdfForm').appendChild(inp); }
  inp.value = pw;
  closeExportPwModal();
  document.getElementById('pdfForm').submit();
  // Set sessionStorage so update_case.php shows the celebration modal on return
  const caseId = '<?= addslashes($case_id) ?>';
  sessionStorage.setItem('noticeExporting', caseId);
  // Redirect back to update_case after (PDF opens in new tab)
  setTimeout(function(){
    window.location.href = 'update_case.php?case_id=' + encodeURIComponent(caseId) + '&notice_done=1';
  }, 1800);
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeExportPwModal(); });
document.getElementById('exportPwInput')?.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); confirmExportPw(); } });

</script>

<!-- EXPORT PASSWORD MODAL -->
<div class="eb-modal-overlay" id="exportPwOverlay" style="z-index:9500;">
  <div class="eb-modal">
    <h3><i class="fas fa-lock" style="color:var(--accent);margin-right:.5rem;"></i>Confirm Export</h3>
    <p class="text-sm text-muted" style="margin-bottom:1rem;">Enter your password to authorize this PDF export.</p>
    <div class="eb-field">
      <label>Password</label>
      <input type="password" id="exportPwInput" placeholder="Enter password" autocomplete="off">
    </div>
    <div id="exportPwErr" class="eb-alert eb-alert-error" style="display:none;"></div>
    <div class="flex gap-1 mt-2">
      <button class="btn btn-accent" onclick="confirmExportPw()" style="flex:1;justify-content:center;">
        <i class="fas fa-file-pdf"></i> Export PDF
      </button>
      <button class="btn btn-ghost" onclick="closeExportPwModal()">Cancel</button>
    </div>
  </div>
</div>
<?php include '_eb_footer.php'; ?>
</body>
</html>