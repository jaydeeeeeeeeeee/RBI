<?php
$_ebU       = currentUser();
$_ebRole    = $_ebU['role']      ?? 'kagawad';
$_ebName    = $_ebU['full_name'] ?? $_ebU['username'] ?? 'User';
$_ebBadge   = match($_ebRole) {
    'chairperson' => ['icon'=>'fa-crown',    'label'=>'Chairperson', 'color'=>'#fbbf24'],
    'secretary'   => ['icon'=>'fa-user-tie', 'label'=>'Secretary',   'color'=>'#93c5fd'],
    default       => ['icon'=>'fa-user',     'label'=>'Kagawad',     'color'=>'#94a3b8'],
};
$_ebActive  = $active_page ?? '';
$_ebIsGuest = ($_ebRole === 'kagawad');

// eBlotter stats for sidebar
$_sb_total    = (int)($conn->query("SELECT COUNT(*) AS t FROM blotter_cases")->fetch_assoc()['t'] ?? 0);
$_sb_pending  = (int)($conn->query("SELECT COUNT(*) AS t FROM blotter_cases WHERE status='Pending'")->fetch_assoc()['t'] ?? 0);
$_sb_ongoing  = (int)($conn->query("SELECT COUNT(*) AS t FROM blotter_cases WHERE status='Ongoing'")->fetch_assoc()['t'] ?? 0);
$_sb_resolved = (int)($conn->query("SELECT COUNT(*) AS t FROM blotter_cases WHERE status='Resolved'")->fetch_assoc()['t'] ?? 0);
$_sb_settled  = (int)($conn->query("SELECT COUNT(*) AS t FROM blotter_cases WHERE mediation_outcome='Settled'")->fetch_assoc()['t'] ?? 0);
$_sb_court    = (int)($conn->query("SELECT COUNT(*) AS t FROM blotter_cases WHERE mediation_outcome='Referred to Court'")->fetch_assoc()['t'] ?? 0);
?>
<style>
.eb-sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:998}
.eb-sidebar-overlay.open{display:block}
.eb-sidebar{position:fixed;top:0;right:-310px;width:290px;height:100vh;background:#0f172a;z-index:999;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.07);overflow-y:auto}
.eb-sidebar.open{right:0}
.eb-sb-head{padding:16px 14px 12px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.eb-sb-close{width:26px;height:26px;border-radius:6px;background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center}
.eb-sb-stats{padding:12px 10px 0;border-bottom:1px solid rgba(255,255,255,.07)}
.eb-sb-stats-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px;padding:0 2px}
.eb-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px}
.eb-stat-mini{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.06);border-radius:9px;padding:8px 10px;display:flex;align-items:center;gap:8px}
.eb-stat-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.eb-stat-num{font-size:16px;font-weight:700;color:#fff;line-height:1}
.eb-stat-lbl{font-size:10px;color:rgba(255,255,255,.4);margin-top:1px;line-height:1.2}
.s-blue{background:rgba(59,130,246,.2);color:#60a5fa}
.s-teal{background:rgba(20,184,166,.2);color:#2dd4bf}
.s-navy{background:rgba(15,23,42,.4);color:#94a3b8}
.s-green{background:rgba(34,197,94,.2);color:#4ade80}
.s-amber{background:rgba(245,158,11,.2);color:#fbbf24}
.s-purple{background:rgba(139,92,246,.2);color:#a78bfa}
.s-rose{background:rgba(244,63,94,.2);color:#fb7185}
.s-cyan{background:rgba(6,182,212,.2);color:#67e8f9}
.eb-sb-section{padding:12px 10px 6px}
.eb-sb-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);padding:0 8px;margin-bottom:5px}
.eb-sb-link{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;color:rgba(255,255,255,.65);text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;margin-bottom:2px}
.eb-sb-link:hover{background:rgba(255,255,255,.08);color:#fff}
.eb-sb-link.active{background:rgba(59,130,246,.2);color:#93c5fd}
.eb-sb-icon{width:28px;height:28px;border-radius:6px;background:rgba(255,255,255,.07);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.eb-sb-settings{width:100%;display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.eb-sb-settings:hover{background:rgba(59,130,246,.2)}
.eb-sb-footer{margin-top:auto;padding:10px;border-top:1px solid rgba(255,255,255,.07);flex-shrink:0}
</style>

<!-- Overlay -->
<div class="eb-sidebar-overlay" id="ebSidebarOverlay" onclick="ebCloseSidebar()"></div>

<!-- Sidebar -->
<aside class="eb-sidebar" id="ebSidebar">

  <!-- Head -->
  <div class="eb-sb-head">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div>
        <div style="font-size:13px;font-weight:600;color:#fff">ProjectRBI</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4)"><?= defined('BRGY_NAME') ? htmlspecialchars(BRGY_NAME) : 'Barangay' ?> &middot; Manila</div>
      </div>
    </div>
    <button class="eb-sb-close" onclick="ebCloseSidebar()"><i class="fas fa-times"></i></button>
  </div>

  <!-- eBlotter Stats -->
  <div class="eb-sb-stats">
    <div class="eb-sb-stats-lbl">eBlotter Summary</div>
    <div class="eb-stat-grid">
      <div class="eb-stat-mini"><div class="eb-stat-icon s-blue"><i class="fas fa-folder-open"></i></div><div><div class="eb-stat-num"><?= $_sb_total ?></div><div class="eb-stat-lbl">Total Records</div></div></div>
      <div class="eb-stat-mini"><div class="eb-stat-icon s-amber"><i class="fas fa-clock"></i></div><div><div class="eb-stat-num"><?= $_sb_pending ?></div><div class="eb-stat-lbl">Pending</div></div></div>
      <div class="eb-stat-mini"><div class="eb-stat-icon s-navy"><i class="fas fa-spinner"></i></div><div><div class="eb-stat-num"><?= $_sb_ongoing ?></div><div class="eb-stat-lbl">Ongoing</div></div></div>
      <div class="eb-stat-mini"><div class="eb-stat-icon s-green"><i class="fas fa-check-circle"></i></div><div><div class="eb-stat-num"><?= $_sb_resolved ?></div><div class="eb-stat-lbl">Resolved</div></div></div>
      <div class="eb-stat-mini"><div class="eb-stat-icon s-teal"><i class="fas fa-handshake"></i></div><div><div class="eb-stat-num"><?= $_sb_settled ?></div><div class="eb-stat-lbl">Settled</div></div></div>
      <div class="eb-stat-mini"><div class="eb-stat-icon s-rose"><i class="fas fa-balance-scale"></i></div><div><div class="eb-stat-num"><?= $_sb_court ?></div><div class="eb-stat-lbl">To Court</div></div></div>
    </div>
  </div>

  <!-- Settings -->
  <div style="padding:14px 12px 6px">
    <?php if (!$_ebIsGuest): ?>
    <a href="signer_settings.php" class="eb-sb-settings" style="text-decoration:none">
      <i class="fas fa-gear"></i> Signer Settings
      <i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i>
    </a>
    <?php endif; ?>
  </div>

  <!-- Main nav -->
  <div class="eb-sb-section">
    <div class="eb-sb-label">Main</div>
    <a href="../Home.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if (!$_ebIsGuest): ?>
    <a href="../Register.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-user-plus"></i></span> Register Resident</a>
    <?php if ($_ebRole === 'chairperson'): ?>
    <a href="../manage_accounts.php" class="eb-sb-link"><span class="eb-sb-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><i class="fas fa-users-gear"></i></span> Manage Accounts</a>
    <?php endif; ?>
    <a href="../Display_List.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-users"></i></span> Residents</a>
    <?php endif; ?>
  </div>

  <!-- Modules nav -->
  <div class="eb-sb-section">
    <div class="eb-sb-label">Modules</div>
    <a href="../RBI.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if (!$_ebIsGuest): ?>
    <a href="../data_tracking.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eblotter_home.php" class="eb-sb-link <?= $_ebActive==='home'?'active':'' ?>"><span class="eb-sb-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="../equipment.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="../senior_citizen.php" class="eb-sb-link"><span class="eb-sb-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif; ?>
  </div>

  <div class="eb-sb-footer"></div>
</aside>

<!-- Topbar -->
<header style="background:#1b263b;height:56px;display:flex;align-items:center;padding:0 1.5rem;justify-content:space-between;position:sticky;top:0;z-index:500;box-shadow:0 2px 12px rgba(0,0,0,.25);gap:1rem">
  <a href="../Home.php" style="display:flex;align-items:center;gap:.6rem;text-decoration:none;flex:1">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <span style="color:#fff;font-weight:700;font-size:1.05rem;font-family:'Inter',sans-serif">
      <?= defined('BRGY_NAME') ? htmlspecialchars(BRGY_NAME) : 'Barangay' ?>
    </span>
  </a>
  <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
    <span style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#fff;font-family:'Inter',sans-serif">
      <i class="fas <?= $_ebBadge['icon'] ?>" style="color:<?= $_ebBadge['color'] ?>;font-size:13px"></i>
      <?= htmlspecialchars($_ebName) ?>
      <span style="font-size:11px;font-weight:400;color:rgba(255,255,255,.5)">(<?= $_ebBadge['label'] ?>)</span>
    </span>
    <button onclick="ebOpenSidebar()" title="Menu"
      style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;padding:0;flex-shrink:0">
      <span style="display:block;width:16px;height:2px;border-radius:2px"></span>
      <span style="display:block;width:16px;height:2px;border-radius:2px"></span>
      <span style="display:block;width:16px;height:2px;border-radius:2px"></span>
    </button>
  </div>
</header>

<script>
function ebOpenSidebar(){document.getElementById('ebSidebar').classList.add('open');document.getElementById('ebSidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function ebCloseSidebar(){document.getElementById('ebSidebar').classList.remove('open');document.getElementById('ebSidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.addEventListener('keydown',function(e){if(e.key==='Escape')ebCloseSidebar();});
</script>

<?php if (!empty($page_title) && ($active_page ?? '') !== 'home' && empty($hero_mode)): ?>
<div style="background:#1b263b;padding:1rem 2rem;border-bottom:1px solid rgba(255,255,255,.06)">
  <div style="max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:12px">
    <div>
      <h1 style="color:#fff;font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:800;margin:0"><?= $page_title ?></h1>
      <?php if (!empty($page_subtitle)): ?>
      <p style="color:rgba(255,255,255,.4);font-size:11.5px;margin-top:3px"><?= $page_subtitle ?></p>
      <?php else: ?>
      <p style="color:rgba(255,255,255,.4);font-size:11.5px;margin-top:3px"><?= defined('BRGY_NAME') ? htmlspecialchars(BRGY_NAME) : 'Barangay' ?> &middot; eBlotter Case Management</p>
      <?php endif; ?>
    </div>
    <?php if (!empty($page_actions)): ?>
    <div style="display:flex;gap:8px;flex-shrink:0"><?= $page_actions ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
