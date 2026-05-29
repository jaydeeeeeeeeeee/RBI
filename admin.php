<?php
session_start();

$host='localhost';$user='root';$pass='';$dbname='projectrbi';
$conn=new mysqli($host,$user,$pass,$dbname);
if($conn->connect_error){die("Connection failed: ".$conn->connect_error);}

// Auto-add columns if missing
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS role ENUM('captain','secretary','guest') NOT NULL DEFAULT 'secretary'");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS full_name VARCHAR(150) DEFAULT NULL");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS expires_at DATETIME DEFAULT NULL");

// Password reset requests table
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','resolved') DEFAULT 'pending'
) ENGINE=InnoDB");

// Login attempts table
$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    locked_until DATETIME DEFAULT NULL,
    UNIQUE KEY uq_ip (ip)
) ENGINE=InnoDB");

// Remove expired lockouts — also catches any corrupted locked_until values
$conn->query("DELETE FROM login_attempts WHERE
    (locked_until IS NOT NULL AND locked_until < NOW())
    OR last_attempt < NOW() - INTERVAL 10 MINUTE");

define('MAX_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 5);

$ip = $_SERVER['REMOTE_ADDR'];
$ip_e = $conn->real_escape_string($ip);

// Check current lockout status — use MySQL to compute seconds left (avoids timezone mismatch)
$lockRow = null;
$lockRes = $conn->query("SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS secs_left FROM login_attempts WHERE ip='$ip_e'");
if($lockRes && $lockRes->num_rows > 0) $lockRow = $lockRes->fetch_assoc();

$isLocked     = false;
$lockSecsLeft = 0;
if($lockRow && $lockRow['locked_until'] && (int)$lockRow['secs_left'] > 0){
    $isLocked     = true;
    $lockSecsLeft = (int)$lockRow['secs_left'];
}

$error = "";
$resetStatus = ""; // 'sent' | 'notfound' | 'already'

if($_SERVER["REQUEST_METHOD"]==="POST"){
    $action = $_POST['action'] ?? 'login';

    if($action === 'request_reset'){
        $ru = trim($_POST['reset_username'] ?? '');
        if(!empty($ru)){
            $chk = $conn->prepare("SELECT id FROM admins WHERE username=? AND is_active=1 AND role IN ('captain','secretary')");
            $chk->bind_param("s",$ru); $chk->execute();
            if($chk->get_result()->num_rows > 0){
                $dup = $conn->prepare("SELECT id FROM password_reset_requests WHERE username=? AND status='pending'");
                $dup->bind_param("s",$ru); $dup->execute();
                if($dup->get_result()->num_rows > 0){
                    $resetStatus = 'already';
                } else {
                    $ru_e2 = $conn->real_escape_string($ru);
                    $conn->query("INSERT INTO password_reset_requests (username) VALUES ('$ru_e2')");
                    $resetStatus = 'sent';
                }
            } else { $resetStatus = 'notfound'; }
        }
    } else {
        if($isLocked){
            $error = "locked";
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username=? AND is_active=1");
            $stmt->bind_param("s",$username);
            $stmt->execute();
            $result = $stmt->get_result();
            if($result->num_rows == 1){
                $row = $result->fetch_assoc();
                if(password_verify($password,$row['password'])){
                    // Successful login — clear attempts
                    $conn->query("DELETE FROM login_attempts WHERE ip='$ip_e'");
                    $_SESSION['admin']     = $row['username'];
                    $_SESSION['role']      = $row['role'] ?? 'secretary';
                    $_SESSION['full_name'] = $row['full_name'] ?? $row['username'];
                    $_SESSION['admin_id']  = $row['id'];
                    $uname_log = $conn->real_escape_string($username);
                    $role_log  = $conn->real_escape_string($row['role'] ?? 'secretary');
                    $ip_log    = $conn->real_escape_string($ip);
                    $fn_log    = $conn->real_escape_string($row['full_name'] ?? $username);
                    $conn->query("INSERT INTO access_log (event_type,detail,performed_by,role,ip_address) VALUES ('LOGIN','$fn_log logged in','$uname_log','$role_log','$ip_log')");
                    header("Location: Home.php"); exit();
                } else {
                    // Wrong password — record attempt
                    $newAttempts = ($lockRow ? (int)$lockRow['attempts'] : 0) + 1;
                    $lockUntilSQL = ($newAttempts >= MAX_ATTEMPTS)
                        ? ", locked_until = NOW() + INTERVAL ".LOCKOUT_MINUTES." MINUTE"
                        : ", locked_until = NULL";
                    $conn->query("INSERT INTO login_attempts (ip, attempts, last_attempt)
                        VALUES ('$ip_e', 1, NOW())
                        ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()$lockUntilSQL");
                    if($newAttempts >= MAX_ATTEMPTS){
                        $isLocked = true;
                        $lockSecsLeft = LOCKOUT_MINUTES * 60;
                        $error = "locked";
                    } else {
                        $remaining = MAX_ATTEMPTS - $newAttempts;
                        $error = "Incorrect password. " . $remaining . " attempt" . ($remaining === 1 ? "" : "s") . " remaining.";
                    }
                }
            } else {
                // Unknown user — still count attempt against IP
                $newAttempts = ($lockRow ? (int)$lockRow['attempts'] : 0) + 1;
                $lockUntilSQL = ($newAttempts >= MAX_ATTEMPTS)
                    ? ", locked_until = NOW() + INTERVAL ".LOCKOUT_MINUTES." MINUTE"
                    : ", locked_until = NULL";
                $conn->query("INSERT INTO login_attempts (ip, attempts, last_attempt)
                    VALUES ('$ip_e', 1, NOW())
                    ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()$lockUntilSQL");
                if($newAttempts >= MAX_ATTEMPTS){
                    $isLocked = true;
                    $lockSecsLeft = LOCKOUT_MINUTES * 60;
                    $error = "locked";
                } else {
                    $remaining = MAX_ATTEMPTS - $newAttempts;
                    $error = "Account not found or inactive. " . $remaining . " attempt" . ($remaining === 1 ? "" : "s") . " remaining.";
                }
            }
            $stmt->close();
        }
    }
}

// ── Public census stats ───────────────────────────────────────────────────────
function pub($conn,$where){
    $r=mysqli_query($conn,"SELECT COUNT(*) AS c FROM residents WHERE is_hidden=0 AND $where");
    return $r ? (int)mysqli_fetch_assoc($r)['c'] : 0;
}
$pub_total      = pub($conn,"1=1");
$pub_households = (int)(mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(DISTINCT CONCAT(head_first_name,' ',head_last_name)) AS c FROM residents WHERE head_first_name!='' AND is_hidden=0"))['c']);
$pub_male       = pub($conn,"LOWER(gender)='male'");
$pub_female     = pub($conn,"LOWER(gender)='female'");
$pub_employed   = pub($conn,"employment_status='Employed'");
$pub_unemployed = pub($conn,"employment_status!='Employed'");
$pub_seniors    = pub($conn,"TIMESTAMPDIFF(YEAR,birthdate,CURDATE())>=60");
$pub_voters     = pub($conn,"voter='Yes'");
$pub_pwd        = pub($conn,"pwd_status='Yes'");
$pub_youth      = pub($conn,"TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 15 AND 30");

// Age groups for chart
$pub_age_child  = pub($conn,"TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 0 AND 17");
$pub_age_adult  = pub($conn,"TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN 18 AND 59");
$pub_age_senior = $pub_seniors;

// Civil status for chart
$pub_single   = pub($conn,"marital_status='Single'");
$pub_married  = pub($conn,"marital_status='Married'");
$pub_widowed  = pub($conn,"marital_status='Widowed'");
$pub_other_cs = $pub_total - $pub_single - $pub_married - $pub_widowed;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>iBarangay – Barangay 410, Manila City</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── RESET & ROOT ────────────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d1526;
  --blue:#3b82f6;
  --teal:#14b8a6;
}
html,body{height:100%;overflow:hidden;font-family:'Inter',sans-serif}

/* ── ANIMATIONS ──────────────────────────────────────────────────────────── */
@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes shimmer{0%,100%{opacity:.5}50%{opacity:1}}

/* ── FULL-SCREEN LANDING ─────────────────────────────────────────────────── */
.landing{
  position:relative;width:100%;height:100vh;
  display:flex;align-items:stretch;overflow:hidden;
  background:var(--bg);
}

/* Layered background */
.bg-img{
  position:absolute;inset:0;z-index:0;
  background:url('images/Barangay_officials_410.png') center center/cover no-repeat;
  opacity:.12;
}
.bg-overlay{
  position:absolute;inset:0;z-index:1;
  background:linear-gradient(125deg,rgba(13,21,38,.99) 0%,rgba(13,21,38,.93) 55%,rgba(17,35,65,.88) 100%);
}
/* Dot-grid texture */
.bg-dots{
  position:absolute;inset:0;z-index:1;
  background-image:radial-gradient(rgba(255,255,255,.045) 1px,transparent 1px);
  background-size:26px 26px;
}
/* Ambient glow orbs */
.bg-glow-a{
  position:absolute;left:-200px;top:5%;z-index:1;
  width:520px;height:520px;border-radius:50%;
  background:radial-gradient(circle,rgba(59,130,246,.16) 0%,transparent 68%);
  pointer-events:none;
}
.bg-glow-b{
  position:absolute;right:-150px;bottom:0%;z-index:1;
  width:480px;height:480px;border-radius:50%;
  background:radial-gradient(circle,rgba(20,184,166,.13) 0%,transparent 68%);
  pointer-events:none;
}

/* ── LEFT PANEL ─────────────────────────────────────────────────────────── */
.left-panel{
  position:relative;z-index:2;
  flex:0 0 50%;display:flex;flex-direction:column;
  justify-content:center;padding:2.5rem 3.5rem;
}
.left-panel::before{
  content:'';position:absolute;top:0;bottom:0;left:0;right:-40px;z-index:0;
  background:url('images/Brgy410_officials2.png') center center/cover no-repeat;
  opacity:.08;
  pointer-events:none;
}
.left-panel > *{position:relative;z-index:1;}

/* ── LOGO BLOCK ──────────────────────────────────────────────────────────── */
.lp-logo{
  display:flex;align-items:center;gap:18px;margin-bottom:2.25rem;
  animation:fadeUp .65s ease both;
}
.logo-mark{
  position:relative;width:72px;height:72px;border-radius:20px;flex-shrink:0;
  background:#fff;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 10px 32px rgba(59,130,246,.4),0 0 0 1px rgba(255,255,255,.12);
  overflow:hidden;
}
.logo-mark::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(145deg,rgba(255,255,255,.25) 0%,transparent 50%);
}
.logo-name{
  display:block;font-family:'Syne',sans-serif;
  font-size:24px;font-weight:800;color:#fff;line-height:1.1;letter-spacing:-.01em;
}
.logo-loc{
  display:flex;align-items:center;gap:5px;
  font-size:11.5px;color:rgba(255,255,255,.38);margin-top:4px;
}
.logo-loc i{font-size:10px;color:var(--teal)}

/* Accent divider */
.lp-divider{
  width:44px;height:2.5px;
  background:linear-gradient(90deg,var(--blue),var(--teal));
  border-radius:2px;margin-bottom:1.75rem;
  animation:fadeIn .6s .1s ease both;
}

/* Hero copy */
.lp-hero{animation:fadeUp .65s .13s ease both}
.lp-eyebrow{
  font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;
  color:var(--teal);margin-bottom:.85rem;opacity:.85;
}
.hero-title{
  font-family:'Syne',sans-serif;
  font-size:clamp(1.85rem,3.1vw,2.85rem);
  font-weight:800;color:#fff;line-height:1.18;margin-bottom:1rem;
}
.hero-title span{color:var(--teal);cursor:default}
.hero-desc{
  font-size:13.5px;color:rgba(255,255,255,.42);
  line-height:1.82;max-width:400px;
}

/* Quick-stats row */
.lp-stats{
  display:flex;align-items:center;
  margin-top:1.9rem;padding:.85rem 1.2rem;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:14px;width:fit-content;
  animation:fadeUp .65s .26s ease both;
}
.lstat{display:flex;flex-direction:column;align-items:center;padding:0 1.15rem}
.lstat-n{
  font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;line-height:1;
}
.lstat-l{font-size:10px;color:rgba(255,255,255,.32);margin-top:4px;white-space:nowrap}
.lstat-sep{width:1px;height:30px;background:rgba(255,255,255,.1)}

/* Copyright */
.lp-copy{
  margin-top:1.6rem;font-size:10.5px;color:rgba(255,255,255,.17);
  animation:fadeIn .6s .4s ease both;
}

/* ── RIGHT PANEL ─────────────────────────────────────────────────────────── */
.right-panel{
  position:relative;z-index:2;
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:2rem 2.5rem 2rem 1rem;
  animation:fadeUp .65s .2s ease both;
}
.right-card{
  width:100%;max-width:510px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.09);
  backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
  border-radius:22px;overflow:hidden;
  display:flex;flex-direction:column;
  height:clamp(420px,calc(100vh - 4rem),640px);
  box-shadow:0 28px 72px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.07);
}
.rcard-head{
  padding:.85rem 1.2rem;
  border-bottom:1px solid rgba(255,255,255,.07);
  display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
  background:rgba(255,255,255,.025);
}
.rcard-title{font-size:12px;font-weight:700;color:#fff;letter-spacing:.02em}
.rcard-sub{font-size:10px;color:rgba(255,255,255,.32);margin-top:2px}
.rdots{display:flex;gap:5px;align-items:center}
.rdot{
  width:5px;height:5px;border-radius:50%;
  background:rgba(255,255,255,.17);border:none;cursor:pointer;
  transition:all .35s cubic-bezier(.4,0,.2,1);padding:0;flex-shrink:0;
}
.rdot.active{width:16px;border-radius:3px;background:var(--teal)}
.rcard-body{position:relative;flex:1;min-height:0}

/* Slides: fade */
.rslide{
  position:absolute;inset:0;opacity:0;pointer-events:none;
  transition:opacity .5s ease;display:flex;flex-direction:column;
  padding:1.1rem 1.2rem;overflow:hidden;
}
.rslide.active{opacity:1;pointer-events:all}

/* Stats grid */
.stats-grid{
  display:grid;grid-template-columns:1fr 1fr;gap:.6rem;height:100%;
}
.stat-box{
  background:rgba(255,255,255,.055);
  border:1px solid rgba(255,255,255,.07);
  border-radius:13px;padding:.85rem .95rem;
  display:flex;align-items:center;gap:.7rem;
  transition:background .2s;
}
.stat-box:hover{background:rgba(255,255,255,.09)}
.stat-box-icon{
  width:34px;height:34px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;color:#fff;
}
.stat-box-num{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:#fff;line-height:1}
.stat-box-lbl{font-size:9.5px;color:rgba(255,255,255,.36);margin-top:3px}

/* Chart slides — strict contained box */
.chart-wrap-inner{
  position:relative;flex:1;min-height:0;overflow:hidden;
  border-radius:8px;
}
.chart-wrap-inner canvas{position:absolute;inset:0;width:100%!important;height:100%!important}
.chart-label{
  font-size:10px;font-weight:600;color:rgba(255,255,255,.35);
  text-align:center;margin-bottom:.5rem;flex-shrink:0;
  text-transform:uppercase;letter-spacing:.08em;
}

/* Project slides */
.proj-slide{display:flex;flex-direction:column;gap:.8rem;height:100%}
.proj-img{
  width:100%;border-radius:13px;overflow:hidden;
  background:#0d1f3c;
  display:flex;align-items:center;justify-content:center;
  position:relative;
}
.proj-img img{width:100%;height:auto;object-fit:contain;display:block;border-radius:13px;}
.proj-badge{
  display:inline-flex;align-items:center;gap:5px;
  font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
  padding:3px 10px;border-radius:20px;width:fit-content;
}
.proj-badge.done{background:rgba(16,185,129,.14);color:#34d399;border:1px solid rgba(16,185,129,.22)}
.proj-badge.ongoing{background:rgba(245,158,11,.14);color:#fbbf24;border:1px solid rgba(245,158,11,.22)}
.proj-badge.planned{background:rgba(99,102,241,.14);color:#a5b4fc;border:1px solid rgba(99,102,241,.22)}
.proj-title{
  font-family:'Syne',sans-serif;font-size:13.5px;font-weight:800;
  color:#fff;margin-top:3px;line-height:1.25;
}
.proj-desc{font-size:12.5px;color:rgba(255,255,255,.55);line-height:1.75}
.proj-footer{
  margin-top:auto;padding-top:.6rem;border-top:1px solid rgba(255,255,255,.07);
  font-size:10px;color:rgba(255,255,255,.25);display:flex;align-items:center;gap:5px;
}

/* ── LOGIN MODAL ─────────────────────────────────────────────────────────── */
.modal-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;
  opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(6px);
}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal-box{
  position:fixed;top:50%;left:50%;transform:translate(-50%,-54%);
  width:100%;max-width:400px;border-radius:22px;
  background:#fff;
  padding:2rem 1.75rem;z-index:501;
  box-shadow:0 32px 100px rgba(0,0,0,.35);
  transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .25s;
  opacity:0;pointer-events:none;
}
.modal-box.open{transform:translate(-50%,-50%);opacity:1;pointer-events:all}
.modal-close{
  position:absolute;top:.9rem;right:.9rem;
  width:30px;height:30px;border-radius:8px;border:none;
  background:#f1f5f9;color:#64748b;cursor:pointer;font-size:13px;
  display:flex;align-items:center;justify-content:center;transition:all .18s;
}
.modal-close:hover{background:#fee2e2;color:#dc2626}
.modal-logo{
  width:48px;height:48px;border-radius:13px;
  background:#fff;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:.9rem;box-shadow:0 6px 20px rgba(59,130,246,.28);
  overflow:hidden;
}
.modal-title{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;color:#0f172a;margin-bottom:2px}
.modal-sub{font-size:12px;color:#64748b;margin-bottom:1.25rem}
.form-group{margin-bottom:.9rem}
.form-group label{
  display:block;font-size:10.5px;font-weight:700;color:#374151;
  margin-bottom:5px;text-transform:uppercase;letter-spacing:.07em;
}
.input-wrap{position:relative;display:flex;align-items:center}
.form-input{
  width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;
  border-radius:10px;font-size:13px;font-family:'Inter',sans-serif;
  color:#0f172a;outline:none;transition:border .2s,box-shadow .2s;background:#f8fafc;
}
.form-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.form-input.has-eye{padding-right:40px}
.eye-btn{
  position:absolute;right:7px;top:50%;transform:translateY(-50%);
  width:28px;height:28px;border-radius:7px;background:none;border:none;
  cursor:pointer;color:#94a3b8;font-size:13px;
  display:flex;align-items:center;justify-content:center;transition:all .18s;
}
.eye-btn:hover{color:var(--blue)}
.err-box{
  background:#fff1f2;border:1px solid #fecdd3;color:#be123c;
  padding:9px 12px;border-radius:9px;font-size:12px;margin-bottom:.9rem;
  display:flex;align-items:center;gap:7px;
}
.login-submit{
  width:100%;padding:12px;border:none;border-radius:10px;
  background:linear-gradient(135deg,var(--blue),var(--teal));
  color:#fff;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;
  cursor:pointer;transition:all .25s;
  display:flex;align-items:center;justify-content:center;gap:7px;
  box-shadow:0 4px 16px rgba(59,130,246,.3);
  margin-top:.15rem;
}
.login-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,130,246,.42)}
.forgot-link{display:block;text-align:center;margin-top:.8rem;font-size:11px;color:#94a3b8;text-decoration:none}
.forgot-link:hover{color:var(--blue)}
.lock-box{
  display:flex;align-items:center;gap:12px;
  background:#fff7ed;border:1px solid #fed7aa;
  border-radius:10px;padding:11px 14px;margin-bottom:.9rem;
}
.lock-icon{
  width:36px;height:36px;border-radius:9px;flex-shrink:0;
  background:linear-gradient(135deg,#f97316,#ea580c);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:15px;
}
.lock-title{font-size:12px;font-weight:700;color:#9a3412;margin-bottom:2px}
.lock-msg{font-size:11px;color:#c2410c}
.login-submit:disabled{opacity:.45;cursor:not-allowed;transform:none!important}
.form-input:disabled{background:#f1f5f9;color:#94a3b8;cursor:not-allowed}
</style>



</head>
<body>

<div class="landing">
  <!-- Layered background -->
  <div class="bg-img"></div>
  <div class="bg-overlay"></div>
  <div class="bg-dots"></div>
  <div class="bg-glow-a"></div>
  <div class="bg-glow-b"></div>

  <!-- LEFT: Hero -->
  <div class="left-panel">

    <!-- Logo block -->
    <div class="lp-logo">
      <div class="logo-mark"><img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
      <div>
        <span class="logo-name">iBarangay</span>
        <span class="logo-loc"><i class="fas fa-location-dot"></i> Manila City</span>
      </div>
    </div>

    <!-- Accent line -->
    <div class="lp-divider"></div>

    <!-- Hero copy -->
    <div class="lp-hero">
      <p class="lp-eyebrow">Digital Community Portal</p>
      <h1 class="hero-title">Welcome to<br><span onclick="openLogin()">iBarangay 410</span></h1>
      <p class="hero-desc">
        Your community's digital home. Browse barangay census data,
        demographic statistics, and community reports — all in one
        transparent and accessible portal.
      </p>
    </div>

    <p class="lp-copy">&copy; <?=date('Y')?> Barangay 410 · Manila City · All rights reserved</p>
  </div>

  <!-- RIGHT: Auto-slideshow panel -->
  <div class="right-panel">
    <div class="right-card">
      <div class="rcard-head">
        <div>
          <div class="rcard-title" id="rSlideTitle">Community Overview</div>
          <div class="rcard-sub" id="rSlideSub">Barangay 410 census summary</div>
        </div>
        <div class="rdots" id="rDots"></div>
      </div>
      <div class="rcard-body">

        <!-- Slide 0: Census stats -->
        <div class="rslide active" id="rslide-0">
          <div class="stats-grid">
            <?php
              $sc=[
                [$pub_total,    'Total Residents',  '#3b82f6','fa-users'],
                [$pub_households,'Households',      '#14b8a6','fa-house-chimney'],
                [$pub_voters,   'Reg. Voters',      '#f59e0b','fa-check-to-slot'],
                [$pub_employed, 'Employed',         '#10b981','fa-briefcase'],
                [$pub_seniors,  'Senior Citizens',  '#8b5cf6','fa-person-cane'],
                [$pub_pwd,      'PWD Residents',    '#6366f1','fa-wheelchair'],
              ];
              foreach($sc as [$n,$l,$c,$i]):
            ?>
            <div class="stat-box">
              <div class="stat-box-icon" style="background:<?=$c?>"><i class="fas <?=$i?>"></i></div>
              <div>
                <div class="stat-box-num"><?=number_format($n)?></div>
                <div class="stat-box-lbl"><?=$l?></div>
              </div>
            </div>
            <?php endforeach;?>
          </div>
        </div>

        <!-- Slide 1: Gender -->
        <div class="rslide" id="rslide-1">
          <div class="chart-label">Population by Gender</div>
          <div class="chart-wrap-inner"><canvas id="chartGender"></canvas></div>
        </div>

        <!-- Slide 2: Age groups -->
        <div class="rslide" id="rslide-2">
          <div class="chart-label">Age Group Breakdown</div>
          <div class="chart-wrap-inner"><canvas id="chartAge"></canvas></div>
        </div>

        <!-- Slide 3: Employment -->
        <div class="rslide" id="rslide-3">
          <div class="chart-label">Employment Status</div>
          <div class="chart-wrap-inner"><canvas id="chartEmp"></canvas></div>
        </div>

        <!-- Slide 4: Civil status -->
        <div class="rslide" id="rslide-4">
          <div class="chart-label">Civil Status</div>
          <div class="chart-wrap-inner"><canvas id="chartCivil"></canvas></div>
        </div>

        <!-- Slide 5: Green Initiative -->
        <div class="rslide" id="rslide-5">
          <div class="proj-slide">
            <div class="proj-img">
              <img src="images/achieve1.png" alt="Green Initiative">
            </div>
            <div>
              <span class="proj-badge done"><i class="fas fa-circle-check"></i> Completed</span>
              <div class="proj-title">Green Initiative Blossoms</div>
              <p class="proj-desc">Barangay 410's Green Team launched a community tree-planting drive, transforming vacant lots into lush green spaces. Hundreds of residents joined hands to plant trees, clear pathways, and commit to a cleaner, more eco-friendly neighborhood for future generations.</p>
            </div>
            <div class="proj-footer"><i class="fas fa-calendar-check"></i> Completed · 2024 · Barangay 410 Environment Committee</div>
          </div>
        </div>

        <!-- Slide 6: Youth Empowerment -->
        <div class="rslide" id="rslide-6">
          <div class="proj-slide">
            <div class="proj-img">
              <img src="images/achievement2.png" alt="Youth Empowerment">
            </div>
            <div>
              <span class="proj-badge ongoing"><i class="fas fa-spinner"></i> Ongoing</span>
              <div class="proj-title">Youth Empowerment Program</div>
              <p class="proj-desc">A flagship initiative providing sports, educational, and leadership opportunities for the youth of Barangay 410. The program actively develops skills, builds confidence, and nurtures the next generation of community leaders who will drive positive change in District 4 and beyond.</p>
            </div>
            <div class="proj-footer"><i class="fas fa-calendar"></i> Ongoing · 2024–2025 · District 4 Youth Affairs</div>
          </div>
        </div>

        <!-- Slide 7: Digital Literacy -->
        <div class="rslide" id="rslide-7">
          <div class="proj-slide">
            <div class="proj-img">
              <img src="images/achievement3.png" alt="Digital Literacy">
            </div>
            <div>
              <span class="proj-badge ongoing"><i class="fas fa-spinner"></i> Ongoing</span>
              <div class="proj-title">Digital Literacy Workshops</div>
              <p class="proj-desc">In partnership with local tech advocates, Barangay 410 conducts free digital literacy sessions covering online safety, basic computer proficiency, and e-government services. These workshops equip residents of all ages with essential digital skills, helping bridge the gap in an increasingly connected world.</p>
            </div>
            <div class="proj-footer"><i class="fas fa-calendar"></i> Ongoing · 2025 · Barangay 410 × Tech Partners</div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ── LOGIN MODAL ─────────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="modalOverlay" onclick="closeLogin()"></div>
<div class="modal-box" id="modalBox">
  <button class="modal-close" onclick="closeLogin()"><i class="fas fa-xmark"></i></button>
  <div class="modal-logo"><img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
  <div class="modal-title">Login</div>
  <div class="modal-sub">iBarangay · Barangay 410, Manila City</div>
  <?php if($error === 'locked' || $isLocked):?>
  <div class="lock-box" id="lockBox">
    <div class="lock-icon"><i class="fas fa-shield-halved"></i></div>
    <div>
      <div class="lock-title">Too many failed attempts</div>
      <div class="lock-msg">Please wait <span id="lockCountdown"><?=gmdate('i:s',$lockSecsLeft)?></span> before trying again.</div>
    </div>
  </div>
  <?php elseif(!empty($error)):?>
  <div class="err-box"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div>
  <?php endif;?>
  <form action="admin.php" method="POST" id="loginForm">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" class="form-input" placeholder="Enter your username" <?=$isLocked?'disabled':''?> required autocomplete="off"/>
    </div>
    <div class="form-group">
      <label>Password</label>
      <div class="input-wrap">
        <input type="password" id="loginPw" name="password" class="form-input has-eye" placeholder="Enter your password" <?=$isLocked?'disabled':''?> required autocomplete="new-password"/>
        <button type="button" class="eye-btn" onclick="togglePw()"><i class="fas fa-eye" id="eyeIcon"></i></button>
      </div>
    </div>
    <button type="submit" class="login-submit" <?=$isLocked?'disabled':''?>><i class="fas fa-right-to-bracket"></i> Login</button>
  </form>
  <a href="#" class="forgot-link" onclick="openReset();return false">
    <i class="fas fa-lock" style="margin-right:4px"></i>Forgot your password? Request a reset.
  </a>
</div>

<!-- ── RESET REQUEST MODAL ────────────────────────────────────────────────── -->
<div class="modal-overlay" id="resetOverlay" onclick="closeReset()"></div>
<div class="modal-box" id="resetBox">
  <button class="modal-close" onclick="closeReset()"><i class="fas fa-xmark"></i></button>
  <div class="modal-logo" style="background:linear-gradient(135deg,#0f172a,#1e3a8a)"><i class="fas fa-key"></i></div>
  <div class="modal-title">Reset Password</div>
  <div class="modal-sub">Your request will be reviewed by the other administrator.</div>

  <?php if($resetStatus==='sent'):?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:11px 14px;border-radius:10px;font-size:12px;display:flex;align-items:center;gap:8px;margin-bottom:1rem">
    <i class="fas fa-circle-check"></i> Request sent! The other administrator will reset your password shortly.
  </div>
  <?php elseif($resetStatus==='already'):?>
  <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:11px 14px;border-radius:10px;font-size:12px;display:flex;align-items:center;gap:8px;margin-bottom:1rem">
    <i class="fas fa-clock"></i> A reset request for this account is already pending. Please wait.
  </div>
  <?php elseif($resetStatus==='notfound'):?>
  <div style="background:#fff1f2;border:1px solid #fecdd3;color:#be123c;padding:11px 14px;border-radius:10px;font-size:12px;display:flex;align-items:center;gap:8px;margin-bottom:1rem">
    <i class="fas fa-exclamation-circle"></i> Username not found or account is inactive.
  </div>
  <?php endif;?>

  <form action="admin.php" method="POST">
    <input type="hidden" name="action" value="request_reset">
    <div class="form-group">
      <label>Your Username</label>
      <input type="text" name="reset_username" class="form-input" placeholder="Enter your username" required autocomplete="off"/>
    </div>
    <button type="submit" class="login-submit"><i class="fas fa-paper-plane"></i> Send Reset Request</button>
  </form>
</div>

<script>
// ── Login modal ────────────────────────────────────────────────────────────
function openLogin(){
  document.getElementById('modalOverlay').classList.add('open');
  document.getElementById('modalBox').classList.add('open');
}
function closeLogin(){
  document.getElementById('modalOverlay').classList.remove('open');
  document.getElementById('modalBox').classList.remove('open');
}
function openReset(){
  document.getElementById('resetOverlay').classList.add('open');
  document.getElementById('resetBox').classList.add('open');
}
function closeReset(){
  document.getElementById('resetOverlay').classList.remove('open');
  document.getElementById('resetBox').classList.remove('open');
}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeLogin();closeReset();}});
<?php if(!empty($error)):?>
document.addEventListener('DOMContentLoaded',openLogin);
<?php endif;?>
<?php if(!empty($resetStatus)):?>
document.addEventListener('DOMContentLoaded',openReset);
<?php endif;?>

// ── Lockout countdown ─────────────────────────────────────────────────────
<?php if($isLocked && $lockSecsLeft > 0):?>
(function(){
  var secs = <?=(int)$lockSecsLeft?>;
  var el   = document.getElementById('lockCountdown');
  function fmt(s){
    var m=Math.floor(s/60),r=s%60;
    return (m<10?'0':'')+m+':'+(r<10?'0':'')+r;
  }
  var t = setInterval(function(){
    secs--;
    if(secs <= 0){
      clearInterval(t);
      location.reload();
    } else {
      if(el) el.textContent = fmt(secs);
    }
  }, 1000);
})();
<?php endif;?>

// ── Password toggle ────────────────────────────────────────────────────────
function togglePw(){
  var inp=document.getElementById('loginPw'),ico=document.getElementById('eyeIcon');
  var show=inp.type==='password';
  inp.type=show?'text':'password';
  ico.className=show?'fas fa-eye-slash':'fas fa-eye';
}

// ── Charts (all rendered at once, hidden via opacity) ──────────────────────
var C={blue:'#3b82f6',teal:'#14b8a6',amber:'#f59e0b',purple:'#8b5cf6',pink:'#ec4899',green:'#10b981',red:'#ef4444'};
var co={responsive:true,maintainAspectRatio:false};
var lgOpts={position:'bottom',labels:{color:'rgba(255,255,255,.6)',font:{size:10},padding:8,usePointStyle:true}};
new Chart(document.getElementById('chartGender'),{type:'doughnut',
  data:{labels:['Male','Female'],datasets:[{data:[<?=$pub_male?>,<?=$pub_female?>],backgroundColor:[C.blue,C.pink],borderWidth:0}]},
  options:{...co,plugins:{legend:lgOpts},cutout:'65%'}
});
new Chart(document.getElementById('chartAge'),{type:'doughnut',
  data:{labels:['Children (0–17)','Adults (18–59)','Seniors (60+)'],datasets:[{data:[<?=$pub_age_child?>,<?=$pub_age_adult?>,<?=$pub_age_senior?>],backgroundColor:[C.amber,C.blue,C.purple],borderWidth:0}]},
  options:{...co,plugins:{legend:lgOpts},cutout:'65%'}
});
new Chart(document.getElementById('chartEmp'),{type:'bar',
  data:{labels:['Employed','Not Employed'],datasets:[{data:[<?=$pub_employed?>,<?=$pub_unemployed?>],backgroundColor:[C.green,C.red],borderRadius:8,borderSkipped:false,barThickness:36}]},
  options:{...co,plugins:{legend:{display:false}},scales:{
    x:{grid:{display:false},ticks:{color:'rgba(255,255,255,.5)',font:{size:11}}},
    y:{grid:{color:'rgba(255,255,255,.07)'},ticks:{color:'rgba(255,255,255,.5)',font:{size:10}},beginAtZero:true}
  }}
});
new Chart(document.getElementById('chartCivil'),{type:'doughnut',
  data:{labels:['Single','Married','Widowed','Other'],datasets:[{data:[<?=$pub_single?>,<?=$pub_married?>,<?=$pub_widowed?>,<?=max(0,$pub_other_cs)?>],backgroundColor:[C.blue,C.teal,C.amber,C.purple],borderWidth:0}]},
  options:{...co,plugins:{legend:lgOpts},cutout:'65%'}
});

// ── Right panel slideshow ──────────────────────────────────────────────────
var rMeta=[
  {title:'Community Overview',       sub:'Barangay 410 census summary'},
  {title:'Population by Gender',     sub:'Male vs Female distribution'},
  {title:'Age Group Breakdown',      sub:'Children, Adults & Seniors'},
  {title:'Employment Status',        sub:'Employed vs not-employed residents'},
  {title:'Civil Status',             sub:'Marital status of residents'},
  {title:'Barangay Projects',        sub:'Road Rehabilitation — Completed 2023'},
  {title:'Barangay Projects',        sub:'Health Center Upgrade — Ongoing'},
  {title:'Barangay Projects',        sub:'Livelihood Training — Planned Q4 2025'},
];
var rIdx=0,rSlides=document.querySelectorAll('.rslide'),rDotsEl=document.getElementById('rDots');
rMeta.forEach(function(_,i){
  var d=document.createElement('button');
  d.className='rdot'+(i===0?' active':'');
  d.onclick=function(){rGoTo(i);};
  rDotsEl.appendChild(d);
});
function rGoTo(n){
  rSlides[rIdx].classList.remove('active');
  rIdx=(n+rMeta.length)%rMeta.length;
  rSlides[rIdx].classList.add('active');
  document.querySelectorAll('.rdot').forEach(function(d,i){d.classList.toggle('active',i===rIdx);});
  document.getElementById('rSlideTitle').textContent=rMeta[rIdx].title;
  document.getElementById('rSlideSub').textContent=rMeta[rIdx].sub;
}
setInterval(function(){rGoTo(rIdx+1);},4500);
</script>
</body>
</html>
