<?php
$_config = __DIR__ . '/config.php';
if (file_exists($_config)) { require_once $_config; }

$host     = defined('DB_HOST') ? DB_HOST : "localhost";
$port     = defined('DB_PORT') ? DB_PORT : 3306;
$user     = defined('DB_USER') ? DB_USER : "root";
$password = defined('DB_PASS') ? DB_PASS : "";
$database = defined('DB_NAME') ? DB_NAME : "projectrbi";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($host, $user, $password, $database, $port);
// OOP alias — lets any file use $conn->query() or $conn->prepare() interchangeably
// mysqli_connect() returns a mysqli object, so both styles work on the same handle.

if (!$conn) {
    $db_errno = mysqli_connect_errno();
    $db_error = mysqli_connect_error();
    $is_refused = (strpos($db_error, 'refused') !== false || $db_errno === 2002 || $db_errno === 2003);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Database Error – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:#fff;border-radius:18px;box-shadow:0 4px 30px rgba(0,0,0,.08);max-width:640px;width:100%;overflow:hidden}
.card-top{background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:2rem;text-align:center}
.err-icon{width:64px;height:64px;background:rgba(244,63,94,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;border:2px solid rgba(244,63,94,.3)}
.card-title{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:6px}
.card-sub{font-size:13px;color:rgba(255,255,255,.5)}
.card-body{padding:1.75rem}
.step{display:flex;gap:12px;margin-bottom:1rem;padding:14px;background:#f8fafc;border-radius:10px;border-left:3px solid #3b82f6}
.step-num{width:26px;height:26px;border-radius:50%;background:#3b82f6;color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.step-text{font-size:13px;color:#374151;line-height:1.6}
.step-text strong{color:#0f172a;display:block;margin-bottom:2px}
.sql-box{background:#0f172a;border-radius:10px;padding:14px 16px;margin-top:1rem;font-family:'Courier New',monospace;font-size:12px;color:#7dd3fc;line-height:1.8;white-space:pre;overflow-x:auto}
.sql-comment{color:#475569}
.section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:1.25rem 0 .75rem;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
.err-detail{background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;padding:10px 14px;font-size:12px;color:#be123c;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px}
.back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#0f172a;color:#fff;border-radius:9px;text-decoration:none;font-size:13px;font-weight:600;margin-top:1.25rem}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="err-icon"><i class="fas fa-database" style="color:#f43f5e;font-size:24px"></i></div>
    <div class="card-title">Database Connection Error</div>
    <div class="card-sub">ProjectRBI · Barangay 410</div>
  </div>
  <div class="card-body">
    <div class="err-detail">
      <i class="fas fa-exclamation-circle"></i>
      <span>Error <?=$db_errno?>: <?=htmlspecialchars($db_error)?></span>
    </div>

    <?php if($is_refused): ?>
    <div class="section-label">How to fix</div>
    <div class="step">
      <div class="step-num">1</div>
      <div class="step-text"><strong>Start MySQL in XAMPP</strong>Open the XAMPP Control Panel and click <strong>Start</strong> next to <em>MySQL</em>. The status should turn green.</div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-text"><strong>Open phpMyAdmin</strong>Go to <a href="http://localhost/phpmyadmin" target="_blank" style="color:#3b82f6">localhost/phpmyadmin</a> and run the setup SQL below if the database doesn't exist yet.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-text"><strong>Reload this page</strong>Once MySQL is running, refresh the page and the system will connect automatically.</div>
    </div>
    <?php endif; ?>

    <div class="section-label">How to set up the database</div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-text"><strong>Run the setup SQL file</strong>Open phpMyAdmin, click the <em>SQL</em> tab, then copy and paste the contents of <strong>projectrbi.sql</strong> found in the project root folder. Click <em>Go</em>. This creates all tables and a default admin account.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-text"><strong>Default login credentials</strong>After running the SQL, use <strong>Username:</strong> admin &nbsp;|&nbsp; <strong>Password:</strong> admin123 &nbsp;(Role: Captain). Change the password after first login via Manage Accounts.</div>
    </div>

    <a href="admin.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
  </div>
</div>
</body>
</html>
    <?php
    exit();
}
?>