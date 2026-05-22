<?php
// masterlist.php - Registered Senior Citizens with Password Protected Birthday Reminder
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'class.php';

$barangay_id = $_SESSION['barangay_id'];
$full_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$message = '';
$add_message = '';
$show_details = false;
$show_add_form = false;
$show_birthday_modal = false;
$selected_senior = null;
$password_error = '';
$add_password_error = '';
$birthday_password_error = '';

// Handle Password Verification for viewing details
if (isset($_POST['verify_password']) && isset($_POST['senior_id'])) {
    $entered_password = $_POST['password'];
    $senior_id = intval($_POST['senior_id']);
    $user_id = $_SESSION['user_id'];
    $pass_query = "SELECT password FROM users WHERE id = $user_id";
    $pass_result = mysqli_query($conn, $pass_query);
    if ($pass_result && mysqli_num_rows($pass_result) > 0) {
        $pass_row = mysqli_fetch_assoc($pass_result);
        $stored_password = $pass_row['password'];
        $verified = false;
        if ($entered_password === $stored_password) $verified = true;
        elseif (password_verify($entered_password, $stored_password)) $verified = true;
        elseif (md5($entered_password) === $stored_password) $verified = true;
        elseif (sha1($entered_password) === $stored_password) $verified = true;
        if ($verified) {
            $detail_query = "SELECT * FROM senior_citizens WHERE id = $senior_id AND barangay_id = $barangay_id";
            $detail_result = mysqli_query($conn, $detail_query);
            if ($detail_result && mysqli_num_rows($detail_result) > 0) {
                $selected_senior = mysqli_fetch_assoc($detail_result);
                $show_details = true;
            }
        } else { $password_error = 'Incorrect password. Please try again.'; }
    } else { $password_error = 'User not found!'; }
}

// Handle Password Verification for Birthday Reminder
if (isset($_POST['verify_birthday_password'])) {
    $entered_password = $_POST['birthday_password'];
    $user_id = $_SESSION['user_id'];
    $pass_query = "SELECT password FROM users WHERE id = $user_id";
    $pass_result = mysqli_query($conn, $pass_query);
    if ($pass_result && mysqli_num_rows($pass_result) > 0) {
        $pass_row = mysqli_fetch_assoc($pass_result);
        $stored_password = $pass_row['password'];
        $verified = false;
        if ($entered_password === $stored_password) $verified = true;
        elseif (password_verify($entered_password, $stored_password)) $verified = true;
        elseif (md5($entered_password) === $stored_password) $verified = true;
        elseif (sha1($entered_password) === $stored_password) $verified = true;
        if ($verified) $show_birthday_modal = true;
        else $birthday_password_error = 'Incorrect password. Please try again.';
    } else { $birthday_password_error = 'User not found!'; }
}

// Handle Password Verification for Add New Senior Form
if (isset($_POST['verify_add_password'])) {
    $entered_password = $_POST['add_password'];
    $user_id = $_SESSION['user_id'];
    $pass_query = "SELECT password FROM users WHERE id = $user_id";
    $pass_result = mysqli_query($conn, $pass_query);
    if ($pass_result && mysqli_num_rows($pass_result) > 0) {
        $pass_row = mysqli_fetch_assoc($pass_result);
        $stored_password = $pass_row['password'];
        $verified = false;
        if ($entered_password === $stored_password) $verified = true;
        elseif (password_verify($entered_password, $stored_password)) $verified = true;
        elseif (md5($entered_password) === $stored_password) $verified = true;
        elseif (sha1($entered_password) === $stored_password) $verified = true;
        if ($verified) $show_add_form = true;
        else $add_password_error = 'Incorrect password. Please try again.';
    } else { $add_password_error = 'User not found!'; }
}

