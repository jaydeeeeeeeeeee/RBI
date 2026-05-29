<?php
error_reporting(0);
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$fpdf_path = dirname(__DIR__) . '/eBlotter/fpdf/fpdf.php';
if (!file_exists($fpdf_path)) die('FPDF library not found.');
require_once $fpdf_path;

$request_id = (int)($_GET['id'] ?? 0);
if ($request_id <= 0) die('Invalid request ID');

$stmt = $pdo->prepare("
    SELECT cr.id AS request_id, cr.purpose, ct.template_name,
           r.first_name, r.middle_name, r.last_name, r.perm_address AS address,
           TIMESTAMPDIFF(YEAR, r.birthdate, CURDATE()) AS age, r.gender
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.id
    JOIN certificate_templates ct ON cr.template_id = ct.id
    WHERE cr.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();
if (!$request) die('Request not found');

$sig_name = 'PUNONG BARANGAY'; $sig_title = 'Punong Barangay'; $sec_name = 'BARANGAY SECRETARY';
try {
    $s = $pdo->query("SELECT `value` FROM settings WHERE `key`='cert_signatory'")->fetch();
    if ($s) { $d=json_decode($s['value'],true)??[]; $sig_name=$d['name']??$sig_name; $sig_title=$d['title']??$sig_title; }
    $s2 = $pdo->query("SELECT `value` FROM settings WHERE `key`='cert_secretary'")->fetch();
    if ($s2) { $d2=json_decode($s2['value'],true)??[]; $sec_name=$d2['name']??$sec_name; }
} catch (Exception $e) {}

$mi     = trim($request['middle_name'] ?? '');
$mi_str = $mi ? ' '.strtoupper(substr($mi,0,1)).'.' : '';
$resident_name = strtoupper($request['first_name']).$mi_str.' '.strtoupper($request['last_name']);
$address       = strtoupper($request['address'] ?? '');
$age           = $request['age'];
$himher        = ($request['gender']=='Female') ? 'her' : 'him';
$template_name = $request['template_name'] ?? 'Barangay Certification';
$purpose       = strtoupper($request['purpose'] ?? '');
$control_no    = date('Y').'-'.date('m').'-'.str_pad($request_id,3,'0',STR_PAD_LEFT);
$printed_by    = $_SESSION['full_name'] ?? ($_SESSION['admin'] ?? 'System');
$day_suffix    = date('jS');
$month_upper   = strtoupper(date('F'));
$year_now      = date('Y');

$_base    = dirname(__DIR__);
$logo_bp  = $_base.'/images/logo_bagong_pilipinas.png';   // left outer — Bagong Pilipinas
$logo_brgy= $_base.'/images/Brgy410_seal.png';            // left inner  — Barangay seal
$logo_mla = $_base.'/images/lungsod_ng_manila_logo.png';  // right inner — Manila
$logo_ncr = $_base.'/images/barangay-logo.png';           // right outer

// ── PDF class ──────────────────────────────────────────────────────────────────
class CertPDF extends FPDF {
    public $printedBy = '', $printedAt = '', $wmLogo = null;
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

    function Header() {
        if (!$this->wmLogo) return;
        $W=$this->GetPageWidth(); $H=$this->GetPageHeight(); $sz=80;
        $this->_out('q');
        $this->SetAlpha(0.07);
        $this->Image($this->wmLogo,($W-$sz)/2,($H-$sz)/2-10,$sz);
        $this->_out('Q');
        $this->SetAlpha(1);
    }

    function Footer() {
        $this->SetY(-8);
        $this->SetFont('Times','I',7);
        $this->SetTextColor(150,150,150);
        $this->Cell(0,4,'Printed by: '.$this->printedBy.'   |   '.$this->printedAt,0,0,'C');
    }
}

$pdf = new CertPDF('P','mm','A4');
$pdf->printedBy = $printed_by;
$pdf->printedAt = date('F j, Y  h:i A');
$pdf->wmLogo    = file_exists($_base.'/images/Brgy410_seal.png') ? $_base.'/images/Brgy410_seal.png' : $logo_brgy;
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(20,12,20);
$pdf->AddPage();

$W  = $pdf->GetPageWidth();   // 210
$H  = $pdf->GetPageHeight();  // 297
$LM = 20; $RM = 20; $TW = $W-$LM-$RM;
$lh = 7;  // standard line height

// ── Page border ────────────────────────────────────────────────────────────────
$pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.5);
$pdf->Rect(10,10,$W-20,$H-20);

// ── Header logos ───────────────────────────────────────────────────────────────
$hy = 14; $ls = 20;  // logo y-start, logo size

