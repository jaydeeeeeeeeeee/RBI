<?php
require_once __DIR__.'/auth.php';
requireRole(); // all roles can add records
// date_default_timezone_set already called in auth.php

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $cf   = trim($_POST['complainant_first']  ?? '');
    $cm   = trim($_POST['complainant_middle'] ?? '');
    $cl   = trim($_POST['complainant_last']   ?? '');
    $cage = (int)($_POST['complainant_age']   ?? 0);
    $caddr= trim($_POST['complainant_address']?? '');

    $rf   = trim($_POST['respondent_first']   ?? '');
    $rm   = trim($_POST['respondent_middle']  ?? '');
    $rl   = trim($_POST['respondent_last']    ?? '');
    $raddr= trim($_POST['respondent_address'] ?? '');

    $bc   = trim($_POST['brief_case']         ?? '');
    $when = $_POST['when_incident']           ?? '';
    $where= trim($_POST['where_incident']     ?? '');
    $disp = trim($_POST['disposition']        ?? '');

    $today = date("Y-m-d");

    if (empty($when))                $errors[] = "Incident date is required.";
    elseif (!strtotime($when))       $errors[] = "Incident date is not a valid date.";
    elseif ($when > $today)          $errors[] = "Incident date cannot be in the future.";
    if (empty($bc))                  $errors[] = "Description is required.";
    if (empty($where))               $errors[] = "Location is required.";
    if ($cf===''||$cl===''||$rf===''||$rl==='')
                                     $errors[] = "Complainant and respondent first/last names are required.";
    if (empty($caddr))               $errors[] = "Complainant address is required.";
    if ($cage < 18 || $cage > 130)   $errors[] = "Complainant age must be between 18 and 130.";
    if ($disp === '')                $errors[] = "Please select a record type.";

    if (empty($errors)) {
        $year   = date("Y");
        $uname  = currentUser()['full_name'];
        $status = "Pending";

        // Sequence table prevents race-condition duplicate case IDs
        $conn->query("
            CREATE TABLE IF NOT EXISTS eb_case_sequence (
                seq_year  CHAR(4)      NOT NULL,
                seq_next  INT UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY (seq_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->begin_transaction();
        $stmtIns = $conn->prepare("INSERT IGNORE INTO eb_case_sequence (seq_year, seq_next) VALUES (?, 1)");
        $stmtIns->bind_param('s', $year); $stmtIns->execute();
        $stmtSel = $conn->prepare("SELECT seq_next FROM eb_case_sequence WHERE seq_year=? FOR UPDATE");
        $stmtSel->bind_param('s', $year); $stmtSel->execute();
        $seqRes  = $stmtSel->get_result();
        $seqRow  = $seqRes ? $seqRes->fetch_assoc() : null;
        $seqNum  = $seqRow ? (int)$seqRow['seq_next'] : 1;
        $prefix  = defined('BLOTTER_CASE_PREFIX') ? BLOTTER_CASE_PREFIX : 'BRGY 410';
        $case_id = "$prefix - " . str_pad($seqNum, 4, '0', STR_PAD_LEFT) . "-$year";
        $stmtUpd = $conn->prepare("UPDATE eb_case_sequence SET seq_next=seq_next+1 WHERE seq_year=?");
        $stmtUpd->bind_param('s', $year); $stmtUpd->execute();

        $sql  = "INSERT INTO blotter_cases
          (case_id, complainant_first, complainant_middle, complainant_last, complainant_age, complainant_address,
           respondent_first, respondent_middle, respondent_last, respondent_address,
           when_incident, where_incident, brief_case, disposition, status, created_by)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssisssssssssss',
            $case_id, $cf, $cm, $cl, $cage, $caddr,
            $rf, $rm, $rl, $raddr,
            $when, $where, $bc, $disp, $status, $uname
        );

        if ($stmt->execute()) {
            $conn->commit();
            $success = "Record <strong>" . htmlspecialchars($case_id) . "</strong> saved successfully.";
            auditLog($conn,'ADD_CASE',$case_id,'Added by '.$uname);
        } else {
            $conn->rollback();
            $errors[] = "Error saving record: " . $conn->error;
        }
    }
}
$active_page = 'add';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Record - eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css?v=<?=filemtime(dirname(__DIR__).'/assets/css/main.css')?>">
  <link rel="stylesheet" href="eblotter.css?v=<?=filemtime(__DIR__.'/eblotter.css')?>">
  <style>
    .disp-cards { display: flex; gap: 1rem; margin-top: .5rem; }
    .disp-card  { flex: 1; border: 2px solid var(--gray200); border-radius: 12px; padding: 1.25rem; cursor: pointer; transition: border-color .2s, background .2s; text-align: center; }
    .disp-card:hover { border-color: var(--accent); }
    .disp-card input[type=radio] { display: none; }
    .disp-card i { font-size: 1.8rem; margin-bottom: .5rem; color: var(--gray400); display: block; }
    .disp-card span { font-weight: 700; font-size: .95rem; color: var(--gray600); }
    .disp-card.selected { border-color: var(--accent); background: #eff6ff; }
    .disp-card.selected i, .disp-card.selected span { color: var(--accent); }

    .step-actions { display: flex; gap: .75rem; margin-top: 1.75rem; align-items: center; }
    .step-counter { font-size: .82rem; color: var(--gray400); margin-left: auto; }
    .name-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .75rem; }
    @media(max-width:600px) { .name-row { grid-template-columns: 1fr; } .disp-cards { flex-direction: column; } }

    .success-banner {
      background: #f0fdf4; border: 1.5px solid #bbf7d0;
      border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 1rem;
    }
    .success-banner i { color: var(--green); font-size: 1.5rem; }
    .success-banner a { color: var(--accent); font-weight: 600; text-decoration: none; }

    /*Age field*/
    input[name="complainant_age"] { max-width: 110px; }
  </style>
</head>
<body>


<?php
$hero_mode   = true;
$hero_title  = 'Add Record';
$hero_active = 'add';
include '_eb_topbar.php';
include '_eb_hero.php';
?>
<main style="padding:1.5rem;max-width:1100px;margin:0 auto">
<div class="eb-form-card">

  <?php if ($errors): ?>
    <div class="eb-alert eb-alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-banner">
      <i class="fas fa-check-circle"></i>
      <div>
        <?= $success ?><br>
        <a href="add_case.php">Add another record</a> &nbsp;|&nbsp;
        <a href="view_cases.php">View all records</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="eb-steps-bar">
    <div class="eb-step-tab active" id="tab0">1. Type</div>
    <div class="eb-step-tab" id="tab1">2. Complainant</div>
    <div class="eb-step-tab" id="tab2">3. Respondent</div>
    <div class="eb-step-tab" id="tab3">4. Incident</div>
  </div>

  <form method="post" id="caseForm">
    <?= csrfField() ?>

    <!-- STEP 0 -->
    <div class="eb-step visible" id="step0">
      <h2 class="eb-form-card" style="font-family:'Syne',sans-serif;font-size:1.4rem;color:var(--navy);margin-bottom:1rem;">
        What type of record is this?
      </h2>
      <div class="disp-cards">
        <label class="disp-card" id="dc-complain">
          <input type="radio" name="disposition" value="Complain">
          <i class="fas fa-exclamation-triangle"></i>
          <span>Complain</span>
          <p style="font-size:.77rem;color:var(--gray400);margin-top:.25rem;">Formal complaint against a party</p>
        </label>
        <label class="disp-card" id="dc-record">
          <input type="radio" name="disposition" value="Record">
          <i class="fas fa-book-open"></i>
          <span>Record</span>
          <p style="font-size:.77rem;color:var(--gray400);margin-top:.25rem;">For documentation purposes</p>
        </label>
      </div>
    </div>

    <!-- STEP 1 -->
    <div class="eb-step" id="step1">
      <h2 style="font-family:'Syne',sans-serif;font-size:1.1rem;color:var(--navy);margin-bottom:1rem;">
        <i class="fas fa-user" style="color:var(--accent)"></i> Complainant Information
      </h2>
      <div class="name-row">
        <div class="eb-field"><label>First Name *</label><input type="text" name="complainant_first" placeholder="e.g. Juan"></div>
        <div class="eb-field"><label>Middle Name</label><input type="text" name="complainant_middle" placeholder="Optional"></div>
        <div class="eb-field"><label>Last Name *</label><input type="text" name="complainant_last" placeholder="e.g. dela Cruz"></div>
      </div>
      <div class="eb-row">
        <div class="eb-field" style="max-width:180px;">
          <label>Age * (18–130)</label>
          <input type="number" name="complainant_age" min="18" max="130" maxlength="3"
                 placeholder="Age" oninput="if(this.value.length>3)this.value=this.value.slice(0,3);">
        </div>
      </div>
      <div class="eb-field"><label>Address *</label><input type="text" name="complainant_address" placeholder="Full address"></div>
    </div>

    <!-- STEP 2 -->
    <div class="eb-step" id="step2">
      <h2 style="font-family:'Syne',sans-serif;font-size:1.1rem;color:var(--navy);margin-bottom:1rem;">
        <i class="fas fa-user-shield" style="color:var(--red)"></i> Respondent Information
      </h2>
      <div class="name-row">
        <div class="eb-field"><label>First Name *</label><input type="text" name="respondent_first" placeholder="e.g. Maria"></div>
        <div class="eb-field"><label>Middle Name</label><input type="text" name="respondent_middle" placeholder="Optional"></div>
        <div class="eb-field"><label>Last Name *</label><input type="text" name="respondent_last" placeholder="e.g. Santos"></div>
      </div>
      <div class="eb-field"><label>Address</label><input type="text" name="respondent_address" placeholder="Full address"></div>
    </div>

    <!-- STEP 3 -->
    <div class="eb-step" id="step3">
      <h2 style="font-family:'Syne',sans-serif;font-size:1.1rem;color:var(--navy);margin-bottom:1rem;">
        <i class="fas fa-file-alt" style="color:var(--gold)"></i> Description of Incident
      </h2>
      <div class="eb-row">
        <div class="eb-field">
          <label>Date of Incident *</label>
          <input type="date" name="when_incident" id="whenIncident">
        </div>
        <div class="eb-field">
          <label>Location / Saan *</label>
          <input type="text" name="where_incident" placeholder="Where did it happen?">
        </div>
      </div>
      <div class="eb-field">
        <label>Brief Description *</label>
        <textarea name="brief_case" placeholder="Describe what happened in detail…" rows="5"></textarea>
      </div>
    </div>

    <div class="step-actions">
      <button type="button" class="btn btn-ghost" id="prevBtn" style="display:none;">
        <i class="fas fa-arrow-left"></i> Back
      </button>
      <button type="button" class="btn btn-primary" id="nextBtn">
        Next <i class="fas fa-arrow-right"></i>
      </button>
      <button type="submit" class="btn btn-accent" id="submitBtn" style="display:none;">
        <i class="fas fa-save"></i> Submit Record
      </button>
      <span class="step-counter" id="stepCounter">Step 1 of 4</span>
    </div>
  </form>
</div>
</main>

<script>
document.querySelectorAll('.disp-card input[type=radio]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.disp-card').forEach(c => c.classList.remove('selected'));
    radio.parentElement.classList.add('selected');
  });
});

