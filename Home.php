<?php
session_start();
if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
include 'Residents_DB.php';
include 'role_helper.php';
$admin   = $_SESSION['admin'];
$hour    = (int)date('H');
$greeting= $hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');

$total_residents  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM residents WHERE is_hidden=0"))['t'];
$total_households = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(DISTINCT CONCAT(head_first_name,' ',head_last_name)) AS t FROM residents WHERE head_first_name!='' AND is_hidden=0"))['t'];
$total_pets       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM pets"))['t'];
$total_pwd        = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM residents WHERE pwd_status='Yes' AND is_hidden=0"))['t'];
$total_senior     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM residents WHERE birthdate IS NOT NULL AND birthdate!='' AND TIMESTAMPDIFF(YEAR,birthdate,CURDATE())>=60 AND is_hidden=0"))['t'];
$total_solo       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM residents WHERE solo_parent_status='Yes' AND is_hidden=0"))['t'];
$total_voters     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM residents WHERE voter='Yes' AND is_hidden=0"))['t'];
$total_employed   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM residents WHERE employment_status='Employed' AND is_hidden=0"))['t'];
// Labor force breakdown
$lf_labels=[]; $lf_counts=[];
$lf_res=mysqli_query($conn,"SELECT employer,COUNT(*) AS c FROM residents WHERE employment_status='Employed' AND employer IS NOT NULL AND employer!='' AND is_hidden=0 GROUP BY employer ORDER BY c DESC");
while($lf_res && $lr=mysqli_fetch_assoc($lf_res)){$lf_labels[]=$lr['employer'];$lf_counts[]=(int)$lr['c'];}
if(empty($lf_labels)){$lf_labels=['No Data'];$lf_counts=[0];}