// Handle Add New Senior
if (isset($_POST['add_senior'])) {
    $last   = mysqli_real_escape_string($conn, $_POST['last_name']);
    $first  = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle = mysqli_real_escape_string($conn, $_POST['middle_initial']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $birthdate  = $_POST['birthdate'];
    $date_parts = explode('/', $birthdate);
    $bmonth = intval($date_parts[0]);
    $bday   = intval($date_parts[1]);
    $byear  = intval($date_parts[2]);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $sql = "INSERT INTO senior_citizens (last_name, first_name, middle_name, gender, birth_month, birth_day, birth_year, address, contact_number, status, barangay_id)
            VALUES ('$last','$first','$middle','$gender',$bmonth,$bday,$byear,'$address','$contact','Active',$barangay_id)";
    if (mysqli_query($conn, $sql)) {
        $add_message = '<div class="alert success"><i class="fas fa-check-circle"></i> New senior citizen added successfully!</div>';
        $show_add_form = false;
        echo "<script>setTimeout(function(){ window.location.href='masterlist.php'; }, 1500);</script>";
    } else {
        $add_message = '<div class="alert error"><i class="fas fa-times-circle"></i> Error: ' . mysqli_error($conn) . '</div>';
    }
}

// Handle Status Update
if (isset($_POST['update_status']) && isset($_POST['senior_id'])) {
    $senior_id  = intval($_POST['senior_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $update_sql = "UPDATE senior_citizens SET status='$new_status' WHERE id=$senior_id AND barangay_id=$barangay_id";
    if (mysqli_query($conn, $update_sql)) {
        echo "<script>window.location.href='masterlist.php';</script>"; exit;
    } else {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Error updating status</div>';
    }
}

// Handle Edit Senior
if (isset($_POST['edit_senior'])) {
    $senior_id  = intval($_POST['senior_id']);
    $last   = mysqli_real_escape_string($conn, $_POST['last_name']);
    $first  = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle = mysqli_real_escape_string($conn, $_POST['middle_initial']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $birthdate  = $_POST['birthdate'];
    $date_parts = explode('/', $birthdate);
    $bmonth = intval($date_parts[0]);
    $bday   = intval($date_parts[1]);
    $byear  = intval($date_parts[2]);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $update_sql = "UPDATE senior_citizens SET last_name='$last', first_name='$first', middle_name='$middle', gender='$gender',
                   birth_month=$bmonth, birth_day=$bday, birth_year=$byear, address='$address', contact_number='$contact'
                   WHERE id=$senior_id AND barangay_id=$barangay_id";
    if (mysqli_query($conn, $update_sql)) {
        echo "<script>window.location.href='masterlist.php';</script>"; exit;
    } else {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Error: ' . mysqli_error($conn) . '</div>';
    }
}

// Build query for main list
$sql = "SELECT * FROM senior_citizens WHERE barangay_id = $barangay_id";
if ($search) $sql .= " AND (last_name LIKE '%$search%' OR first_name LIKE '%$search%' OR address LIKE '%$search%')";
$sql .= " ORDER BY last_name, first_name";
$result = mysqli_query($conn, $sql);

$current_month = intval(date('m'));
$current_day   = intval(date('d'));
$current_year  = intval(date('Y'));

function calculateAge($birth_month, $birth_day, $birth_year) {
    $today     = new DateTime();
    $birthdate = new DateTime("$birth_year-$birth_month-$birth_day");
    return $today->diff($birthdate)->y;
}

$birthday_by_month = array();
for ($i = 1; $i <= 12; $i++) $birthday_by_month[$i] = array();
$birthday_today      = array();
$birthday_this_month = array();

$birthday_query = "SELECT id, last_name, first_name, middle_name, birth_month, birth_day, birth_year, address, contact_number, status
                   FROM senior_citizens WHERE barangay_id=$barangay_id ORDER BY birth_month, birth_day";
$birthday_result = mysqli_query($conn, $birthday_query);

$month_names = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

while ($row = mysqli_fetch_assoc($birthday_result)) {
    $month      = intval($row['birth_month']);
    $day        = intval($row['birth_day']);
    $year       = intval($row['birth_year']);
    $full_name2 = $row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '');
    $age        = calculateAge($month, $day, $year);
    $status_emoji = ($row['status'] == 'Active') ? '😊' : '😢';
    if ($month >= 1 && $month <= 12) {
        $birthday_by_month[$month][] = ['name'=>$full_name2,'day'=>$day,'year'=>$year,'age'=>$age,'status'=>$status_emoji,'address'=>$row['address'],'contact'=>$row['contact_number']];
    }
    if ($month == $current_month && $day == $current_day)
        $birthday_today[] = ['name'=>$full_name2,'age'=>$age,'year'=>$year,'status'=>$status_emoji];
    if ($month == $current_month && $day != $current_day)
        $birthday_this_month[] = ['name'=>$full_name2,'day'=>$day,'age'=>$age,'status'=>$status_emoji];
}

for ($m = 1; $m <= 12; $m++)
    if (count($birthday_by_month[$m]) > 0)
        usort($birthday_by_month[$m], fn($a,$b) => $a['day'] - $b['day']);

if (count($birthday_this_month) > 0)
    usort($birthday_this_month, fn($a,$b) => $a['day'] - $b['day']);

$generated_timestamp = date('F j, Y  h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Citizens Masterlist · Barangay 409</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --navy:   #1e293b;
            --navy-2: #334155;
            --blue:   #3b82f6;
            --blue-lt:#eff6ff;
            --gold:   #f59e0b;
            --green:  #10b981;
            --red:    #ef4444;
            --bg:     #f1f5f9;
            --card:   #ffffff;
            --border: #e2e8f0;
            --text:   #1e293b;
            --muted:  #64748b;
            --shadow: 0 1px 3px rgba(0,0,0,.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10);
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Inter','Segoe UI',system-ui,sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
        }

        /* ── SIDEBAR ──────────────────────────────────── */
        .sidebar {
            width: 255px;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 40;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 22px 18px;
            background: linear-gradient(160deg,#1e293b,#0f172a);
            border-bottom: 1px solid rgba(255,255,255,.07);
        }

        .sidebar-logo img {
            width: 42px; height: 42px;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,.2);
            object-fit: cover;
        }

        .sidebar-logo h2 { font-size:14px; font-weight:700; color:#fff; line-height:1.2; }
        .sidebar-logo p  { font-size:11px; color:#94a3b8; margin-top:2px; }

        .nav-links { list-style:none; flex:1; padding:10px 0; }
        .nav-links li { padding:2px 10px; }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 10px;
            transition: all .18s;
            font-size: 13.5px;
            font-weight: 500;
        }

        .nav-links a i { width:18px; font-size:14px; }
        .nav-links a:hover { background:rgba(255,255,255,.07); color:#fff; }
        .nav-links a.active { background:var(--blue); color:#fff; }

        .user-info-side {
            border-top: 1px solid rgba(255,255,255,.07);
            padding: 14px 10px;
        }

        .user-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            margin-bottom: 10px;
            padding: 10px 14px;
            background: rgba(255,255,255,.05);
            border-radius: 10px;
        }

        .user-detail i { font-size:1.7rem; color:#93c5fd; flex-shrink:0; }
        .user-detail strong { font-size:13px; display:block; }
        .user-detail span   { font-size:11px; color:#94a3b8; }

        .logout-btn {
            display: flex; align-items: center; justify-content: center; gap:8px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: all .18s;
            cursor: pointer;
        }

        .logout-btn:hover { background:rgba(239,68,68,.15); color:#fca5a5; border-color:rgba(239,68,68,.3); }

        /* ── MAIN ─────────────────────────────────────── */
        .main-content { margin-left:255px; display:flex; flex-direction:column; min-height:100vh; }

        .top-header {
            background: var(--card);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow);
            position: sticky; top:0; z-index:30;
        }

        .header-left h1 { font-size:17px; font-weight:700; color:var(--navy); }
        .header-left p  { font-size:11.5px; color:var(--muted); margin-top:2px; }

        .user-badge {
            display: flex; align-items: center; gap:8px;
            background: var(--blue-lt);
            color: var(--blue);
            border: 1px solid #bfdbfe;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .page-content { padding: 24px 28px; flex:1; }

        /* ── BUTTONS ──────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap:6px;
            padding: 9px 18px;
            border-radius: 10px; border: none; cursor: pointer;
            font-size: 13px; font-weight: 600;
            transition: all .18s ease;
            text-decoration: none; white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            font-family: inherit;
        }

        .btn:hover  { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.14); }
        .btn:active { transform:translateY(0); }

        .btn-primary { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
        .btn-primary:hover { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; }
        .btn-success { background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
        .btn-success:hover { background:linear-gradient(135deg,#059669,#047857); color:#fff; }
        .btn-warning { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
        .btn-warning:hover { background:linear-gradient(135deg,#d97706,#b45309); color:#fff; }
        .btn-danger  { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; }
        .btn-danger:hover  { background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; }
        .btn-navy   { background:linear-gradient(135deg,#1e293b,#334155); color:#fff; }
        .btn-navy:hover { background:linear-gradient(135deg,#334155,#1e293b); color:#fff; }
        .btn-outline {
            background:var(--card); color:var(--text);
            border:1.5px solid var(--border);
            box-shadow:0 1px 2px rgba(0,0,0,.05);
        }
        .btn-outline:hover { border-color:var(--blue); color:var(--blue); }
        .btn-sm { padding:6px 13px; font-size:12px; border-radius:8px; }

        .button-group { display:flex; gap:10px; flex-wrap:wrap; }

        /* ── PAGE HEADER ──────────────────────────────── */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap; gap:12px;
        }

        .page-header h2 {
            font-size: 1.2rem; font-weight:700; color:var(--navy);
            display:flex; align-items:center; gap:8px;
        }

        /* ── SEARCH ───────────────────────────────────── */
        .search-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex; gap:10px; align-items:center; flex-wrap:wrap;
            box-shadow: var(--shadow);
        }

        .search-input {
            flex:1; min-width:220px;
            padding: 9px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px; font-family:inherit; outline:none;
            transition: border-color .2s;
        }

        .search-input:focus { border-color:var(--blue); }

        /* ── ADD FORM ─────────────────────────────────── */
        .add-senior-form {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }

        .add-senior-form h3 {
            font-size:15px; font-weight:700; color:var(--navy);
            margin-bottom:16px;
            display:flex; align-items:center; gap:8px;
        }

        .form-control {
            width:100%; padding:10px 14px;
            border:1.5px solid var(--border); border-radius:10px;
            font-size:13px; font-family:inherit; outline:none;
            transition: border-color .2s;
            margin-bottom:12px;
        }

        .form-control:focus { border-color:var(--blue); }
        .form-row { display:flex; gap:12px; flex-wrap:wrap; }
        .form-group { flex:1; min-width:140px; }

        /* ── TABLE ────────────────────────────────────── */
        .table-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table-wrap { overflow-x:auto; }

        table { width:100%; border-collapse:collapse; font-size:13px; }

        thead th {
            background: var(--navy);
            color: #fff;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#f8fafc; }
        td { padding:12px 16px; vertical-align:middle; }

        .clickable-name {
            color:var(--navy); cursor:pointer;
            text-decoration:none; font-weight:600; font-size:13px;
        }

        .clickable-name:hover { color:var(--blue); }

        .birthday-today { background:#f0fdf4 !important; }

        .badge {
            display:inline-flex; align-items:center; gap:4px;
            padding:3px 10px; border-radius:20px;
            font-size:11px; font-weight:600;
        }

        .badge-birthday { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
        .badge-today    { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }

        .age-pill {
            background:var(--blue-lt); color:var(--blue);
            border:1px solid #bfdbfe;
            padding:3px 10px; border-radius:20px;
            font-size:12px; font-weight:600; white-space:nowrap;
        }

        /* ── MODALS ───────────────────────────────────── */
        .modal {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.5);
            backdrop-filter:blur(4px);
            z-index:1000;
            justify-content:center; align-items:center;
            padding:16px;
        }

        .modal-box {
            background:var(--card);
            border-radius:20px;
            width:100%; max-width:480px;
            max-height:88vh; overflow-y:auto;
            box-shadow:0 24px 60px rgba(0,0,0,.22);
        }

        .modal-box-lg { max-width:760px; }

        .modal-hd {
            background:var(--navy);
            color:#fff;
            padding:18px 24px;
            border-radius:20px 20px 0 0;
            display:flex; align-items:center; gap:10px;
        }

        .modal-hd h3 { font-size:15px; font-weight:700; color:#fff; margin:0; flex:1; }
        .modal-hd .close-x {
            background:rgba(255,255,255,.1); border:none; color:#fff;
            width:30px; height:30px; border-radius:8px;
            cursor:pointer; font-size:16px;
            display:flex; align-items:center; justify-content:center;
            transition:background .15s;
        }
        .modal-hd .close-x:hover { background:rgba(255,255,255,.2); }

        .modal-bd { padding:24px; }

        .pw-hint {
            background:#f8fafc; border:1px solid var(--border);
            border-radius:10px; padding:12px 16px;
            font-size:12.5px; color:var(--muted); margin-bottom:16px;
        }

        /* ── INFO ROWS ────────────────────────────────── */
        .info-row {
            display:flex; padding:10px 0;
            border-bottom:1px solid var(--border);
        }

        .info-label {
            width:120px; font-weight:600;
            font-size:11px; color:var(--muted);
            text-transform:uppercase; letter-spacing:.04em;
            padding-right:8px; flex-shrink:0;
        }

        .info-value { flex:1; color:var(--text); font-size:13.5px; }

        .edit-form {
            margin-top:20px; padding-top:20px;
            border-top:2px solid var(--border);
            display:none;
        }

        .edit-form h4 {
            font-size:13px; font-weight:700; color:var(--navy);
            margin-bottom:14px;
            display:flex; align-items:center; gap:6px;
        }

        /* ── BIRTHDAY MODAL ───────────────────────────── */
        .bday-header {
            display:flex; align-items:center; gap:16px;
            margin-bottom:18px; padding-bottom:16px;
            border-bottom:2px solid var(--border);
        }

        .brgy-logo {
            width:62px; height:62px; border-radius:50%;
            object-fit:cover; border:3px solid #bfdbfe; flex-shrink:0;
        }

        .bday-title h3 { font-size:17px; font-weight:700; color:var(--navy); margin:0; }
        .bday-title p  { font-size:12px; color:var(--muted); margin:4px 0 0; }

        .timestamp {
            background:#f8fafc; border:1px solid var(--border);
            border-radius:8px; padding:7px 14px;
            font-size:12px; color:var(--muted); margin-bottom:16px;
            display:flex; align-items:center; gap:6px;
        }

        .month-hd {
            background:var(--navy); color:#fff;
            padding:9px 16px; border-radius:10px;
            margin:16px 0 8px; font-size:14px; font-weight:600;
            display:flex; align-items:center; gap:8px;
        }

        .month-hd.today-hd { background:linear-gradient(135deg,#f59e0b,#d97706); }

        .sub-month-hd {
            background:#eff6ff; color:var(--navy);
            border:1px solid #bfdbfe;
            padding:7px 14px; border-radius:8px;
            font-size:13px; font-weight:600; margin:12px 0 6px;
        }

        .bday-list { list-style:none; padding:0; }

        .bday-list li {
            background:#f8fafc; border:1px solid var(--border);
            margin:5px 0; padding:11px 16px; border-radius:10px;
            border-left:4px solid var(--gold);
            display:flex; justify-content:space-between;
            align-items:center; flex-wrap:wrap; gap:8px;
        }

        .bday-name { font-weight:700; font-size:13.5px; color:var(--navy); }
        .bday-date { color:#d97706; font-weight:600; font-size:12.5px; }
        .bday-age  { color:var(--green); font-size:12px; font-weight:600; }

        /* ── ALERTS ───────────────────────────────────── */
        .alert {
            padding:11px 16px; border-radius:10px;
            margin-bottom:16px; font-size:13px;
            display:flex; align-items:center; gap:8px;
        }

        .alert.success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .alert.error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

        /* ── FOOTER ───────────────────────────────────── */
        .footer {
            text-align:center; padding:18px 28px;
            color:var(--muted); border-top:1px solid var(--border);
            margin-top:24px; font-size:12px;
        }

        /* ── RESPONSIVE ───────────────────────────────── */
        @media (max-width:768px) {
            .sidebar { width:68px; }
            .sidebar-logo h2,.sidebar-logo p,
            .nav-links a span,.user-detail strong,
            .user-detail span,.logout-btn span { display:none; }
            .main-content { margin-left:68px; }
            .page-content { padding:16px; }
            .top-header { padding:12px 16px; }
        }

        @media print {
            .sidebar,.top-header,.page-header,.search-card,.btn,
            .modal-hd .btn-outline { display:none !important; }
            .bday-list li { break-inside:avoid; }
        }
    </style>
</head>
<body>
<div style="display:flex; min-height:100vh;">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="barangay_logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/42?text=B'">
            <div><h2>Barangay 409</h2><p>Senior Info System</p></div>
        </div>
        <ul class="nav-links">
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="masterlist.php" class="active"><i class="fas fa-users"></i> <span>Senior Citizens</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> <span>Reports</span></a></li>
            <li><a href="equipment.php"><i class="fas fa-tools"></i> <span>Equipment</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> <span>Profile</span></a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li><a href="bulk_upload.php"><i class="fas fa-upload"></i> <span>Bulk Upload</span></a></li>
            <?php endif; ?>
        </ul>
        <div class="user-info-side">
            <div class="user-detail">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?php echo htmlspecialchars($full_name); ?></strong>
                    <span><?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOP HEADER -->
        <header class="top-header">
            <div class="header-left">
                <h1>Barangay 409 Senior Citizen Information System</h1>
                <p>Zone 42 Sta. Teresita, Sampaloc, Manila</p>
            </div>
            <a href="profile.php" class="user-badge">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($full_name); ?>
            </a>
        </header>

        <div class="page-content">

            <?php if ($message)     echo $message; ?>
            <?php if ($add_message) echo $add_message; ?>

            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-users"></i> Registered Senior Citizens</h2>
                <div class="button-group">
                    <button class="btn btn-warning" onclick="openBirthdayPasswordModal()">
                        <i class="fas fa-birthday-cake"></i> Birthday Reminder
                    </button>
                    <button class="btn btn-success" onclick="openAddPasswordModal()">
                        <i class="fas fa-plus"></i> Add New Senior
                    </button>
                </div>
            </div>

            <!-- Add Senior Form (shown after password verify) -->
            <?php if ($show_add_form): ?>
            <div class="add-senior-form">
                <h3><i class="fas fa-user-plus"></i> Add New Senior Citizen</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="last_name" placeholder="Last Name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <input type="text" name="first_name" placeholder="First Name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <input type="text" name="middle_initial" placeholder="M.I." maxlength="1" style="text-transform:uppercase" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <select name="gender" required class="form-control">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="text" name="birthdate" id="birthdate" placeholder="Birthdate (MM/DD/YYYY)" required autocomplete="off" class="form-control">
                        </div>
                    </div>
                    <textarea name="address" placeholder="Complete Address" rows="2" required class="form-control"></textarea>
                    <input type="text" name="contact_number" placeholder="Contact Number" class="form-control">
                    <div class="button-group">
                        <button type="submit" name="add_senior" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Senior Citizen
                        </button>
                        <button type="button" class="btn btn-outline" onclick="window.location.href='masterlist.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Search -->
            <form method="GET" class="search-card">
                <input type="text" name="search" class="search-input"
                       placeholder="Search by name or address…"
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="masterlist.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>

            <!-- Table -->
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <div class="table-card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:60px; text-align:center;">#</th>
                                <th>Name</th>
                                <th style="width:110px; text-align:center;">Age</th>
                                <th style="width:130px;">Birthdate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)):
                                $full      = $row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '');
                                $bdate_display = str_pad($row['birth_day'], 2, '0', STR_PAD_LEFT) . '-'
                                               . str_pad($row['birth_month'], 2, '0', STR_PAD_LEFT) . '-'
                                               . $row['birth_year'];
                                $is_today  = ($row['birth_month'] == $current_month && $row['birth_day'] == $current_day);
                                $is_month  = ($row['birth_month'] == $current_month);
                                $age       = calculateAge($row['birth_month'], $row['birth_day'], $row['birth_year']);
                            ?>
                            <tr class="<?php echo $is_today ? 'birthday-today' : ''; ?>">
                                <td style="text-align:center; color:var(--muted); font-weight:600;">
                                    <?php echo $row['id']; ?>
                                </td>
                                <td>
                                    <a href="?view_details=<?php echo $row['id']; ?>" class="clickable-name">
                                        <?php echo htmlspecialchars($full); ?>
                                    </a>
                                    <?php if ($is_today): ?>
                                        <span class="badge badge-today" style="margin-left:6px;">🎉 Today!</span>
                                    <?php elseif ($is_month): ?>
                                        <span class="badge badge-birthday" style="margin-left:6px;">🎂 This Month</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="age-pill"><?php echo $age; ?> yrs</span>
                                </td>
                                <td style="color:var(--muted); font-size:12.5px;"><?php echo $bdate_display; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align:center; padding:60px 30px; background:var(--card); border-radius:16px; border:1px solid var(--border); color:var(--muted);">
                <i class="fas fa-users" style="font-size:2.5rem; opacity:.25; display:block; margin-bottom:14px;"></i>
                <p style="font-size:15px; font-weight:600;">No records found</p>
                <p style="font-size:13px; margin-top:4px;">
                    <?php echo $search ? "No results for \"" . htmlspecialchars($search) . "\"" : "Add senior citizens to get started."; ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="footer">
                © <?php echo date('Y'); ?> Barangay 409 Senior Citizen Information System · Zone 42 Sta. Teresita, Sampaloc, Manila
            </div>
        </div>
    </div>
</div>


<!-- ── PASSWORD MODAL: Birthday Reminder ───────────────────────── -->
<div id="birthdayPasswordModal" class="modal">
    <div class="modal-box">
        <div class="modal-hd">
            <i class="fas fa-lock"></i>
            <h3>Admin Verification</h3>
            <button class="close-x" onclick="closeBirthdayPasswordModal()">×</button>
        </div>
        <div class="modal-bd">
            <div class="pw-hint"><i class="fas fa-info-circle"></i> Enter your admin password to view the birthday reminder.</div>
            <?php if ($birthday_password_error): ?>
                <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo $birthday_password_error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="birthday_password" placeholder="Enter admin password" autocomplete="off" required class="form-control">
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
                    <button type="button" class="btn btn-outline" onclick="closeBirthdayPasswordModal()">Cancel</button>
                    <button type="submit" name="verify_birthday_password" class="btn btn-primary">
                        <i class="fas fa-unlock"></i> Verify
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ── BIRTHDAY REMINDER MODAL ─────────────────────────────────── -->
<?php if ($show_birthday_modal): ?>
<div id="birthdayModal" class="modal" style="display:flex;">
    <div class="modal-box modal-box-lg">
        <div class="modal-hd">
            <i class="fas fa-birthday-cake"></i>
            <h3>Birthday Reminder</h3>
            <a href="masterlist.php" class="close-x">×</a>
        </div>
        <div class="modal-bd">
            <div class="bday-header">
                <img src="barangay_logo.png" alt="Logo" class="brgy-logo" onerror="this.src='https://via.placeholder.com/62?text=B'">
                <div class="bday-title">
                    <h3>Senior Citizens Birthday Reminder</h3>
                    <p>Barangay 409, Zone 42 Sta. Teresita, Sampaloc, Manila</p>
                </div>
            </div>

            <div class="timestamp">
                <i class="far fa-calendar-alt"></i> Generated: <?php echo $generated_timestamp; ?>
            </div>

            <?php if (count($birthday_today) > 0): ?>
                <div class="month-hd today-hd"><i class="fas fa-bell"></i> 🎉 Today's Birthdays — <?php echo date('F j, Y'); ?></div>
                <ul class="bday-list">
                    <?php foreach ($birthday_today as $bday): ?>
                    <li>
                        <span class="bday-name">🎂 <?php echo htmlspecialchars($bday['name']); ?></span>
                        <span class="bday-age"><?php echo $bday['age']; ?> years old <?php echo $bday['status']; ?></span>
                        <span class="bday-date">🎁 Today is their special day!</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (count($birthday_this_month) > 0): ?>
                <div class="month-hd"><i class="fas fa-calendar-alt"></i> Other Birthdays This Month (<?php echo $month_names[$current_month].' '.$current_year; ?>)</div>
                <ul class="bday-list">
                    <?php foreach ($birthday_this_month as $bday): ?>
                    <li>
                        <span class="bday-name">🎂 <?php echo htmlspecialchars($bday['name']); ?></span>
                        <span class="bday-date">📅 <?php echo str_pad($bday['day'],2,'0',STR_PAD_LEFT).'-'.str_pad($current_month,2,'0',STR_PAD_LEFT).'-'.$current_year; ?></span>
                        <span class="bday-age">Turning <?php echo $bday['age']+1; ?> <?php echo $bday['status']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="month-hd" style="margin-top:20px;"><i class="fas fa-list"></i> Complete Birthday List (All Months)</div>

            <?php
            $has_birthdays = false;
            for ($m = 1; $m <= 12; $m++):
                if (!empty($birthday_by_month[$m])):
                    $has_birthdays = true;
            ?>
                <div class="sub-month-hd"><i class="fas fa-calendar"></i> <?php echo $month_names[$m]; ?></div>
                <ul class="bday-list">
                    <?php foreach ($birthday_by_month[$m] as $bday): ?>
                    <li>
                        <span class="bday-name"><?php echo htmlspecialchars($bday['name']); ?> <?php echo $bday['status']; ?></span>
                        <span class="bday-date">📅 <?php echo str_pad($bday['day'],2,'0',STR_PAD_LEFT).'-'.str_pad($m,2,'0',STR_PAD_LEFT).'-'.$bday['year']; ?></span>
                        <span class="bday-age">🎂 <?php echo $bday['age']; ?> yrs old</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php
                endif;
            endfor;
            if (!$has_birthdays):
            ?>
                <p style="text-align:center; padding:30px; color:var(--muted); font-style:italic;">
                    <i class="fas fa-info-circle"></i> No registered senior citizens yet.
                </p>
            <?php endif; ?>

            <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
                <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <a href="masterlist.php" class="btn btn-navy"><i class="fas fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ── PASSWORD MODAL: Add New Senior ─────────────────────────── -->
<div id="addPasswordModal" class="modal">
    <div class="modal-box">
        <div class="modal-hd">
            <i class="fas fa-lock"></i>
            <h3>Admin Verification</h3>
            <button class="close-x" onclick="closeAddPasswordModal()">×</button>
        </div>
        <div class="modal-bd">
            <div class="pw-hint"><i class="fas fa-info-circle"></i> Enter your admin password to add a new senior citizen.</div>
            <?php if ($add_password_error): ?>
                <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo $add_password_error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="add_password" placeholder="Enter admin password" autocomplete="off" required class="form-control">
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
                    <button type="button" class="btn btn-outline" onclick="closeAddPasswordModal()">Cancel</button>
                    <button type="submit" name="verify_add_password" class="btn btn-primary">
                        <i class="fas fa-unlock"></i> Verify
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ── PASSWORD MODAL: View Details ───────────────────────────── -->
<?php if (isset($_GET['view_details']) && !$show_details): ?>
<div id="passwordModal" class="modal" style="display:flex;">
    <div class="modal-box">
        <div class="modal-hd">
            <i class="fas fa-lock"></i>
            <h3>Admin Verification</h3>
            <a href="masterlist.php" class="close-x">×</a>
        </div>
        <div class="modal-bd">
            <div class="pw-hint"><i class="fas fa-info-circle"></i> Enter your admin password to view complete details.</div>
            <?php if ($password_error): ?>
                <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo $password_error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="senior_id" value="<?php echo intval($_GET['view_details']); ?>">
                <input type="password" name="password" placeholder="Enter admin password" autocomplete="off" required class="form-control">
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
                    <a href="masterlist.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" name="verify_password" class="btn btn-primary">
                        <i class="fas fa-unlock"></i> Verify
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ── SENIOR DETAILS MODAL ────────────────────────────────────── -->
<?php if ($show_details && $selected_senior):
    $senior        = $selected_senior;
    $bdate_display = str_pad($senior['birth_day'],2,'0',STR_PAD_LEFT).'-'.str_pad($senior['birth_month'],2,'0',STR_PAD_LEFT).'-'.$senior['birth_year'];
    $age           = calculateAge($senior['birth_month'], $senior['birth_day'], $senior['birth_year']);
    $isActive      = $senior['status'] == 'Active';
?>
<div id="detailsModal" class="modal" style="display:flex;">
    <div class="modal-box modal-box-lg">
        <div class="modal-hd">
            <i class="fas fa-user-circle"></i>
            <h3>Senior Citizen Details</h3>
            <a href="masterlist.php" class="close-x">×</a>
        </div>
        <div class="modal-bd">
            <div class="info-row"><div class="info-label">ID</div><div class="info-value"><strong>#<?php echo $senior['id']; ?></strong></div></div>
            <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><strong><?php echo htmlspecialchars($senior['last_name'].', '.$senior['first_name'].' '.($senior['middle_name']??'')); ?></strong></div></div>
            <div class="info-row"><div class="info-label">Age</div><div class="info-value"><?php echo $age; ?> years old</div></div>
            <div class="info-row"><div class="info-label">Birthdate</div><div class="info-value"><?php echo $bdate_display; ?></div></div>
            <div class="info-row"><div class="info-label">Gender</div><div class="info-value"><?php echo htmlspecialchars($senior['gender']); ?></div></div>
            <div class="info-row"><div class="info-label">Address</div><div class="info-value"><?php echo nl2br(htmlspecialchars($senior['address']??'—')); ?></div></div>
            <div class="info-row"><div class="info-label">Contact</div><div class="info-value"><?php echo htmlspecialchars($senior['contact_number']??'—'); ?></div></div>
            <div class="info-row" style="border:none;">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span style="font-size:1.5rem;"><?php echo $isActive ? '😊' : '😢'; ?></span>
                    <span class="badge <?php echo $isActive ? 'badge-today' : ''; ?>" style="margin-left:8px; <?php echo !$isActive ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;' : ''; ?>">
                        <?php echo $senior['status']; ?>
                    </span>
                </div>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px;">
                <button class="btn btn-primary btn-sm" onclick="toggleEditForm()"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn btn-warning btn-sm" onclick="toggleStatusForm()"><i class="fas fa-exchange-alt"></i> Change Status</button>
            </div>

            <!-- Edit Form -->
            <div id="editForm" class="edit-form">
                <h4><i class="fas fa-edit"></i> Edit Information</h4>
                <form method="POST" action="masterlist.php">
                    <input type="hidden" name="senior_id" value="<?php echo $senior['id']; ?>">
                    <div class="form-row">
                        <div class="form-group"><input type="text" name="last_name"      value="<?php echo htmlspecialchars($senior['last_name']); ?>"        placeholder="Last Name"     required class="form-control"></div>
                        <div class="form-group"><input type="text" name="first_name"     value="<?php echo htmlspecialchars($senior['first_name']); ?>"       placeholder="First Name"    required class="form-control"></div>
                        <div class="form-group"><input type="text" name="middle_initial" value="<?php echo htmlspecialchars($senior['middle_name']??''); ?>" placeholder="M.I." maxlength="1" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <select name="gender" required class="form-control">
                                <option value="Male"   <?php echo $senior['gender']=='Male'  ?'selected':''; ?>>Male</option>
                                <option value="Female" <?php echo $senior['gender']=='Female'?'selected':''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="text" name="birthdate" class="edit-birthdate form-control"
                                   value="<?php echo str_pad($senior['birth_month'],2,'0',STR_PAD_LEFT).'/'.str_pad($senior['birth_day'],2,'0',STR_PAD_LEFT).'/'.$senior['birth_year']; ?>"
                                   placeholder="Birthdate (MM/DD/YYYY)" required autocomplete="off">
                        </div>
                    </div>
                    <textarea name="address" placeholder="Complete Address" rows="2" required class="form-control"><?php echo htmlspecialchars($senior['address']??''); ?></textarea>
                    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($senior['contact_number']??''); ?>" placeholder="Contact Number" class="form-control">
                    <div style="display:flex; gap:10px;">
                        <button type="submit" name="edit_senior" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Save Changes</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="toggleEditForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Status Form -->
            <div id="statusForm" class="edit-form">
                <h4><i class="fas fa-exchange-alt"></i> Change Status</h4>
                <form method="POST" action="masterlist.php">
                    <input type="hidden" name="senior_id" value="<?php echo $senior['id']; ?>">
                    <select name="status" required class="form-control" style="max-width:200px;">
                        <option value="Active"   <?php echo $senior['status']=='Active'  ?'selected':''; ?>>😊 Active</option>
                        <option value="Inactive" <?php echo $senior['status']=='Inactive'?'selected':''; ?>>😢 Inactive</option>
                    </select>
                    <div style="display:flex; gap:10px; margin-top:4px;">
                        <button type="submit" name="update_status" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Update</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="toggleStatusForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div style="margin-top:20px; text-align:right;">
                <a href="masterlist.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script>
$(function() {
    $("#birthdate").datepicker({ changeMonth:true, changeYear:true, yearRange:"1900:2025", dateFormat:"mm/dd/yy", defaultDate:"01/01/1960" });
    $(".edit-birthdate").datepicker({ changeMonth:true, changeYear:true, yearRange:"1900:2025", dateFormat:"mm/dd/yy" });
});

function openAddPasswordModal()      { document.getElementById('addPasswordModal').style.display = 'flex'; }
function closeAddPasswordModal()     { document.getElementById('addPasswordModal').style.display = 'none'; }
function openBirthdayPasswordModal() { document.getElementById('birthdayPasswordModal').style.display = 'flex'; }
function closeBirthdayPasswordModal(){ document.getElementById('birthdayPasswordModal').style.display = 'none'; }

function toggleEditForm() {
    var f = document.getElementById('editForm');
    f.style.display = (f.style.display === 'block') ? 'none' : 'block';
}

function toggleStatusForm() {
    var f = document.getElementById('statusForm');
    f.style.display = (f.style.display === 'block') ? 'none' : 'block';
}

window.onclick = function(e) {
    ['birthdayModal','detailsModal','passwordModal','addPasswordModal','birthdayPasswordModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && e.target === el) el.style.display = 'none';
    });
};
</script>
</body>
</html>
