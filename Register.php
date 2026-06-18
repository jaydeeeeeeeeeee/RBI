<?php
session_start();
include 'Residents_DB.php';
include 'role_helper.php';
if(!$can_register){ header("Location: Home.php?denied=register"); exit(); }
$initial_mode = (isset($_GET['mode']) && $_GET['mode'] === 'bulk') ? 'bulk' : 'single';
include 'generate_id.php';
// Auto-create resident_code column if missing
ensureResidentCodeColumn($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
csrf_verify();
// ── BULK ADD HANDLER ──────────────────────────────────────────────────────
if (isset($_POST['_bulk_submit']) && !empty($_POST['bulk_rows'])) {
    $bulk_rows = json_decode($_POST['bulk_rows'], true);
    $bulk_results = [];
    $bulk_added = 0;
    $bulk_errors = 0;

    // Helper: insert one resident row, returns ['ok', 'code'/'msg', 'rid']
    $insertResident = function(array $row, string $family_code) use ($conn): array {
        $fn  = mysqli_real_escape_string($conn, trim($row['first_name']   ?? ''));
        $mn  = mysqli_real_escape_string($conn, trim($row['middle_name']  ?? ''));
        $ln  = mysqli_real_escape_string($conn, trim($row['last_name']    ?? ''));
        $sx  = mysqli_real_escape_string($conn, trim($row['suffix']       ?? ''));
        $bd  = mysqli_real_escape_string($conn, trim($row['birthdate']    ?? ''));
        $gen = mysqli_real_escape_string($conn, trim($row['gender']       ?? ''));
        $ms  = mysqli_real_escape_string($conn, trim($row['marital_status'] ?? 'Single'));
        $cit = mysqli_real_escape_string($conn, trim($row['citizenship']  ?? 'Filipino'));
        $rel = mysqli_real_escape_string($conn, trim($row['religion']     ?? ''));
        $padr= mysqli_real_escape_string($conn, trim($row['perm_address'] ?? ''));
        $mob = mysqli_real_escape_string($conn, trim($row['mobile']       ?? ''));
        $emp = mysqli_real_escape_string($conn, trim($row['employment_status'] ?? ''));
        $hof = mysqli_real_escape_string($conn, trim($row['head_of_family'] ?? 'Yes'));
        $vtr = mysqli_real_escape_string($conn, trim($row['voter']        ?? 'No'));
        $issr= mysqli_real_escape_string($conn, trim($row['is_senior']    ?? 'No'));
        $pwd = mysqli_real_escape_string($conn, trim($row['pwd_status']   ?? 'No'));
        $solo= mysqli_real_escape_string($conn, trim($row['solo_parent_status'] ?? 'No'));
        $fc  = mysqli_real_escape_string($conn, $family_code);

        if (empty($fn) || empty($ln)) return ['ok'=>false,'name'=>"$fn $ln",'msg'=>'Missing first or last name'];

        $dup = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT id FROM residents WHERE first_name='$fn' AND last_name='$ln' AND birthdate='$bd' LIMIT 1"));
        if ($dup) return ['ok'=>false,'name'=>"$fn $ln",'msg'=>'Already exists (duplicate skipped)'];

        $code = generateResidentCode($conn);
        $sql  = "INSERT INTO residents (first_name,middle_name,last_name,suffix,head_of_family,
            perm_address,mobile,birthdate,gender,marital_status,religion,citizenship,
            employment_status,voter,is_senior,pwd_status,solo_parent_status,resident_code,family_code)
            VALUES ('$fn','$mn','$ln','$sx','$hof',
            '$padr','$mob','$bd','$gen','$ms','$rel','$cit',
            '$emp','$vtr','$issr','$pwd','$solo','$code','$fc')";
        if (!mysqli_query($conn, $sql)) return ['ok'=>false,'name'=>"$fn $ln",'msg'=>mysqli_error($conn)];

        $rid = mysqli_insert_id($conn);
        $adm = mysqli_real_escape_string($conn, $_SESSION['admin'] ?? 'admin');
        $ip  = $_SERVER['REMOTE_ADDR'];
        mysqli_query($conn, "INSERT INTO audit_log (action,record_id,resident_name,performed_by,ip_address,notes)
            VALUES ('CREATE',$rid,'$fn $ln','$adm','$ip','Bulk registration')");

        // Auto-sync to senior_citizens
        if ($bd) {
            $sc_bdate = new DateTime($bd);
            $sc_age   = (new DateTime())->diff($sc_bdate)->y;
            if ($sc_age >= 60 || $issr === 'Yes') {
                $sc_bm  = (int)$sc_bdate->format('n');
                $sc_bd2 = (int)$sc_bdate->format('j');
                $sc_by  = (int)$sc_bdate->format('Y');
                $sc_who = mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? $_SESSION['admin']);
                $exists = $conn->query("SELECT id FROM senior_citizens WHERE last_name='$ln' AND first_name='$fn' AND birth_month=$sc_bm AND birth_day=$sc_bd2 AND birth_year=$sc_by LIMIT 1");
                if ($exists && $exists->num_rows === 0) {
                    $conn->query("INSERT INTO senior_citizens (last_name,first_name,middle_name,gender,birth_month,birth_day,birth_year,address,contact_number,status,added_by)
                        VALUES ('$ln','$fn','$mn','$gen',$sc_bm,$sc_bd2,$sc_by,'$padr','$mob','Active','$sc_who')");
                }
            }
        }
        return ['ok'=>true,'name'=>"$fn $ln",'code'=>$code,'rid'=>$rid,'msg'=>'Added'];
    };

    if (is_array($bulk_rows)) {
        // ── PASS 1: find & register the head first to establish family_code ──
        $head_idx    = null;
        $family_code = '';

        foreach ($bulk_rows as $i => $row) {
            $fn = trim($row['first_name'] ?? '');
            $ln = trim($row['last_name']  ?? '');
            if (!empty($fn) && !empty($ln) && trim($row['head_of_family'] ?? 'Yes') === 'Yes') {
                $head_idx = $i;
                break;
            }
        }
        // Fallback: first valid row becomes head
        if ($head_idx === null) {
            foreach ($bulk_rows as $i => $row) {
                if (!empty(trim($row['first_name'] ?? '')) && !empty(trim($row['last_name'] ?? ''))) {
                    $head_idx = $i;
                    $bulk_rows[$i]['head_of_family'] = 'Yes';
                    break;
                }
            }
        }

        if ($head_idx !== null) {
            $res = $insertResident($bulk_rows[$head_idx], '');
            if ($res['ok']) {
                $family_code = $res['code'];
                // Backfill family_code = own resident_code for the head
                $fc_esc = mysqli_real_escape_string($conn, $family_code);
                mysqli_query($conn, "UPDATE residents SET family_code='$fc_esc' WHERE resident_code='$fc_esc'");
                $bulk_results[$head_idx] = $res;
                $bulk_added++;
            } else {
                $bulk_results[$head_idx] = $res;
                $bulk_errors++;
            }
        }

        // ── PASS 2: register all other rows with the shared family_code ──────
        foreach ($bulk_rows as $i => $row) {
            if ($i === $head_idx) continue;
            $fn = trim($row['first_name'] ?? '');
            $ln = trim($row['last_name']  ?? '');
            if (empty($fn) && empty($ln)) continue; // skip truly empty rows

            $res = $insertResident($row, $family_code);
            $bulk_results[$i] = $res;
            if ($res['ok']) $bulk_added++; else $bulk_errors++;
        }

        ksort($bulk_results); // restore original row order for display
        $bulk_results = array_values($bulk_results);
    }
    // Stay on page to show results
} elseif (!isset($_POST['_bulk_submit'])) {
// ── SINGLE ADD HANDLER ────────────────────────────────────────────────────

    // Default values
    $has_pets = (isset($_POST['has_pets']) && $_POST['has_pets'] === 'Yes') ? 1 : 0;
    $years_in_barangay = empty($_POST['years_in_barangay']) ? 0 : (int)$_POST['years_in_barangay'];
    $pwd_status = (isset($_POST['pwd_status']) && $_POST['pwd_status'] === 'Yes') ? 'Yes' : 'No';
    $solo_parent_status = (isset($_POST['solo_parent_status']) && $_POST['solo_parent_status'] === 'Yes') ? 'Yes' : 'No';
    $out_of_school_youth = isset($_POST['out_of_school_youth']) ? $_POST['out_of_school_youth'] : '';

   if ($_POST['head_of_family'] === 'No') {
    $head_first_name = $_POST['head_first_name'];
    $head_middle_name = $_POST['head_middle_name'];
    $head_last_name = $_POST['head_last_name'];
    $head_suffix = $_POST['head_suffix'];
} else {
    // Copy from main name fields
    $head_first_name = $_POST['first_name'];
    $head_middle_name = $_POST['middle_name'];
    $head_last_name = $_POST['last_name'];
    $head_suffix = $_POST['suffix'];
}

// Use 'other' input for gender if selected
if (isset($_POST['gender']) && $_POST['gender'] === 'Other' && !empty($_POST['other_gender'])) {
    $_POST['gender'] = $_POST['other_gender'];
}

// Use 'other' input for religion if selected
if (isset($_POST['religion']) && $_POST['religion'] === 'Other' && !empty($_POST['other_religion'])) {
    $_POST['religion'] = $_POST['other_religion'];
}

// Use 'other' input for citizenship if selected
if (isset($_POST['citizenship']) && in_array($_POST['citizenship'], ['Other', 'Dual Citizenship']) && !empty($_POST['other_citizenship'])) {
    $_POST['citizenship'] = $_POST['other_citizenship'];
}

     // Prepare statement
    $res_code = generateResidentCode($conn);
    $stmt = $conn->prepare("INSERT INTO residents (
    first_name, middle_name, last_name, suffix, head_of_family, relationship, 
    head_first_name, head_middle_name, head_last_name, head_suffix,
    perm_address, prov_address, house_owner, house_details, years_in_barangay, voter, precinct_no, mobile,
    landline, email, birthdate, gender, marital_status, religion, citizenship, education, employment_status,
    occupation, employer, work_hours, grade_level, school_name, out_of_school_youth,
    has_car, car_brand, car_model, car_color, car_plate,
    has_motorcycle, motor_brand, motor_model, motor_color, motor_plate,
    is_senior, osca_id, pwd_status, pwd_id, disability_type,
    solo_parent_status, solo_parent_id, has_pets, resident_code
    ) VALUES (
       ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    $stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssssssss",
    $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $_POST['suffix'],
    $_POST['head_of_family'], $_POST['relationship'],
    $head_first_name, $head_middle_name, $head_last_name, $head_suffix,
    $_POST['perm_address'], $_POST['prov_address'], $_POST['house_owner'], $_POST['house_details'],
    $years_in_barangay, $_POST['voter'], $_POST['precinct_no'], $_POST['mobile'],
    $_POST['landline'], $_POST['email'], $_POST['birthdate'], $_POST['gender'],
    $_POST['marital_status'], $_POST['religion'], $_POST['citizenship'], $_POST['education'], $_POST['employment_status'],
    $_POST['occupation'], $_POST['employer'], $_POST['work_hours'], $_POST['grade_level'],
    $_POST['school_name'], $out_of_school_youth,
    $_POST['has_car'], $_POST['car_brand'], $_POST['car_model'], $_POST['car_color'], $_POST['car_plate'],
    $_POST['has_motorcycle'], $_POST['motor_brand'], $_POST['motor_model'], $_POST['motor_color'], $_POST['motor_plate'],
    $_POST['is_senior'], $_POST['osca_id'], $pwd_status, $_POST['pwd_id'], $_POST['disability_type'],
    $solo_parent_status, $_POST['solo_parent_id'], $has_pets, $res_code
);

    if ($stmt->execute()) {
        $resident_id = $stmt->insert_id;
        // Audit log
        $adm_esc = mysqli_real_escape_string($conn, $_SESSION['admin'] ?? 'admin');
        $fn_esc  = mysqli_real_escape_string($conn, $_POST['first_name'].' '.$_POST['last_name']);
        $ip_esc  = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']);
        mysqli_query($conn, "INSERT INTO audit_log (action,record_id,resident_name,performed_by,ip_address,notes) VALUES ('CREATE',$resident_id,'$fn_esc','$adm_esc','$ip_esc','New resident registered')");

        // Auto-sync to senior_citizens if aged 60+ or marked as senior
        $sc_birthdate = $_POST['birthdate'] ?? '';
        $sc_is_senior = $_POST['is_senior'] ?? 'No';
        if ($sc_birthdate) {
            $sc_bdate = new DateTime($sc_birthdate);
            $sc_age   = (new DateTime())->diff($sc_bdate)->y;
            if ($sc_age >= 60 || $sc_is_senior === 'Yes') {
                $sc_bm  = (int)$sc_bdate->format('n');
                $sc_bd  = (int)$sc_bdate->format('j');
                $sc_by  = (int)$sc_bdate->format('Y');
                $sc_ln  = mysqli_real_escape_string($conn, $_POST['last_name']  ?? '');
                $sc_fn  = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
                $sc_mn  = mysqli_real_escape_string($conn, $_POST['middle_name']?? '');
                $sc_gen = mysqli_real_escape_string($conn, $_POST['gender']     ?? '');
                $sc_addr= mysqli_real_escape_string($conn, $_POST['perm_address']?? '');
                $sc_con = mysqli_real_escape_string($conn, $_POST['mobile']     ?? '');
                $sc_who = mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? $_SESSION['admin']);
                // Only insert if not already in the table (match by name + birthdate)
                $exists = $conn->query("SELECT id FROM senior_citizens WHERE last_name='$sc_ln' AND first_name='$sc_fn' AND birth_month=$sc_bm AND birth_day=$sc_bd AND birth_year=$sc_by LIMIT 1");
                if ($exists && $exists->num_rows === 0) {
                    $conn->query("INSERT INTO senior_citizens (last_name,first_name,middle_name,gender,birth_month,birth_day,birth_year,address,contact_number,status,added_by)
                        VALUES ('$sc_ln','$sc_fn','$sc_mn','$sc_gen',$sc_bm,$sc_bd,$sc_by,'$sc_addr','$sc_con','Active','$sc_who')");
                }
            }
        }

        // Insert pets if applicable
        if ($has_pets && isset($_POST['pet_name'])) {
            for ($i = 0; $i < count($_POST['pet_name']); $i++) {
                $breeder_status = (isset($_POST['breeder_status'][$i]) && $_POST['breeder_status'][$i] === 'Yes') ? 'Yes' : 'No';

                $stmt_pet = $conn->prepare("INSERT INTO pets (
                    resident_id, pet_name, pet_age, pet_sex, pet_color, pet_type, breeder_status, other_pets
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt_pet->bind_param("isssssss", 
                    $resident_id, 
                    $_POST['pet_name'][$i], 
                    $_POST['pet_age'][$i],
                    $_POST['pet_sex'][$i], 
                    $_POST['pet_color'][$i], 
                    $_POST['pet_type'][$i],
                    $breeder_status, 
                    $_POST['other_pets'][$i]
                );

                $stmt_pet->execute();
            }
        }

        header("Location: Display_List.php?registered=1");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
} // end elseif single
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Census of Inhabitants</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
</head>
<body>

<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name">Barangay 410</div></div>
  </a>
  <div class="topbar-right" style="margin-left:auto">
    <a href="Display_List.php" class="btn btn-nav" style="font-size:13px"><i class="fas fa-arrow-left"></i> Back to List</a>
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain): ?>background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#fbbf24;<?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);color:#93c5fd;<?php else: ?>background:rgba(148,163,184,.15);border:1px solid rgba(148,163,184,.3);color:#94a3b8;<?php endif; ?>">
      <i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i>
    </div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head"><div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div></div><button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button></div>
  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register):?><a href="Register.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest):?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif;?>
  </div>
  <div class="sidebar-footer"></div>
</aside>

        <div class="contain">
  <div class="form-card">
    <!-- ── PAGE HEADER ── -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:1.25rem">
      <div>
        <h2 style="margin-bottom:4px"><i class="fas fa-user-plus" style="color:#3b82f6;margin-right:8px"></i>Resident Registration</h2>
        <p style="font-size:13px;color:#64748b">Barangay 410 · Manila City</p>
      </div>
      <!-- Mode toggle -->
      <div style="display:flex;background:#f1f5f9;border-radius:10px;padding:3px;gap:2px;flex-shrink:0">
        <button type="button" id="btnSingle" onclick="switchMode('single')"
          style="padding:8px 20px;border-radius:8px;border:none;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;transition:all .2s;background:<?= $initial_mode==='single' ? '#fff' : 'none' ?>;color:<?= $initial_mode==='single' ? '#0f172a' : '#64748b' ?>;box-shadow:<?= $initial_mode==='single' ? '0 1px 4px rgba(0,0,0,.08)' : 'none' ?>">
          <i class="fas fa-user-plus"></i> Single
        </button>
        <button type="button" id="btnBulk" onclick="switchMode('bulk')"
          style="padding:8px 20px;border-radius:8px;border:none;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;transition:all .2s;background:<?= $initial_mode==='bulk' ? '#fff' : 'none' ?>;color:<?= $initial_mode==='bulk' ? '#0f172a' : '#64748b' ?>;box-shadow:<?= $initial_mode==='bulk' ? '0 1px 4px rgba(0,0,0,.08)' : 'none' ?>">
          <i class="fas fa-layer-group"></i> Multiple
        </button>
      </div>
    </div>

    <?php if (!empty($bulk_results)): ?>
    <!-- ── BULK RESULTS ── -->
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem">
      <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:10px">
        Multiple Results:
        <span style="color:#15803d"><?= $bulk_added ?? 0 ?> added</span>
        <?php if (!empty($bulk_errors)): ?>, <span style="color:#be123c"><?= $bulk_errors ?> failed</span><?php endif; ?>
      </div>
      <?php foreach ($bulk_results as $br): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
        <i class="fas fa-<?= $br['ok'] ? 'check-circle' : 'times-circle' ?>"
           style="color:<?= $br['ok'] ? '#22c55e' : '#f43f5e' ?>"></i>
        <span style="font-weight:600"><?= htmlspecialchars($br['name']) ?></span>
        <?php if ($br['ok'] && isset($br['code'])): ?>
          <span style="font-family:monospace;font-size:11px;background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:20px"><?= $br['code'] ?></span>
        <?php endif; ?>
        <span style="color:#64748b;font-size:12px"><?= htmlspecialchars($br['msg']) ?></span>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:10px;display:flex;gap:8px">
        <a href="Display_List.php" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:#3b82f6;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600"><i class="fas fa-users"></i> View Residents</a>
        <button type="button" onclick="switchMode('bulk')" style="padding:7px 16px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:Inter,sans-serif"><i class="fas fa-plus"></i> Add More</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── SINGLE MODE: Progress bar -->
    <div class="progress-bar-reg"><div class="progress-bar-fill" id="regProgressFill" style="width:25%"></div></div>

    <!-- ── SINGLE MODE FORM ── -->
    <div id="singleMode" style="display:<?= $initial_mode==='single' ? 'block' : 'none' ?>">
    <!-- Steps indicator -->
    <div class="steps-indicator" id="stepsIndicator">
      <div class="step-ind active" id="si-0"><div class="step-num">1</div><div class="step-label">Personal</div></div>
      <div class="step-line" id="sl-0"></div>
      <div class="step-ind" id="si-1"><div class="step-num">2</div><div class="step-label">Address</div></div>
      <div class="step-line" id="sl-1"></div>
      <div class="step-ind" id="si-2"><div class="step-num">3</div><div class="step-label">Education</div></div>
      <div class="step-line" id="sl-2"></div>
      <div class="step-ind" id="si-3"><div class="step-num">4</div><div class="step-label">Pets & Submit</div></div>
    </div>

    <form action="Register.php" method="POST" id="multiStepForm" autocomplete="off">
          <?= csrf_field() ?>
          <!-- Step 1: Personal Information -->
          <div class="form-step active">
            <div class="form-section-title"><i class="fas fa-circle-dot" style="color:#3b82f6"></i>Step 1: Personal Information</div>
           <div class="name-row">
              <div class="name-group">
          <label>First Name: <span class="required">*</span></label>
          <input type="text" name="first_name" class="capitalize" required>
        </div>
        <div class="name-group">
          <label>Middle Name:</label>
          <input type="text" name="middle_name" class="capitalize">
        </div>
        <div class="name-group">
          <label>Last Name: <span class="required">*</span></label>
          <input type="text" name="last_name" class="capitalize" required>
        </div>
        <div class="name-group">
          <label>Suffix:</label>
          <input type="text" name="suffix" class="capitalize">
        </div>
      </div>

      <label for="head_of_family">Are you the Head of the Family? <span class="required">*</span></label>
      <select name="head_of_family" id="head_of_family" required>
        <option value="">-- Select --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <!-- This container will show if 'No' is selected -->
      <div id="relationship-container" style="display: none;">
        <label for="relationship">Relationship to Head:</label>
        <input type="text" name="relationship" id="relationship">

        <div class="name-row">
          <div class="name-group">
            <label>Head's First Name:</label>
            <input type="text" name="head_first_name" id="head_first_name" class="capitalize">
          </div>
          <div class="name-group">
            <label>Head's Middle Name:</label>
            <input type="text" name="head_middle_name" id="head_middle_name" class="capitalize">
          </div>
          <div class="name-group">
            <label>Head's Last Name:</label>
            <input type="text" name="head_last_name" id="head_last_name" class="capitalize">
          </div>
          <div class="name-group">
            <label>Head's Suffix:</label>
            <input type="text" name="head_suffix" id="head_suffix" class="capitalize">
          </div>
        </div>
      </div>

      <script>
        const headSelect = document.getElementById('head_of_family');
        const relationshipContainer = document.getElementById('relationship-container');

        headSelect.addEventListener('change', function () {
          if (this.value === 'No') {
            relationshipContainer.style.display = 'block';
          } else {
            relationshipContainer.style.display = 'none';
            document.getElementById('relationship').value = '';
            document.getElementById('head_first_name').value = '';
            document.getElementById('head_middle_name').value = '';
            document.getElementById('head_last_name').value = '';
            document.getElementById('head_suffix').value = '';
          }
        });

        // Optional: show fields on load if "No" is already selected
        window.addEventListener('DOMContentLoaded', () => {
          if (headSelect.value === 'No') {
            relationshipContainer.style.display = 'block';
          }
        });
      </script>

    <div class="personal-row">
      <div class="personal-group">
        <label>Birthdate: <span class="required">*</span></label>
        <input type="date" name="birthdate" id="birthdate" required onwheel="this.blur()" ondragstart="return false">
      </div>

      <script>
        document.addEventListener("DOMContentLoaded", function () {
          const today = new Date().toISOString().split('T')[0];
          document.getElementById('birthdate').setAttribute('max', today);
        });
      </script>

      <div class="personal-group">
        <label for="gender">Gender: <span class="required">*</span></label>
        <select name="gender" id="gender" onchange="toggleOtherGender()" required>
          <option value="">-- Select --</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="personal-group" id="other_gender_container" style="display: none;">
        <label for="other_gender">Please specify gender:</label>
        <input type="text" name="other_gender" id="other_gender">
      </div>

     <script>
        function toggleOtherGender() {
          const genderSelect = document.getElementById('gender');
          const otherGenderContainer = document.getElementById('other_gender_container');
          const otherGenderInput = document.getElementById('other_gender');

          if (genderSelect.value === 'Other') {
            otherGenderContainer.style.display = 'block';
          } else {
            otherGenderContainer.style.display = 'none';
            otherGenderInput.value = '';
          }
        }

        window.addEventListener('DOMContentLoaded', toggleOtherGender);
      </script>

      <div class="personal-group">
        <label for="marital_status">Marital Status: <span class="required">*</span></label>
        <select name="marital_status" id="marital_status" required>
          <option value="" disabled selected>Select status</option>
          <option value="Single">Single</option>
          <option value="Married">Married</option>
          <option value="Widowed">Widow/Widower</option>
          <option value="Divorced">Divorced</option>
          <option value="Annulled">Annulled</option>
        </select>
      </div>
    </div>

<div class="personal-row">
      <!-- Religion -->
      <div class="personal-group">
        <label for="religion">Religion: <span class="required">*</span></label>
        <select name="religion" id="religion" onchange="toggleOtherReligion()" required>
          <option value="">-- Select --</option>
          <option value="None">None</option>
          <option value="Roman Catholic">Roman Catholic</option>
          <option value="Christian">Christian</option>
          <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
          <option value="Muslim">Muslim</option>
          <option value="Buddhism">Buddhism</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="personal-group" id="other_religion_container" style="display: none;">
        <label for="other_religion">Please specify religion:</label>
        <input type="text" name="other_religion" id="other_religion">
      </div>

      <!-- Citizenship -->
      <div class="personal-group">
        <label for="citizenship">Citizenship: <span class="required">*</span></label>
        <select name="citizenship" id="citizenship" onchange="toggleOtherCitizenship()" required>
          <option value="">-- Select --</option>
          <option value="Filipino">Filipino</option>
          <option value="Dual Citizenship">Dual Citizenship</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="personal-group" id="other_citizenship_container" style="display: none;">
        <label for="other_citizenship">Please specify citizenhip:</label>
        <input type="text" name="other_citizenship" id="other_citizenship">
      </div>
    </div>

    <script>
      function toggleOtherReligion() {
        const select = document.getElementById('religion');
        const container = document.getElementById('other_religion_container');
          if (select.value === 'Other') {
                container.style.display = 'block';
              } else {
                container.style.display = 'none';
                document.getElementById('other_religion').value = '';
              }
            }

        window.addEventListener('DOMContentLoaded', toggleOtherReligion);
    </script>
        
    <script>
       function toggleOtherCitizenship() {
          const select = document.getElementById('citizenship');
          const container = document.getElementById('other_citizenship_container');
          const input = document.getElementById('other_citizenship');
            if (select.value === 'Other' || select.value === 'Dual Citizenship') {
               container.style.display = 'block';
            } else {
              container.style.display = 'none';
              input.value = '';
            }
          }

        window.addEventListener('DOMContentLoaded', toggleOtherCitizenship);
    </script>    

    <div class="nav-buttons nav-right">
       <button type="button" onclick="nextStep()">Next</button>
    </div>
  </div>

  <!-- Step 2: Address & Contact -->
 <div class="form-step">
  <div class="form-section-title"><i class="fas fa-circle-dot" style="color:#3b82f6"></i>Step 2: Address & Contact</div>
   <div class="personal-row">
    <div class="personal-group">
    <label>Permanent Address: <span class="required">*</span></label>
    <input type="text" name="perm_address" value="" required>
    </div>
    <div class="personal-group">
    <label>Province:</label>
    <input type="text" name="prov_address" value="">
  </div>
</div>

  <div class="personal-row">
    <div class="personal-group">
      <label for="house_owner">Are you the House Owner? <span class="required">*</span></label>
      <select name="house_owner" id="house_owner" required>
        <option value="">-- Select --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>
    </div>
    <div class="personal-group" id="house-details-container" style="display: none;">
      <label for="house_details">House Details (e.g. rented, borrowed, etc.):</label>
      <input type="text" name="house_details" id="house_details" value="">
    </div>
  </div>

    <script>
      const houseOwnerSelect = document.getElementById('house_owner');
      const houseDetailsContainer = document.getElementById('house-details-container');
      const houseDetailsInput = document.getElementById('house_details');

      function toggleHouseDetails() {
        if (houseOwnerSelect.value === 'No') {
          houseDetailsContainer.style.display = 'block';
        } else {
          houseDetailsContainer.style.display = 'none';
          houseDetailsInput.value = '';
        }
      }
      houseOwnerSelect.addEventListener('change', toggleHouseDetails);
      window.addEventListener('DOMContentLoaded', toggleHouseDetails);
    </script>

 <div class="personal-row">
    <div class="personal-group">
      <label>Years in Barangay:</label>
      <input type="text" name="years_in_barangay" id="years_in_barangay">
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
      const birthdateInput = document.getElementById('birthdate');
      const yearsInput = document.getElementById('years_in_barangay');

      function getAge(birthdate) {
        const today = new Date();
        const bdate = new Date(birthdate);
        let age = today.getFullYear() - bdate.getFullYear();
        const m = today.getMonth() - bdate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < bdate.getDate())) {
          age--;
        }
        return age;
      }

      birthdateInput.addEventListener('change', () => {
        yearsInput.disabled = !birthdateInput.value;
        yearsInput.value = ''; // reset years input on birthdate change
      });

      yearsInput.addEventListener('input', () => {
        const age = getAge(birthdateInput.value);
        let val = yearsInput.value;

        // Remove non-numeric characters
        val = val.replace(/\D/g, '');

        if (val !== '') {
          const num = parseInt(val);
          if (num > age) {
            val = age.toString(); // clamp to max age
          }
        }

        yearsInput.value = val;
      });
    });
    </script>

    <div class="personal-group">
      <label>Voter? <span class="required">*</span></label>
      <div style="display: flex; gap: 1rem; align-items: center;">
        <label>
          <input type="radio" name="voter" value="Yes" required> Yes
        </label>
        <label>
          <input type="radio" name="voter" value="No" required> No
        </label>
      </div>
    </div>
  </div>

  <div id="precinct_container" style="display: none;">
    <label for="precinct_no">Precinct No:</label>
    <input type="text" name="precinct_no" id="precinct_no" value="">
  </div>

