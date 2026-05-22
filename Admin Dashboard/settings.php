<?php
// admin/settings.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$page_title = "Settings - Barangay 410 Zone 42";

// Only captain/admin can access settings
if ($_SESSION['role'] !== 'captain' && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// ============================================
// DARK MODE TOGGLE - FIXED
// ============================================
if (isset($_POST['dark_mode']) || isset($_POST['dark_mode_toggle'])) {
    // Get the value from either POST variable
    $mode = isset($_POST['dark_mode']) ? $_POST['dark_mode'] : $_POST['dark_mode_toggle'];
    
    // Toggle based on the value
    if ($mode == 'dark') {
        $_SESSION['dark_mode'] = 'dark';
    } elseif ($mode == 'light') {
        $_SESSION['dark_mode'] = 'light';
    } elseif ($mode == '1' || $mode == 'on') {
        // Handle checkbox toggle
        $_SESSION['dark_mode'] = ($_SESSION['dark_mode'] == 'dark') ? 'light' : 'dark';
    } else {
        // Default toggle
        $_SESSION['dark_mode'] = ($_SESSION['dark_mode'] == 'dark') ? 'light' : 'dark';
    }
    
    header('Location: settings.php?success=theme');
    exit();
}

// Initialize dark mode if not set
if (!isset($_SESSION['dark_mode'])) {
    $_SESSION['dark_mode'] = 'light';
}

$message = '';
$message_type = '';

// ============================================
// BARANGAY INFORMATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_barangay'])) {
    $_SESSION['barangay_name'] = trim($_POST['barangay_name']);
    $_SESSION['barangay_email'] = trim($_POST['barangay_email']);
    $_SESSION['barangay_telephone'] = trim($_POST['barangay_telephone']);
    $_SESSION['barangay_captain'] = trim($_POST['barangay_captain']);
    $_SESSION['barangay_address'] = trim($_POST['barangay_address']);
    header('Location: settings.php?success=barangay');
    exit();
}

// ============================================
// IMPORT CSV
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $extension = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
        
        if (strtolower($extension) != 'csv') {
            header('Location: settings.php?error=invalid_csv');
            exit();
        }
        
        $import_count = 0;
        $error_count = 0;
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Skip header row
            $header = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                try {
                    $last_name = trim($data[0] ?? '');
                    $first_name = trim($data[1] ?? '');
                    $middle_name = trim($data[2] ?? '');
                    $suffix = trim($data[3] ?? '');
                    $address = trim($data[4] ?? '');
                    $birth_date = !empty($data[5]) ? date('Y-m-d', strtotime($data[5])) : null;
                    $gender = trim($data[6] ?? 'Male');
                    $civil_status = trim($data[7] ?? 'Single');
                    $occupation = trim($data[8] ?? '');
                    $citizenship = trim($data[9] ?? 'Filipino');
                    $household_name = trim($data[10] ?? 'Uncategorized');
                    
                    if (empty($last_name) || empty($first_name)) {
                        $error_count++;
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO residents 
                        (household_name, last_name, first_name, middle_name, suffix, 
                         address, birth_date, gender, civil_status, occupation, citizenship) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $household_name, $last_name, $first_name, $middle_name, $suffix,
                        $address, $birth_date, $gender, $civil_status, $occupation, $citizenship
                    ]);
                    
                    $import_count++;
                } catch (Exception $e) {
                    $error_count++;
                }
            }
            fclose($handle);
        }
        
        header("Location: settings.php?success=import&imported=$import_count&errors=$error_count");
        exit();
    } else {
        header('Location: settings.php?error=no_file');
        exit();
    }
}

// ============================================
// EXPORT TO EXCEL
// ============================================
if (isset($_GET['export_excel'])) {
    $residents = $pdo->query("
        SELECT id, household_name, last_name, first_name, middle_name, suffix,
               address, birth_date, age, gender, civil_status, citizenship, 
               occupation, employment_status, relation_to_head, created_at
        FROM residents 
        ORDER BY household_name, last_name, first_name
    ")->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="residents_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'ID', 'Household Name', 'Last Name', 'First Name', 'Middle Name', 'Suffix',
        'Address', 'Birth Date', 'Age', 'Gender', 'Civil Status', 'Citizenship',
        'Occupation', 'Employment Status', 'Relationship to Head', 'Date Registered'
    ]);
    
    foreach ($residents as $resident) {
        fputcsv($output, [
            $resident['id'],
            $resident['household_name'],
            $resident['last_name'],
            $resident['first_name'],
            $resident['middle_name'],
            $resident['suffix'],
            $resident['address'],
            $resident['birth_date'],
            $resident['age'],
            $resident['gender'],
            $resident['civil_status'],
            $resident['citizenship'],
            $resident['occupation'],
            $resident['employment_status'],
            $resident['relation_to_head'],
            date('Y-m-d H:i:s', strtotime($resident['created_at']))
        ]);
    }
    
    fclose($output);
    exit();
}

