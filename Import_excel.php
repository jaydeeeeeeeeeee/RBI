<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit(); }
include 'Residents_DB.php';
include 'csrf_helper.php';
include 'generate_id.php';
include 'role_helper.php';

// Must have unlocked the resident list (password gate) and IP must match
$ip = $_SERVER['REMOTE_ADDR'];
$list_unlocked = isset($_SESSION['list_unlocked'])
    && isset($_SESSION['list_unlock_time'])
    && (time() - $_SESSION['list_unlock_time']) < 1800
    && ($_SESSION['list_unlock_ip'] ?? '') === $ip;

if (!$list_unlocked) {
    header('Location: Display_List.php');
    exit();
}

function normalize($string) {
    return strtolower(preg_replace('/[^a-z]/', '', $string));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Display_List.php');
    exit();
}

csrf_verify();

if (!is_uploaded_file($_FILES['csv_file']['tmp_name'] ?? '')) {
    header('Location: Display_List.php?import=error&reason=nofile');
    exit();
}

// Validate file extension
$orig_name = $_FILES['csv_file']['name'] ?? '';
if (strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) !== 'csv') {
    header('Location: Display_List.php?import=error&reason=wrongtype');
    exit();
}

// Validate file size (max 5 MB)
if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
    header('Location: Display_List.php?import=error&reason=toobig');
    exit();
}

$filename = $_FILES['csv_file']['tmp_name'];
$handle   = fopen($filename, 'r');

// Strip UTF-8 BOM if present
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

// Read and normalize header row
$raw_headers = fgetcsv($handle);
if (!$raw_headers) {
    fclose($handle);
    header('Location: Home.php?import=error&reason=empty');
    exit();
}
$headers = [];
foreach ($raw_headers as $index => $label) {
    $headers[normalize($label)] = $index;
}

function col($key, $headers, $data) {
    return isset($headers[$key]) ? trim($data[$headers[$key]] ?? '') : '';
}
function esc($conn, $val) {
    return mysqli_real_escape_string($conn, $val);
}

ensureResidentCodeColumn($conn);

$imported = 0;
$failed   = 0;
$row_num  = 1; // starts at 1 (after header)

