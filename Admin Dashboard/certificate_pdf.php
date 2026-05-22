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
           r.first_name, r.last_name, r.perm_address AS address,
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

$resident_name = strtoupper($request['first_name'].' '.$request['last_name']);
$address       = $request['address'];
$age           = $request['age'];
$pronoun       = ($request['gender']=='Female') ? 'Ms.' : 'Mr.';
$hisher        = ($request['gender']=='Female') ? 'her' : 'his';
$himher        = ($request['gender']=='Female') ? 'her' : 'him';
$template_name = $request['template_name'] ?? 'Barangay Certification';
$purpose       = $request['purpose'] ?? '';
$document_no   = 'CERT-'.date('Ymd').'-'.str_pad($request_id,4,'0',STR_PAD_LEFT);
$printed_by    = $_SESSION['full_name'] ?? ($_SESSION['admin'] ?? 'System');
$date_issued   = date('F j, Y');
$valid_until   = date('F j, Y', strtotime('+1 month'));
$day_ord       = date('jS');
$month_year    = date('F Y');

$_base = dirname(__DIR__);
$logo  = null;
foreach ([$_base.'/images/brgy410_logo.png', $_base.'/eBlotter/images/brgy410_logo.png'] as $p)
    if (file_exists($p)) { $logo = $p; break; }

// ── PDF Class ──────────────────────────────────────────────────────────────────
class CertPDF extends FPDF {
    public $logo=null, $docNo='', $printedBy='', $printedAt='', $certTitle='';
    public $extgstates = [];

    function SetAlpha($alpha) {
        foreach ($this->extgstates as $i => $v) {
            if (abs($v['ca'] - $alpha) < 0.001) { $this->_out('/GS'.$i.' gs'); return; }
        }
        $i = count($this->extgstates) + 1;
        $this->extgstates[$i] = ['ca' => $alpha, 'n' => 0];
        $this->_out('/GS'.$i.' gs');
    }

    function _putresourcedict() {
        parent::_putresourcedict();
        if (!empty($this->extgstates)) {
            $this->_put('/ExtGState <<');
            foreach ($this->extgstates as $k => $v)
                $this->_put('/GS'.$k.' '.$v['n'].' 0 R');
            $this->_put('>>');
        }
    }

    // Write ExtGState objects BEFORE calling parent so n values are assigned
    // before _putresourcedict() references them inside parent::_putresources()
    function _putresources() {
        foreach ($this->extgstates as $k => &$v) {
            $this->_newobj();
            $v['n'] = $this->n;
            $this->_put('<</Type /ExtGState /ca '.$v['ca'].' /CA '.$v['ca'].' /BM /Normal>>');
            $this->_put('endobj');
        }
        parent::_putresources();
    }

    function RotatedText($x,$y,$txt,$angle) {
        $this->_out('q');
        $r=deg2rad($angle); $c=cos($r); $s=sin($r);
        $cx=$x*$this->k; $cy=($this->h-$y)*$this->k;
        $this->_out(sprintf('%.5F %.5F %.5F %.5F %.5F %.5F cm',$c,$s,-$s,$c,$cx,$cy));
        $this->_out($this->TextColor);
        $this->_out(sprintf('BT /F%d %.2F Tf 0 0 Td (%s) Tj ET',
            $this->CurrentFont['i'],$this->FontSizePt,$this->_escape($txt)));
        $this->_out('Q');
    }

