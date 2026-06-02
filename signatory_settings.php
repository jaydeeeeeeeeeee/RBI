<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit(); }
include 'Residents_DB.php';
include 'role_helper.php';
include 'signatory_helper.php';

ensureSignatoryTable($conn);

$success = '';
$error   = '';
$active_tab = $_GET['tab'] ?? 'signatories';

// ── Handle POST: Signatory Settings ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_section'] ?? '') === 'signatories') {
    $roles = $_POST['role_key']  ?? [];
    $names = $_POST['full_name'] ?? [];
    $titles= $_POST['title']     ?? [];

    $captain_name = '';
    foreach ($roles as $i => $rk) {
        $rk_esc = $conn->real_escape_string(trim($rk));
        $nm_esc = $conn->real_escape_string(trim($names[$i] ?? ''));
        $ti_esc = $conn->real_escape_string(trim($titles[$i] ?? ''));
        $conn->query("UPDATE barangay_officials SET full_name='$nm_esc', title='$ti_esc' WHERE role_key='$rk_esc'");
        if (trim($rk) === 'captain') $captain_name = trim($names[$i] ?? '');
    }

    if ($captain_name) syncEBlotterSigner($conn, $captain_name);

    $success = 'Signatory settings saved. All documents will now use these names.';
    $active_tab = 'signatories';
}

// ── Handle POST: Account / General Settings (extend as needed) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_section'] ?? '') === 'account') {
    // TODO: handle account/password changes here
    $success = 'Account settings saved.';
    $active_tab = 'account';
}

