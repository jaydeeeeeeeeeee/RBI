<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']);
// PDF Export Password Gate
if (!isset($_POST['export_pw']) || empty($_POST['export_pw'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;"><h2 style="color:#991b1b;">&#128274; Password Required</h2><p>Please close this window and export with the correct password.</p></body></html>';
    exit();
}
// auth.php; fallback for safety
if (!function_exists('verifyCurrentUserOrChairPassword')) {
    function verifyCurrentUserOrChairPassword(mysqli $conn, string $attempt): bool {
        if (isChairperson()) {
            $uid = currentUser()['id'];
            $s = $conn->prepare("SELECT password FROM admins WHERE id=? LIMIT 1");
            if (!$s) return false;
            $s->bind_param('i', $uid); $s->execute();
            $row = $s->get_result()->fetch_assoc();
            return $row && password_verify($attempt, $row['password']);
        }
        return verifyChairpersonPassword($conn, $attempt);
    }
}
$exportPwOk = false;
$exportPwOk = verifyCurrentUserOrChairPassword($conn, $_POST['export_pw']);
if (!$exportPwOk) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;"><h2 style="color:#991b1b;">&#128274; Incorrect Password</h2><p>PDF export was blocked. Please close this window and try again.</p></body></html>';
    exit();
}

require_once 'fpdf/fpdf.php';


$case_id   = trim($_POST['case_id']   ?? $_GET['case_id']   ?? '');
$to_name   = trim($_POST['to_name']   ?? '');
$hear_day  = trim($_POST['hear_day']  ?? '______');
$hear_mo   = trim($_POST['hear_mo']   ?? '____________');
$hear_yr   = trim($_POST['hear_yr']   ?? '__');
$hear_time = trim($_POST['hear_time'] ?? '____');
$notif_day = trim($_POST['notif_day'] ?? '______');
$notif_mo  = trim($_POST['notif_mo']  ?? '____________');
$notif_yr  = trim($_POST['notif_yr']  ?? '__');

$case = null;
if ($case_id) {
    $stmt = $conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
    $stmt->bind_param('s', $case_id); $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
}
$cFull = $case ? trim($case['complainant_first'] . ' ' . $case['complainant_middle'] . ' ' . $case['complainant_last']) : '';
if (empty($to_name)) $to_name = $cFull;

require_once __DIR__ . '/barcode.php';

// ── half-page copy
function drawNoticeCopy(FPDF $pdf, float $startY,
    string $to_name, string $hear_day, string $hear_mo, string $hear_yr,
    string $hear_time, string $notif_day, string $notif_mo, string $notif_yr,
    bool $showRef = false, string $signerName = ''): float
{
    $ml  = 22;
    $pw  = 166;

    $y = $startY;
    $ls = 13; $ib = dirname(__DIR__).'/images/';

    // 4 logos (compact for half-page)
    if(file_exists($ib.'logo_bagong_pilipinas.png'))  $pdf->Image($ib.'logo_bagong_pilipinas.png',  $ml,           $y,$ls);
    if(file_exists($ib.'Brgy410_seal.png'))           $pdf->Image($ib.'Brgy410_seal.png',           $ml+$ls+1,    $y,$ls);
    if(file_exists($ib.'lungsod_ng_manila_logo.png')) $pdf->Image($ib.'lungsod_ng_manila_logo.png', $ml+$pw-$ls*2-1,$y,$ls);
    if(file_exists($ib.'barangay-logo.png'))          $pdf->Image($ib.'barangay-logo.png',          $ml+$pw-$ls,  $y,$ls);

    // Center text
    $cx=$ml+$ls*2+3; $cw=$pw-$ls*4-6;
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Times','B',8);
    $pdf->SetXY($cx,$y+1); $pdf->Cell($cw,3.5,'Republic of the Philippines',0,1,'C');
    $pdf->SetX($cx);       $pdf->Cell($cw,3.5,'National Capital Region',0,1,'C');
    $pdf->SetFont('Times','B',9);
    $pdf->SetX($cx);       $pdf->Cell($cw,3.5,'City of Manila',0,1,'C');
    $pdf->SetFont('Times','B',7.5);
    $pdf->SetX($cx);       $pdf->Cell($cw,3.5,'TANGGAPAN NG PUNONG BARANGAY',0,1,'C');
    $pdf->SetFont('Times','',7);
    $pdf->SetX($cx);       $pdf->Cell($cw,3,'Barangay 410, Zone 42, District IV, Manila',0,1,'C');
    $pdf->SetX($cx);       $pdf->Cell($cw,3,'230 M. F. Jhocson St. Sampaloc, Manila',0,1,'C');

    // Separator
    $sep_y = max($pdf->GetY(), $y+$ls)+1;
    $pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.4);
    $pdf->Line($ml,$sep_y,$ml+$pw,$sep_y);
    $pdf->SetLineWidth(0.15); $pdf->Line($ml,$sep_y+1,$ml+$pw,$sep_y+1);
    $y = $sep_y+3;

    $pdf->SetFont('Times', 'B', 9.5);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetXY($ml, $y);
    $pdf->Cell($pw, 4.5, 'OFFICE OF THE LUPONG TAGAPAMAYAPA', 0, 1, 'C');
    $y += 5;

    $pdf->SetFont('Times', 'B', 14);
    $pdf->SetXY($ml, $y);
    $pdf->Cell($pw, 7, 'NOTICE OF HEARING', 0, 1, 'C');
    $y += 7;

    $pdf->SetFont('Times', 'B', 11);
    $pdf->SetXY($ml, $y);
    $pdf->Cell($pw, 5, '(MEDIATION PROCEEDINGS)', 0, 1, 'C');
    $y += 10;

    // To:
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY($ml, $y);
    $pdf->Cell(8, 5, 'To:', 0, 0);
    $tx = $pdf->GetX(); $ty = $y;
    $toW = 70;
    $pdf->Cell($toW, 5, $to_name, 0, 0, 'C');
    $pdf->Line($tx, $ty + 5, $tx + $toW, $ty + 5);
    $y += 6;
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY($ml+30, $y);
    $pdf->Cell(0, 4, 'Complainant/s', 0, 1, 'L');
    $y += 16;

    // Body paragraph
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY($ml, $y);
    $pdf->Cell(8, 5, '', 0, 0);
    $pdf->Write(5, 'You      are      hereby      required       to      appear     before     me     on      the     ');
    $u1x = $pdf->GetX(); $u1y = $pdf->GetY();
    $pdf->Cell(20, 5, $hear_day, 0, 0, 'C');
    $pdf->Line($u1x, $u1y + 5, $u1x + 20, $u1y + 5);
    $pdf->Write(5, '   day    of');
    $pdf->Ln();
    $pdf->SetX($ml);
    $u2x = $pdf->GetX(); $u2y = $pdf->GetY();
    $pdf->Cell(38, 5, $hear_mo, 0, 0, 'C');
    $pdf->Line($u2x, $u2y + 5, $u2x + 38, $u2y + 5);
    $pdf->Write(5, ', ');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Write(5, '   20    ');
    $pdf->SetFont('Times', '', 10);
    $u3x = $pdf->GetX(); $u3y = $pdf->GetY();
    $pdf->Cell(12, 5, $hear_yr, 0, 0, 'C');
    $pdf->Line($u3x, $u3y + 5, $u3x + 12, $u3y + 5);
    $pdf->Write(5, '  at    ');
    $u4x = $pdf->GetX(); $u4y = $pdf->GetY();
    $pdf->Cell(16, 5, $hear_time, 0, 0, 'C');
    $pdf->Line($u4x, $u4y + 5, $u4x + 16, $u4y + 5);
    $pdf->Write(5, '   am/pm  for  the    hearing   of    your    complaint.');
    $pdf->Ln();
    $y = $pdf->GetY() + 16;

    // Signature — right
    $sigX = $ml + $pw - 68;
    $sigW = 66;
    $pdf->Line($sigX, $y, $sigX + $sigW, $y);
    $y += 1;
    $pdf->SetFont('Times', 'B', 9.5);
    $pdf->SetXY($sigX, $y);
    $pdf->Cell($sigW, 4.5, $signerName, 0, 1, 'C');
    $y += 4.5;
    $pdf->SetFont('Times', '', 8.5);
    $pdf->SetXY($sigX, $y);
    $pdf->Cell($sigW, 4, 'Barangay Chairman', 0, 1, 'C');
    $y += 10;

    // Notified this …
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY($ml, $y);
    $pdf->Write(5, 'Notified this ');
    $n1x = $pdf->GetX(); $n1y = $pdf->GetY();
    $pdf->Cell(20, 5, $notif_day, 0, 0, 'C');
    $pdf->Line($n1x, $n1y + 5, $n1x + 20, $n1y + 5);
    $pdf->Write(5, ' day of ');
    $n2x = $pdf->GetX(); $n2y = $pdf->GetY();
    $pdf->Cell(38, 5, $notif_mo, 0, 0, 'C');
    $pdf->Line($n2x, $n2y + 5, $n2x + 38, $n2y + 5);
    $pdf->Write(5, ', 20');
    $n3x = $pdf->GetX(); $n3y = $pdf->GetY();
    $pdf->Cell(12, 5, $notif_yr, 0, 0, 'C');
    $pdf->Line($n3x, $n3y + 5, $n3x + 12, $n3y + 5);
    $pdf->Write(5, '.');
    $y = $pdf->GetY() + 6;

    return $y;
}
 
