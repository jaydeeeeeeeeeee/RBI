<?php
error_reporting(0);
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';
date_default_timezone_set('Asia/Manila');
ob_end_clean();

header('Content-Type: application/json');

$u = $_SESSION['eb_user'] ?? null;
if (!$u || !in_array($u['role'], ['chairperson','secretary'])) {
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit();
}

$savedBy = $u['full_name'];
$docType = trim($_POST['doc_type'] ?? '');
$case_id = trim($_POST['case_id']  ?? '');

if (!$case_id || !in_array($docType, ['summons','notice','mediation'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing case_id or doc_type']);
    exit();
}

$ok = false;

if ($docType === 'summons') {
    $stmt = $conn->prepare("
        INSERT INTO case_summons
            (case_id, to_name, hearing_day, hearing_mo, hearing_yr, hearing_time,
             this_day, this_mo, this_yr,
             or_respondent, or_day, or_mo, or_yr,
             or_opt1, or_opt2, or_opt3, or_name3, or_opt4, or_name4, saved_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            to_name=VALUES(to_name),
            hearing_day=VALUES(hearing_day), hearing_mo=VALUES(hearing_mo),
            hearing_yr=VALUES(hearing_yr), hearing_time=VALUES(hearing_time),
            this_day=VALUES(this_day), this_mo=VALUES(this_mo), this_yr=VALUES(this_yr),
            or_respondent=VALUES(or_respondent),
            or_day=VALUES(or_day), or_mo=VALUES(or_mo), or_yr=VALUES(or_yr),
            or_opt1=VALUES(or_opt1), or_opt2=VALUES(or_opt2),
            or_opt3=VALUES(or_opt3), or_name3=VALUES(or_name3),
            or_opt4=VALUES(or_opt4), or_name4=VALUES(or_name4),
            saved_by=VALUES(saved_by), updated_at=NOW()
    ");
    $p1=$_POST['to_name']??''; $p2=$_POST['hearing_day']??''; $p3=$_POST['hearing_mo']??'';
    $p4=$_POST['hearing_yr']??''; $p5=$_POST['hearing_time']??''; $p6=$_POST['this_day']??'';
    $p7=$_POST['this_mo']??''; $p8=$_POST['this_yr']??''; $p9=$_POST['or_respondent']??'';
    $p10=$_POST['or_day']??''; $p11=$_POST['or_mo']??''; $p12=$_POST['or_yr']??'';
    $p13=$_POST['or_opt1']??''; $p14=$_POST['or_opt2']??''; $p15=$_POST['or_opt3']??'';
    $p16=$_POST['or_name3']??''; $p17=$_POST['or_opt4']??''; $p18=$_POST['or_name4']??'';
    $stmt->bind_param('ssssssssssssssssssss',
        $case_id,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,
        $p9,$p10,$p11,$p12,$p13,$p14,$p15,$p16,$p17,$p18,$savedBy);
    $ok = $stmt->execute();

} elseif ($docType === 'notice') {
    $stmt = $conn->prepare("
        INSERT INTO case_notice
            (case_id, hear_day, hear_mo, hear_yr, hear_time,
             notif_day, notif_mo, notif_yr, saved_by)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            hear_day=VALUES(hear_day), hear_mo=VALUES(hear_mo),
            hear_yr=VALUES(hear_yr), hear_time=VALUES(hear_time),
            notif_day=VALUES(notif_day), notif_mo=VALUES(notif_mo),
            notif_yr=VALUES(notif_yr),
            saved_by=VALUES(saved_by), updated_at=NOW()
    ");
    $p1=$_POST['hear_day']??''; $p2=$_POST['hear_mo']??''; $p3=$_POST['hear_yr']??'';
    $p4=$_POST['hear_time']??''; $p5=$_POST['notif_day']??''; $p6=$_POST['notif_mo']??'';
    $p7=$_POST['notif_yr']??'';
    $stmt->bind_param('sssssssss',$case_id,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$savedBy);
    $ok = $stmt->execute();

} elseif ($docType === 'mediation') {
    $stmt = $conn->prepare("
        INSERT INTO case_mediation
            (case_id, agreement_text, saved_by)
        VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE
            agreement_text=VALUES(agreement_text),
            saved_by=VALUES(saved_by), updated_at=NOW()
    ");

    $agreement = $_POST['agreement_text'] ?? '';

    $stmt->bind_param('sss', $case_id, $agreement, $savedBy);
    $ok = $stmt->execute();
}

echo json_encode([
    'ok'       => $ok,
    'saved_at' => date('M d, Y g:i A'),
    'error'    => $ok ? null : $conn->error
]);