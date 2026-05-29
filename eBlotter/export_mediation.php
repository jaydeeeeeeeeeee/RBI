<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']);
require_once __DIR__.'/../signatory_helper.php';
$_eb_sigs     = getSignatories($conn);
$_eb_sec_name = strtoupper(trim($_eb_sigs['secretary']['full_name'] ?? '')) ?: 'BARANGAY SECRETARY';

// ── PDF Export Password Gate 
if (empty($_POST['export_pw'])) {
    http_response_code(403); header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;">
    <h2 style="color:#991b1b;">&#128274; Password Required</h2>
    <p>Please close this window and export with the correct password.</p></body></html>';
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
    http_response_code(403); header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;">
    <h2 style="color:#991b1b;">&#128274; Incorrect Password</h2>
    <p>PDF export was blocked. Please close this window and try again.</p></body></html>';
    exit();
}

require_once 'fpdf/fpdf.php';

// ── Load case
$case_id = trim($_POST['case_id'] ?? $_GET['case_id'] ?? '');
$minutes = trim($_POST['minutes'] ?? '');

$case=null;
if($case_id){
    $stmt=$conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
    $stmt->bind_param('s',$case_id); $stmt->execute();
    $case=$stmt->get_result()->fetch_assoc();
}

// Workflow check: Mediation Minutes are only available for Ongoing cases
if($case && $case['status']==='Pending'){
    http_response_code(403); header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;">
    <h2 style="color:#991b1b;">&#128683; Step Locked</h2>
    <p>Mediation Minutes are only available for <strong>Ongoing</strong> cases.<br>
    Please issue a Summons and update the case status to Ongoing first.</p></body></html>';
    exit();
}

$cFull=$case?trim($case['complainant_first'].' '.$case['complainant_middle'].' '.$case['complainant_last']):'';
$rFull=$case?trim($case['respondent_first'].' '.$case['respondent_middle'].' '.$case['respondent_last']):'';
$today=date('F d, Y');

require_once __DIR__ . '/barcode.php';

// ── PDF Class
class MedMinutesPDF extends FPDF {
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
        $this->SetX($cx);        $this->Cell($cw,3.5,'E-mail Address: barangay410zone42@gmail.com',0,1,'C');
        // Separator
        $sep_y=max($this->GetY(),$hy+$ls)+2;
        $this->SetDrawColor(0,0,0); $this->SetLineWidth(0.5);
        $this->Line(12,$sep_y,$W-12,$sep_y);
        $this->SetLineWidth(0.2); $this->Line(12,$sep_y+1.5,$W-12,$sep_y+1.5);
        // Office + document title
        $this->SetFont('Times','B',11); $this->SetTextColor(0,0,0);
        $this->SetXY(20,$sep_y+4);
        $this->Cell(170,5,'OFFICE OF THE LUPONG TAGAPAMAYAPA',0,1,'C');
        $this->SetLineWidth(0.5); $this->Line(20,$this->GetY(),$W-20,$this->GetY()); $this->Ln(4);
        $this->SetFont('Times','B',13);
        $this->SetX(20); $this->Cell(170,6,'MEDIATION MINUTES',0,1,'C'); $this->Ln(2);
    }

    function Footer(){
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
        $this->SetFont('Times','I',7);
        $this->SetTextColor(130,130,130);
        $this->Cell(0,4,'Exported by: '.$this->exportedBy.' | '.$this->exportedAt,0,0,'C');
    }
}

$ml=20; $pw=170; $lineH=7; $sigBlockTop = 297 - 70;
$pdf=new MedMinutesPDF('P','mm','A4');
$pdf->SetAutoPageBreak(false);
$pdf->SetTitle('Mediation Minutes - '.$case_id);
$pdf->SetMargins($ml,65,210-$ml-$pw);
$pdf->AddPage();


// CASE NO.
$pdf->SetFont('Times','',9); $pdf->SetTextColor(0,0,0); $pdf->SetX($ml);
$pdf->Cell($pw-52,5,'',0,0); $pdf->Cell(20,5,'CASE NO.',0,0,'R');
$cnx=$pdf->GetX(); $cny=$pdf->GetY();
$pdf->Cell(30,5,$case_id,0,1,'L'); $pdf->Line($cnx,$cny+5,$cnx+32,$cny+5); $pdf->Ln(2);

// DATE
$pdf->SetFont('Times','',10); $pdf->SetX($ml); $pdf->Cell(14,5,'DATE',0,0,'L');
$dx=$pdf->GetX(); $dy=$pdf->GetY();
$pdf->Cell($pw-14,5,$today,0,1,'L'); $pdf->Line($dx,$dy+5,$dx+($pw-14),$dy+5); $pdf->Ln(1);

// COMPLAINANT/S
$lbl2W=$pdf->GetStringWidth('COMPLAINANT/S')+2;
$pdf->SetX($ml); $pdf->Cell($lbl2W,5,'COMPLAINANT/S',0,0,'L');
$cx=$pdf->GetX(); $cy=$pdf->GetY(); $cvalW=$pw-$lbl2W;
$pdf->Cell($cvalW,5,$cFull,0,1,'L'); $pdf->Line($cx,$cy+5,$cx+$cvalW,$cy+5);

