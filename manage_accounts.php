<?php
session_start();
if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
include 'Admin_DB.php';
include 'role_helper.php';
if(!$is_secretary && !$is_captain){header("Location: Home.php?denied=accounts");exit();}
// Account management has moved to the Super Admin panel
// This page is kept for reference but account creation/deletion is Super Admin only

// Auto-add columns if missing
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS role ENUM('captain','secretary','guest') NOT NULL DEFAULT 'secretary'");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS full_name VARCHAR(150) DEFAULT NULL");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");

// Auto-create access_log table if missing
$conn->query("CREATE TABLE IF NOT EXISTS access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    detail TEXT,
    performed_by VARCHAR(100) DEFAULT NULL,
    role VARCHAR(50) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Auto-create failed_attempts if missing
$conn->query("CREATE TABLE IF NOT EXISTS failed_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    locked_until DATETIME DEFAULT NULL,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ip (ip_address)
) ENGINE=InnoDB");

// Password reset requests table
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','resolved') DEFAULT 'pending'
) ENGINE=InnoDB");

$msg=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify();
    $action=trim($_POST['action']??'');

    // CREATE ACCOUNT — Secretary only (Super Admin is the main account manager)
    if($action==='create'){
        if(!$is_secretary){
            $err="Account creation is handled by the Super Admin.";
        } else {
            $uname = trim($_POST['username']??'');
            $fname = trim($_POST['full_name']??'');
            $role  = 'secretary';
            $pw    = trim($_POST['password']??'');
            $pw2   = trim($_POST['password2']??'');

            if(empty($uname)||empty($fname)||empty($pw)){
                $err="Username, full name, and password are required.";
            } elseif($pw !== $pw2){
                $err="Passwords do not match.";
            } elseif(strlen($pw)<6){
                $err="Password must be at least 6 characters.";
            } elseif($role!=='secretary'){
                $err="Invalid role. Only Secretary accounts can be created here.";
            } else {
                $check=$conn->prepare("SELECT id FROM admins WHERE username=?");
                $check->bind_param("s",$uname);$check->execute();
                if($check->get_result()->num_rows>0){
                    $err="Username '$uname' already exists.";
                } else {
                    $hash=password_hash($pw,PASSWORD_BCRYPT);
                    $uname_e=mysqli_real_escape_string($conn,$uname);
                    $fname_e=mysqli_real_escape_string($conn,$fname);
                    $role_e =mysqli_real_escape_string($conn,$role);
                    $conn->query("INSERT INTO admins (username,password,role,full_name,is_active) VALUES ('$uname_e','$hash','$role_e','$fname_e',1)");
                    $msg="Account '@$uname' created successfully as ".ucfirst($role).".";
                    $by=mysqli_real_escape_string($conn,$_SESSION['admin']);
                    $ip=mysqli_real_escape_string($conn,$_SERVER['REMOTE_ADDR']);
                    $conn->query("INSERT INTO access_log (event_type,detail,performed_by,role,ip_address) VALUES ('ACCOUNT_CREATED','Created account: $uname_e ($role_e)','$by','secretary','$ip')");
                }
            }
        }
    } // end create
    // TOGGLE ACTIVE
    elseif($action==='toggle'){
        $uid=(int)$_POST['uid'];
        $cur=(int)$_POST['current'];
        $new=$cur?0:1;
        $conn->query("UPDATE admins SET is_active=$new WHERE id=$uid AND role!='captain'");
        $msg=$new?'Account activated.':'Account deactivated.';
        $by=mysqli_real_escape_string($conn,$_SESSION['admin']);
        $ip=mysqli_real_escape_string($conn,$_SERVER['REMOTE_ADDR']);
        $conn->query("INSERT INTO access_log (event_type,detail,performed_by,role,ip_address) VALUES ('ACCOUNT_TOGGLED','Account ID $uid set to ".($new?'active':'inactive')."','$by','captain','$ip')");
    }
    // CHANGE PASSWORD
    elseif($action==='change_password'){
        $uid=(int)$_POST['uid'];
        $np=trim($_POST['new_password']??'');
        if(strlen($np)<6){$err="Password must be at least 6 characters.";}
        else{
            $hash=password_hash($np,PASSWORD_BCRYPT);
            $stmt=$conn->prepare("UPDATE admins SET password=? WHERE id=?");
            $stmt->bind_param("si",$hash,$uid);$stmt->execute();$stmt->close();
            $msg="Password updated successfully.";
        }
    }
    // UPDATE NAME
    elseif($action==='update_name'){
        $uid=(int)$_POST['uid'];
        $fn=mysqli_real_escape_string($conn,trim($_POST['full_name']??''));
        $conn->query("UPDATE admins SET full_name='$fn' WHERE id=$uid");
        $msg="Name updated.";
    }
    // DELETE ACCOUNT — Secretary only
    elseif($action==='delete'){
        if(!$is_secretary){ $err="Account deletion is handled by the Super Admin."; }
        else {
            $uid=(int)$_POST['uid'];
            $conn->query("DELETE FROM admins WHERE id=$uid AND role!='captain'");
            $msg="Account deleted.";
        }
    }
    // RESOLVE RESET REQUEST
    elseif($action==='resolve_reset'){
        $rid=(int)$_POST['rid'];
        $ru=trim($_POST['reset_target']??'');
        $np=trim($_POST['new_password']??'');
        if(strlen($np)<6){ $err="Password must be at least 6 characters."; }
        else {
            $hash=password_hash($np,PASSWORD_BCRYPT);
            $ru_e=$conn->real_escape_string($ru);
            $conn->query("UPDATE admins SET password='$hash' WHERE username='$ru_e' AND role!='captain'");
            $conn->query("UPDATE password_reset_requests SET status='resolved' WHERE id=$rid");
            $by=$conn->real_escape_string($_SESSION['admin']);
            $ip=$conn->real_escape_string($_SERVER['REMOTE_ADDR']);
            $conn->query("INSERT INTO access_log (event_type,detail,performed_by,role,ip_address) VALUES ('PASSWORD_RESET','Reset password for: $ru_e','$by','captain','$ip')");
            $msg="Password for '@$ru' has been reset successfully.";
        }
    }
    // DISMISS RESET REQUEST
    elseif($action==='dismiss_reset'){
        $rid=(int)$_POST['rid'];
        $conn->query("DELETE FROM password_reset_requests WHERE id=$rid");
        $msg="Reset request dismissed.";
    }
}

