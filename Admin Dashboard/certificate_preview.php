<?php
// admin/certificate_preview.php - SIMPLIFIED VERSION
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$request_id = $_GET['id'] ?? 0;

if ($request_id <= 0) {
    die('Invalid request ID');
}

// Get data
$stmt = $pdo->prepare("
    SELECT 
        cr.id as request_id,
        cr.purpose,
        r.first_name,
        r.last_name,
        r.address,
        r.age,
        r.gender
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.id
    WHERE cr.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    die('Request not found');
}

// Variables
$resident_name = $request['first_name'] . ' ' . $request['last_name'];
$address = $request['address'];
$age = $request['age'];
$purpose = $request['purpose'];
$gender = $request['gender'];
$gender_title = ($gender == 'Female') ? 'Ms.' : (($gender == 'Male') ? 'Mr.' : '');
$date_issued = date('Y-m-d');
$valid_until = date('Y-m-d', strtotime('+1 year'));
$barangay_captain = 'MICHAEL JOHN M. REGALA';
$barangay_secretary = 'RUDYLINDA P. DOMINGO';
$document_no = 'FTJS-' . date('Ymd') . '-' . str_pad($request_id, 4, '0', STR_PAD_LEFT);
$years_of_residency = 15;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>First Time Job Seeker Certificate</title>
    <style>
        @media print { .no-print { display: none; } }
        body { font-family: 'Times New Roman', serif; background: #f0f0f0; padding: 50px; }
        .certificate {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            border: 2px solid #0a2447;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .logo-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .logo { width: 80px; height: 80px; object-fit: contain; }
        .header-text { text-align: center; margin-bottom: 30px; }
        .certification-title { font-size: 18px; font-weight: bold; text-align: center; margin: 20px 0 5px; }
        .ra-text { font-size: 14px; font-style: italic; text-align: center; margin-bottom: 30px; }
        .content { font-size: 13px; line-height: 1.6; text-align: justify; }
        .content p { margin-bottom: 15px; }
        .validity-note { font-size: 13px; margin: 20px 0; text-align: justify; }
        .signature-section { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 45%; }
        .signature-line { border-top: 1px solid #000; width: 80%; margin: 30px auto 5px; }
        .signature-name { font-weight: bold; text-decoration: underline; }
        .signature-title { font-size: 12px; margin-top: 5px; }
        .footer { margin-top: 30px; text-align: center; font-size: 11px; color: #333; }
        .document-number { text-align: right; font-size: 10px; margin-bottom: 15px; }
        button { padding: 10px 20px; margin: 10px; cursor: pointer; background: #0a2447; color: white; border: none; border-radius: 5px; }
        button:hover { background: #1e3a8a; }
    </style>
</head>
<body>
<div class="certificate">
    <div class="document-number">
        Document No: <?php echo $document_no; ?>
    </div>
    
    <div class="logo-container">
        <img src="../assets/images/manila-logo.webp" class="logo" onerror="this.style.display='none'">
        <div></div>
        <img src="../assets/images/barangay-logo.webp" class="logo" onerror="this.style.display='none'">
    </div>
    
    <div class="header-text">
        <div style="font-size: 12px;">Republic of the Philippines</div>
        <div style="font-size: 14px; font-weight: bold;">Barangay 410 Zone 42</div>
        <div style="font-size: 11px;">District IV, Sampaloc, Manila</div>
    </div>
    
    <div class="certification-title">
        BARANGAY CERTIFICATION
    </div>
    <div class="ra-text">
        First Time Jobseekers Assistance Act. (R.A. 11261)
    </div>
    
    <div class="content">
        <p>This is to certify that <strong><?php echo $gender_title; ?> <?php echo htmlspecialchars($resident_name); ?></strong>, 
        <strong><?php echo $age; ?> years old</strong>, is a resident of Barangay 410 Zone 42 District IV, 
        Manila with postal address at <strong><?php echo htmlspecialchars($address); ?></strong> for 
        <strong><?php echo $years_of_residency; ?> years</strong> is qualified to avail of R.A. 11261 or the 
        First Time Jobseekers Assistance Act of 2019.</p>
        
        <p>I further certify that the holder bearer was informed of her/his rights. Including the duties 
        and responsibilities accorded by R.A. 11261 through the oath of undertaking she has assigned 
        and executed in the presence of Barangay Officials.</p>
        
        <p>Signed this <strong><?php echo date('jS', strtotime($date_issued)); ?></strong> day of 
        <strong><?php echo date('F', strtotime($date_issued)); ?></strong> 
        <strong><?php echo date('Y', strtotime($date_issued)); ?></strong> in the City / Municipality of Manila.</p>
    </div>
    
    <div class="validity-note">
        This certification is valid only until the <strong><?php echo date('jS', strtotime($valid_until)); ?></strong> 
        day of <strong><?php echo date('F', strtotime($valid_until)); ?></strong> 
        <strong><?php echo date('Y', strtotime($valid_until)); ?></strong>, one year from the issuance.
    </div>
    
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name"><?php echo $barangay_secretary; ?></div>
            <div class="signature-title">Brgy. Secretary</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name"><?php echo $barangay_captain; ?></div>
            <div class="signature-title">Punong Barangay</div>
        </div>
    </div>
    
    <div class="footer">
        Email Add.: barangay410zone42@gmail.com
    </div>
</div>

<div class="no-print" style="text-align: center; margin-top: 20px;">
    <button onclick="window.print()">🖨️ Print Certificate</button>
    <button onclick="history.back()">← Back to Requests</button>
</div>
</body>
</html>