    function Header() {
        $W = $this->GetPageWidth();   // 210mm (A4)
        $H = $this->GetPageHeight();  // 297mm (A4)

        // ── Watermark: centered horizontally, vertically mid-content ────────
        if ($this->logo) {
            $sz = 68;
            $this->_out('q');
            $this->SetAlpha(0.18);
            $this->Image($this->logo, ($W-$sz)/2, ($H-$sz)/2 - 10, $sz);
            $this->_out('Q');
        }

        // ── Page double border ───────────────────────────────────────────────
        $this->SetDrawColor(15,23,42); $this->SetLineWidth(0.8);
        $this->Rect(8, 8, $W-16, $H-16);
        $this->SetDrawColor(201,162,39); $this->SetLineWidth(0.35);
        $this->Rect(11, 11, $W-22, $H-22);
        $this->SetLineWidth(0.2); $this->SetDrawColor(0,0,0);

        // ── Navy top bar ─────────────────────────────────────────────────────
        $this->SetFillColor(15,23,42);
        $this->Rect(11, 11, $W-22, 6, 'F');
        $this->SetFillColor(201,162,39);
        $this->Rect(11, 17, $W-22, 1.2, 'F');

        // ── Document number ──────────────────────────────────────────────────
        $this->SetFont('Times','I',7.5);
        $this->SetTextColor(120,120,120);
        $this->SetXY(14, 12);
        $this->Cell($W-28, 4, 'Document No: '.$this->docNo, 0, 0, 'R');

        // ── Corner logos (28mm — prominent) ──────────────────────────────────
        if ($this->logo) {
            $this->Image($this->logo, 13, 18, 28);         // left logo
            $this->Image($this->logo, $W-41, 18, 28);      // right logo
        }

        // ── Centered header text ─────────────────────────────────────────────
        $tx = 43; $tw = $W-86;
        $this->SetTextColor(180,140,30);
        $this->SetFont('Times','I',8.5);
        $this->SetXY($tx, 20);
        $this->Cell($tw, 4.5, 'Republic of the Philippines', 0, 1, 'C');

        $this->SetTextColor(15,23,42);
        $this->SetFont('Times','B',13);
        $this->SetX($tx);
        $this->Cell($tw, 6.5, 'BARANGAY 410 ZONE 42', 0, 1, 'C');

        $this->SetFont('Times','',8.5);
        $this->SetTextColor(80,80,80);
        $this->SetX($tx);
        $this->Cell($tw, 4, 'District IV, City of Manila', 0, 1, 'C');

        // ── Gold divider ─────────────────────────────────────────────────────
        $this->SetDrawColor(201,162,39); $this->SetLineWidth(0.55);
        $this->Line(18, 47, $W-18, 47);
        $this->SetLineWidth(0.2);
        $this->Line(18, 49, $W-18, 49);

        // ── Certificate title banner (auto-size font for long titles) ────────
        $this->SetFillColor(15,23,42);
        $this->SetTextColor(255,255,255);
        $banW = $W - 36;
        $fs = 14;
        $this->SetFont('Times','B',$fs);
        while ($this->GetStringWidth($this->certTitle) > $banW - 6 && $fs > 9) {
            $fs -= 0.5;
            $this->SetFont('Times','B',$fs);
        }
        $this->SetXY(18, 51);
        $this->Cell($banW, 11, $this->certTitle, 0, 1, 'C', true);
        $this->SetFillColor(201,162,39);
        $this->Rect(18, 62, $W-36, 1.2, 'F');

        $this->SetDrawColor(0,0,0);
        $this->SetTextColor(0,0,0);
    }

    function Footer() {
        $W = $this->GetPageWidth(); $H = $this->GetPageHeight();
        // Gold footer accent
        $this->SetFillColor(201,162,39);
        $this->Rect(11, $H-13, $W-22, 0.8, 'F');
        // Watermark text on left margin
        $txt = 'DIGITAL COPY - NOT VALID IF UNSIGNED';
        $this->SetFont('Times','B',11);
        $uw = $this->GetStringWidth($txt);
        $fs = ($uw>0) ? (11*($H-22)/$uw) : 28;
        $this->SetFont('Times','B',$fs);
        $tw = $this->GetStringWidth($txt);
        $this->SetTextColor(205,205,205);
        $this->RotatedText(8.5, ($H+$tw)/2, $txt, 90);
        // Printed by
        $this->SetY(-10);
        $this->SetFont('Times','I',7);
        $this->SetTextColor(140,140,140);
        $this->Cell(0,4,'Printed by: '.$this->printedBy.'   |   '.$this->printedAt,0,0,'C');
    }
}

// ── Build PDF ──────────────────────────────────────────────────────────────────
$pdf = new CertPDF('P','mm','A4');
$pdf->logo      = $logo;
$pdf->docNo     = $document_no;
$pdf->printedBy = $printed_by;
$pdf->printedAt = date('F j, Y  h:i A');
switch ($template_name) {
    case 'Barangay Clearance':           $cert_title='BARANGAY CLEARANCE'; break;
    case 'Certificate of Residency':     $cert_title='CERTIFICATE OF RESIDENCY'; break;
    case 'Certificate of Indigency':     $cert_title='CERTIFICATE OF INDIGENCY'; break;
    case 'Certificate of Good Moral Character': $cert_title='CERTIFICATE OF GOOD MORAL CHARACTER'; break;
    case 'Business Permit':              $cert_title='BARANGAY BUSINESS CLEARANCE'; break;
    default:                             $cert_title='BARANGAY CERTIFICATION'; break;
}

