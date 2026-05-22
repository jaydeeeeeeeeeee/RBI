<?php
// admin/residents.php - HIDDEN FOR DATA PRIVACY
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_dark_mode; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Barangay 410 Zone 42 (Data Privacy Mode)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #dc2626;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --warning: #f59e0b;
            --success: #10b981;
        }

        [data-theme="dark"] {
            --primary: #3b82f6;
            --primary-light: #60a5fa;
            --secondary: #ef4444;
            --surface: #1e293b;
            --surface-2: #0f172a;
            --border: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--surface-2);
            color: var(--text-primary);
            line-height: 1.5;
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .privacy-container {
            max-width: 600px;
            margin: 2rem;
            width: 100%;
        }

        .privacy-card {
            background: var(--surface);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
            padding: 3rem 2rem;
            border: 1px solid var(--border);
        }

        .privacy-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .privacy-icon i {
            font-size: 3rem;
            color: white;
        }

        .privacy-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .privacy-badge {
            display: inline-block;
            background: var(--warning);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .privacy-message {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .privacy-note {
            background: var(--surface-2);
            border-radius: 12px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }

        .privacy-note p {
            margin: 0.5rem 0;
            font-size: 0.8rem;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
            color: white;
        }

        .btn-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--success);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            margin-left: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-dashboard:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }

        .footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .privacy-card {
                padding: 2rem 1.5rem;
            }
            .btn-dashboard {
                margin-left: 0;
                margin-top: 0.5rem;
            }
            .button-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="privacy-container">
        <div class="privacy-card">
            <div class="privacy-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            
            <span class="privacy-badge">
                <i class="fas fa-lock me-1"></i> Data Privacy Mode
            </span>
            
            <h1 class="privacy-title">Residents Data is Hidden</h1>
            
            <p class="privacy-message">
                <strong>Data Privacy Act of 2012 (RA 10173)</strong>, residents' data is temporarily hidden for viewing purposes.
            </p>
            
            <div class="privacy-note">
                <p><i class="fas fa-info-circle me-2" style="color: var(--primary);"></i> <strong>For Viewing/Demo Purposes Only</strong></p>
                <p>This page will be restored once the system is ready for full deployment with proper data privacy measures in place.</p>
                <p class="mt-2"><i class="fas fa-tachometer-alt me-2" style="color: var(--success);"></i> For now, please go back to the Dashboard.</p>
            </div>
            
            <div class="button-group">
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="dashboard.php" class="btn-dashboard">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
            </div>
        </div>
        
        <div class="footer">
            <i class="fas fa-shield-alt me-1"></i> Barangay 410 Zone 42 | Data Privacy Compliant
        </div>
    </div>
</body>
</html>