const dateInput = document.getElementById('whenIncident');
const maxDate = new Date().toISOString().split('T')[0];
dateInput.setAttribute('max', maxDate);
dateInput.addEventListener('input', function(){
  if (this.value > maxDate) { alert('Future dates are not allowed.'); this.value = maxDate; }
});

let cur = 0;
const steps = document.querySelectorAll('.eb-step');
const tabs  = document.querySelectorAll('.eb-step-tab');
const prevBtn   = document.getElementById('prevBtn');
const nextBtn   = document.getElementById('nextBtn');
const submitBtn = document.getElementById('submitBtn');
const counter   = document.getElementById('stepCounter');

function showStep(n) {
  steps.forEach((s, i) => s.classList.toggle('visible', i === n));
  tabs.forEach((t, i) => {
    t.classList.toggle('active', i === n);
    t.classList.toggle('done', i < n);
  });
  prevBtn.style.display   = n === 0 ? 'none' : '';
  nextBtn.style.display   = n === steps.length - 1 ? 'none' : '';
  submitBtn.style.display = n === steps.length - 1 ? '' : 'none';
  counter.textContent = `Step ${n+1} of ${steps.length}`;
}

function validate(n) {
  if (n === 0) {
    if (!document.querySelector('input[name=disposition]:checked')) { alert('Please select a record type.'); return false; }
  }
  if (n === 1) {
    const age = parseInt(document.querySelector('[name=complainant_age]').value);
    if (!document.querySelector('[name=complainant_first]').value ||
        !document.querySelector('[name=complainant_last]').value ||
        !document.querySelector('[name=complainant_address]').value) {
      alert('Please complete all required complainant fields.'); return false;
    }
    if (isNaN(age) || age < 18 || age > 130) { alert('Age must be between 18 and 130.'); return false; }
  }
  if (n === 2) {
    if (!document.querySelector('[name=respondent_first]').value ||
        !document.querySelector('[name=respondent_last]').value) {
      alert('Respondent first and last name are required.'); return false;
    }
  }
  if (n === 3) {
    if (!document.querySelector('[name=when_incident]').value ||
        !document.querySelector('[name=where_incident]').value ||
        !document.querySelector('[name=brief_case]').value) {
      alert('All incident fields are required.'); return false;
    }
  }
  return true;
}

nextBtn.addEventListener('click', () => { if (validate(cur)) { cur++; showStep(cur); } });
prevBtn.addEventListener('click', () => { cur--; showStep(cur); });
showStep(0);
</script>
<?php include '_eb_footer.php'; ?>
</body>
</html>