$pdf->certTitle = $cert_title;
$LM = 30;  // left/right margin — wider = text block more centred on page
$pdf->SetAutoPageBreak(true, 22);
$pdf->SetMargins($LM, 85, $LM);  // 85mm top pushes paragraphs lower on A4
$pdf->SetTitle('Barangay Certificate - '.$resident_name);
$pdf->AddPage();
$pdf->SetTextColor(0,0,0);
$W = $pdf->GetPageWidth();

// ── Certificate body ───────────────────────────────────────────────────────────
$purpose_line = $purpose
    ? "\n\n     This certificate is issued upon the request of the above-named individual for the purpose of {$purpose}."
    : '';
$sign_line = "     Signed this {$day_ord} day of {$month_year} at Barangay 410 Zone 42, City of Manila.";

switch ($template_name) {
    case 'Barangay Clearance':
        $body = "TO WHOM IT MAY CONCERN:\n\n"
            . "     This is to certify that {$pronoun} {$resident_name}, {$age} years old, a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at {$address}, is personally known to this office and has been found to be a person of GOOD MORAL CHARACTER and GOOD STANDING in the community.\n\n"
            . "     {$pronoun} {$resident_name} has no derogatory record or pending case filed against {$himher} in this barangay."
            . $purpose_line."\n\n".$sign_line;
        break;
    case 'Certificate of Residency':
        $body = "TO WHOM IT MAY CONCERN:\n\n"
            . "     This is to certify that {$pronoun} {$resident_name}, {$age} years old, is a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at {$address}."
            . $purpose_line."\n\n".$sign_line;
        break;
    case 'Certificate of Indigency':
        $body = "TO WHOM IT MAY CONCERN:\n\n"
            . "     This is to certify that {$pronoun} {$resident_name}, {$age} years old, a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at {$address}, belongs to an indigent family in this barangay.\n\n"
            . "     This certification is issued to attest to the financial status of the bearer and to enable {$himher} to avail of benefits and assistance from government and non-government agencies."
            . $purpose_line."\n\n".$sign_line;
        break;
    case 'Certificate of Good Moral Character':
        $body = "TO WHOM IT MAY CONCERN:\n\n"
            . "     This is to certify that {$pronoun} {$resident_name}, {$age} years old, a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at {$address}, is known in the community to be a person of GOOD MORAL CHARACTER.\n\n"
            . "     {$pronoun} {$resident_name} is known to be law-abiding, industrious, and has no known derogatory record in this barangay."
            . $purpose_line."\n\n".$sign_line;
        break;
    case 'Business Permit':
        $body = "TO WHOM IT MAY CONCERN:\n\n"
            . "     This is to certify that {$pronoun} {$resident_name}, a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with address at {$address}, has been issued this Business Clearance by this Barangay Office.\n\n"
            . "     The bearer is duly authorized to conduct business operations within the jurisdiction of Barangay 410 Zone 42, subject to compliance with all applicable laws, ordinances, and regulations."
            . $purpose_line."\n\n".$sign_line;
        break;
    default:
        $body = "TO WHOM IT MAY CONCERN:\n\n"
            . "     This is to certify that {$pronoun} {$resident_name}, {$age} years old, is a bonafide resident of Barangay 410 Zone 42, District IV, City of Manila, with postal address at {$address}, and is qualified to avail of Republic Act No. 11261, otherwise known as the First Time Jobseekers Assistance Act of 2019.\n\n"
            . "     This further certifies that the bearer was duly informed of the rights, duties, and responsibilities accorded by R.A. 11261 through the oath of undertaking executed in the presence of Barangay Officials."
            . $purpose_line."\n\n".$sign_line;
        break;
}

// ── Split body into paragraphs, render "TO WHOM IT MAY CONCERN:" bold ──────────
$paras = array_values(array_filter(array_map('trim', explode("\n\n", $body))));
foreach ($paras as $i => $para) {
    if ($i === 0 && strncmp($para, 'TO WHOM', 7) === 0) {
        // Formal heading — bold, small-caps style, with bottom rule
        $pdf->SetFont('Times','B',12);
        $pdf->SetTextColor(15,23,42);
        $pdf->Cell(0, 7, $para, 0, 1, 'L');
        // Thin underline beneath heading
        $pdf->SetDrawColor(201,162,39); $pdf->SetLineWidth(0.25);
        $pdf->Line($LM, $pdf->GetY(), $LM+70, $pdf->GetY());
        $pdf->SetDrawColor(0,0,0);
        $pdf->Ln(5);
    } else {
        $pdf->SetFont('Times','',12);
        $pdf->SetTextColor(30,30,30);
        $pdf->MultiCell(0, 7, $para, 0, 'J');
        $pdf->Ln(5);
    }
}

