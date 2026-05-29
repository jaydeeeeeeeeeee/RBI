<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit(); }
include 'Residents_DB.php';
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

$today = date('Y-m-d');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Barangay410_Residents_' . $today . '.csv');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens it correctly without garbled characters
fwrite($out, "\xEF\xBB\xBF");

// Column headers — normalize() in Import_excel.php strips non-letters and lowercases,
// so "First Name" → "firstname", "Has Car" → "hascar", etc.
fputcsv($out, [
    'First Name','Middle Name','Last Name','Suffix',
    'Head Of Family','Relationship',
    'Head First Name','Head Middle Name','Head Last Name','Head Suffix',
    'Perm Address','Prov Address','House Owner','House Details','Years In Barangay',
    'Voter','Precinct No','Mobile','Landline','Email',
    'Birthdate','Gender','Marital Status','Religion','Citizenship',
    'Education','Occupation','Employer','Work Hours',
    'Grade Level','School Name','Out Of School Youth','Employment Status',
    'Has Car','Car Brand','Car Model','Car Color','Car Plate',
    'Has Motorcycle','Motor Brand','Motor Model','Motor Color','Motor Plate',
    'Is Senior','OSCA ID','PWD Status','PWD ID','Disability Type',
    'Solo Parent Status','Solo Parent ID','Has Pets','Pets Info',
]);

$rr = mysqli_query($conn, "SELECT * FROM residents WHERE is_hidden=0 ORDER BY last_name, first_name");
$pr = mysqli_query($conn, "SELECT * FROM pets ORDER BY resident_id, id");
$pb = [];
while ($p = mysqli_fetch_assoc($pr)) $pb[$p['resident_id']][] = $p;

function xval($v) { return $v ?? ''; }

while ($r = mysqli_fetch_assoc($rr)) {
    // Build pets info string in format the importer expects:
    // "Name: Buddy, Age: 3, Sex: Male, Color: Brown, Type: Dog, Breeder: No, Other Pets:  || ..."
    $pi = '';
    if (!empty($pb[$r['id']])) {
        $parts = [];
        foreach ($pb[$r['id']] as $p) {
            $parts[] = 'Name: '       . xval($p['pet_name'])       .
                       ', Age: '      . xval($p['pet_age'])        .
                       ', Sex: '      . xval($p['pet_sex'])        .
                       ', Color: '    . xval($p['pet_color'])      .
                       ', Type: '     . xval($p['pet_type'])       .
                       ', Breeder: '  . xval($p['breeder_status']) .
                       ', Other Pets: ' . xval($p['other_pets']);
        }
        $pi = implode(' || ', $parts);
    }

    fputcsv($out, [
        xval($r['first_name']),
        xval($r['middle_name']),
        xval($r['last_name']),
        xval($r['suffix']),
        xval($r['head_of_family']),
        xval($r['relationship']),
        xval($r['head_first_name']),
        xval($r['head_middle_name']),
        xval($r['head_last_name']),
        xval($r['head_suffix']),
        xval($r['perm_address']),
        xval($r['prov_address']),
        xval($r['house_owner']),
        xval($r['house_details']),
        xval($r['years_in_barangay']),
        xval($r['voter']),
        xval($r['precinct_no']),
        xval($r['mobile']),
        xval($r['landline']),
        xval($r['email']),
        xval($r['birthdate']),
        xval($r['gender']),
        xval($r['marital_status']),
        xval($r['religion']),
        xval($r['citizenship']),
        xval($r['education']),
        xval($r['occupation']),
        xval($r['employer']),
        xval($r['work_hours']),
        xval($r['grade_level']),
        xval($r['school_name']),
        xval($r['out_of_school_youth']),
        xval($r['employment_status']),
        xval($r['has_car']),
        xval($r['car_brand']),
        xval($r['car_model']),
        xval($r['car_color']),
        xval($r['car_plate']),
        xval($r['has_motorcycle']),
        xval($r['motor_brand']),
        xval($r['motor_model']),
        xval($r['motor_color']),
        xval($r['motor_plate']),
        xval($r['is_senior']),
        xval($r['osca_id']),
        xval($r['pwd_status']),
        xval($r['pwd_id']),
        xval($r['disability_type']),
        xval($r['solo_parent_status']),
        xval($r['solo_parent_id']),
        $r['has_pets'] ? 'Yes' : 'No',
        $pi,
    ]);
}

fclose($out);
