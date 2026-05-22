<?php
// admin/household_edit.php - Edit Household Member
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Edit Household Member - Barangay 410 Zone 42";

$member_id = $_GET['id'] ?? 0;
$category = $_GET['category'] ?? '';

if ($member_id <= 0) {
    header('Location: residents.php');
    exit;
}

// Get member details
$stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: residents.php');
    exit;
}

// Get current dark mode setting
$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $suffix = trim($_POST['suffix']);
    $place_birth = trim($_POST['place_birth']);
    $birth_date = $_POST['birth_date'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $civil_status = $_POST['civil_status'];
    $citizenship = trim($_POST['citizenship']);
    $occupation = trim($_POST['occupation']);
    $employment_status = $_POST['employment_status'];
    $relation = $_POST['relation'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE residents SET 
                last_name = ?, first_name = ?, middle_name = ?, suffix = ?,
                place_of_birth = ?, birth_date = ?, age = ?, gender = ?,
                civil_status = ?, citizenship = ?, occupation = ?,
                employment_status = ?, relation_to_head = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $last_name, $first_name, $middle_name, $suffix,
            $place_birth, $birth_date, $age, $gender,
            $civil_status, $citizenship, $occupation,
            $employment_status, $relation, $member_id
        ]);
        
        $success = "Member updated successfully!";
        
        // Refresh member data
        $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
        
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
            --warning: #f59e0b;
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

        .form-card {
            max-width: 900px;
            margin: 0 auto;
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
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
            color: var(--warning);
        }

        .form-body {
            padding: 1.5rem;
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
            background: var(--surface);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
        }

        .required {
            color: var(--secondary);
        }

        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

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

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
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

        .button-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
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

        <main class="main-content">
            <header class="header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="header-title">
                            <i class="fas fa-edit"></i>
                            Edit Household Member
                        </h1>
                        <p class="header-subtitle">
                            Category: <strong><?php echo htmlspecialchars($member['household_name']); ?></strong>
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

            <div class="form-card">
                <div class="form-header">
                    <h2>
                        <i class="fas fa-user-edit"></i>
                        Edit Member Information
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
                            <a href="household_view.php?category=<?php echo urlencode($member['household_name']); ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Household
                            </a>
                            <a href="household_edit.php?id=<?php echo $member_id; ?>&category=<?php echo urlencode($member['household_name']); ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit Again
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="row">
                                <div>
                                    <label class="form-label">Last Name <span class="required">*</span></label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">First Name <span class="required">*</span></label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($member['middle_name']); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Suffix</label>
                                    <input type="text" name="suffix" class="form-control" value="<?php echo htmlspecialchars($member['suffix']); ?>" placeholder="Jr., Sr., III">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div>
                                    <label class="form-label">Place of Birth</label>
                                    <input type="text" name="place_birth" class="form-control" value="<?php echo htmlspecialchars($member['place_of_birth']); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Birth Date</label>
                                    <input type="date" name="birth_date" class="form-control" value="<?php echo $member['birth_date']; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Age</label>
                                    <input type="number" name="age" class="form-control" value="<?php echo $member['age']; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Sex</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" <?php echo $member['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $member['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div>
                                    <label class="form-label">Civil Status</label>
                                    <select name="civil_status" class="form-select">
                                        <option value="Single" <?php echo $member['civil_status'] == 'Single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo $member['civil_status'] == 'Married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="Widowed" <?php echo $member['civil_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                        <option value="Separated" <?php echo $member['civil_status'] == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Citizenship</label>
                                    <input type="text" name="citizenship" class="form-control" value="<?php echo htmlspecialchars($member['citizenship'] ?: 'Filipino'); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Occupation</label>
                                    <input type="text" name="occupation" class="form-control" value="<?php echo htmlspecialchars($member['occupation']); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Relationship to Head</label>
                                    <select name="relation" class="form-select">
                                        <option value="Head" <?php echo $member['relation_to_head'] == 'Head' ? 'selected' : ''; ?>>Head</option>
                                        <option value="Spouse" <?php echo $member['relation_to_head'] == 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                                        <option value="Child" <?php echo $member['relation_to_head'] == 'Child' ? 'selected' : ''; ?>>Child</option>
                                        <option value="Parent" <?php echo $member['relation_to_head'] == 'Parent' ? 'selected' : ''; ?>>Parent</option>
                                        <option value="Sibling" <?php echo $member['relation_to_head'] == 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                                        <option value="Other" <?php echo $member['relation_to_head'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div>
                                    <label class="form-label">Employment Status</label>
                                    <select name="employment_status" class="form-select">
                                        <option value="" <?php echo empty($member['employment_status']) ? 'selected' : ''; ?>>-- Select --</option>
                                        <option value="Employed" <?php echo $member['employment_status'] == 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                        <option value="Unemployed" <?php echo $member['employment_status'] == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                        <option value="Labor" <?php echo $member['employment_status'] == 'Labor' ? 'selected' : ''; ?>>Labor</option>
                                        <option value="PWD" <?php echo $member['employment_status'] == 'PWD' ? 'selected' : ''; ?>>PWD</option>
                                        <option value="OFW" <?php echo $member['employment_status'] == 'OFW' ? 'selected' : ''; ?>>OFW</option>
                                        <option value="Solo Parent" <?php echo $member['employment_status'] == 'Solo Parent' ? 'selected' : ''; ?>>Solo Parent</option>
                                        <option value="Out of School Youth (OSY)" <?php echo $member['employment_status'] == 'Out of School Youth (OSY)' ? 'selected' : ''; ?>>OSY</option>
                                        <option value="Out of School Children (OSC)" <?php echo $member['employment_status'] == 'Out of School Children (OSC)' ? 'selected' : ''; ?>>OSC</option>
                                        <option value="Indigenous People (IP)" <?php echo $member['employment_status'] == 'Indigenous People (IP)' ? 'selected' : ''; ?>>IP</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Member
                                </button>
                                <a href="household_view.php?category=<?php echo urlencode($member['household_name']); ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
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