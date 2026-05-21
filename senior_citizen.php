<?php
session_start();
if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
include 'role_helper.php';
include 'Residents_DB.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS senior_citizens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    last_name      VARCHAR(100) NOT NULL,
    first_name     VARCHAR(100) NOT NULL,
    middle_name    VARCHAR(100) DEFAULT NULL,
    gender         ENUM('Male','Female') DEFAULT NULL,
    birth_month    TINYINT NOT NULL,
    birth_day      TINYINT NOT NULL,
    birth_year     SMALLINT NOT NULL,
    address        VARCHAR(300) DEFAULT NULL,
    contact_number VARCHAR(30) DEFAULT NULL,
    status         ENUM('Active','Deceased') DEFAULT 'Active',
    added_by       VARCHAR(150) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = ''; $err = '';

// ── Handle POST actions ──────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && $can_edit){
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // Backfill: sync all existing residents aged 60+ into senior_citizens
    if($action === 'sync_from_residents' && $is_captain){
        $all = $conn->query("SELECT first_name,middle_name,last_name,gender,birthdate,perm_address,mobile FROM residents WHERE birthdate IS NOT NULL AND birthdate != '' AND (is_senior='Yes' OR TIMESTAMPDIFF(YEAR,birthdate,CURDATE())>=60)");
        $added = 0;
        while($r = $all->fetch_assoc()){
            $bd_obj = new DateTime($r['birthdate']);
            $sc_bm  = (int)$bd_obj->format('n');
            $sc_bd  = (int)$bd_obj->format('j');
            $sc_by  = (int)$bd_obj->format('Y');
            $sc_ln  = mysqli_real_escape_string($conn, $r['last_name']);
            $sc_fn  = mysqli_real_escape_string($conn, $r['first_name']);
            $sc_mn  = mysqli_real_escape_string($conn, $r['middle_name'] ?? '');
            $sc_gen = mysqli_real_escape_string($conn, $r['gender']      ?? '');
            $sc_addr= mysqli_real_escape_string($conn, $r['perm_address']?? '');
            $sc_con = mysqli_real_escape_string($conn, $r['mobile']      ?? '');
            $sc_who = mysqli_real_escape_string($conn, $user_fullname);
            $exists = $conn->query("SELECT id FROM senior_citizens WHERE last_name='$sc_ln' AND first_name='$sc_fn' AND birth_month=$sc_bm AND birth_day=$sc_bd AND birth_year=$sc_by LIMIT 1");
            if($exists && $exists->num_rows === 0){
                $conn->query("INSERT INTO senior_citizens (last_name,first_name,middle_name,gender,birth_month,birth_day,birth_year,address,contact_number,status,added_by)
                    VALUES ('$sc_ln','$sc_fn','$sc_mn','$sc_gen',$sc_bm,$sc_bd,$sc_by,'$sc_addr','$sc_con','Active','$sc_who')");
                $added++;
            }
        }
        $msg = "Sync complete — $added new senior citizen(s) imported from the residents list.";
    }

    if($action === 'edit'){
        $id  = (int)($_POST['sc_id'] ?? 0);
        $ln  = mysqli_real_escape_string($conn, trim($_POST['last_name']  ?? ''));
        $fn  = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
        $mn  = mysqli_real_escape_string($conn, trim($_POST['middle_name']?? ''));
        $gen = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
        $bm  = (int)($_POST['birth_month'] ?? 0);
        $bd  = (int)($_POST['birth_day']   ?? 0);
        $by  = (int)($_POST['birth_year']  ?? 0);
        $addr= mysqli_real_escape_string($conn, trim($_POST['address']        ?? ''));
        $con = mysqli_real_escape_string($conn, trim($_POST['contact_number'] ?? ''));
        $sta = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');

        if(!$id || !$ln || !$fn){$err="Missing required fields.";}
        else{
            $conn->query("UPDATE senior_citizens SET
                last_name='$ln',first_name='$fn',middle_name='$mn',gender='$gen',
                birth_month=$bm,birth_day=$bd,birth_year=$by,
                address='$addr',contact_number='$con',status='$sta'
                WHERE id=$id");
            $msg = "Record updated.";
        }
    }

    elseif($action === 'delete' && $is_captain){
        $id = (int)($_POST['sc_id'] ?? 0);
        if($id){ $conn->query("DELETE FROM senior_citizens WHERE id=$id"); $msg="Record deleted."; }
    }
}

// ── Stats ────────────────────────────────────────────────────────────────────
$total    = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens")->fetch_assoc()['c'];
$active   = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens WHERE status='Active'")->fetch_assoc()['c'];
$deceased = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens WHERE status='Deceased'")->fetch_assoc()['c'];
$cur_m    = (int)date('n');
$cur_d    = (int)date('j');
$bday_month = (int)$conn->query("SELECT COUNT(*) c FROM senior_citizens WHERE birth_month=$cur_m AND status='Active'")->fetch_assoc()['c'];

// ── Search / List ────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$sq = $search ? "WHERE (last_name LIKE '%".mysqli_real_escape_string($conn,$search)."%'
                  OR first_name LIKE '%".mysqli_real_escape_string($conn,$search)."%'
                  OR address LIKE '%".mysqli_real_escape_string($conn,$search)."%')" : "";
