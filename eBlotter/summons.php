<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']); // documents: kagawad cannot access

$case_id = trim($_GET['case_id'] ?? '');

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
$today = date('F d, Y');
$cFull = $case ? trim($case['complainant_first'].' '.$case['complainant_middle'].' '.$case['complainant_last']) : '';
$rFull = $case ? trim($case['respondent_first'].' '.$case['respondent_middle'].' '.$case['respondent_last']) : '';
$signerName = getSignerName($conn);
$active_page = 'summons';

// load previously saved summons data
$savedSummons = null;
if ($case_id) {
    $stmtSS = $conn->prepare("SELECT * FROM case_summons WHERE case_id=? LIMIT 1");
    $stmtSS->bind_param('s', $case_id);
    $stmtSS->execute();
    $savedSummons = $stmtSS->get_result()->fetch_assoc();
}
function sv($saved, $key, $fallback='') {
    return htmlspecialchars($saved[$key] ?? $fallback);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Summons - eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css?v=<?=filemtime(dirname(__DIR__).'/assets/css/main.css')?>">
  <link rel="stylesheet" href="eblotter.css?v=<?=filemtime(__DIR__.'/eblotter.css')?>">
  <style>
    .doc-container{max-width:720px;margin:2rem auto;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
    .doc-toolbar{background:var(--navy);padding:.85rem 1.25rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;}
    .doc-toolbar select{border-radius:6px;border:none;padding:.4rem .7rem;font-family:inherit;font-size:.84rem;flex:1;min-width:180px;}

    .doc-body{padding:1.2cm 1.8cm;font-family:'Times New Roman',Times,serif;}

    /* HEADER with logos */
    .summons-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.4cm;}
    .summons-header .center-text{flex:1;text-align:center;font-size:9.5pt;line-height:1.55;}
    .summons-header .center-text strong{font-size:10.5pt;}
    .office-line{font-size:12pt;font-weight:700;text-align:center;text-transform:uppercase;letter-spacing:.03em;margin:.25cm 0 .15cm;}

    /* Party block */
    .party-block{display:flex;gap:1.5cm;margin-bottom:.4cm;}
    .party-col{flex:1;}
    .underline-input{display:block;border:none;border-bottom:1px solid #000;width:100%;font-family:inherit;font-size:10pt;outline:none;padding:.05cm 0;margin-bottom:.1cm;}
    .small-label{font-size:8pt;}

    .ref-col{min-width:5cm;font-size:9.5pt;}
    .ref-row{margin-bottom:.3cm;}
    .ref-row .ref-line{border:none;border-bottom:1px solid #000;font-family:inherit;font-size:9.5pt;outline:none;padding:0 .1cm;min-width:3cm;}

    .summons-title{font-size:13pt;font-weight:700;text-align:center;letter-spacing:.15em;text-transform:uppercase;margin:.4cm 0 .35cm;}

    .body-text{font-size:10pt;line-height:1.85;}
    .body-text p{margin-bottom:.3cm;}
    .il{border:none;border-bottom:1px solid #000;font-family:inherit;font-size:10pt;outline:none;padding:0 .1cm;text-align:center;}

    /* Signature — right aligned */
    .sig-right{width:8cm;margin-left:auto;margin-top:.6cm;text-align:center;}
    .sig-line{border-top:1px solid #000;margin-bottom:.1cm;}
    .sig-name{font-size:9pt;font-weight:700;text-decoration:underline;}
    .sig-role{font-size:8pt;}

    /* Officer's Return */
    .return-box{border-top:1px solid #000;margin-top:.6cm;padding-top:.4cm;}
    .return-title{text-align:center;font-weight:700;letter-spacing:.07em;font-size:10pt;margin-bottom:.4cm;text-transform:uppercase;}
    .return-text{font-size:9.5pt;line-height:1.9;}
    .return-options{font-size:9.5pt;line-height:2.1;}

    .rcv-grid{display:grid;grid-template-columns:1fr 1fr;gap:.3cm 1cm;margin-top:.5cm;}
    .rcv-block .rcv-line{border-top:1px solid #000;margin-bottom:.1cm;}
    .rcv-label{font-size:8pt;text-align:center;}
  
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
$hero_title  = 'Summons';
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
<div class="doc-container">
  <div class="doc-toolbar">
    <select onchange="if(this.value) window.location.href='summons.php?case_id='+encodeURIComponent(this.value)">
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
    <?php if (!empty($savedSummons)): ?>
    <span style="font-size:.73rem;color:#34d399;display:flex;align-items:center;gap:.35rem;margin-left:.25rem;" id="savedLabel">
      <i class="fas fa-check-circle"></i>
      Last saved by <?= htmlspecialchars($savedSummons['saved_by']) ?>
      &mdash; <?= date('M d, Y g:i A', strtotime($savedSummons['updated_at'])) ?>
    </span>
    <?php else: ?>
    <span style="font-size:.73rem;color:#94a3b8;display:none;align-items:center;gap:.35rem;margin-left:.25rem;" id="savedLabel">
      <i class="fas fa-check-circle" style="color:#34d399"></i> Draft saved
    </span>
    <?php endif; ?>
  </div>

  <form id="draftForm" method="post" action="save_draft.php" style="display:none;">
    <input type="hidden" name="doc_type"     value="summons">
    <input type="hidden" name="case_id"      id="draftCaseId"      value="<?= htmlspecialchars($case_id) ?>">
    <input type="hidden" name="to_name"      id="draftToName">
    <input type="hidden" name="hearing_day"  id="draftHDay">
    <input type="hidden" name="hearing_mo"   id="draftHMo">
    <input type="hidden" name="hearing_yr"   id="draftHYr">
    <input type="hidden" name="hearing_time" id="draftHTime">
    <input type="hidden" name="this_day"     id="draftTDay">
    <input type="hidden" name="this_mo"      id="draftTMo">
    <input type="hidden" name="this_yr"      id="draftTYr">
    <input type="hidden" name="or_respondent" id="draftOrRes">
    <input type="hidden" name="or_day"        id="draftOrDay">
    <input type="hidden" name="or_mo"         id="draftOrMo">
    <input type="hidden" name="or_yr"         id="draftOrYr">
    <input type="hidden" name="or_opt1"       id="draftOpt1">
    <input type="hidden" name="or_opt2"       id="draftOpt2">
    <input type="hidden" name="or_opt3"       id="draftOpt3">
    <input type="hidden" name="or_name3"      id="draftName3">
    <input type="hidden" name="or_opt4"       id="draftOpt4">
    <input type="hidden" name="or_name4"      id="draftName4">
  </form>

  <form id="pdfForm" method="post" action="export_summons.php" target="_blank" style="display:none;">
    <input type="hidden" name="case_id"      value="<?= htmlspecialchars($case_id) ?>">
    <input type="hidden" name="hearing_day"  id="hDay">
    <input type="hidden" name="hearing_mo"   id="hMo">
    <input type="hidden" name="hearing_yr"   id="hYr">
    <input type="hidden" name="hearing_time" id="hTime">
    <input type="hidden" name="this_day"     id="tDay">
    <input type="hidden" name="this_mo"      id="tMo">
    <input type="hidden" name="this_yr"      id="tYr">
    <input type="hidden" name="to_name"      id="toName">
    <input type="hidden" name="or_respondent" id="orRespondent">
    <input type="hidden" name="or_day"        id="orDay">
    <input type="hidden" name="or_mo"         id="orMo">
    <input type="hidden" name="or_yr"         id="orYr">
    <input type="hidden" name="or_opt1"       id="orOpt1">
    <input type="hidden" name="or_opt2"       id="orOpt2">
    <input type="hidden" name="or_opt3"       id="orOpt3">
    <input type="hidden" name="or_name3"      id="orName3">
    <input type="hidden" name="or_opt4"       id="orOpt4">
    <input type="hidden" name="or_name4"      id="orName4">
  </form>

  <div class="doc-body">
    <div class="summons-header">
      <div class="center-text">
        Republic of the Philippines<br>
        City of Manila<br>
        <strong><?= defined('BRGY_FULLNAME') ? htmlspecialchars(BRGY_FULLNAME) : 'Barangay 410 Zone 42' ?></strong><br>
        District <?= defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : 'IV' ?>
      </div>
    </div>
    <div class="office-line">Office of the Lupong Tagapamayapa</div>

    <!-- PARTY + CASE NO BLOCK -->
    <div class="party-block">
      <div class="party-col">
        <input type="text" class="underline-input" id="complainantInput" value="<?= htmlspecialchars($cFull) ?>" style="text-align:center;">
        <div class="small-label" align="center">Complainant/s</div>
        <div style="font-style:italic;font-size:9.5pt;margin:.2cm 0;" align="center">-against-</div>
        <input type="text" class="underline-input" value="<?= htmlspecialchars($rFull) ?>" style="text-align:center;">
        <div class="small-label" align="center">Respondent/s</div>
      </div>
      <div class="ref-col">
        <div class="ref-row">
          Barangay Case No.<br>
          <input type="text" class="ref-line" value="<?= htmlspecialchars($case_id) ?>" style="text-align:center;">
        </div>
        <div class="ref-row">
          For:<br>
          <input type="text" class="ref-line" value="<?= $case?htmlspecialchars($case['disposition']):'' ?>" style="text-align:center;">
        </div>
      </div>
    </div>

    <!-- SUMMONS -->
    <div class="summons-title">S U M M O N S</div>

    <div class="body-text">
      <p>To: <input type="text" class="il" id="toField" value="<?= sv($savedSummons,'to_name',$rFull) ?>" style="min-width:10cm;"></p>
      <p style="font-weight:700;">Respondent/s</p>
      <p>
        &nbsp;&nbsp;&nbsp;&nbsp;You are hereby summoned to appear before me in person together with your witnesses,
        on the <input class="il" id="dayIn" placeholder="____" style="min-width:1.4cm;" value="<?= sv($savedSummons,'hearing_day') ?>"> day of
        <input class="il" id="moIn" placeholder="__________" style="min-width:3.5cm;" value="<?= sv($savedSummons,'hearing_mo') ?>">
        <strong>20</strong><input class="il" id="yrIn" placeholder="__" style="min-width:1cm;" value="<?= sv($savedSummons,'hearing_yr') ?>"> at
        <input class="il" id="timeIn" placeholder="______" style="min-width:1.8cm;" value="<?= sv($savedSummons,'hearing_time') ?>"> am/pm, then and there to answer to a
        complaint made before me, copy of which is attached hereto, for mediation/conciliation of your dispute with complainant/s.
      </p>
      <p>
        &nbsp;&nbsp;&nbsp;&nbsp;You are hereby warned that if you refuse or willfully fail to appear in obedience to this
        summons, you may be barred from filing any counterclaim arising from said complaint.
      </p>
      <p>FAIL NOT or else face punishment as for contempt of court.</p>
      <p>
        This <input class="il" id="thisDayIn" placeholder="________" style="min-width:2.5cm;" value="<?= sv($savedSummons,'this_day') ?>"> day of
        <input class="il" id="thisMoIn" placeholder="______________" style="min-width:4.5cm;" value="<?= sv($savedSummons,'this_mo') ?>">, 20<input class="il" id="thisYrIn" placeholder="__" style="min-width:1cm;" value="<?= sv($savedSummons,'this_yr') ?>">.
      </p>
    </div>

    <!-- Signature — right -->
    <div class="sig-right">
      <div class="sig-line"></div>
      <div class="sig-name"><?= htmlspecialchars($signerName) ?></div>
      <div class="sig-role">Punong Barangay</div>
    </div>

    <!-- OFFICER'S RETURN -->
    <div class="return-box">
      <div class="return-title">Officer's Return</div>
      <div class="return-text">
        I served this summons upon respondent
        <input class="il" id="orRespondentIn" style="min-width:6cm;" value="<?= sv($savedSummons,'or_respondent') ?>"> on the
        <input class="il" id="orDayIn" style="min-width:1.4cm;" value="<?= sv($savedSummons,'or_day') ?>"> day of
        <input class="il" id="orMoIn" style="min-width:4.5cm;" value="<?= sv($savedSummons,'or_mo') ?>">,
        <strong>20</strong><input class="il" id="orYrIn" style="min-width:1cm;" value="<?= sv($savedSummons,'or_yr') ?>">.
      </div>
      <div class="return-options" style="margin-top:.3cm;">
        <input class="il" id="orOpt1In" autocomplete="off" style="min-width:1.4cm;" value="<?= sv($savedSummons,'or_opt1') ?>"> 1. Handing to him/them said summons in person, or<br>
        <input class="il" id="orOpt2In" autocomplete="off" style="min-width:1.4cm;" value="<?= sv($savedSummons,'or_opt2') ?>"> 2. Handing to him/them said summons and he/they refused to received it, or<br>
        <input class="il" id="orOpt3In" autocomplete="off" style="min-width:1.4cm;" value="<?= sv($savedSummons,'or_opt3') ?>"> 3. Leaving said summons at his/their dwelling with <input class="il" id="orName3In" autocomplete="off" style="min-width:5cm;" value=""> (name) a person suitable age and discretion residing therein, or<br>
        <input class="il" id="orOpt4In" autocomplete="off" style="min-width:1.4cm;" value=""> 4. Leaving said summons at his/their office/place of business with <input class="il" id="orName4In" autocomplete="off" style="min-width:5cm;" value=""> (name) a competent person in charge thereof.
      <!-- Officer sig — right -->
      <div class="sig-right" style="margin-top:.5cm;">
        <div class="sig-line"></div>
        <div class="sig-role">Officer</div>
      </div>
      <div style="font-size:9.5pt;margin-top:.4cm;">Received by Respondent/s representative/s:</div>
      <div class="rcv-grid">
        <div class="rcv-block"><div class="rcv-line"></div><div class="rcv-label">Signature</div></div>
        <div class="rcv-block"><div class="rcv-line"></div><div class="rcv-label">Date</div></div>
      </div>
      <div class="rcv-grid" style="margin-top:.4cm;">
        <div class="rcv-block"><div class="rcv-line"></div><div class="rcv-label">Signature</div></div>
        <div class="rcv-block"><div class="rcv-line"></div><div class="rcv-label">Date</div></div>
      </div>
    </div>
  </div>
</div>
</main>

<script>
function saveDraft() {
  document.getElementById('draftToName').value   = document.getElementById('toField').value;
  document.getElementById('draftHDay').value     = document.getElementById('dayIn').value;
  document.getElementById('draftHMo').value      = document.getElementById('moIn').value;
  document.getElementById('draftHYr').value      = document.getElementById('yrIn').value;
  document.getElementById('draftHTime').value    = document.getElementById('timeIn').value;
  document.getElementById('draftTDay').value     = document.getElementById('thisDayIn').value;
  document.getElementById('draftTMo').value      = document.getElementById('thisMoIn').value;
  document.getElementById('draftTYr').value      = document.getElementById('thisYrIn').value;
  document.getElementById('draftOrRes').value    = document.getElementById('orRespondentIn').value;
  document.getElementById('draftOrDay').value    = document.getElementById('orDayIn').value;
  document.getElementById('draftOrMo').value     = document.getElementById('orMoIn').value;
  document.getElementById('draftOrYr').value     = document.getElementById('orYrIn').value;
  document.getElementById('draftOpt1').value     = document.getElementById('orOpt1In').value;
  document.getElementById('draftOpt2').value     = document.getElementById('orOpt2In').value;
  document.getElementById('draftOpt3').value     = document.getElementById('orOpt3In').value;
  document.getElementById('draftName3').value = '';
  document.getElementById('draftOpt4').value = document.getElementById('orOpt4In').value;
  document.getElementById('draftName4').value = '';

  const btn = document.getElementById('saveDraftBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

  fetch('save_draft.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: new FormData(document.getElementById('draftForm'))
  }).then(r => r.text()).then(raw => {
    console.log('save_draft raw response:', raw);
    let data;
    try { data = JSON.parse(raw); } catch(e) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
      alert('Server error. Check console (F12) for details.');
      return;
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
    if (data.ok) {
      const lbl = document.getElementById('savedLabel');
      lbl.style.display = 'flex';
      lbl.innerHTML = '<i class="fas fa-check-circle" style="color:#34d399"></i> Draft saved — ' + data.saved_at;
    } else {
      alert('Save failed: ' + (data.error || 'Unknown error'));
    }
  }).catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
    alert('Network error: ' + err);
  });
}

function populateExportFields(){
  document.getElementById('hDay').value        = document.getElementById('dayIn').value         || '';
  document.getElementById('hMo').value         = document.getElementById('moIn').value          || '';
  document.getElementById('hYr').value         = document.getElementById('yrIn').value          || '';
  document.getElementById('hTime').value       = document.getElementById('timeIn').value        || '';
  document.getElementById('tDay').value        = document.getElementById('thisDayIn').value     || '';
  document.getElementById('tMo').value         = document.getElementById('thisMoIn').value      || '';
  document.getElementById('tYr').value         = document.getElementById('thisYrIn').value      || '';
  document.getElementById('toName').value      = document.getElementById('toField').value;
  document.getElementById('orRespondent').value= document.getElementById('orRespondentIn').value|| '';
  document.getElementById('orDay').value       = document.getElementById('orDayIn').value       || '';
  document.getElementById('orMo').value        = document.getElementById('orMoIn').value        || '';
  document.getElementById('orYr').value        = document.getElementById('orYrIn').value        || '';
  document.getElementById('orOpt1').value      = document.getElementById('orOpt1In').value      || '';
  document.getElementById('orOpt2').value      = document.getElementById('orOpt2In').value      || '';
  document.getElementById('orOpt3').value      = document.getElementById('orOpt3In').value      || '';
  document.getElementById('orName3').value = '';
  document.getElementById('orOpt4').value      = document.getElementById('orOpt4In').value      || '';
  document.getElementById('orName4').value = '';
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
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeExportPwModal(); });

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
