<?php
$_hb = defined('BRGY_NAME')     ? htmlspecialchars(BRGY_NAME)     : 'Barangay';
$_hc = defined('BRGY_CITY')     ? htmlspecialchars(BRGY_CITY)     : 'Manila';
$_hd = defined('BRGY_DISTRICT') ? htmlspecialchars(BRGY_DISTRICT) : '';

$_ht = $hero_title ?? 'eBlotter';
$_hs = $hero_subtitle ?? "{$_hb} Case Management System &mdash; {$_hc}, District {$_hd}";
$_ha = $hero_active ?? 'home';

$_hero_total = 0;
$_hero_pending = 0;
if (isset($conn)) {
    $_hero_total = (int)($conn->query("SELECT COUNT(*) AS c FROM blotter_cases")->fetch_assoc()['c'] ?? 0);
    $_hero_pending = (int)($conn->query("SELECT COUNT(*) AS c FROM blotter_cases WHERE status='Pending'")->fetch_assoc()['c'] ?? 0);
}
?>
<style>
  .eb-module-hero {
    min-height: 360px;
    background:
      linear-gradient(to right, rgba(15,23,42,.88), rgba(15,23,42,.54)),
      url('../images/Barangay_officials_410.png') center center/cover no-repeat;
    color: #fff;
    display: flex;
    align-items: center;
    padding: 3.4rem 3rem;
  }
  .eb-module-hero-inner { width: 100%; max-width: 1788px; margin: 0 auto; }
  .eb-module-hero h1 {
    font-family: 'Syne', sans-serif;
    font-size: clamp(2.25rem, 4.2vw, 3.7rem);
    font-weight: 800;
    line-height: 1;
    color: #fff;
    margin: 0 0 .45rem;
  }
  .eb-module-hero p { color: rgba(255,255,255,.72); font-size: 1rem; margin: 0; }
  .eb-module-main { max-width: 1788px; margin: 0 auto; padding: 1.55rem 1.5rem 0; }
  .eb-module-tabs {
    display: inline-flex;
    gap: 1.1rem;
    align-items: center;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 6px;
    margin-bottom: 1.9rem;
  }
  .eb-module-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 42px;
    padding: 0 1.55rem;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
  }
  .eb-module-tab:hover { color: #0f172a; background: #f8fafc; }
  .eb-module-tab.active { background: #0f172a; color: #fff; }
  .eb-module-pill {
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    color: #fff;
    font-size: .78rem;
    line-height: 1;
  }
  @media(max-width:700px) {
    .eb-module-hero { min-height: 270px; padding: 2.2rem 1.25rem; }
    .eb-module-main { padding: 1rem 1rem 0; }
    .eb-module-tabs { display: flex; }
    .eb-module-tab { flex: 1 1 100%; justify-content: center; padding: 0 1rem; }
  }
</style>

<section class="eb-module-hero">
  <div class="eb-module-hero-inner">
    <h1><i class="fas fa-shield-halved" style="opacity:.82;margin-right:.6rem"></i><?= $_ht ?></h1>
    <p><?= $_hs ?></p>
  </div>
</section>

<div class="eb-module-main">
  <nav class="eb-module-tabs" aria-label="eBlotter sections">
    <a href="eblotter_home.php" class="eb-module-tab <?= $_ha === 'home' ? 'active' : '' ?>">
      <i class="fas fa-house"></i> Home
    </a>
    <a href="dashboard.php" class="eb-module-tab <?= $_ha === 'dashboard' ? 'active' : '' ?>">
      <i class="fas fa-chart-line"></i> Dashboard
    </a>
    <a href="view_cases.php" class="eb-module-tab <?= $_ha === 'view' ? 'active' : '' ?>">
      <i class="fas fa-folder-open"></i> Records <span class="eb-module-pill"><?= $_hero_total ?></span>
    </a>
    <?php if (function_exists('canEdit') && canEdit()): ?>
    <a href="add_case.php" class="eb-module-tab <?= $_ha === 'add' ? 'active' : '' ?>">
      <i class="fas fa-plus"></i> New Record
      <?php if ($_hero_pending > 0): ?><span class="eb-module-pill"><?= $_hero_pending ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
  </nav>
</div>