if (file_exists($logo_bp))   $pdf->Image($logo_bp,  13,              $hy, $ls);
if (file_exists($logo_brgy)) $pdf->Image($logo_brgy, 13+$ls+2,      $hy, $ls);
if (file_exists($logo_mla))  $pdf->Image($logo_mla,  $W-13-$ls*2-2, $hy, $ls);
if (file_exists($logo_ncr))  $pdf->Image($logo_ncr,  $W-13-$ls,     $hy, $ls);

// Center text between the logo columns
$cx = 13+$ls*2+4;
$cw = $W-26-$ls*4-8;

$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Times','B',9);
$pdf->SetXY($cx,$hy+1); $pdf->Cell($cw,5,'Republic of the Philippines',0,1,'C');
$pdf->SetX($cx);        $pdf->Cell($cw,5,'National Capital Region',0,1,'C');
$pdf->SetFont('Times','B',10);
$pdf->SetX($cx);        $pdf->Cell($cw,5,'City of Manila',0,1,'C');
$pdf->SetFont('Times','B',8);
$pdf->SetX($cx);        $pdf->Cell($cw,5,'TANGGAPAN NG PUNONG BARANGAY',0,1,'C');
$pdf->SetFont('Times','',7.5);
$pdf->SetX($cx);        $pdf->Cell($cw,4,'Barangay 410, Zone 42, District IV, Manila',0,1,'C');
$pdf->SetX($cx);        $pdf->Cell($cw,4,'230 M. F. Jhocson St. Sampaloc, Manila',0,1,'C');
$pdf->SetX($cx);        $pdf->Cell($cw,4,'E-mail Address: barangay410zone42@gmail.com',0,1,'C');

// Separator drawn AFTER text so it never overlaps the header
$sep_y = max($pdf->GetY(), $hy+$ls) + 2;
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.5); $pdf->Line($LM,$sep_y,$W-$RM,$sep_y);
$pdf->SetLineWidth(0.2); $pdf->Line($LM,$sep_y+1.5,$W-$RM,$sep_y+1.5);

// Control No — right-aligned
$pdf->SetFont('Times','',8.5); $pdf->SetTextColor(80,80,80);
$pdf->SetXY($LM,$sep_y+3);
$pdf->Cell($TW,5,'Control No: '.$control_no,0,1,'R');

// Certificate title
switch ($template_name) {
    case 'Barangay Clearance':                  $cert_title='BARANGAY CLEARANCE'; break;
    case 'Certificate of Residency':            $cert_title='CERTIFICATE OF RESIDENCY'; break;
    case 'Certificate of Indigency':            $cert_title='CERTIFICATE OF INDIGENCY'; break;
    case 'Certificate of Good Moral Character': $cert_title='CERTIFICATE OF GOOD MORAL CHARACTER'; break;
    case 'Business Permit':                     $cert_title='BARANGAY BUSINESS CLEARANCE'; break;
    default:                                    $cert_title='BARANGAY CERTIFICATION'; break;
}

$title_y = $sep_y+12;
$pdf->SetFont('Times','B',16); $pdf->SetTextColor(0,0,0);
$pdf->SetXY($LM,$title_y);
$pdf->Cell($TW,10,$cert_title,0,1,'C');
$pdf->SetLineWidth(0.4);
$pdf->Line($LM+25,$title_y+11,$W-$RM-25,$title_y+11);

// ── Body paragraphs (inline bold/underline via Write()) ────────────────────────
$by = $title_y+17;
$pdf->SetTextColor(0,0,0);

// Shared: issued-date line (called at end of every case)
$date_line = function() use ($pdf,$LM,$lh,$day_suffix,$month_upper,$year_now) {
    $pdf->SetXY($LM,$pdf->GetY());
    $pdf->SetFont('Times','',12); $pdf->Write($lh,'     Issued this ');
    $pdf->SetFont('Times','B',12); $pdf->Write($lh,$day_suffix.' day of '.$month_upper.', '.$year_now);
    $pdf->SetFont('Times','',12); $pdf->Write($lh,' in the City of Manila, Philippines.');
};

// Shared: purpose line
$purpose_line = function() use ($pdf,$LM,$lh,$purpose) {
    if (!$purpose) return;
    $pdf->SetXY($LM,$pdf->GetY());
    $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This certificate is issued for ');
    $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$purpose);
    $pdf->SetFont('Times','',12); $pdf->Write($lh,' purposes only.');
    $pdf->Ln($lh+4);
};

