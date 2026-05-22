<?php
// admin/household_add.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Add Household - Barangay 410 Zone 42";

$category = $_GET['category'] ?? '';

if (empty($category)) {
    header('Location: residents.php');
    exit;
}

$error = '';
$success = '';

// Get current dark mode setting
$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';

// Check if table has required columns
try {
    $pdo->query("SELECT address, region, province, municipality, barangay FROM residents LIMIT 1");
} catch (PDOException $e) {
    // Add missing columns if they don't exist
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS address TEXT NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS region VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS province VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS municipality VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS barangay VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS place_of_birth VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS citizenship VARCHAR(50) DEFAULT 'Filipino'");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS occupation VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS employment_status VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS relation_to_head VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS is_head TINYINT DEFAULT 0");
    $pdo->exec("ALTER TABLE residents ADD COLUMN IF NOT EXISTS household_name VARCHAR(100) NULL");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member_count = (int)$_POST['member_count'];
    $address = trim($_POST['address']);
    $region = trim($_POST['region']);
    $province = trim($_POST['province']);
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    
    try {
        for ($i = 0; $i < $member_count; $i++) {
            $last_name = trim($_POST["last_name_$i"] ?? '');
            $first_name = trim($_POST["first_name_$i"] ?? '');
            
            if (empty($last_name) || empty($first_name)) {
                continue;
            }
            
            $middle_name = trim($_POST["middle_name_$i"] ?? '');
            $suffix = trim($_POST["suffix_$i"] ?? '');
            $place_birth = trim($_POST["place_birth_$i"] ?? '');
            $birth_date = $_POST["birth_date_$i"] ?? null;
            $age = $_POST["age_$i"] ?? null;
            $gender = $_POST["gender_$i"] ?? 'Male';
            $civil_status = $_POST["civil_status_$i"] ?? 'Single';
            $citizenship = trim($_POST["citizenship_$i"] ?? 'Filipino');
            $occupation = trim($_POST["occupation_$i"] ?? '');
            $employment_status = $_POST["employment_status_$i"] ?? '';
            $relation = $_POST["relation_$i"] ?? 'Member';
            $is_head = ($relation == 'Head') ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO residents 
                (household_name, last_name, first_name, middle_name, suffix, 
                 place_of_birth, birth_date, age, gender, civil_status, 
                 citizenship, occupation, employment_status, relation_to_head, 
                 is_head, address, region, province, municipality, barangay) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $category, $last_name, $first_name, $middle_name, $suffix,
                $place_birth, $birth_date, $age, $gender, $civil_status,
                $citizenship, $occupation, $employment_status, $relation,
                $is_head, $address, $region, $province, $municipality, $barangay
            ]);
        }
        $success = "Household added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
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
            --success: #10b981;
        }

        /* DARK MODE VARIABLES */
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
            --success: #10b981;
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

        /* Sidebar - COMPACT */
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

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            flex: 1;
        }

        /* Header - COMPACT */
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

        /* User Dropdown */
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

        .user-menu:hover .user-name {
            color: white;
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

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item i {
            width: 16px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Form Card */
        .form-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: background 0.3s ease;
        }

        .form-header {
            padding: 1rem 1.5rem;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
        }

        .form-header h2 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-header h2 i {
            color: var(--primary);
            font-size: 1rem;
        }

        .form-body {
            padding: 1.5rem;
        }

        /* Form Sections */
        .form-section {
            background: var(--surface-2);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border);
            transition: background 0.3s ease;
        }

        .form-section h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section h3 i {
            color: var(--primary);
            font-size: 0.85rem;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            display: block;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.5rem 0.7rem;
            font-size: 0.8rem;
            font-family: 'Inter', sans-serif;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.2s ease;
            background: var(--surface);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
        }

        .required {
            color: var(--secondary);
        }

        /* Member Card */
        .member-card {
            background: var(--surface);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .member-card:hover {
            box-shadow: var(--shadow-md);
        }

        .member-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        .member-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .badge-head {
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 500;
        }

        /* Alert Messages */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        [data-theme="dark"] .alert-danger {
            background: #7f1d1d;
            color: #fecaca;
            border: 1px solid #991b1b;
        }

        [data-theme="dark"] .alert-success {
            background: #064e3b;
            color: #d1fae5;
            border: 1px solid #065f46;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1.25rem;
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--surface-2);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            color: var(--text-primary);
        }

        .button-group {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        /* Row Layout */
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        /* Footer */
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

        /* Responsive */
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
            .row {
                grid-template-columns: 1fr;
            }
            .button-group {
                flex-direction: column;
            }
            .btn {
                justify-content: center;
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
                <a href="residents.php" class="nav-link active">
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
                            <i class="fas fa-home"></i>
                            Add Household
                        </h1>
                        <p class="header-subtitle">Category: <strong><?php echo htmlspecialchars($category); ?></strong></p>
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

            <!-- Form Card -->
            <div class="form-card">
                <div class="form-header">
                    <h2>
                        <i class="fas fa-pen-alt"></i>
                        Household Information
                    </h2>
                </div>
                <div class="form-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                        <div class="button-group">
                            <a href="residents.php" class="btn btn-primary">
                                <i class="fas fa-folder"></i> View Categories
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <!-- Address Information -->
                            <div class="form-section">
                                <h3><i class="fas fa-location-dot"></i> Address Information</h3>
                                <div class="row">
                                    <div>
                                        <label class="form-label">Region <span class="required">*</span></label>
                                        <input type="text" name="region" class="form-control" value="NCR" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Province <span class="required">*</span></label>
                                        <input type="text" name="province" class="form-control" value="Manila" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Municipality <span class="required">*</span></label>
                                        <input type="text" name="municipality" class="form-control" value="Manila" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Barangay <span class="required">*</span></label>
                                        <input type="text" name="barangay" class="form-control" value="Barangay 410 Zone 42" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">House Address <span class="required">*</span></label>
                                    <input type="text" name="address" class="form-control" placeholder="e.g., 3rd floor, 2 Landu St., Lapu-Lapu, Manila" required>
                                </div>
                            </div>

                            <!-- Number of Members -->
                            <div class="form-section">
                                <h3><i class="fas fa-users"></i> Household Members</h3>
                                <div>
                                    <label class="form-label">Number of Members <span class="required">*</span></label>
                                    <input type="number" name="member_count" id="member_count" class="form-control" min="1" max="10" required style="width: 150px;">
                                </div>
                            </div>

                            <!-- Members Container -->
                            <div id="members_container"></div>

                            <!-- Buttons -->
                            <div class="button-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Household
                                </button>
                                <a href="residents.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
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

        // Member form generator
        document.getElementById('member_count').addEventListener('change', function() {
            const count = parseInt(this.value);
            const container = document.getElementById('members_container');
            container.innerHTML = '';
            
            for (let i = 0; i < count; i++) {
                const isHead = (i === 0);
                container.innerHTML += `
                    <div class="member-card">
                        <div class="member-header">
                            <span class="member-title">Member ${i + 1}</span>
                            ${isHead ? '<span class="badge-head"><i class="fas fa-crown"></i> Head of Family</span>' : ''}
                        </div>
                        <div class="row">
                            <div>
                                <label class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name_${i}" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" name="first_name_${i}" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name_${i}" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Suffix</label>
                                <input type="text" name="suffix_${i}" class="form-control" placeholder="Jr., Sr., III">
                            </div>
                        </div>
                        <div class="row">
                            <div>
                                <label class="form-label">Place of Birth</label>
                                <input type="text" name="place_birth_${i}" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Birth Date</label>
                                <input type="date" name="birth_date_${i}" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Age</label>
                                <input type="number" name="age_${i}" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Sex</label>
                                <select name="gender_${i}" class="form-select">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div>
                                <label class="form-label">Civil Status</label>
                                <select name="civil_status_${i}" class="form-select">
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Separated">Separated</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Citizenship</label>
                                <input type="text" name="citizenship_${i}" class="form-control" value="Filipino">
                            </div>
                            <div>
                                <label class="form-label">Occupation</label>
                                <input type="text" name="occupation_${i}" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Relationship to Head</label>
                                <select name="relation_${i}" class="form-select">
                                    <option value="Head" ${isHead ? 'selected' : ''}>Head</option>
                                    <option value="Spouse">Spouse</option>
                                    <option value="Child">Child</option>
                                    <option value="Parent">Parent</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Employment Status</label>
                            <select name="employment_status_${i}" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="Employed">Employed</option>
                                <option value="Unemployed">Unemployed</option>
                                <option value="Labor">Labor</option>
                                <option value="PWD">PWD</option>
                                <option value="OFW">OFW</option>
                                <option value="Solo Parent">Solo Parent</option>
                                <option value="Out of School Youth (OSY)">OSY</option>
                                <option value="Out of School Children (OSC)">OSC</option>
                                <option value="Indigenous People (IP)">IP</option>
                            </select>
                        </div>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>