// RESPONDENT/S
$lbl3W=$pdf->GetStringWidth('RESPONDENT/S')+2;
$pdf->SetX($ml); $pdf->Cell($lbl3W,5,'RESPONDENT/S',0,0,'L');
$rx=$pdf->GetX(); $ry=$pdf->GetY(); $rvalW=$pw-$lbl3W;
$pdf->Cell($rvalW,5,$rFull,0,1,'L'); $pdf->Line($rx,$ry+5,$rx+$rvalW,$ry+5);

$pdf->Ln(4);
$writeStartY=$pdf->GetY();

// PASS 1: ruled lines
$pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.25);
for($ly=$writeStartY;$ly+$lineH<=$sigBlockTop;$ly+=$lineH)
    $pdf->Line($ml,$ly+$lineH,$ml+$pw,$ly+$lineH);

// PASS 2: text overlay
if(!empty($minutes)){
    $pdf->SetFont('Times','',10); $pdf->SetTextColor(0,0,0);
    $lines=[];
    foreach(explode("\
",str_replace("\
\
","\
",$minutes)) as $raw){
        if($raw===''){$lines[]='';continue;}
        $words=explode(' ',$raw); $line='';
        foreach($words as $wd){
            $test=$line===''?$wd:$line.' '.$wd;
            if($pdf->GetStringWidth($test)>$pw-2){$lines[]=$line;$line=$wd;}
            else $line=$test;
        }
        if($line!=='') $lines[]=$line;
    }
    $curY=$writeStartY;
    foreach($lines as $tl){
        if($curY+$lineH>$sigBlockTop) break;
        $pdf->SetXY($ml,$curY+1); $pdf->Cell($pw,$lineH-2,$tl,0,0,'L'); $curY+=$lineH;
    }
}

// SIGNATURE BLOCK
$col1x=$ml; $col2x=$ml+$pw/2+6; $colW=$pw/2-10; $sigY=297-60;
$pdf->SetLineWidth(0.4);
$pdf->Line($col1x,$sigY,$col1x+$colW,$sigY); $pdf->Line($col2x,$sigY,$col2x+$colW,$sigY);
$pdf->SetFont('Times','',8.5);
$pdf->SetXY($col1x,$sigY+1); $pdf->Cell($colW,4,'Complainant/s',0,0,'C');
$pdf->SetX($col2x);           $pdf->Cell($colW,4,'Respondent/s',0,1,'C');
$sigY2=$sigY+12;
$pdf->Line($col1x,$sigY2,$col1x+$colW,$sigY2); $pdf->Line($col2x,$sigY2,$col2x+$colW,$sigY2);
$pdf->SetXY($col1x,$sigY2+1); $pdf->Cell($colW,4,'Witness',0,0,'C');
$pdf->SetX($col2x);            $pdf->Cell($colW,4,'Witness',0,1,'C');
$sigY3=$sigY2+13;
$pdf->Line($col1x,$sigY3,$col1x+$colW,$sigY3); $pdf->Line($col2x,$sigY3,$col2x+$colW,$sigY3);
$pdf->SetFont('Times','BU',9);
$pdf->SetXY($col1x,$sigY3+1); $pdf->Cell($colW,5,strtoupper(getSignerName($conn)),0,0,'C');
$pdf->SetX($col2x);            $pdf->Cell($colW,5,$_eb_sec_name,0,1,'C');
$pdf->SetFont('Times','',8);
$pdf->SetXY($col1x,$sigY3+6); $pdf->Cell($colW,4,'Lupon Chairman/Punong Barangay',0,0,'C');
$pdf->SetX($col2x);            $pdf->Cell($colW,4,'Lupon Secretary/Barangay Secretary',0,1,'C');

$u=currentUser();
$pdf->exportedBy=$u?$u['full_name']:'Unknown';
$pdf->exportedAt=date('M d, Y h:i A');
markExported($conn,$case_id,'MEDIATION_MINUTES');

// 128 barcode using Case ID 
if (!empty($case_id)) {
    $barcodeH    = 10;    // bar height (mm)
    $barcodeNarW = 0.28;  // narrow module width (mm)
    $barcodeBits  = 35 + strlen($case_id) * 11;
    $barcodeW     = $barcodeBits * $barcodeNarW;
    $barcodeX = $pdf->GetPageWidth() - $barcodeW - 10;
    $barcodeY = $pdf->GetPageHeight() - 22;
    draw_code128($pdf, $case_id, $barcodeX, $barcodeY, $barcodeH, $barcodeNarW);
}

// Mark mediation as done
$conn->query("UPDATE blotter_cases SET mediation_done=1 WHERE case_id='".mysqli_real_escape_string($conn,$case_id)."' AND (mediation_done IS NULL OR mediation_done=0)");
auditLog($conn,'EXPORT_MEDIATION',$case_id,'Mediation Minutes PDF exported');

// save mediation data to DB BEFORE Output() 
$savedBy = $u ? $u['full_name'] : 'Unknown';
$p_agreement = $_POST['agreement_text'] ?? $_POST['minutes'] ?? '';

$stmtSave = $conn->prepare("
    INSERT INTO case_mediation
        (case_id, agreement_text, saved_by)
    VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE
        agreement_text=VALUES(agreement_text),
        saved_by=VALUES(saved_by), updated_at=NOW()
");

$stmtSave->bind_param('sss', $case_id, $p_agreement, $savedBy);
$stmtSave->execute();

$pdf->Output('I','brgy410_mediation_'.preg_replace('/[^A-Za-z0-9\-]/','', $case_id).'.pdf');

exit;