switch ($template_name) {

    case 'Certificate of Indigency':
        $pdf->SetXY($LM,$by);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This is to certify that ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$resident_name);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,' of legal age, is an indigent and a bonafide resident of this barangay with postal address at ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$address.'.');
        $pdf->Ln($lh+4);
        $purpose_line();
        $date_line();
        break;

    case 'Barangay Clearance':
        $pdf->SetXY($LM,$by);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This is to certify that ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$resident_name);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', '.$age.' years old, a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$address);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', is personally known to this office and has been found to be a person of ');
        $pdf->SetFont('Times','B',12); $pdf->Write($lh,'GOOD MORAL CHARACTER');
        $pdf->SetFont('Times','',12); $pdf->Write($lh,' and ');
        $pdf->SetFont('Times','B',12); $pdf->Write($lh,'GOOD STANDING');
        $pdf->SetFont('Times','',12); $pdf->Write($lh,' in the community, and has no derogatory record or pending case filed against '.$himher.' in this barangay.');
        $pdf->Ln($lh+4);
        $purpose_line();
        $date_line();
        break;

    case 'Certificate of Residency':
        $pdf->SetXY($LM,$by);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This is to certify that ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$resident_name);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', '.$age.' years old, is a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$address.'.');
        $pdf->Ln($lh+4);
        $purpose_line();
        $date_line();
        break;

    case 'Certificate of Good Moral Character':
        $pdf->SetXY($LM,$by);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This is to certify that ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$resident_name);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', '.$age.' years old, a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$address);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', is known in the community to be a person of ');
        $pdf->SetFont('Times','B',12); $pdf->Write($lh,'GOOD MORAL CHARACTER');
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', and is known to be law-abiding, industrious, and has no known derogatory record in this barangay.');
        $pdf->Ln($lh+4);
        $purpose_line();
        $date_line();
        break;

    case 'Business Permit':
        $pdf->SetXY($LM,$by);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This is to certify that ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$resident_name);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$address);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', has been issued this Business Clearance and is duly authorized to conduct business operations within the jurisdiction of Barangay 410 Zone 42.');
        $pdf->Ln($lh+4);
        $purpose_line();
        $date_line();
        break;

    default:
        $pdf->SetXY($LM,$by);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,'     This is to certify that ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$resident_name);
        $pdf->SetFont('Times','',12); $pdf->Write($lh,', '.$age.' years old, is a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with postal address at ');
        $pdf->SetFont('Times','BU',12); $pdf->Write($lh,$address.'.');
        $pdf->Ln($lh+4);
        $purpose_line();
        $date_line();
        break;
}

// ── Signature section ──────────────────────────────────────────────────────────
$sig_top   = $H-88;
$half_tw   = $TW/2;

// "Attested by:" / "Noted By;" labels
$pdf->SetFont('Times','',11); $pdf->SetTextColor(0,0,0);
$pdf->SetXY($LM,$sig_top);
$pdf->Cell($half_tw,6,'Attested by:',0,0,'L');
$pdf->Cell($half_tw,6,'Noted By;',0,1,'L');

// Signature underlines
$sig_line_y = $sig_top+20;
$rx = $LM+$half_tw+15;
$pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.4);
$pdf->Line($LM,          $sig_line_y, $LM+$half_tw-15, $sig_line_y);
$pdf->Line($rx,          $sig_line_y, $W-$RM,           $sig_line_y);

// Secretary (left)
$pdf->SetXY($LM,$sig_line_y+2);
$pdf->SetFont('Times','B',11); $pdf->SetTextColor(0,0,0);
$pdf->Cell($half_tw-15,6,strtoupper($sec_name),0,1,'L');
$pdf->SetX($LM);
$pdf->SetFont('Times','',10); $pdf->SetTextColor(50,50,50);
$pdf->Cell($half_tw-15,5,'Barangay Secretary',0,1,'L');

// Captain (right)
$pdf->SetXY($rx,$sig_line_y+2);
$pdf->SetFont('Times','B',11); $pdf->SetTextColor(0,0,0);
$pdf->Cell($W-$RM-$rx,6,strtoupper($sig_name),0,1,'L');
$pdf->SetX($rx);
$pdf->SetFont('Times','',10); $pdf->SetTextColor(50,50,50);
$pdf->Cell($W-$RM-$rx,5,$sig_title,0,1,'L');

// Specimen Signature line (bottom-left)
$spec_y = $sig_line_y+24;
$pdf->SetXY($LM,$spec_y-5);
$pdf->SetFont('Times','',9); $pdf->SetTextColor(80,80,80);
$pdf->Cell(50,5,'Specimen Signature',0,1,'L');
$pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.3);
$pdf->Line($LM,$spec_y+2,$LM+45,$spec_y+2);

// "Not Valid without..." notice — bottom right
$pdf->SetXY($W/2,$H-16);
$pdf->SetFont('Times','I',7.5); $pdf->SetTextColor(100,100,100);
$pdf->Cell($W/2-12,4,'Not Valid without the Official Barangay Seal and Signature',0,0,'R');

$pdf->Output('I','Certificate_'.$request_id.'.pdf');
?>