class NoticePDF extends FPDF {
    public string $exportedBy='', $exportedAt='';
    public $extgstates=[];

    function SetAlpha($alpha) {
        foreach($this->extgstates as $i=>$v){
            if(abs($v['ca']-$alpha)<0.001){$this->_out('/GS'.$i.' gs');return;}
        }
        $i=count($this->extgstates)+1;
        $this->extgstates[$i]=['ca'=>$alpha,'n'=>0];
        $this->_out('/GS'.$i.' gs');
    }
    function _putresourcedict(){
        parent::_putresourcedict();
        if(!empty($this->extgstates)){
            $this->_put('/ExtGState <<');
            foreach($this->extgstates as $k=>$v) $this->_put('/GS'.$k.' '.$v['n'].' 0 R');
            $this->_put('>>');
        }
    }
    function _putresources(){
        foreach($this->extgstates as $k=>&$v){
            $this->_newobj(); $v['n']=$this->n;
            $this->_put('<</Type /ExtGState /ca '.$v['ca'].' /CA '.$v['ca'].' /BM /Normal>>');
            $this->_put('endobj');
        }
        parent::_putresources();
    }

    function RotatedText($x, $y, $txt, $angle) {
        $this->_out('q');
        $rad = deg2rad($angle);
        $c = cos($rad); $s = sin($rad);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf('%.5F %.5F %.5F %.5F %.5F %.5F cm', $c, $s, -$s, $c, $cx, $cy));
        $this->_out($this->TextColor);
        $this->_out(sprintf('BT /F%d %.2F Tf 0 0 Td (%s) Tj ET',
            $this->CurrentFont['i'], $this->FontSizePt, $this->_escape($txt)));
        $this->_out('Q');
    }

    function Header() {
        $wm=dirname(__DIR__).'/images/Brgy410_seal.png';
        if(file_exists($wm)){
            $W=$this->GetPageWidth(); $H=$this->GetPageHeight(); $sz=90;
            $this->_out('q'); $this->SetAlpha(0.07);
            $this->Image($wm,($W-$sz)/2,($H-$sz)/2-10,$sz);
            $this->_out('Q'); $this->SetAlpha(1);
        }
    }

    function Footer() {
        $txt = 'DIGITAL COPY - NOT VALID IF UNSIGNED';
        $this->SetFont('Times', 'B', 10);
        $unitW = $this->GetStringWidth($txt);
        $targetH = $this->GetPageHeight() - 20;
        $fs = ($unitW > 0) ? (10 * $targetH / $unitW) : 30;
        $this->SetFont('Times', 'B', $fs);
        $tw = $this->GetStringWidth($txt);
        $this->SetTextColor(190, 190, 190);
        $this->RotatedText(10, ($this->GetPageHeight() + $tw) / 2, $txt, 90);

        $this->SetY(-10);
        $this->SetFont('Times', 'I', 7);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 4, 'Exported by: ' . $this->exportedBy . ' | ' . $this->exportedAt, 0, 0, 'C');
    }
}