function cd($conn,$col,$extra='AND is_hidden=0'){
  $l=[];$c=[];
  $r=mysqli_query($conn,"SELECT $col,COUNT(*) AS c FROM residents WHERE 1=1 $extra GROUP BY $col");
  while($row=mysqli_fetch_assoc($r)){$l[]=$row[$col];$c[]=(int)$row['c'];}
  return[$l,$c];
}
[$gender_l,$gender_c]=cd($conn,'gender');
[$marital_l,$marital_c]=cd($conn,'marital_status');
[$voter_l,$voter_c]=cd($conn,'voter');
[$house_l,$house_c]=cd($conn,'house_owner');
[$car_l,$car_c]=cd($conn,'has_car');
[$moto_l,$moto_c]=cd($conn,'has_motorcycle');
$emp_l=['Employed','Unemployed'];$emp_c=[0,0];
$r=mysqli_query($conn,"SELECT employment_status,COUNT(*) AS c FROM residents WHERE is_hidden=0 GROUP BY employment_status");
while($row=mysqli_fetch_assoc($r)){if(strtolower($row['employment_status'])==='employed')$emp_c[0]+=$row['c'];else $emp_c[1]+=$row['c'];}
$cit_l=['Filipino','Dual','Other'];$cit_c=[0,0,0];
$r=mysqli_query($conn,"SELECT citizenship,COUNT(*) AS c FROM residents WHERE is_hidden=0 GROUP BY citizenship");
while($row=mysqli_fetch_assoc($r)){$v=strtolower($row['citizenship']);if($v==='filipino')$cit_c[0]+=$row['c'];elseif($v==='dual citizenship')$cit_c[1]+=$row['c'];else $cit_c[2]+=$row['c'];}
$age_l=[];$age_c=[];
$r=mysqli_query($conn,"SELECT CASE WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE())<18 THEN 'Youth (0-17)' WHEN TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 18 AND 59 THEN 'Adult (18-59)' ELSE 'Senior (60+)' END AS ag,COUNT(*) AS c FROM residents WHERE birthdate IS NOT NULL AND is_hidden=0 GROUP BY ag");
while($row=mysqli_fetch_assoc($r)){$age_l[]=$row['ag'];$age_c[]=(int)$row['c'];}
$tc=['Dog'=>0,'Cat'=>0,'Others'=>0];
$r=mysqli_query($conn,"SELECT pet_type,COUNT(*) AS c FROM pets GROUP BY pet_type");
while($row=mysqli_fetch_assoc($r)){$t=strtolower(trim($row['pet_type']));if($t==='dog')$tc['Dog']+=$row['c'];elseif($t==='cat')$tc['Cat']+=$row['c'];else $tc['Others']+=$row['c'];}
$pet_l=array_keys($tc);$pet_c=array_values($tc);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Dashboard – ProjectRBI</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Brgy 410">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="assets/icons/icon.svg">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<style>
/* ── HERO ── */
.hero{background:linear-gradient(to right,rgba(15,23,42,.85),rgba(15,23,42,.55)),url('images/Barangay_officials_410.png') center center/cover no-repeat;padding:2.5rem 2rem 2rem;position:relative;overflow:hidden;display:flex;align-items:flex-end;justify-content:space-between;min-height:160px}
.hero-bg{position:absolute;inset:0;background-image:radial-gradient(circle at 20% 50%,rgba(59,130,246,.12) 0%,transparent 60%),radial-gradient(circle at 80% 20%,rgba(20,184,166,.08) 0%,transparent 50%);pointer-events:none}
.hero-grad{position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(to bottom,transparent,rgba(15,23,42,.4));pointer-events:none}
.hero-content{position:relative;z-index:1}
.hero-greet{font-size:13px;color:rgba(255,255,255,.5);margin-bottom:5px}
.hero-title{font-family:'Syne',sans-serif;font-size:clamp(1.6rem,4vw,2.4rem);font-weight:800;color:#fff;line-height:1.1;margin-bottom:8px}
.hero-sub{font-size:13px;color:rgba(255,255,255,.45)}
.hero-seal{position:relative;z-index:1;width:90px;height:90px;border-radius:50%;border:2px solid rgba(255,255,255,.15);overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
.seal-num{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:#3b82f6;line-height:1}
.seal-txt{font-size:9px;color:rgba(255,255,255,.5);text-align:center;text-transform:uppercase;letter-spacing:.08em;line-height:1.4}

/* ── TOPBAR ── */
.topbar{background:#0f172a;height:56px;display:flex;align-items:center;padding:0 1.5rem;justify-content:space-between;position:sticky;top:0;z-index:200;border-bottom:1px solid rgba(255,255,255,.06)}
.topbar-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.topbar-logo{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#14b8a6);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:12px;font-weight:800;color:#fff}
.topbar-name{font-size:14px;font-weight:600;color:#fff}
.topbar-sub{font-size:11px;color:rgba(255,255,255,.4);margin-top:1px}
.topbar-right{display:flex;align-items:center;gap:10px}
.topbar-clock{font-size:12px;color:rgba(255,255,255,.5);background:rgba(255,255,255,.06);padding:4px 12px;border-radius:20px;border:1px solid rgba(255,255,255,.08)}
.menu-toggle{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:all .2s}
.menu-toggle:hover{background:rgba(255,255,255,.15)}
.menu-toggle span{display:block;width:16px;height:2px;background:#fff;background:#fff;border-radius:2px}

/* ── SIDEBAR ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:998}
.sidebar-overlay.open{display:block}
.sidebar{position:fixed;top:0;right:-310px;width:290px;height:100vh;background:#0f172a;z-index:999;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.07);overflow-y:auto}
.sidebar.open{right:0}
.sidebar-head{padding:16px 14px 12px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.sidebar-head-brand{display:flex;align-items:center;gap:10px}
.sidebar-head-logo{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#3b82f6,#14b8a6);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:11px;font-weight:800;color:#fff}
.sidebar-head-title{font-size:13px;font-weight:600;color:#fff}
.sidebar-head-sub{font-size:11px;color:rgba(255,255,255,.4)}
.sidebar-close-btn{width:26px;height:26px;border-radius:6px;background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center}
.sidebar-close-btn:hover{background:rgba(255,255,255,.15)}

/* ── STAT CARDS IN SIDEBAR ── */
.sidebar-stats{padding:12px 10px 0;border-bottom:1px solid rgba(255,255,255,.07)}
.sidebar-stats-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px;padding:0 2px}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px}
.stat-mini{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.06);border-radius:9px;padding:8px 10px;display:flex;align-items:center;gap:8px}
.stat-mini-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.stat-mini-num{font-size:16px;font-weight:700;color:#fff;line-height:1}
.stat-mini-lbl{font-size:10px;color:rgba(255,255,255,.4);margin-top:1px;line-height:1.2}
.s-blue{background:rgba(59,130,246,.2);color:#60a5fa}
.s-teal{background:rgba(20,184,166,.2);color:#2dd4bf}
.s-amber{background:rgba(245,158,11,.2);color:#fbbf24}
.s-green{background:rgba(34,197,94,.2);color:#4ade80}
.s-purple{background:rgba(139,92,246,.2);color:#a78bfa}
.s-rose{background:rgba(244,63,94,.2);color:#fb7185}
.s-navy{background:rgba(15,23,42,.4);color:#94a3b8}
.s-cyan{background:rgba(6,182,212,.2);color:#67e8f9}

/* ── SIDEBAR NAV ── */
.sidebar-section{padding:12px 10px 6px}
.sidebar-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);padding:0 8px;margin-bottom:5px}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;color:rgba(255,255,255,.65);text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;margin-bottom:2px}
.sidebar-link:hover{background:rgba(255,255,255,.08);color:#fff}
.sidebar-link.active{background:rgba(59,130,246,.2);color:#93c5fd}
.sidebar-icon{width:28px;height:28px;border-radius:6px;background:rgba(255,255,255,.07);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.sidebar-footer{margin-top:auto;padding:10px;border-top:1px solid rgba(255,255,255,.07);flex-shrink:0}
.sidebar-settings-btn{width:100%;display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.sidebar-settings-btn:hover{background:rgba(59,130,246,.2)}

/* ── MAIN CONTENT ── */
main{padding:1.5rem;max-width:1200px;margin:0 auto}
.section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}

/* ── MODULE CARDS (dashboard main content) ── */
.modules-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:2rem}
.mod-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem;text-decoration:none;transition:all .2s;cursor:pointer;display:block;position:relative;overflow:hidden}
.mod-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.1);border-color:transparent}
.mod-card::before{content:'';position:absolute;inset:0;opacity:0;transition:opacity .2s}
.mod-card:hover::before{opacity:1}
.mod-card.blue::before{background:linear-gradient(135deg,rgba(59,130,246,.04),rgba(59,130,246,.01))}
.mod-card.teal::before{background:linear-gradient(135deg,rgba(20,184,166,.04),rgba(20,184,166,.01))}
.mod-card.purple::before{background:linear-gradient(135deg,rgba(139,92,246,.04),rgba(139,92,246,.01))}
.mod-card.amber::before{background:linear-gradient(135deg,rgba(245,158,11,.04),rgba(245,158,11,.01))}
.mod-card.rose::before{background:linear-gradient(135deg,rgba(244,63,94,.04),rgba(244,63,94,.01))}
.mod-card.green::before{background:linear-gradient(135deg,rgba(16,185,129,.04),rgba(16,185,129,.01))}
.mod-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:1rem;position:relative;z-index:1}
.mod-icon.blue{background:#eff6ff;color:#3b82f6}
.mod-icon.teal{background:#f0fdfa;color:#14b8a6}
.mod-icon.purple{background:#f5f3ff;color:#8b5cf6}
.mod-icon.amber{background:#fffbeb;color:#f59e0b}
.mod-icon.rose{background:#fff1f2;color:#f43f5e}
.mod-icon.green{background:#f0fdf4;color:#10b981}
.mod-title{font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px;position:relative;z-index:1}
.mod-desc{font-size:12px;color:#64748b;line-height:1.5;position:relative;z-index:1}
.mod-arrow{position:absolute;top:1.25rem;right:1.25rem;color:#e2e8f0;font-size:14px;transition:all .2s}
.mod-card:hover .mod-arrow{color:#94a3b8;transform:translateX(3px)}
.mod-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-top:8px;position:relative;z-index:1}

/* ── CHART SLIDESHOW ── */
.chart-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:1.5rem}
.chart-panel-head{padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.chart-panel-title{font-size:13px;font-weight:600;color:#0f172a}
.chart-panel-sub{font-size:11px;color:#64748b;margin-top:2px}
.chart-nav{display:flex;align-items:center;gap:8px}
.cnav-btn{width:28px;height:28px;border-radius:7px;border:1px solid #e2e8f0;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:11px;transition:all .2s}
.cnav-btn:hover{background:#f8fafc;border-color:#94a3b8}
.chart-wrap{padding:.75rem 1rem .5rem}
/* All slides stacked — same spot, fade in/out, nothing beside them to peek */
.chart-slides{position:relative;height:280px;}
@media(min-height:900px){.chart-slides{height:340px}}
@media(max-height:650px){.chart-slides{height:220px}}
.chart-slide{
  position:absolute;inset:0;
  opacity:0;pointer-events:none;
  transition:opacity .45s ease;
  display:flex;flex-direction:column;align-items:center;
}
.chart-slide.active{opacity:1;pointer-events:auto}

/* Strict chart box — overflow:hidden keeps canvas inside */
.chart-box{
  width:100%;height:100%;
  position:relative;overflow:hidden;
  border-radius:10px;
}
.chart-box.narrow{max-width:400px;margin:0 auto}
.chart-box canvas{position:absolute;inset:0;width:100%!important;height:100%!important}
.cdots{display:flex;gap:5px;justify-content:center;padding:.5rem 0}
.cdot{width:6px;height:6px;border-radius:50%;background:#e2e8f0;cursor:pointer;transition:all .3s}
.cdot.active{width:18px;border-radius:3px;background:#3b82f6}
.auto-badge{font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:4px;background:#f8fafc;padding:3px 8px;border-radius:20px;border:1px solid #f1f5f9}

/* ── SETTINGS BUTTON CSS ── */
/* pw-eye inherits from main.css */

@media(max-width:1100px){.modules-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.hero-seal{display:none}.modules-grid{grid-template-columns:1fr 1fr}}
@media(max-width:400px){.modules-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <a href="Home.php" class="topbar-brand">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div><div class="topbar-sub">Census Dashboard</div></div>
  </a>
  <div class="topbar-right">
    <div style="display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;border:1px solid;
  <?php if($is_captain): ?>background:rgba(245,158,11,.15);color:#fbbf24;border-color:rgba(245,158,11,.3);<?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);color:#93c5fd;border-color:rgba(59,130,246,.3);<?php else: ?>background:rgba(148,163,184,.15);color:#94a3b8;border-color:rgba(148,163,184,.3);<?php endif; ?>">
  <i class="fas <?=$rbadge['icon']?>"></i> <?=$rbadge['label']?>
</div>
<div class="topbar-clock" id="clock"></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div>
    </div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>

  <!-- STATS IN SIDEBAR -->
  <div class="sidebar-stats">
    <div class="sidebar-stats-label">Census Summary</div>
    <div class="stat-grid">
      <div class="stat-mini">
        <div class="stat-mini-icon s-blue"><i class="fas fa-users"></i></div>
        <div><div class="stat-mini-num"><?=$total_residents?></div><div class="stat-mini-lbl">Residents</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-teal"><i class="fas fa-house"></i></div>
        <div><div class="stat-mini-num"><?=$total_households?></div><div class="stat-mini-lbl">Households</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-navy"><i class="fas fa-person-booth"></i></div>
        <div><div class="stat-mini-num"><?=$total_voters?></div><div class="stat-mini-lbl">Voters</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-green"><i class="fas fa-briefcase"></i></div>
        <div><div class="stat-mini-num"><?=$total_employed?></div><div class="stat-mini-lbl">Employed</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-amber"><i class="fas fa-paw"></i></div>
        <div><div class="stat-mini-num"><?=$total_pets?></div><div class="stat-mini-lbl">Pets</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-purple"><i class="fas fa-wheelchair"></i></div>
        <div><div class="stat-mini-num"><?=$total_pwd?></div><div class="stat-mini-lbl">PWD</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-rose"><i class="fas fa-person-cane"></i></div>
        <div><div class="stat-mini-num"><?=$total_senior?></div><div class="stat-mini-lbl">Senior</div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon s-cyan"><i class="fas fa-child"></i></div>
        <div><div class="stat-mini-num"><?=$total_solo?></div><div class="stat-mini-lbl">Solo Parent</div></div>
      </div>
    </div>
  </div>

  <div style="padding:14px 12px 6px">
    <button onclick="openSettings()" class="sidebar-settings-btn">
      <i class="fas fa-gear"></i> Settings & More
      <i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i>
    </button>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if(!$is_guest): ?>
    <?php if($can_register): ?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register Resident</a><?php endif; ?>
    <?php if($is_captain): ?><a href="manage_accounts.php" class="sidebar-link"><span class="sidebar-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><i class="fas fa-users-gear"></i></span> <span>Manage Accounts</span></a><?php endif; ?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
    <?php endif; ?>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest): ?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <!-- empty — logout is in Settings drawer -->
  </div>
</aside>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div><div class="hero-grad"></div>
  <div class="hero-content">
    <div class="hero-greet"><?=$greeting?>, <?=htmlspecialchars($admin)?> 👋</div>
    <div class="hero-title">Barangay <span style="color:#3b82f6">410</span> Census<br>Inhabitants Dashboard</div>
    <p class="hero-sub">Centralized system for resident data, household records, and community management · Manila City</p>
  </div>
  <div class="hero-seal"><img src="images/brgy410_logo.png" style="width:90px;height:90px;object-fit:cover;border-radius:50%"></div>
</section>

<main>
  <?php if(isset($_GET['denied'])): ?>
  <div class="alert alert-error" style="margin-bottom:1.25rem;display:flex;align-items:center;gap:10px">
    <i class="fas fa-ban" style="font-size:18px"></i>
    <div><strong>Access Denied.</strong> Your account role (<?=ucfirst($user_role)?>) does not have permission to access that page.</div>
  </div>
  <?php endif; ?>

<?php if($is_guest): ?>
  <!-- ══ GUEST DASHBOARD ══════════════════════════════════════════════════════ -->
  <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:14px;padding:1.5rem 2rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:16px">
    <div style="width:48px;height:48px;background:#f59e0b;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="fas fa-eye" style="color:#fff;font-size:20px"></i>
    </div>
    <div>
      <div style="font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800;color:#92400e;margin-bottom:4px">Kagawad Guest Access</div>
      <div style="font-size:13px;color:#92400e;opacity:.85;line-height:1.6">
        You are logged in as a <strong>Barangay Kagawad</strong>. You can view the census summary and the official RBI Report.<br>
        For full resident details or system access, please coordinate with the <strong>Barangay Secretary</strong>.
      </div>
    </div>
  </div>

  <!-- Census summary for guests -->
  <div class="section-label" style="margin-bottom:14px">Census Summary</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:2rem">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;text-align:center">
      <div style="font-size:2rem;font-weight:800;color:#3b82f6;font-family:'Syne',sans-serif"><?=$total_residents?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em">Residents</div>
    </div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;text-align:center">
      <div style="font-size:2rem;font-weight:800;color:#8b5cf6;font-family:'Syne',sans-serif"><?=$total_households?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em">Households</div>
    </div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;text-align:center">
      <div style="font-size:2rem;font-weight:800;color:#f59e0b;font-family:'Syne',sans-serif"><?=$total_senior?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em">Senior Citizens</div>
    </div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;text-align:center">
      <div style="font-size:2rem;font-weight:800;color:#22c55e;font-family:'Syne',sans-serif"><?=$total_voters?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em">Registered Voters</div>
    </div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;text-align:center">
      <div style="font-size:2rem;font-weight:800;color:#ef4444;font-family:'Syne',sans-serif"><?=$total_pwd?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em">PWD</div>
    </div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;text-align:center">
      <div style="font-size:2rem;font-weight:800;color:#14b8a6;font-family:'Syne',sans-serif"><?=$total_employed?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em">Employed</div>
    </div>
  </div>

  <!-- RBI Report CTA for guests -->
  <div class="section-label" style="margin-bottom:14px">Available Reports</div>
  <div style="max-width:480px">
    <a href="RBI.php" class="mod-card blue" style="display:flex;align-items:center;gap:16px;padding:1.25rem 1.5rem;text-decoration:none">
      <div class="mod-icon blue" style="flex-shrink:0"><i class="fas fa-clipboard-list"></i></div>
      <div>
        <div class="mod-title" style="margin-bottom:4px">RBI Report</div>
        <div class="mod-desc">View the official Register of Barangay Inhabitants — census data, age brackets, sectors, and civil status summary.</div>
      </div>
      <i class="fas fa-chevron-right mod-arrow" style="position:relative;margin-left:auto;flex-shrink:0;color:#94a3b8"></i>
    </a>
  </div>

<?php else: ?>
  <!-- ══ FULL DASHBOARD (captain / secretary) ════════════════════════════════ -->
  <!-- BARANGAY MODULES -->
  <div class="section-label" style="margin-bottom:14px">Barangay Modules</div>
  <div class="modules-grid">

    <a href="RBI.php" class="mod-card blue">
      <i class="fas fa-chevron-right mod-arrow"></i>
      <div class="mod-icon blue"><i class="fas fa-clipboard-list"></i></div>
      <div class="mod-title">RBI Report</div>
      <div class="mod-desc">Register of Barangay Inhabitants. Official census report with age brackets, sectors, and civil status.</div>
      <span class="mod-badge" style="background:#eff6ff;color:#1d4ed8">Official Report</span>
    </a>

    <a href="data_tracking.php" class="mod-card teal">
      <i class="fas fa-chevron-right mod-arrow"></i>
      <div class="mod-icon teal"><i class="fas fa-database"></i></div>
      <div class="mod-title">Document Tracking System</div>
      <div class="mod-desc">Audit logs, document requests, certificate generation, and system activity records for full transparency.</div>
      <span class="mod-badge" style="background:#f0fdfa;color:#0f766e">Docs & Requests</span>
    </a>

    <a href="eBlotter/eblotter_home.php" class="mod-card purple">
      <i class="fas fa-chevron-right mod-arrow"></i>
      <div class="mod-icon purple"><i class="fas fa-shield-halved"></i></div>
      <div class="mod-title">E-Blotter</div>
      <div class="mod-desc">Electronic barangay blotter for recording incidents, complaints, and case tracking.</div>
      <span class="mod-badge" style="background:#f5f3ff;color:#6d28d9">Incident Records</span>
    </a>

    <a href="equipment.php" class="mod-card amber">
      <i class="fas fa-chevron-right mod-arrow"></i>
      <div class="mod-icon amber"><i class="fas fa-box-archive"></i></div>
      <div class="mod-title">Equipment Borrowing</div>
      <div class="mod-desc">Manage barangay equipment lending, borrowing requests, and return tracking.</div>
      <span class="mod-badge" style="background:#fffbeb;color:#92400e">Inventory</span>
    </a>

    <a href="senior_citizen.php" class="mod-card green">
      <i class="fas fa-chevron-right mod-arrow"></i>
      <div class="mod-icon green"><i class="fas fa-person-cane"></i></div>
      <div class="mod-title">Senior Citizens</div>
      <div class="mod-desc">Registry of senior citizens with age tracking, birthday reminders, and benefits management.</div>
      <span class="mod-badge" style="background:#f0fdf4;color:#15803d">Benefits & Records</span>
    </a>

  </div>

<?php endif; // end guest/non-guest main content ?>

  <!-- CHART SLIDESHOW (non-guest only) -->
  <?php if(!$is_guest): ?>
  <div class="section-label">Demographic Overview</div>
  <div class="chart-panel">
    <div class="chart-panel-head">
      <div>
        <div class="chart-panel-title" id="slideTitle">Gender & Marital Status</div>
        <div class="chart-panel-sub" id="slideSub">Resident breakdown</div>
      </div>
      <div class="chart-nav">
<button class="cnav-btn" id="prevBtn"><i class="fas fa-chevron-left"></i></button>
        <button class="cnav-btn" id="nextBtn"><i class="fas fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="chart-wrap">
      <div class="chart-slides" id="chartSlides">

        <!-- SLIDE 1: Gender — pie -->
        <div class="chart-slide active">
          <div class="chart-box narrow"><canvas id="genderChart"></canvas></div>
        </div>

        <!-- SLIDE 2: Age Groups — bar -->
        <div class="chart-slide">
          <div class="chart-box"><canvas id="ageChart"></canvas></div>
        </div>

        <!-- SLIDE 3: Employment — horizontal bar -->
        <div class="chart-slide">
          <div class="chart-box"><canvas id="empChart"></canvas></div>
        </div>

        <!-- SLIDE 4: Voter status — donut -->
        <div class="chart-slide">
          <div class="chart-box narrow"><canvas id="voterChart"></canvas></div>
        </div>

        <!-- SLIDE 5: Marital status — pie -->
        <div class="chart-slide">
          <div class="chart-box narrow"><canvas id="maritalChart"></canvas></div>
        </div>

        <!-- SLIDE 6: Special sectors — bar -->
        <div class="chart-slide">
          <div class="chart-box"><canvas id="sectorChart"></canvas></div>
        </div>

        <!-- SLIDE 7: Labor Force Type — horizontal bar -->
        <div class="chart-slide">
          <div class="chart-box"><canvas id="laborChart"></canvas></div>
        </div>

        <!-- SLIDE 8: Pets — pie -->
        <div class="chart-slide">
          <div class="chart-box narrow"><canvas id="petChart"></canvas></div>
        </div>

        <!-- SLIDE 9: House Ownership — pie -->
        <div class="chart-slide">
          <div class="chart-box narrow"><canvas id="houseChart"></canvas></div>
        </div>

        <!-- SLIDE 10: Car Ownership — donut -->
        <div class="chart-slide">
          <div class="chart-box narrow"><canvas id="carChart"></canvas></div>
        </div>

        <!-- SLIDE 11: Motorcycle Ownership — donut -->
        <div class="chart-slide">
          <div class="chart-box narrow"><canvas id="motoChart"></canvas></div>
        </div>

      </div>
    </div>
    <div class="cdots" id="dots"></div>
  </div>
  <?php endif; // end !$is_guest chart section ?>


</main>
<footer style="background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem 2rem;letter-spacing:.02em">
  &copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City · All rights reserved.
</footer>

<!-- SETTINGS OVERLAY -->
<div id="settingsOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100" onclick="closeSettings()"></div>
<div id="settingsDrawer" style="position:fixed;top:0;right:-360px;width:340px;height:100vh;background:#0f172a;z-index:1101;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.08)">
  <div style="padding:20px 20px 14px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#3b82f6,#14b8a6);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-gear" style="color:#fff;font-size:13px"></i></div>
      <div><div style="font-family:Syne,sans-serif;font-size:14px;font-weight:800;color:#fff">Settings</div><div style="font-size:11px;color:rgba(255,255,255,.4)">ProjectRBI Barangay 410</div></div>
    </div>
    <button onclick="closeSettings()" style="width:28px;height:28px;background:rgba(255,255,255,.08);border:none;border-radius:7px;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-times"></i></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:16px">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Appearance</div>
    <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:10px"><div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-circle-half-stroke" style="color:#94a3b8;font-size:13px"></i></div><div><div style="font-size:13px;font-weight:600;color:#fff">Dark Mode</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Toggle dark/light theme</div></div></div>
      <button id="darkToggle" onclick="toggleDarkMode()" style="width:42px;height:24px;border-radius:12px;background:#475569;border:none;cursor:pointer;position:relative;transition:background .25s;flex-shrink:0"><span id="darkThumb" style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;transition:left .25s"></span></button>
    </div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Data</div>
    <form id="settingsImportForm" action="Import_excel.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div style="background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:8px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px"><div style="width:30px;height:30px;background:rgba(59,130,246,.15);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-upload" style="color:#60a5fa;font-size:13px"></i></div><div><div style="font-size:13px;font-weight:600;color:#fff">Import CSV</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Upload resident data</div></div></div>
        <input type="file" name="csv_file" accept=".csv" style="width:100%;font-size:12px;color:#94a3b8;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:7px;padding:6px 10px;margin-bottom:7px">
        <button type="submit" style="width:100%;padding:8px;background:rgba(59,130,246,.2);border:1px solid rgba(59,130,246,.35);border-radius:7px;color:#60a5fa;font-family:Inter,sans-serif;font-size:12px;font-weight:600;cursor:pointer"><i class="fas fa-upload"></i> Import</button>
      </div>
    </form>
    <div style="background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px"><div style="width:30px;height:30px;background:rgba(34,197,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-download" style="color:#4ade80;font-size:13px"></i></div><div><div style="font-size:13px;font-weight:600;color:#fff">Export Data</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Download residents as Excel</div></div></div>
      <a href="Export_excel.php" style="display:block;width:100%;padding:8px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);border-radius:7px;color:#4ade80;font-family:Inter,sans-serif;font-size:12px;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;box-sizing:border-box"><i class="fas fa-download"></i> Export</a>
    </div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Actions</div>
    <button onclick="window.print()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print Page</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print current view</div></div>
    </button>
  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div><div style="font-size:11px;color:rgba(244,63,94,.6)">End your session</div></div>
    </a>
  </div>
</div>

<script>
// Clock
function tc(){const n=new Date();document.getElementById('clock').textContent=n.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})+' '+n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});}tc();setInterval(tc,1000);
// Sidebar
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSidebar();closeSettings();}});
// Settings
function openSettings(){document.getElementById('settingsOverlay').style.display='block';document.getElementById('settingsDrawer').style.right='0';document.body.style.overflow='hidden';closeSidebar();}
function closeSettings(){document.getElementById('settingsOverlay').style.display='none';document.getElementById('settingsDrawer').style.right='-360px';document.body.style.overflow='';}
// Dark mode
let darkMode=localStorage.getItem('rbi_dark')==='1';
function applyDark(on){document.body.classList.toggle('dark-mode',on);const th=document.getElementById('darkThumb'),tg=document.getElementById('darkToggle');if(th)th.style.left=on?'21px':'3px';if(tg)tg.style.background=on?'#3b82f6':'#475569';}
applyDark(darkMode);
function toggleDarkMode(){darkMode=!darkMode;localStorage.setItem('rbi_dark',darkMode?'1':'0');applyDark(darkMode);}
// Password eye toggle
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('input[type="password"]').forEach(function(inp){
    if(inp.parentElement&&inp.parentElement.classList.contains('pw-wrap'))return;
    const wrap=document.createElement('div');wrap.className='pw-wrap';wrap.style.cssText='width:100%;position:relative';
    inp.parentNode.insertBefore(wrap,inp);wrap.appendChild(inp);
    const btn=document.createElement('button');btn.type='button';btn.className='pw-eye';btn.innerHTML='<i class="fas fa-eye"></i>';
    btn.addEventListener('click',function(){const show=inp.type==='password';inp.type=show?'text':'password';btn.innerHTML=show?'<i class="fas fa-eye-slash"></i>':'<i class="fas fa-eye"></i>';inp.focus();});
    wrap.appendChild(btn);
  });
});
// Chart slideshow
const stl=[
  ['Population by Gender','Overall gender distribution of all registered residents'],
  ['Residents by Age Group','Youth, Adult, and Senior breakdown'],
  ['Employment Status','Employed vs unemployed residents'],
  ['Voter Registration','Registered voters among residents'],
  ['Marital Status','Civil status breakdown'],
  ['Special Sectors','PWD, Senior Citizens, and Solo Parents'],
  ['Labor Force Type','Breakdown of employed residents by employer/sector'],
  ['Registered Pets','Dogs, Cats, and other pets in the barangay'],
  ['House Ownership','Whether residents own or rent their home'],
  ['Car Ownership','Households with cars in the barangay'],
  ['Motorcycle Ownership','Households with motorcycles in the barangay'],
];
let cs=0,autoTimer=null;
const slides=document.querySelectorAll('.chart-slide');
const de=document.getElementById('dots');
stl.forEach((_,i)=>{const d=document.createElement('div');d.className='cdot'+(i===0?' active':'');d.onclick=()=>{gs(i);resetAuto();};de.appendChild(d);});
function gs(n){
  slides[cs].classList.remove('active');
  cs=(n+stl.length)%stl.length;
  slides[cs].classList.add('active');
  document.querySelectorAll('.cdot').forEach((d,i)=>d.classList.toggle('active',i===cs));
  document.getElementById('slideTitle').textContent=stl[cs][0];
  document.getElementById('slideSub').textContent=stl[cs][1];
}
function startAuto(){autoTimer=setInterval(()=>gs(cs+1),4000);}
function resetAuto(){if(autoTimer)clearInterval(autoTimer);startAuto();}
document.getElementById('prevBtn').onclick=()=>{gs(cs-1);resetAuto();};
document.getElementById('nextBtn').onclick=()=>{gs(cs+1);resetAuto();};
startAuto();
// Charts
// Chart colors and shared config
const COLORS={blue:'#3b82f6',rose:'#f43f5e',amber:'#f59e0b',green:'#22c55e',purple:'#8b5cf6',teal:'#14b8a6',gray:'#94a3b8'};
Chart.register(ChartDataLabels);

// Pie chart with legend
function mkPie(id,labels,data,colors){
  new Chart(document.getElementById(id),{
    type:'pie',
    data:{labels,datasets:[{data,backgroundColor:colors,borderWidth:2,borderColor:'#fff'}]},
    options:{
      responsive:true,
      maintainAspectRatio:false,
      plugins:{
        legend:{display:true,position:'bottom',labels:{font:{size:11,family:'Inter'},padding:12,usePointStyle:true,pointStyleWidth:10}},
        datalabels:{formatter:(v,ctx)=>{const t=ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);return t&&v?((v/t)*100).toFixed(0)+'%':'';},color:'#fff',font:{weight:'bold',size:12}}
      },
      animation:{duration:700,animateRotate:true}
    },
    plugins:[ChartDataLabels]
  });
}

// Donut chart with center label
function mkDonut(id,labels,data,colors){
  new Chart(document.getElementById(id),{
    type:'doughnut',
    data:{labels,datasets:[{data,backgroundColor:colors,borderWidth:2,borderColor:'#fff'}]},
    options:{
      responsive:true,
      maintainAspectRatio:false,
      cutout:'65%',
      plugins:{
        legend:{display:true,position:'bottom',labels:{font:{size:11,family:'Inter'},padding:10,usePointStyle:true,pointStyleWidth:10}},
        datalabels:{formatter:(v,ctx)=>{const t=ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);return t&&v?((v/t)*100).toFixed(0)+'%':'';},color:'#fff',font:{weight:'bold',size:12}}
      },
      animation:{duration:700}
    },
    plugins:[ChartDataLabels]
  });
}

// Vertical bar chart
function mkBar(id,labels,data,colors){
  new Chart(document.getElementById(id),{
    type:'bar',
    data:{labels,datasets:[{
      data,backgroundColor:colors,borderRadius:8,borderSkipped:false,
      maxBarThickness:48,barPercentage:0.55,categoryPercentage:0.6
    }]},
    options:{
      responsive:true,
      maintainAspectRatio:false,
      plugins:{
        legend:{display:false},
        datalabels:{anchor:'end',align:'top',color:'#475569',font:{weight:'700',size:12,family:'Inter'}}
      },
      scales:{
        y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{font:{size:11}}},
        x:{grid:{display:false},ticks:{font:{size:11,family:'Inter'}}}
      },
      animation:{duration:700}
    },
    plugins:[ChartDataLabels]
  });
}

// Horizontal bar chart
function mkHBar(id,labels,data,colors){
  new Chart(document.getElementById(id),{
    type:'bar',
    data:{labels,datasets:[{
      data,backgroundColor:colors,borderRadius:6,borderSkipped:false,
      maxBarThickness:22,barPercentage:0.5,categoryPercentage:0.65
    }]},
    options:{
      indexAxis:'y',
      responsive:true,
      maintainAspectRatio:false,
      plugins:{
        legend:{display:false},
        datalabels:{anchor:'end',align:'right',color:'#475569',font:{weight:'700',size:12,family:'Inter'}}
      },
      scales:{
        x:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{font:{size:11}}},
        y:{grid:{display:false},ticks:{font:{size:11,family:'Inter'}}}
      },
      animation:{duration:700}
    },
    plugins:[ChartDataLabels]
  });
}

// RENDER CHARTS
// Slide 1: Gender — pie
mkPie('genderChart',<?=json_encode($gender_l)?>,<?=json_encode($gender_c)?>,[COLORS.blue,COLORS.rose,COLORS.amber]);

// Slide 2: Age groups — vertical bar
mkBar('ageChart',<?=json_encode($age_l)?>,<?=json_encode($age_c)?>,[COLORS.blue,COLORS.teal,COLORS.amber]);

// Slide 3: Employment — horizontal bar
mkHBar('empChart',<?=json_encode($emp_l)?>,<?=json_encode($emp_c)?>,[COLORS.green,COLORS.rose]);

// Slide 4: Voter — donut
mkDonut('voterChart',<?=json_encode($voter_l)?>,<?=json_encode($voter_c)?>,[COLORS.teal,COLORS.gray]);

// Slide 5: Marital status — pie
mkPie('maritalChart',<?=json_encode($marital_l)?>,<?=json_encode($marital_c)?>,[COLORS.blue,COLORS.rose,COLORS.amber,COLORS.purple,COLORS.teal]);

// Slide 6: Special sectors — bar
mkBar('sectorChart',
  ['PWD','Senior Citizen','Solo Parent'],
  [<?=$total_pwd?>,<?=$total_senior?>,<?=$total_solo?>],
  [COLORS.purple,COLORS.amber,COLORS.rose]
);
// Slide 7: Labor force type — horizontal bar
mkHBar('laborChart',
  <?=json_encode($lf_labels)?>,
  <?=json_encode($lf_counts)?>,
  [COLORS.blue,COLORS.green,COLORS.teal,COLORS.amber,COLORS.purple]
);

// Slide 8: Pets — pie (filter zeros so empty slices don't hide data)
(function(){
  const pl=<?=json_encode($pet_l)?>,pc=<?=json_encode($pet_c)?>;
  const fl=[],fc=[],colors=[COLORS.amber,COLORS.rose,COLORS.gray];const fc2=[];
  pl.forEach((l,i)=>{if(pc[i]>0){fl.push(l);fc.push(pc[i]);fc2.push(colors[i]);}});
  if(fl.length){mkPie('petChart',fl,fc,fc2);}
  else{const cv=document.getElementById('petChart');if(cv){const ctx=cv.getContext('2d');ctx.font='14px Inter';ctx.fillStyle='#94a3b8';ctx.textAlign='center';ctx.fillText('No pets registered yet',cv.width/2,cv.height/2||80);}}
})();

// Slide 9: House ownership — pie
(function(){
  const pl=<?=json_encode($house_l)?>,pc=<?=json_encode($house_c)?>;
  const palette=[COLORS.blue,COLORS.teal,COLORS.amber,COLORS.rose,COLORS.purple,COLORS.gray];
  const fl=[],fc=[],fc2=[];
  pl.forEach((l,i)=>{if(pc[i]>0){fl.push(l);fc.push(pc[i]);fc2.push(palette[i%palette.length]);}});
  if(fl.length)mkPie('houseChart',fl,fc,fc2);
})();

// Slide 10: Car ownership — donut
(function(){
  const pl=<?=json_encode($car_l)?>,pc=<?=json_encode($car_c)?>;
  const palette=[COLORS.blue,COLORS.rose,COLORS.gray];
  const fl=[],fc=[],fc2=[];
  pl.forEach((l,i)=>{if(pc[i]>0){fl.push(l);fc.push(pc[i]);fc2.push(palette[i%palette.length]);}});
  if(fl.length)mkDonut('carChart',fl,fc,fc2);
})();

// Slide 11: Motorcycle ownership — donut
(function(){
  const pl=<?=json_encode($moto_l)?>,pc=<?=json_encode($moto_c)?>;
  const palette=[COLORS.teal,COLORS.rose,COLORS.gray];
  const fl=[],fc=[],fc2=[];
  pl.forEach((l,i)=>{if(pc[i]>0){fl.push(l);fc.push(pc[i]);fc2.push(palette[i%palette.length]);}});
  if(fl.length)mkDonut('motoChart',fl,fc,fc2);
})();
</script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(console.error);
}
</script>
</body>
</html>