<script>
  const voterRadios = document.querySelectorAll('input[name="voter"]');
  const precinctContainer = document.getElementById('precinct_container');
  const precinctInput = document.getElementById('precinct_no');

  function togglePrecinct() {
    const selected = document.querySelector('input[name="voter"]:checked');
    if (selected && selected.value === 'Yes') {
      precinctContainer.style.display = 'block';
    } else {
      precinctContainer.style.display = 'none';
    }
  }

  voterRadios.forEach(radio => {
    radio.addEventListener('change', togglePrecinct);
  });

  window.addEventListener('DOMContentLoaded', togglePrecinct);
</script>
  
    <div class="personal-row"> 
      <div class="personal-group">
        <label>Mobile Number:</label>
        <input type="text" name="mobile" id="mobile" maxlength="11" pattern="\d{11}" inputmode="numeric">
      </div>

      <div class="personal-group">
        <label>Landline:</label>
        <input type="text" name="landline" id="landline" maxlength="7" pattern="\d{7}" inputmode="numeric">
      </div>

      <div class="personal-group">
        <label>Email:</label>
        <input type="email" name="email" value="">
      </div>
    </div>

    <script>
      function allowOnlyDigits(e) {
        const char = String.fromCharCode(e.which);
        if (!/[0-9]/.test(char)) {
          e.preventDefault();
        }
      }

      const mobileInput = document.getElementById('mobile');
      const landlineInput = document.getElementById('landline');

      mobileInput.addEventListener('keypress', allowOnlyDigits);
      landlineInput.addEventListener('keypress', allowOnlyDigits);

      mobileInput.addEventListener('input', () => {
        mobileInput.value = mobileInput.value.replace(/\D/g, '').slice(0, 11);
      });

      landlineInput.addEventListener('input', () => {
        landlineInput.value = landlineInput.value.replace(/\D/g, '').slice(0, 7);
      });
    </script>

  <div class="nav-buttons">
    <button type="button" onclick="prevStep()">Previous</button>
    <button type="button" onclick="nextStep()">Next</button>
  </div>
