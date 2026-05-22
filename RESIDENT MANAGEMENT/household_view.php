<?php
// admin/household_view.php - View households inside a category
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Household View - Barangay 410 Zone 42";

$category = $_GET['category'] ?? '';

if (empty($category)) {
    header('Location: residents.php');
    exit;
}

// Get all households in this category
$households = $pdo->prepare("
    SELECT address, COUNT(*) as member_count
    FROM residents 
    WHERE household_name = ? 
    GROUP BY address
    ORDER BY address
");
$households->execute([$category]);
$household_list = $households->fetchAll();

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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.3);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.3), 0 1px 2px -1px rgb(0 0 0 / 0.3);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.3), 0 2px 4px -2px rgb(0 0 0 / 0.3);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.3), 0 4px 6px -4px rgb(0 0 0 / 0.3);
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

        .dropdown-item i {
            width: 16px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Household Cards */
        .household-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .household-card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header-custom {
            padding: 1rem 1.5rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .card-header-custom h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header-custom h3 i {
            color: var(--primary);
        }

        .badge-household {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .table-container {
            padding: 1rem 1.5rem 1.5rem;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .data-table th {
            text-align: left;
            padding: 0.75rem 0.5rem;
            background: var(--surface-2);
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }

        .data-table td {
            padding: 0.75rem 0.5rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background: var(--surface-2);
        }

        .badge-head {
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .badge-status {
            background: var(--surface-2);
            color: var(--text-secondary);
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-block;
        }

        .btn-edit {
            background: var(--warning);
            color: #78350f;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s ease;
        }

        .btn-edit:hover {
            background: #d97706;
            color: white;
        }

        .btn-add {
            background: var(--success);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-add:hover {
            background: #059669;
            transform: translateY(-1px);
            color: white;
        }

        .btn-back {
            background: var(--surface-2);
            color: var(--text-secondary);
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            border: 1px solid var(--border);
        }

        .btn-back:hover {
            background: var(--border);
            color: var(--text-primary);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 0.8rem;
            color: var(--text-secondary);
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
            .card-header-custom {
                flex-direction: column;
                align-items: flex-start;
            }
            .data-table th,
            .data-table td {
                font-size: 0.7rem;
                padding: 0.5rem 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-gauge-high"></i>
                    Dashboard
                </a>
                <a href="residents.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Residents
                </a>
                <a href="templates.php" class="nav-link">
                    <i class="fas fa-file-lines"></i>
                    Templates
                </a>
                <a href="requests.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i>
                    Requests
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="header-title">
                            <i class="fas fa-folder-open"></i>
                            Household View
                        </h1>
                        <p class="header-subtitle">
                            Category: <strong><?php echo htmlspecialchars($category); ?></strong>
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
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

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; justify-content: space-between; flex-wrap: wrap;">
                <a href="residents.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Categories
                </a>
                <a href="household_add.php?category=<?php echo urlencode($category); ?>" class="btn-add">
                    <i class="fas fa-plus"></i> Add New Household
                </a>
            </div>

            <!-- Household List -->
            <?php if (empty($household_list)): ?>
                <div class="household-card">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h4>No Households Yet</h4>
                        <p>Click "Add New Household" to add households to <?php echo htmlspecialchars($category); ?>.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($household_list as $index => $h): 
                    $members = $pdo->prepare("
                        SELECT * FROM residents 
                        WHERE household_name = ? AND address = ?
                        ORDER BY is_head DESC, id ASC
                    ");
                    $members->execute([$category, $h['address']]);
                    $member_list = $members->fetchAll();
                ?>
                <div class="household-card">
                    <div class="card-header-custom">
                        <h3>
                            <i class="fas fa-home"></i>
                            Household <?php echo $index + 1; ?>
                        </h3>
                        <div>
                            <span class="badge-household">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($h['address']); ?>
                            </span>
                            <span class="badge-household" style="background: var(--success); margin-left: 0.5rem;">
                                <i class="fas fa-users"></i> <?php echo $h['member_count']; ?> members
                            </span>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Relation</th>
                                    <th>Birth Date</th>
                                    <th>Age</th>
                                    <th>Sex</th>
                                    <th>Civil Status</th>
                                    <th>Occupation</th>
                                    <th>Employment</th>
                                    <th width="80">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($member_list as $m): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?></strong>
                                        <?php if ($m['middle_name']): ?>
                                            <?php echo ' ' . htmlspecialchars(substr($m['middle_name'], 0, 1)) . '.'; ?>
                                        <?php endif; ?>
                                        <?php if ($m['is_head']): ?>
                                            <span class="badge-head">Head</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['relation_to_head'] ?: 'Member'); ?></td>
                                    <td><?php echo $m['birth_date'] ? date('M d, Y', strtotime($m['birth_date'])) : '—'; ?></td>
                                    <td><?php echo $m['age'] ?: '—'; ?></td>
                                    <td><?php echo $m['gender']; ?></td>
                                    <td><?php echo htmlspecialchars($m['civil_status']); ?></td>
                                    <td><?php echo htmlspecialchars($m['occupation'] ?: '—'); ?></td>
                                    <td>
                                        <span class="badge-status">
                                            <?php echo htmlspecialchars($m['employment_status'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="household_edit.php?id=<?php echo $m['id']; ?>&category=<?php echo urlencode($category); ?>" class="btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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