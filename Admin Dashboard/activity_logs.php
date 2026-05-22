<?php
// admin/activity_logs.php - COMPLETE AUDIT TRAIL WITH WORKING USER DROPDOWN
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Activity Logs - Barangay 410";

$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';

// Filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where = [];
$params = [];
if ($action_filter) { $where[] = "action = ?"; $params[] = $action_filter; }
if ($user_filter) { $where[] = "user_id = ?"; $params[] = $user_filter; }
if ($date_from) { $where[] = "DATE(created_at) >= ?"; $params[] = $date_from; }
if ($date_to) { $where[] = "DATE(created_at) <= ?"; $params[] = $date_to; }

$where_clause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

$logs = $pdo->prepare("
    SELECT al.*, u.full_name, u.username
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_clause
    ORDER BY al.created_at DESC
    LIMIT 500
");
$logs->execute($params);
$logs = $logs->fetchAll();

// Get filter options
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll();
$users = $pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();
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
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        [data-theme="dark"] {
            --primary: #3b82f6;
            --surface: #1e293b;
            --surface-2: #0f172a;
            --border: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--surface-2); color: var(--text-primary); transition: background 0.3s; }
        .app-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: var(--surface);
            position: fixed;
            height: 100vh;
            border-right: 1px solid var(--border);
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, var(--primary) 0%, #3b82f6 100%);
            color: white;
            text-align: center;
        }
        .logos { display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 0.75rem; }
        .logo { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
        .sidebar-title { font-size: 1rem; font-weight: 600; }
        .sidebar-subtitle { font-size: 0.7rem; opacity: 0.9; }
        .nav { padding: 1rem 0; }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.7rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { background: var(--surface-2); color: var(--primary); border-left-color: var(--primary); }
        .nav-link i { width: 20px; margin-right: 0.75rem; }
        .main-content { margin-left: 260px; padding: 1.5rem; flex: 1; }
        .header {
            background: var(--surface);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .header-title { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .filter-bar {
            background: var(--surface);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .data-table th {
            text-align: left;
            padding: 0.75rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }
        .badge-success { background: var(--success); color: white; }
        .badge-warning { background: var(--warning); color: white; }
        .badge-danger { background: var(--danger); color: white; }
        .badge-info { background: var(--info); color: white; }
        .badge-primary { background: var(--primary); color: white; }
        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
        }
        /* User Dropdown Styles */
        .user-dropdown { position: relative; }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--surface-2);
            border-radius: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
        }
        .user-menu:hover { background: var(--primary); color: white; }
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
        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 180px;
            background: var(--surface);
            border-radius: 10px;
            border: 1px solid var(--border);
            display: none;
            margin-top: 0.5rem;
            z-index: 100;
            overflow: hidden;
        }
        .user-dropdown-menu.show { display: block; }
        .dropdown-item {
            padding: 0.7rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
        }
        .dropdown-item:hover { background: var(--surface-2); }
        .dropdown-item:last-child { border-bottom: none; }
        .footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 1rem;
            text-align: center;
            font-size: 0.75rem;
            margin-left: 260px;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content, .footer { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logos">
                <img src="../assets/images/barangay-logo.webp" class="logo">
                <img src="../assets/images/manila-logo.webp" class="logo">
            </div>
            <h3 class="sidebar-title">Barangay 410<br>Zone 42</h3>
            <p class="sidebar-subtitle">Sampaloc, Manila</p>
        </div>
        <nav class="nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-gauge-high"></i> Dashboard</a>
            <a href="residents.php" class="nav-link"><i class="fas fa-users"></i> Residents</a>
            <a href="templates.php" class="nav-link"><i class="fas fa-file-lines"></i> Templates</a>
            <a href="requests.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Requests</a>
            <a href="activity_logs.php" class="nav-link active"><i class="fas fa-history"></i> Activity Logs</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="header-content">
                <div>
                    <h1 class="header-title"><i class="fas fa-history"></i> Activity Logs</h1>
                    <p>Track all system activities - residents, certificates, templates, and user actions</p>
                </div>
                <div class="user-dropdown">
                    <div class="user-menu" onclick="toggleUserDropdown()">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                        <div><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown-menu" id="userDropdown">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> Profile</a>
                        <a href="settings.php" class="dropdown-item"><i class="fas fa-gear"></i> Settings</a>
                        <div class="dropdown-item" onclick="logout()"><i class="fas fa-power-off"></i> Logout</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="action" class="form-select" style="width: 100%; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border); background: var(--surface);">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo $action['action']; ?>" <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>><?php echo $action['action']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="user_id" class="form-select" style="width: 100%; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border); background: var(--surface);">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" style="width: 100%; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border); background: var(--surface);">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" style="width: 100%; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border); background: var(--surface);">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn w-100"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- ACTIVITY LOGS TABLE -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System'); ?></td>
                        <td>
                            <?php
                            $badge_color = 'primary';
                            $action = $log['action'];
                            if (strpos($action, 'LOGIN') !== false) $badge_color = 'info';
                            elseif (strpos($action, 'ADD') !== false) $badge_color = 'success';
                            elseif (strpos($action, 'EDIT') !== false) $badge_color = 'warning';
                            elseif (strpos($action, 'DELETE') !== false) $badge_color = 'danger';
                            elseif (strpos($action, 'APPROVE') !== false) $badge_color = 'success';
                            elseif (strpos($action, 'REJECT') !== false) $badge_color = 'danger';
                            elseif (strpos($action, 'REQUEST') !== false) $badge_color = 'info';
                            ?>
                            <span class="badge badge-<?php echo $badge_color; ?>"><?php echo $action; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                        <td><?php echo $log['ip_address']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No activity logs found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- LEGEND / INFO -->
        <div class="filter-bar" style="margin-top: 1rem;">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                <strong>Actions tracked:</strong> 
                <span class="badge badge-success">RESIDENT_ADDED</span> - New resident added |
                <span class="badge badge-success">REQUEST_APPROVED</span> - Certificate approved |
                <span class="badge badge-info">REQUEST_SUBMITTED</span> - New certificate request |
                <span class="badge badge-warning">RESIDENT_EDITED</span> - Resident details updated |
                <span class="badge badge-danger">RESIDENT_DELETED</span> - Resident removed |
                <span class="badge badge-primary">TEMPLATE_CREATED</span> - New template added
            </small>
        </div>
    </main>
</div>
<footer class="footer">© <?php echo date('Y'); ?> Barangay 410 Zone 42. All rights reserved.</footer>

<script>
    let userDropdownOpen = false;
    
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        userDropdownOpen = !userDropdownOpen;
        dropdown.classList.toggle('show', userDropdownOpen);
    }
    
    function logout() { 
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = 'logout.php';
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const userMenu = document.querySelector('.user-menu');
        const dropdown = document.getElementById('userDropdown');
        if (userMenu && !userMenu.contains(e.target) && dropdown) {
            dropdown.classList.remove('show');
            userDropdownOpen = false;
        }
    });
</script>
</body>
</html>