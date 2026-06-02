<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin.php"); exit(); }
include 'Residents_DB.php';
include 'role_helper.php';
if ($is_guest) { header("Location: Home.php?denied=equipment"); exit(); }

$admin = $_SESSION['admin'];

// ── Ensure tables exist (idempotent) ─────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS equipment (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    item_code        VARCHAR(30)  NOT NULL UNIQUE,
    item_name        VARCHAR(200) NOT NULL,
    category         ENUM('Audio/Visual','Furniture','Cleaning','Sports','Medical','Office','Others') DEFAULT 'Others',
    description      TEXT,
    quantity         INT DEFAULT 1,
    available        INT DEFAULT 1,
    condition_status ENUM('Good','Fair','Needs Repair','Retired') DEFAULT 'Good',
    added_by         VARCHAR(100),
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS equipment_borrowing (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    borrow_code      VARCHAR(30)  NOT NULL UNIQUE,
    equipment_id     INT NOT NULL,
    senior_citizen_id INT DEFAULT NULL,
    borrower_name    VARCHAR(200) NOT NULL,
    borrower_contact VARCHAR(50),
    purpose          TEXT,
    borrow_date      DATE NOT NULL,
    return_date      DATE NOT NULL,
    actual_return    DATE,
    status           ENUM('Pending','Approved','Returned','Overdue','Rejected') DEFAULT 'Approved',
    approved_by      VARCHAR(100),
    remarks          TEXT,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = ''; $msg_type = '';

// ── ADD EQUIPMENT ─────────────────────────────────────────────────────────────
if (isset($_POST['add_equipment'])) {
    $item_code   = trim($conn->real_escape_string($_POST['item_code']));
    $item_name   = trim($conn->real_escape_string($_POST['item_name']));
    $category    = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $quantity    = max(1, (int)$_POST['quantity']);
    $condition   = $conn->real_escape_string($_POST['condition_status']);

    if (empty($item_code) || empty($item_name)) {
        $msg = 'Item Code and Item Name are required.'; $msg_type = 'error';
    } else {
        $chk = $conn->query("SELECT id FROM equipment WHERE item_code='$item_code'");
        if ($chk->num_rows > 0) {
            $msg = 'Item Code already exists.'; $msg_type = 'error';
        } else {
            $conn->query("INSERT INTO equipment (item_code,item_name,category,description,quantity,available,condition_status,added_by)
                          VALUES ('$item_code','$item_name','$category','$description',$quantity,$quantity,'$condition','$admin')");
            $msg = 'Equipment added successfully!'; $msg_type = 'success';
        }
    }
}

// ── BORROW ────────────────────────────────────────────────────────────────────
if (isset($_POST['borrow_submit'])) {
    $equipment_id  = (int)$_POST['equipment_id'];
    $borrower_name = trim($conn->real_escape_string($_POST['borrower_name']));
    $borrow_date   = $_POST['borrow_date'];
    $return_date   = $_POST['return_date'];
    $purpose       = $conn->real_escape_string($_POST['purpose']);
    if ($purpose === 'Others') $purpose = $conn->real_escape_string($_POST['other_purpose'] ?? 'Others');

    $sc = $conn->query("SELECT id, contact_number FROM senior_citizens
                        WHERE status='Active' AND CONCAT(last_name,', ',first_name)='$borrower_name'");
    $sc_id = null; $sc_contact = '';
    if ($sc && $sc->num_rows > 0) {
        $sc_row = $sc->fetch_assoc();
        $sc_id  = $sc_row['id'];
        $sc_contact = $sc_row['contact_number'] ?? '';
    }

    if (!$sc_id) {
        $res = $conn->query("SELECT id FROM residents WHERE is_hidden=0
                             AND CONCAT(first_name,' ',last_name)='$borrower_name' LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            $msg = 'Borrower not found in resident or senior citizen records.'; $msg_type = 'error';
        }
    }

    if ($msg_type !== 'error') {
        if ($borrow_date < date('Y-m-d')) {
            $msg = 'Borrow date cannot be in the past.'; $msg_type = 'error';
        } elseif ($return_date <= $borrow_date) {
            $msg = 'Return date must be after borrow date.'; $msg_type = 'error';
        } else {
            $eq = $conn->query("SELECT available, item_name FROM equipment WHERE id=$equipment_id")->fetch_assoc();
            if (!$eq || $eq['available'] < 1) {
                $msg = 'No units available to borrow.'; $msg_type = 'error';
            } else {
                $code = 'BRW-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
                $sc_id_val = $sc_id ?? 'NULL';
                $conn->query("INSERT INTO equipment_borrowing
                    (borrow_code,equipment_id,senior_citizen_id,borrower_name,borrower_contact,purpose,borrow_date,return_date,status,approved_by)
                    VALUES ('$code',$equipment_id,$sc_id_val,'$borrower_name','$sc_contact','$purpose','$borrow_date','$return_date','Approved','$admin')");
                $conn->query("UPDATE equipment SET available=available-1 WHERE id=$equipment_id");
                $msg = "Borrow recorded. Code: <strong>$code</strong>"; $msg_type = 'success';
            }
        }
    }
}

// ── RETURN ────────────────────────────────────────────────────────────────────
if (isset($_GET['return_id'])) {
    $tid = (int)$_GET['return_id'];
    $tr  = $conn->query("SELECT * FROM equipment_borrowing WHERE id=$tid AND status IN ('Approved','Pending','Overdue')");
    if ($tr && $tr->num_rows === 1) {
        $trr = $tr->fetch_assoc();
        $conn->query("UPDATE equipment_borrowing SET status='Returned',actual_return=CURDATE() WHERE id=$tid");
        $conn->query("UPDATE equipment SET available=available+1 WHERE id={$trr['equipment_id']}");
        $msg = 'Item marked as Returned.'; $msg_type = 'success';
    } else {
        $msg = 'Transaction not found or already closed.'; $msg_type = 'error';
    }
}

// ── DELETE EQUIPMENT ──────────────────────────────────────────────────────────
if (isset($_GET['delete_equipment']) && $is_captain) {
    $eid = (int)$_GET['delete_equipment'];
    $conn->query("DELETE FROM equipment_borrowing WHERE equipment_id=$eid");
    $conn->query("DELETE FROM equipment WHERE id=$eid");
    header('Location: equipment.php?deleted=1'); exit();
}

// ── QUERIES ───────────────────────────────────────────────────────────────────
$equipment_result = $conn->query("SELECT * FROM equipment ORDER BY item_name");
$equip_count      = $equipment_result ? $equipment_result->num_rows : 0;
if ($equipment_result) $equipment_result->data_seek(0);

$seniors_list = [];
$sr = $conn->query("SELECT last_name,first_name FROM senior_citizens WHERE status='Active' ORDER BY last_name,first_name");
if ($sr) while ($s = $sr->fetch_assoc()) $seniors_list[] = $s['last_name'] . ', ' . $s['first_name'];

$residents_list = [];
$rr = $conn->query("SELECT first_name,last_name FROM residents WHERE is_hidden=0 ORDER BY last_name,first_name");
if ($rr) while ($r = $rr->fetch_assoc()) $residents_list[] = $r['first_name'] . ' ' . $r['last_name'];

$borrowed_result = $conn->query("SELECT eb.*,e.item_name,e.item_code
    FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id
    WHERE eb.status IN ('Approved','Pending','Overdue') ORDER BY eb.borrow_date DESC");
$borrowed_count  = $borrowed_result ? $borrowed_result->num_rows : 0;
if ($borrowed_result) $borrowed_result->data_seek(0);

$avail_row   = $conn->query("SELECT COALESCE(SUM(available),0) AS a FROM equipment")->fetch_assoc();
$avail_total = (int)$avail_row['a'];

$history_result = $conn->query("SELECT eb.*,e.item_name FROM equipment_borrowing eb
    JOIN equipment e ON eb.equipment_id=e.id
    WHERE eb.status='Returned' ORDER BY eb.actual_return DESC LIMIT 50");

$_brgy = defined('BRGY_NAME') ? BRGY_NAME : 'Barangay';

// Print data
$print_borrowed = $conn->query("SELECT eb.*,e.item_name,e.item_code
  FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id
  ORDER BY eb.borrow_date DESC");
$print_equip_tot = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(quantity),0) q, COALESCE(SUM(available),0) avail FROM equipment")->fetch_assoc();

$print_borrower_summary = $conn->query("SELECT eb.borrower_name, eb.borrower_contact, COUNT(*) AS cnt,
  GROUP_CONCAT(CONCAT(e.item_code,' - ',e.item_name) SEPARATOR '; ') AS items
  FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id
  GROUP BY eb.borrower_name, eb.borrower_contact
  ORDER BY cnt DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Equipment Borrowing – <?= $_brgy ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css?v=<?=filemtime(__DIR__.'/assets/css/main.css')?>"/>
<style>
.page-hero{background:linear-gradient(to right,rgba(15,23,42,.85),rgba(15,23,42,.55)),url('images/Barangay_officials_410.png') center center/cover no-repeat;padding:2.5rem 2rem;min-height:300px;display:flex;align-items:center}
.page-hero h1{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:#fff;margin:0 0 .3rem}
.page-hero p{color:rgba(255,255,255,.55);font-size:.85rem;margin:0 0 1.25rem}

main{padding:1.75rem 2rem;max-width:1600px;width:min(100%,1600px);margin:0 auto}

/* ── STAT ROW ── */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:1.75rem}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:14px}
.stat-ico{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-ico.blue  {background:var(--blue-lt);color:var(--blue)}
.stat-ico.green {background:var(--green-lt);color:#16a34a}
.stat-ico.amber {background:var(--amber-lt);color:#b45309}
.stat-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
.stat-val{font-size:26px;font-weight:800;color:var(--text);line-height:1.1}

.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;gap:12px;flex-wrap:wrap}
.sec-hd h2{font-family:'Syne',sans-serif;font-size:1.15rem;font-weight:800;color:var(--text)}
.section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:var(--border)}

.add-panel{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.5rem;margin-bottom:1.75rem;display:none}
.add-panel h3{font-size:.95rem;font-weight:700;color:var(--text);margin-bottom:1rem;display:flex;align-items:center;gap:8px}

.equip-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
.equip-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.25rem;display:flex;flex-direction:column;gap:10px;transition:box-shadow .2s,transform .2s;position:relative}
.equip-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.09);transform:translateY(-2px)}
.equip-card-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.equip-card-hd h3{font-size:.9rem;font-weight:700;color:var(--text);line-height:1.3}
.cond-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;flex-shrink:0}
.cond-Good       {background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.cond-Fair       {background:var(--blue-lt);color:#1d4ed8;border:1px solid #bfdbfe}
.cond-NeedsRepair{background:var(--amber-lt);color:#92400e;border:1px solid #fde68a}
.cond-Retired    {background:var(--bg);color:var(--muted);border:1px solid var(--border)}
.equip-meta{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.equip-desc{font-size:12px;color:var(--muted);line-height:1.5}
.equip-stock{display:flex;gap:8px}
.stock-item{flex:1;background:var(--bg);border-radius:9px;padding:8px;text-align:center;border:1px solid var(--border)}
.stock-item .s-v{font-size:20px;font-weight:800;color:var(--text);line-height:1}
.stock-item .s-l{font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;margin-top:2px}
.stock-item.avail .s-v{color:#16a34a}
.stock-item.out   .s-v{color:#b45309}
.stock-bar{background:var(--border);border-radius:4px;height:5px;overflow:hidden;margin-top:-2px}
.stock-bar-fill{height:100%;border-radius:4px;transition:width .4s}
.equip-actions{display:flex;gap:8px;flex-wrap:wrap}

.borrow-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1200;align-items:center;justify-content:center}
.borrow-modal-overlay.open{display:flex}
.borrow-modal{background:#fff;border-radius:16px;width:100%;max-width:480px;margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:modalIn .22s ease}
@keyframes modalIn{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.bm-head{background:var(--navy);padding:1.1rem 1.4rem;display:flex;align-items:center;justify-content:space-between}
.bm-close{width:30px;height:30px;background:rgba(255,255,255,.1);border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;line-height:1}
.bm-close:hover{background:rgba(255,255,255,.2)}
.bm-body{padding:1.4rem}

.tbl-card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.tbl-hd{background:var(--navy);color:#fff;padding:.9rem 1.25rem;display:flex;align-items:center;justify-content:space-between}
.tbl-hd h3{font-size:.9rem;font-weight:700}
.count-chip{background:rgba(255,255,255,.15);padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700}
.tbl-wrap{overflow-x:auto;max-height:460px;overflow-y:auto}
.tbl-wrap table{width:100%;border-collapse:collapse;font-size:13px}
.tbl-wrap thead th{background:#f8fafc;color:var(--muted);padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--border);position:sticky;top:0;white-space:nowrap}
.tbl-wrap tbody tr:hover{background:#f8fafc}
.tbl-wrap td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.tbl-wrap tbody tr:last-child td{border-bottom:none}
.due-badge{display:inline-block;padding:2px 9px;border-radius:6px;font-size:11px;font-weight:600}
.due-ok  {background:var(--green-lt);color:#15803d}
.due-soon{background:var(--amber-lt);color:#92400e}
.due-late{background:var(--rose-lt);color:#be123c}
.no-data{text-align:center;padding:2.5rem 1rem;color:var(--muted)}

/* ═══════════════════════════════════════════════
   PRINT STYLES — Equipment Borrowing Report
   ═══════════════════════════════════════════════ */
@media print {

  /* Hide everything */
  body * {
    display: none !important;
    visibility: hidden !important;
  }

  /* Show only the print report */
  .print-report,
  .print-report * {
    display: revert !important;
    visibility: visible !important;
  }

  .print-report {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    background: #fff !important;
    color: #000 !important;
    font-family: 'Times New Roman', Times, serif !important;
    font-size: 9pt !important;
    line-height: 1.4 !important;
  }

  @page {
    size: A4 portrait;
    margin: 12mm 12mm 14mm 12mm;
  }

  /* ── Header band ── */
  .print-report .print-header {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
    padding: 8px 0 10px !important;
    margin-bottom: 14px !important;
    border-top: 2.5px solid #000 !important;
    border-bottom: 2.5px solid #000 !important;
  }

  .print-report .print-header-logos {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
  }

  .print-report .print-header-logo {
    display: inline-block !important;
    width: 44px !important;
    height: 44px !important;
    object-fit: contain !important;
  }

  .print-report .print-header-text {
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
    flex: 1 !important;
    padding: 0 10px !important;
    border-left: 1.5px solid #ccc !important;
    border-right: 1.5px solid #ccc !important;
    text-align: center !important;
  }

  .print-report .print-header-text p {
    margin: 0 !important;
    font-size: 8.5pt !important;
  }

  .print-report .brgy-name {
    font-size: 11.5pt !important;
    font-weight: 700 !important;
    color: #000 !important;
    margin-bottom: 2px !important;
  }

  .print-report .doc-title-line {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 5px 0 3px !important;
    justify-content: center !important;
  }

  .print-report .doc-title-line::before,
  .print-report .doc-title-line::after {
    content: '' !important;
    display: block !important;
    flex: 1 !important;
    height: 1px !important;
    background: #000 !important;
  }

  .print-report .doc-title {
    font-size: 13pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .07em !important;
    white-space: nowrap !important;
    color: #000 !important;
  }

  .print-report .doc-sub {
    font-size: 8pt !important;
    color: #555 !important;
  }

  /* ── Two-column summary row ── */
  .print-report .print-body {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    margin-bottom: 14px !important;
    width: 100% !important;
  }

  .print-report .print-col-left,
  .print-report .print-col-right {
    display: block !important;
    flex: 1 1 300px !important;
    min-width: 220px !important;
  }

  /* ── Cards ── */
  .print-report .rbi-card {
    display: block !important;
    border: 1px solid #000 !important;
    border-radius: 0 !important;
    padding: 10px 12px !important;
    margin-bottom: 14px !important;
    page-break-inside: avoid !important;
    box-shadow: none !important;
  }

  .print-report .section-title {
    font-size: 9pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .05em !important;
    color: #000 !important;
    border-bottom: 1.5px solid #000 !important;
    padding-bottom: 4px !important;
    margin: 0 0 10px !important;
  }

  /* ── Tables (shared rules) ── */
  .print-report table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 8pt !important;
    table-layout: fixed !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
  }

  .print-report thead {
    display: table-header-group !important;
  }

  .print-report thead th {
    background: #1e293b !important;
    color: #fff !important;
    font-size: 7.5pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .04em !important;
    padding: 5px 6px !important;
    border: 1px solid #000 !important;
    text-align: left !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .print-report tbody td {
    border: 1px solid #ccc !important;
    padding: 5px 6px !important;
    vertical-align: top !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    white-space: normal !important;
  }

  .print-report tbody tr:nth-child(even) td {
    background: #f8f8f8 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .print-report td.center,
  .print-report th.center {
    text-align: center !important;
  }

  /* ── Inventory overview table (auto-width, not fixed) ── */
  .print-report .overview-table {
    table-layout: auto !important;
  }

  .print-report .overview-table td {
    text-align: center !important;
    font-weight: 700 !important;
    font-size: 11pt !important;
    padding: 8px !important;
  }

  /* ── Borrower summary table column widths ── */
  .print-report .borrower-table colgroup col:nth-child(1) { width: 5% !important; }
  .print-report .borrower-table colgroup col:nth-child(2) { width: 35% !important; }
  .print-report .borrower-table colgroup col:nth-child(3) { width: 10% !important; }
  .print-report .borrower-table colgroup col:nth-child(4) { width: 50% !important; }

  /* ── Detailed records table column widths ── */
  .print-report .detail-table colgroup col:nth-child(1) { width: 5% !important; }
  .print-report .detail-table colgroup col:nth-child(2) { width: 18% !important; }
  .print-report .detail-table colgroup col:nth-child(3) { width: 25% !important; }
  .print-report .detail-table colgroup col:nth-child(4) { width: 22% !important; }
  .print-report .detail-table colgroup col:nth-child(5) { width: 15% !important; }
  .print-report .detail-table colgroup col:nth-child(6) { width: 15% !important; }

  /* ── Footer ── */
  .print-report .foot {
    display: block !important;
    text-align: center !important;
    font-size: 7.5pt !important;
    font-style: italic !important;
    color: #555 !important;
    border-top: 1px solid #000 !important;
    padding-top: 5px !important;
    margin-top: 18px !important;
  }
}
/* end @media print */
</style>
</head>
<body>

<!-- ══ TOPBAR ══════════════════════════════════════════════════════════════════ -->
<header class="topbar">
  <a href="Home.php" class="topbar-brand">
    <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0">
      <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
    </div>
    <div><div class="topbar-name"><?= $_brgy ?></div></div>
  </a>
  <div class="topbar-right">
    <div style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;border:1px solid;
      <?php if($is_captain): ?>background:rgba(245,158,11,.15);color:#fbbf24;border-color:rgba(245,158,11,.3);
      <?php elseif($is_secretary): ?>background:rgba(59,130,246,.15);color:#93c5fd;border-color:rgba(59,130,246,.3);
      <?php else: ?>background:rgba(148,163,184,.15);color:#94a3b8;border-color:rgba(148,163,184,.3);<?php endif; ?>">
      <i class="fas <?= $rbadge['icon'] ?>"></i> <?= $rbadge['label'] ?>
    </div>
    <div class="topbar-time" id="clock"></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>

<!-- ══ PRINTABLE REPORT (hidden on screen, shown when printing) ══════════════ -->
<div class="print-report" style="display:none">

  <!-- Header -->
  <div class="print-header">
    <div class="print-header-logos">
      <img src="images/logo_bagong_pilipinas.png" class="print-header-logo" alt="Bagong Pilipinas">
      <img src="images/lungsod_ng_manila_logo.png" class="print-header-logo" alt="City of Manila">
    </div>
    <div class="print-header-text">
      <p class="doc-sub">Republic of the Philippines &nbsp;·&nbsp; City of Manila</p>
      <p class="brgy-name"><?= htmlspecialchars($_brgy) ?></p>
      <div class="doc-title-line"><span class="doc-title">Equipment Borrowing Report</span></div>
      <p class="doc-sub">Generated: <?= date('F j, Y \a\t g:i A') ?></p>
    </div>
    <div class="print-header-logos">
      <img src="images/brgy410_logo.png" class="print-header-logo" alt="Barangay 410">
    </div>
  </div>

  <!-- Summary row -->
  <div class="print-body">
    <!-- Inventory overview -->
    <div class="print-col-left">
      <div class="rbi-card">
        <div class="section-title">Inventory Overview</div>
        <table class="overview-table">
          <thead>
            <tr>
              <th class="center">Types</th>
              <th class="center">Total Units</th>
              <th class="center">Available</th>
              <th class="center">Borrowed</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= (int)$print_equip_tot['c'] ?></td>
              <td><?= (int)$print_equip_tot['q'] ?></td>
              <td><?= (int)$print_equip_tot['avail'] ?></td>
              <td><?= (int)$borrowed_count ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Borrower summary -->
    <div class="print-col-right">
      <div class="rbi-card">
        <div class="section-title">Borrower Summary</div>
        <table class="borrower-table">
          <colgroup><col><col><col><col></colgroup>
          <thead>
            <tr>
              <th class="center">#</th>
              <th>Borrower</th>
              <th class="center">Count</th>
              <th>Items</th>
            </tr>
          </thead>
          <tbody>
          <?php
          // Re-run query since we may have consumed it earlier
          $ps2 = $conn->query("SELECT eb.borrower_name, eb.borrower_contact, COUNT(*) AS cnt,
            GROUP_CONCAT(CONCAT(e.item_code,' - ',e.item_name) SEPARATOR '; ') AS items
            FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id
            GROUP BY eb.borrower_name, eb.borrower_contact
            ORDER BY cnt DESC");
          if ($ps2 && $ps2->num_rows): $j=1; while($b = $ps2->fetch_assoc()): ?>
            <tr>
              <td class="center"><?= $j ?></td>
              <td><?= htmlspecialchars($b['borrower_name']) ?></td>
              <td class="center"><?= (int)$b['cnt'] ?></td>
              <td><?= htmlspecialchars($b['items']) ?></td>
            </tr>
          <?php $j++; endwhile; else: ?>
            <tr><td colspan="4" style="text-align:center;padding:10px;font-style:italic">No borrowing data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Detailed records table -->
  <div class="rbi-card">
    <div class="section-title">Detailed Borrowing Records</div>
    <table class="detail-table">
      <colgroup><col><col><col><col><col><col></colgroup>
      <thead>
        <tr>
          <th class="center">#</th>
          <th>Borrow Code</th>
          <th>Item</th>
          <th>Borrower</th>
          <th class="center">Borrow Date</th>
          <th class="center">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $pd2 = $conn->query("SELECT eb.*,e.item_name,e.item_code
        FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id
        ORDER BY eb.borrow_date DESC");
      if ($pd2 && $pd2->num_rows): $i=1; while($r = $pd2->fetch_assoc()): ?>
        <tr>
          <td class="center"><?= $i ?></td>
          <td><?= htmlspecialchars($r['borrow_code']) ?></td>
          <td><?= htmlspecialchars($r['item_code'] . ' — ' . $r['item_name']) ?></td>
          <td><?= htmlspecialchars($r['borrower_name']) ?></td>
          <td class="center"><?= htmlspecialchars($r['borrow_date']) ?></td>
          <td class="center"><?= htmlspecialchars($r['status']) ?></td>
        </tr>
      <?php $i++; endwhile; else: ?>
        <tr><td colspan="6" style="text-align:center;padding:12px;font-style:italic">No borrowing records found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="foot">
    ProjectRBI &mdash; <?= htmlspecialchars($_brgy) ?> &nbsp;&middot;&nbsp; Printed <?= date('F j, Y') ?>
  </div>
</div>
<!-- end .print-report -->

<!-- ══ SIDEBAR ═════════════════════════════════════════════════════════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-head-brand">
      <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0">
        <img src="images/brgy410_logo.png" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub"><?= $_brgy ?> · Manila</div></div>
    </div>
    <button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <div style="padding:14px 12px 6px"><button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer"><i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i></button></div>
  <div class="sidebar-section">
    <div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if(!$is_guest): ?>
    <?php if($can_register): ?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register Resident</a><?php endif; ?>
    <?php if($is_captain): ?><a href="manage_accounts.php" class="sidebar-link"><span class="sidebar-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><i class="fas fa-users-gear"></i></span> Manage Accounts</a><?php endif; ?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
    <?php endif; ?>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <?php if(!$is_guest): ?>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link active"><span class="sidebar-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
    <?php endif; ?>
  </div>
  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-logout"><span class="sidebar-logout-icon"><i class="fas fa-right-from-bracket"></i></span> Logout</a>
  </div>
</aside>

<!-- ══ HERO ════════════════════════════════════════════════════════════════════ -->
<div class="page-hero">
  <div style="max-width:1200px;width:100%">
    <h1><i class="fas fa-box-archive" style="font-size:1.6rem;margin-right:.5rem;opacity:.85"></i>Equipment Borrowing</h1>
    <p>Manage barangay equipment inventory, lending requests, and return tracking.</p>
  </div>
</div>

<!-- Tab nav bar -->
<div style="background:#fff;border-bottom:1px solid #e2e8f0;padding:0 2rem">
  <div style="max-width:1200px;margin:0 auto;display:flex;gap:.25rem">
    <a href="#inventory" id="tab-inventory-link" style="display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13px;font-weight:600;color:#3b82f6;border-bottom:2px solid #3b82f6;text-decoration:none"><i class="fas fa-boxes-stacked"></i> Inventory</a>
    <a href="#borrowed"  id="tab-borrowed-link"  style="display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13px;font-weight:600;color:#64748b;border-bottom:2px solid transparent;text-decoration:none"><i class="fas fa-hand-holding"></i> Borrowed (<?= $borrowed_count ?>)</a>
    <a href="#history"   id="tab-history-link"   style="display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13px;font-weight:600;color:#64748b;border-bottom:2px solid transparent;text-decoration:none"><i class="fas fa-clock-rotate-left"></i> History</a>
  </div>
</div>

<!-- ══ MAIN ═════════════════════════════════════════════════════════════════════ -->
<main>
  <?php if ($msg): ?>
  <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:1.25rem">
    <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'triangle-exclamation' ?>"></i>
    <?= $msg ?>
  </div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success" style="margin-bottom:1.25rem"><i class="fas fa-check-circle"></i> Equipment deleted.</div>
  <?php endif; ?>

  <!-- STAT ROW -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-ico blue"><i class="fas fa-boxes-stacked"></i></div>
      <div><div class="stat-lbl">Equipment Types</div><div class="stat-val"><?= $equip_count ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ico green"><i class="fas fa-circle-check"></i></div>
      <div><div class="stat-lbl">Units Available</div><div class="stat-val"><?= $avail_total ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-ico amber"><i class="fas fa-hand-holding"></i></div>
      <div><div class="stat-lbl">Currently Borrowed</div><div class="stat-val"><?= $borrowed_count ?></div></div>
    </div>
  </div>

  <!-- ══ INVENTORY TAB ══ -->
  <div id="tab-inventory">
    <div class="sec-hd">
      <div class="section-label" style="margin-bottom:0">Inventory</div>
      <button class="btn btn-primary btn-sm" onclick="toggleAddPanel()"><i class="fas fa-plus"></i> Add Equipment</button>
    </div>

    <!-- ADD PANEL -->
    <div id="addPanel" class="add-panel">
      <h3><i class="fas fa-plus-circle" style="color:var(--blue)"></i> New Equipment</h3>
      <form method="POST">
        <div class="form-row-4" style="margin-bottom:12px">
          <div class="form-group"><label class="form-group">Item Code <span class="required">*</span></label>
            <input type="text" name="item_code" class="form-control" placeholder="e.g. CHR-001" required></div>
          <div class="form-group"><label class="form-group">Item Name <span class="required">*</span></label>
            <input type="text" name="item_name" class="form-control" placeholder="e.g. Plastic Chair" required></div>
          <div class="form-group"><label class="form-group">Category</label>
            <select name="category" class="form-control">
              <option value="Furniture">Furniture</option>
              <option value="Audio/Visual">Audio/Visual</option>
              <option value="Cleaning">Cleaning</option>
              <option value="Sports">Sports</option>
              <option value="Medical">Medical</option>
              <option value="Office">Office</option>
              <option value="Others">Others</option>
            </select></div>
          <div class="form-group"><label class="form-group">Condition</label>
            <select name="condition_status" class="form-control">
              <option value="Good">Good</option>
              <option value="Fair">Fair</option>
              <option value="Needs Repair">Needs Repair</option>
              <option value="Retired">Retired</option>
            </select></div>
        </div>
        <div class="form-row" style="margin-bottom:12px">
          <div class="form-group"><label class="form-group">Quantity <span class="required">*</span></label>
            <input type="number" name="quantity" class="form-control" min="1" value="1" required></div>
          <div class="form-group"><label class="form-group">Description</label>
            <input type="text" name="description" class="form-control" placeholder="Optional notes"></div>
        </div>
        <button type="submit" name="add_equipment" class="btn btn-success"><i class="fas fa-save"></i> Save Equipment</button>
        <button type="button" class="btn btn-outline" onclick="toggleAddPanel()" style="margin-left:8px">Cancel</button>
      </form>
    </div>

    <!-- EQUIPMENT CARDS -->
    <?php if ($equip_count === 0): ?>
    <div class="no-data" style="background:var(--card);border-radius:14px;border:1px solid var(--border)">
      <i class="fas fa-box-open" style="font-size:2.5rem;color:var(--border);display:block;margin-bottom:10px"></i>
      No equipment yet. Click <strong>Add Equipment</strong> above to get started.
    </div>
    <?php else: ?>
    <div class="equip-grid">
      <?php $equipment_result->data_seek(0); while ($row = $equipment_result->fetch_assoc()):
        $cond    = $row['condition_status'] ?? 'Good';
        $condKey = str_replace(' ', '', $cond);
        $pct     = $row['quantity'] > 0 ? round($row['available'] / $row['quantity'] * 100) : 0;
        $barCol  = $pct > 50 ? '#22c55e' : ($pct > 20 ? '#f59e0b' : '#ef4444');
      ?>
      <div class="equip-card">
        <div class="equip-card-hd">
          <h3><?= htmlspecialchars($row['item_name']) ?></h3>
          <span class="cond-badge cond-<?= $condKey ?>"><i class="fas fa-circle" style="font-size:7px"></i> <?= htmlspecialchars($cond) ?></span>
        </div>
        <div class="equip-meta">
          <i class="fas fa-barcode"></i><?= htmlspecialchars($row['item_code']) ?>
          &bull; <i class="fas fa-tag"></i><?= htmlspecialchars($row['category'] ?? '—') ?>
        </div>
        <?php if (!empty($row['description'])): ?>
        <div class="equip-desc"><?= htmlspecialchars($row['description']) ?></div>
        <?php endif; ?>
        <div class="equip-stock">
          <div class="stock-item"><div class="s-v"><?= $row['quantity'] ?></div><div class="s-l">Total</div></div>
          <div class="stock-item avail"><div class="s-v"><?= $row['available'] ?></div><div class="s-l">Available</div></div>
          <div class="stock-item out"><div class="s-v"><?= $row['quantity'] - $row['available'] ?></div><div class="s-l">Borrowed</div></div>
        </div>
        <div class="stock-bar"><div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $barCol ?>"></div></div>
        <div class="equip-actions">
          <?php if ($row['available'] > 0 && $cond !== 'Retired'): ?>
          <button class="btn btn-primary btn-sm" onclick="openBorrowModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['item_name'])) ?>')"><i class="fas fa-hand-holding"></i> Borrow</button>
          <?php else: ?>
          <span class="btn btn-sm btn-outline" style="cursor:default;pointer-events:none"><i class="fas fa-ban"></i> <?= $cond === 'Retired' ? 'Retired' : 'Unavailable' ?></span>
          <?php endif; ?>
          <?php if ($is_captain): ?>
          <a href="?delete_equipment=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this equipment and all its borrow records?')"><i class="fas fa-trash"></i></a>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ BORROWED TAB ══ -->
  <div id="tab-borrowed" style="display:none">
    <div class="section-label" style="margin-bottom:1rem">Currently Borrowed Items</div>
    <div class="tbl-card">
      <div class="tbl-hd">
        <h3><i class="fas fa-clipboard-list" style="margin-right:7px"></i>Active Borrows</h3>
        <span class="count-chip"><?= $borrowed_count ?></span>
      </div>
      <div class="tbl-wrap">
        <?php if ($borrowed_count > 0): ?>
        <table>
          <thead><tr><th>Borrow Code</th><th>Equipment</th><th>Borrower</th><th>Date Borrowed</th><th>Return By</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php $borrowed_result->data_seek(0); while ($br = $borrowed_result->fetch_assoc()):
            $due   = strtotime($br['return_date']);
            $diff  = ($due - strtotime(date('Y-m-d'))) / 86400;
            $dueCls = $diff < 0 ? 'due-late' : ($diff <= 2 ? 'due-soon' : 'due-ok');
          ?>
          <tr>
            <td><code style="font-size:12px;background:var(--bg);padding:2px 7px;border-radius:5px;border:1px solid var(--border)"><?= htmlspecialchars($br['borrow_code']) ?></code></td>
            <td><div style="font-weight:600"><?= htmlspecialchars($br['item_name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($br['item_code']) ?></div></td>
            <td><?= htmlspecialchars($br['borrower_name']) ?></td>
            <td style="font-size:12px"><?= date('M j, Y', strtotime($br['borrow_date'])) ?></td>
            <td><span class="due-badge <?= $dueCls ?>"><?= date('M j, Y', $due) ?></span></td>
            <td><span class="due-badge <?= $dueCls ?>"><?= $diff < 0 ? 'Overdue' : 'Active' ?></span></td>
            <td><a href="?return_id=<?= $br['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark as returned?')"><i class="fas fa-rotate-left"></i> Return</a></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="no-data"><i class="fas fa-clipboard-check" style="font-size:2rem;color:var(--border);display:block;margin-bottom:8px"></i>No borrowed items at the moment.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ══ HISTORY TAB ══ -->
  <div id="tab-history" style="display:none">
    <div class="section-label" style="margin-bottom:1rem">Return History</div>
    <div class="tbl-card">
      <div class="tbl-hd">
        <h3><i class="fas fa-clock-rotate-left" style="margin-right:7px"></i>Returned Items <span style="font-size:11px;opacity:.6;font-weight:400">(last 50)</span></h3>
      </div>
      <div class="tbl-wrap">
        <?php if ($history_result && $history_result->num_rows > 0): ?>
        <table>
          <thead><tr><th>Code</th><th>Equipment</th><th>Borrower</th><th>Borrowed</th><th>Returned</th></tr></thead>
          <tbody>
          <?php while ($h = $history_result->fetch_assoc()): ?>
          <tr>
            <td><code style="font-size:12px;background:var(--bg);padding:2px 7px;border-radius:5px;border:1px solid var(--border)"><?= htmlspecialchars($h['borrow_code']) ?></code></td>
            <td><?= htmlspecialchars($h['item_name']) ?></td>
            <td><?= htmlspecialchars($h['borrower_name']) ?></td>
            <td style="font-size:12px"><?= date('M j, Y', strtotime($h['borrow_date'])) ?></td>
            <td><span class="due-badge due-ok"><?= $h['actual_return'] ? date('M j, Y', strtotime($h['actual_return'])) : '—' ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="no-data"><i class="fas fa-clock-rotate-left" style="font-size:2rem;color:var(--border);display:block;margin-bottom:8px"></i>No return history yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</main>

<footer style="background:var(--navy);color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem 2rem;letter-spacing:.02em">
  &copy; <?= date('Y') ?> ProjectRBI – <?= $_brgy ?> Census Management System · Manila City
</footer>

<!-- Settings Drawer -->
<div id="settingsOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100" onclick="closeSettings()"></div>
<div id="settingsDrawer" style="position:fixed;top:0;right:-360px;width:340px;height:100vh;background:#0f172a;z-index:1101;transition:right .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.08)">
  <div style="padding:20px 20px 14px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#3b82f6,#14b8a6);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-gear" style="color:#fff;font-size:13px"></i></div>
      <div><div style="font-family:Syne,sans-serif;font-size:14px;font-weight:800;color:#fff">Settings</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Equipment Borrowing</div></div>
    </div>
    <button onclick="closeSettings()" style="width:28px;height:28px;background:rgba(255,255,255,.08);border:none;border-radius:7px;color:rgba(255,255,255,.6);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-times"></i></button>
  </div>
  <div style="flex:1;overflow-y:auto;padding:16px">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:8px">Actions</div>
    <button onclick="printEquip()" style="width:100%;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:none;border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer">
      <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-print" style="color:#94a3b8;font-size:13px"></i></div>
      <div style="text-align:left"><div style="font-size:13px;font-weight:600;color:#fff">Print Inventory</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Print the equipment borrowing report</div></div>
    </button>
  </div>
  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.07)">
    <a href="logout.php" style="display:flex;align-items:center;gap:10px;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.25);border-radius:10px;padding:12px 14px;text-decoration:none">
      <div style="width:30px;height:30px;background:rgba(244,63,94,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-right-from-bracket" style="color:#f43f5e;font-size:13px"></i></div>
      <div><div style="font-size:13px;font-weight:700;color:#f43f5e">Logout</div><div style="font-size:11px;color:rgba(244,63,94,.6)">End your session</div></div>
    </a>
  </div>
</div>

<!-- ══ BORROW MODAL ═════════════════════════════════════════════════════════════ -->
<div class="borrow-modal-overlay" id="borrowModalOverlay" onclick="closeBorrowModal()">
  <div class="borrow-modal" onclick="event.stopPropagation()">
    <div class="bm-head">
      <div>
        <div style="font-size:.95rem;font-weight:700;color:#fff"><i class="fas fa-hand-holding" style="margin-right:8px;opacity:.8"></i>Borrow Equipment</div>
        <div id="borrowModalEquipName" style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px"></div>
      </div>
      <button class="bm-close" onclick="closeBorrowModal()">&times;</button>
    </div>
    <div class="bm-body">
      <form method="POST">
        <input type="hidden" name="equipment_id" id="borrowEquipId">
        <div style="margin-bottom:12px">
          <label style="font-size:11px;font-weight:700;color:var(--muted);display:block;margin-bottom:4px">Borrower Name</label>
          <input type="text" name="borrower_name" id="borrowerNameInput" list="modal_borrowers"
                 class="form-control" placeholder="Search resident or senior…" autocomplete="off" required>
          <datalist id="modal_borrowers">
            <?php foreach ($seniors_list as $sn): ?><option value="<?= htmlspecialchars($sn) ?>"><?php endforeach; ?>
            <?php foreach ($residents_list as $rn): ?><option value="<?= htmlspecialchars($rn) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--muted);display:block;margin-bottom:4px">Purpose</label>
            <select name="purpose" id="modalPurpose" class="form-control" onchange="toggleModalOther()">
              <option value="Birthday">Birthday</option>
              <option value="Event">Event</option>
              <option value="Meeting">Meeting</option>
              <option value="Others">Others</option>
            </select>
          </div>
          <div id="modalOtherWrap" style="display:none">
            <label style="font-size:11px;font-weight:700;color:var(--muted);display:block;margin-bottom:4px">Specify</label>
            <input type="text" name="other_purpose" class="form-control" placeholder="Specify purpose">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:1.25rem">
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--muted);display:block;margin-bottom:4px">Borrow Date</label>
            <input type="date" name="borrow_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--muted);display:block;margin-bottom:4px">Return By</label>
            <input type="date" name="return_date" class="form-control" required>
          </div>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" name="borrow_submit" class="btn btn-success" style="flex:1"><i class="fas fa-check"></i> Confirm Borrow</button>
          <button type="button" class="btn btn-outline" onclick="closeBorrowModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Clock
function tc(){const n=new Date();document.getElementById('clock').textContent=n.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})+' '+n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});}tc();setInterval(tc,1000);

// Sidebar
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);

// Settings drawer
function openSettings(){document.getElementById('settingsOverlay').style.display='block';document.getElementById('settingsDrawer').style.right='0';document.body.style.overflow='hidden';closeSidebar();}
function closeSettings(){document.getElementById('settingsOverlay').style.display='none';document.getElementById('settingsDrawer').style.right='-360px';document.body.style.overflow='';}

// Print — show the report div first, then call window.print(), then hide again
function printEquip(){
  closeSettings();
  var el = document.querySelector('.print-report');
  el.style.display = 'block';
  setTimeout(function(){
    window.print();
    setTimeout(function(){ el.style.display = 'none'; }, 500);
  }, 350);
}

document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSettings();closeSidebar();closeBorrowModal();}});

// Tab switching
const tabs  = { inventory: document.getElementById('tab-inventory'), borrowed: document.getElementById('tab-borrowed'), history: document.getElementById('tab-history') };
const links = { inventory: document.getElementById('tab-inventory-link'), borrowed: document.getElementById('tab-borrowed-link'), history: document.getElementById('tab-history-link') };
const activeStyle   = 'display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13px;font-weight:600;text-decoration:none;color:#3b82f6;border-bottom:2px solid #3b82f6;';
const inactiveStyle = 'display:inline-flex;align-items:center;gap:7px;padding:14px 18px;font-size:13px;font-weight:600;text-decoration:none;color:#64748b;border-bottom:2px solid transparent;';
function showTab(name){
  Object.keys(tabs).forEach(k=>{
    tabs[k].style.display  = k===name ? 'block' : 'none';
    links[k].style.cssText = k===name ? activeStyle : inactiveStyle;
  });
  location.hash = name;
}
(function(){ const h=location.hash.replace('#',''); showTab(h in tabs ? h : 'inventory'); })();
Object.keys(links).forEach(k=>links[k].addEventListener('click',e=>{e.preventDefault();showTab(k);}));

// Add panel
function toggleAddPanel(){const p=document.getElementById('addPanel');p.style.display=p.style.display==='block'?'none':'block';}

// Borrow modal
function openBorrowModal(id,name){
  document.getElementById('borrowEquipId').value=id;
  document.getElementById('borrowModalEquipName').textContent=name;
  document.getElementById('borrowerNameInput').value='';
  document.getElementById('modalPurpose').value='Birthday';
  document.getElementById('modalOtherWrap').style.display='none';
  document.getElementById('borrowModalOverlay').classList.add('open');
  document.body.style.overflow='hidden';
  setTimeout(()=>document.getElementById('borrowerNameInput').focus(),120);
}
function closeBorrowModal(){document.getElementById('borrowModalOverlay').classList.remove('open');document.body.style.overflow='';}
function toggleModalOther(){document.getElementById('modalOtherWrap').style.display=document.getElementById('modalPurpose').value==='Others'?'block':'none';}
</script>
</body>
</html>