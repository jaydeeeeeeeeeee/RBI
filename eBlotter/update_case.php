<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']);

function fmt_name($l,$f,$m){ return trim("$l, $f $m"); }
function status_chip($s){ return match($s){'Ongoing'=>'chip-ongoing','Resolved'=>'chip-resolved',default=>'chip-pending'}; }

$rIcons  = ['chairperson'=>'fas fa-crown','secretary'=>'fas fa-user-tie','kagawad'=>'fas fa-user'];
$rColors = ['chairperson'=>'#fbbf24','secretary'=>'#34d399','kagawad'=>'#60a5fa'];

$case_id   = trim($_GET['case_id'] ?? $_POST['case_id'] ?? '');
$errors    = [];
$success   = '';
$gateError = '';
$gateOk    = false;


// list view
if (empty($case_id) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $q   = trim($_GET['q'] ?? '');
    $sql = "SELECT case_id,complainant_first,complainant_middle,complainant_last,
                   respondent_first,respondent_middle,respondent_last,status,when_incident
            FROM blotter_cases";
    if ($q !== '') $sql .= " WHERE CONCAT(complainant_last,' ',complainant_first,' ',respondent_last,' ',respondent_first,' ',case_id) LIKE ?";
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if ($q !== '') { $like = '%' . str_replace(['%','_'],['\%','\_'],$q) . '%'; $stmt->bind_param('s', $like); }
    $stmt->execute();
    $cases = $stmt->get_result();
    $active_page = 'update';
    $u = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Update Record - eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="eblotter.css">
  <style>
    .search-bar{display:flex;gap:.6rem;align-items:center;background:var(--white);border-radius:var(--radius);padding:1rem 1.25rem;box-shadow:var(--shadow);margin-bottom:1.25rem;flex-wrap:wrap;}
    .search-bar input{border:1.5px solid var(--gray200);border-radius:999px;padding:.5rem 1rem;font-family:inherit;font-size:.88rem;flex:1;min-width:200px;outline:none;}
    .search-bar input:focus{border-color:var(--accent);}
  </style>
</head>
<body>

<nav class="eb-navbar">
  <a class="brand" href="eblotter_home.php"><img src="../eBlotter/images/Barangay_logo_409.png" alt="Logo">Barangay 409</a>
  <?php if($u): ?>
  <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto;margin-right:3.5rem;font-size:.75rem;color:rgba(255,255,255,.6);">
    <?php
      $role=$u['role'];
      echo "<i class='{$rIcons[$role]}' style='color:{$rColors[$role]};margin-right:4px'></i>";
      echo htmlspecialchars($u['full_name']).' ('.ucfirst($role).')';
    ?>
    &nbsp;<a href="logout.php" style="color:rgba(255,255,255,.4);text-decoration:none;"><i class="fas fa-sign-out-alt"></i></a>
  </div>
  <?php endif; ?>
</nav>
<div class="hero-banner">
  <div class="inner">
    <h1>Update Record</h1>
    <p>Barangay 409 Case Management System — City of Manila, District IV</p>
    <div class="hero-actions">
      <a href="eblotter_home.php" class="ha-btn"><i class="fas fa-home"></i> Home</a>
      <a href="add_case.php"      class="ha-btn"><i class="fas fa-plus-circle"></i> Add Record</a>
      <a href="view_cases.php"    class="ha-btn"><i class="fas fa-list"></i> View Records</a>
    </div>
  </div>
</div>
<main class="eb-main">
  <form method="get" class="search-bar">
    <i class="fas fa-search" style="color:var(--gray400)"></i>
    <input type="search" name="q" placeholder="Search by name or ID…" value="<?= htmlspecialchars($q) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
  </form>
  <div class="eb-table-wrap">
    <table>
      <thead>
        <tr><th>ID</th><th>Complainant</th><th>Respondent</th><th>Status</th><th>Incident Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php if ($cases->num_rows === 0): ?>
          <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--gray400);">No records found.</td></tr>
        <?php else: while ($r = $cases->fetch_assoc()):
          $cls = status_chip($r['status']);
          $c   = fmt_name($r['complainant_last'],$r['complainant_first'],$r['complainant_middle']);
          $res = fmt_name($r['respondent_last'],$r['respondent_first'],$r['respondent_middle']);
          $dt  = ($r['when_incident'] && $r['when_incident'] !== '0000-00-00')
                 ? date('M d, Y', strtotime($r['when_incident'])) : '—';
        ?>
          <tr>
            <td style="font-weight:600;font-size:.82rem;"><?= htmlspecialchars($r['case_id']) ?></td>
            <td><?= htmlspecialchars($c) ?></td>
            <td><?= htmlspecialchars($res) ?></td>
            <td><span class="chip <?= $cls ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td><?= htmlspecialchars($dt) ?></td>
            <td>
              <a class="btn btn-primary btn-sm"
                 href="update_case.php?case_id=<?= urlencode($r['case_id']) ?>">
                <i class="fas fa-edit"></i> Update
              </a>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