// ── Build PDF
$pdf = new NoticePDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(false);
$pdf->SetTitle('Notice of Hearing - ' . $case_id);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();

// Copy 1 (top half)
$endY1 = drawNoticeCopy(
    $pdf, 8,
    $to_name, $hear_day, $hear_mo, $hear_yr, $hear_time,
    $notif_day, $notif_mo, $notif_yr, true,
    getSignerName($conn)
);

// Dashed separator
$sepY = 148;
$pdf->SetDrawColor(140, 140, 140);
$pdf->SetLineWidth(0.3);
$x = 10;
while ($x < 200) {
    $pdf->Line($x, $sepY, min($x + 4, 200), $sepY);
    $x += 7;
}

// Copy 2 (bottom half)
drawNoticeCopy(
    $pdf, $sepY + 5,
    $to_name, $hear_day, $hear_mo, $hear_yr, $hear_time,
    $notif_day, $notif_mo, $notif_yr, false,
    getSignerName($conn)
);

$u = currentUser();
$pdf->exportedBy = $u ? $u['full_name'] : 'Unknown';
$pdf->exportedAt = date('M d, Y h:i A');
markExported($conn, $case_id, 'NOTICE_OF_HEARING');

//128 barcode using Case ID 
if (!empty($case_id)) {
    $barcodeH    = 10;    // bar height (mm)
    $barcodeNarW = 0.28;  // narrow module width (mm)
    $barcodeBits  = 35 + strlen($case_id) * 11;
    $barcodeW     = $barcodeBits * $barcodeNarW;
    $barcodeX = $pdf->GetPageWidth() - $barcodeW - 10;
    $barcodeY = $pdf->GetPageHeight() - 22;
    draw_code128($pdf, $case_id, $barcodeX, $barcodeY, $barcodeH, $barcodeNarW);
}

