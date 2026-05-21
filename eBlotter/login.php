<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

if (isset($_SESSION['eb_user'])) { header('Location: eblotter_home.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM eb_users WHERE username=? AND is_active=1 LIMIT 1");
        $stmt->bind_param('s', $username); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['eb_user'] = ['id'=>$user['id'],'username'=>$user['username'],'full_name'=>$user['full_name'],'role'=>$user['role']];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $s2 = $conn->prepare("INSERT INTO eb_audit_log (user_id,username,full_name,action,details,ip_address) VALUES (?,?,?,'LOGIN','Login successful',?)");
            $s2->bind_param('isss',$user['id'],$user['username'],$user['full_name'],$ip); $s2->execute();
            header('Location: eblotter_home.php'); exit();
        } else { $error = 'Incorrect username or password.'; }
    } else { $error = 'Please enter your username and password.'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>eBlotter — Login | Brgy 409</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:"DM Sans",sans-serif;}

body {
  display:flex;
  min-height:100vh;
  background:#f8fafc;
}

/* LEFT PANEL — branding */
.login-left {
  flex: 1.1;
  background: linear-gradient(145deg, #0f172a 0%, #1b263b 45%, #1a3a5c 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 2.5rem;
  position: relative;
  overflow: hidden;
}
.login-left::before {
  content: '';
  position: absolute; inset: 0;
  background: url('../eBlotter/images/Barangay_officials_409_1.png') right/cover no-repeat;
  opacity: .12;
}
.login-left::after {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(ellipse at 30% 70%, rgba(37,99,235,.18) 0%, transparent 60%);
}
.left-inner {
  position: relative; z-index: 1;
  text-align: center;
  display: flex; flex-direction: column; align-items: center; gap: 1.5rem;
}
.brgy-logo {
  width: 110px; height: 110px;
  background: rgba(255,255,255,.08);
  border: 2px solid rgba(255,255,255,.15);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(4px);
  overflow: hidden;
}
.brgy-logo img { width: 110px; height: 110px; align-items: center; justify-content: center; }
.left-title {
  font-family: "DM Serif Display", serif;
  font-size: 2rem;
  color: #fff;
  line-height: 1.1;
}
.left-sub {
  font-size: .85rem;
  color: rgba(255,255,255,.55);
  line-height: 1.6;
  max-width: 280px;
}
.left-badges {
  display: flex; flex-direction: column; gap: .6rem;
  margin-top: .5rem; width: 100%; max-width: 240px;
  text-align: left;
}
.role-badge {
  display: flex; align-items: center; gap: .75rem;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 10px;
  padding: .6rem 1rem;
  color: rgba(255,255,255,.85);
  font-size: .82rem; font-weight: 600;
}
.role-badge i { font-size: .95rem; }
.badge-cw i  { color: #fbbf24; }
.badge-sec i { color: #34d399; }
.badge-kag i { color: #60a5fa; }
.role-desc { font-size: .7rem; color: rgba(255,255,255,.4); font-weight: 400; margin-top: .1rem; }

.divider-line {
  width: 40px; height: 2px; background: rgba(255,255,255,.15); border-radius: 2px; margin: .5rem 0;
}

/* RIGHT PANEL — form */
.login-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  background: #f8fafc;
}
.login-card {
  width: 100%;
  max-width: 380px;
}
.login-card .card-header {
  margin-bottom: 2rem;
}
.login-card .card-header h2 {
  font-family: "DM Serif Display", serif;
  font-size: 1.65rem;
  color: #0f172a;
  margin-bottom: .3rem;
}
.login-card .card-header p {
  font-size: .83rem;
  color: #94a3b8;
}

.field { margin-bottom: 1.1rem; }
.field label {
  display: block; font-size: .75rem; font-weight: 700;
  color: #475569; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .4rem;
}
.field-wrap {
  position: relative;
}
.field-wrap i {
  position: absolute; left: .9rem; top: 50%; transform: translateY(-50%);
  color: #94a3b8; font-size: .9rem; pointer-events: none;
}
.field-wrap input {
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: .65rem .9rem .65rem 2.4rem;
  font-family: inherit; font-size: .92rem; color: #0f172a;
  background: #fff;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.field-wrap input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

.btn-login {
  width: 100%;
  background: linear-gradient(135deg, #1b263b 0%, #2563eb 100%);
  color: #fff;
  border: none; border-radius: 999px;
  padding: .8rem 1.5rem;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer;
  transition: opacity .2s, transform .1s;
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  margin-top: .5rem;
}
.btn-login:hover { opacity: .88; }
.btn-login:active { transform: scale(.98); }

.err {
  background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
  border-radius: 10px; padding: .7rem 1rem; font-size: .84rem;
  margin-bottom: 1.1rem; display: flex; align-items: center; gap: .5rem;
}

.system-tag {
  margin-top: 2rem;
  padding-top: 1.25rem;
  border-top: 1px solid #e2e8f0;
  text-align: center;
  font-size: .72rem;
  color: #94a3b8;
}
.system-tag strong { color: #475569; }

@media(max-width: 700px) {
  body { flex-direction: column; }
  .login-left { flex: none; padding: 2.5rem 1.5rem; min-height: 260px; }
  .left-badges { display: none; }
  .brgy-logo { width: 80px; height: 80px; }
  .brgy-logo img { width: 64px; height: 64px; }
  .left-title { font-size: 1.4rem; }
  .login-right { padding: 2rem 1.25rem; }
}
</style>
</head>
<body>

<!-- LEFT: Branding Panel -->
<div class="login-left">
  <div class="left-inner">
    <div class="brgy-logo">
      <img src="../eBlotter/images/Barangay_logo_409.png" alt="Barangay 409 Logo">
    </div>
    <div class="left-title">Barangay 409<br>Zone 42</div>
    <div class="divider-line"></div>
    <p class="left-sub">Sampaloc, City of Manila, District IV<br>eBlotter Case Management System</p>

    <div class="left-badges">
      <div class="role-badge badge-cw">
        <i class="fas fa-crown"></i>
        <div>
          <div>Chairperson</div>
          <div class="role-desc">Full system access</div>
        </div>
      </div>
      <div class="role-badge badge-sec">
        <i class="fas fa-user-tie"></i>
        <div>
          <div>Secretary</div>
          <div class="role-desc">Records & documents</div>
        </div>
      </div>
      <div class="role-badge badge-kag">
        <i class="fas fa-user"></i>
        <div>
          <div>Kagawad</div>
          <div class="role-desc">View & add records</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- RIGHT: Login Form -->
<div class="login-right">
  <div class="login-card">
    <div class="card-header">
      <h2>Welcome</h2>
      <p>Sign in to access the eBlotter system</p>
    </div>

    <?php if($error): ?>
    <div class="err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Username</label>
        <div class="field-wrap">
          <i class="fas fa-user"></i>
          <input type="text" name="username" placeholder="Enter your username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username">
        </div>
      </div>
      <div class="field">
        <label>Password</label>
        <div class="field-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password">
        </div>
      </div>
      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <div class="system-tag">
      <strong>eBlotter</strong> — Barangay 409 Case Management System<br>
      Katarungang Pambarangay | City of Manila
    </div>
  </div>
</div>

</body>
</html>