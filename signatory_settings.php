<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit(); }
include 'Residents_DB.php';
include 'role_helper.php';
include 'signatory_helper.php';

ensureSignatoryTable($conn);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roles = $_POST['role_key']  ?? [];
    $names = $_POST['full_name'] ?? [];
    $titles= $_POST['title']     ?? [];

    foreach ($roles as $i => $rk) {
        $rk_esc = $conn->real_escape_string(trim($rk));
        $nm_esc = $conn->real_escape_string(trim($names[$i] ?? ''));
        $ti_esc = $conn->real_escape_string(trim($titles[$i] ?? ''));
        $conn->query("UPDATE barangay_officials SET full_name='$nm_esc', title='$ti_esc' WHERE role_key='$rk_esc'");
    }

    // Sync captain name → eBlotter signer
    $captain_name = trim($names[array_search('captain', $roles)] ?? '');
    if ($captain_name) syncEBlotterSigner($conn, $captain_name);

    $success = 'Signatory settings saved. All documents will now use these names.';
}

$signatories = getSignatories($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Signatory Settings — ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<style>
main{padding:1.5rem;max-width:720px;margin:0 auto}
.sig-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem;margin-bottom:1rem}
.sig-card-header{display:flex;align-items:center;gap:12px;margin-bottom:1.25rem}
.sig-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#0f172a,#1e40af);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0}
.sig-role{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#0f172a}
.sig-role-sub{font-size:12px;color:#64748b;margin-top:1px}
.field-group{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.field-group{grid-template-columns:1fr}}
label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:5px}
input[type=text]{width:100%;padding:10px 13px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;color:#0f172a;outline:none;transition:border .2s;box-sizing:border-box}
input[type=text]:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.preview-box{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:10px 14px;margin-top:10px;font-size:11px;color:#64748b}
.preview-sig{border-top:1.5px solid #0f172a;padding-top:4px;margin-top:8px;display:inline-block;min-width:180px;text-align:center}
.preview-sig .pname{font-size:12px;font-weight:700;color:#0f172a;text-transform:uppercase}
.preview-sig .ptitle{font-size:10px;color:#475569}
.info-banner{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;margin-bottom:1.5rem;font-size:13px;color:#1e40af;display:flex;gap:10px;align-items:flex-start}
.topbar{position:sticky;top:0;z-index:200}
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

<main>
  <div style="margin-bottom:1.5rem">
    <h1 style="font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;color:#0f172a;margin:0 0 .25rem"><i class="fas fa-pen-nib" style="margin-right:8px;opacity:.7"></i>Signatory Settings</h1>
    <p style="font-size:13px;color:#64748b;margin:0">Names set here appear on all printed documents — RBI Report, eBlotter summons, notices, and mediation minutes.</p>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert" style="margin-bottom:1rem;background:#fff1f2;border:1px solid #fecdd3;color:#be123c;border-radius:10px;padding:12px 16px"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="info-banner">
    <i class="fas fa-circle-info" style="font-size:15px;flex-shrink:0;margin-top:1px"></i>
    <div>
      <strong>Punong Barangay</strong> is also automatically synced to the eBlotter document signer, so you only need to set it once here.
    </div>
  </div>

  <form method="POST">
    <?php foreach ($signatories as $rk => $sig): ?>
    <?php
      $icons = ['captain'=>'fa-star','secretary'=>'fa-file-signature'];
      $icon  = $icons[$rk] ?? 'fa-user-tie';
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

    <div style="display:flex;gap:10px;margin-top:.5rem">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Signatories</button>
      <a href="Home.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</main>

<script>
function updatePreview(rk) {
  const card = document.querySelector('input[name="role_key[]"][value="' + rk + '"]').closest('.sig-card');
  const name  = card.querySelectorAll('input[type=text]')[0].value;
  const title = card.querySelectorAll('input[type=text]')[1].value;
  document.getElementById('prev_name_'  + rk).textContent = name  ? name.toUpperCase()  : '———';
  document.getElementById('prev_title_' + rk).textContent = title ? title : '';
}
</script>
</body>
</html>
