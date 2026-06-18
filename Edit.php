<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin.php"); exit(); }
include 'Residents_DB.php';
include 'generate_id.php';
include 'role_helper.php';
// Block Chairman from editing — secretary only
if(!$can_edit){
    header("Location: Display_List.php?denied=edit"); exit();
}
ensureResidentCodeColumn($conn);

if (!isset($_GET['id'])) { header("Location: Display_List.php"); exit(); }
$id = intval($_GET['id']);

$res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM residents WHERE id=$id LIMIT 1"));
if (!$res) { header("Location: Display_List.php"); exit(); }

$pets_r = mysqli_query($conn, "SELECT * FROM pets WHERE resident_id=$id ORDER BY id");
$pets   = [];
while ($p = mysqli_fetch_assoc($pets_r)) $pets[] = $p;

$success = false;
$error   = '';

// ── Handle save ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_save'])) {
    csrf_verify();
    $submitted_code = trim($_POST['resident_code'] ?? '');
    if (!empty($submitted_code) && isForeignCode($submitted_code)) {
        logSecurityEvent($conn, 'FOREIGN_CODE_EDIT', "Foreign code '$submitted_code' on ID $id", $_SERVER['REMOTE_ADDR']);
        $error = "⚠ Security alert: Invalid resident code format. Changes rejected.";
    } else {
        $fn   = mysqli_real_escape_string($conn, trim($_POST['first_name']         ?? ''));
        $mn   = mysqli_real_escape_string($conn, trim($_POST['middle_name']        ?? ''));
        $ln   = mysqli_real_escape_string($conn, trim($_POST['last_name']          ?? ''));
        $sx   = mysqli_real_escape_string($conn, trim($_POST['suffix']             ?? ''));
        $hof  = mysqli_real_escape_string($conn, $_POST['head_of_family']          ?? '');
        $rel  = mysqli_real_escape_string($conn, trim($_POST['relationship']       ?? ''));
        if ($hof === 'No') {
            $hfn = mysqli_real_escape_string($conn, trim($_POST['head_first_name']  ?? ''));
            $hmn = mysqli_real_escape_string($conn, trim($_POST['head_middle_name'] ?? ''));
            $hln = mysqli_real_escape_string($conn, trim($_POST['head_last_name']   ?? ''));
            $hsx = mysqli_real_escape_string($conn, trim($_POST['head_suffix']      ?? ''));
        } else { $hfn=$fn; $hmn=$mn; $hln=$ln; $hsx=$sx; }
        $padr = mysqli_real_escape_string($conn, trim($_POST['perm_address']       ?? ''));
        $prv  = mysqli_real_escape_string($conn, trim($_POST['prov_address']       ?? ''));
        $ho   = mysqli_real_escape_string($conn, $_POST['house_owner']             ?? '');
        $hd   = mysqli_real_escape_string($conn, trim($_POST['house_details']      ?? ''));
        $yib  = intval($_POST['years_in_barangay']                                 ?? 0);
        $vtr  = mysqli_real_escape_string($conn, $_POST['voter']                   ?? '');
        $pno  = mysqli_real_escape_string($conn, trim($_POST['precinct_no']        ?? ''));
        $mob  = mysqli_real_escape_string($conn, trim($_POST['mobile']             ?? ''));
        $land = mysqli_real_escape_string($conn, trim($_POST['landline']           ?? ''));
        $em   = mysqli_real_escape_string($conn, trim($_POST['email']              ?? ''));
        $bd   = mysqli_real_escape_string($conn, $_POST['birthdate']               ?? '');
        $gen  = mysqli_real_escape_string($conn, $_POST['gender']                  ?? '');
        $ms   = mysqli_real_escape_string($conn, $_POST['marital_status']          ?? '');
        $rel2 = mysqli_real_escape_string($conn, $_POST['religion']                ?? '');
        $cit  = mysqli_real_escape_string($conn, $_POST['citizenship']             ?? '');
        $edu  = mysqli_real_escape_string($conn, $_POST['education']               ?? '');
        $emp  = mysqli_real_escape_string($conn, $_POST['employment_status']       ?? '');
        $occ  = mysqli_real_escape_string($conn, trim($_POST['occupation']         ?? ''));
        $empr = mysqli_real_escape_string($conn, trim($_POST['employer']           ?? ''));
        $wh   = mysqli_real_escape_string($conn, trim($_POST['work_hours']         ?? ''));
        $gl   = mysqli_real_escape_string($conn, trim($_POST['grade_level']        ?? ''));
        $sn   = mysqli_real_escape_string($conn, trim($_POST['school_name']        ?? ''));
        $osy  = mysqli_real_escape_string($conn, $_POST['out_of_school_youth']     ?? '');
        $hcar = mysqli_real_escape_string($conn, $_POST['has_car']                 ?? '');
        $cbr  = mysqli_real_escape_string($conn, trim($_POST['car_brand']          ?? ''));
        $cmo  = mysqli_real_escape_string($conn, trim($_POST['car_model']          ?? ''));
        $ccl  = mysqli_real_escape_string($conn, trim($_POST['car_color']          ?? ''));
        $cpl  = mysqli_real_escape_string($conn, trim($_POST['car_plate']          ?? ''));
        $hmt  = mysqli_real_escape_string($conn, $_POST['has_motorcycle']          ?? '');
        $mbr  = mysqli_real_escape_string($conn, trim($_POST['motor_brand']        ?? ''));
        $mmo  = mysqli_real_escape_string($conn, trim($_POST['motor_model']        ?? ''));
        $mcl  = mysqli_real_escape_string($conn, trim($_POST['motor_color']        ?? ''));
        $mpl  = mysqli_real_escape_string($conn, trim($_POST['motor_plate']        ?? ''));
        $issr = mysqli_real_escape_string($conn, $_POST['is_senior']               ?? 'No');
        $osca = mysqli_real_escape_string($conn, trim($_POST['osca_id']            ?? ''));
        $pwd  = mysqli_real_escape_string($conn, $_POST['pwd_status']              ?? 'No');
        $pwdi = mysqli_real_escape_string($conn, trim($_POST['pwd_id']             ?? ''));
        $dis  = mysqli_real_escape_string($conn, trim($_POST['disability_type']    ?? ''));
        $solo = mysqli_real_escape_string($conn, $_POST['solo_parent_status']      ?? 'No');
        $slid = mysqli_real_escape_string($conn, trim($_POST['solo_parent_id']     ?? ''));
        $hpts = (isset($_POST['has_pets']) && $_POST['has_pets'] === 'Yes') ? 1 : 0;
        $code = $submitted_code ?: mysqli_real_escape_string($conn, $res['resident_code'] ?? '');

        $sql = "UPDATE residents SET
            first_name='$fn', middle_name='$mn', last_name='$ln', suffix='$sx',
            head_of_family='$hof', relationship='$rel',
            head_first_name='$hfn', head_middle_name='$hmn', head_last_name='$hln', head_suffix='$hsx',
            perm_address='$padr', prov_address='$prv', house_owner='$ho', house_details='$hd',
            years_in_barangay=$yib, voter='$vtr', precinct_no='$pno',
            mobile='$mob', landline='$land', email='$em', birthdate='$bd',
            gender='$gen', marital_status='$ms', religion='$rel2', citizenship='$cit',
            education='$edu', employment_status='$emp', occupation='$occ',
            employer='$empr', work_hours='$wh', grade_level='$gl', school_name='$sn',
            out_of_school_youth='$osy',
            has_car='$hcar', car_brand='$cbr', car_model='$cmo', car_color='$ccl', car_plate='$cpl',
            has_motorcycle='$hmt', motor_brand='$mbr', motor_model='$mmo', motor_color='$mcl', motor_plate='$mpl',
            is_senior='$issr', osca_id='$osca', pwd_status='$pwd', pwd_id='$pwdi', disability_type='$dis',
            solo_parent_status='$solo', solo_parent_id='$slid', has_pets=$hpts, resident_code='$code'
            WHERE id=$id";

        if (mysqli_query($conn, $sql)) {
            $adm = mysqli_real_escape_string($conn, $_SESSION['admin']);
            $ip  = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']);
            $nm  = mysqli_real_escape_string($conn, "$fn $ln");
            mysqli_query($conn, "INSERT INTO audit_log (action,record_id,resident_name,performed_by,ip_address,notes)
                VALUES ('UPDATE',$id,'$nm','$adm','$ip','Resident record edited')");

            // Auto-sync to senior_citizens if aged 60+ or marked as senior
            if ($bd) {
                $sc_bdate = new DateTime($bd);
                $sc_age   = (new DateTime())->diff($sc_bdate)->y;
                if ($sc_age >= 60 || $issr === 'Yes') {
                    $sc_bm = (int)$sc_bdate->format('n');
                    $sc_bd = (int)$sc_bdate->format('j');
                    $sc_by = (int)$sc_bdate->format('Y');
                    $sc_who = mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? $_SESSION['admin']);
                    // Update if exists, insert if not
                    $exists = $conn->query("SELECT id FROM senior_citizens WHERE last_name='$ln' AND first_name='$fn' AND birth_month=$sc_bm AND birth_day=$sc_bd AND birth_year=$sc_by LIMIT 1");
                    if ($exists && $exists->num_rows > 0) {
                        $sc_row = $exists->fetch_assoc();
                        $conn->query("UPDATE senior_citizens SET address='$padr',contact_number='$mob',gender='$gen' WHERE id={$sc_row['id']}");
                    } else {
                        $conn->query("INSERT INTO senior_citizens (last_name,first_name,middle_name,gender,birth_month,birth_day,birth_year,address,contact_number,status,added_by)
                            VALUES ('$ln','$fn','$mn','$gen',$sc_bm,$sc_bd,$sc_by,'$padr','$mob','Active','$sc_who')");
                    }
                }
            }

            mysqli_query($conn, "DELETE FROM pets WHERE resident_id=$id");
            if ($hpts && isset($_POST['pet_name'])) {
                foreach ($_POST['pet_name'] as $i => $pname) {
                    if (empty(trim($pname))) continue;
                    $pn  = mysqli_real_escape_string($conn, $pname);
                    $pa  = mysqli_real_escape_string($conn, $_POST['pet_age'][$i]   ?? '');
                    $ps  = mysqli_real_escape_string($conn, $_POST['pet_sex'][$i]   ?? 'Male');
                    $pc  = mysqli_real_escape_string($conn, $_POST['pet_color'][$i] ?? '');
                    $pt  = mysqli_real_escape_string($conn, $_POST['pet_type'][$i]  ?? 'Dog');
                    $pbs = (($_POST['breeder_status'][$i] ?? '') === 'Yes') ? 'Yes' : 'No';
                    $po  = mysqli_real_escape_string($conn, $_POST['other_pets'][$i] ?? '');
                    mysqli_query($conn, "INSERT INTO pets (resident_id,pet_name,pet_age,pet_sex,pet_color,pet_type,breeder_status,other_pets)
                        VALUES ($id,'$pn','$pa','$ps','$pc','$pt','$pbs','$po')");
                }
            }
            $success = true;
            $res  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM residents WHERE id=$id"));
            $pets = [];
            $pr2  = mysqli_query($conn, "SELECT * FROM pets WHERE resident_id=$id");
            while ($p = mysqli_fetch_assoc($pr2)) $pets[] = $p;
            // Keep unlocked after successful save
            $_SESSION['edit_unlocked_'.$id] = time() + 1800;
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    }
}

// Handle unlock/lock AJAX calls (before any HTML output)
$ip = $_SERVER['REMOTE_ADDR'];
if(isset($_GET['_unlock'])){
    // Only process if proper POST request with verification token
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pw_verified'])){
        $_SESSION['edit_unlocked_'.$id] = time() + 1800;
        $_SESSION['edit_unlock_ip_'.$id] = $ip;
        echo 'ok'; exit();
    } else {
        // Direct GET access to _unlock - just redirect to edit page normally
        header("Location: Edit.php?id=$id"); exit();
    }
}
if(isset($_GET['_lock'])){
    if($_SERVER['REQUEST_METHOD']==='POST'){
        unset($_SESSION['edit_unlocked_'.$id]);
        unset($_SESSION['edit_unlock_ip_'.$id]);
        echo 'ok'; exit();
    } else {
        header("Location: Edit.php?id=$id"); exit();
    }
}

// Check if edit session is still valid (30 min)
$is_unlocked = !empty($_SESSION['edit_unlocked_'.$id])
    && $_SESSION['edit_unlocked_'.$id] > time()
    && ($_SESSION['edit_unlock_ip_'.$id] ?? '') === $ip;

function v($r, $k) { return htmlspecialchars($r[$k] ?? ''); }
function sel($r, $k, $v) { return ($r[$k] ?? '') == $v ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Edit Resident – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<style>
body{background:#f8fafc}
.page-top{background:#0f172a;padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.page-top h1{color:#fff;font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800}
.page-top p{color:rgba(255,255,255,.45);font-size:12px;margin-top:2px}
main{padding:1.5rem;max-width:980px;margin:0 auto}

/* ── GATE ── */
.gate-wrap{min-height:70vh;display:flex;align-items:center;justify-content:center}
.gate-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:2.5rem 2rem;max-width:440px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.07)}
.gate-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#0f172a,#1e40af);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:28px;color:#fff;position:relative}
.gate-lock{position:absolute;bottom:-4px;right:-4px;width:26px;height:26px;background:#f43f5e;border-radius:50%;border:3px solid #fff;display:flex;align-items:center;justify-content:center;font-size:10px;color:#fff}
.gate-name{font-family:'Syne',sans-serif;font-size:1.25rem;font-weight:800;color:#0f172a;margin-bottom:4px}
.gate-code{font-size:12px;font-family:monospace;background:#f1f5f9;color:#475569;padding:3px 12px;border-radius:20px;display:inline-block;margin-bottom:1rem}
.gate-desc{font-size:13px;color:#64748b;line-height:1.6;margin-bottom:1.5rem}
.gate-input-wrap{position:relative;margin-bottom:10px}
.gate-input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px}
.gate-input{width:100%;padding:12px 14px 12px 42px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:'Inter',sans-serif;color:#0f172a;outline:none;transition:border .2s,box-shadow .2s;box-sizing:border-box}
.gate-input:focus{border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.1)}
.gate-input.error{border-color:#f43f5e;box-shadow:0 0 0 4px rgba(244,63,94,.1)}
.gate-err{background:#fff1f2;border:1px solid #fecdd3;color:#be123c;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:10px;text-align:left;display:none;align-items:center;gap:8px}
.gate-btn{width:100%;padding:13px;background:#0f172a;color:#fff;border:none;border-radius:10px;font-family:'Syne',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.gate-btn:hover{background:#1e293b;transform:translateY(-1px);box-shadow:0 4px 14px rgba(15,23,42,.25)}
.gate-btn:active{transform:translateY(0)}
.gate-btn:disabled{background:#e2e8f0;color:#94a3b8;cursor:not-allowed;transform:none;box-shadow:none}
.gate-hint{margin-top:1rem;font-size:11px;color:#94a3b8;display:flex;align-items:center;justify-content:center;gap:6px}
.attempts-bar{display:flex;gap:4px;justify-content:center;margin-top:.75rem}
.att-dot{width:8px;height:8px;border-radius:50%;background:#e2e8f0;transition:background .3s}
.att-dot.used{background:#f43f5e}

/* ── EDIT FORM ── */
.edit-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04)}
.edit-header{background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:1.5rem 1.75rem;display:flex;align-items:center;gap:14px}
.edit-header-av{width:50px;height:50px;border-radius:12px;background:rgba(255,255,255,.12);border:2px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;font-family:'Syne',sans-serif;font-weight:800}
.edit-header h2{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#fff}
.edit-header p{font-size:12px;color:rgba(255,255,255,.55);margin-top:3px}
.edit-code{display:inline-flex;align-items:center;gap:6px;background:rgba(59,130,246,.2);border:1px solid rgba(59,130,246,.35);color:#93c5fd;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:6px;font-family:monospace;letter-spacing:.05em}
.unlocked-badge{margin-left:auto;display:flex;align-items:center;gap:7px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);color:#86efac;font-size:12px;font-weight:600;padding:5px 12px;border-radius:20px;flex-shrink:0}
.unlocked-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

.edit-body{padding:1.75rem}
.sec{margin-bottom:1.5rem}
.sec-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;padding:8px 0 8px;border-bottom:2px solid #f1f5f9;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.sec-title i{color:#3b82f6;font-size:12px;width:16px;text-align:center}
.fg label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px}
.fg input,.fg select,.fg textarea{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;color:#0f172a;outline:none;transition:border .2s,box-shadow .2s;box-sizing:border-box}
.fg input:focus,.fg select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.08)}
.fg input:hover,.fg select:hover{border-color:#94a3b8}
.r2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.r3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.r4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px}
@media(max-width:700px){.r2,.r3,.r4{grid-template-columns:1fr 1fr}}
@media(max-width:500px){.r2,.r3,.r4{grid-template-columns:1fr}}

.pet-row{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;margin-bottom:10px;position:relative}
.remove-pet{position:absolute;top:10px;right:10px;background:#fff1f2;color:#f43f5e;border:1px solid #fecdd3;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
.remove-pet:hover{background:#ffe4e6}
.add-pet-btn{width:100%;padding:9px;background:#f8fafc;border:2px dashed #e2e8f0;border-radius:9px;color:#64748b;font-family:'Inter',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;margin-top:6px}
.add-pet-btn:hover{border-color:#3b82f6;color:#3b82f6;background:#eff6ff}

.edit-footer{display:flex;gap:10px;justify-content:space-between;align-items:center;padding:1.25rem 1.75rem;border-top:1px solid #f1f5f9;background:#f8fafc}
.edit-footer-right{display:flex;gap:10px}
.lock-btn{display:flex;align-items:center;gap:7px;padding:8px 16px;background:#fff1f2;color:#f43f5e;border:1px solid #fecdd3;border-radius:8px;font-family:'Inter',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s}
.lock-btn:hover{background:#ffe4e6}
.changed-indicator{display:none;align-items:center;gap:6px;font-size:12px;color:#f59e0b;background:#fffbeb;padding:5px 12px;border-radius:20px;border:1px solid #fde68a}

footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem 2rem;margin-top:2rem}

/* ── PASSWORD EYE TOGGLE ── */
.pw-wrap{position:relative;display:flex;align-items:center}
.pw-wrap input{padding-right:40px !important;flex:1}
.pw-eye{position:absolute;right:12px;background:none;border:none;cursor:pointer;
  color:#94a3b8;font-size:14px;padding:4px;transition:color .2s;z-index:2}
.pw-eye:hover{color:#3b82f6}
</style>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div>
  </a>
  <div style="display:flex;align-items:center;gap:6px;border-left:1px solid rgba(255,255,255,.12);padding-left:14px">
    <span style="font-size:13px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><i class="fas fa-user-pen" style="opacity:.8;margin-right:5px"></i> Edit Resident</span>
  </div>
  <div class="topbar-right" style="margin-left:auto">
    <a href="Display_List.php" class="btn btn-nav" style="font-size:13px"><i class="fas fa-arrow-left"></i> Back to List</a>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div></div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <div style="padding:14px 12px 6px">
    <button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s">
      <i class="fas fa-gear"></i> Settings & More
      <i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i>
    </button>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a>
    <a href="Display_List.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<!-- ══ SETTINGS DRAWER ══ -->
<div id="settingsOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100" onclick="closeSettings()"></div>
<div id="settingsDrawer" style="position:fixed;top:0;right:-360px;width:340px;height:100vh;background:#0f172a;z-index:1101;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.08)">
  <div style="padding:20px 20px 14px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#3b82f6,#14b8a6);border-radius:8px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-gear" style="color:#fff;font-size:13px"></i>
      </div>
      <div>
        <div style="font-family:Syne,sans-serif;font-size:14px;font-weight:800;color:#fff">Settings</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4)">ProjectRBI Barangay 410</div>
      </div>
    </div>
    <button onclick="closeSettings()" style="width:28px;height:28px;background:rgba(255,255,255,.08);border:none;border-radius:7px;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:16px">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Appearance</div>
    <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-circle-half-stroke" style="color:#94a3b8;font-size:13px"></i></div>
        <div><div style="font-size:13px;font-weight:600;color:#fff">Dark Mode</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Toggle dark/light theme</div></div>
      </div>
      <button id="darkToggle" onclick="toggleDarkMode()" style="width:42px;height:24px;border-radius:12px;background:#475569;border:none;cursor:pointer;position:relative;transition:background .25s;flex-shrink:0">
        <span id="darkThumb" style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;transition:left .25s"></span>
      </button>
    </div>
    <div style="background:rgba(255,255,255,.05);border-radius:10px;padding:12px 14px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-magnifying-glass" style="color:#94a3b8;font-size:13px"></i></div>
        <div><div style="font-size:13px;font-weight:600;color:#fff">Page Zoom</div><div style="font-size:11px;color:rgba(255,255,255,.4)" id="zoomLabel">100%</div></div>
        <button onclick="resetZoom()" style="margin-left:auto;font-size:11px;color:#64748b;background:none;border:none;cursor:pointer">Reset</button>
      </div>
      <div style="display:flex;gap:8px">
        <button onclick="pageZoom(0.9)" style="flex:1;padding:8px;background:rgba(255,255,255,.08);border:none;border-radius:8px;color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px"><i class="fas fa-magnifying-glass-minus"></i> Out</button>
        <button onclick="pageZoom(1.1)" style="flex:1;padding:8px;background:rgba(255,255,255,.08);border:none;border-radius:8px;color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px"><i class="fas fa-magnifying-glass-plus"></i> In</button>
      </div>
    </div>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Actions</div>
    <button onclick="window.print()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .2s">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print Page</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print current view</div></div>
    </button>
  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div><div style="font-size:11px;color:rgba(244,63,94,.6)">End your session</div></div>
    </a>
  </div>
</div>

<div class="page-top">
  <div>
    <h1><i class="fas fa-user-pen" style="margin-right:8px;opacity:.7"></i>Edit Resident Record</h1>
    <p><?=v($res,'first_name').' '.v($res,'middle_name').' '.v($res,'last_name')?> · ID: <?=v($res,'resident_code')?:'-'?></p>
  </div>
</div>

<main>
  <?php if($success): ?>
  <div class="alert alert-success" style="margin-bottom:1.25rem;display:flex;align-items:center;gap:10px">
    <i class="fas fa-check-circle" style="font-size:18px"></i>
    <div><strong>Changes saved!</strong> The resident record has been updated successfully.</div>
    <a href="Display_List.php" style="margin-left:auto;font-size:12px;color:#15803d;font-weight:600;text-decoration:none">← Back to List</a>
  </div>
  <?php endif; ?>
  <?php if($error): ?>
  <div class="alert alert-error" style="margin-bottom:1.25rem"><i class="fas fa-exclamation-triangle"></i> <?=$error?></div>
  <?php endif; ?>

  <?php if (!$is_unlocked): ?>
  <!-- ══════════════════════════════════════════════════════════
       PASSWORD GATE
  ══════════════════════════════════════════════════════════ -->
  <div class="gate-wrap">
    <div class="gate-card">
      <!-- Resident preview -->
      <div class="gate-avatar">
        <?=strtoupper(substr($res['first_name'],0,1).substr($res['last_name'],0,1))?>
        <div class="gate-lock"><i class="fas fa-lock"></i></div>
      </div>
      <div class="gate-name"><?=v($res,'first_name').' '.v($res,'last_name')?></div>
      <?php if($res['resident_code']): ?>
      <div class="gate-code"><i class="fas fa-id-badge"></i> <?=v($res,'resident_code')?></div>
      <?php endif; ?>
      <div class="gate-desc">
        This record is <strong>protected</strong>. Only authorized secretaries can edit resident data.<br>
        Enter your admin password to unlock editing.
      </div>

      <!-- Attempt indicator dots -->
      <div class="attempts-bar" id="attemptDots">
        <div class="att-dot" id="d1"></div>
        <div class="att-dot" id="d2"></div>
        <div class="att-dot" id="d3"></div>
        <div class="att-dot" id="d4"></div>
        <div class="att-dot" id="d5"></div>
      </div>

      <!-- Error box -->
      <div class="gate-err" id="gateErr" style="margin-top:.75rem">
        <i class="fas fa-exclamation-circle"></i>
        <span id="gateErrMsg">Incorrect password.</span>
      </div>

      <!-- Password input -->
      <div class="gate-input-wrap" style="margin-top:.75rem">
        <i class="fas fa-key"></i>
        <input type="password" id="gatePw" class="gate-input" placeholder="Enter admin password" autocomplete="off">
      </div>

      <button class="gate-btn" id="gateBtn" onclick="unlockEdit()">
        <i class="fas fa-unlock-alt"></i> Unlock & Edit Record
      </button>

      <div class="gate-hint">
        <i class="fas fa-shield-halved"></i>
        Session stays unlocked for 30 minutes · 5 attempts before 10-min lockout
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ══════════════════════════════════════════════════════════
       EDIT FORM (unlocked)
  ══════════════════════════════════════════════════════════ -->
  <div class="edit-card">

    <!-- Header -->
    <div class="edit-header">
      <div class="edit-header-av">
        <?=strtoupper(substr($res['first_name'],0,1).substr($res['last_name'],0,1))?>
      </div>
      <div>
        <h2><?=v($res,'first_name').' '.v($res,'middle_name').' '.v($res,'last_name')?></h2>
        <p>Editing resident record</p>
        <div class="edit-code"><i class="fas fa-id-badge"></i> <?=v($res,'resident_code')?:'-'?></div>
      </div>
      <div class="unlocked-badge">
        <span class="unlocked-dot"></span> Secretary Access Active
      </div>
    </div>

    <form method="POST" action="Edit.php?id=<?=$id?>" id="editForm">
      <?= csrf_field() ?>
      <input type="hidden" name="_save" value="1">
      <input type="hidden" name="resident_code" value="<?=v($res,'resident_code')?>">

    <div class="edit-body">

      <!-- PERSONAL -->
      <div class="sec">
        <div class="sec-title"><i class="fas fa-user"></i> Personal Information</div>
        <div class="r4" style="margin-bottom:12px">
          <div class="fg"><label>First Name <span class="required">*</span></label><input type="text" name="first_name" value="<?=v($res,'first_name')?>" required></div>
          <div class="fg"><label>Middle Name</label><input type="text" name="middle_name" value="<?=v($res,'middle_name')?>"></div>
          <div class="fg"><label>Last Name <span class="required">*</span></label><input type="text" name="last_name" value="<?=v($res,'last_name')?>" required></div>
          <div class="fg"><label>Suffix</label><input type="text" name="suffix" value="<?=v($res,'suffix')?>"></div>
        </div>
        <div class="r3" style="margin-bottom:12px">
          <div class="fg"><label>Birthdate</label><input type="date" name="birthdate" value="<?=v($res,'birthdate')?>" max="<?=date('Y-m-d')?>" onwheel="this.blur()" ondragstart="return false"></div>
          <div class="fg"><label>Gender</label>
            <select name="gender"><option <?=sel($res,'gender','Male')?>>Male</option><option <?=sel($res,'gender','Female')?>>Female</option></select>
          </div>
          <div class="fg"><label>Marital Status</label>
            <select name="marital_status">
              <?php foreach(['Single','Married','Widowed','Divorced','Annulled'] as $o): ?>
              <option <?=sel($res,'marital_status',$o)?>><?=$o?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="r2" style="margin-bottom:12px">
          <div class="fg"><label>Religion</label><input type="text" name="religion" value="<?=v($res,'religion')?>"></div>
          <div class="fg"><label>Citizenship</label><input type="text" name="citizenship" value="<?=v($res,'citizenship')?>"></div>
        </div>
        <div class="r2">
          <div class="fg"><label>Head of Family?</label>
            <select name="head_of_family" id="hofSel" onchange="toggleHOF()">
              <option value="Yes" <?=sel($res,'head_of_family','Yes')?>>Yes</option>
              <option value="No"  <?=sel($res,'head_of_family','No') ?>>No</option>
            </select>
          </div>
          <div class="fg"><label>Relationship to Head</label><input type="text" name="relationship" value="<?=v($res,'relationship')?>"></div>
        </div>
        <div id="hofSection" style="display:<?=$res['head_of_family']==='No'?'grid':'none'?>;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-top:12px">
          <div class="fg"><label>Head First Name</label><input type="text" name="head_first_name" value="<?=v($res,'head_first_name')?>"></div>
          <div class="fg"><label>Head Middle Name</label><input type="text" name="head_middle_name" value="<?=v($res,'head_middle_name')?>"></div>
          <div class="fg"><label>Head Last Name</label><input type="text" name="head_last_name" value="<?=v($res,'head_last_name')?>"></div>
          <div class="fg"><label>Head Suffix</label><input type="text" name="head_suffix" value="<?=v($res,'head_suffix')?>"></div>
        </div>
      </div>

      <!-- ADDRESS -->
      <div class="sec">
        <div class="sec-title"><i class="fas fa-map-marker-alt"></i> Address & Contact</div>
        <div class="r2" style="margin-bottom:12px">
          <div class="fg"><label>Permanent Address</label><input type="text" name="perm_address" value="<?=v($res,'perm_address')?>"></div>
          <div class="fg"><label>Provincial Address</label><input type="text" name="prov_address" value="<?=v($res,'prov_address')?>"></div>
        </div>
        <div class="r3" style="margin-bottom:12px">
          <div class="fg"><label>House Owner</label>
            <select name="house_owner"><option <?=sel($res,'house_owner','Yes')?>>Yes</option><option <?=sel($res,'house_owner','No')?>>No</option></select>
          </div>
          <div class="fg"><label>House Details</label><input type="text" name="house_details" value="<?=v($res,'house_details')?>"></div>
          <div class="fg"><label>Years in Barangay</label><input type="number" name="years_in_barangay" value="<?=v($res,'years_in_barangay')?>" min="0"></div>
        </div>
        <div class="r2" style="margin-bottom:12px">
          <div class="fg"><label>Voter</label>
            <select name="voter"><option <?=sel($res,'voter','Yes')?>>Yes</option><option <?=sel($res,'voter','No')?>>No</option></select>
          </div>
          <div class="fg"><label>Precinct No.</label><input type="text" name="precinct_no" value="<?=v($res,'precinct_no')?>"></div>
        </div>
        <div class="r3">
          <div class="fg"><label>Mobile</label><input type="text" name="mobile" value="<?=v($res,'mobile')?>"></div>
          <div class="fg"><label>Landline</label><input type="text" name="landline" value="<?=v($res,'landline')?>"></div>
          <div class="fg"><label>Email</label><input type="email" name="email" value="<?=v($res,'email')?>"></div>
        </div>
      </div>

      <!-- EDUCATION -->
      <div class="sec">
        <div class="sec-title"><i class="fas fa-graduation-cap"></i> Education & Employment</div>
        <div class="r2" style="margin-bottom:12px">
          <div class="fg"><label>Education</label>
            <select name="education">
              <?php foreach(['No Formal Education','Elementary Graduate','High School Graduate','Senior High School Graduate','Vocational/Technical','College Undergraduate','College Graduate','Post Graduate'] as $o): ?>
              <option <?=sel($res,'education',$o)?>><?=$o?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label>Out of School Youth</label>
            <select name="out_of_school_youth">
              <option value="" <?=sel($res,'out_of_school_youth','')?>></option>
              <option value="Yes" <?=sel($res,'out_of_school_youth','Yes')?>>Yes</option>
              <option value="No"  <?=sel($res,'out_of_school_youth','No') ?>>No</option>
            </select>
          </div>
        </div>
        <div class="r4">
          <div class="fg"><label>Employment Status</label>
            <select name="employment_status">
              <?php foreach(['Employed','Unemployed','Self-Employed','Student','Retired'] as $o): ?>
              <option <?=sel($res,'employment_status',$o)?>><?=$o?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label>Occupation</label><input type="text" name="occupation" value="<?=v($res,'occupation')?>"></div>
          <div class="fg"><label>Employer</label><input type="text" name="employer" value="<?=v($res,'employer')?>"></div>
          <div class="fg"><label>Work Hours</label><input type="text" name="work_hours" value="<?=v($res,'work_hours')?>"></div>
        </div>
      </div>

      <!-- VEHICLES -->
      <div class="sec">
        <div class="sec-title"><i class="fas fa-car"></i> Vehicles</div>
        <div class="r4" style="margin-bottom:12px">
          <div class="fg"><label>Has Car</label><select name="has_car"><option <?=sel($res,'has_car','Yes')?>>Yes</option><option <?=sel($res,'has_car','No')?>>No</option></select></div>
          <div class="fg"><label>Car Brand</label><input type="text" name="car_brand" value="<?=v($res,'car_brand')?>"></div>
          <div class="fg"><label>Car Model</label><input type="text" name="car_model" value="<?=v($res,'car_model')?>"></div>
          <div class="fg"><label>Car Plate</label><input type="text" name="car_plate" value="<?=v($res,'car_plate')?>"></div>
        </div>
        <div class="r4">
          <div class="fg"><label>Has Motorcycle</label><select name="has_motorcycle"><option <?=sel($res,'has_motorcycle','Yes')?>>Yes</option><option <?=sel($res,'has_motorcycle','No')?>>No</option></select></div>
          <div class="fg"><label>Motor Brand</label><input type="text" name="motor_brand" value="<?=v($res,'motor_brand')?>"></div>
          <div class="fg"><label>Motor Model</label><input type="text" name="motor_model" value="<?=v($res,'motor_model')?>"></div>
          <div class="fg"><label>Motor Plate</label><input type="text" name="motor_plate" value="<?=v($res,'motor_plate')?>"></div>
        </div>
      </div>

      <!-- SPECIAL -->
      <div class="sec">
        <div class="sec-title"><i class="fas fa-star"></i> Special Categories</div>
        <div class="r4">
          <div class="fg"><label>Senior Citizen</label><select name="is_senior"><option value="Yes" <?=sel($res,'is_senior','Yes')?>>Yes</option><option value="No" <?=sel($res,'is_senior','No')?>>No</option></select></div>
          <div class="fg"><label>OSCA ID</label><input type="text" name="osca_id" value="<?=v($res,'osca_id')?>"></div>
          <div class="fg"><label>PWD</label><select name="pwd_status"><option value="Yes" <?=sel($res,'pwd_status','Yes')?>>Yes</option><option value="No" <?=sel($res,'pwd_status','No')?>>No</option></select></div>
          <div class="fg"><label>Disability Type</label><input type="text" name="disability_type" value="<?=v($res,'disability_type')?>"></div>
        </div>
        <div class="r4" style="margin-top:12px">
          <div class="fg"><label>Solo Parent</label><select name="solo_parent_status"><option value="Yes" <?=sel($res,'solo_parent_status','Yes')?>>Yes</option><option value="No" <?=sel($res,'solo_parent_status','No')?>>No</option></select></div>
          <div class="fg"><label>Solo Parent ID</label><input type="text" name="solo_parent_id" value="<?=v($res,'solo_parent_id')?>"></div>
          <div class="fg"><label>PWD ID</label><input type="text" name="pwd_id" value="<?=v($res,'pwd_id')?>"></div>
        </div>
      </div>

      <!-- PETS -->
      <div class="sec">
        <div class="sec-title"><i class="fas fa-paw"></i> Pets</div>
        <div class="fg" style="margin-bottom:12px"><label>Has Pets?</label>
          <select name="has_pets" id="hasPetsSel" onchange="togglePets()" style="max-width:180px">
            <option value="Yes" <?=($res['has_pets'])?'selected':''?>>Yes</option>
            <option value="No"  <?=(!$res['has_pets'])?'selected':''?>>No</option>
          </select>
        </div>
        <div id="petsSection" style="display:<?=$res['has_pets']?'block':'none'?>">
          <div id="petsList">
            <?php foreach($pets as $i=>$p): ?>
            <div class="pet-row" id="pr-<?=$i?>">
              <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px"><i class="fas fa-paw" style="color:#3b82f6;margin-right:5px"></i>Pet #<?=$i+1?></div>
              <button type="button" class="remove-pet" onclick="this.closest('.pet-row').remove()">Remove</button>
              <div class="r4" style="margin-bottom:10px">
                <div class="fg"><label>Name</label><input type="text" name="pet_name[]" value="<?=htmlspecialchars($p['pet_name'])?>"></div>
                <div class="fg"><label>Age</label><input type="text" name="pet_age[]" value="<?=htmlspecialchars($p['pet_age'])?>"></div>
                <div class="fg"><label>Sex</label><select name="pet_sex[]"><option <?=$p['pet_sex']==='Male'?'selected':''?>>Male</option><option <?=$p['pet_sex']==='Female'?'selected':''?>>Female</option></select></div>
                <div class="fg"><label>Color</label><input type="text" name="pet_color[]" value="<?=htmlspecialchars($p['pet_color'])?>"></div>
              </div>
              <div class="r3">
                <div class="fg"><label>Type</label><select name="pet_type[]"><option <?=$p['pet_type']==='Dog'?'selected':''?>>Dog</option><option <?=$p['pet_type']==='Cat'?'selected':''?>>Cat</option><option>Bird</option><option>Other</option></select></div>
                <div class="fg"><label>Breeder?</label><select name="breeder_status[]"><option value="Yes" <?=$p['breeder_status']==='Yes'?'selected':''?>>Yes</option><option value="No" <?=$p['breeder_status']!=='Yes'?'selected':''?>>No</option></select></div>
                <div class="fg"><label>Notes</label><input type="text" name="other_pets[]" value="<?=htmlspecialchars($p['other_pets'])?>"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="add-pet-btn" onclick="addPet()"><i class="fas fa-plus"></i> Add Pet</button>
        </div>
      </div>

    </div><!-- end edit-body -->

    <!-- Footer -->
    <div class="edit-footer">
      <div style="display:flex;align-items:center;gap:10px">
        <button type="button" class="lock-btn" onclick="lockEdit()">
          <i class="fas fa-lock"></i> Lock
        </button>
        <div class="changed-indicator" id="changedIndicator">
          <i class="fas fa-circle-dot"></i> Unsaved changes
        </div>
      </div>
      <div class="edit-footer-right">
        <a href="Display_List.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" name="_save" value="1" class="btn btn-primary">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </div>

    </form>
  </div><!-- end edit-card -->
  <?php endif; ?>

</main>
<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City</footer>

<script>
// Sidebar
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar();});

<?php if(!$is_unlocked): ?>
// ── GATE LOGIC ──────────────────────────────────────────────────────────────
let attempts = 0;
const MAX_ATT = 5;

function updateDots(){
  for(let i=1;i<=MAX_ATT;i++){
    document.getElementById('d'+i).classList.toggle('used', i<=attempts);
  }
}

function unlockEdit(){
  const pw  = document.getElementById('gatePw').value;
  const btn = document.getElementById('gateBtn');
  const err = document.getElementById('gateErr');
  const inp = document.getElementById('gatePw');
  if(!pw){ inp.classList.add('error'); inp.focus(); return; }
  inp.classList.remove('error');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
  err.style.display = 'none';

  const fd = new FormData();
  fd.append('password', pw);
  fetch('verify_secretary.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(res => {
      if(res.ok){
        btn.innerHTML = '<i class="fas fa-check"></i> Access granted! Loading...';
        btn.style.background = '#22c55e';
        // Set session via POST fetch then reload
        fetch('Edit.php?id=<?=$id?>&_unlock=1', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'pw_verified=1'
        }).then(() => location.reload());
      } else {
        attempts++;
        updateDots();
        inp.classList.add('error');
        inp.value = '';
        err.querySelector('#gateErrMsg').textContent = res.message || 'Incorrect password.';
        err.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock-alt"></i> Unlock & Edit Record';
        setTimeout(() => inp.classList.remove('error'), 800);
        inp.focus();
      }
    }).catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-unlock-alt"></i> Unlock & Edit Record';
      err.querySelector('#gateErrMsg').textContent = 'Server error. Please try again.';
      err.style.display = 'flex';
    });
}
document.getElementById('gatePw').addEventListener('keydown', e => { if(e.key==='Enter') unlockEdit(); });
document.getElementById('gatePw').addEventListener('input', e => {
  e.target.classList.remove('error');
  document.getElementById('gateErr').style.display='none';
});

<?php else: ?>
// ── EDIT FORM LOGIC ──────────────────────────────────────────────────────────
function toggleHOF(){
  const hof = document.getElementById('hofSection');
  hof.style.display = document.getElementById('hofSel').value==='No' ? 'grid' : 'none';
}
function togglePets(){
  document.getElementById('petsSection').style.display =
    document.getElementById('hasPetsSel').value==='Yes' ? 'block' : 'none';
}

// Lock the session
function lockEdit(){
  if(confirm('Lock this editing session? You will need your password again to continue editing.')){
    fetch('Edit.php?id=<?=$id?>&_lock=1', {method:'POST'}).then(() => location.reload());
  }
}

// Unsaved changes indicator
let formChanged = false;
document.querySelectorAll('#editForm input, #editForm select, #editForm textarea').forEach(el => {
  el.addEventListener('change', () => {
    if(!formChanged){
      formChanged = true;
      document.getElementById('changedIndicator').style.display = 'flex';
    }
  });
});
window.addEventListener('beforeunload', e => {
  if(formChanged){
    e.preventDefault();
    e.returnValue = '';
  }
});
document.getElementById('editForm').addEventListener('submit', () => { formChanged = false; });

// Add pet
let petCount = <?=count($pets)?>;
function addPet(){
  petCount++;
  const d = document.createElement('div');
  d.className = 'pet-row'; d.id = 'pr-'+petCount;
  d.innerHTML = `
    <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px">
      <i class="fas fa-paw" style="color:#3b82f6;margin-right:5px"></i>Pet #${petCount}
    </div>
    <button type="button" class="remove-pet" onclick="this.closest('.pet-row').remove()">Remove</button>
    <div class="r4" style="margin-bottom:10px">
      <div class="fg"><label>Name</label><input type="text" name="pet_name[]"></div>
      <div class="fg"><label>Age</label><input type="text" name="pet_age[]"></div>
      <div class="fg"><label>Sex</label><select name="pet_sex[]"><option>Male</option><option>Female</option></select></div>
      <div class="fg"><label>Color</label><input type="text" name="pet_color[]"></div>
    </div>
    <div class="r3">
      <div class="fg"><label>Type</label><select name="pet_type[]"><option>Dog</option><option>Cat</option><option>Bird</option><option>Other</option></select></div>
      <div class="fg"><label>Breeder?</label><select name="breeder_status[]"><option value="No">No</option><option value="Yes">Yes</option></select></div>
      <div class="fg"><label>Notes</label><input type="text" name="other_pets[]"></div>
    </div>`;
  document.getElementById('petsList').appendChild(d);
}
<?php endif; ?>
</script>



<script>
// ── PASSWORD SHOW/HIDE ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('input[type="password"]').forEach(function(inp){
    // Skip if already wrapped
    if(inp.parentElement && inp.parentElement.classList.contains('pw-wrap')) return;
    // Wrap the input
    const wrap = document.createElement('div');
    wrap.className = 'pw-wrap';
    // Copy width from parent or set full width
    wrap.style.cssText = 'width:100%;position:relative';
    inp.parentNode.insertBefore(wrap, inp);
    wrap.appendChild(inp);
    // Create eye button
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-eye';
    btn.innerHTML = '<i class="fas fa-eye"></i>';
    btn.title = 'Show / hide password';
    btn.addEventListener('click', function(){
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.innerHTML = show
        ? '<i class="fas fa-eye-slash"></i>'
        : '<i class="fas fa-eye"></i>';
      inp.focus();
    });
    wrap.appendChild(btn);
  });
});
</script>

<script>
function openSettings(){document.getElementById('settingsOverlay').style.display='block';document.getElementById('settingsDrawer').style.right='0';document.body.style.overflow='hidden';if(typeof closeSidebar==='function')closeSidebar();}
function closeSettings(){document.getElementById('settingsOverlay').style.display='none';document.getElementById('settingsDrawer').style.right='-360px';document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSettings();});
let darkMode=localStorage.getItem('rbi_dark')==='1';
function applyDark(on){document.body.classList.toggle('dark-mode',on);const th=document.getElementById('darkThumb'),tg=document.getElementById('darkToggle');if(th)th.style.left=on?'21px':'3px';if(tg)tg.style.background=on?'#3b82f6':'#475569';document.documentElement.style.setProperty('--bg',on?'#0f172a':'#f8fafc');document.documentElement.style.setProperty('--card',on?'#1e293b':'#ffffff');document.documentElement.style.setProperty('--border',on?'#334155':'#e2e8f0');document.documentElement.style.setProperty('--text',on?'#e2e8f0':'#0f172a');}
applyDark(darkMode);
function toggleDarkMode(){darkMode=!darkMode;localStorage.setItem('rbi_dark',darkMode?'1':'0');applyDark(darkMode);}
let zoomLvl=parseFloat(localStorage.getItem('rbi_zoom')||'1');
function applyZoom(){document.body.style.zoom=zoomLvl;const l=document.getElementById('zoomLabel');if(l)l.textContent=Math.round(zoomLvl*100)+'%';}
applyZoom();
function pageZoom(f){zoomLvl=parseFloat(Math.min(Math.max(zoomLvl*f,0.6),1.5).toFixed(2));localStorage.setItem('rbi_zoom',zoomLvl);applyZoom();}
function resetZoom(){zoomLvl=1;localStorage.setItem('rbi_zoom','1');applyZoom();}
</script>
</body>
</html>