$list = $conn->query("SELECT * FROM senior_citizens $sq ORDER BY last_name, first_name");

// ── Birthday lists ───────────────────────────────────────────────────────────
$bday_today = []; $bday_this_month = [];
$br = $conn->query("SELECT last_name,first_name,birth_month,birth_day,birth_year FROM senior_citizens WHERE status='Active' ORDER BY birth_month,birth_day");
while($row = $br->fetch_assoc()){
    if($row['birth_month']==$cur_m && $row['birth_day']==$cur_d)
        $bday_today[] = $row;
    elseif($row['birth_month']==$cur_m)
        $bday_this_month[] = $row;
}

function sc_age($m,$d,$y){
    $today = new DateTime(); $bd = new DateTime("$y-$m-$d");
    return $today->diff($bd)->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Senior Citizens – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css"/>
<style>
.page-top{background:#0f172a;padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.page-top h1{color:#fff;font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800}
.page-top p{color:rgba(255,255,255,.45);font-size:12px;margin-top:2px}
main{padding:1.5rem;max-width:1200px;margin:0 auto}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.25rem}
@media(max-width:600px){.stat-row{grid-template-columns:1fr 1fr}}
.scard{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem;text-align:center}
.scard-num{font-size:22px;font-weight:700;line-height:1}
.scard-lbl{font-size:11px;color:#64748b;margin-top:3px}
.tabs{display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:1.25rem;gap:4px}
.tab{padding:8px 16px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s;background:none;border-top:none;border-left:none;border-right:none;font-family:'Inter',sans-serif}
.tab.active{color:#10b981;border-bottom-color:#10b981}
.tab-content{display:none}.tab-content.active{display:block}

/* Search bar */
.search-row{display:flex;gap:10px;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
.search-row input{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:Inter,sans-serif;outline:none}
.search-row input:focus{border-color:#10b981}

/* Table */
.sc-table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:860px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
thead th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1.5px solid #e2e8f0}
tbody td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#1e293b;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:#f8fafc}
tbody tr.birthday-today{background:#f0fdf4}

/* Badges */
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700}
.badge-alive{background:#dcfce7;color:#15803d}
.badge-deceased{background:#fee2e2;color:#dc2626}
.badge-bday{background:#fef9c3;color:#a16207;margin-left:6px}
.badge-today{background:#fde68a;color:#92400e;margin-left:6px}
.age-pill{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#eff6ff;color:#1d4ed8}

/* Birthday tab */
.bday-section{margin-bottom:1.5rem}
.bday-section-title{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
.bday-card{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:8px}
.bday-card.today{border-color:#fbbf24;background:#fffbeb}
.bday-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.bday-avatar.today-av{background:#fef3c7}
.bday-avatar.month-av{background:#f0fdf4}
.bday-name{font-size:13px;font-weight:600;color:#0f172a}
.bday-info{font-size:11px;color:#64748b;margin-top:2px}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;padding:2rem;width:100%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-box h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:1.25rem}
.fg{margin-bottom:12px}
.fg label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px}
.fg input,.fg select,.fg textarea{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#10b981}
.fgrow{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.fgrow2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem;margin-top:2rem}
.empty-state{text-align:center;padding:3rem;color:#94a3b8}
.empty-state i{font-size:48px;opacity:.2;display:block;margin-bottom:12px}
</style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div class="topbar-logo">410</div>
    <div><div class="topbar-name">Barangay 410</div><div class="topbar-sub">Senior Citizens</div></div>
  </a>
  <div style="display:flex;align-items:center;border-left:1px solid rgba(255,255,255,.12);padding-left:14px;min-width:0">
    <span style="font-size:13px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;white-space:nowrap"><i class="fas fa-person-cane" style="opacity:.8;margin-right:5px"></i> Senior Citizens</span>
  </div>
  <div class="topbar-right" style="margin-left:auto">
    <?php if($is_captain):?>
    <button class="btn btn-primary" style="font-size:12px;padding:6px 12px" onclick="document.getElementById('syncModal').classList.add('open')"><i class="fas fa-rotate"></i> Sync Residents</button>
    <?php endif;?>
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain):?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);<?php elseif($is_secretary):?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);<?php else:?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif;?>"><i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div class="sidebar-head-logo">410</div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div>
    </div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <div style="padding:14px 12px 6px">
    <button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer">
      <i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i>
    </button>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register):?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest):?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="../eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif;?>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<main style="padding-top:1.75rem">
  <?php if($msg):?><div class="alert alert-success" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?=$msg?></div><?php endif;?>
  <?php if($err):?><div class="alert alert-error" style="margin-bottom:1rem"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($err)?></div><?php endif;?>

  <!-- Stats -->
  <div class="stat-row">
    <div class="scard"><div class="scard-num" style="color:#0f172a"><?=$total?></div><div class="scard-lbl">Total Registered</div></div>
    <div class="scard"><div class="scard-num" style="color:#10b981"><?=$active?></div><div class="scard-lbl">Active / Alive</div></div>
    <div class="scard"><div class="scard-num" style="color:#f43f5e"><?=$deceased?></div><div class="scard-lbl">Deceased</div></div>
    <div class="scard">
      <div class="scard-num" style="color:#f59e0b"><?=$bday_month?></div>
      <div class="scard-lbl">Birthdays This Month</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('masterlist',this)"><i class="fas fa-list"></i> Masterlist</button>
    <button class="tab" onclick="switchTab('birthdays',this)">
      <i class="fas fa-cake-candles"></i> Birthday Reminders
      <?php if(count($bday_today)>0):?>
        <span style="background:#f43f5e;color:#fff;border-radius:20px;font-size:10px;padding:1px 7px;margin-left:5px"><?=count($bday_today)?> today</span>
      <?php endif;?>
    </button>
  </div>

  <!-- MASTERLIST TAB -->
  <div id="tab-masterlist" class="tab-content active">

    <form method="GET" class="search-row">
      <input type="text" name="search" placeholder="Search by name or address..." value="<?=htmlspecialchars($search)?>">
      <button type="submit" class="btn btn-primary" style="white-space:nowrap"><i class="fas fa-search"></i> Search</button>
      <?php if($search):?><a href="senior_citizen.php" class="btn btn-outline" style="white-space:nowrap"><i class="fas fa-times"></i> Clear</a><?php endif;?>
    </form>

    <?php if($list && $list->num_rows > 0): ?>
    <div class="sc-table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Age</th>
            <th>Birthdate</th>
            <th>Gender</th>
            <th>Address</th>
            <th>Contact</th>
            <th>Status</th>
            <?php if($can_edit):?><th>Actions</th><?php endif;?>
          </tr>
        </thead>
        <tbody>
        <?php $n=1; while($row=$list->fetch_assoc()):
            $full = htmlspecialchars($row['last_name'].', '.$row['first_name'].($row['middle_name']?' '.$row['middle_name']:''));
            $age  = sc_age($row['birth_month'],$row['birth_day'],$row['birth_year']);
            $bdate = sprintf('%02d/%02d/%04d', $row['birth_month'], $row['birth_day'], $row['birth_year']);
            $is_today = ($row['birth_month']==$cur_m && $row['birth_day']==$cur_d);
            $is_month = ($row['birth_month']==$cur_m);
        ?>
        <tr class="<?=$is_today?'birthday-today':''?>">
          <td style="color:#94a3b8;font-size:11px"><?=$n++?></td>
          <td>
            <?=$full?>
            <?php if($is_today):?>
              <span class="badge badge-today">🎉 Today!</span>
            <?php elseif($is_month):?>
              <span class="badge badge-bday">🎂 This month</span>
            <?php endif;?>
          </td>
          <td><span class="age-pill"><?=$age?></span></td>
          <td style="font-size:12px;color:#64748b"><?=$bdate?></td>
          <td style="font-size:12px"><?=htmlspecialchars($row['gender']??'—')?></td>
          <td style="font-size:12px;max-width:180px"><?=htmlspecialchars($row['address']??'—')?></td>
          <td style="font-size:12px;color:#64748b"><?=htmlspecialchars($row['contact_number']??'—')?></td>
          <td>
            <span class="badge <?=$row['status']==='Active'?'badge-alive':'badge-deceased'?>">
              <?=$row['status']==='Active'?'Alive':'Deceased'?>
            </span>
          </td>
          <?php if($can_edit):?>
          <td style="white-space:nowrap">
            <button class="btn btn-outline btn-sm" onclick="openEdit(<?=htmlspecialchars(json_encode($row))?>)">
              <i class="fas fa-pen"></i> Edit
            </button>
            <?php if($is_captain):?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record permanently?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="sc_id" value="<?=$row['id']?>">
              <button type="submit" class="btn btn-sm" style="background:#f43f5e;border-color:#f43f5e;color:#fff"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif;?>
          </td>
          <?php endif;?>
        </tr>
        <?php endwhile;?>
        </tbody>
      </table>
    </div>
    <?php else:?>
    <div class="empty-state">
      <i class="fas fa-user-slash"></i>
      <p><?=$search?'No results for "'.htmlspecialchars($search).'"':'No senior citizens registered yet.'?></p>
    </div>
    <?php endif;?>
  </div>

  <!-- BIRTHDAY TAB -->
  <div id="tab-birthdays" class="tab-content">

    <!-- Today's birthdays -->
    <div class="bday-section">
      <div class="bday-section-title">
        <i class="fas fa-star" style="color:#f59e0b"></i> Today's Birthdays
        <?php if(count($bday_today)>0):?>
          <span style="background:#fef9c3;color:#a16207;padding:1px 8px;border-radius:20px;font-size:10px"><?=count($bday_today)?> celebrant<?=count($bday_today)>1?'s':''?></span>
        <?php endif;?>
      </div>
      <?php if(count($bday_today)===0):?>
        <p style="color:#94a3b8;font-size:13px;padding:.5rem 0">No birthdays today.</p>
      <?php else: foreach($bday_today as $r):
          $age = sc_age($r['birth_month'],$r['birth_day'],$r['birth_year']);
      ?>
      <div class="bday-card today">
        <div class="bday-avatar today-av">🎉</div>
        <div style="flex:1">
          <div class="bday-name"><?=htmlspecialchars($r['last_name'].', '.$r['first_name'])?></div>
          <div class="bday-info">Turns <?=$age?> today · <?=date('F j')?></div>
        </div>
        <span class="badge" style="background:#fef3c7;color:#92400e">Prepare benefits</span>
      </div>
      <?php endforeach; endif;?>
    </div>

    <!-- This month's other birthdays -->
    <div class="bday-section">
      <div class="bday-section-title">
        <i class="fas fa-calendar-days" style="color:#10b981"></i> Other Birthdays This Month (<?=date('F')?>)
        <?php if(count($bday_this_month)>0):?>
          <span style="background:#dcfce7;color:#15803d;padding:1px 8px;border-radius:20px;font-size:10px"><?=count($bday_this_month)?></span>
        <?php endif;?>
      </div>
      <?php if(count($bday_this_month)===0):?>
        <p style="color:#94a3b8;font-size:13px;padding:.5rem 0">No other birthdays this month.</p>
      <?php else: foreach($bday_this_month as $r):
          $age = sc_age($r['birth_month'],$r['birth_day'],$r['birth_year']);
          $days_until = (int)(new DateTime(date('Y').'-'.$r['birth_month'].'-'.$r['birth_day']))->diff(new DateTime())->days;
      ?>
      <div class="bday-card">
        <div class="bday-avatar month-av">🎂</div>
        <div style="flex:1">
          <div class="bday-name"><?=htmlspecialchars($r['last_name'].', '.$r['first_name'])?></div>
          <div class="bday-info">
            <?=date('F j', mktime(0,0,0,$r['birth_month'],$r['birth_day'],date('Y')))?> · Turns <?=$age?>
          </div>
        </div>
      </div>
      <?php endforeach; endif;?>
    </div>

    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;font-size:13px;color:#15803d">
      <i class="fas fa-circle-info" style="margin-right:6px"></i>
      Don't forget to prepare birthday gifts and benefits for our senior citizens!
    </div>
  </div>
</main>

<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City</footer>

<?php if($can_edit):?>
<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <h3><i class="fas fa-pen" style="color:#3b82f6;margin-right:8px"></i>Edit Senior Citizen</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="sc_id" id="edit_id">
      <div class="fgrow">
        <div class="fg"><label>Last Name *</label><input type="text" name="last_name" id="edit_ln" required></div>
        <div class="fg"><label>First Name *</label><input type="text" name="first_name" id="edit_fn" required></div>
        <div class="fg"><label>Middle Name</label><input type="text" name="middle_name" id="edit_mn"></div>
      </div>
      <div class="fgrow2">
        <div class="fg"><label>Gender</label>
          <select name="gender" id="edit_gen">
            <option value="">— Select —</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div class="fg"><label>Status</label>
          <select name="status" id="edit_status">
            <option value="Active">Active / Alive</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
      </div>
      <div class="fgrow">
        <div class="fg"><label>Birth Month</label><input type="number" name="birth_month" id="edit_bm" min="1" max="12"></div>
        <div class="fg"><label>Birth Day</label><input type="number" name="birth_day" id="edit_bd" min="1" max="31"></div>
        <div class="fg"><label>Birth Year</label><input type="number" name="birth_year" id="edit_by" min="1900"></div>
      </div>
      <div class="fg"><label>Contact Number</label><input type="text" name="contact_number" id="edit_con"></div>
      <div class="fg"><label>Address</label><textarea name="address" id="edit_addr" rows="2"></textarea></div>
      <div style="display:flex;gap:10px;margin-top:.5rem">
        <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>

<?php if($is_captain):?>
<!-- SYNC MODAL -->
<div class="modal-overlay" id="syncModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:420px;text-align:center">
    <h3><i class="fas fa-rotate" style="color:#10b981;margin-right:8px"></i>Sync from Residents</h3>
    <p style="font-size:13px;color:#475569;margin-bottom:1.25rem;line-height:1.6">
      This will scan all registered residents and automatically add those aged <strong>60+</strong> or marked as senior citizen to this list.<br><br>
      Already-existing records will not be duplicated.
    </p>
    <form method="POST" style="display:flex;gap:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="sync_from_residents">
      <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('syncModal').classList.remove('open')">Cancel</button>
      <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-rotate"></i> Run Sync</button>
    </form>
  </div>
</div>
<?php endif;?>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
function openSettings(){}

function switchTab(name, el){
    document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
    el.classList.add('active');
}

function openEdit(row){
    document.getElementById('edit_id').value  = row.id;
    document.getElementById('edit_ln').value  = row.last_name;
    document.getElementById('edit_fn').value  = row.first_name;
    document.getElementById('edit_mn').value  = row.middle_name || '';
    document.getElementById('edit_gen').value = row.gender || '';
    document.getElementById('edit_bm').value  = row.birth_month;
    document.getElementById('edit_bd').value  = row.birth_day;
    document.getElementById('edit_by').value  = row.birth_year;
    document.getElementById('edit_con').value = row.contact_number || '';
    document.getElementById('edit_addr').value= row.address || '';
    document.getElementById('edit_status').value = row.status;
    document.getElementById('editModal').classList.add('open');
}
</script>
</body>
</html>