// Fetch accounts
$accounts=[];
$r=$conn->query("SELECT id,username,full_name,role,is_active FROM admins ORDER BY FIELD(role,'captain','secretary','guest'),username");
while($row=$r->fetch_assoc()) $accounts[]=$row;

// Fetch pending reset requests
$reset_requests=[];
$rr=$conn->query("SELECT * FROM password_reset_requests WHERE status='pending' ORDER BY requested_at DESC");
if($rr) while($row=$rr->fetch_assoc()) $reset_requests[]=$row;

// Fetch recent access logs
$logs=$conn->query("SELECT * FROM access_log ORDER BY created_at DESC LIMIT 30");

$role_info=[
    'captain'  =>['#d97706','#fffbeb','#fde68a','fa-star',  'Full system control'],
    'secretary'=>['#2563eb','#eff6ff','#bfdbfe','fa-user-tie','Register, edit, manage residents'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manage Accounts – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<style>
main{padding:1.5rem;max-width:980px;margin:0 auto}
.tabs{display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:1.25rem;gap:4px}
.tab-btn{padding:8px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;background:none;border-top:none;border-left:none;border-right:none;font-family:'Inter',sans-serif;transition:all .2s}
.tab-btn.active{color:#3b82f6;border-bottom-color:#3b82f6}
.tab-pane{display:none}.tab-pane.active{display:block}

.acc-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;margin-bottom:12px;overflow:hidden}
.acc-head{display:flex;align-items:center;gap:14px;padding:1.1rem 1.5rem}
.acc-av{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.acc-name{font-size:14px;font-weight:700;color:#0f172a}
.acc-user{font-size:12px;color:#64748b;font-family:monospace}
.role-badge{font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;margin-left:6px}
.acc-body{padding:1.1rem 1.5rem;border-top:1px solid #f1f5f9;background:#f8fafc;display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.acc-body{grid-template-columns:1fr}}
.fg label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;display:block;margin-bottom:4px}
.fg input{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;width:100%;box-sizing:border-box;transition:border .2s;margin-bottom:0!important}
.acc-body .pw-wrap input[type="password"],.acc-body .pw-wrap input[type="text"]{padding-right:36px!important}
.fg input:focus{border-color:#3b82f6}
/* pw-eye: small icon inside the field, no blue */
.acc-body .pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:24px;height:24px;min-width:unset;border-radius:5px;background:none!important;border:none;cursor:pointer;color:#94a3b8!important;font-size:12px;display:flex;align-items:center;justify-content:center;padding:0;z-index:2;transition:color .15s}
.acc-body .pw-eye:hover{color:#64748b!important;background:rgba(0,0,0,.05)!important}
.acc-body .pw-eye:active{background:rgba(0,0,0,.08)!important}
/* Update / Change buttons: outline style, not blue */
.acc-body button[type="submit"]{background:#fff!important;color:#374151!important;border:1px solid #d1d5db!important;padding:7px 13px!important;font-size:12px!important;font-weight:600!important;min-width:unset!important;border-radius:8px!important;white-space:nowrap}
.acc-body button[type="submit"]:hover{background:#f1f5f9!important}
.acc-footer{padding:.75rem 1.5rem;border-top:1px solid #f1f5f9;display:flex;gap:8px;flex-wrap:wrap;align-items:center}

.create-card{background:#fff;border:2px dashed #e2e8f0;border-radius:14px;padding:2rem;margin-bottom:1.25rem;transition:border-color .2s}
.create-card:hover{border-color:#3b82f6}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.form-row-2,.role-select-grid{grid-template-columns:1fr}}

.log-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12px}
.log-row:last-child{border-bottom:none}
.log-type{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;flex-shrink:0}
.log-login{background:#eff6ff;color:#1d4ed8}
.log-logout{background:#f1f5f9;color:#475569}
.log-create{background:#f0fdf4;color:#15803d}
.log-other{background:#fffbeb;color:#92400e}
.log-fail{background:#fff1f2;color:#be123c}

.captain-note{background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12px;padding:10px 14px;border-radius:9px;display:flex;align-items:center;gap:8px;margin-bottom:1.25rem}
.reset-alert{background:#fff1f2;border:1px solid #fecdd3;border-radius:14px;margin-bottom:1.25rem;overflow:hidden}
.reset-alert-head{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#fff5f5}
.reset-alert-head i{color:#e11d48;font-size:16px}
.reset-alert-head strong{color:#be123c;font-size:13px}
.reset-alert-head span{font-size:11px;color:#f43f5e;margin-left:auto;background:#ffe4e6;padding:2px 9px;border-radius:20px;font-weight:700}
.reset-item{padding:12px 16px;border-top:1px solid #fecdd3;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.reset-item-info{flex:1;min-width:180px}
.reset-item-user{font-size:13px;font-weight:700;color:#0f172a;font-family:monospace}
.reset-item-time{font-size:11px;color:#94a3b8;margin-top:2px}
.reset-pw-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.reset-pw-form input{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;width:180px;transition:border .2s}
.reset-pw-form input:focus{border-color:#3b82f6}
.reset-pw-form button{background:#be123c;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .2s}
.reset-pw-form button:hover{background:#9f1239}
.dismiss-btn{background:none;color:#94a3b8;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .2s;font-family:'Inter',sans-serif}
.dismiss-btn:hover{background:#fff1f2;border-color:#fecdd3;color:#be123c}
footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem;margin-top:2rem}
</style>
</head>
<body>
<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div></a>
  <div style="display:flex;align-items:center;gap:6px;border-left:1px solid rgba(255,255,255,.12);padding-left:14px">
    <span style="font-size:13px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><i class="fas fa-users-gear" style="opacity:.8;margin-right:5px"></i> Account Management</span>
  </div>
  <div class="topbar-right" style="margin-left:auto">
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#fbbf24">
      <i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i>
    </div>
  </div>
</header>

<main>
  <?php if($msg):?><div class="alert alert-success" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif;?>
  <?php if($err):?><div class="alert alert-error" style="margin-bottom:1rem"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($err)?></div><?php endif;?>

  <?php if(!empty($reset_requests)):?>
  <div class="reset-alert">
    <div class="reset-alert-head">
      <i class="fas fa-key"></i>
      <strong>Pending Password Reset Requests</strong>
      <span><?=count($reset_requests)?> pending</span>
    </div>
    <?php foreach($reset_requests as $req):?>
    <div class="reset-item">
      <div class="reset-item-info">
        <div class="reset-item-user">@<?=htmlspecialchars($req['username'])?></div>
        <div class="reset-item-time"><i class="fas fa-clock"></i> Requested <?=date('M j, Y g:i A',strtotime($req['requested_at']))?></div>
      </div>
      <form method="POST" class="reset-pw-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="resolve_reset">
        <input type="hidden" name="rid" value="<?=(int)$req['id']?>">
        <input type="hidden" name="reset_target" value="<?=htmlspecialchars($req['username'])?>">
        <input type="password" name="new_password" placeholder="Set new password" required minlength="6" autocomplete="new-password">
        <button type="submit"><i class="fas fa-check"></i> Reset &amp; Resolve</button>
      </form>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="dismiss_reset">
        <input type="hidden" name="rid" value="<?=(int)$req['id']?>">
        <button type="submit" class="dismiss-btn" onclick="return confirm('Dismiss this reset request?')"><i class="fas fa-xmark"></i> Dismiss</button>
      </form>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <div class="captain-note" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af">
    <i class="fas fa-shield-halved"></i>
    <span>
      <strong>Note:</strong> Full account management (create, delete, role changes) is handled by the <strong>Super Admin</strong>.
      The Secretary can reset passwords and activate/deactivate accounts here.
      <?php if($is_captain): ?>You are in <strong>monitoring mode</strong> — changes here are restricted to the Secretary.<?php endif; ?>
    </span>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('accounts',this)"><i class="fas fa-users"></i> Accounts (<?=count($accounts)?>)</button>
    <button class="tab-btn" onclick="switchTab('create',this)"><i class="fas fa-user-plus"></i> Create Account</button>
    <button class="tab-btn" onclick="switchTab('logs',this)"><i class="fas fa-clock-rotate-left"></i> Access Log</button>
  </div>

  <!-- ══ ACCOUNTS TAB ══ -->
  <div id="tab-accounts" class="tab-pane active">
    <?php foreach($accounts as $acc):
      $ri=$role_info[$acc['role']];
      $is_self=($acc['username']===$_SESSION['admin']);
      $is_cap =($acc['role']==='captain');
    ?>
    <div class="acc-card">
      <div class="acc-head">
        <div class="acc-av" style="background:<?=$ri[1]?>;color:<?=$ri[0]?>"><i class="fas <?=$ri[3]?>"></i></div>
        <div style="flex:1">
          <div class="acc-name">
            <?=htmlspecialchars($acc['full_name']??$acc['username'])?>
            <span class="role-badge" style="color:<?=$ri[0]?>;background:<?=$ri[1]?>;border:1px solid <?=$ri[2]?>"><?=ucfirst($acc['role'])?></span>
            <?php if($is_self):?><span style="font-size:10px;background:#f0fdf4;color:#15803d;padding:2px 8px;border-radius:20px;font-weight:600;margin-left:4px">You</span><?php endif;?>
          </div>
          <div class="acc-user">@<?=htmlspecialchars($acc['username'])?> · <?=htmlspecialchars($ri[4]??'')?></div>
        </div>
        <div style="display:flex;align-items:center;gap:7px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?=$acc['is_active']?'#22c55e':'#f43f5e'?>;display:inline-block"></span>
          <span style="font-size:12px;font-weight:600;color:<?=$acc['is_active']?'#15803d':'#be123c'?>"><?=$acc['is_active']?'Active':'Inactive'?></span>
        </div>
      </div>

      <?php if(!$is_cap): ?>
      <div class="acc-body">
        <form method="POST" class="fg">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_name">
          <input type="hidden" name="uid" value="<?=$acc['id']?>">
          <label>Display Name</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" name="full_name" value="<?=htmlspecialchars($acc['full_name']??'')?>" style="flex:1;margin-bottom:0">
            <button type="submit" class="btn btn-outline btn-sm" style="white-space:nowrap;flex-shrink:0"><i class="fas fa-save"></i> Update</button>
          </div>
        </form>
        <form method="POST" class="fg">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <input type="hidden" name="uid" value="<?=$acc['id']?>">
          <label>New Password</label>
          <div style="display:flex;gap:6px;align-items:center">
            <div class="pw-wrap" style="flex:1">
              <input type="password" name="new_password" placeholder="Min. 6 characters" id="pw<?=$acc['id']?>" style="margin-bottom:0">
              <button type="button" class="pw-eye" onclick="tpw('pw<?=$acc['id']?>',this)"><i class="fas fa-eye"></i></button>
            </div>
            <button type="submit" class="btn btn-outline btn-sm" style="white-space:nowrap;flex-shrink:0"><i class="fas fa-key"></i> Change</button>
          </div>
        </form>
      </div>
      <div class="acc-footer">
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="uid" value="<?=$acc['id']?>">
          <input type="hidden" name="current" value="<?=$acc['is_active']?>">
          <button type="submit" class="btn btn-sm" onclick="return confirm('<?=$acc['is_active']?'Deactivate':'Activate'?> this account?')"
            style="<?=$acc['is_active']?'background:#fff1f2;color:#f43f5e;border:1px solid #fecdd3':'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0'?>">
            <i class="fas fa-<?=$acc['is_active']?'ban':'check'?>"></i> <?=$acc['is_active']?'Deactivate':'Activate'?>
          </button>
        </form>
        <?php if(!$is_self): ?>
        <form method="POST" style="display:inline;margin-left:auto">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="uid" value="<?=$acc['id']?>">
          <button type="submit" class="btn btn-sm" onclick="return confirm('Permanently delete this account? This cannot be undone.')"
            style="background:#fff1f2;color:#f43f5e;border:1px solid #fecdd3">
            <i class="fas fa-trash"></i> Delete Account
          </button>
        </form>
        <?php endif;?>
      </div>
      <?php else: ?>
      <div class="acc-footer"><span style="font-size:12px;color:#94a3b8"><i class="fas fa-shield-halved"></i> Captain account is protected and cannot be modified.</span></div>
      <?php endif;?>
    </div>
    <?php endforeach;?>
  </div>

  <!-- ══ CREATE ACCOUNT TAB ══ -->
  <div id="tab-create" class="tab-pane">
    <div class="create-card">
      <h2 style="font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:4px"><i class="fas fa-user-plus" style="color:#3b82f6;margin-right:8px"></i>Create New Account</h2>
      <p style="font-size:13px;color:#64748b;margin-bottom:1.25rem">Add a secretary account. Captain accounts cannot be created here.</p>

      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="role" value="secretary">

        <div class="form-row-2" style="margin-bottom:12px">
          <div class="fg"><label>Full Name *</label><input type="text" name="full_name" placeholder="e.g. Maria Santos" required></div>
          <div class="fg"><label>Username *</label><input type="text" name="username" placeholder="e.g. maria.secretary" required autocomplete="off"></div>
        </div>
        <div class="form-row-2" style="margin-bottom:1.25rem">
          <div class="fg"><label>Password *</label>
            <div class="pw-wrap"><input type="password" name="password" id="newPw" placeholder="Min. 6 characters" required><button type="button" class="pw-eye" onclick="tpw('newPw',this)"><i class="fas fa-eye"></i></button></div>
          </div>
          <div class="fg"><label>Confirm Password *</label>
            <div class="pw-wrap"><input type="password" name="password2" id="newPw2" placeholder="Re-enter password" required><button type="button" class="pw-eye" onclick="tpw('newPw2',this)"><i class="fas fa-eye"></i></button></div>
          </div>
        </div>

        <!-- Role permissions preview -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:12px 14px;margin-bottom:1.25rem;font-size:13px;color:#1d4ed8">
          <i class="fas fa-user-tie" style="margin-right:7px"></i>
          <strong>Secretary:</strong> Can register residents, edit records, process document requests, and generate RBI reports. Cannot manage other accounts.
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;padding:12px"><i class="fas fa-user-plus"></i> Create Account</button>
      </form>
    </div>
  </div>

  <!-- ══ ACCESS LOG TAB ══ -->
  <div id="tab-logs" class="tab-pane">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">
      <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between">
        <div style="font-size:13px;font-weight:600;color:#0f172a"><i class="fas fa-clock-rotate-left" style="color:#3b82f6;margin-right:8px"></i>Recent Access Log</div>
        <span style="font-size:11px;color:#94a3b8">Last 30 entries</span>
      </div>
      <div style="padding:4px 16px">
        <?php if(!$logs || $logs->num_rows===0): ?>
        <div style="text-align:center;padding:2rem;color:#94a3b8"><i class="fas fa-clock-rotate-left" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px"></i><p>No access logs yet.</p></div>
        <?php else: while($log=$logs->fetch_assoc()):
          $type=strtolower($log['event_type']??'');
          $cls='log-other';
          if(str_contains($type,'login')) $cls='log-login';
          elseif(str_contains($type,'logout')) $cls='log-logout';
          elseif(str_contains($type,'create')) $cls='log-create';
          elseif(str_contains($type,'fail')||str_contains($type,'lock')) $cls='log-fail';
          $role_c=['captain'=>'#d97706','secretary'=>'#2563eb','guest'=>'#475569'];
          $rc=$role_c[$log['role']??'']??'#94a3b8';
        ?>
        <div class="log-row">
          <span class="log-type <?=$cls?>"><?=htmlspecialchars($log['event_type']??'')?></span>
          <div style="flex:1;min-width:0">
            <div style="font-size:12px;color:#0f172a"><?=htmlspecialchars($log['detail']??'')?></div>
            <div style="display:flex;gap:10px;margin-top:2px;flex-wrap:wrap">
              <span style="font-size:11px;color:<?=$rc?>;font-weight:600"><i class="fas fa-user" style="font-size:10px"></i> <?=htmlspecialchars($log['performed_by']??'-')?><?php if($log['role']):?> (<?=ucfirst($log['role'])?>)<?php endif;?></span>
              <span style="font-size:11px;color:#94a3b8"><i class="fas fa-globe" style="font-size:10px"></i> <?=htmlspecialchars($log['ip_address']??'')?></span>
              <span style="font-size:11px;color:#94a3b8"><i class="fas fa-clock" style="font-size:10px"></i> <?=date('M d, Y g:i A',strtotime($log['created_at']))?></span>
            </div>
          </div>
        </div>
        <?php endwhile; endif;?>
      </div>
    </div>
  </div>
</main>
<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410</footer>
<script>
function switchTab(t,btn){
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+t).classList.add('active');
  btn.classList.add('active');
}
function tpw(id,btn){const inp=document.getElementById(id);const show=inp.type==='password';inp.type=show?'text':'password';btn.querySelector('i').className=show?'fas fa-eye-slash':'fas fa-eye';}
</script>
</body>
</html>