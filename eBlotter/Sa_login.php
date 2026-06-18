<?php
/**
 * ProjectRBI — Super Admin Secret Login
 * Access only via: sa_login.php?token=brgy410-super-9x7kqm2z
 * Anyone without the correct token gets a 404.
 */
session_start();
require_once __DIR__ . '/superadmin_config.php';
require_once __DIR__ . '/Admin_DB.php';

// ── Token gate — wrong/missing token = 404 ───────────────────────────────
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!hash_equals(SA_URL_TOKEN, $token)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>
    <body style="font-family:sans-serif;text-align:center;padding:4rem;">
    <h1>404 Not Found</h1><p>The page you requested could not be found.</p>
    </body></html>';
    exit();
}

// ── Already logged in as super admin ────────────────────────────────────
if (isset($_SESSION['sa_logged_in']) && $_SESSION['sa_logged_in'] === true) {
    $elapsed = time() - ($_SESSION['sa_login_time'] ?? 0);
    if ($elapsed < SA_SESSION_TIMEOUT * 60) {
        header('Location: sa_panel.php?token=' . SA_URL_TOKEN);
        exit();
    }
    // Session expired
    session_destroy(); session_start();
}

// ── Brute force protection ────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS sa_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    attempts INT DEFAULT 1,
    locked_until DATETIME DEFAULT NULL,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("DELETE FROM sa_login_attempts WHERE
    (locked_until IS NOT NULL AND locked_until < NOW())
    OR last_attempt < NOW() - INTERVAL 15 MINUTE");

$ip     = $_SERVER['REMOTE_ADDR'];
$ip_e   = $conn->real_escape_string($ip);
$lr     = $conn->query("SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS secs_left FROM sa_login_attempts WHERE ip_address='$ip_e'");
$lock   = $lr && $lr->num_rows ? $lr->fetch_assoc() : null;
$locked = $lock && (int)($lock['secs_left'] ?? 0) > 0;
$secs_left = $locked ? (int)$lock['secs_left'] : 0;

$error = '';

// ── Handle POST login ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $u = trim($_POST['sa_username'] ?? '');
    $p = $_POST['sa_password'] ?? '';

    if ($u === SA_USERNAME && password_verify($p, SA_PASSWORD_HASH)) {
        // Success
        $conn->query("DELETE FROM sa_login_attempts WHERE ip_address='$ip_e'");
        $_SESSION['sa_logged_in']   = true;
        $_SESSION['sa_login_time']  = time();
        $_SESSION['sa_login_ip']    = $ip;

        // Log it
        $conn->query("INSERT INTO access_log (event_type, detail, performed_by, ip_address)
            VALUES ('SA_LOGIN', 'Super Admin logged in', 'superadmin', '$ip_e')");

        header('Location: sa_panel.php?token=' . SA_URL_TOKEN);
        exit();
    } else {
        // Failed
        $attempts = ($lock ? (int)$lock['attempts'] : 0) + 1;
        $lockSQL  = $attempts >= 3 ? ", locked_until = NOW() + INTERVAL 15 MINUTE" : ", locked_until = NULL";
        $conn->query("INSERT INTO sa_login_attempts (ip_address, attempts, last_attempt)
            VALUES ('$ip_e', 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()$lockSQL");

        $conn->query("INSERT INTO access_log (event_type, detail, performed_by, ip_address)
            VALUES ('SA_FAIL', 'Failed Super Admin login attempt', 'unknown', '$ip_e')");

        $remaining = max(0, 3 - $attempts);
        $error = $attempts >= 3
            ? 'locked'
            : "Incorrect credentials. {$remaining} attempt(s) remaining.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>System Access</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#060d1a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.card{background:#0d1526;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 32px 80px rgba(0,0,0,.6)}
.icon-wrap{width:52px;height:52px;border-radius:14px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.25);display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem}
.icon-wrap i{color:#f87171;font-size:22px}
h1{font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:800;color:#fff;margin-bottom:.3rem}
.sub{font-size:12px;color:rgba(255,255,255,.3);margin-bottom:1.75rem}
.field{margin-bottom:1rem}
.field label{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);margin-bottom:.4rem}
.field input{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:10px 14px;color:#fff;font-size:13px;font-family:'Inter',sans-serif;outline:none;transition:border .2s}
.field input:focus{border-color:rgba(239,68,68,.5);box-shadow:0 0 0 3px rgba(239,68,68,.1)}
.field input::placeholder{color:rgba(255,255,255,.2)}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5;padding:9px 12px;border-radius:9px;font-size:12px;margin-bottom:1rem;display:flex;align-items:center;gap:8px}
.lock-box{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);color:#fbbf24;padding:12px;border-radius:10px;font-size:12px;margin-bottom:1rem;text-align:center}
.lock-box .countdown{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;display:block;margin-top:4px}
.btn{width:100%;padding:12px;background:linear-gradient(135deg,#dc2626,#991b1b);border:none;border-radius:10px;color:#fff;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:.25rem}
.btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 8px 24px rgba(220,38,38,.35)}
.btn:disabled{opacity:.4;cursor:not-allowed}
.notice{margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,.06);font-size:11px;color:rgba(255,255,255,.2);text-align:center;line-height:1.6}
</style>
</head>
<body>
<div class="card">
  <div class="icon-wrap"><i class="fas fa-shield-halved"></i></div>
  <h1>System Access</h1>
  <p class="sub">Restricted area &mdash; authorized personnel only</p>

  <?php if ($error === 'locked' || $locked): ?>
  <div class="lock-box">
    <i class="fas fa-lock"></i> Access temporarily suspended<br>
    <span class="countdown" id="saCountdown"><?= gmdate('i:s', $secs_left) ?></span>
  </div>
  <?php elseif ($error): ?>
  <div class="err"><i class="fas fa-exclamation-triangle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="field">
      <label>Access ID</label>
      <input type="text" name="sa_username" placeholder="Enter access ID" <?= $locked ? 'disabled' : '' ?> required autocomplete="off" autocapitalize="none"/>
    </div>
    <div class="field">
      <label>Passphrase</label>
      <input type="password" name="sa_password" placeholder="Enter passphrase" <?= $locked ? 'disabled' : '' ?> required autocomplete="new-password"/>
    </div>
    <button type="submit" class="btn" <?= $locked ? 'disabled' : '' ?>>
      <i class="fas fa-right-to-bracket"></i> Authenticate
    </button>
  </form>

  <p class="notice">This access point is monitored. All login attempts are logged with IP address and timestamp.</p>
</div>

<?php if ($locked && $secs_left > 0): ?>
<script>
(function(){
  var s = <?= (int)$secs_left ?>;
  var el = document.getElementById('saCountdown');
  var t = setInterval(function(){
    s--;
    if(s <= 0){ clearInterval(t); location.reload(); return; }
    var m = Math.floor(s/60), r = s%60;
    el.textContent = (m<10?'0':'')+m+':'+(r<10?'0':'')+r;
  }, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>