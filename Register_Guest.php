<?php
// Guest registration - no admin session required
if (session_status() === PHP_SESSION_NONE) session_start();
include 'Residents_DB.php';
include 'generate_id.php';
include 'csrf_helper.php';

if($_SERVER["REQUEST_METHOD"]=="POST"){
  csrf_verify();
  $has_pets=isset($_POST['has_pets'])&&$_POST['has_pets']==='Yes'?'Yes':'No';
  $years_in_barangay=empty($_POST['years_in_barangay'])?0:(int)$_POST['years_in_barangay'];
  $pwd_status=(isset($_POST['pwd_status'])&&$_POST['pwd_status']==='Yes')?'Yes':'No';
  $solo_parent_status=(isset($_POST['solo_parent_status'])&&$_POST['solo_parent_status']==='Yes')?'Yes':'No';
  $out_of_school_youth=isset($_POST['out_of_school_youth'])?$_POST['out_of_school_youth']:'';
  if($_POST['head_of_family']==='No'){$head_first_name=$_POST['head_first_name'];$head_middle_name=$_POST['head_middle_name'];$head_last_name=$_POST['head_last_name'];$head_suffix=$_POST['head_suffix'];}
  else{$head_first_name=$_POST['first_name'];$head_middle_name=$_POST['middle_name'];$head_last_name=$_POST['last_name'];$head_suffix=$_POST['suffix'];}
  if(isset($_POST['gender'])&&$_POST['gender']==='Other'&&!empty($_POST['other_gender']))$_POST['gender']=$_POST['other_gender'];
  if(isset($_POST['religion'])&&$_POST['religion']==='Other'&&!empty($_POST['other_religion']))$_POST['religion']=$_POST['other_religion'];
  if(isset($_POST['citizenship'])&&in_array($_POST['citizenship'],['Other','Dual Citizenship'])&&!empty($_POST['other_citizenship']))$_POST['citizenship']=$_POST['other_citizenship'];
  $stmt=$conn->prepare("INSERT INTO residents (first_name,middle_name,last_name,suffix,head_of_family,relationship,head_first_name,head_middle_name,head_last_name,head_suffix,perm_address,prov_address,house_owner,house_details,years_in_barangay,voter,precinct_no,mobile,landline,email,birthdate,gender,marital_status,religion,citizenship,education,employment_status,occupation,employer,work_hours,grade_level,school_name,out_of_school_youth,has_car,car_brand,car_model,car_color,car_plate,has_motorcycle,motor_brand,motor_model,motor_color,motor_plate,is_senior,osca_id,pwd_status,pwd_id,disability_type,solo_parent_status,solo_parent_id,has_pets) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssssssss",$_POST['first_name'],$_POST['middle_name'],$_POST['last_name'],$_POST['suffix'],$_POST['head_of_family'],$_POST['relationship'],$head_first_name,$head_middle_name,$head_last_name,$head_suffix,$_POST['perm_address'],$_POST['prov_address'],$_POST['house_owner'],$_POST['house_details'],$years_in_barangay,$_POST['voter'],$_POST['precinct_no'],$_POST['mobile'],$_POST['landline'],$_POST['email'],$_POST['birthdate'],$_POST['gender'],$_POST['marital_status'],$_POST['religion'],$_POST['citizenship'],$_POST['education'],$_POST['employment_status'],$_POST['occupation'],$_POST['employer'],$_POST['work_hours'],$_POST['grade_level'],$_POST['school_name'],$out_of_school_youth,$_POST['has_car'],$_POST['car_brand'],$_POST['car_model'],$_POST['car_color'],$_POST['car_plate'],$_POST['has_motorcycle'],$_POST['motor_brand'],$_POST['motor_model'],$_POST['motor_color'],$_POST['motor_plate'],$_POST['is_senior'],$_POST['osca_id'],$pwd_status,$_POST['pwd_id'],$_POST['disability_type'],$solo_parent_status,$_POST['solo_parent_id'],$has_pets);
  if($stmt->execute()){
    $resident_id=$stmt->insert_id;
    if($_POST['has_pets']==='Yes'&&isset($_POST['pet_name'])){
      for($i=0;$i<count($_POST['pet_name']);$i++){
        $bs=(isset($_POST['breeder_status'][$i])&&$_POST['breeder_status'][$i]==='Yes')?'Yes':'No';
        $sp=$conn->prepare("INSERT INTO pets (resident_id,pet_name,pet_age,pet_sex,pet_color,pet_type,breeder_status,other_pets) VALUES (?,?,?,?,?,?,?,?)");
        $sp->bind_param("isssssss",$resident_id,$_POST['pet_name'][$i],$_POST['pet_age'][$i],$_POST['pet_sex'][$i],$_POST['pet_color'][$i],$_POST['pet_type'][$i],$bs,$_POST['other_pets'][$i]);
        $sp->execute();
      }
    }
    $success=true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Guest Registration – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css"/>
<style>
.guest-header{background:#0f172a;padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between}
.guest-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.guest-logo-box{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#3b82f6,#14b8a6);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:800;color:#fff}
.guest-logo-name{font-size:14px;font-weight:600;color:#fff}
main{padding:2rem 1.5rem;max-width:860px;margin:0 auto}
.reg-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}
.reg-head{background:linear-gradient(135deg,#0f172a,#1e40af);padding:1.5rem 2rem}
.reg-head h1{font-family:'Syne',sans-serif;font-size:1.15rem;font-weight:800;color:#fff}
.reg-head p{font-size:12px;color:rgba(255,255,255,.55);margin-top:3px}
.reg-body{padding:2rem}
.step-title{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid #3b82f6;display:inline-block}
.steps{display:flex;padding:1.25rem 2rem;border-bottom:1px solid #f1f5f9;gap:0;overflow-x:auto}
.step{display:flex;align-items:center;gap:8px;flex-shrink:0}
.step-num{width:28px;height:28px;border-radius:50%;border:2px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#94a3b8}
.step-label{font-size:12px;font-weight:500;color:#94a3b8}
.step.active .step-num{background:#3b82f6;border-color:#3b82f6;color:#fff}
.step.active .step-label{color:#0f172a}
.step.done .step-num{background:#22c55e;border-color:#22c55e;color:#fff}
.step-line{flex:1;height:2px;background:#e2e8f0;margin:0 6px;min-width:20px}
.step-line.done{background:#22c55e}
.form-step{display:none}.form-step.active{display:block}
.nav-btns{display:flex;justify-content:space-between;margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid #f1f5f9}
.nav-btns.right{justify-content:flex-end}
.pet-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1.25rem;margin-bottom:1rem;position:relative}
.success-card{text-align:center;padding:3rem 2rem}
.success-icon{width:72px;height:72px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:28px;color:#22c55e}
.add-pet-btn{width:100%;padding:10px;background:#f8fafc;border:2px dashed #e2e8f0;border-radius:10px;color:#64748b;font-family:'Inter',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;margin-top:.5rem}
.add-pet-btn:hover{border-color:#3b82f6;color:#3b82f6;background:#eff6ff}
.progress-bar{height:3px;background:#e2e8f0}.progress-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#14b8a6);transition:width .4s}
footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1rem 2rem;margin-top:2rem}
</style>
</head>
<body style="background:#f8fafc">
<header class="guest-header">
  <a href="admin.php" class="guest-logo">
    <div class="guest-logo-box">410</div>
    <span class="guest-logo-name">ProjectRBI – Barangay 410</span>
  </a>
  <a href="admin.php" class="btn btn-outline" style="font-size:13px;color:#fff;border-color:rgba(255,255,255,.2)"><i class="fas fa-sign-in-alt"></i> Admin Login</a>
</header>

<main>
<?php if(isset($success)&&$success):?>
<div class="reg-card"><div class="reg-body"><div class="success-card">
  <div class="success-icon"><i class="fas fa-check"></i></div>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;color:#0f172a;margin-bottom:8px">Registration Submitted!</h2>
  <p style="font-size:14px;color:#64748b;margin-bottom:1.5rem">Your information has been successfully submitted to Barangay 410. The admin will review your registration shortly.</p>
  <a href="Register_Guest.php" class="btn btn-primary"><i class="fas fa-plus"></i> Register Another</a>
</div></div></div>
<?php else:?>
<div class="reg-card">
  <div class="reg-head">
    <h1><i class="fas fa-user-clock" style="margin-right:8px;opacity:.8"></i>Guest Registration Form</h1>
    <p>Barangay 410 · Manila City · Please fill in your complete information</p>
  </div>
  <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
  <div class="steps">
    <div class="step active"><div class="step-num">1</div><div class="step-label">Personal</div></div>
    <div class="step-line" id="line0"></div>
    <div class="step"><div class="step-num">2</div><div class="step-label">Address</div></div>
    <div class="step-line" id="line1"></div>
    <div class="step"><div class="step-num">3</div><div class="step-label">Education</div></div>
    <div class="step-line" id="line2"></div>
    <div class="step"><div class="step-num">4</div><div class="step-label">Vehicles</div></div>
    <div class="step-line" id="line3"></div>
    <div class="step"><div class="step-num">5</div><div class="step-label">Special</div></div>
    <div class="step-line" id="line4"></div>
    <div class="step"><div class="step-num">6</div><div class="step-label">Pets</div></div>
  </div>
  <div class="reg-body">
  <form method="POST" action="Register_Guest.php">
  <?= csrf_field() ?>
  <!-- Same form fields as Register.php – reusing same structure -->
  <div class="form-step active">
    <div class="step-title"><i class="fas fa-user" style="color:#3b82f6;margin-right:6px"></i>Step 1: Personal Information</div>
    <div class="form-row-4">
      <div class="form-group"><label>First Name <span class="required">*</span></label><input type="text" name="first_name" class="form-control capitalize" required></div>
      <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" class="form-control capitalize"></div>
      <div class="form-group"><label>Last Name <span class="required">*</span></label><input type="text" name="last_name" class="form-control capitalize" required></div>
      <div class="form-group"><label>Suffix</label><input type="text" name="suffix" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Head of Family? <span class="required">*</span></label>
        <select name="head_of_family" id="headSelect" class="form-control" required>
          <option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option>
        </select>
      </div>
      <div class="form-group"><label>Relationship to Head</label><input type="text" name="relationship" class="form-control"></div>
    </div>
    <div id="headNameSection" style="display:none">
      <div class="form-section-title"><i class="fas fa-house-user" style="color:#3b82f6"></i> Head of Family Name</div>
      <div class="form-row-4">
        <div class="form-group"><label>First Name</label><input type="text" name="head_first_name" class="form-control capitalize"></div>
        <div class="form-group"><label>Middle Name</label><input type="text" name="head_middle_name" class="form-control capitalize"></div>
        <div class="form-group"><label>Last Name</label><input type="text" name="head_last_name" class="form-control capitalize"></div>
        <div class="form-group"><label>Suffix</label><input type="text" name="head_suffix" class="form-control"></div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Birthdate <span class="required">*</span></label><input type="date" name="birthdate" id="birthdate" class="form-control" required></div>
      <div class="form-group"><label>Gender <span class="required">*</span></label>
        <select name="gender" id="genderSel" class="form-control" onchange="toggleOther('genderSel','otherGender')" required>
          <option value="">-- Select --</option><option>Male</option><option>Female</option><option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group" id="otherGender" style="display:none"><label>Specify</label><input type="text" name="other_gender" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Marital Status <span class="required">*</span></label>
        <select name="marital_status" class="form-control" required>
          <option value="">-- Select --</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced</option><option>Annulled</option>
        </select>
      </div>
      <div class="form-group"><label>Religion <span class="required">*</span></label>
        <select name="religion" id="religionSel" class="form-control" onchange="toggleOther('religionSel','otherReligion')" required>
          <option value="">-- Select --</option><option>None</option><option>Roman Catholic</option><option>Christian</option><option>Iglesia ni Cristo</option><option>Muslim</option><option>Buddhism</option><option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group" id="otherReligion" style="display:none"><label>Specify</label><input type="text" name="other_religion" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Citizenship <span class="required">*</span></label>
        <select name="citizenship" id="citizenSel" class="form-control" onchange="toggleCitizen()" required>
          <option value="">-- Select --</option><option value="Filipino">Filipino</option><option value="Dual Citizenship">Dual Citizenship</option><option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group" id="otherCitizen" style="display:none"><label>Specify</label><input type="text" name="other_citizenship" class="form-control"></div>
    </div>
    <div class="nav-btns right"><button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-chevron-right"></i></button></div>
  </div>
  <div class="form-step">
    <div class="step-title"><i class="fas fa-map-marker-alt" style="color:#3b82f6;margin-right:6px"></i>Step 2: Address & Contact</div>
    <div class="form-row">
      <div class="form-group"><label>Permanent Address <span class="required">*</span></label><input type="text" name="perm_address" class="form-control" required></div>
      <div class="form-group"><label>Provincial Address</label><input type="text" name="prov_address" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>House Owner? <span class="required">*</span></label>
        <select name="house_owner" id="houseOwner" class="form-control" onchange="toggleHouseDetail()" required>
          <option value="">-- Select --</option><option>Yes</option><option>No</option>
        </select>
      </div>
      <div class="form-group" id="houseDetail" style="display:none"><label>House Details</label><input type="text" name="house_details" class="form-control"></div>
      <div class="form-group"><label>Years in Barangay</label><input type="number" name="years_in_barangay" class="form-control" min="0"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Voter? <span class="required">*</span></label>
        <select name="voter" id="voterSel" class="form-control" onchange="togglePrecinct()" required>
          <option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option>
        </select>
      </div>
      <div class="form-group" id="precinctGroup" style="display:none"><label>Precinct No.</label><input type="text" name="precinct_no" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Mobile</label><input type="text" name="mobile" class="form-control" maxlength="11"></div>
      <div class="form-group"><label>Landline</label><input type="text" name="landline" class="form-control" maxlength="7"></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
    </div>
    <div class="nav-btns">
      <button type="button" class="btn btn-outline" onclick="prevStep()"><i class="fas fa-chevron-left"></i> Back</button>
      <button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-chevron-right"></i></button>
    </div>
  </div>
  <div class="form-step">
    <div class="step-title"><i class="fas fa-graduation-cap" style="color:#3b82f6;margin-right:6px"></i>Step 3: Education & Employment</div>
    <div class="form-row">
      <div class="form-group"><label>Highest Educational Attainment</label>
        <select name="education" class="form-control"><option value="">-- Select --</option><option>No Formal Education</option><option>Elementary Graduate</option><option>High School Graduate</option><option>Senior High School Graduate</option><option>Vocational/Technical</option><option>College Undergraduate</option><option>College Graduate</option><option>Post Graduate</option></select>
      </div>
      <div class="form-group"><label>Out of School Youth?</label>
        <select name="out_of_school_youth" id="osySel" class="form-control" onchange="toggleOSY()"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select>
      </div>
    </div>
    <div id="schoolSection" style="display:none">
      <div class="form-row">
        <div class="form-group"><label>Grade Level</label><input type="text" name="grade_level" class="form-control"></div>
        <div class="form-group"><label>School Name</label><input type="text" name="school_name" class="form-control"></div>
      </div>
    </div>
    <div class="form-row" style="margin-top:1rem">
      <div class="form-group"><label>Employment Status</label>
        <select name="employment_status" id="empSel" class="form-control" onchange="toggleEmployment()"><option value="">-- Select --</option><option>Employed</option><option>Unemployed</option><option>Self-Employed</option><option>Student</option><option>Retired</option></select>
      </div>
    </div>
    <div id="empDetails" style="display:none">
      <div class="form-row">
        <div class="form-group"><label>Occupation</label><input type="text" name="occupation" class="form-control"></div>
        <div class="form-group"><label>Employer</label><input type="text" name="employer" class="form-control"></div>
        <div class="form-group"><label>Work Hours</label><input type="text" name="work_hours" class="form-control"></div>
      </div>
    </div>
    <div class="nav-btns">
      <button type="button" class="btn btn-outline" onclick="prevStep()"><i class="fas fa-chevron-left"></i> Back</button>
      <button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-chevron-right"></i></button>
    </div>
  </div>
  <div class="form-step">
    <div class="step-title"><i class="fas fa-car" style="color:#3b82f6;margin-right:6px"></i>Step 4: Vehicles</div>
    <div class="form-row">
      <div class="form-group"><label>Owns a Car?</label><select name="has_car" id="carSel" class="form-control" onchange="toggleVehicle('carSel','carDetails')"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
    </div>
    <div id="carDetails" style="display:none"><div class="form-row-4">
      <div class="form-group"><label>Brand</label><input type="text" name="car_brand" class="form-control"></div>
      <div class="form-group"><label>Model</label><input type="text" name="car_model" class="form-control"></div>
      <div class="form-group"><label>Color</label><input type="text" name="car_color" class="form-control"></div>
      <div class="form-group"><label>Plate No.</label><input type="text" name="car_plate" class="form-control"></div>
    </div></div>
    <div class="form-row" style="margin-top:1rem">
      <div class="form-group"><label>Owns a Motorcycle?</label><select name="has_motorcycle" id="motoSel" class="form-control" onchange="toggleVehicle('motoSel','motoDetails')"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
    </div>
    <div id="motoDetails" style="display:none"><div class="form-row-4">
      <div class="form-group"><label>Brand</label><input type="text" name="motor_brand" class="form-control"></div>
      <div class="form-group"><label>Model</label><input type="text" name="motor_model" class="form-control"></div>
      <div class="form-group"><label>Color</label><input type="text" name="motor_color" class="form-control"></div>
      <div class="form-group"><label>Plate No.</label><input type="text" name="motor_plate" class="form-control"></div>
    </div></div>
    <div class="nav-btns">
      <button type="button" class="btn btn-outline" onclick="prevStep()"><i class="fas fa-chevron-left"></i> Back</button>
      <button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-chevron-right"></i></button>
    </div>
  </div>
  <div class="form-step">
    <div class="step-title"><i class="fas fa-star" style="color:#3b82f6;margin-right:6px"></i>Step 5: Special Categories</div>
    <div class="form-row">
      <div class="form-group"><label>Senior Citizen?</label><select name="is_senior" id="seniorSel" class="form-control" onchange="toggleDetail('seniorSel','seniorDetail')"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
      <div class="form-group" id="seniorDetail" style="display:none"><label>OSCA ID</label><input type="text" name="osca_id" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>PWD?</label><select name="pwd_status" id="pwdSel" class="form-control" onchange="togglePWD()"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
      <div class="form-group" id="pwdDetail" style="display:none"><label>PWD ID</label><input type="text" name="pwd_id" class="form-control"></div>
      <div class="form-group" id="pwdDisability" style="display:none"><label>Disability Type</label><input type="text" name="disability_type" class="form-control"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Solo Parent?</label><select name="solo_parent_status" id="soloSel" class="form-control" onchange="toggleDetail('soloSel','soloDetail')"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
      <div class="form-group" id="soloDetail" style="display:none"><label>Solo Parent ID</label><input type="text" name="solo_parent_id" class="form-control"></div>
    </div>
    <div class="nav-btns">
      <button type="button" class="btn btn-outline" onclick="prevStep()"><i class="fas fa-chevron-left"></i> Back</button>
      <button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-chevron-right"></i></button>
    </div>
  </div>
  <div class="form-step">
    <div class="step-title"><i class="fas fa-paw" style="color:#3b82f6;margin-right:6px"></i>Step 6: Pets</div>
    <div class="form-row" style="margin-bottom:1rem">
      <div class="form-group"><label>Has Pets?</label><select name="has_pets" id="hasPets" class="form-control" onchange="togglePets()"><option value="No">No</option><option value="Yes">Yes</option></select></div>
    </div>
    <div id="petsSection" style="display:none">
      <div id="petsList"></div>
      <button type="button" class="add-pet-btn" onclick="addPet()"><i class="fas fa-plus"></i> Add Pet</button>
    </div>
    <div class="nav-btns">
      <button type="button" class="btn btn-outline" onclick="prevStep()"><i class="fas fa-chevron-left"></i> Back</button>
      <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Submit Registration</button>
    </div>
  </div>
  </form>
  </div>
</div>
<?php endif;?>
</main>
<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City</footer>
<script>
let cs=0;const ts=6;
function updateUI(){
  document.querySelectorAll('.form-step').forEach((s,i)=>s.classList.toggle('active',i===cs));
  document.querySelectorAll('.step').forEach((s,i)=>{s.classList.remove('active','done');if(i===cs)s.classList.add('active');else if(i<cs)s.classList.add('done');});
  document.querySelectorAll('.step-line').forEach((l,i)=>l.classList.toggle('done',i<cs));
  document.getElementById('progressFill').style.width=((cs+1)/ts*100)+'%';
}
function nextStep(){
  const inputs=document.querySelectorAll('.form-step.active [required]');let valid=true;
  inputs.forEach(inp=>{inp.style.borderColor='';if(!inp.value.trim()){inp.style.borderColor='#f43f5e';valid=false;}});
  if(!valid){alert('Please fill in all required fields.');return;}
  if(cs<ts-1){cs++;updateUI();window.scrollTo(0,0);}
}
function prevStep(){if(cs>0){cs--;updateUI();window.scrollTo(0,0);}}
document.getElementById('headSelect').addEventListener('change',function(){document.getElementById('headNameSection').style.display=this.value==='No'?'block':'none';});
function toggleOther(s,c){document.getElementById(c).style.display=document.getElementById(s).value==='Other'?'flex':'none';}
function toggleCitizen(){const v=document.getElementById('citizenSel').value;document.getElementById('otherCitizen').style.display=(v==='Other'||v==='Dual Citizenship')?'flex':'none';}
function toggleHouseDetail(){document.getElementById('houseDetail').style.display=document.getElementById('houseOwner').value==='No'?'flex':'none';}
function togglePrecinct(){document.getElementById('precinctGroup').style.display=document.getElementById('voterSel').value==='Yes'?'flex':'none';}
function toggleOSY(){document.getElementById('schoolSection').style.display=document.getElementById('osySel').value==='No'?'block':'none';}
function toggleEmployment(){const v=document.getElementById('empSel').value;document.getElementById('empDetails').style.display=(v==='Employed'||v==='Self-Employed')?'block':'none';}
function toggleVehicle(s,d){document.getElementById(d).style.display=document.getElementById(s).value==='Yes'?'block':'none';}
function toggleDetail(s,d){document.getElementById(d).style.display=document.getElementById(s).value==='Yes'?'flex':'none';}
function togglePWD(){const v=document.getElementById('pwdSel').value==='Yes';document.getElementById('pwdDetail').style.display=v?'flex':'none';document.getElementById('pwdDisability').style.display=v?'flex':'none';}
function togglePets(){document.getElementById('petsSection').style.display=document.getElementById('hasPets').value==='Yes'?'block':'none';if(document.getElementById('hasPets').value==='Yes'&&document.getElementById('petsList').children.length===0)addPet();}
let pc=0;
function addPet(){pc++;const d=document.createElement('div');d.className='pet-card';d.id='p'+pc;
d.innerHTML=`<div style="font-size:13px;font-weight:600;color:#475569;margin-bottom:10px"><i class="fas fa-paw" style="color:#3b82f6;margin-right:6px"></i>Pet #${pc}</div>
<button type="button" style="position:absolute;top:12px;right:12px;background:#fff1f2;color:#f43f5e;border:1px solid #fecdd3;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;font-family:inherit" onclick="document.getElementById('p${pc}').remove()">Remove</button>
<div class="form-row-4">
<div class="form-group"><label>Name</label><input type="text" name="pet_name[]" class="form-control"></div>
<div class="form-group"><label>Age</label><input type="text" name="pet_age[]" class="form-control"></div>
<div class="form-group"><label>Sex</label><select name="pet_sex[]" class="form-control"><option>Male</option><option>Female</option></select></div>
<div class="form-group"><label>Color</label><input type="text" name="pet_color[]" class="form-control"></div>
</div>
<div class="form-row">
<div class="form-group"><label>Type</label><select name="pet_type[]" class="form-control"><option>Dog</option><option>Cat</option><option>Bird</option><option>Other</option></select></div>
<div class="form-group"><label>Breeder?</label><select name="breeder_status[]" class="form-control"><option value="No">No</option><option value="Yes">Yes</option></select></div>
<div class="form-group"><label>Notes</label><input type="text" name="other_pets[]" class="form-control"></div>
</div>`;
document.getElementById('petsList').appendChild(d);}
document.querySelectorAll('.capitalize').forEach(el=>el.addEventListener('input',function(){const p=this.selectionStart;this.value=this.value.replace(/\b\w/g,c=>c.toUpperCase());this.setSelectionRange(p,p);}));
document.getElementById('birthdate').setAttribute('max',new Date().toISOString().split('T')[0]);
</script>
</body>
</html>
