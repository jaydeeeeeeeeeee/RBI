<?php
// admin/requests.php - COMPLETE WITH PDF VIEW BUTTON
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Certificate Requests - Barangay 410 Zone 42";

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');
$current_datetime = date('Y-m-d H:i:s');

// Handle Approve
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE certificate_requests SET status = 'Approved', approved_at = ? WHERE id = ?");
        $stmt->execute([$current_datetime, $id]);
        header('Location: requests.php?success=approved');
        exit();
    }
}

// Handle Reject
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE certificate_requests SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: requests.php?success=rejected');
        exit();
    }
}

// Handle Release
if (isset($_GET['release'])) {
    $id = (int)$_GET['release'];
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE certificate_requests SET status = 'Released' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: requests.php?success=released');
        exit();
    }
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $stmt = $pdo->prepare("
        SELECT cr.*, r.first_name, r.last_name, r.perm_address AS address, ct.template_name
        FROM certificate_requests cr
        JOIN residents r ON cr.resident_id = r.id
        JOIN certificate_templates ct ON cr.template_id = ct.id
        WHERE r.first_name LIKE ? OR r.last_name LIKE ? OR ct.template_name LIKE ? OR cr.purpose LIKE ? OR cr.status LIKE ?
        ORDER BY cr.requested_at DESC
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
    $requests = $stmt->fetchAll();
    $search_performed = true;
} else {
    $requests = $pdo->query("
        SELECT cr.*, r.first_name, r.last_name, r.perm_address AS address, ct.template_name
        FROM certificate_requests cr
        JOIN residents r ON cr.resident_id = r.id
        JOIN certificate_templates ct ON cr.template_id = ct.id
        ORDER BY cr.requested_at DESC
    ")->fetchAll();
    $search_performed = false;
}

$total_requests = count($requests);
$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_dark_mode; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --warning: #f59e0b;
            --success: #10b981;
            --info: #3b82f6;
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
            --warning: #f59e0b;
            --success: #10b981;
            --info: #3b82f6;
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
        }

        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--surface);
            box-shadow: var(--shadow-lg);
            position: fixed;
            height: 100vh;
            z-index: 40;
            border-right: 1px solid var(--border);
            transition: background 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            text-align: center;
        }

        .logos {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-bottom: 0.75rem;
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .sidebar-subtitle {
            font-size: 0.7rem;
            opacity: 0.9;
            margin: 0;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.7rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--surface-2);
            color: var(--primary);
            border-left-color: var(--primary);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            flex: 1;
        }

        .header {
            background: var(--surface);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: background 0.3s ease;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            flex: 1;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0.25rem 0 0 0;
        }

        .search-container {
            position: relative;
            width: 280px;
        }

        .search-box {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.8rem;
            background: var(--surface);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .user-dropdown {
            position: relative;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--surface-2);
            border-radius: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-menu:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .dropdown-arrow {
            font-size: 0.7rem;
            transition: transform 0.2s ease;
        }

        .user-menu.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 180px;
            background: var(--surface);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            z-index: 50;
            display: none;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .user-dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            padding: 0.7rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: background 0.2s ease;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: var(--surface-2);
        }

        .content-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.5rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert {
            margin: 1rem 1.5rem 0;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-warning {
            background: #fed7aa;
            color: #9b2c1d;
            border: 1px solid #fdba74;
        }

        [data-theme="dark"] .alert-success {
            background: #064e3b;
            color: #d1fae5;
            border: 1px solid #065f46;
        }

        [data-theme="dark"] .alert-warning {
            background: #451a03;
            color: #fed7aa;
            border: 1px solid #854d0e;
        }

        .search-summary {
            margin: 1rem 1.5rem;
            padding: 0.75rem 1rem;
            background: var(--surface-2);
            border-radius: 10px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-container {
            padding: 0 1.5rem 1.5rem;
            overflow-x: auto;
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .requests-table th {
            text-align: left;
            padding: 0.75rem 0.5rem;
            background: var(--surface-2);
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }

        .requests-table td {
            padding: 0.75rem 0.5rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .requests-table tr:hover td {
            background: var(--surface-2);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        [data-theme="dark"] .badge-warning {
            background: #451a03;
            color: #fef3c7;
        }

        [data-theme="dark"] .badge-success {
            background: #064e3b;
            color: #d1fae5;
        }

        [data-theme="dark"] .badge-danger {
            background: #7f1d1d;
            color: #fecaca;
        }

        [data-theme="dark"] .badge-info {
            background: #1e3a8a;
            color: #dbeafe;
        }

        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.3rem 0.7rem;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: var(--info);
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-approve {
            background: var(--success);
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-reject {
            background: var(--secondary);
            color: white;
        }

        .btn-reject:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-release {
            background: var(--primary);
            color: white;
        }

        .btn-release:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-icon {
            font-size: 2.5rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 1rem 0;
            text-align: center;
            font-size: 0.75rem;
            margin-left: 260px;
            transition: background 0.3s ease;
        }

        @media (max-width: 1024px) {
            .action-buttons {
                flex-direction: column;
                gap: 0.3rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content,
            .footer {
                margin-left: 0;
                padding: 1rem;
            }
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            .search-container {
                width: 100%;
            }
            .table-container {
                padding: 0 1rem 1rem;
            }
            .requests-table th,
            .requests-table td {
                font-size: 0.7rem;
                padding: 0.5rem 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logos">
                    <img src="../assets/images/barangay-logo.webp" alt="Barangay Logo" class="logo">
                    <img src="../assets/images/manila-logo.webp" alt="Manila Logo" class="logo">
                </div>
                <h3 class="sidebar-title">Barangay 410<br>Zone 42</h3>
                <p class="sidebar-subtitle">Sampaloc, Manila</p>
            </div>
            
            <nav class="nav">
                <a href="../Home.php" class="nav-link" style="border-top:1px solid var(--border);margin-bottom:4px">
                    <i class="fas fa-arrow-left"></i>
                    Back to ProjectRBI
                </a>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-gauge-high"></i>
                    Dashboard
                </a>
                <a href="residents.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Residents
                </a>
                <a href="requests.php" class="nav-link active">
                    <i class="fas fa-clipboard-list"></i>
                    Requests
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="header-title">
                            <i class="fas fa-clipboard-list"></i>
                            Certificate Requests
                        </h1>
                        <p class="header-subtitle">Manage barangay certificate requests</p>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <form method="GET" action="" style="margin: 0;">
                                <input type="text" name="search" class="search-box" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                            </form>
                        </div>

                        <div class="user-dropdown">
                            <div class="user-menu" onclick="toggleUserDropdown()">
                                <div class="avatar">
                                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                    <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                                </div>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </div>
                            
                            <div class="user-dropdown-menu" id="userDropdown">
                                <a href="profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i>
                                    Profile
                                </a>
                                <a href="settings.php" class="dropdown-item">
                                    <i class="fas fa-gear"></i>
                                    Settings
                                </a>
                                <div class="dropdown-item" onclick="logout()" style="color: var(--secondary);">
                                    <i class="fas fa-power-off"></i>
                                    Log Out
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-clipboard-list"></i>
                        Requests List
                        <span style="font-size: 0.7rem; font-weight: normal; color: var(--text-secondary); margin-left: 0.5rem;">(<?php echo $total_requests; ?> total)</span>
                    </h2>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <?php if ($_GET['success'] == 'approved'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Request approved successfully!
                        </div>
                    <?php elseif ($_GET['success'] == 'rejected'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-times-circle"></i> Request rejected.
                        </div>
                    <?php elseif ($_GET['success'] == 'released'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-double"></i> Request marked as released!
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($search_performed && !empty($search)): ?>
                    <div class="search-summary">
                        <span>
                            <i class="fas fa-search"></i> Search results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                            (<?php echo $total_requests; ?> request<?php echo $total_requests != 1 ? 's' : ''; ?> found)
                        </span>
                        <a href="requests.php" style="color: var(--primary); text-decoration: none;">Clear search</a>
                    </div>
                <?php endif; ?>

                <?php if (count($requests) > 0): ?>
                    <div class="table-container">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Resident</th>
                                    <th>Certificate Type</th>
                                    <th>Purpose</th>
                                    <th>Date Requested</th>
                                    <th>Status</th>
                                    <th style="min-width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></strong>
                                            <br><small style="font-size: 0.65rem; color: var(--text-secondary);"><?php echo htmlspecialchars(substr($r['address'], 0, 40)); ?>...</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['template_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($r['purpose'], 0, 50)) . (strlen($r['purpose']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($r['requested_at'])); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'Pending' => 'badge-warning',
                                                'Approved' => 'badge-success',
                                                'Rejected' => 'badge-danger',
                                                'Released' => 'badge-info'
                                            ][$r['status']];
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $r['status']; ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- VIEW PDF BUTTON - OPENS IN NEW TAB -->
                                                <a href="certificate_pdf.php?id=<?php echo $r['id']; ?>" class="btn-action btn-view" target="_blank">
                                                    <i class="fas fa-file-pdf"></i> View PDF
                                                </a>
                                                
                                                <?php if ($r['status'] == 'Pending'): ?>
                                                    <a href="?approve=<?php echo $r['id']; ?>" class="btn-action btn-approve" onclick="return confirm('Approve this request?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="?reject=<?php echo $r['id']; ?>" class="btn-action btn-reject" onclick="return confirm('Reject this request?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                <?php elseif ($r['status'] == 'Approved'): ?>
                                                    <a href="?release=<?php echo $r['id']; ?>" class="btn-action btn-release" onclick="return confirm('Mark as released?')">
                                                        <i class="fas fa-check-double"></i> Release
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <?php if ($search_performed && !empty($search)): ?>
                            <h4 style="font-size: 1rem;">No requests found</h4>
                            <p style="font-size: 0.8rem; color: var(--text-secondary);">No matching requests for "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                            <p><a href="requests.php" style="color: var(--primary);">Clear search</a> to see all requests</p>
                        <?php else: ?>
                            <h4 style="font-size: 1rem;">No certificate requests yet</h4>
                            <p style="font-size: 0.8rem; color: var(--text-secondary);">Requests will appear here when residents apply for certificates.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer class="footer">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 1rem;">
            © <?php echo date('Y'); ?> Barangay 410 Zone 42. All rights reserved.
        </div>
    </footer>

    <script>
        let userDropdownOpen = false;

        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const menu = document.querySelector('.user-menu');
            
            if (userDropdownOpen) {
                dropdown.classList.remove('show');
                menu.classList.remove('active');
            } else {
                dropdown.classList.add('show');
                menu.classList.add('active');
            }
            userDropdownOpen = !userDropdownOpen;
        }

        function logout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'logout.php';
            }
        }

        document.addEventListener('click', function(e) {
            const userDropdown = document.getElementById('userDropdown');
            const userTrigger = document.querySelector('.user-menu');
            
            if (userTrigger && !userTrigger.contains(e.target) && userDropdown && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
                userTrigger.classList.remove('active');
                userDropdownOpen = false;
            }
        });
    </script>
</body>
</html>