<?php
    exit();
}

// PASSWORD GATE 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($case_id)) {
    $u = currentUser();
    $active_page = 'update';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Update Record - Authorization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="eblotter.css">
  <style>
    .gate-wrap{min-height:calc(100vh - 220px);display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
    .gate-card{background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(27,38,59,.15);padding:2.5rem 2rem;width:100%;max-width:420px;text-align:center;}
    .gate-icon{width:72px;height:72px;background:linear-gradient(135deg,#1b263b,#2563eb);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.75rem;color:#fff;}
    .gate-card h2{font-family:'DM Serif Display',serif;font-size:1.45rem;color:var(--navy);margin-bottom:.35rem;}
    .gate-card p{font-size:.84rem;color:var(--gray400);margin-bottom:1.5rem;line-height:1.6;}
    .gate-field{position:relative;margin-bottom:1rem;}
    .gate-field i{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--gray400);pointer-events:none;}
    .gate-field input{width:100%;border:1.5px solid var(--gray200);border-radius:10px;padding:.65rem .9rem .65rem 2.4rem;font-family:inherit;font-size:.93rem;outline:none;transition:border-color .2s,box-shadow .2s;}
    .gate-field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
    .gate-btn{width:100%;background:linear-gradient(135deg,#1b263b 0%,#2563eb 100%);color:#fff;border:none;border-radius:999px;padding:.75rem 1.5rem;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;transition:opacity .2s;}
    .gate-btn:hover{opacity:.88;}
    .gate-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:9px;padding:.6rem .9rem;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .gate-back{display:inline-flex;align-items:center;gap:.35rem;margin-top:1rem;font-size:.82rem;color:var(--gray400);text-decoration:none;}
    .gate-back:hover{color:var(--navy);}
    .role-tag{display:inline-flex;align-items:center;gap:.4rem;background:var(--gray100);border-radius:999px;padding:.3rem .75rem;font-size:.75rem;font-weight:600;color:var(--gray600);margin-bottom:1.25rem;}
  </style>
</head>
<body>

<nav class="eb-navbar">
  <a class="brand" href="eblotter_home.php"><img src="../eBlotter/images/Barangay_logo_409.png" alt="Logo">Barangay 409</a>
  <?php if($u): ?>
  <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto;margin-right:3.5rem;font-size:.75rem;color:rgba(255,255,255,.6);">
    <?php $role=$u['role'];
      echo "<i class='{$rIcons[$role]}' style='color:{$rColors[$role]};margin-right:4px'></i>";
      echo htmlspecialchars($u['full_name']).' ('.ucfirst($role).')';
    ?>
    &nbsp;<a href="logout.php" style="color:rgba(255,255,255,.4);text-decoration:none;"><i class="fas fa-sign-out-alt"></i></a>
  </div>
  <?php endif; ?>
</nav>
<div class="hero-banner">
  <div class="inner">
    <h1>Update Record</h1>
    <p>Barangay 409 Case Management System — City of Manila, District IV</p>
    <div class="hero-actions">
      <a href="eblotter_home.php" class="ha-btn"><i class="fas fa-home"></i> Home</a>
      <a href="add_case.php"      class="ha-btn"><i class="fas fa-plus-circle"></i> Add Record</a>
      <a href="view_cases.php"    class="ha-btn"><i class="fas fa-list"></i> View Records</a>
    </div>
  </div>
</div>
<div class="gate-wrap">
  <div class="gate-card">
    <div class="gate-icon"><i class="fas fa-lock"></i></div>
    <h2>Authorization Required</h2>
    <?php if($u): ?>
    <div class="role-tag">
      <i class="<?= $rIcons[$u['role']] ?>" style="color:<?= $rColors[$u['role']] ?>"></i>
      <?= htmlspecialchars($u['full_name']) ?> &mdash; <?= ucfirst($u['role']) ?>
    </div>
    <?php endif; ?>
    <p>
      <?php if(isSecretary()): ?>
        Secretary must enter the <strong>Chairperson's password</strong> to update records.
      <?php else: ?>
        Enter your password to access the Update Record module.
      <?php endif; ?>
    </p>
    <form method="post" action="update_case.php">
      <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id) ?>">
      <input type="hidden" name="action" value="gate">
      <?= csrfField() ?>
      <div class="gate-field">
        <i class="fas fa-key"></i>
        <input type="password" name="gate_pw"
               placeholder="<?= isSecretary() ? "Chairperson's password" : 'Your password' ?>"
               autocomplete="off" autofocus required>
      </div>
      <button type="submit" class="gate-btn">
        <i class="fas fa-unlock"></i> Unlock Update Module
      </button>
    </form>
    <a href="view_cases.php" class="gate-back">
      <i class="fas fa-arrow-left"></i> Back to Records
    </a>
  </div>
</div>
</body>
</html>
<?php
    exit();
}

// POST HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $case_id  = trim($_POST['case_id'] ?? '');
    $gatePw   = $_POST['gate_pw'] ?? '';
    $action   = $_POST['action']  ?? 'gate';

    if (empty($case_id)) { header('Location: update_case.php'); exit(); }

    $gateOk = verifyCurrentUserOrChairPassword($conn, $gatePw);

    if (!$gateOk) {
        $gateError = isSecretary()
            ? "Incorrect. Secretary must enter the Chairperson's password."
            : "Incorrect password.";
        $u = currentUser();
        $active_page = 'update';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Update Record - Authorization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="eblotter.css">
  <style>
    .gate-wrap{min-height:calc(100vh - 220px);display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
    .gate-card{background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(27,38,59,.15);padding:2.5rem 2rem;width:100%;max-width:420px;text-align:center;}
    .gate-icon{width:72px;height:72px;background:linear-gradient(135deg,#1b263b,#2563eb);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.75rem;color:#fff;}
    .gate-card h2{font-family:'DM Serif Display',serif;font-size:1.45rem;color:var(--navy);margin-bottom:.35rem;}
    .gate-card p{font-size:.84rem;color:var(--gray400);margin-bottom:1.5rem;line-height:1.6;}
    .gate-field{position:relative;margin-bottom:1rem;}
    .gate-field i{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--gray400);pointer-events:none;}
    .gate-field input{width:100%;border:1.5px solid var(--gray200);border-radius:10px;padding:.65rem .9rem .65rem 2.4rem;font-family:inherit;font-size:.93rem;outline:none;}
    .gate-field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
    .gate-btn{width:100%;background:linear-gradient(135deg,#1b263b 0%,#2563eb 100%);color:#fff;border:none;border-radius:999px;padding:.75rem 1.5rem;font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;}
    .gate-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:9px;padding:.6rem .9rem;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .gate-back{display:inline-flex;align-items:center;gap:.35rem;margin-top:1rem;font-size:.82rem;color:var(--gray400);text-decoration:none;}
  </style>
</head>
<body>

<nav class="eb-navbar">
  <a class="brand" href="eblotter_home.php"><img src="../eBlotter/images/Barangay_logo_409.png" alt="Logo">Barangay 409</a>
</nav>
<div class="hero-banner"><div class="inner"><h1>Update Record</h1></div></div>
<div class="gate-wrap">
  <div class="gate-card">
    <div class="gate-icon"><i class="fas fa-lock"></i></div>
    <h2>Authorization Required</h2>
    <div class="gate-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($gateError) ?></div>
    <form method="post" action="update_case.php">
      <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id) ?>">
      <input type="hidden" name="action" value="gate">
      <?= csrfField() ?>
      <div class="gate-field">
        <i class="fas fa-key"></i>
        <input type="password" name="gate_pw"
               placeholder="<?= isSecretary() ? "Chairperson's password" : 'Your password' ?>"
               autocomplete="off" autofocus required>
      </div>
      <button type="submit" class="gate-btn"><i class="fas fa-unlock"></i> Try Again</button>
    </form>
    <a href="view_cases.php" class="gate-back"><i class="fas fa-arrow-left"></i> Back to Records</a>
  </div>
</div>
</body>
</html>
<?php
        exit();
    }

    // Password OK — fetch case
    $stmt = $conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
    $stmt->bind_param('s', $case_id); $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
    if (!$case) { header('Location: update_case.php'); exit(); }

    if ($action !== 'gate' && $action !== 'save') {
        // Unknown action — redirect safely
        header('Location: update_case.php?case_id=' . urlencode($case_id));
        exit();
    }

    if ($action === 'save') {
        // Original fields
        $status = $_POST['status']              ?? 'Pending';
        $disp   = trim($_POST['disposition']    ?? '');
        $brief  = trim($_POST['brief_case']     ?? '');
        $when   = $_POST['when_incident']       ?? null;
        $where  = trim($_POST['where_incident'] ?? '');

        // Validate: incident date cannot be in the future
        if (!empty($when) && $when > date('Y-m-d')) {
            $errors[] = 'Incident date cannot be in the future.';
        }

        // Existing new fields
        $hearingDate = trim($_POST['hearing_date'] ?? '') ?: null;

        // Mediation outcome fields
        $mediationOutcome = trim($_POST['mediation_outcome'] ?? '') ?: null;
        $mediationReason  = trim($_POST['mediation_reason']  ?? '') ?: null;

        if (empty($errors)) {
        // Auto-resolve: if mediation is done and outcome is settled or referred to court - resolved
        $stmtMedCheck = $conn->prepare("SELECT mediation_done FROM blotter_cases WHERE case_id=? LIMIT 1");
        $stmtMedCheck->bind_param('s', $case_id);
        $stmtMedCheck->execute();
        $medCheckRow = $stmtMedCheck->get_result()->fetch_assoc();
        if (!empty($medCheckRow['mediation_done']) && in_array($mediationOutcome, ['Settled', 'Referred to Court'])) {
            $status = 'Resolved';
        }

        $uname  = currentUser()['full_name'];
        $now_dt = date('Y-m-d H:i:s');

        $stmt2 = $conn->prepare(
            "UPDATE blotter_cases
             SET status=?, disposition=?, brief_case=?, when_incident=?, where_incident=?,
                 hearing_date=?,
                 mediation_outcome=?, mediation_reason=?,
                 last_updated_by=?, last_updated_at=?
             WHERE case_id=?"
        );
        $stmt2->bind_param(
            'sssssssssss',
            $status, $disp, $brief, $when, $where,
            $hearingDate,
            $mediationOutcome, $mediationReason,
            $uname, $now_dt,
            $case_id
        );

        if ($stmt2->execute()) {
            $success = "Record updated successfully.";
            auditLog($conn, 'UPDATE_CASE', $case_id,
                     'Status: '.$status.', Type: '.$disp);
            // Fresh statement to re-fetch updated case data
            $stmtRefetch = $conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
            $stmtRefetch->bind_param('s', $case_id); $stmtRefetch->execute();
            $case = $stmtRefetch->get_result()->fetch_assoc();
        } else {
            $errors[] = "Error updating: " . $conn->error;
        }
        } // end if (empty($errors))
    }
}

// RENDER EDIT FORM 
$cFull = fmt_name($case['complainant_last'],$case['complainant_first'],$case['complainant_middle']);
$rFull = fmt_name($case['respondent_last'], $case['respondent_first'], $case['respondent_middle']);

// step
$summonsDone   = !empty($case['summons_done']);
$noticeDone    = !empty($case['notice_done']);
$mediationDone = !empty($case['mediation_done']);

$active_page = 'update';
$u = currentUser();

// active step 
if (!$summonsDone)
    $hint = '→ <strong>Next step:</strong> Issue Summons to the respondent.';
elseif (!$noticeDone)
    $hint = '→ <strong>Next step:</strong> Issue Notice of Hearing. Mediation Minutes will unlock after.';
elseif (!$mediationDone)
    $hint = '→ <strong>Active step:</strong> Conduct Mediation and record the minutes.';
elseif ($case['mediation_outcome'] === 'Settled')
    $hint = '✔ All steps completed.';
elseif ($case['mediation_outcome'] === 'Referred to Court')
    $hint = '⚖️ All steps completed.';
else
    $hint = '✔ All proceeding steps completed. Piliin ang Mediation Outcome (Settled o Referred to Court) para ma-resolve ang kaso.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Update Record - eBlotter</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="eblotter.css">
  <style>
    .case-meta-card{background:linear-gradient(135deg,var(--navy) 0%,#243447 100%);border-radius:var(--radius);padding:1.5rem;color:#fff;margin-bottom:1.5rem;}
    .case-meta-card h3{font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:.5rem;}
    .case-meta-card .meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;font-size:.84rem;color:rgba(255,255,255,.8);}
    .pw-reminder{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:9px;padding:.55rem 1rem;font-size:.82rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .pw-field-row{display:flex;gap:.75rem;align-items:flex-end;background:var(--gray50);border:1.5px solid var(--gray200);border-radius:10px;padding:.75rem 1rem;margin-bottom:1.1rem;}
    .pw-field-row label{font-size:.75rem;font-weight:700;color:var(--gray600);text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.3rem;}
    .pw-field-row input{border:1.5px solid var(--gray200);border-radius:8px;padding:.5rem .8rem;font-family:inherit;font-size:.9rem;outline:none;flex:1;}
    .pw-field-row input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.1);}

    .step-btn{display:inline-flex;align-items:center;gap:.45rem;border:none;border-radius:999px;padding:.52rem 1.15rem;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;text-decoration:none;transition:opacity .15s,transform .1s;}
    .step-btn:hover{opacity:.85;transform:scale(.98);}
    .step-btn.done{background:#e2e8f0;color:#64748b;cursor:default;opacity:.8;pointer-events:none;}
    .step-btn.active{background:#2563eb;color:#fff;}
    .step-btn.locked{background:#f1f5f9;color:#94a3b8;cursor:not-allowed;opacity:.6;pointer-events:none;}
    .step-btn .badge{font-size:.72rem;font-weight:600;background:rgba(255,255,255,.25);border-radius:999px;padding:.1rem .45rem;margin-left:.2rem;}
    .step-btn.done .badge{background:rgba(0,0,0,.08);color:#64748b;}
    .active-hint{background:#fef9c3;border:1.5px solid #fde047;border-radius:10px;padding:.6rem 1rem;font-size:.85rem;color:#713f12;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;}
    .section-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray400);margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem;}
    .form-section-title{font-size:1rem;color:var(--navy);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;}
    .form-divider{margin:1.25rem 0;border:none;border-top:1.5px solid var(--gray100);}
    /* Notice celebration modal */
    .notice-celebration .eb-modal{border-top:5px solid #2563eb;text-align:center;}
    .celebrate-icon{font-size:3rem;margin-bottom:.75rem;}
    .celebrate-steps{display:flex;flex-direction:column;gap:.5rem;text-align:left;margin:1rem 0;}
    .celebrate-step{display:flex;align-items:center;gap:.6rem;font-size:.9rem;padding:.5rem .75rem;border-radius:8px;background:#f0fdf4;color:#166534;}
    .celebrate-step.next{background:#eff6ff;color:#1e40af;font-weight:600;}
  </style>
</head>
<body>

<nav class="eb-navbar">
  <a class="brand" href="eblotter_home.php"><img src="../eBlotter/images/Barangay_logo_409.png" alt="Logo">Barangay 409</a>
  <?php if($u): ?>
  <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto;margin-right:3.5rem;font-size:.75rem;color:rgba(255,255,255,.6);">
    <?php
      $role=$u['role'];
      echo "<i class='{$rIcons[$role]}' style='color:{$rColors[$role]};margin-right:4px'></i>";
      echo htmlspecialchars($u['full_name']).' ('.ucfirst($role).')';
    ?>
    &nbsp;<a href="logout.php" style="color:rgba(255,255,255,.4);text-decoration:none;"><i class="fas fa-sign-out-alt"></i></a>
  </div>
  <?php endif; ?>
</nav>

<div class="hero-banner">
  <div class="inner">
    <h1>Update Record</h1>
    <p>Barangay 409 Case Management System — City of Manila, District IV</p>
    <div class="hero-actions">
      <a href="eblotter_home.php" class="ha-btn"><i class="fas fa-home"></i> Home</a>
      <a href="add_case.php"      class="ha-btn"><i class="fas fa-plus-circle"></i> Add Record</a>
      <a href="view_cases.php"    class="ha-btn"><i class="fas fa-list"></i> View Records</a>
    </div>
  </div>
</div>

<main class="eb-main">

  <?php if ($success): ?>
    <div class="eb-alert eb-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $e): ?>
    <div class="eb-alert eb-alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <div class="active-hint"><i class="fas fa-lightbulb"></i> <span><?= $hint ?></span></div>

  <div class="case-meta-card">
    <h3><?= htmlspecialchars($case['case_id']) ?></h3>
    <div class="meta-grid">
      <div><strong>Complainant:</strong> <?= htmlspecialchars($cFull) ?></div>
      <div><strong>Respondent:</strong>  <?= htmlspecialchars($rFull) ?></div>
      <div><strong>Incident Date:</strong>
        <?= (!empty($case['when_incident']) && $case['when_incident'] !== '0000-00-00')
            ? date('F d, Y', strtotime($case['when_incident'])) : '—' ?>
      </div>
      <div><strong>Current Status:</strong>
        <span class="chip <?= status_chip($case['status']) ?>"><?= htmlspecialchars($case['status']) ?></span>
      </div>
    </div>
  </div>

  <div class="eb-form-card">
    <h2><i class="fas fa-edit" style="color:var(--accent)"></i> Edit Record Details</h2>

    <div class="pw-reminder">
      <i class="fas fa-shield-alt"></i>
      Password is required every time you save changes.
    </div>

    <form method="post" action="update_case.php">
      <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id) ?>">
      <input type="hidden" name="action" value="save">
      <?= csrfField() ?>

      <div class="pw-field-row">
        <div style="flex:1;">
          <label><?= isSecretary() ? "Chairperson's Password" : "Your Password" ?> (required to save)</label>
          <input type="password" name="gate_pw"
                 placeholder="<?= isSecretary() ? "Enter Chairperson's password" : 'Enter your password' ?>"
                 autocomplete="off" required>
        </div>
      </div>

      <?php $autoResolved = $mediationDone && ($case['mediation_outcome'] === 'Settled'); ?>
      <div class="eb-row">
        <div class="eb-field">
          <label>Status</label>
          <select name="status">
            <option value="Pending"  <?= $case['status']==='Pending'  ? 'selected' : '' ?>>Pending</option>
            <option value="Ongoing"  <?= $case['status']==='Ongoing'  ? 'selected' : '' ?>>Ongoing</option>
            <option value="Resolved" <?= $case['status']==='Resolved' ? 'selected' : '' ?>>Resolved</option>
          </select>
        </div>
        <div class="eb-field">
          <label>Type</label>
          <select name="disposition">
            <option value="Complain" <?= $case['disposition']==='Complain' ? 'selected' : '' ?>>Complain</option>
            <option value="Record"   <?= $case['disposition']==='Record'   ? 'selected' : '' ?>>Record</option>
          </select>
        </div>
      </div>

      <div class="eb-row">
        <div class="eb-field">
          <label>Incident Date</label>
          <input type="date" name="when_incident" value="<?= htmlspecialchars($case['when_incident'] ?? '') ?>">
        </div>
        <div class="eb-field">
          <label>Location / Saan</label>
          <input type="text" name="where_incident" value="<?= htmlspecialchars($case['where_incident'] ?? '') ?>">
        </div>
      </div>

      <!--Description -->
      <div class="eb-field">
        <label>Description</label>
        <textarea name="brief_case" rows="6"><?= htmlspecialchars($case['brief_case'] ?? '') ?></textarea>
      </div>

      <!-- Hearing Schedule section -->
      <hr class="form-divider">
      <h3 class="form-section-title">
        <i class="fas fa-gavel" style="color:var(--accent)"></i> Hearing Schedule
      </h3>
      <div class="eb-row">
        <div class="eb-field">
          <label>Hearing Date</label>
          <input type="date" name="hearing_date"
                 value="<?= htmlspecialchars($case['hearing_date'] ?? '') ?>">
        </div>
      </div>

      <!-- Mediation Outcome section -->
      <hr class="form-divider">
      <h3 class="form-section-title">
        <i class="fas fa-handshake" style="color:var(--accent)"></i> Mediation Outcome
        <?php if ($mediationDone): ?>
          <span style="font-size:.72rem;font-weight:600;background:#dcfce7;color:#15803d;border-radius:999px;padding:.15rem .65rem;margin-left:.4rem;">
            <i class="fas fa-check"></i> Mediation Conducted
          </span>
        <?php else: ?>
          <span style="font-size:.72rem;color:var(--gray400);font-weight:400;margin-left:.4rem;">(fill after mediation is conducted)</span>
        <?php endif; ?>
      </h3>
      <?php if (!$mediationDone): ?>
      <div class="eb-alert eb-alert-info" style="font-size:.83rem;padding:.6rem .9rem;margin-bottom:.85rem;">
        <i class="fas fa-lock"></i>
        <span>Nakalocked ang Mediation Outcome. Mag-export muna ng <strong>Mediation Minutes</strong> para ma-unlock ito.</span>
      </div>
      <fieldset disabled style="border:none;padding:0;margin:0;opacity:.45;pointer-events:none;">
      <?php endif; ?>
      <div class="eb-row">
        <div class="eb-field">
          <label>Outcome / Resulta</label>
          <select name="mediation_outcome" id="mediationOutcomeSelect" onchange="toggleMediationReason(this.value)">
            <option value="">— Select outcome —</option>
            <option value="Settled"           <?= ($case['mediation_outcome']??'')==='Settled'           ? 'selected':'' ?>>✅ Settled — Nagkasundo</option>
            <option value="Referred to Court" <?= ($case['mediation_outcome']??'')==='Referred to Court' ? 'selected':'' ?>>⚖️ Referred to Court</option>
          </select>
        </div>
      </div>
      <div class="eb-field" id="mediationReasonWrap"
           style="<?= empty($case['mediation_outcome']) ? 'display:none' : '' ?>">
        <label>Reason / Details <span style="color:var(--gray400);font-weight:400;">(optional)</span></label>
        <textarea name="mediation_reason" rows="3"
                  placeholder="e.g. Magkasamang nagpirma ng kasunduan tungkol sa…"><?= htmlspecialchars($case['mediation_reason'] ?? '') ?></textarea>
      </div>
      <?php if (!$mediationDone): ?>
      </fieldset>
      <?php endif; ?>

      <!-- Save/Back buttons -->
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
        <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Save Changes</button>
        <a href="update_case.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back to List</a>
      </div>
    </form>

    <!-- workflow step buttons-->
    <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1.5px solid var(--gray100);">
      <div class="section-label"><i class="fas fa-sitemap"></i> Case Workflow Steps</div>
      <div style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:center;">

        <!-- Step 1: Summons -->
        <?php if ($summonsDone): ?>
          <span class="step-btn done">
            <i class="fas fa-gavel"></i> Summons <span class="badge"><i class="fas fa-check"></i> done</span>
          </span>
        <?php else: ?>
          <a href="summons.php?case_id=<?= urlencode($case_id) ?>" class="step-btn active">
            <i class="fas fa-gavel"></i> Summons <span class="badge">← Do This Now</span>
          </a>
        <?php endif; ?>

        <i class="fas fa-chevron-right" style="color:var(--gray300);font-size:.75rem;"></i>

        <!-- Step 2: Notice of Hearing -->
        <?php if ($noticeDone): ?>
          <span class="step-btn done">
            <i class="fas fa-bell"></i> Notice of Hearing <span class="badge"><i class="fas fa-check"></i> done</span>
          </span>
        <?php elseif ($summonsDone): ?>
          <a href="notice_hearing.php?case_id=<?= urlencode($case_id) ?>"
             class="step-btn active"
             onclick="sessionStorage.setItem('noticeExporting','<?= htmlspecialchars($case_id) ?>')">
            <i class="fas fa-bell"></i> Notice of Hearing <span class="badge">← Active</span>
          </a>
        <?php else: ?>
          <span class="step-btn locked" title="Complete Summons first">
            <i class="fas fa-lock" style="font-size:.72rem;"></i> Notice of Hearing
          </span>
        <?php endif; ?>

        <i class="fas fa-chevron-right" style="color:var(--gray300);font-size:.75rem;"></i>

        <!-- Step 3: Mediation Minutes -->
        <?php if ($mediationDone): ?>
          <span class="step-btn done">
            <i class="fas fa-file-alt"></i> Mediation Minutes <span class="badge"><i class="fas fa-check"></i> done</span>
          </span>
        <?php elseif ($noticeDone): ?>
          <a href="mediation_minutes.php?case_id=<?= urlencode($case_id) ?>" class="step-btn active">
            <i class="fas fa-file-alt"></i> Mediation Minutes <span class="badge">← Active</span>
          </a>
        <?php else: ?>
          <span class="step-btn locked" title="Complete Notice of Hearing first">
            <i class="fas fa-lock" style="font-size:.72rem;"></i> Mediation Minutes
          </span>
        <?php endif; ?>

        <!-- signer settings (chairperson /secretary only) -->
        <?php if (in_array(currentRole(),['chairperson','secretary'])): ?>
        <a href="signer_settings.php" class="step-btn"
           style="background:var(--gray200);color:var(--gray600);">
          <i class="fas fa-signature"></i> Signer Settings
        </a>
        <?php endif; ?>

      </div>
      <?php if (!$summonsDone): ?>
      <p style="font-size:.76rem;color:var(--gray400);margin-top:.6rem;">
        <i class="fas fa-info-circle"></i>
        Notice of Hearing unlocks after Summons is issued. Mediation Minutes unlock after Notice of Hearing is exported.
      </p>
      <?php elseif (!$noticeDone): ?>
      <p style="font-size:.76rem;color:var(--gray400);margin-top:.6rem;">
        <i class="fas fa-info-circle"></i>
        Mediation Minutes will unlock after Notice of Hearing is exported.
      </p>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- notice of hearing completion -->
<div class="eb-modal-overlay notice-celebration" id="noticeDoneModal">
  <div class="eb-modal" style="max-width:440px">
    <div class="celebrate-icon">🔔</div>
    <h3 style="text-align:center;font-size:1.3rem">Notice of Hearing Issued!</h3>
    <p style="text-align:center;color:var(--gray600);font-size:.9rem;margin-bottom:1rem">
      The Notice of Hearing for <strong><?= htmlspecialchars($case_id) ?></strong>
      has been printed and marked as done.
    </p>
    <div class="celebrate-steps">
      <div class="celebrate-step">
        <i class="fas fa-check-circle" style="color:#16a34a"></i>
        <span>Summons — <strong>Completed</strong></span>
      </div>
      <div class="celebrate-step">
        <i class="fas fa-check-circle" style="color:#16a34a"></i>
        <span>Notice of Hearing — <strong>Completed</strong></span>
      </div>
      <div class="celebrate-step next">
        <i class="fas fa-arrow-right"></i>
        <span><strong>Next:</strong> Conduct Mediation &amp; record the Minutes</span>
      </div>
    </div>
    <div style="display:flex;gap:.65rem;justify-content:center;margin-top:1rem">
      <a href="mediation_minutes.php?case_id=<?= urlencode($case_id) ?>"
         class="btn btn-accent" onclick="closeNoticeModal()">
        <i class="fas fa-file-alt"></i> Go to Mediation
      </a>
      <button class="btn btn-ghost" onclick="closeNoticeModal()">
        <i class="fas fa-times"></i> Close
      </button>
    </div>
  </div>
</div>

<!-- Mediation Minutes-->
<div class="eb-modal-overlay" id="mediationDoneModal">
  <div class="eb-modal" style="max-width:460px;border-top:5px solid #16a34a;text-align:center;">
    <div class="celebrate-icon">🤝</div>
    <h3 style="text-align:center;font-size:1.3rem;color:var(--navy)">Mediation Minutes Recorded!</h3>
    <p style="text-align:center;color:var(--gray600);font-size:.9rem;margin-bottom:1rem">
      The Mediation Minutes for <strong><?= htmlspecialchars($case_id) ?></strong>
      have been exported and the workflow is now complete.
    </p>
    <div class="celebrate-steps">
      <div class="celebrate-step">
        <i class="fas fa-check-circle" style="color:#16a34a"></i>
        <span>Summons — <strong>Completed</strong></span>
      </div>
      <div class="celebrate-step">
        <i class="fas fa-check-circle" style="color:#16a34a"></i>
        <span>Notice of Hearing — <strong>Completed</strong></span>
      </div>
      <div class="celebrate-step">
        <i class="fas fa-check-circle" style="color:#16a34a"></i>
        <span>Mediation Minutes — <strong>Completed</strong></span>
      </div>
      <div class="celebrate-step next" style="background:#fef9c3;color:#713f12;">
        <i class="fas fa-lightbulb"></i>
        <span><strong>Next:</strong> Record the mediation outcome below and mark case as <em>Resolved</em> if settled.</span>
      </div>
    </div>
    <div style="display:flex;gap:.65rem;justify-content:center;margin-top:1rem">
      <button class="btn btn-accent" onclick="closeMediationModal(); document.getElementById('mediationOutcomeSelect').focus();">
        <i class="fas fa-handshake"></i> Record Outcome
      </button>
      <button class="btn btn-ghost" onclick="closeMediationModal()">
        <i class="fas fa-times"></i> Close
      </button>
    </div>
  </div>
</div>

<script>
(function(){
  var caseId = '<?= addslashes($case_id) ?>';
  if (sessionStorage.getItem('noticeExporting') === caseId) {
    sessionStorage.removeItem('noticeExporting');
    <?php if ($noticeDone): ?> showNoticeModal(); <?php endif; ?>
  }
  if (sessionStorage.getItem('mediationExporting') === caseId) {
    sessionStorage.removeItem('mediationExporting');
    <?php if ($mediationDone): ?> showMediationModal(); <?php endif; ?>
  }
  var p = new URLSearchParams(window.location.search);
  if (p.get('notice_done') === '1') {
    <?php if ($noticeDone): ?> showNoticeModal(); <?php endif; ?>
  }
  // Redirect from export_mediation → show mediation done modal
  if (p.get('mediation_done') === '1') {
    <?php if ($mediationDone): ?> showMediationModal(); <?php endif; ?>
  }
})();
function showNoticeModal(){
  document.getElementById('noticeDoneModal').classList.add('open');
}
function closeNoticeModal(){
  document.getElementById('noticeDoneModal').classList.remove('open');
}
document.getElementById('noticeDoneModal').addEventListener('click',function(e){
  if(e.target===this) closeNoticeModal();
});
function showMediationModal(){
  document.getElementById('mediationDoneModal').classList.add('open');
}
function closeMediationModal(){
  document.getElementById('mediationDoneModal').classList.remove('open');
}
document.getElementById('mediationDoneModal').addEventListener('click',function(e){
  if(e.target===this) closeMediationModal();
});
function toggleMediationReason(val) {
  document.getElementById('mediationReasonWrap').style.display = val ? '' : 'none';
}
</script>

</body>
</html>