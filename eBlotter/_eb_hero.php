<?php
$_hb = defined('BRGY_NAME')     ? htmlspecialchars(BRGY_NAME)     : 'Barangay';
$_hc = defined('BRGY_CITY')     ? htmlspecialchars(BRGY_CITY)     : 'Manila';
$_hd = defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : '';

$_ht = isset($hero_title)    ? $hero_title    : 'eBlotter';
$_hs = isset($hero_subtitle) ? $hero_subtitle : "{$_hb} Case Management System &mdash; {$_hc}, District {$_hd}";
$_ha = isset($hero_active)   ? $hero_active   : 'home';

$_bg = "display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.1);backdrop-filter:blur(4px)";
$_ba = "display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid #3b82f6;color:#fff;background:#3b82f6";
?>
<div style="background:linear-gradient(to right,rgba(15,23,42,.85),rgba(15,23,42,.55)),url('../images/Barangay_officials_410.png') center center/cover no-repeat;padding:2.5rem 2rem">
  <div style="max-width:1100px;margin:0 auto">
    <h1 style="font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:#fff;margin:0 0 .25rem"><?= $_ht ?></h1>
    <p style="color:rgba(255,255,255,.65);font-size:.84rem;margin:0 0 1.25rem"><?= $_hs ?></p>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <?php if ($_ha !== 'home'): ?>
      <a href="eblotter_home.php" style="<?= $_bg ?>"><i class="fas fa-home"></i> Home</a>
      <?php endif; ?>
      <?php if (function_exists('canEdit') && canEdit()): ?>
      <a href="add_case.php" style="<?= $_ha==='add' ? $_ba : $_bg ?>"><i class="fas fa-plus"></i> Add Record</a>
      <?php endif; ?>
      <a href="view_cases.php" style="<?= $_ha==='view' ? $_ba : $_bg ?>"><i class="fas fa-list"></i> View Records</a>
    </div>
  </div>
</div>
