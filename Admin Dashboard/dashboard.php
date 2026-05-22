<?php
// admin/dashboard.php - WITH CHARTS, QUICK ACTIONS, CAPTAIN NAME
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Dashboard - Barangay 410 Zone 42";

// Get current dark mode
$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';

// ============================================
// STATISTICS
// ============================================
$residents_count = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
$pending_count = $pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'Pending'")->fetchColumn();
$approved_count = $pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'Approved'")->fetchColumn();
$released_count = $pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'Released'")->fetchColumn();
$rejected_count = $pdo->query("SELECT COUNT(*) FROM certificate_requests WHERE status = 'Rejected'")->fetchColumn();
$templates_count = $pdo->query("SELECT COUNT(*) FROM certificate_templates")->fetchColumn();

// ============================================
// CHART 1: MONTHLY RESIDENT REGISTRATION (Last 6 months)
// ============================================
$monthly_residents = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%M') as month,
        COUNT(*) as count
    FROM residents 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
")->fetchAll();

$months = [];
$resident_counts = [];
foreach ($monthly_residents as $row) {
    $months[] = $row['month'];
    $resident_counts[] = $row['count'];
}

// ============================================
// CHART 2: CERTIFICATE REQUESTS BY TYPE
// ============================================
$requests_by_type = $pdo->query("
    SELECT 
        ct.template_name as type,
        COUNT(*) as count
    FROM certificate_requests cr
    JOIN certificate_templates ct ON cr.template_id = ct.id
    GROUP BY cr.template_id
    LIMIT 5
")->fetchAll();

$request_types = [];
$request_type_counts = [];
foreach ($requests_by_type as $row) {
    $request_types[] = $row['type'];
    $request_type_counts[] = $row['count'];
}

// ============================================
// CHART 3: REQUEST STATUS (Bar Chart)
// ============================================
$status_data = [
    'Pending' => $pending_count,
    'Approved' => $approved_count,
    'Released' => $released_count,
    'Rejected' => $rejected_count
];

// ============================================
// CHART 4: POPULATION DEMOGRAPHICS (Age Groups)
// ============================================
$age_groups = $pdo->query("
    SELECT 
        CASE 
            WHEN age < 18 THEN '0-17'
            WHEN age BETWEEN 18 AND 30 THEN '18-30'
            WHEN age BETWEEN 31 AND 45 THEN '31-45'
            WHEN age BETWEEN 46 AND 60 THEN '46-60'
            WHEN age > 60 THEN '60+'
            ELSE 'Unknown'
        END as age_group,
        COUNT(*) as count
    FROM residents 
    WHERE age IS NOT NULL
    GROUP BY age_group
    ORDER BY age_group
")->fetchAll();

$age_labels = [];
$age_counts = [];
foreach ($age_groups as $row) {
    $age_labels[] = $row['age_group'];
    $age_counts[] = $row['count'];
}

// ============================================
// ACTIVITY LOGS (Recent)
// ============================================
$recent_logs = $pdo->query("
    SELECT al.*, u.full_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

// Notifications
$notifications = $pdo->query("
    SELECT cr.*, r.first_name, r.last_name, ct.template_name 
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.id
    JOIN certificate_templates ct ON cr.template_id = ct.id
    WHERE cr.status = 'Pending'
    ORDER BY cr.requested_at DESC
    LIMIT 5
")->fetchAll();
$notification_count = count($notifications);

// Barangay Captain name from session
$barangay_captain = $_SESSION['barangay_captain'] ?? 'P/B MICHAEL JOHN M. REGALA';
$barangay_address = $_SESSION['barangay_address'] ?? 'Zone 42, District IV, Manila';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--surface-2);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .app-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: var(--surface);
            position: fixed;
            height: 100vh;
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
        .logos { display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 0.75rem; }
        .logo { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); }
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
            transition: all 0.2s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: var(--surface-2);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        .nav-link i { width: 20px; margin-right: 0.75rem; font-size: 1rem; }
        .main-content { margin-left: 260px; padding: 1.5rem; flex: 1; }

        .header {
            background: var(--surface);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            transition: background 0.3s ease;
        }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .header-title { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .header-subtitle { color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.25rem; }

        .search-box {
            position: relative;
            width: 260px;
        }
        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.25rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.8rem;
            background: var(--surface);
            color: var(--text-primary);
        }
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .notification {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-secondary);
            cursor: pointer;
        }
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            width: 320px;
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            display: none;
            z-index: 50;
        }
        .notification-dropdown.show { display: block; }
        .notification-item {
            padding: 0.875rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
        }
        .notification-item:hover { background: var(--surface-2); }

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
        }
        .dropdown-item:hover { background: var(--surface-2); }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--border);
            text-align: center;
        }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; }

        /* Quick Actions Card */
        .quick-actions-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            flex-wrap: wrap;
        }
        .action-btn {
            flex: 1;
            min-width: 180px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        .action-btn i { font-size: 1.5rem; }
        .action-btn span { font-weight: 500; }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .chart-header {
            padding: 1rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }
        .chart-body { padding: 1rem; }
        canvas { max-height: 250px; width: 100%; }

        /* System Info Card */
        .system-info-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .info-item:last-child { border-bottom: none; }
        .info-icon {
            width: 36px;
            height: 36px;
            background: var(--surface-2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        .live-time {
            background: var(--primary);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.8rem;
        }

        /* Activity Logs Table */
        .activity-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .activity-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .activity-table th {
            text-align: left;
            padding: 0.75rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
        }
        .activity-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        [data-theme="dark"] .badge-success { background: #064e3b; color: #d1fae5; }
        [data-theme="dark"] .badge-danger { background: #7f1d1d; color: #fecaca; }
        [data-theme="dark"] .badge-warning { background: #451a03; color: #fef3c7; }
        [data-theme="dark"] .badge-info { background: #1e3a8a; color: #dbeafe; }

        .footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 1rem;
            text-align: center;
            font-size: 0.75rem;
            margin-left: 260px;
        }

        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content, .footer { margin-left: 0; padding: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
            <a href="../Home.php" class="nav-link" style="border-top:1px solid var(--border);margin-bottom:4px"><i class="fas fa-arrow-left"></i> Back to ProjectRBI</a>
            <a href="dashboard.php" class="nav-link active"><i class="fas fa-gauge-high"></i> Dashboard</a>
            <a href="residents.php" class="nav-link"><i class="fas fa-users"></i> Residents</a>
            <a href="requests.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Requests</a>
            <a href="activity_logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="header-content">
                <div>
                    <h1 class="header-title"><i class="fas fa-chart-line"></i> Dashboard</h1>
                    <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search..." id="searchInput">
                    </div>
                    <div class="notification" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                            <div class="notification-badge"><?php echo $notification_count; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header" style="padding: 0.875rem 1rem; background: var(--surface-2); border-bottom: 1px solid var(--border); font-weight: 600;">Notifications (<?php echo $notification_count; ?>)</div>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item" onclick="window.location.href='requests.php'">
                                    <div><strong><?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?></strong> requested <?php echo htmlspecialchars($notif['template_name']); ?></div>
                                    <small><?php echo date('M j, Y g:i A', strtotime($notif['requested_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-item">No new notifications</div>
                        <?php endif; ?>
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
            </div>
        </header>

        <!-- STATISTICS CARDS -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo number_format($residents_count); ?></div><div class="stat-label">Total Residents</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($pending_count); ?></div><div class="stat-label">Pending Requests</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($approved_count); ?></div><div class="stat-label">Approved</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($released_count); ?></div><div class="stat-label">Released</div></div>
        </div>

        <!-- QUICK ACTIONS BUTTONS -->
        <div class="quick-actions-card">
            <div class="chart-header"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="action-buttons">
                <a href="resident_add.php" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New Resident</span>
                </a>
                <a href="template_add.php" class="action-btn">
                    <i class="fas fa-file-alt"></i>
                    <span>Create Template</span>
                </a>
                <a href="residents.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <span>View Residents</span>
                </a>
            </div>
        </div>

        <!-- CHARTS GRID -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header"><i class="fas fa-chart-line"></i> Monthly Resident Registration</div>
                <div class="chart-body"><canvas id="monthlyChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><i class="fas fa-chart-pie"></i> Requests by Certificate Type</div>
                <div class="chart-body"><canvas id="typeChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><i class="fas fa-chart-bar"></i> Request Status Overview</div>
                <div class="chart-body"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><i class="fas fa-chart-pie"></i> Population by Age Group</div>
                <div class="chart-body"><canvas id="ageChart"></canvas></div>
            </div>
        </div>

        <!-- SYSTEM INFORMATION (with Barangay Captain) -->
        <div class="system-info-card">
            <div class="chart-header"><i class="fas fa-info-circle"></i> System Information</div>
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-calendar"></i></div>
                <div><strong>Date:</strong> <?php echo date('F j, Y'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-clock"></i></div>
                <div><strong>Time:</strong> <span id="liveTime" class="live-time"></span></div>
            </div>
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-user-tie"></i></div>
                <div><strong>Barangay Captain:</strong> <?php echo $barangay_captain; ?></div>
            </div>
            <div class="info-item">
                <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div><strong>Location:</strong> <?php echo $barangay_address; ?></div>
            </div>
        </div>

        <!-- RECENT ACTIVITY LOGS -->
        <div class="activity-card">
            <div class="chart-header"><i class="fas fa-history"></i> Recent Activity Logs</div>
            <div style="overflow-x: auto;">
                <table class="activity-table">
                    <thead>
                        <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP Address</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></td>
                            <td><span class="badge badge-info"><?php echo $log['action']; ?></span></td>
                            <td><?php echo htmlspecialchars(substr($log['details'], 0, 50)); ?></td>
                            <td><?php echo $log['ip_address']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_logs)): ?>
                        <tr><td colspan="5" style="text-align: center;">No activity logs yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<footer class="footer">
    © <?php echo date('Y'); ?> Barangay 410 Zone 42. All rights reserved.
</footer>

<script>
    // Toggle functions
    let userDropdownOpen = false, notifOpen = false;
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        userDropdownOpen = !userDropdownOpen;
        dropdown.classList.toggle('show', userDropdownOpen);
    }
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        notifOpen = !notifOpen;
        dropdown.classList.toggle('show', notifOpen);
    }
    function logout() { if (confirm('Logout?')) window.location.href = 'logout.php'; }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-menu')) document.getElementById('userDropdown').classList.remove('show');
        if (!e.target.closest('.notification')) document.getElementById('notificationDropdown').classList.remove('show');
    });

    // Live Time
    function updateTime() {
        const now = new Date();
        document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    updateTime();
    setInterval(updateTime, 1000);

    // CHART 1: Monthly Resident Registration
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'New Residents',
                data: <?php echo json_encode($resident_counts); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // CHART 2: Certificate Requests by Type
    new Chart(document.getElementById('typeChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($request_types); ?>,
            datasets: [{
                data: <?php echo json_encode($request_type_counts); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // CHART 3: Request Status (Bar Chart)
    new Chart(document.getElementById('statusChart'), {
        type: 'bar',
        data: {
            labels: ['Pending', 'Approved', 'Released', 'Rejected'],
            datasets: [{
                label: 'Number of Requests',
                data: [<?php echo $pending_count; ?>, <?php echo $approved_count; ?>, <?php echo $released_count; ?>, <?php echo $rejected_count; ?>],
                backgroundColor: ['#f59e0b', '#10b981', '#3b82f6', '#ef4444'],
                borderRadius: 8
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // CHART 4: Population by Age Group
    new Chart(document.getElementById('ageChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($age_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($age_counts); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
            }]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });

    // Search functionality
    document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && this.value.length > 0) {
            window.location.href = `dashboard.php?search=${encodeURIComponent(this.value)}`;
        }
    });
</script>
</body>
</html>