</div>

  <!-- Step 3: Education & Employment -->
<div class="form-step">
  <div class="form-section-title"><i class="fas fa-circle-dot" style="color:#3b82f6"></i>Step 3: Education & Employment</div>
  <div class="personal-row">
    <div class="personal-group">
      <label for="education">Highest Educational Attainment: <span class="required">*</span></label>
      <select name="education" id="education"required>
        <option value="">-- Select --</option>
        <option value="None">None</option>
        <option value="Nursery">Nursery</option>
        <option value="Kindergarten">Kindergarten</option>
        <option value="SPED">Special Needs Educational Program (SPED)</option>
        <option value="Elementary Undergraduate">Elementary Undergraduate</option>
        <option value="Elementary Graduate">Elementary Graduate</option>
        <option value="High School Undergraduate">High School Undergraduate</option>
        <option value="High School Graduate">High School Graduate</option>
        <option value="Senior High School Graduate">Senior High School Graduate</option>
        <option value="Vocational">Vocational</option>
        <option value="College Undergraduate">College Undergraduate</option>
        <option value="College Graduate">College Graduate</option>
        <option value="Post-Baccalaureate">Post-Baccalaureate</option>
        <option value="Postgraduate">Postgraduate</option>
      </select>
    </div>

   <div class="personal-group">
    <label for="employment_status">Employment Status: <span class="required">*</span></label>
    <select name="employment_status" id="employment_status" required>
      <option value="">Select status</option>
      <option value="Employed">Employed</option>
      <option value="Unemployed">Unemployed</option>
      <option value="Self-Employed">Self-Employed</option>
      <option value="Student">Student</option>
      <option value="Retired">Retired</option>
    </select>
  </div>