// ── Validity box ───────────────────────────────────────────────────────────────
$pdf->Ln(2);
$vy = $pdf->GetY();
$pdf->SetFillColor(255,252,232);
$pdf->SetDrawColor(201,162,39);
$pdf->SetLineWidth(0.4);
$pdf->Rect($LM, $vy, $W-2*$LM, 9, 'DF');
$pdf->SetFont('Times','I',10);
$pdf->SetTextColor(110,70,0);
$pdf->SetXY($LM+4, $vy+1.5);
$pdf->Cell($W-2*$LM-8, 6, "Validity Period: This certification is valid until {$valid_until} only.", 0, 1, 'L');
$pdf->SetTextColor(30,30,30);

// ── Signature section — anchored to bottom ────────────────────────────────────
$H_page = $pdf->GetPageHeight();

// "IN WITNESS WHEREOF" line sits 18mm above the signature box
$witness_y = $H_page - 72;
$pdf->SetXY($LM, $witness_y);
$pdf->SetFont('Times','I',10.5);
$pdf->SetTextColor(50,50,50);
$pdf->MultiCell($W-2*$LM, 6,
    'IN WITNESS WHEREOF, I have hereunto set my hand and caused the seal of this Barangay to be affixed on the date and place first above written.',
    0, 'J');

// Thin gold rule above signature box
$sig_y = $H_page - 52;
$pdf->SetDrawColor(201,162,39); $pdf->SetLineWidth(0.3);
$pdf->Line($LM, $sig_y-4, $W-$LM, $sig_y-4);
$pdf->SetDrawColor(0,0,0);

// Signature box — light fill
$bw   = $W - 2*$LM;          // box width matches content width
$pdf->SetFillColor(248,249,252);
$pdf->Rect($LM, $sig_y-3, $bw, 34, 'F');

$half   = $bw / 2;
$pad    = 10;                 // inner padding from box edge
$lx     = $LM + $pad;        // left column x
$rx     = $LM + $half + $pad; // right column x
$cw     = $half - 2*$pad;    // column text width
$line_y = $sig_y + 13;

// Left — Secretary
$pdf->SetDrawColor(15,23,42); $pdf->SetLineWidth(0.5);
$pdf->Line($lx, $line_y, $lx+$cw, $line_y);
$pdf->SetXY($lx, $line_y+2);
$pdf->SetFont('Times','B',11); $pdf->SetTextColor(15,23,42);
$pdf->Cell($cw, 6, strtoupper($sec_name), 0, 1, 'C');
$pdf->SetX($lx);
$pdf->SetFont('Times','',9); $pdf->SetTextColor(80,80,80);
$pdf->Cell($cw, 4.5, 'Barangay Secretary', 0, 0, 'C');

// Right — Punong Barangay
$pdf->SetDrawColor(15,23,42);
$pdf->Line($rx, $line_y, $rx+$cw, $line_y);
$pdf->SetXY($rx, $line_y+2);
$pdf->SetFont('Times','B',11); $pdf->SetTextColor(15,23,42);
$pdf->Cell($cw, 6, strtoupper($sig_name), 0, 1, 'C');
$pdf->SetX($rx);
$pdf->SetFont('Times','',9); $pdf->SetTextColor(80,80,80);
$pdf->Cell($cw, 4.5, $sig_title, 0, 0, 'C');

// Bottom disclaimer
$pdf->SetXY($LM, $sig_y+30);
$pdf->SetDrawColor(201,162,39); $pdf->SetLineWidth(0.25);
$pdf->Line($LM, $sig_y+29, $W-$LM, $sig_y+29);
$pdf->SetFont('Times','I',7.5);
$pdf->SetTextColor(150,150,150);
$pdf->Cell($bw, 4, 'NOT VALID WITHOUT OFFICIAL DRY SEAL AND SIGNATURE OF THE AUTHORIZED SIGNATORY', 0, 0, 'C');

$pdf->Output('I','Certificate_'.$request_id.'.pdf');
?>
