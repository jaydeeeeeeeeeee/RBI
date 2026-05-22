<?php
// reset_password.php - SAME VIBE AS INDEX
require_once 'config/database.php';
require_once 'includes/functions.php';

$barangay_name = "Barangay 410 Zone 42";
$barangay_address = "District IV, Sampaloc, Manila";
$barangay_contact = "Tel. #: 522-2991";
$barangay_logo = "assets/images/barangay-logo.webp";
$manila_logo = "assets/images/manila-logo.webp";
$barangay_captain = "P/B MICHAEL JOHN M. REGALA";

$error = '';
$success = false;
$message = '';
$token = $_GET['token'] ?? '';
$user = null;

// Verify token
if ($token) {
    $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token && $user && !$error) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password != $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($update->execute([$hashed_password, $user['id']])) {
            $success = true;
            $message = "Password reset successfully! You can now login with your new password.";
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Barangay 410</title>
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

        .user-info {
            background: #e8f0fe;
            border-left: 4px solid var(--gov-gold);
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
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
            <h2 class="form-title">Create New Password</h2>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?php echo $message; ?></span>
                </div>
                <div class="back-link">
                    <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
                </div>
            <?php elseif ($user && !$error): ?>
                <div class="user-info">
                    <i class="bi bi-person-circle"></i> Resetting password for: <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                </div>
                
                <form method="POST" action="">
                    <div class="field">
                        <label for="password">New Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-check-circle-fill"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Reset Password</button>
                </form>
                
                <div class="back-link">
                    <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
                </div>
            <?php else: ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Invalid reset link. Please request a new one.</span>
                </div>
                <div class="back-link">
                    <a href="forgot_password.php">Request new reset link</a>
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