</div>

  <div class="personal-row">
    <div class="personal-group">
      <label>Occupation:</label>
      <input type="text" name="occupation">
    </div>
    <div class="personal-group">
      <label for="employer">Labor Force Type:</label>
      <select name="employer" id="employer">
        <option value="">-- Select --</option>
        <option value="Private">Private Sector</option>
        <option value="Government">Government / Public</option>
        <option value="Self-Employed">Self-Employed</option>
        <option value="OFW">OFW (Overseas Worker)</option>
        <option value="NGO">NGO / Non-Profit</option>
      </select>
    </div>
    <div class="personal-group">
      <label for="work_hours">Work Hours:</label>
      <select name="work_hours" id="work_hours">
        <option value="">-- Select --</option>
        <option value="Morning">Morning</option>
        <option value="Afternoon">Afternoon</option>
        <option value="Evening">Evening</option>
        <option value="None">None</option>
      </select>
    </div>
  </div>

 <script>
  const employmentSelect = document.getElementById('employment_status');
  const occupationInput = document.querySelector('input[name="occupation"]');
  const employerSelect = document.getElementById('employer');
  const workHoursSelect = document.getElementById('work_hours');

  function toggleEmploymentFields() {
    const val = employmentSelect.value;
    const isEmployed   = val === 'Employed';
    const hasSector    = isEmployed; // only show labor force type when formally employed
    const hasOccupation = isEmployed || val === 'Self-Employed';

    occupationInput.parentElement.style.display  = hasOccupation ? 'block' : 'none';
    employerSelect.parentElement.style.display   = hasSector     ? 'block' : 'none';
    workHoursSelect.parentElement.style.display  = isEmployed    ? 'block' : 'none';

    if (!hasOccupation)  occupationInput.value  = '';
    if (!hasSector)      employerSelect.value   = '';
    if (!isEmployed)     workHoursSelect.value  = '';
  }

  employmentSelect.addEventListener('change', toggleEmploymentFields);

  // Initialize on page load
  window.addEventListener('DOMContentLoaded', toggleEmploymentFields);
</script>

  <div class="personal-row">
  <!-- Out of School Youth -->
  <div class="personal-group">
    <label for="out_of_school_youth">Out of School Youth? <span class="required">*</span></label>
    <select name="out_of_school_youth" id="out_of_school_youth" required onchange="toggleStudentFields()">
      <option value="">-- Select --</option>
      <option value="No">No</option>
      <option value="Yes">Yes</option>
    </select>
  </div>

  <!-- Grade Level (only if OSY = No) -->
  <div class="personal-group" id="grade_level_container" style="display: none;">
    <label for="grade_level">Grade Level (For Students only):</label>
    <select name="grade_level" id="grade_level">
      <option value="">-- Select Grade Level --</option>
      <option value="Kinder">Kinder</option>
      <option value="Grade 1">Grade 1</option>
      <option value="Grade 2">Grade 2</option>
      <option value="Grade 3">Grade 3</option>
      <option value="Grade 4">Grade 4</option>
      <option value="Grade 5">Grade 5</option>
      <option value="Grade 6">Grade 6</option>
      <option value="Grade 7">Grade 7</option>
      <option value="Grade 8">Grade 8</option>
      <option value="Grade 9">Grade 9</option>
      <option value="Grade 10">Grade 10</option>
      <option value="Grade 11">Grade 11</option>
      <option value="Grade 12">Grade 12</option>
      <option value="College">College</option>
      <option value="Vocational">Vocational</option>
    </select>
  </div>

  <!-- School Name (only if OSY = No) -->
  <div class="personal-group" id="school_name_container" style="display: none;">
    <label for="school_name">School Name:</label>
    <input type="text" name="school_name" id="school_name">
  </div>
