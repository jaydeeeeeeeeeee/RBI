<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']);

// Handle GET reset (chairperson only)
if (isset($_GET['reset']) && isChairperson()) {
    resetSignerToDefault($conn);
    unset($_SESSION['eb_signer_override']); // clean up old session key if present
    auditLog($conn, 'SIGNER_RESET', null, 'Signer name reset to default.');
    header('Location: signer_settings.php?cleared=1');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $newName = trim($_POST['signer_name'] ?? '');
    $chapw   = $_POST['chairperson_password'] ?? '';

    if (!$newName) {
        $error = 'Signer name cannot be empty.';
    } else {
        if (isChairperson()) {
            if (setSignerOverride($conn, $newName)) {
                auditLog($conn, 'SIGNER_UPDATED', null, 'Signer name changed to: ' . strtoupper($newName));
                $success = 'Document signer name updated successfully.';
            } else {
                $error = 'Failed to save signer name. Please try again.';
            }
        } elseif (isSecretary()) {
            if (!$chapw) {
                $error = 'Please enter the Chairperson\'s password to confirm this change.';
            } elseif (setSignerOverride($conn, $newName, $chapw)) {
                auditLog($conn, 'SIGNER_UPDATED', null, 'Signer name changed by secretary to: ' . strtoupper($newName));
                $success = 'Document signer name updated successfully.';
            } else {
                $error = 'Incorrect Chairperson password. Change not saved.';
            }
        }
    }
}

$currentSigner = getSignerName($conn);
$active_page   = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signer Settings - eBlotter</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="eblotter.css">
<style>
</style>
</head>
<body>


<nav class="eb-navbar">
  <a class="brand" href="eblotter_home.php"><img src="../eBlotter/images/Barangay_logo_409.png" alt="Logo">Barangay 409</a>
  <?php $u = currentUser(); if ($u): ?>
  <div style="display:flex;align-items:center;gap:.5rem;margin-left:auto;margin-right:3.5rem;font-size:.75rem;color:rgba(255,255,255,.6);">
    <?php
      $rIcons  = ['chairperson' => 'fas fa-crown', 'secretary' => 'fas fa-user-tie', 'kagawad' => 'fas fa-user'];
      $rColors = ['chairperson' => '#fbbf24',      'secretary' => '#34d399',          'kagawad' => '#60a5fa'];
      $role = $u['role'];
      echo "<i class='{$rIcons[$role]}' style='color:{$rColors[$role]};margin-right:4px'></i>";
      echo htmlspecialchars($u['full_name']) . " (" . ucfirst($role) . ")";
    ?>
    &nbsp;<a href="logout.php" style="color:rgba(255,255,255,.4);text-decoration:none;"><i class="fas fa-sign-out-alt"></i></a>
  </div>
  <?php endif; ?>
</nav>

<div class="hero-banner">
  <div class="inner">
    <h1><i class="fas fa-pen-nib" style="font-size:1.8rem"></i> Document Signer Settings</h1>
    <p>Change who signs official barangay documents (Summons, Notice of Hearing, Mediation Minutes)</p>
    <div class="hero-actions">
      <a href="eblotter_home.php"   class="ha-btn"><i class="fas fa-home"></i> Home</a>
      <a href="add_case.php"        class="ha-btn"><i class="fas fa-plus-circle"></i> Add Record</a>
      <a href="view_cases.php"      class="ha-btn"><i class="fas fa-list"></i> View Records</a>
    </div>
  </div>
</div>

<div class="eb-main">
  <div class="eb-form-card" style="max-width:520px">
    <h2><i class="fas fa-signature"></i> Edit Signer Name</h2>

    <?php if ($error): ?>
    <div class="eb-alert eb-alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="eb-alert eb-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['cleared'])): ?>
    <div class="eb-alert eb-alert-success"><i class="fas fa-check-circle"></i> Signer name reset to default.</div>
    <?php endif; ?>

    <!-- Info box -->
    <div class="eb-alert eb-alert-info" style="margin-bottom:1.5rem">
      <i class="fas fa-info-circle"></i>
      <div>
        <strong>Current signer on all documents:</strong><br>
        <span style="font-size:1.05rem;font-weight:700;letter-spacing:.04em"><?= htmlspecialchars($currentSigner) ?></span>
        <div style="margin-top:.4rem;font-size:.78rem;color:#374151">
          This name appears as the signing official on Summons, Notice of Hearing, and Mediation Minutes PDFs.
        </div>
      </div>
    </div>

    <form method="post">
      <?= csrfField() ?>
      <div class="eb-field">
        <label>New Signer Name</label>
        <input type="text" name="signer_name"
               value="<?= htmlspecialchars($currentSigner) ?>"
               placeholder="e.g. MARIA SANTOS, Punong Barangay"
               maxlength="120" required>
        <div class="text-sm text-muted mt-1">Enter the full name as it should appear on documents.</div>
      </div>

      <?php if (isSecretary()): ?>
      <div class="eb-field">
        <label><i class="fas fa-lock"></i> Chairperson's Password <span style="color:#dc2626">*</span></label>
        <input type="password" name="chairperson_password"
               placeholder="Required — confirm with Chairperson's password" required>
        <div class="text-sm text-muted mt-1">Secretary must have the Chairperson's authorization to change the signer.</div>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Save Signer Name
        </button>
        <a href="view_cases.php" class="btn btn-ghost">
          <i class="fas fa-arrow-left"></i> Back
        </a>
        <?php if (isChairperson()): ?>
        <a href="signer_settings.php?reset=1" class="btn btn-ghost" style="color:#dc2626"
           onclick="return confirm('Reset signer to default name?')">
          <i class="fas fa-undo"></i> Reset to Default
        </a>
        <?php endif; ?>
      </div>
    </form>

    <div class="divider"></div>
    <div class="text-sm text-muted">
      <i class="fas fa-shield-alt"></i>
      <strong>Access control:</strong>
      Chairperson can change freely. Secretary needs Chairperson's password.
      Kagawad does not have access to this page.
    </div>
  </div>
</div>

</body>
</html>