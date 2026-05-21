<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'Residents_DB.php';
include 'csrf_helper.php';

function normalize($string) {
    return strtolower(preg_replace('/[^a-z]/', '', $string));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $filename = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($filename, 'r');

        // Normalize header row
        $raw_headers = fgetcsv($handle);
        $headers = [];
        foreach ($raw_headers as $index => $label) {
            $headers[normalize($label)] = $index;
        }

        function val($key, $headers, $data) {
            return isset($headers[$key]) ? trim($data[$headers[$key]]) : '';
        }

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $values = [];
            $keys = [
                'firstname','middlename','lastname','suffix',
                'headoffamily','relationship','headfirstname','headmiddlename','headlastname','headsuffix',
                'permaddress','provaddress','houseowner','housedetails','yearsinbarangay',
                'voter','precinctno','mobile','landline','email','birthdate','gender','maritalstatus',
                'religion','citizenship','education','occupation','employer','workhours',
                'gradelevel','schoolname','outofschoolyouth','employmentstatus','hascar','carbrand',
                'carmodel','carcolor','carplate','hasmotorcycle','motorbrand',
                'motormodel','motorcolor','motorplate','issenior','oscaid','pwdstatus','pwdid',
                'disabilitytype','soloparentstatus','soloparentid','haspets','petsinfo'
            ];

            foreach ($keys as $k) {
                $values[$k] = val($k, $headers, $data);
            }

            $has_pets = strtolower($values['haspets']) === 'yes' ? 1 : 0;
            $created_at = date('Y-m-d H:i:s');

            // Format birthdate to YYYY-MM-DD if in DD/MM/YYYY format
            $birthdate_raw = trim($values['birthdate']);
            $birthdate_sql = null;

            if (!empty($birthdate_raw)) {
                // Try parsing with strtotime (handles many formats including "June 5, 2023")
                $timestamp = strtotime($birthdate_raw);

                if ($timestamp) {
                    $birthdate_sql = date('Y-m-d', $timestamp); // Format to YYYY-MM-DD
                } else {
                    // fallback if strtotime fails — try DD/MM/YYYY manually
                    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthdate_raw, $m)) {
                        // Assume DD/MM/YYYY
                        $birthdate_sql = "$m[3]-$m[2]-$m[1]";
                    } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $birthdate_raw, $m)) {
                        // DD-MM-YYYY
                        $birthdate_sql = "$m[3]-$m[2]-$m[1]";
                    } else {
                        $birthdate_sql = ''; // Invalid format
                    }
                }
            } else {
                $birthdate_sql = '';
            }

            $insert_resident = "INSERT INTO residents (
                created_at, first_name, middle_name, last_name, suffix,
                head_of_family, relationship, head_first_name, head_middle_name, head_last_name, head_suffix,
                perm_address, prov_address, house_owner, house_details, years_in_barangay,
                voter, precinct_no, mobile, landline, email,
                birthdate, gender, marital_status, religion, citizenship,
                education, occupation, employer, work_hours, grade_level, school_name, out_of_school_youth, employment_status,
                has_car, car_brand, car_model, car_color, car_plate,
                has_motorcycle, motor_brand, motor_model, motor_color, motor_plate,
                is_senior, osca_id, pwd_status, pwd_id, disability_type,
                solo_parent_status, solo_parent_id, has_pets
            ) VALUES (
                '$created_at', '{$values['firstname']}', '{$values['middlename']}', '{$values['lastname']}', '{$values['suffix']}',
                '{$values['headoffamily']}', '{$values['relationship']}', '{$values['headfirstname']}', '{$values['headmiddlename']}', '{$values['headlastname']}', '{$values['headsuffix']}',
                '{$values['permaddress']}', '{$values['provaddress']}', '{$values['houseowner']}', '{$values['housedetails']}', '{$values['yearsinbarangay']}',
                '{$values['voter']}', '{$values['precinctno']}', '{$values['mobile']}', '{$values['landline']}', '{$values['email']}',
                '$birthdate_sql', '{$values['gender']}', '{$values['maritalstatus']}', '{$values['religion']}', '{$values['citizenship']}',
                '{$values['education']}', '{$values['occupation']}', '{$values['employer']}', '{$values['workhours']}', '{$values['gradelevel']}', '{$values['schoolname']}', '{$values['outofschoolyouth']}', '{$values['employmentstatus']}',
                '{$values['hascar']}', '{$values['carbrand']}', '{$values['carmodel']}', '{$values['carcolor']}', '{$values['carplate']}',
                '{$values['hasmotorcycle']}', '{$values['motorbrand']}', '{$values['motormodel']}', '{$values['motorcolor']}', '{$values['motorplate']}',
                '{$values['issenior']}', '{$values['oscaid']}', '{$values['pwdstatus']}', '{$values['pwdid']}', '{$values['disabilitytype']}',
                '{$values['soloparentstatus']}', '{$values['soloparentid']}', '$has_pets'
            )";

            mysqli_query($conn, $insert_resident);
            $resident_id = mysqli_insert_id($conn);

            if ($has_pets && !empty($values['petsinfo'])) {
                $pets = explode(" || ", $values['petsinfo']);
                foreach ($pets as $pet) {
                    $parts = explode(", ", $pet);
                    $pet_data = [];
                    foreach ($parts as $part) {
                        $kv = explode(": ", $part, 2);
                        if (count($kv) === 2) {
                            $pet_data[trim($kv[0])] = trim($kv[1]);
                        }
                    }

                    $pet_name = mysqli_real_escape_string($conn, $pet_data['Name'] ?? '');
                    $pet_age = mysqli_real_escape_string($conn, $pet_data['Age'] ?? '');
                    $pet_sex_raw = $pet_data['Sex'] ?? '';
                    $pet_sex = ucfirst(strtolower(trim($pet_sex_raw)));
                    $pet_sex = in_array($pet_sex, ['Male', 'Female']) ? $pet_sex : 'Male';
                    $pet_sex = mysqli_real_escape_string($conn, $pet_sex);
                    $pet_color = mysqli_real_escape_string($conn, $pet_data['Color'] ?? '');
                    $pet_type_raw = $pet_data['Type'] ?? '';
                    $pet_type = ucfirst(strtolower(trim($pet_type_raw)));
                    $pet_type = in_array($pet_type, ['Dog', 'Cat']) ? $pet_type : 'Dog';
                    $pet_type = mysqli_real_escape_string($conn, $pet_type);
                    $breeder_status = mysqli_real_escape_string($conn, $pet_data['Breeder'] ?? '');
                    $other_pets = mysqli_real_escape_string($conn, $pet_data['Other Pets'] ?? '');

                    if (!empty($pet_name) && !empty($pet_sex)) {
                        $insert_pet = "INSERT INTO pets (
                            resident_id, pet_name, pet_age, pet_sex, pet_color, pet_type, breeder_status, other_pets
                        ) VALUES (
                            '$resident_id',
                            '$pet_name',
                            '$pet_age',
                            '$pet_sex',
                            '$pet_color',
                            '$pet_type',
                            '$breeder_status',
                            '$other_pets'
                        )";
                        mysqli_query($conn, $insert_pet);
                    }
                }
            }
        }

        fclose($handle);
        // Remove duplicates after import
        $columns = [];
        $result = mysqli_query($conn, "SHOW COLUMNS FROM residents");
        while ($row = mysqli_fetch_assoc($result)) {
            if (!in_array($row['Field'], ['id', 'created_at'])) {
            $columns[] = $row['Field'];
        }}

        $conditions = [];
        foreach ($columns as $col) {
            $conditions[] = "t1.$col <=> t2.$col";
        }
        $condition_sql = implode(" AND ", $conditions);

        $delete_sql = "
        DELETE t1 FROM residents t1
        INNER JOIN residents t2 
        WHERE t1.id > t2.id AND $condition_sql
        ";
        mysqli_query($conn, $delete_sql);
        $duplicatesRemoved = mysqli_affected_rows($conn) > 0;

        $redirect_url = "display_list.php?import=success";
        if ($duplicatesRemoved) {
            $redirect_url .= "&duplicates_removed=true";
        }
        header("Location: $redirect_url");
        exit();
    }
}
?>
