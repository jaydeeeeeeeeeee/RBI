<?php
// forgot_password.php - SAME VIBE AS INDEX
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

$barangay_name = "Barangay 410 Zone 42";
$barangay_address = "District IV, Sampaloc, Manila";
$barangay_contact = "Tel. #: 522-2991";
$barangay_logo = "assets/images/barangay-logo.webp";
$manila_logo = "assets/images/manila-logo.webp";
$barangay_captain = "P/B MICHAEL JOHN M. REGALA";

$page_title = "Forgot Password - " . $barangay_name;

$message = '';
$error = '';
$success = false;
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error = 'Please enter your username.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token to database
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$reset_token, $expires, $user['id']]);
            
            // Generate reset link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $reset_link = $protocol . $host . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $reset_token;
            
            $success = true;
            $message = "Password reset link generated successfully!";
        } else {
            $error = "Username not found. Please contact the administrator.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gov-navy: #0a2447;
            --gov-navy-dark: #061633;
            --gov-gold: #c9a227;
            --gov-gold-light: #e8c547;
            --gov-red: #a4161a;
            --gov-cream: #faf7f0;
            --gov-border: #d9d3c2;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            margin: 0;
            background:
                linear-gradient(135deg, rgba(10,36,71,0.92), rgba(6,22,51,0.95)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60' viewBox='0 0 60 60'%3E%3Cpath d='M30 0l3 27 27 3-27 3-3 27-3-27L0 30l27-3z' fill='%23c9a227' fill-opacity='0.04'/%3E%3C/svg%3E");
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1a1a1a;
        }

        .login-shell {
            width: 100%;
            max-width: 460px;
        }

        .flag-bar {
            height: 6px;
            display: flex;
            border-radius: 4px 4px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .flag-bar span { flex: 1; }
        .flag-bar .blue { background: #0038a8; }
        .flag-bar .red { background: var(--gov-red); }
        .flag-bar .gold { background: var(--gov-gold); flex: 0.3; }

        .login-card {
            background: var(--gov-cream);
            border: 1px solid var(--gov-border);
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 25px 60px -15px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(180deg, #ffffff 0%, var(--gov-cream) 100%);
            padding: 28px 32px 22px;
            text-align: center;
            border-bottom: 2px solid var(--gov-gold);
            position: relative;
        }

        .logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 22px;
            margin-bottom: 14px;
        }
        .logos img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
        }

        .republic {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 3px;
            color: var(--gov-red);
            text-transform: uppercase;
            margin: 0 0 4px;
        }

        .barangay-name {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 900;
            color: var(--gov-navy);
            letter-spacing: 1px;
            margin: 0 0 8px;
            line-height: 1.1;
        }

        .meta-line {
            font-size: 12px;
            color: #555;
            margin: 2px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .meta-line i { color: var(--gov-gold); font-size: 11px; }

        .captain-strip {
            margin-top: 14px;
            padding: 8px 14px;
            background: var(--gov-navy);
            color: var(--gov-gold-light);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border-radius: 3px;
            display: inline-block;
        }

        .login-body {
            padding: 30px 32px 28px;
        }

        .form-title {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--gov-navy);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 0 0 22px;
            position: relative;
        }
        .form-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 2px;
            background: var(--gov-gold);
            margin: 8px auto 0;
        }

        .field { margin-bottom: 16px; }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--gov-navy);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gov-navy);
            opacity: 0.5;
        }
        .field input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid var(--gov-border);
            background: #fff;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            color: #1a1a1a;
            transition: all 0.2s;
        }
        .field input:focus {
            outline: none;
            border-color: var(--gov-navy);
            box-shadow: 0 0 0 3px rgba(10,36,71,0.1);
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(180deg, var(--gov-navy) 0%, var(--gov-navy-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 3px;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(10,36,71,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(10,36,71,0.4);
            background: linear-gradient(180deg, #0d2c55 0%, var(--gov-navy) 100%);
        }
        .btn-login::before, .btn-login::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gov-gold);
            opacity: 0.5;
            max-width: 30px;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 12px 14px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-left: 4px solid var(--gov-red);
            color: #7f1d1d;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reset-link-box {
            background: #e8f0fe;
            border: 1px solid var(--gov-gold);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            word-break: break-all;
        }

        .reset-link-box a {
            color: var(--gov-navy);
            font-weight: 600;
            text-decoration: none;
        }

        .reset-link-box a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 18px;
        }
        .back-link a {
            color: var(--gov-navy);
            font-size: 12px;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px dotted var(--gov-navy);
            padding-bottom: 1px;
        }
        .back-link a:hover { color: var(--gov-red); border-color: var(--gov-red); }

        .system-footer {
            text-align: center;
            color: rgba(255,255,255,0.7);
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 20px;
            font-weight: 500;
        }
        .system-footer i { color: var(--gov-gold); margin-right: 6px; }

        @media (max-width: 480px) {
            .login-header, .login-body { padding-left: 22px; padding-right: 22px; }
            .barangay-name { font-size: 18px; }
            .logos img { width: 64px; height: 64px; }
        }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="flag-bar">
        <span class="blue"></span>
        <span class="red"></span>
        <span class="gold"></span>
    </div>

    <div class="login-card">
        <div class="login-header">
            <div class="logos">
                <img src="<?php echo $manila_logo; ?>" alt="Manila Logo">
                <img src="<?php echo $barangay_logo; ?>" alt="Barangay Logo">
            </div>
            <p class="republic">Republic of the Philippines</p>
            <h1 class="barangay-name"><?php echo strtoupper($barangay_name); ?></h1>
            <p class="meta-line"><i class="bi bi-geo-alt-fill"></i> <?php echo $barangay_address; ?></p>
            <p class="meta-line"><i class="bi bi-telephone-fill"></i> <?php echo $barangay_contact; ?></p>
            <div class="captain-strip">
                <i class="bi bi-shield-fill-check"></i> &nbsp;<?php echo $barangay_captain; ?>
            </div>
        </div>

        <div class="login-body">
            <h2 class="form-title">Reset Password</h2>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success && $reset_link): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?php echo $message; ?></span>
                </div>
                <div class="reset-link-box">
                    <p><strong><i class="bi bi-link-45deg"></i> Password Reset Link:</strong></p>
                    <p><a href="<?php echo $reset_link; ?>" target="_blank"><?php echo $reset_link; ?></a></p>
                    <p class="text-muted mt-2" style="font-size: 11px; margin-bottom: 0;">
                        <i class="bi bi-clock-history"></i> This link will expire in 1 hour.
                    </p>
                </div>
                <div class="back-link">
                    <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
                </div>
            <?php elseif (!$success): ?>
                <form method="POST" action="">
                    <div class="field">
                        <label for="username">Username or Email</label>
                        <div class="input-wrap">
                            <i class="bi bi-person-fill"></i>
                            <input type="text" id="username" name="username" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Generate Reset Link</button>
                </form>
                <div class="back-link">
                    <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="system-footer">
        <i class="bi bi-shield-lock-fill"></i>
        <?php echo $barangay_name; ?> Official Management System
    </p>
</div>
</body>
</html>