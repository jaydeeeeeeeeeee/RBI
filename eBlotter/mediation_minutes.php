<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']); // documents: kagawad cannot access


$case_id = trim($_GET['case_id'] ?? '');

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
$today = date('F d, Y');
$cFull = $case ? trim($case['complainant_first'].' '.$case['complainant_middle'].' '.$case['complainant_last']) : '';
$rFull = $case ? trim($case['respondent_first'].' '.$case['respondent_middle'].' '.$case['respondent_last']) : '';
$signerName = getSignerName($conn);
$active_page = 'mediation';

// load previously saved mediation data
$savedMediation = null;
if ($case_id) {
    $stmtSM = $conn->prepare("SELECT * FROM case_mediation WHERE case_id=? LIMIT 1");
    $stmtSM->bind_param('s', $case_id);
    $stmtSM->execute();
    $savedMediation = $stmtSM->get_result()->fetch_assoc();
}
// sv_saved() is defined in auth.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mediation Minutes - eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="eblotter.css">
  <style>
    .doc-container{max-width:720px;margin:2rem auto;background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
    .doc-toolbar{background:var(--navy);padding:.85rem 1.25rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;}
    .doc-toolbar select{border-radius:6px;border:none;padding:.4rem .7rem;font-family:inherit;font-size:.84rem;flex:1;min-width:180px;}

    .doc-body{
      padding:1.5cm 2cm;
      font-family:'Times New Roman',Times,serif;
      background:#fff;
    }

    .med-header{text-align:center;margin-bottom:.5cm;}
    .med-header p{font-size:9pt;line-height:1.5;margin:0;}
    .med-header .office{font-size:11pt;font-weight:700;text-transform:uppercase;margin:.2cm 0 .05cm;}
    .med-header .address{font-size:9pt;}
    .med-header-rule{border:none;border-top:1.5px solid #000;margin:.25cm 0;}

    .med-title{font-size:14pt;font-weight:700;text-align:center;letter-spacing:.05em;text-transform:uppercase;margin:.2cm 0 .35cm;}

    /* Case No. — top right */
    .caseno-row{display:flex;justify-content:flex-end;margin-bottom:.3cm;font-size:9.5pt;}
    .caseno-row span{margin-right:.15cm;}
    .caseno-row input{border:none;border-bottom:1px solid #000;min-width:3.5cm;font-family:inherit;font-size:9.5pt;outline:none;padding:0 .1cm;}

    /* DATE / COMPLAINANT / RESPONDENT */
    .field-row{display:flex;align-items:baseline;margin-bottom:.25cm;font-size:10pt;}
    .field-row .fl{font-weight:700;white-space:nowrap;margin-right:.2cm;font-size:10pt;}
    .field-row input{border:none;border-bottom:1px solid #000;flex:1;font-family:inherit;font-size:10pt;outline:none;padding:0 .1cm;}

    /* Lined writing area */
    .writing-area{margin:.4cm 0;}
    .writing-area textarea{
      width:100%;
      font-family:'Times New Roman',Times,serif;
      font-size:10.5pt;
      border:none;
      outline:none;
      resize:none;
      background:transparent;
      line-height:2.05;
      min-height:11cm;
      /* ruled lines */
      background-image:repeating-linear-gradient(
        transparent,
        transparent calc(2.05em - 1px),
        #000 calc(2.05em - 1px),
        #000 2.05em
      );
      padding:0;
      overflow:hidden;
    }

    .sig-block{margin-top:.6cm;display:grid;grid-template-columns:1fr 1fr;gap:.5cm 1.5cm;}
    .sig-col{text-align:center;}
    .sig-line{border-top:1px solid #000;margin-bottom:.1cm;}
    .sig-label{font-size:8.5pt;}
    .sig-name{font-size:9pt;font-weight:700;text-decoration:underline;}
  
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
    .pw-gate-modal h3 { font-family: 'DM Serif Display',serif; font-size: 1.25rem; color: var(--navy); margin-bottom: .35rem; }
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
<nav class="eb-navbar">
  <a class="brand" href="eblotter_home.php">
    <img src="../eBlotter/images/Barangay_logo_409.png" alt="Logo">Barangay 409</a>
  <?php $u=currentUser(); if($u): ?>
  <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto;margin-right:3.5rem;font-size:.75rem;color:rgba(255,255,255,.6);">
    <?php
      $rIcons=['chairperson'=>'fas fa-crown','secretary'=>'fas fa-user-tie','kagawad'=>'fas fa-user'];
      $rColors=['chairperson'=>'#fbbf24','secretary'=>'#34d399','kagawad'=>'#60a5fa'];
      $role=$u['role'];
      echo "<i class='{$rIcons[$role]}' style='color:{$rColors[$role]};margin-right:4px'></i>";
      echo htmlspecialchars($u['full_name'])." (".ucfirst($role).")";
    ?>
    &nbsp;<a href="logout.php" style="color:rgba(255,255,255,.4);text-decoration:none;"><i class="fas fa-sign-out-alt"></i></a>
  </div>
  <?php endif; ?>
</nav>
<div class="hero-banner">
  <div class="inner">
    <h1>Mediation Minutes</h1>
    <p>Barangay 409 Case Management System — City of Manila, District IV</p>
    <div class="hero-actions">
      <a href="eblotter_home.php" class="ha-btn"><i class="fas fa-home"></i> Home</a>
      <a href="add_case.php"      class="ha-btn"><i class="fas fa-plus-circle"></i> Add Record</a>
      <a href="view_cases.php"    class="ha-btn"><i class="fas fa-list"></i> View Records</a>
    </div>
  </div>
</div>

<main class="eb-main">
<div class="doc-container">
  <div class="doc-toolbar">
    <select onchange="if(this.value) window.location.href='mediation_minutes.php?case_id='+encodeURIComponent(this.value)">
      <option value="">— Select a Record —</option>
      <?php while ($r=$allCases->fetch_assoc()): ?>
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
    <?php if (!empty($savedMediation)): ?>
    <span style="font-size:.73rem;color:#34d399;display:flex;align-items:center;gap:.35rem;margin-left:.25rem;" id="savedLabel">
      <i class="fas fa-check-circle"></i>
      Last saved by <?= htmlspecialchars($savedMediation['saved_by']) ?>
      &mdash; <?= date('M d, Y g:i A', strtotime($savedMediation['updated_at'])) ?>
    </span>
    <?php else: ?>
    <span style="font-size:.73rem;color:#94a3b8;display:none;align-items:center;gap:.35rem;margin-left:.25rem;" id="savedLabel">
      <i class="fas fa-check-circle" style="color:#34d399"></i> Draft saved
    </span>
    <?php endif; ?>
  </div>

  <form id="draftForm" method="post" action="save_draft.php" style="display:none;">
    <input type="hidden" name="doc_type"        value="mediation">
    <input type="hidden" name="csrf_token"       value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="case_id"         value="<?= htmlspecialchars($case_id) ?>">
    <input type="hidden" name="agreement_text"  id="draftMedAgreement">
  </form>

  <form id="pdfForm" method="post" action="export_mediation.php" target="_blank" style="display:none;">
    <input type="hidden" name="case_id"          value="<?= htmlspecialchars($case_id) ?>">
    <input type="hidden" name="agreement_text"    id="medAgreement"  value="<?= sv_saved($savedMediation,'agreement_text') ?>">
  </form>

  <div class="doc-body">
    <div class="med-header">
      <p>Republic of the Philippines</p>
      <p>City of Manila</p>
      <p>District IV</p>
      <div class="office">Office of Lupong Tagapamayapa</div>
      <div class="address">254 Sta. Teresita St. Sampaloc, Manila</div>
    </div>
    <hr class="med-header-rule">

    <div class="med-title">Mediation Minutes</div>

    <!-- CASE NO. — top right -->
    <div class="caseno-row">
      <span>CASE NO.</span>
      <input type="text" value="<?= htmlspecialchars($case_id) ?>">
    </div>

    <!-- DATE -->
    <div class="field-row">
      <span class="fl">DATE</span>
      <input type="text" value="<?= $today ?>">
    </div>

    <!-- COMPLAINANT/S -->
    <div class="field-row">
      <span class="fl">COMPLAINANT/S</span>
      <input type="text" value="<?= htmlspecialchars($cFull) ?>">
    </div>

    <!-- RESPONDENT/S -->
    <div class="field-row">
      <span class="fl">RESPONDENT/S</span>
      <input type="text" value="<?= htmlspecialchars($rFull) ?>">
    </div>

    <!-- LINED WRITING AREA -->
    <div class="writing-area">
      <textarea id="minutesArea"
        placeholder="Enter the minutes of the mediation proceedings here…"
        oninput="auto_grow(this)"><?= htmlspecialchars($savedMediation['agreement_text'] ?? '') ?></textarea>
    </div>

    <!-- SIGNATURE BLOCK — 4 columns matching physical form -->
    <div class="sig-block">
      <!-- Left column -->
      <div>
        <div class="sig-col" style="margin-bottom:.8cm;">
          <div class="sig-line"></div>
          <div class="sig-label">Complainant/s</div>
        </div>
        <div class="sig-col" style="margin-bottom:.8cm;">
          <div class="sig-line"></div>
          <div class="sig-label">Witness</div>
        </div>
        <div class="sig-col">
          <div class="sig-line"></div>
          <div class="sig-name"><?= htmlspecialchars($signerName) ?></div>
          <div class="sig-label">Lupon Chairman/Punong Barangay</div>
        </div>
      </div>
      <!-- Right column -->
      <div>
        <div class="sig-col" style="margin-bottom:.8cm;">
          <div class="sig-line"></div>
          <div class="sig-label">Respondent/s</div>
        </div>
        <div class="sig-col" style="margin-bottom:.8cm;">
          <div class="sig-line"></div>
          <div class="sig-label">Witness</div>
        </div>
        <div class="sig-col">
          <div class="sig-line"></div>
          <div class="sig-name">MA. VERONICA M. PAJARES</div>
          <div class="sig-label">Lupon Secretary/Barangay Secretary</div>
        </div>
      </div>
    </div>
  </div>
</div>
</main>

<script>
function auto_grow(el){ el.style.height='auto'; el.style.height=(el.scrollHeight)+'px'; }
document.addEventListener('DOMContentLoaded', function(){
  var ta = document.getElementById('minutesArea');
  if (ta) auto_grow(ta);
});

function saveDraft() {
  document.getElementById('draftMedAgreement').value =
    document.getElementById('minutesArea')?.value || '';

  const btn = document.getElementById('saveDraftBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  fetch('save_draft.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: new FormData(document.getElementById('draftForm'))
  }).then(r => r.json()).then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';

    if (!data.ok) {
      alert(data.error || 'Save failed.');
      return;
    }

    const lbl = document.getElementById('savedLabel');
    lbl.style.display = 'flex';
    lbl.innerHTML = '<i class="fas fa-check-circle" style="color:#34d399"></i> Draft saved - ' + data.saved_at;
  }).catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
    alert('Save failed. Please try again.');
  });
}

function populateExportFields(){
  document.getElementById('medAgreement').value  = document.getElementById('minutesArea').value;
}
function openExportPwModal() {
  populateExportFields();
  document.getElementById('exportPwOverlay').classList.add('open');
  setTimeout(()=>document.getElementById('exportPwInput').focus(), 100);
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
  // Set sessionStorage so update_case.php shows the mediation done modal on return
  const caseId = '<?= addslashes($case_id) ?>';
  sessionStorage.setItem('mediationExporting', caseId);
  // Redirect back to update_case after a moment (PDF opens in new tab/window)
  setTimeout(function(){
    window.location.href = 'update_case.php?case_id=' + encodeURIComponent(caseId) + '&mediation_done=1';
  }, 1800);
}
document.addEventListener('keydown', function(e){
  if(e.key==='Escape') closeExportPwModal();
  if(e.key==='Enter' && document.getElementById('exportPwOverlay').classList.contains('open')) confirmExportPw();
});

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

</body>
</html>