</div>

<script>
  const osySelect = document.getElementById('out_of_school_youth');
  const gradeLevelContainer = document.getElementById('grade_level_container');
  const schoolNameContainer = document.getElementById('school_name_container');

  function toggleStudentFields() {
    const isOSY = osySelect.value === 'Yes';
    gradeLevelContainer.style.display = isOSY ? 'none' : 'block';
    schoolNameContainer.style.display = isOSY ? 'none' : 'block';

    if (isOSY) {
      document.getElementById('grade_level').value = '';
      document.getElementById('school_name').value = '';
    }
  }

  window.addEventListener('DOMContentLoaded', toggleStudentFields);
</script>

 <div class="personal-row">
    <div class="personal-group">
      <label for="has_car">Has Car? <span class="required">*</span></label>
      <select name="has_car" id="has_car"required>
        <option value="">-- Select --</option>
        <option value="No">No</option>
        <option value="Yes">Yes</option>
      </select>
    </div>
    <div class="personal-group">
      <label for="has_motorcycle">Has Motorcycle? <span class="required">*</span></label>
      <select name="has_motorcycle" id="has_motorcycle"required>
        <option value="">-- Select --</option>
        <option value="No">No</option>
        <option value="Yes">Yes</option>
      </select>
    </div>
  </div>

<!-- Car and Motorcycle Info Inputs -->
<div class="personal-row" style="display: flex; gap: 2rem;" id="vehicle_info_section">
  <!-- Car Info Column -->
  <div id="car_info_container" style="flex: 1; display: none; display: flex; flex-direction: column;">
    <h4>Car Information</h4>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <div class="personal-group">
        <label for="car_brand">Brand:</label>
        <input type="text" name="car_brand" id="car_brand">
      </div>
      <div class="personal-group">
        <label for="car_model">Model:</label>
        <input type="text" name="car_model" id="car_model">
      </div>
      <div class="personal-group">
        <label for="car_color">Color:</label>
        <input type="text" name="car_color" id="car_color">
      </div>
      <div class="personal-group">
        <label for="car_plate">Plate Number:</label>
        <input type="text" name="car_plate" id="car_plate">
      </div>
    </div>
  </div>

  <!-- Motorcycle Info Column -->
  <div id="motorcycle_info_container" style="flex: 1; display: none; display: flex; flex-direction: column;">
    <h4>Motorcycle Information</h4>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <div class="personal-group">
        <label for="motor_brand">Brand:</label>
        <input type="text" name="motor_brand" id="motor_brand">
      </div>
      <div class="personal-group">
        <label for="motor_model">Model:</label>
        <input type="text" name="motor_model" id="motor_model">
      </div>
      <div class="personal-group">
        <label for="motor_color">Color:</label>
        <input type="text" name="motor_color" id="motor_color">
      </div>
      <div class="personal-group">
        <label for="motor_plate">Plate Number:</label>
        <input type="text" name="motor_plate" id="motor_plate">
      </div>
    </div>
  </div>
</div>

  <script>
  const hasCarSelect = document.getElementById('has_car');
  const carInfoContainer = document.getElementById('car_info_container');

  const hasMotorSelect = document.getElementById('has_motorcycle');
  const motorInfoContainer = document.getElementById('motorcycle_info_container');

  function toggleCarInfo() {
    if (hasCarSelect.value === 'Yes') {
      carInfoContainer.style.display = 'flex';
    } else {
      carInfoContainer.style.display = 'none';
      document.getElementById('car_brand').value = '';
      document.getElementById('car_model').value = '';
      document.getElementById('car_color').value = '';
      document.getElementById('car_plate').value = '';
    }
  }

  function toggleMotorInfo() {
    if (hasMotorSelect.value === 'Yes') {
      motorInfoContainer.style.display = 'flex';
    } else {
      motorInfoContainer.style.display = 'none';
      document.getElementById('motor_brand').value = '';
      document.getElementById('motor_model').value = '';
      document.getElementById('motor_color').value = '';
      document.getElementById('motor_plate').value = '';
    }
  }

  hasCarSelect.addEventListener('change', toggleCarInfo);
  hasMotorSelect.addEventListener('change', toggleMotorInfo);

  window.addEventListener('DOMContentLoaded', () => {
    toggleCarInfo();
    toggleMotorInfo();
  });
</script>

  <div class="nav-buttons">
    <button type="button" onclick="prevStep()">Previous</button>
    <button type="button" onclick="nextStep()">Next</button>
  </div>
</div>

<!-- Step 4: Other Info & Pets -->
<div class="form-step">
  <div class="form-section-title" style="font-family:Syne,sans-serif;font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid #3b82f6;display:flex;align-items:center;gap:8px"><i class="fas fa-circle-dot" style="color:#3b82f6;font-size:12px"></i>Step 4: Special Categories & Pets</div>

  <div class="personal-row">
    <div class="personal-group">
      <label for="is_senior">Senior Citizen?</label>
      <select name="is_senior" id="is_senior">
        <option value="">-- Select --</option>
        <option value="No">No</option>
        <option value="Yes">Yes</option>
      </select>
    </div>
    <div class="personal-group">
      <label for="pwd_status">PWD:</label>
      <select name="pwd_status" id="pwd_status">
        <option value="">-- Select --</option>
        <option value="No">No</option>
        <option value="Yes">Yes</option>
      </select>
    </div>
    <div class="personal-group">
      <label for="solo_parent_status">Solo Parent?</label>
      <select name="solo_parent_status" id="solo_parent_status">
        <option value="">-- Select --</option>
        <option value="No">No</option>
        <option value="Yes">Yes</option>
      </select>
    </div>
  </div>

  <div class="personal-row">
    <div class="personal-group" id="osca_container" style="display: none;">
      <label>OSCA ID:</label>
      <input type="text" name="osca_id">
    </div>
    <div class="personal-group" id="pwd_container" style="display: none;">
      <label>PWD ID:</label>
      <input type="text" name="pwd_id">
      <label>Disability:</label>
      <input type="text" name="disability_type">
    </div>
    <div class="personal-group" id="solo_container" style="display: none;">
      <label>Solo Parent ID:</label>
      <input type="text" name="solo_parent_id">
    </div>
  </div>

<script>
  function toggleVisibility(selectId, containerId) {
    const select = document.getElementById(selectId);
    const container = document.getElementById(containerId);
    container.style.display = (select.value === "Yes") ? "block" : "none";
    select.addEventListener('change', () => {
      container.style.display = (select.value === "Yes") ? "block" : "none";
    });
  }

  window.addEventListener('DOMContentLoaded', () => {
    toggleVisibility('is_senior', 'osca_container');
    toggleVisibility('pwd_status', 'pwd_container');
    toggleVisibility('solo_parent_status', 'solo_container');
  });
</script>


  <label>Do you have pets? <span class="required">*</span></label>
  <select name="has_pets" id="has_pets" onchange="togglePetFields(this.value)"required>
    <option value="No">No</option>
    <option value="Yes">Yes</option>
  </select>

  <div id="pet_fields" style="display: none;">
  <h3>Pet Information</h3>
  <div id="pet_container">
    <div class="pet_entry">
      <label>Pet Name:</label>
      <input type="text" name="pet_name[]">

      <div class="personal-row">
        <div class="personal-group">
          <label>Pet Sex:</label>
          <select name="pet_sex[]">
            <option value="">-- Select --</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div class="personal-group">
          <label>Pet Age:</label>
          <input type="number" name="pet_age[]">
        </div>
        <div class="personal-group">
          <label>Pet Color:</label>
          <input type="text" name="pet_color[]">
        </div>
      </div>

      <div class="personal-row">
        <div class="personal-group">
          <label>Dog/Cat/Others:</label>
          <select name="pet_type[]" class="pet-type-select">
            <option value="">-- Select --</option>
            <option value="Dog">Dog</option>
            <option value="Cat">Cat</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="personal-group">
          <label>If others, specify pet (e.g. birds, monkeys):</label>
          <input type="text" name="other_pets[]">
        </div>

        <script>
          document.addEventListener('DOMContentLoaded', function () {
            const petTypeSelect = document.querySelector('.pet-type-select');
            const otherPetsGroup = document.querySelector('.other-pets-group');

            petTypeSelect.addEventListener('change', function () {
              if (this.value === 'Other') {
                otherPetsGroup.style.display = 'block';
              } else {
                otherPetsGroup.style.display = 'none';
              }
            });
          });
        </script>
        
        <div class="personal-group">
          <label>Breeder?</label>
          <select name="breeder_status[]">
            <option value="">-- Select --</option>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
          </select>
        </div>
      </div>     
        <button type="button" class="pet-button" onclick="removePet(this)">Remove</button>
        <hr>
      </div>
      </div>
        <button type="button" class="pet-button" onclick="addPet()">Add Pet</button>
      </div>

  <div class="nav-buttons" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid #f1f5f9">
    <button type="button" onclick="prevStep()" style="background:#f1f5f9;color:#0f172a">
      <i class="fas fa-chevron-left"></i> Previous
    </button>
    <button type="button" id="submitButton" onclick="showConfirmPanel()"
      style="background:#22c55e;color:#fff;padding:.65rem 2rem;font-size:.95rem">
      <i class="fas fa-check-circle"></i> Register Resident
    </button>
  </div>


