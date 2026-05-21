<?php
include 'Residents_DB.php';
$today=date('Y-m-d');
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Barangay410_Residents_$today.xls");
header("Pragma: no-cache");header("Expires: 0");
$rr=mysqli_query($conn,"SELECT * FROM residents WHERE is_hidden=0 ORDER BY last_name");
$pr=mysqli_query($conn,"SELECT * FROM pets");
$pb=[];while($p=mysqli_fetch_assoc($pr))$pb[$p['resident_id']][]=$p;
function safe($v){return trim($v)!==''?htmlspecialchars($v):'N/A';}
function age($b){if(!$b)return'N/A';return(new DateTime())->diff(new DateTime($b))->y;}
$ex=date('F j, Y \a\t g:i A');
echo "<table border='1'>";
echo "<tr><td colspan='54'><strong>Barangay 410 – Residents Export</strong> | Generated: $ex</td></tr>";
echo "<tr><th>created_at</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Suffix</th><th>Head of Family</th><th>Relationship</th><th>Head First</th><th>Head Middle</th><th>Head Last</th><th>Head Suffix</th><th>Perm Address</th><th>Prov Address</th><th>House Owner</th><th>House Details</th><th>Years in Barangay</th><th>Voter</th><th>Precinct No</th><th>Mobile</th><th>Landline</th><th>Email</th><th>Birthdate</th><th>Age</th><th>Gender</th><th>Marital Status</th><th>Religion</th><th>Citizenship</th><th>Education</th><th>Occupation</th><th>Employer</th><th>Work Hours</th><th>Grade Level</th><th>School</th><th>Out of School Youth</th><th>Employment</th><th>Has Car</th><th>Car Brand</th><th>Car Model</th><th>Car Color</th><th>Car Plate</th><th>Has Motorcycle</th><th>Motor Brand</th><th>Motor Model</th><th>Motor Color</th><th>Motor Plate</th><th>Senior</th><th>OSCA ID</th><th>PWD</th><th>PWD ID</th><th>Disability</th><th>Solo Parent</th><th>Solo Parent ID</th><th>Has Pets</th><th>Pets Info</th></tr>";
while($r=mysqli_fetch_assoc($rr)){
  $pi='N/A';
  if(!empty($pb[$r['id']])){$pi='';foreach($pb[$r['id']] as $p)$pi.="Name:".safe($p['pet_name']).",Age:".safe($p['pet_age']).",Sex:".safe($p['pet_sex']).",Type:".safe($p['pet_type'])." || ";$pi=rtrim($pi,' || ');}
  echo "<tr><td>".safe($r['created_at'])."</td><td>".safe($r['first_name'])."</td><td>".safe($r['middle_name'])."</td><td>".safe($r['last_name'])."</td><td>".safe($r['suffix'])."</td><td>".safe($r['head_of_family'])."</td><td>".safe($r['relationship'])."</td><td>".safe($r['head_first_name'])."</td><td>".safe($r['head_middle_name'])."</td><td>".safe($r['head_last_name'])."</td><td>".safe($r['head_suffix'])."</td><td>".safe($r['perm_address'])."</td><td>".safe($r['prov_address'])."</td><td>".safe($r['house_owner'])."</td><td>".safe($r['house_details'])."</td><td>".safe($r['years_in_barangay'])."</td><td>".safe($r['voter'])."</td><td>".safe($r['precinct_no'])."</td><td>".safe($r['mobile'])."</td><td>".safe($r['landline'])."</td><td>".safe($r['email'])."</td><td>".safe($r['birthdate'])."</td><td>".age($r['birthdate'])."</td><td>".safe($r['gender'])."</td><td>".safe($r['marital_status'])."</td><td>".safe($r['religion'])."</td><td>".safe($r['citizenship'])."</td><td>".safe($r['education'])."</td><td>".safe($r['occupation'])."</td><td>".safe($r['employer'])."</td><td>".safe($r['work_hours'])."</td><td>".safe($r['grade_level'])."</td><td>".safe($r['school_name'])."</td><td>".safe($r['out_of_school_youth'])."</td><td>".safe($r['employment_status'])."</td><td>".safe($r['has_car'])."</td><td>".safe($r['car_brand'])."</td><td>".safe($r['car_model'])."</td><td>".safe($r['car_color'])."</td><td>".safe($r['car_plate'])."</td><td>".safe($r['has_motorcycle'])."</td><td>".safe($r['motor_brand'])."</td><td>".safe($r['motor_model'])."</td><td>".safe($r['motor_color'])."</td><td>".safe($r['motor_plate'])."</td><td>".safe($r['is_senior'])."</td><td>".safe($r['osca_id'])."</td><td>".safe($r['pwd_status'])."</td><td>".safe($r['pwd_id'])."</td><td>".safe($r['disability_type'])."</td><td>".safe($r['solo_parent_status'])."</td><td>".safe($r['solo_parent_id'])."</td><td>".($r['has_pets']?'Yes':'No')."</td><td>".safe($pi)."</td></tr>";
}
echo "</table>";