// ============================================
// DOWNLOAD SAMPLE CSV
// ============================================
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_residents_import.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'Last Name', 'First Name', 'Middle Name', 'Suffix', 'Address', 
        'Birth Date', 'Gender', 'Civil Status', 'Occupation', 'Citizenship', 'Household Name'
    ]);
    
    fputcsv($output, [
        'Dela Cruz', 'Juan', 'Santos', '', '123 Rizal St., Sampaloc, Manila',
        '1990-01-15', 'Male', 'Married', 'Electrician', 'Filipino', 'ROSAL'
    ]);
    
    fputcsv($output, [
        'Dela Cruz', 'Maria', 'Reyes', '', '123 Rizal St., Sampaloc, Manila',
        '1992-03-20', 'Female', 'Married', 'Teacher', 'Filipino', 'ROSAL'
    ]);
    
    fclose($output);
    exit();
}

// ============================================
// PASSWORD CHANGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed_password, $_SESSION['user_id']]);
            header('Location: settings.php?success=password');
            exit();
        } else {
            header('Location: settings.php?error=password_mismatch');
            exit();
        }
    } else {
        header('Location: settings.php?error=wrong_password');
        exit();
    }
}

// ============================================
// ADD NEW USER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    
    if ($check->rowCount() > 0) {
        header('Location: settings.php?error=username_exists');
        exit();
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $full_name, $hashed_password, $role])) {
            header('Location: settings.php?success=user_added');
            exit();
        } else {
            header('Location: settings.php?error=add_failed');
            exit();
        }
    }
}

// ============================================
// DELETE USER
// ============================================
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        header('Location: settings.php?success=user_deleted');
        exit();
    } else {
        header('Location: settings.php?error=cannot_delete_self');
        exit();
    }
}

// ============================================
// MESSAGE HANDLING
// ============================================
if (isset($_GET['success'])) {
    $success_messages = [
        'theme' => 'Theme preference saved! Dark mode applied to entire system.',
        'barangay' => 'Barangay information saved successfully!',
        'password' => 'Password changed successfully!',
        'user_added' => 'User added successfully!',
        'user_deleted' => 'User deleted successfully!',
        'import' => sprintf(
            'Import completed! %d residents imported successfully, %d errors.',
            $_GET['imported'] ?? 0,
            $_GET['errors'] ?? 0
        )
    ];
    if (isset($success_messages[$_GET['success']])) {
        $message = $success_messages[$_GET['success']];
        $message_type = 'success';
    }
}

if (isset($_GET['error'])) {
    $error_messages = [
        'password_mismatch' => 'New passwords do not match!',
        'wrong_password' => 'Current password is incorrect!',
        'username_exists' => 'Username already exists!',
        'add_failed' => 'Error adding user!',
        'cannot_delete_self' => 'You cannot delete your own account!',
        'invalid_csv' => 'Invalid file format. Please upload a CSV file.',
        'no_file' => 'Please select a CSV file to upload.'
    ];
    if (isset($error_messages[$_GET['error']])) {
        $message = $error_messages[$_GET['error']];
        $message_type = 'danger';
    }
}

// Get current settings
$current_dark_mode = $_SESSION['dark_mode'] ?? 'light';
$barangay_name = $_SESSION['barangay_name'] ?? 'Barangay 410 Zone 42';
$barangay_email = $_SESSION['barangay_email'] ?? '';
$barangay_telephone = $_SESSION['barangay_telephone'] ?? '';
$barangay_captain = $_SESSION['barangay_captain'] ?? 'P/B MICHAEL JOHN M. REGALA';
$barangay_address = $_SESSION['barangay_address'] ?? 'Zone 42, District IV, Manila';

// Get all users
$users = $pdo->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id")->fetchAll();