</div>
  </div>
</form>

</div><!-- end singleMode -->

<!-- ══ BULK MODE ══════════════════════════════════════════════════════════ -->
<div id="bulkMode" style="display:<?= $initial_mode==='bulk' ? 'block' : 'none' ?>">

  <!-- Tip box -->
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:12px;color:#92400e;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:10px">
    <i class="fas fa-lightbulb" style="font-size:15px;margin-top:1px;flex-shrink:0"></i>
    <div>
      <strong>Tips for multiple entries:</strong><br>
      • Fill at minimum: <strong>First Name, Last Name, Birthdate, Gender</strong> per row<br>
      • Each row gets an auto-generated Resident ID (e.g. <code>04-0526-1001-00</code>)<br>
      • Duplicate entries (same name + birthdate) are automatically skipped<br>
      • <span style="color:#3b82f6;font-weight:600"><i class="fas fa-paste"></i> Excel tip:</span> Copy rows in Excel → click any cell in the table → Paste
    </div>
  </div>

  <!-- Bulk results (shown after save) -->
  <?php if (!empty($bulk_results)): ?>
  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem" data-bulk-results>
    <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:10px">
      Results: <span style="color:#15803d"><?= $bulk_added ?? 0 ?> added</span>
      <?php if (!empty($bulk_errors)): ?>, <span style="color:#be123c"><?= $bulk_errors ?> failed</span><?php endif; ?>
    </div>
    <?php foreach ($bulk_results as $br): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
      <i class="fas fa-<?= $br['ok'] ? 'check-circle' : 'times-circle' ?>" style="color:<?= $br['ok'] ? '#22c55e' : '#f43f5e' ?>;flex-shrink:0"></i>
      <span style="font-weight:600"><?= htmlspecialchars($br['name']) ?></span>
      <?php if ($br['ok'] && isset($br['code'])): ?>
        <span style="font-family:monospace;font-size:11px;background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:20px"><?= $br['code'] ?></span>
      <?php endif; ?>
      <span style="color:#64748b;font-size:12px"><?= htmlspecialchars($br['msg']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:10px;display:flex;gap:8px">
      <a href="Display_List.php" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:#3b82f6;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600">
        <i class="fas fa-users"></i> View Residents
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Table section -->
  <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:4px"><i class="fas fa-layer-group" style="color:#3b82f6;margin-right:8px"></i>Resident Data Entry</div>
  <div style="font-size:13px;color:#64748b;margin-bottom:1rem">Fill each row — add as many rows as needed.</div>

  <div style="overflow-x:auto;margin-bottom:.75rem;border-radius:10px;border:1px solid #e2e8f0">
    <table id="bulkTable" style="width:100%;border-collapse:collapse;font-size:13px;min-width:900px">
      <thead>
        <tr style="background:#f8fafc">
          <th style="padding:9px 10px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">#</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">First Name *</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Middle Name</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Last Name *</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Suffix</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Birthdate *</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Gender *</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Marital Status</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Citizenship</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Religion</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Perm. Address</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Mobile</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Employment</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Head of Family</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Voter</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Senior</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">PWD</th>
          <th style="padding:9px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap">Solo Parent</th>
          <th style="padding:9px 10px;border-bottom:1px solid #e2e8f0"></th>
        </tr>
      </thead>
      <tbody id="bulkBody"></tbody>
    </table>
  </div>

  <!-- Add row button -->
  <button type="button" onclick="addBulkRow()"
    style="background:#f0fdf4;color:#15803d;border:2px dashed #bbf7d0;border-radius:8px;padding:10px;width:100%;font-size:13px;font-weight:600;cursor:pointer;font-family:Inter,sans-serif;transition:all .2s;margin-bottom:1.25rem"
    onmouseover="this.style.borderColor='#22c55e';this.style.background='#dcfce7'"
    onmouseout="this.style.borderColor='#bbf7d0';this.style.background='#f0fdf4'">
    <i class="fas fa-plus"></i> Add Row
  </button>

  <!-- Confirm & save row -->
  <form method="POST" action="Register.php" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="bulk_rows" id="bulkRowsInput">
    <input type="hidden" name="_bulk_submit" value="1">
    <div style="display:flex;align-items:center;gap:10px;padding-top:1rem;border-top:1px solid #f1f5f9">
      <button type="button" onclick="clearBulk()"
        style="padding:9px 18px;border:1px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:13px;font-weight:500;cursor:pointer;color:#0f172a;display:flex;align-items:center;gap:6px">
        <i class="fas fa-trash" style="color:#f43f5e"></i> Clear All
      </button>
      <button type="button" onclick="openBulkPwModal()" id="bulkSubmitBtn"
        style="margin-left:auto;padding:9px 24px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;white-space:nowrap"
        onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#3b82f6'">
        <i class="fas fa-save"></i> Save All Residents
      </button>
    </div>
  </form>

  <!-- ── Floating password modal ── -->
  <style>
  @keyframes bpm-overlay-in { from{opacity:0} to{opacity:1} }
  @keyframes bpm-modal-in   { from{opacity:0;transform:translate(-50%,-48%) scale(.96)} to{opacity:1;transform:translate(-50%,-50%) scale(1)} }
  #bulkPwModal { animation: bpm-modal-in .2s cubic-bezier(.22,1,.36,1) both }
  #bulkPwOverlay { animation: bpm-overlay-in .18s ease both }
  #bulkModalConfirmBtn:hover { background:#1d4ed8 !important }
  #bulkModalConfirmBtn:disabled { opacity:.7;cursor:not-allowed }
  </style>

  <div id="bulkPwOverlay" onclick="closeBulkPwModal()" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:900"></div>

  <div id="bulkPwModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:901;background:#fff;border-radius:16px;width:400px;max-width:calc(100vw - 2rem);box-shadow:0 24px 64px rgba(15,23,42,.18),0 0 0 1px #e2e8f0">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fas fa-lock" style="color:#3b82f6;font-size:15px"></i>
        </div>
        <div>
          <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:.95rem;color:#0f172a;line-height:1.2">Password Required</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:1px">Saving <span id="bulkSaveCount" style="font-weight:700;color:#3b82f6"></span> resident(s)</div>
        </div>
      </div>
      <button type="button" onclick="closeBulkPwModal()"
        style="width:32px;height:32px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;color:#64748b;font-size:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1">
        &times;
      </button>
    </div>

    <!-- Body -->
    <div style="padding:1.5rem">
      <div style="font-size:13px;color:#475569;margin-bottom:1rem;line-height:1.5">
        Enter your <strong style="color:#0f172a">admin password</strong> to authorize this registration and save the records.
      </div>

      <div style="position:relative;margin-bottom:.75rem">
        <i class="fas fa-key" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none"></i>
        <input type="password" id="bulkConfirmPw" placeholder="Enter admin password"
          style="width:100%;padding:11px 42px 11px 36px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:Inter,sans-serif;outline:none;box-sizing:border-box;color:#0f172a;background:#fafafa;transition:border-color .15s,box-shadow .15s"
          onfocus="this.style.borderColor='#3b82f6';this.style.boxShadow='0 0 0 3px rgba(59,130,246,.1)';this.style.background='#fff';this.setAttribute('autocomplete','off');this.value=''"
          onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none';this.style.background='#fafafa'"
          onkeydown="if(event.key==='Enter')confirmBulkSave();if(event.key==='Escape')closeBulkPwModal()"
          autocomplete="off" data-lpignore="true" data-1p-ignore>
        <button type="button" onclick="toggleBulkPw()" tabindex="-1"
          style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;padding:5px;line-height:1;z-index:2">
          <i class="fas fa-eye" id="bulkPwEyeIcon"></i>
        </button>
      </div>

      <div id="bulkPwErr" style="display:none;background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;padding:8px 12px;margin-bottom:.75rem;font-size:12px;color:#be123c">
        <i class="fas fa-circle-exclamation" style="margin-right:5px"></i><span id="bulkPwErrMsg">Incorrect password.</span>
      </div>

      <!-- Buttons -->
      <div style="display:grid;grid-template-columns:1fr 2fr;gap:8px;margin-top:1.25rem">
        <button type="button" onclick="closeBulkPwModal()"
          style="padding:11px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer;color:#475569;background:#fff;width:100%">
          Cancel
        </button>
        <button type="button" onclick="confirmBulkSave()" id="bulkModalConfirmBtn"
          style="padding:11px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-family:Inter,sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;transition:background .15s">
          <i class="fas fa-floppy-disk"></i> Save All Residents
        </button>
      </div>
    </div>
  </div>

</div><!-- end bulkMode -->





<script>
  document.querySelectorAll('.capitalize').forEach(input => {
    input.addEventListener('blur', () => {
      if (input.value.trim() !== '') {
        input.value = input.value
          .split(' ')
          .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
          .join(' ');
      }
    });
  });
</script>

<script>
  let currentStep = 0;
  const steps = document.querySelectorAll(".form-step");

  function validateStep(step) {
    const stepDiv = steps[step];
    const requiredFields = stepDiv.querySelectorAll("[required]");
    for (let field of requiredFields) {
      // For selects, check value is not empty
      if (field.tagName.toLowerCase() === "select" && field.value === "") {
        alert("Please fill out all required fields.");
        field.focus();
        return false;
      }
      // For inputs, check value is not empty or whitespace only
      if ((field.tagName.toLowerCase() === "input" || field.tagName.toLowerCase() === "textarea") && !field.value.trim()) {
        alert("Please fill out all required fields.");
        field.focus();
        return false;
      }
    }
    return true;
  }

  function showStep(step) {
    steps.forEach((stepDiv, idx) => {
      stepDiv.classList.toggle("active", idx === step);
    });
    updateStepUI(step, steps.length);
  }

  function updateStepUI(step, total) {
  document.querySelectorAll('.step-ind').forEach((el,i) => {
    el.classList.remove('active','done');
    if(i === step) el.classList.add('active');
    else if(i < step) el.classList.add('done');
  });
  document.querySelectorAll('.step-line').forEach((el,i) => {
    el.classList.toggle('done', i < step);
  });
  const pct = Math.min(((step+1)/4*100), 100).toFixed(1);
  const pb = document.getElementById('regProgressFill');
  if(pb) pb.style.width = pct + '%';
  window.scrollTo({top:0,behavior:'smooth'});
}
function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < steps.length - 1) {
      currentStep++;
      showStep(currentStep);
    }
  }

  function prevStep() {
    if (currentStep > 0) {
      currentStep--;
      showStep(currentStep);
    }
  }

  // Initialize form on page load
  window.addEventListener('DOMContentLoaded', () => {
    showStep(currentStep);
  });