$signatories = getSignatories($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Settings — ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<style>
/* ── Layout ──────────────────────────────────────────────── */
.settings-wrap {
  max-width: 900px;
  margin: 0 auto;
  padding: 1.5rem;
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1.5rem;
  align-items: start;
}
@media (max-width: 640px) {
  .settings-wrap { grid-template-columns: 1fr; gap: 1rem; }
  .settings-sidebar { display: flex; flex-wrap: wrap; gap: 6px; }
}

/* ── Sidebar ─────────────────────────────────────────────── */
.settings-sidebar {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 0.5rem;
  position: sticky;
  top: 72px;
}
.sidebar-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: #94a3b8;
  padding: 10px 12px 4px;
}
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 9px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  text-decoration: none;
  transition: background .15s, color .15s;
}
.sidebar-item:hover { background: #f1f5f9; color: #0f172a; }
.sidebar-item.active {
  background: linear-gradient(135deg, #0f172a, #1e40af);
  color: #fff;
}
.sidebar-item.active .sidebar-item-icon { color: #93c5fd; }
.sidebar-item-icon { font-size: 14px; width: 18px; text-align: center; }
.sidebar-divider { height: 1px; background: #e2e8f0; margin: 6px 0; }

/* ── Main panel ──────────────────────────────────────────── */
.settings-panel { min-width: 0; }
.panel-section { display: none; }
.panel-section.active { display: block; }

.panel-header {
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}
.panel-title {
  font-family: 'Syne', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 .25rem;
}
.panel-subtitle { font-size: 13px; color: #64748b; margin: 0; }

/* ── Signatory cards ─────────────────────────────────────── */
.sig-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.25rem 1.5rem;
  margin-bottom: .875rem;
  transition: border-color .2s, box-shadow .2s;
}
.sig-card:hover { border-color: #bfdbfe; box-shadow: 0 2px 12px rgba(59,130,246,.07); }
.sig-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 1rem; }
.sig-icon {
  width: 38px; height: 38px; border-radius: 10px;
  background: linear-gradient(135deg, #0f172a, #1e40af);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 15px; flex-shrink: 0;
}
.sig-role { font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 800; color: #0f172a; }
.sig-role-sub { font-size: 11px; color: #64748b; margin-top: 1px; }
.field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 480px) { .field-group { grid-template-columns: 1fr; } }

label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: 5px; }
input[type=text], input[type=email], input[type=password] {
  width: 100%; padding: 9px 12px;
  border: 1px solid #e2e8f0; border-radius: 8px;
  font-size: 13px; font-family: 'Inter', sans-serif; color: #0f172a;
  outline: none; transition: border .2s; box-sizing: border-box;
}
input[type=text]:focus,
input[type=email]:focus,
input[type=password]:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }

.preview-box {
  background: #f8fafc; border: 1px dashed #cbd5e1;
  border-radius: 8px; padding: 10px 14px; margin-top: 10px; font-size: 11px; color: #64748b;
}
.preview-sig {
  border-top: 1.5px solid #0f172a; padding-top: 4px; margin-top: 8px;
  display: inline-block; min-width: 180px; text-align: center;
}
.preview-sig .pname { font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase; }
.preview-sig .ptitle { font-size: 10px; color: #475569; }

.info-banner {
  background: #eff6ff; border: 1px solid #bfdbfe;
  border-radius: 10px; padding: 11px 14px; margin-bottom: 1.25rem;
  font-size: 13px; color: #1e40af; display: flex; gap: 10px; align-items: flex-start;
}

/* ── Account section placeholders ───────────────────────── */
.account-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: .875rem;
}
.account-card-title {
  font-size: 13px; font-weight: 700; color: #0f172a; margin: 0 0 1rem;
  padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;
}
.field-row { margin-bottom: 12px; }
.hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }

/* ── Form actions ────────────────────────────────────────── */
.form-actions { display: flex; gap: 10px; margin-top: .75rem; }

/* ── Topbar ──────────────────────────────────────────────── */
.topbar { position: sticky; top: 0; z-index: 200; }

/* ── Page heading ────────────────────────────────────────── */
.page-heading {
  max-width: 900px; margin: 0 auto;
  padding: 1.25rem 1.5rem .25rem;
}
.page-heading h1 {
  font-family: 'Syne', sans-serif; font-size: 1.1rem;
  font-weight: 800; color: #0f172a; margin: 0;
}
.page-heading p { font-size: 13px; color: #64748b; margin: 3px 0 0; }

.badge-new {
  display: inline-block; background: #dbeafe; color: #1d4ed8;
  font-size: 9px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; border-radius: 4px; padding: 1px 5px;
  vertical-align: middle; margin-left: 6px;
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div>
  </a>
  <div class="topbar-right" style="margin-left:auto">
    <a href="Home.php" class="btn btn-outline" style="font-size:13px"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</header>

<!-- Page heading -->
<div class="page-heading">
  <h1><i class="fas fa-gear" style="margin-right:8px;opacity:.6"></i>Admin Settings</h1>
  <p>Manage barangay officials, document signatories, and system preferences.</p>
</div>

<!-- Alerts -->
<?php if ($success): ?>
<div style="max-width:900px;margin:.75rem auto 0;padding:0 1.5rem">
  <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="max-width:900px;margin:.75rem auto 0;padding:0 1.5rem">
  <div class="alert" style="background:#fff1f2;border:1px solid #fecdd3;color:#be123c;border-radius:10px;padding:12px 16px"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
</div>
<?php endif; ?>

<!-- Settings layout -->
<div class="settings-wrap">

  <!-- ── Sidebar ── -->
  <nav class="settings-sidebar">
    <div class="sidebar-label">Document Setup</div>
    <a href="?tab=signatories" class="sidebar-item <?= $active_tab==='signatories'?'active':'' ?>">
      <span class="sidebar-item-icon"><i class="fas fa-pen-nib"></i></span> Signatories
    </a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-label">Administration</div>
    <a href="?tab=account" class="sidebar-item <?= $active_tab==='account'?'active':'' ?>">
      <span class="sidebar-item-icon"><i class="fas fa-user-shield"></i></span> Account & Password
    </a>
    <a href="?tab=barangay" class="sidebar-item <?= $active_tab==='barangay'?'active':'' ?>">
      <span class="sidebar-item-icon"><i class="fas fa-building"></i></span> Barangay Info
    </a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-label">System</div>
    <a href="?tab=notifications" class="sidebar-item <?= $active_tab==='notifications'?'active':'' ?>">
      <span class="sidebar-item-icon"><i class="fas fa-bell"></i></span> Notifications
    </a>
    <a href="?tab=audit" class="sidebar-item <?= $active_tab==='audit'?'active':'' ?>">
      <span class="sidebar-item-icon"><i class="fas fa-clock-rotate-left"></i></span> Audit Log
    </a>
  </nav>

  <!-- ── Panels ── -->
  <div class="settings-panel">

    <!-- ════════════ SIGNATORIES ════════════ -->
    <div class="panel-section <?= $active_tab==='signatories'?'active':'' ?>" id="tab-signatories">
      <div class="panel-header">
        <h2 class="panel-title"><i class="fas fa-pen-nib" style="margin-right:8px;opacity:.7"></i>Signatory Settings</h2>
        <p class="panel-subtitle">Names set here appear on all printed documents — RBI Report, eBlotter summons, notices, and mediation minutes.</p>
      </div>

      <div class="info-banner">
        <i class="fas fa-circle-info" style="font-size:14px;flex-shrink:0;margin-top:1px"></i>
        <div>
          <strong>Punong Barangay</strong> is automatically synced to the eBlotter document signer — set it once here and it propagates everywhere.
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="form_section" value="signatories">
        <?php
          $icons = ['captain'=>'fa-star','secretary'=>'fa-file-signature'];
          foreach ($signatories as $rk => $sig):
            $icon = $icons[$rk] ?? 'fa-user-tie';
        ?>
        <div class="sig-card">
          <div class="sig-card-header">
            <div class="sig-icon"><i class="fas <?= $icon ?>"></i></div>
            <div>
              <div class="sig-role"><?= htmlspecialchars($sig['role_label']) ?></div>
              <div class="sig-role-sub">Appears on official documents as the <?= htmlspecialchars($sig['role_label']) ?></div>
            </div>
          </div>
          <input type="hidden" name="role_key[]" value="<?= htmlspecialchars($rk) ?>">
          <div class="field-group">
            <div>
              <label>Full Name</label>
              <input type="text" name="full_name[]"
                     value="<?= htmlspecialchars($sig['full_name']) ?>"
                     placeholder="e.g. JUAN DELA CRUZ"
                     oninput="updatePreview('<?= $rk ?>')">
            </div>
            <div>
              <label>Title / Position</label>
              <input type="text" name="title[]"
                     value="<?= htmlspecialchars($sig['title']) ?>"
                     placeholder="e.g. Punong Barangay"
                     oninput="updatePreview('<?= $rk ?>')">
            </div>
          </div>
          <div class="preview-box">
            <div style="font-size:10px;color:#94a3b8;margin-bottom:6px">Preview — how it appears on printed documents:</div>
            <div class="preview-sig" id="preview_<?= $rk ?>">
              <div class="pname" id="prev_name_<?= $rk ?>"><?= $sig['full_name'] ? htmlspecialchars(strtoupper($sig['full_name'])) : '———' ?></div>
              <div class="ptitle" id="prev_title_<?= $rk ?>"><?= htmlspecialchars($sig['title']) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Signatories</button>
          <a href="Home.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div><!-- /signatories -->

    <!-- ════════════ ACCOUNT ════════════ -->
    <div class="panel-section <?= $active_tab==='account'?'active':'' ?>" id="tab-account">
      <div class="panel-header">
        <h2 class="panel-title"><i class="fas fa-user-shield" style="margin-right:8px;opacity:.7"></i>Account & Password</h2>
        <p class="panel-subtitle">Update the admin login credentials for this system.</p>
      </div>

      <form method="POST">
        <input type="hidden" name="form_section" value="account">

        <div class="account-card">
          <div class="account-card-title"><i class="fas fa-id-card" style="margin-right:6px;opacity:.6"></i>Login Credentials</div>
          <div class="field-row">
            <label>Admin Username</label>
            <input type="text" name="admin_username" placeholder="e.g. brgy410admin">
          </div>
          <div class="field-row">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="Enter current password">
          </div>
          <div class="field-group">
            <div>
              <label>New Password</label>
              <input type="password" name="new_password" placeholder="Leave blank to keep current">
            </div>
            <div>
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" placeholder="Repeat new password">
            </div>
          </div>
          <p class="hint"><i class="fas fa-lock" style="margin-right:4px"></i>Use at least 8 characters with a mix of letters and numbers.</p>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div><!-- /account -->

    <!-- ════════════ BARANGAY INFO ════════════ -->
    <div class="panel-section <?= $active_tab==='barangay'?'active':'' ?>" id="tab-barangay">
      <div class="panel-header">
        <h2 class="panel-title"><i class="fas fa-building" style="margin-right:8px;opacity:.7"></i>Barangay Information</h2>
        <p class="panel-subtitle">Details printed on official documents and certificates.</p>
      </div>

      <div class="account-card">
        <div class="account-card-title"><i class="fas fa-map-pin" style="margin-right:6px;opacity:.6"></i>Official Details</div>
        <div class="field-group" style="margin-bottom:12px">
          <div>
            <label>Barangay Name</label>
            <input type="text" placeholder="e.g. Barangay 410">
          </div>
          <div>
            <label>District / City</label>
            <input type="text" placeholder="e.g. Sampaloc, Manila">
          </div>
        </div>
        <div class="field-row">
          <label>Full Address</label>
          <input type="text" placeholder="e.g. 123 Street, Sampaloc, Manila, 1008">
        </div>
        <div class="field-group">
          <div>
            <label>Contact Number</label>
            <input type="text" placeholder="e.g. (02) 8123-4567">
          </div>
          <div>
            <label>Email Address</label>
            <input type="email" placeholder="e.g. brgy410@manila.gov.ph">
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary"><i class="fas fa-save"></i> Save Barangay Info</button>
      </div>
    </div><!-- /barangay -->

    <!-- ════════════ NOTIFICATIONS ════════════ -->
    <div class="panel-section <?= $active_tab==='notifications'?'active':'' ?>" id="tab-notifications">
      <div class="panel-header">
        <h2 class="panel-title"><i class="fas fa-bell" style="margin-right:8px;opacity:.7"></i>Notifications</h2>
        <p class="panel-subtitle">Configure system alerts and notification preferences.</p>
      </div>
      <div class="account-card">
        <div style="font-size:13px;color:#64748b;text-align:center;padding:2rem 0">
          <i class="fas fa-bell-slash" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
          Notification settings coming soon.
        </div>
      </div>
    </div><!-- /notifications -->

    <!-- ════════════ AUDIT LOG ════════════ -->
    <div class="panel-section <?= $active_tab==='audit'?'active':'' ?>" id="tab-audit">
      <div class="panel-header">
        <h2 class="panel-title"><i class="fas fa-clock-rotate-left" style="margin-right:8px;opacity:.7"></i>Audit Log</h2>
        <p class="panel-subtitle">A history of administrative changes made in this system.</p>
      </div>
      <div class="account-card">
        <div style="font-size:13px;color:#64748b;text-align:center;padding:2rem 0">
          <i class="fas fa-scroll" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
          Audit log viewer coming soon.
        </div>
      </div>
    </div><!-- /audit -->

  </div><!-- /settings-panel -->
</div><!-- /settings-wrap -->

<script>
/* Live preview updater for signatory cards */
function updatePreview(rk) {
  const card = document.querySelector('input[name="role_key[]"][value="' + rk + '"]').closest('.sig-card');
  const name  = card.querySelectorAll('input[type=text]')[0].value;
  const title = card.querySelectorAll('input[type=text]')[1].value;
  document.getElementById('prev_name_'  + rk).textContent = name  ? name.toUpperCase()  : '———';
  document.getElementById('prev_title_' + rk).textContent = title || '';
}
</script>
</body>
</html>