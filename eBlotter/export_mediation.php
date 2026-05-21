<?php
require_once __DIR__.'/auth.php';
requireRole(['chairperson','secretary']);

// ── PDF Export Password Gate 
if (empty($_POST['export_pw'])) {
    http_response_code(403); header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;">
    <h2 style="color:#991b1b;">&#128274; Password Required</h2>
    <p>Please close this window and export with the correct password.</p></body></html>';
    exit();
}

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
// Standardized field name: agreement_text (minutes is a legacy alias)
$minutes = trim($_POST['agreement_text'] ?? $_POST['minutes'] ?? '');

$case=null;
if($case_id){
    $stmt=$conn->prepare("SELECT * FROM blotter_cases WHERE case_id=?");
    $stmt->bind_param('s',$case_id); $stmt->execute();
    $case=$stmt->get_result()->fetch_assoc();
}

// Workflow check: Mediation Minutes require Ongoing status AND notice_done=1
// Checking only 'status' is insufficient — a case can be manually set Ongoing
// without completing the Summons → Notice workflow.
if($case && ($case['status']==='Pending' || empty($case['notice_done']))){
    http_response_code(403); header('Content-Type: text/html; charset=utf-8');
    $missing = $case['status']==='Pending' ? 'issue a Summons' : 'issue a Notice of Hearing';
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;">
    <h2 style="color:#991b1b;">&#128683; Step Locked</h2>
    <p>Mediation Minutes are only available after completing all prior steps.<br>
    Please <strong>'.$missing.'</strong> first.</p></body></html>';
    exit();
}

$cFull=$case?trim($case['complainant_first'].' '.$case['complainant_middle'].' '.$case['complainant_last']):'';
$rFull=$case?trim($case['respondent_first'].' '.$case['respondent_middle'].' '.$case['respondent_last']):'';
$today=date('F d, Y');

require_once __DIR__ . '/barcode.php';

// ── PDF Class
class MedMinutesPDF extends FPDF {
    public string $exportedBy='', $exportedAt='';
    function Header(){

        // Watermark
        $logo = __DIR__ . '/../eBlotter/images/Barangay_logo_409_1.png';

        if(file_exists($logo)){
            $pageW = $this->GetPageWidth();
            $pageH = $this->GetPageHeight();

            $size = 100;

            $x = ($pageW - $size) / 2;
            $y = ($pageH - $size) / 2;

            $this->Image($logo, $x, $y, $size);
        }

        $this->SetFont('Times','',9); $this->SetTextColor(0,0,0);
        $this->SetXY(20,10); $this->Cell(170,4,'Republic of the Philippines',0,1,'C');
        $this->SetX(20);     $this->Cell(170,4,'City of Manila',0,1,'C');
        $this->SetX(20);     $this->Cell(170,4,'District IV',0,1,'C');
        $this->Ln(1);
        $this->SetFont('Times','B',11);
        $this->SetX(20); $this->Cell(170,5,'OFFICE OF LUPON TAGAPAMAYAPA',0,1,'C');
        $this->SetFont('Times','',9);
        $this->SetX(20); $this->Cell(170,4,'Barangay 409 Zone 42  |  254 Sta. Teresita St. Sampaloc, Manila',0,1,'C');
        $this->Ln(2); $this->SetLineWidth(0.5);
        $this->Line(20,$this->GetY(),190,$this->GetY()); $this->Ln(4);
        $this->SetFont('Times','B',13);
        $this->SetX(20); $this->Cell(170,6,'MEDIATION MINUTES',0,1,'C'); $this->Ln(2);
    }
    function Footer(){
        $this->SetY(-10);
        $this->SetFont('Times','I',7);
        $this->SetTextColor(130,130,130);
        $this->Cell(56,4,'Exported by: '.$this->exportedBy.' | '.$this->exportedAt.'',0,0,'C');

        $this->SetY(-20);
        $width = 100;
        $x = $this->GetPageWidth() - $width - 10; // 10mm right margin
        $this->SetX($x);
        $this->SetFont('Times','B',12);
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(130, 130, 130);     
        $this->Cell(100,10,'DIGITAL COPY ' .chr(150) . ' NOT VALID IF UNSIGNED',1,0,'C');
    }
}

$ml=20; $pw=170; $lineH=7; $sigBlockTop = 297 - 70;
$pdf=new MedMinutesPDF('P','mm','A4');
$pdf->SetAutoPageBreak(false);
$pdf->SetTitle('Mediation Minutes - '.$case_id);
$pdf->SetMargins($ml,10,210-$ml-$pw);
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
    foreach(explode("\n",str_replace("\r\n","\n",$minutes)) as $raw){
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
$pdf->SetX($col2x);            $pdf->Cell($colW,5,'MA. VERONICA M. PAJARES',0,1,'C');
$pdf->SetFont('Times','',8);
$pdf->SetXY($col1x,$sigY3+6); $pdf->Cell($colW,4,'Lupon Chairman/Punong Barangay',0,0,'C');
$pdf->SetX($col2x);            $pdf->Cell($colW,4,'Lupon Secretary/Barangay Secretary',0,1,'C');

$u=currentUser();
$pdf->exportedBy=$u?$u['full_name']:'Unknown';
$pdf->exportedAt=date('M d, Y h:i A');
markExported($conn,$case_id,'MEDIATION_MINUTES');

// 128 barcode using Case ID 
if (!empty($case_id)) {
    $barcodeX    = 10;   // left margin (mm)
    $barcodeH    = 10;   // bar height (mm)
    $barcodeNarW = 0.30; // narrow module width (mm)
    // above the footer
    $barcodeY = $pdf->GetPageHeight() - 20;
    draw_code128($pdf, $case_id, $barcodeX, $barcodeY, $barcodeH, $barcodeNarW);
}

// Capture PDF string FIRST — only set flags if generation succeeds
$savedBy     = $u ? $u['full_name'] : 'Unknown';
$p_agreement = $_POST['agreement_text'] ?? $_POST['minutes'] ?? '';

$pdfString = $pdf->Output('S', 'brgy409_mediation_'.preg_replace('/[^A-Za-z0-9\-]/','', $case_id).'.pdf');

if (!empty($pdfString)) {
    // Mark mediation as done
    $stmtMed = $conn->prepare("UPDATE blotter_cases SET mediation_done=1 WHERE case_id=? AND (mediation_done IS NULL OR mediation_done=0)");
    $stmtMed->bind_param('s', $case_id);
    $stmtMed->execute();
    auditLog($conn,'EXPORT_MEDIATION',$case_id,'Mediation Minutes PDF exported');

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

    // Send the PDF to the browser
    header('Content-Type: application/pdf');
    $pdfFilename = 'brgy409_mediation_' . preg_replace('/[^A-Za-z0-9\-]/', '', $case_id) . '.pdf';
    header('Content-Disposition: inline; filename=' . $pdfFilename);
    header('Content-Length: ' . strlen($pdfString));
    echo $pdfString;
} else {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem;">
    <h2 style="color:#991b1b;">&#9888; PDF Generation Failed</h2>
    <p>The PDF could not be generated. No workflow flags were changed.</p>
    <p><a href="javascript:history.back()">Go back</a></p></body></html>';
}
exit;