<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit(); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Residents_Import_Template.csv');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

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

// One sample row so users see the expected format
fputcsv($out, [
    'Juan','Santos','Dela Cruz','Jr.',
    'Yes','',
    '','','','',
    '123 Rizal St Barangay 410 Manila','','Yes','Concrete','10',
    'Yes','123-4A','09171234567','','juan@email.com',
    '1990-05-15','Male','Married','Catholic','Filipino',
    'College Graduate','Driver','City Transport Dept','8',
    '','','No','Employed',
    'No','','','','',
    'No','','','','',
    'No','','No','','',
    'No','','No','',
]);

fclose($out);