</script>

<script>
function togglePetFields(value) {
  const petFields = document.getElementById('pet_fields');
  if (value === 'Yes') {
    petFields.style.display = 'block';
  } else {
    petFields.style.display = 'none';
  }
}

function addPet() {
  const container = document.getElementById('pet_container');
  const entry = document.querySelector('.pet_entry');
  const newEntry = entry.cloneNode(true);

  newEntry.querySelectorAll('input, select').forEach(input => input.value = '');

  container.appendChild(newEntry);
}

function removePet(button) {
  const container = document.getElementById('pet_container');
  const entries = container.getElementsByClassName('pet_entry');

  if (entries.length > 1) {
    button.parentElement.remove();
  } else {
    alert("You must have at least one pet entry.");
  }
}
</script>



<script>
  const headSelect = document.getElementById('head_of_family');
  const relationshipContainer = document.getElementById('relationship-container');

  const firstName = document.querySelector('input[name="first_name"]');
  const middleName = document.querySelector('input[name="middle_name"]');
  const lastName = document.querySelector('input[name="last_name"]');
  const suffix = document.querySelector('input[name="suffix"]');

  const headFirstName = document.getElementById('head_first_name');
  const headMiddleName = document.getElementById('head_middle_name');
  const headLastName = document.getElementById('head_last_name');
  const headSuffix = document.getElementById('head_suffix');

  function copyNameToHead() {
    headFirstName.value = firstName.value;
    headMiddleName.value = middleName.value;
    headLastName.value = lastName.value;
    headSuffix.value = suffix.value;
  }

  function clearHeadName() {
    headFirstName.value = '';
    headMiddleName.value = '';
    headLastName.value = '';
    headSuffix.value = '';
  }

  headSelect.addEventListener('change', function () {
    if (this.value === 'No') {
      relationshipContainer.style.display = 'block';
      clearHeadName(); // clear values when not head
    } else {
      relationshipContainer.style.display = 'none';
      copyNameToHead();
    }
  });

  // Update head fields live if user changes original names and selected "Yes"
  [firstName, middleName, lastName, suffix].forEach(field => {
    field.addEventListener('input', () => {
      if (headSelect.value === 'Yes') {
        copyNameToHead();
      }
    });
  });

  // On page load (for edit forms)
  window.addEventListener('DOMContentLoaded', () => {
    if (headSelect.value === 'No') {
      relationshipContainer.style.display = 'block';
    } else {
      copyNameToHead(); // preload values if head
    }
  });
</script>
<script>
// ── Mode switcher ──────────────────────────────────────────────────────────
function switchMode(mode) {
  const single = document.getElementById('singleMode');
  const bulk   = document.getElementById('bulkMode');
  const btnS   = document.getElementById('btnSingle');
  const btnB   = document.getElementById('btnBulk');
  if (!single || !bulk) return;
  if (mode === 'bulk') {
    single.style.display = 'none';
    bulk.style.display   = 'block';
    btnS.style.background = 'none';  btnS.style.color = '#64748b'; btnS.style.boxShadow = 'none';
    btnB.style.background = '#fff';  btnB.style.color = '#0f172a'; btnB.style.boxShadow = '0 1px 4px rgba(0,0,0,.08)';
    if (document.getElementById('bulkBody').children.length === 0) {
      addBulkRow(); addBulkRow(); addBulkRow();
    }
  } else {
    single.style.display = 'block';
    bulk.style.display   = 'none';
    btnS.style.background = '#fff';  btnS.style.color = '#0f172a'; btnS.style.boxShadow = '0 1px 4px rgba(0,0,0,.08)';
    btnB.style.background = 'none'; btnB.style.color = '#64748b'; btnB.style.boxShadow = 'none';
  }
}
<?php if (!empty($bulk_results)): ?>
document.addEventListener('DOMContentLoaded', () => switchMode('bulk'));
<?php elseif (isset($_GET['mode']) && $_GET['mode'] === 'bulk'): ?>
document.addEventListener('DOMContentLoaded', () => switchMode('bulk'));
<?php endif; ?>

// ── Bulk table ─────────────────────────────────────────────────────────────
const TODAY_B = new Date().toISOString().split('T')[0];
let bulkRowCount = 0;

function mkS(field, opts, def) {
  let s = `<select data-field="${field}" style="width:100%;padding:5px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:Inter,sans-serif;outline:none;box-sizing:border-box">`;
  opts.forEach(o => s += `<option${o===def?' selected':''}>${o}</option>`);
  return s + '</select>';
}
function mkI(field, ph) {
  return `<input type="text" data-field="${field}" placeholder="${ph}" style="width:100%;padding:5px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:Inter,sans-serif;outline:none;box-sizing:border-box">`;
}

function addBulkRow(data={}) {
  bulkRowCount++;
  const n = bulkRowCount;
  const tr = document.createElement('tr');
  tr.id = 'brow-' + n;
  tr.style.cssText = 'border-bottom:1px solid #f1f5f9;transition:background .15s';
  tr.onmouseover = () => tr.style.background = '#f8fafc';
  tr.onmouseout  = () => tr.style.background = '';
  tr.innerHTML = `
    <td style="padding:5px 8px;text-align:center;color:#94a3b8;font-size:11px;font-weight:700">${n}</td>
    <td style="padding:3px 4px">${mkI('first_name','First *')}</td>
    <td style="padding:3px 4px">${mkI('middle_name','Middle')}</td>
    <td style="padding:3px 4px">${mkI('last_name','Last *')}</td>
    <td style="padding:3px 4px"><input type="text" data-field="suffix" placeholder="Jr" style="width:46px;padding:5px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:Inter,sans-serif;outline:none"></td>
    <td style="padding:3px 4px"><input type="date" data-field="birthdate" max="${TODAY_B}" style="width:130px;padding:5px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:Inter,sans-serif;outline:none" value="${data.birthdate||''}" onwheel="this.blur()" ondragstart="return false"></td>
    <td style="padding:3px 4px">${mkS('gender',['Male','Female','Other'],data.gender||'Male')}</td>
    <td style="padding:3px 4px">${mkS('marital_status',['Single','Married','Widowed','Divorced','Annulled'],data.marital_status||'Single')}</td>
    <td style="padding:3px 4px"><input type="text" data-field="citizenship" value="${data.citizenship||'Filipino'}" style="width:85px;padding:5px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:Inter,sans-serif;outline:none"></td>
    <td style="padding:3px 4px">${mkI('religion','Religion')}</td>
    <td style="padding:3px 4px">${mkI('perm_address','Address')}</td>
    <td style="padding:3px 4px"><input type="text" data-field="mobile" placeholder="09XXXXXXXXX" style="width:110px;padding:5px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-family:Inter,sans-serif;outline:none"></td>
    <td style="padding:3px 4px">${mkS('employment_status',['','Employed','Unemployed','Self-Employed','Student','Retired'],data.employment_status||'')}</td>
    <td style="padding:3px 4px">${mkS('head_of_family',['Yes','No'],data.head_of_family||'Yes')}</td>
    <td style="padding:3px 4px">${mkS('voter',['Yes','No'],data.voter||'No')}</td>
    <td style="padding:3px 4px">${mkS('is_senior',['No','Yes'],'No')}</td>
    <td style="padding:3px 4px">${mkS('pwd_status',['No','Yes'],'No')}</td>
    <td style="padding:3px 4px">${mkS('solo_parent_status',['No','Yes'],'No')}</td>
    <td style="padding:5px 8px;text-align:center">
      <button type="button" onclick="document.getElementById('brow-${n}').remove()"
        style="background:#fff1f2;color:#f43f5e;border:1px solid #fecdd3;border-radius:6px;padding:4px 8px;font-size:11px;cursor:pointer;font-family:Inter,sans-serif">
        <i class="fas fa-times"></i>
      </button>
    </td>`;
  const textFields = ['first_name','middle_name','last_name','religion','perm_address','mobile'];
  textFields.forEach(f => { if (data[f]) { const el = tr.querySelector(`[data-field="${f}"]`); if(el) el.value = data[f]; }});
  document.getElementById('bulkBody').appendChild(tr);
}