// Mark notice as done so workflow unlocks Mediation Minutes
$conn->query("UPDATE blotter_cases SET notice_done=1 WHERE case_id='".mysqli_real_escape_string($conn,$case_id)."' AND (notice_done IS NULL OR notice_done=0)");
auditLog($conn, 'EXPORT_NOTICE', $case_id, 'Notice of Hearing PDF exported');

// Save notice data to DB BEFORE Output()
$savedBy = $u ? $u['full_name'] : 'Unknown';

$p_hear_day  = $_POST['hear_day']  ?? '';
$p_hear_mo   = $_POST['hear_mo']   ?? '';
$p_hear_yr   = $_POST['hear_yr']   ?? '';
$p_hear_time = $_POST['hear_time'] ?? '';
$p_notif_day = $_POST['notif_day'] ?? '';
$p_notif_mo  = $_POST['notif_mo']  ?? '';
$p_notif_yr  = $_POST['notif_yr']  ?? '';

$stmtSave = $conn->prepare("
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
$stmtSave->bind_param('sssssssss',
    $case_id,
    $p_hear_day,
    $p_hear_mo,
    $p_hear_yr,
    $p_hear_time,
    $p_notif_day,
    $p_notif_mo,
    $p_notif_yr,
    $savedBy
);
$stmtSave->execute();

$pdf->Output('I', 'brgy410_notice_hearing_' . preg_replace('/[^A-Za-z0-9\-]/', '', $case_id) . '.pdf');
exit;