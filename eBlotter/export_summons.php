<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']);
// ── PDF Export Password Gate
if (!isset($_POST['export_pw']) || empty($_POST['export_pw'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;"><h2 style="color:#991b1b;">&#128274; Password Required</h2><p>Please close this window and export with the correct password.</p></body></html>';
    exit();
}
// Guard: defined in auth.php; fallback for safety
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

$case_id      = trim($_POST['case_id']      ?? $_GET['case_id']      ?? '');
$hearing_day  = trim($_POST['hearing_day']  ?? '');
$hearing_mo   = trim($_POST['hearing_mo']   ?? '');
$hearing_yr   = trim($_POST['hearing_yr']   ?? '');
$hearing_time = trim($_POST['hearing_time'] ?? '');
$this_day     = trim($_POST['this_day']     ?? '');
$this_mo      = trim($_POST['this_mo']      ?? '');
$this_yr      = trim($_POST['this_yr']      ?? '');
$to_name      = trim($_POST['to_name']      ?? '');

$case = null;
if ($case_id) {
    $stmt = $conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
    $stmt->bind_param('s',$case_id); $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
}
$cFull = $case ? trim($case['complainant_first'].' '.$case['complainant_middle'].' '.$case['complainant_last']) : '';
$rFull = $case ? trim($case['respondent_first'].' '.$case['respondent_middle'].' '.$case['respondent_last'])   : '';
if (empty($to_name)) $to_name = $rFull;
$disp  = $case ? $case['disposition'] : ''; 

require_once __DIR__ . '/barcode.php';

class SummonsPDF extends FPDF {
    public string $exportedBy='', $exportedAt='';

    public $wmLogo = null;
    public $extgstates = [];

    function SetAlpha($alpha) {
        foreach ($this->extgstates as $i => $v) {
            if (abs($v['ca']-$alpha)<0.001) { $this->_out('/GS'.$i.' gs'); return; }
        }
        $i = count($this->extgstates)+1;
        $this->extgstates[$i] = ['ca'=>$alpha,'n'=>0];
        $this->_out('/GS'.$i.' gs');
    }
    function _putresourcedict() {
        parent::_putresourcedict();
        if (!empty($this->extgstates)) {
            $this->_put('/ExtGState <<');
            foreach ($this->extgstates as $k=>$v) $this->_put('/GS'.$k.' '.$v['n'].' 0 R');
            $this->_put('>>');
        }
    }
    function _putresources() {
        foreach ($this->extgstates as $k=>&$v) {
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
        // Re-emit color and font inside q/Q so they aren't lost by state save/restore
        $this->_out($this->TextColor);
        $this->_out(sprintf('BT /F%d %.2F Tf 0 0 Td (%s) Tj ET',
            $this->CurrentFont['i'], $this->FontSizePt, $this->_escape($txt)));
        $this->_out('Q');
    }

    function Header(){
        $W=$this->GetPageWidth(); $H=$this->GetPageHeight();
        // Watermark
        $wm=dirname(__DIR__).'/images/Brgy410_seal.png';
        if(file_exists($wm)){
            $sz=90;
            $this->_out('q'); $this->SetAlpha(0.07);
            $this->Image($wm,($W-$sz)/2,($H-$sz)/2-10,$sz);
            $this->_out('Q'); $this->SetAlpha(1);
        }
        // 4 logos
        $hy=10; $ls=18; $ib=dirname(__DIR__).'/images/';
        if(file_exists($ib.'logo_bagong_pilipinas.png'))  $this->Image($ib.'logo_bagong_pilipinas.png',  12,             $hy,$ls);
        if(file_exists($ib.'Brgy410_seal.png'))           $this->Image($ib.'Brgy410_seal.png',           12+$ls+2,      $hy,$ls);
        if(file_exists($ib.'lungsod_ng_manila_logo.png')) $this->Image($ib.'lungsod_ng_manila_logo.png', $W-12-$ls*2-2, $hy,$ls);
        if(file_exists($ib.'barangay-logo.png'))          $this->Image($ib.'barangay-logo.png',          $W-12-$ls,     $hy,$ls);
        // Center text
        $cx=12+$ls*2+4; $cw=$W-24-$ls*4-8;
        $this->SetTextColor(0,0,0);
        $this->SetFont('Times','B',9);
        $this->SetXY($cx,$hy+1); $this->Cell($cw,4,'Republic of the Philippines',0,1,'C');
        $this->SetX($cx);        $this->Cell($cw,4,'National Capital Region',0,1,'C');
        $this->SetFont('Times','B',10);
        $this->SetX($cx);        $this->Cell($cw,4,'City of Manila',0,1,'C');
        $this->SetFont('Times','B',8);
        $this->SetX($cx);        $this->Cell($cw,4,'TANGGAPAN NG PUNONG BARANGAY',0,1,'C');
        $this->SetFont('Times','',7.5);
        $this->SetX($cx);        $this->Cell($cw,3.5,'Barangay 410, Zone 42, District IV, Manila',0,1,'C');
        $this->SetX($cx);        $this->Cell($cw,3.5,'230 M. F. Jhocson St. Sampaloc, Manila',0,1,'C');
        // Separator
        $sep_y=max($this->GetY(),$hy+$ls)+2;
        $this->SetDrawColor(0,0,0); $this->SetLineWidth(0.5);
        $this->Line(12,$sep_y,$W-12,$sep_y);
        $this->SetLineWidth(0.2); $this->Line(12,$sep_y+1.5,$W-12,$sep_y+1.5);
        // Office title
        $this->SetFont('Times','B',12); $this->SetTextColor(0,0,0);
        $this->SetXY(20,$sep_y+4);
        $this->Cell($W-40,5,'OFFICE OF THE LUPONG TAGAPAMAYAPA',0,1,'C');
        $this->Ln(1);
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
        $this->Cell(0, 4, 'Exported by: '.$this->exportedBy.' | '.$this->exportedAt, 0, 0, 'C');
    }
}

$pdf = new SummonsPDF('P', 'mm', 'Legal');
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetTitle('Summons - '.$case_id);
$pdf->SetMargins(20, 55, 14);
$pdf->AddPage();


$ml = 20;
$pw = 176;

$pdf->SetFont('Times', '', 10);
$pdf->SetTextColor(0,0,0);

$colLw = 50;
$colRx = $ml + 104;
$colRw = 50;

$y = $pdf->GetY();

ul($pdf, $ml, $y+5, $colLw, 5, $cFull, 'C');
$pdf->SetXY($colRx, $y+5);
$pdf->Cell($colRw, 5, 'Barangay Case No.', 0, 0, 'L');
$y += 10;

$pdf->SetFont('Times', '', 10);
$pdf->SetXY($ml, $y);
$pdf->Cell($colLw, 5, 'Complainant/s', 0, 0, 'C');
$pdf->SetFont('Times', '', 10);
ul($pdf, $colRx, $y, $colRw, 4, $case_id, 'C');
$y += 10;

$pdf->SetFont('Times', 'I', 10);
$pdf->SetXY($ml, $y);
$pdf->Cell($colLw, 5, '-against-', 0, 0, 'C');
$pdf->SetFont('Times', '', 10);
$pdf->SetXY($colRx, $y);
$pdf->Cell($colRw, -2, 'For:', 0, 0, 'L');
$y += 10;

$pdf->SetFont('Times', '', 10);
ul($pdf, $ml, $y, $colLw, 5, $rFull, 'C');
ul($pdf, $colRx, $y-10, $colRw, 5, $disp, 'C');
$y += 5;

$pdf->SetFont('Times', '', 10);
$pdf->SetXY($ml, $y);
$pdf->Cell($colLw, 5, 'Respondent/s', 0, 1, 'C');

$pdf->Ln(6);

$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 6, 'S U M M O N S', 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('Times', '', 10);
$y = $pdf->GetY();
$pdf->SetXY($ml, $y);
$pdf->Cell(8, 5, 'To:', 0, 0, 'L');
$tx = $pdf->GetX();
$toW = 84;
$pdf->Cell($toW*0.7, 5, $to_name, 0, 1, 'C');
$pdf->Line($tx, $y+5, $tx+($toW*0.7), $y+5);
$pdf->Ln(8);

$pdf->SetFont('Times', 'B', 10);
$pdf->SetX($ml);
$pdf->Cell(0, 5, 'Respondent/s', 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('Times', '', 10);
$body1 = '                  You are hereby summoned to appear before me in person together with your witnesses, on the '
       . ($hearing_day ?: '______').' day of '.($hearing_mo ?: '____________')
       . ' 20'.($hearing_yr ?: '__').' at '.($hearing_time ?: '______')
       . ' am/pm, then and there to answer to a complaint made before me, copy of which is attached hereto, for mediation/conciliation of your dispute with complainant/s.';
$pdf->SetX($ml);
$pdf->MultiCell($pw, 5.5, $body1, 0, 'J');
$pdf->Ln(3);

$pdf->SetX($ml);
$pdf->MultiCell($pw, 5.5,
    '                  You are hereby warned that if you refuse or willfully fail to appear in obedience to this summons, you may be barred from filing any counterclaim arising from said complaint.',
    0, 'J');
$pdf->Ln(3);

$pdf->SetX($ml);
$pdf->Cell(0, 5, '                  FAIL NOT or else face punishment as for contempt of court.', 0, 1, 'L');
$pdf->Ln(5);

$pdf->SetFont('Times', '', 10);
$y = $pdf->GetY();
$pdf->SetXY($ml, $y);
$pdf->Write(5.5, '                  This ');
$t1x = $pdf->GetX(); $t1y = $pdf->GetY();
$pdf->Cell(22, 5.5, $this_day, 0, 0, 'C');
$pdf->Line($t1x, $t1y+5.5, $t1x+22, $t1y+5.5);
$pdf->Write(5.5, ' day of ');
$t2x = $pdf->GetX(); $t2y = $pdf->GetY();
$pdf->Cell(38, 5.5, $this_mo, 0, 0, 'C');
$pdf->Line($t2x, $t2y+5.5, $t2x+38, $t2y+5.5);
$pdf->Write(5.5, ', 20');
$t3x = $pdf->GetX(); $t3y = $pdf->GetY();
$pdf->Cell(14, 5.5, $this_yr, 0, 0, 'C');
$pdf->Line($t3x, $t3y+5.5, $t3x+14, $t3y+5.5);
$pdf->Write(5.5, '.');
$pdf->Ln(20);

$sigX = 110; $sigW = 80;
$sy = $pdf->GetY();
$pdf->Line($sigX, $sy, $sigX+$sigW, $sy);
$pdf->SetXY($sigX, $sy+1);
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell($sigW, 5, strtoupper(getSignerName($conn)), 0, 1, 'C');
$pdf->SetXY($sigX, $sy+6);
$pdf->SetFont('Times', '', 9);
$pdf->Cell($sigW, 4, 'Punong Barangay', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Times', 'B', 11);
$pdf->Cell(0, 6, "OFFICER'S RETURN", 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Times', '', 10);
$y = $pdf->GetY();
$pdf->SetXY($ml, $y);
$pdf->Write(8, '                  I         served         this         summons         upon         respondent  ');
$u1x = $pdf->GetX(); $u1y = $pdf->GetY();
$pdf->Cell(56, 8, '', 0, 0); $pdf->Line($u1x, $u1y+6, $u1x+56, $u1y+6);
$pdf->Write(8, ' on the');
$pdf->Ln(6);

$pdf->SetXY($ml, $pdf->GetY());
$r1x = $pdf->GetX(); $r1y = $pdf->GetY();
$pdf->Cell(16, 8, '', 0, 0); $pdf->Line($r1x, $r1y+6, $r1x+16, $r1y+6);
$pdf->Write(8, ' day of  ');
$r2x = $pdf->GetX(); $r2y = $pdf->GetY();
$pdf->Cell(42, 8, '', 0, 0); $pdf->Line($r2x, $r2y+6, $r2x+42, $r2y+6);
$pdf->Write(8, ', 20  ');
$r3x = $pdf->GetX(); $r3y = $pdf->GetY();
$pdf->Cell(14, 8, '', 0, 0); $pdf->Line($r3x, $r3y+6, $r3x+14, $r3y+6);
$pdf->Write(8, '.');
$pdf->Ln(16);

$opts = [
    '1. Handing to him/them said summons in person, or',
    '2. Handing to him/them said summons and he/they refused to received it, or',
    '3. Leaving said summons at his/their dwelling with ________________ (name) a person suitable age and discretion     residing therein, or',
    '4. Leaving said summons at his/their office/place of business with ________________ (name) a competent person       in charge thereof.',
];
foreach ($opts as $opt) {
    $y = $pdf->GetY();
    $pdf->SetXY($ml, $y);
    $lx = $pdf->GetX(); $ly = $pdf->GetY();
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Line($lx, $ly+4, $lx+10, $ly+4);
    $pdf->MultiCell($pw-10, 5, $opt, 0, 'L');
    $pdf->Ln(1);
}
$pdf->Ln(10);

$osy = $pdf->GetY();
$pdf->Line($sigX, $osy, $sigX+$sigW, $osy);
$pdf->SetXY($sigX, $osy+1);
$pdf->SetFont('Times', '', 9);
$pdf->Cell($sigW, 4, 'Officer', 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('Times', '', 10);
$pdf->SetX($ml);
$pdf->Cell(0, 5, 'Received by Respondent/s representative/s:', 0, 1, 'L');
$pdf->Ln(10);

$s1x = $ml;
$s1w = 80;
$d1x = $ml + $pw - 70;
$d1w = 70;

$pdf->Line($s1x, $pdf->GetY(), $s1x+$s1w, $pdf->GetY());
$pdf->Line($d1x, $pdf->GetY(), $d1x+$d1w, $pdf->GetY());
$pdf->Ln(1);
$pdf->SetFont('Times', '', 9);
$pdf->SetX($s1x); $pdf->Cell($s1w, 4, 'Signature', 0, 0, 'C');
$pdf->SetX($d1x); $pdf->Cell($d1w, 4, 'Date',      0, 1, 'C');
$pdf->Ln(7);

$pdf->Line($s1x, $pdf->GetY(), $s1x+$s1w, $pdf->GetY());
$pdf->Line($d1x, $pdf->GetY(), $d1x+$d1w, $pdf->GetY());
$pdf->Ln(1);
$pdf->SetFont('Times', '', 9);
$pdf->SetX($s1x); $pdf->Cell($s1w, 4, 'Signature', 0, 0, 'C');
$pdf->SetX($d1x); $pdf->Cell($d1w, 4, 'Date',      0, 1, 'C');

$u = currentUser();
$pdf->exportedBy = $u ? $u['full_name'] : 'Unknown';
$pdf->exportedAt = date('M d, Y h:i A');
markExported($conn, $case_id, 'SUMMONS');

// 128 barcode using Case ID as value
if (!empty($case_id)) {
    $barcodeH    = 10;    // bar height (mm)
    $barcodeNarW = 0.28;  // narrow module width (mm)
    // Code 128B bit count: START(11) + chars×11 + checksum(11) + STOP(13)
    $barcodeBits  = 35 + strlen($case_id) * 11;
    $barcodeW     = $barcodeBits * $barcodeNarW;
    $barcodeX = $pdf->GetPageWidth() - $barcodeW - 10;
    $barcodeY = $pdf->GetPageHeight() - 22;
    draw_code128($pdf, $case_id, $barcodeX, $barcodeY, $barcodeH, $barcodeNarW);
}

// Mark summons as done so workflow unlocks Notice of Hearing
$conn->query("UPDATE blotter_cases SET summons_done=1 WHERE case_id='".mysqli_real_escape_string($conn,$case_id)."' AND (summons_done IS NULL OR summons_done=0)");
// Auto-advance status to Ongoing when summons is exported
$conn->query("UPDATE blotter_cases SET status='Ongoing' WHERE case_id='".mysqli_real_escape_string($conn,$case_id)."' AND status='Pending'");
auditLog($conn,'EXPORT_SUMMONS',$case_id,'Summons PDF exported');

$savedBy = currentUser() ? currentUser()['full_name'] : 'Unknown';

$stmtSave = $conn->prepare("
    INSERT INTO case_summons
        (case_id, to_name, hearing_day, hearing_mo, hearing_yr, hearing_time,
         this_day, this_mo, this_yr,
         or_respondent, or_day, or_mo, or_yr,
         or_opt1, or_opt2, or_opt3, or_name3, or_opt4, or_name4,
         saved_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        to_name=VALUES(to_name), hearing_day=VALUES(hearing_day),
        hearing_mo=VALUES(hearing_mo), hearing_yr=VALUES(hearing_yr),
        hearing_time=VALUES(hearing_time),
        this_day=VALUES(this_day), this_mo=VALUES(this_mo), this_yr=VALUES(this_yr),
        or_respondent=VALUES(or_respondent), or_day=VALUES(or_day),
        or_mo=VALUES(or_mo), or_yr=VALUES(or_yr),
        or_opt1=VALUES(or_opt1), or_opt2=VALUES(or_opt2),
        or_opt3=VALUES(or_opt3), or_name3=VALUES(or_name3),
        or_opt4=VALUES(or_opt4), or_name4=VALUES(or_name4),
        saved_by=VALUES(saved_by), updated_at=NOW()
");
$or_respondent = $_POST['or_respondent'] ?? '';
$or_day        = $_POST['or_day']        ?? '';
$or_mo         = $_POST['or_mo']         ?? '';
$or_yr         = $_POST['or_yr']         ?? '';
$or_opt1       = $_POST['or_opt1']       ?? '';
$or_opt2       = $_POST['or_opt2']       ?? '';
$or_opt3       = $_POST['or_opt3']       ?? '';
$or_name3      = $_POST['or_name3']      ?? '';
$or_opt4       = $_POST['or_opt4']       ?? '';
$or_name4      = $_POST['or_name4']      ?? '';
$stmtSave->bind_param('ssssssssssssssssssss',
    $case_id,
    $to_name,
    $hearing_day,
    $hearing_mo,
    $hearing_yr,
    $hearing_time,
    $this_day,
    $this_mo,
    $this_yr,
    $or_respondent,
    $or_day,
    $or_mo,
    $or_yr,
    $or_opt1,
    $or_opt2,
    $or_opt3,
    $or_name3,
    $or_opt4,
    $or_name4,
    $savedBy
);
$stmtSave->execute();
$pdf->Output('I', 'brgy410_summons_'.preg_replace('/[^A-Za-z0-9\-]/','',$case_id).'.pdf');
exit;