function clearBulk() {
  document.getElementById('bulkBody').innerHTML = '';
  bulkRowCount = 0;
  addBulkRow(); addBulkRow(); addBulkRow();
}

let _bulkRows = [];

function openBulkPwModal() {
  const rows = [];
  let hasErr = false;
  document.querySelectorAll('#bulkBody tr').forEach(tr => {
    const obj = {};
    tr.querySelectorAll('[data-field]').forEach(el => { obj[el.dataset.field] = el.value.trim(); });
    if (obj.first_name || obj.last_name) {
      if (!obj.first_name || !obj.last_name || !obj.birthdate) {
        tr.style.background = '#fff1f2'; hasErr = true;
      } else {
        tr.style.background = ''; rows.push(obj);
      }
    }
  });
  if (hasErr) { alert('Rows highlighted in red are missing required fields (First Name, Last Name, Birthdate).'); return; }
  if (!rows.length) { alert('No data to save. Add at least one row.'); return; }
  _bulkRows = rows;

  const countEl = document.getElementById('bulkSaveCount');
  if (countEl) countEl.textContent = rows.length;

  document.getElementById('bulkConfirmPw').value = '';
  document.getElementById('bulkPwErr').style.display = 'none';
  document.getElementById('bulkPwOverlay').style.display = 'block';
  document.getElementById('bulkPwModal').style.display = 'block';
  setTimeout(() => document.getElementById('bulkConfirmPw').focus(), 80);
}

function closeBulkPwModal() {
  document.getElementById('bulkPwOverlay').style.display = 'none';
  document.getElementById('bulkPwModal').style.display = 'none';
  document.getElementById('bulkConfirmPw').value = '';
  document.getElementById('bulkPwErr').style.display = 'none';
}

function toggleBulkPw() {
  const inp = document.getElementById('bulkConfirmPw');
  const icon = document.getElementById('bulkPwEyeIcon');
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
}

function confirmBulkSave() {
  const pw = document.getElementById('bulkConfirmPw').value;
  const errBox = document.getElementById('bulkPwErr');
  const errMsg = document.getElementById('bulkPwErrMsg');
  if (!pw) { errBox.style.display='block'; errMsg.textContent='Please enter your admin password.'; document.getElementById('bulkConfirmPw').focus(); return; }
  errBox.style.display = 'none';

  const btn = document.getElementById('bulkModalConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

  const fd2 = new FormData(); fd2.append('password', pw);
  fetch('verify_secretary.php', {method:'POST', body:fd2})
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        document.getElementById('bulkRowsInput').value = JSON.stringify(_bulkRows);
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        document.getElementById('bulkForm').submit();
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save All Residents';
        errBox.style.display = 'block';
        errMsg.textContent = res.message || 'Incorrect password.';
        document.getElementById('bulkConfirmPw').value = '';
        document.getElementById('bulkConfirmPw').focus();
      }
    }).catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save All Residents';
      errBox.style.display = 'block'; errMsg.textContent = 'Server error. Try again.';
    });
}

// ── Paste from Excel ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const tbl = document.getElementById('bulkTable');
  if (!tbl) return;
  tbl.addEventListener('paste', e => {
    const txt = (e.clipboardData || window.clipboardData).getData('text');
    if (!txt.includes('\t')) return;
    e.preventDefault();
    const rows = txt.trim().split('\n').map(r => r.split('\t'));
    const keys = ['first_name','middle_name','last_name','suffix','birthdate','gender',
                  'marital_status','citizenship','religion','perm_address','mobile','employment_status'];
    rows.forEach(cols => {
      const d = {};
      keys.forEach((k, i) => { if (cols[i] !== undefined) d[k] = cols[i].trim(); });
      if (d.first_name || d.last_name) addBulkRow(d);
    });
  });
});
</script>
<script>
function showConfirmPanel() {
  document.getElementById('confirmOverlay').style.display = 'block';
  document.getElementById('confirmModal').style.display = 'block';
  document.getElementById('singlePwErr').style.display = 'none';
  document.getElementById('confirmPassword').value = '';
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('confirmPassword').focus(), 100);
}
function hideConfirmPanel() {
  document.getElementById('confirmOverlay').style.display = 'none';
  document.getElementById('confirmModal').style.display = 'none';
  document.getElementById('confirmPassword').value = '';
  document.body.style.overflow = '';
}
function toggleConfirmPw() {
  const inp = document.getElementById('confirmPassword');
  const icon = document.getElementById('confirmPwEyeIcon');
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') hideConfirmPanel(); });
function doSubmitSingle() {
  const pw = document.getElementById('confirmPassword').value;
  const errBox = document.getElementById('singlePwErr');
  const errMsg = document.getElementById('singlePwErrMsg');
  const btn    = document.getElementById('confirmSaveBtn');
  if (!pw) { errBox.style.display='block'; errMsg.textContent='Please enter your admin password.'; return; }
  errBox.style.display = 'none';
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
  const fd = new FormData();
  fd.append('password', pw);
  fetch('verify_secretary.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        btn.innerHTML = '<i class="fas fa-check"></i> Saving…';
        document.getElementById('multiStepForm').submit();
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus"></i> Confirm & Save';
        errBox.style.display = 'block';
        errMsg.textContent = res.message || 'Incorrect password.';
        document.getElementById('confirmPassword').value = '';
        document.getElementById('confirmPassword').focus();
      }
    }).catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-user-plus"></i> Confirm & Save';
      errBox.style.display = 'block';
      errMsg.textContent = 'Server error. Please try again.';
    });
}
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
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
</script>


<!-- ══ CONFIRM REGISTRATION MODAL ══════════════════════════════════════════ -->
<div id="confirmOverlay" onclick="hideConfirmPanel()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1200;backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)"></div>
<div id="confirmModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1201;width:100%;max-width:420px;padding:0 1rem;box-sizing:border-box">
  <div style="background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden">

    <!-- Modal header -->
    <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:1.75rem 1.75rem 1.25rem;text-align:center">
      <div style="width:60px;height:60px;border-radius:50%;overflow:hidden;margin:0 auto 1rem;border:2px solid rgba(255,255,255,.2)">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover" alt="">
      </div>
      <div style="font-family:'Syne',sans-serif;font-size:1.15rem;font-weight:800;color:#fff;margin-bottom:4px">Confirm Registration</div>
      <div style="font-size:12px;color:rgba(255,255,255,.5)">Enter your admin password to save this resident.</div>
    </div>

    <!-- Modal body -->
    <div style="padding:1.5rem 1.75rem 1.75rem">
      <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:8px">Admin Password</label>
      <div style="position:relative">
        <i class="fas fa-key" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px;pointer-events:none"></i>
        <input type="password" id="confirmPassword" placeholder="Enter your password…"
          style="width:100%;padding:11px 44px 11px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border .2s"
          onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'"
          onkeydown="if(event.key==='Enter')doSubmitSingle()">
        <button type="button" id="confirmPwEye" onclick="toggleConfirmPw()"
          style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;padding:4px">
          <i class="fas fa-eye" id="confirmPwEyeIcon"></i>
        </button>
      </div>
      <div id="singlePwErr" style="display:none;margin-top:8px;color:#be123c;font-size:12px;background:#fff1f2;border:1px solid #fecdd3;border-radius:7px;padding:7px 11px">
        <i class="fas fa-exclamation-circle"></i> <span id="singlePwErrMsg">Incorrect password.</span>
      </div>

      <div style="display:flex;gap:10px;margin-top:1.25rem">
        <button type="button" onclick="hideConfirmPanel()"
          style="flex:1;padding:11px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:10px;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s"
          onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
          Cancel
        </button>
        <button type="button" id="confirmSaveBtn" onclick="doSubmitSingle()"
          style="flex:2;padding:11px;background:#22c55e;color:#fff;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px"
          onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
          <i class="fas fa-user-plus"></i> Confirm & Save
        </button>
      </div>
    </div>
  </div>
</div>

</body>
</html>