while (($data = fgetcsv($handle, 10000, ',')) !== false) {
    $row_num++;
    // Skip completely empty rows
    if (empty(array_filter($data, fn($v) => trim($v) !== ''))) continue;

    // Require at least first name and last name
    $fn_check = col('firstname', $headers, $data);
    $ln_check = col('lastname',  $headers, $data);
    if (empty(trim($fn_check)) && empty(trim($ln_check))) { $failed++; continue; }

    $firstname         = esc($conn, col('firstname',         $headers, $data));
    $middlename        = esc($conn, col('middlename',        $headers, $data));
    $lastname          = esc($conn, col('lastname',          $headers, $data));
    $suffix            = esc($conn, col('suffix',            $headers, $data));
    $headoffamily      = esc($conn, col('headoffamily',      $headers, $data));
    $relationship      = esc($conn, col('relationship',      $headers, $data));
    $headfirstname     = esc($conn, col('headfirstname',     $headers, $data));
    $headmiddlename    = esc($conn, col('headmiddlename',    $headers, $data));
    $headlastname      = esc($conn, col('headlastname',      $headers, $data));
    $headsuffix        = esc($conn, col('headsuffix',        $headers, $data));
    $permaddress       = esc($conn, col('permaddress',       $headers, $data));
    $provaddress       = esc($conn, col('provaddress',       $headers, $data));
    $houseowner        = esc($conn, col('houseowner',        $headers, $data));
    $housedetails      = esc($conn, col('housedetails',      $headers, $data));
    $yearsinbarangay   = esc($conn, col('yearsinbarangay',   $headers, $data));
    $voter             = esc($conn, col('voter',             $headers, $data));
    $precinctno        = esc($conn, col('precinctno',        $headers, $data));
    $mobile            = esc($conn, col('mobile',            $headers, $data));
    $landline          = esc($conn, col('landline',          $headers, $data));
    $email             = esc($conn, col('email',             $headers, $data));
    $gender            = esc($conn, col('gender',            $headers, $data));
    $maritalstatus     = esc($conn, col('maritalstatus',     $headers, $data));
    $religion          = esc($conn, col('religion',          $headers, $data));
    $citizenship       = esc($conn, col('citizenship',       $headers, $data));
    $education         = esc($conn, col('education',         $headers, $data));
    $occupation        = esc($conn, col('occupation',        $headers, $data));
    $employer          = esc($conn, col('employer',          $headers, $data));
    $workhours         = esc($conn, col('workhours',         $headers, $data));
    $gradelevel        = esc($conn, col('gradelevel',        $headers, $data));
    $schoolname        = esc($conn, col('schoolname',        $headers, $data));
    $outofschoolyouth  = esc($conn, col('outofschoolyouth',  $headers, $data));
    $employmentstatus  = esc($conn, col('employmentstatus',  $headers, $data));
    $hascar            = esc($conn, col('hascar',            $headers, $data));
    $carbrand          = esc($conn, col('carbrand',          $headers, $data));
    $carmodel          = esc($conn, col('carmodel',          $headers, $data));
    $carcolor          = esc($conn, col('carcolor',          $headers, $data));
    $carplate          = esc($conn, col('carplate',          $headers, $data));
    $hasmotorcycle     = esc($conn, col('hasmotorcycle',     $headers, $data));
    $motorbrand        = esc($conn, col('motorbrand',        $headers, $data));
    $motormodel        = esc($conn, col('motormodel',        $headers, $data));
    $motorcolor        = esc($conn, col('motorcolor',        $headers, $data));
    $motorplate        = esc($conn, col('motorplate',        $headers, $data));
    $issenior          = esc($conn, col('issenior',          $headers, $data));
    $oscaid            = esc($conn, col('oscaid',            $headers, $data));
    $pwdstatus         = esc($conn, col('pwdstatus',         $headers, $data));
    $pwdid             = esc($conn, col('pwdid',             $headers, $data));
    $disabilitytype    = esc($conn, col('disabilitytype',    $headers, $data));
    $soloparentstatus  = esc($conn, col('soloparentstatus',  $headers, $data));
    $soloparentid      = esc($conn, col('soloparentid',      $headers, $data));
    $petsinfo_raw      = col('petsinfo', $headers, $data);

    $has_pets_str = strtolower(col('haspets', $headers, $data));
    $has_pets     = ($has_pets_str === 'yes' || $has_pets_str === '1') ? 1 : 0;

    // Normalize birthdate to YYYY-MM-DD
    $birthdate_raw = col('birthdate', $headers, $data);
    $birthdate_sql = '';
    if (!empty($birthdate_raw)) {
        $ts = strtotime($birthdate_raw);
        if ($ts) {
            $birthdate_sql = date('Y-m-d', $ts);
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthdate_raw, $m)) {
            $birthdate_sql = "$m[3]-$m[2]-$m[1]";
        } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $birthdate_raw, $m)) {
            $birthdate_sql = "$m[3]-$m[2]-$m[1]";
        }
    }
    $birthdate_sql = esc($conn, $birthdate_sql);

    $res_code   = esc($conn, generateResidentCode($conn));
    $created_at = date('Y-m-d H:i:s');

    $sql = "INSERT INTO residents (
        created_at, first_name, middle_name, last_name, suffix,
        head_of_family, relationship, head_first_name, head_middle_name, head_last_name, head_suffix,
        perm_address, prov_address, house_owner, house_details, years_in_barangay,
        voter, precinct_no, mobile, landline, email,
        birthdate, gender, marital_status, religion, citizenship,
        education, occupation, employer, work_hours, grade_level, school_name, out_of_school_youth, employment_status,
        has_car, car_brand, car_model, car_color, car_plate,
        has_motorcycle, motor_brand, motor_model, motor_color, motor_plate,
        is_senior, osca_id, pwd_status, pwd_id, disability_type,
        solo_parent_status, solo_parent_id, has_pets, resident_code
    ) VALUES (
        '$created_at','$firstname','$middlename','$lastname','$suffix',
        '$headoffamily','$relationship','$headfirstname','$headmiddlename','$headlastname','$headsuffix',
        '$permaddress','$provaddress','$houseowner','$housedetails','$yearsinbarangay',
        '$voter','$precinctno','$mobile','$landline','$email',
        '$birthdate_sql','$gender','$maritalstatus','$religion','$citizenship',
        '$education','$occupation','$employer','$workhours','$gradelevel','$schoolname','$outofschoolyouth','$employmentstatus',
        '$hascar','$carbrand','$carmodel','$carcolor','$carplate',
        '$hasmotorcycle','$motorbrand','$motormodel','$motorcolor','$motorplate',
        '$issenior','$oscaid','$pwdstatus','$pwdid','$disabilitytype',
        '$soloparentstatus','$soloparentid',$has_pets,'$res_code'
    )";

    if (!mysqli_query($conn, $sql)) { $failed++; continue; }

    $resident_id = mysqli_insert_id($conn);
    $imported++;

    // Import pets
    if ($has_pets && !empty($petsinfo_raw)) {
        foreach (explode(' || ', $petsinfo_raw) as $pet_str) {
            $pet_data = [];
            foreach (explode(', ', $pet_str) as $part) {
                $kv = explode(': ', $part, 2);
                if (count($kv) === 2) $pet_data[trim($kv[0])] = trim($kv[1]);
            }

            $pname    = esc($conn, $pet_data['Name']       ?? '');
            $page     = esc($conn, $pet_data['Age']        ?? '');
            $psex_raw = ucfirst(strtolower($pet_data['Sex'] ?? ''));
            $psex     = in_array($psex_raw, ['Male', 'Female']) ? $psex_raw : 'Male';
            $pcolor   = esc($conn, $pet_data['Color']      ?? '');
            $ptype_raw = ucfirst(strtolower($pet_data['Type'] ?? 'Dog'));
            $ptype    = in_array($ptype_raw, ['Dog', 'Cat', 'Bird']) ? $ptype_raw : 'Other';
            $ptype    = esc($conn, $ptype);
            $pbreeder = esc($conn, $pet_data['Breeder']    ?? '');
            $pother   = esc($conn, $pet_data['Other Pets'] ?? '');

            if (!empty($pname)) {
                mysqli_query($conn, "INSERT INTO pets (resident_id,pet_name,pet_age,pet_sex,pet_color,pet_type,breeder_status,other_pets)
                    VALUES ('$resident_id','$pname','$page','$psex','$pcolor','$ptype','$pbreeder','$pother')");
            }
        }
    }
}

fclose($handle);

// Remove exact duplicates (same data, keep lowest id)
$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM residents");
$cols = [];
while ($row = mysqli_fetch_assoc($cols_res)) {
    if (!in_array($row['Field'], ['id', 'created_at', 'resident_code'])) $cols[] = $row['Field'];
}
if ($cols) {
    $cond = implode(' AND ', array_map(fn($c) => "t1.`$c` <=> t2.`$c`", $cols));
    $removed = mysqli_query($conn, "DELETE t1 FROM residents t1 INNER JOIN residents t2 WHERE t1.id > t2.id AND $cond");
    $dupes = mysqli_affected_rows($conn) > 0;
} else {
    $dupes = false;
}

if ($imported === 0 && $failed > 0) {
    header('Location: Display_List.php?import=error&reason=allskipped&failed=' . $failed);
    exit();
}

$url = 'Display_List.php?import=success&count=' . $imported . '&failed=' . $failed;
if ($dupes) $url .= '&duplicates_removed=true';
header('Location: ' . $url);
exit();