// Get resident count for export info
$resident_count = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
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

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .settings-card {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: background 0.3s ease;
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

        .card-header h2 i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.6rem 0.75rem;
            font-size: 0.85rem;
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
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        /* DARK MODE TOGGLE BUTTON - FIXED */
        .dark-mode-toggle-card {
            background: var(--surface-2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .toggle-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .toggle-info i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .toggle-info span {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .toggle-info small {
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border);
            transition: 0.3s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        [data-theme="dark"] .alert-success {
            background: #064e3b;
            color: #d1fae5;
            border: 1px solid #065f46;
        }

        [data-theme="dark"] .alert-danger {
            background: #7f1d1d;
            color: #fecaca;
            border: 1px solid #991b1b;
        }

        .table-container {
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
        }

        .delete-btn {
            background: var(--secondary);
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .delete-btn:hover {
            opacity: 0.8;
            color: white;
        }

        .info-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .info-box {
            background: var(--surface-2);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.75rem;
        }

        .mt-3 {
            margin-top: 1rem;
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
            .settings-grid {
                grid-template-columns: 1fr;
            }
            .header-content {
                flex-direction: column;
                gap: 1rem;
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
                            <i class="fas fa-gear"></i>
                            Settings
                        </h1>
                        <p class="header-subtitle">Manage system preferences, data import/export, barangay information, and users</p>
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
                                <a href="settings.php" class="dropdown-item active">
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

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- DARK MODE TOGGLE - FIXED -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-moon"></i> Dark Mode</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="darkModeForm">
                            <div class="dark-mode-toggle-card">
                                <div class="toggle-container">
                                    <div class="toggle-info">
                                        <i class="fas <?php echo ($current_dark_mode == 'dark') ? 'fa-moon' : 'fa-sun'; ?>"></i>
                                        <div>
                                            <span><?php echo ($current_dark_mode == 'dark') ? 'Dark Mode Active' : 'Light Mode Active'; ?></span>
                                            <small>Toggle to switch between light and dark mode across the entire system</small>
                                        </div>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" id="darkModeCheckbox" <?php echo ($current_dark_mode == 'dark') ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <input type="hidden" name="dark_mode_toggle" id="darkModeValue" value="<?php echo $current_dark_mode; ?>">
                        </form>
                        <div style="margin-top: 0.75rem; padding: 0.5rem; background: var(--surface-2); border-radius: 8px;">
                            <small style="color: var(--text-secondary);">
                            </small>
                        </div>
                    </div>
                </div>

                <!-- IMPORT CSV -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-upload"></i> Import Residents (CSV)</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label class="form-label">Select CSV File</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                <div class="info-text">
                                    <i class="fas fa-info-circle"></i> 
                                    CSV file should have columns: Last Name, First Name, Middle Name, Suffix, Address, Birth Date, Gender, Civil Status, Occupation, Citizenship, Household Name
                                </div>
                            </div>
                            <div class="info-box">
                                <small>
                                    <i class="fas fa-download"></i> 
                                    <a href="?download_sample=1" style="color: var(--primary);">Download sample CSV template</a>
                                </small>
                            </div>
                            <button type="submit" name="import_csv" class="btn btn-primary mt-3">
                                <i class="fas fa-upload"></i> Import CSV
                            </button>
                        </form>
                    </div>
                </div>

                <!-- EXPORT TO EXCEL -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-download"></i> Export Data</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Export Residents Data</label>
                            <div class="info-box">
                                <small>
                                    <i class="fas fa-database"></i> 
                                    Total residents in database: <strong><?php echo number_format($resident_count); ?></strong>
                                </small>
                            </div>
                            <div class="info-text mt-2">
                                <i class="fas fa-file-excel"></i> 
                                Export all resident records to CSV format (compatible with Microsoft Excel)
                            </div>
                        </div>
                        <a href="?export_excel=1" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export to Excel (CSV)
                        </a>
                    </div>
                </div>

                <!-- Barangay Information -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-landmark"></i> Barangay Information</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Barangay Name</label>
                                <input type="text" name="barangay_name" class="form-control" value="<?php echo htmlspecialchars($barangay_name); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Barangay Captain</label>
                                <input type="text" name="barangay_captain" class="form-control" value="<?php echo htmlspecialchars($barangay_captain); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Barangay Address</label>
                                <input type="text" name="barangay_address" class="form-control" value="<?php echo htmlspecialchars($barangay_address); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="barangay_email" class="form-control" placeholder="barangay410@example.com" value="<?php echo htmlspecialchars($barangay_email); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Telephone Number</label>
                                <input type="text" name="barangay_telephone" class="form-control" placeholder="(02) 1234-5678" value="<?php echo htmlspecialchars($barangay_telephone); ?>">
                            </div>
                            <button type="submit" name="save_barangay" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Information
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Add New User -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="admin">Admin</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <button type="submit" name="add_user" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add User
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Users List -->
            <div class="settings-card mt-3">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> System Users</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete_user=<?php echo $user['id']; ?>" class="delete-btn" onclick="return confirm('Delete this user?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.7rem;">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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

        // FIXED DARK MODE TOGGLE - Works both ways
        const darkModeCheckbox = document.getElementById('darkModeCheckbox');
        const darkModeForm = document.getElementById('darkModeForm');
        const darkModeValue = document.getElementById('darkModeValue');
        const htmlElement = document.documentElement;

        function setDarkMode(isDark) {
            const mode = isDark ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', mode);
            darkModeValue.value = mode;
            
            // Submit form to save to session
            const formData = new FormData();
            formData.append('dark_mode_toggle', mode);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(() => {
                // Update the icon and text without reload
                const toggleInfo = document.querySelector('.toggle-info i');
                const toggleText = document.querySelector('.toggle-info span');
                if (mode === 'dark') {
                    toggleInfo.className = 'fas fa-moon';
                    toggleText.innerHTML = 'Dark Mode Active';
                } else {
                    toggleInfo.className = 'fas fa-sun';
                    toggleText.innerHTML = 'Light Mode Active';
                }
            });
        }

        if (darkModeCheckbox) {
            darkModeCheckbox.addEventListener('change', function(e) {
                setDarkMode(e.target.checked);
            });
        }
    </script>
</body>
</html>