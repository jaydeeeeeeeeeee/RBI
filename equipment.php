<?php
session_start();
if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
include 'role_helper.php';
if($is_guest){header("Location: Home.php?denied=equipment");exit();}
include 'Residents_DB.php';

$msg=''; $err='';

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS equipment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(30) NOT NULL UNIQUE,
  item_name VARCHAR(200) NOT NULL,
  category ENUM('Audio/Visual','Furniture','Cleaning','Sports','Medical','Office','Others') DEFAULT 'Others',
  description TEXT,
  quantity INT DEFAULT 1,
  available INT DEFAULT 1,
  condition_status ENUM('Good','Fair','Needs Repair','Retired') DEFAULT 'Good',
  added_by VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$conn->query("CREATE TABLE IF NOT EXISTS equipment_borrowing (
  id INT AUTO_INCREMENT PRIMARY KEY,
  borrow_code VARCHAR(30) NOT NULL UNIQUE,
  equipment_id INT NOT NULL,
  senior_citizen_id INT DEFAULT NULL,
  borrower_name VARCHAR(200) NOT NULL,
  borrower_contact VARCHAR(50),
  purpose TEXT,
  borrow_date DATE NOT NULL,
  return_date DATE NOT NULL,
  actual_return DATE DEFAULT NULL,
  status ENUM('Pending','Approved','Returned','Overdue','Rejected') DEFAULT 'Pending',
  approved_by VARCHAR(100),
  remarks TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipment_id) REFERENCES equipment(id)
) ENGINE=InnoDB");
$conn->query("ALTER TABLE equipment_borrowing ADD COLUMN IF NOT EXISTS senior_citizen_id INT DEFAULT NULL AFTER equipment_id");

// Handle POST actions
if($_SERVER['REQUEST_METHOD']==='POST' && $can_edit){
  csrf_verify();
  $action=$_POST['action']??'';

  if($action==='add_item'){
    $code='EQP-'.strtoupper(substr(uniqid(),-5));
    $name=mysqli_real_escape_string($conn,trim($_POST['item_name']??''));
    $cat =mysqli_real_escape_string($conn,$_POST['category']??'Others');
    $desc=mysqli_real_escape_string($conn,trim($_POST['description']??''));
    $qty =(int)($_POST['quantity']??1);
    $cond=mysqli_real_escape_string($conn,$_POST['condition_status']??'Good');
    $by  =mysqli_real_escape_string($conn,$_SESSION['full_name']??$_SESSION['admin']);
    if(empty($name)){$err="Item name is required.";}
    else{
      $conn->query("INSERT INTO equipment (item_code,item_name,category,description,quantity,available,condition_status,added_by) VALUES ('$code','$name','$cat','$desc',$qty,$qty,'$cond','$by')");
      $msg="Equipment '$name' added (Code: $code).";
    }
  }
  elseif($action==='borrow'){
    $eid=(int)($_POST['equipment_id']??0);
    $scid=(int)($_POST['senior_citizen_id']??0);
    $bc='BRW-'.date('Y').'-'.str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
    // If a senior citizen is selected, pull name/contact from the registry
    if($scid>0){
      $scrow=$conn->query("SELECT first_name,last_name,contact_number FROM senior_citizens WHERE id=$scid LIMIT 1")->fetch_assoc();
      $borrower = $scrow ? mysqli_real_escape_string($conn,trim($scrow['first_name'].' '.$scrow['last_name'])) : '';
      $contact  = $scrow ? mysqli_real_escape_string($conn,$scrow['contact_number']??'') : '';
    } else {
      $borrower=mysqli_real_escape_string($conn,trim($_POST['borrower_name']??''));
      $contact =mysqli_real_escape_string($conn,trim($_POST['borrower_contact']??''));
      $scid=0;
    }
    $purpose =mysqli_real_escape_string($conn,trim($_POST['purpose']??''));
    $bd=mysqli_real_escape_string($conn,$_POST['borrow_date']??date('Y-m-d'));
    $rd=mysqli_real_escape_string($conn,$_POST['return_date']??date('Y-m-d',strtotime('+7 days')));
    if(empty($borrower)||!$eid){$err="Borrower and equipment are required.";}
    else{
      $avail=$conn->query("SELECT available FROM equipment WHERE id=$eid")->fetch_assoc()['available']??0;
      if($avail<1){$err="Equipment is not available for borrowing.";}
      else{
        $scid_sql = $scid>0 ? $scid : 'NULL';
        $conn->query("INSERT INTO equipment_borrowing (borrow_code,equipment_id,senior_citizen_id,borrower_name,borrower_contact,purpose,borrow_date,return_date,status) VALUES ('$bc',$eid,$scid_sql,'$borrower','$contact','$purpose','$bd','$rd','Pending')");
        $msg="Borrow request $bc filed.";
      }
    }
  }
  elseif($action==='update_borrow'){
    $bid=(int)($_POST['bid']??0);
    $status=mysqli_real_escape_string($conn,$_POST['status']??'');
    $remarks=mysqli_real_escape_string($conn,trim($_POST['remarks']??''));
    $by=mysqli_real_escape_string($conn,$_SESSION['full_name']??$_SESSION['admin']);
    if($status==='Approved'){
      $conn->query("UPDATE equipment_borrowing SET status='Approved',approved_by='$by',remarks='$remarks' WHERE id=$bid");
      // Decrease available count
      $conn->query("UPDATE equipment e JOIN equipment_borrowing eb ON e.id=eb.equipment_id SET e.available=e.available-1 WHERE eb.id=$bid AND e.available>0");
    } elseif($status==='Returned'){
      $conn->query("UPDATE equipment_borrowing SET status='Returned',actual_return=CURDATE(),remarks='$remarks' WHERE id=$bid");
      $conn->query("UPDATE equipment e JOIN equipment_borrowing eb ON e.id=eb.equipment_id SET e.available=e.available+1 WHERE eb.id=$bid");
    } else {
      $conn->query("UPDATE equipment_borrowing SET status='$status',remarks='$remarks' WHERE id=$bid");
    }
    $msg="Borrow record updated.";
  }
}

// Fetch data
$sc_list = $conn->query("SELECT id,first_name,last_name,contact_number FROM senior_citizens WHERE status='Active' ORDER BY last_name,first_name");
$equipment_list = $conn->query("SELECT * FROM equipment ORDER BY item_name");
$borrows = $conn->query("SELECT eb.*,e.item_name,e.item_code,sc.id AS sc_id FROM equipment_borrowing eb JOIN equipment e ON eb.equipment_id=e.id LEFT JOIN senior_citizens sc ON eb.senior_citizen_id=sc.id ORDER BY eb.created_at DESC LIMIT 50");
$total_eq  = $conn->query("SELECT COUNT(*) AS c FROM equipment")->fetch_assoc()['c'];
$total_bor = $conn->query("SELECT COUNT(*) AS c FROM equipment_borrowing")->fetch_assoc()['c'];
$pending   = $conn->query("SELECT COUNT(*) AS c FROM equipment_borrowing WHERE status='Pending'")->fetch_assoc()['c'];
$overdue   = $conn->query("SELECT COUNT(*) AS c FROM equipment_borrowing WHERE status='Approved' AND return_date < CURDATE()")->fetch_assoc()['c'];
// Mark overdue
$conn->query("UPDATE equipment_borrowing SET status='Overdue' WHERE status='Approved' AND return_date < CURDATE()");

$status_colors=['Pending'=>'#f59e0b','Approved'=>'#3b82f6','Returned'=>'#22c55e','Overdue'=>'#f43f5e','Rejected'=>'#94a3b8'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Equipment Borrowing – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="assets/css/main.css"/>
<style>
.page-top{background:#0f172a;padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.page-top h1{color:#fff;font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800}
.page-top p{color:rgba(255,255,255,.45);font-size:12px;margin-top:2px}
main{padding:1.5rem;max-width:1100px;margin:0 auto}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.25rem}
@media(max-width:600px){.stat-row{grid-template-columns:1fr 1fr}}
.scard{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem;text-align:center}
.scard-num{font-size:22px;font-weight:700;line-height:1}
.scard-lbl{font-size:11px;color:#64748b;margin-top:3px}
.tabs{display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:1.25rem;gap:4px}
.tab{padding:8px 16px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s;background:none;border-top:none;border-left:none;border-right:none;font-family:'Inter',sans-serif}
.tab.active{color:#3b82f6;border-bottom-color:#3b82f6}
.tab-content{display:none}.tab-content.active{display:block}
.eq-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-bottom:1rem}
.eq-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;position:relative}
.eq-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.07)}
.eq-name{font-size:14px;font-weight:700;color:#0f172a;margin-bottom:3px}
.eq-code{font-size:10px;font-family:monospace;color:#64748b;background:#f1f5f9;padding:2px 7px;border-radius:20px}
.eq-avail{position:absolute;top:1rem;right:1rem;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px}
.avail-ok{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.avail-no{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
.brow-row{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:8px;display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap}
.brow-row:hover{background:#f8fafc}
.status-pill{font-size:10px;font-weight:700;padding:2px 10px;border-radius:20px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;padding:2rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto}
.modal-box h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#0f172a;margin-bottom:1.25rem}
.fg{margin-bottom:12px}
.fg label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px}
.fg input,.fg select,.fg textarea{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#3b82f6}
footer{background:#0f172a;color:rgba(255,255,255,.3);font-size:11px;text-align:center;padding:1.25rem;margin-top:2rem}
</style>
</head>
<body>
<header class="topbar" style="gap:12px">
  <a href="Home.php" class="topbar-brand" style="flex-shrink:0"><div class="topbar-logo">410</div><div><div class="topbar-name">Barangay 410</div><div class="topbar-sub">Equipment Borrowing</div></div></a>
  <div style="display:flex;align-items:center;border-left:1px solid rgba(255,255,255,.12);padding-left:14px;min-width:0">
    <span style="font-size:13px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;white-space:nowrap"><i class="fas fa-box-archive" style="opacity:.8;margin-right:5px"></i> Equipment Borrowing</span>
  </div>
  <div class="topbar-right" style="margin-left:auto">
    <div title="<?=$rbadge['label']?>" style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;<?php if($is_captain):?>background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);<?php elseif($is_secretary):?>background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);<?php else:?>background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);<?php endif;?>"><i class="fas <?=$rbadge['icon']?>" style="font-size:12px"></i></div>
    <button class="menu-toggle" id="menuToggle"><span></span><span></span><span></span></button>
  </div>
</header>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head"><div class="sidebar-head-brand"><div class="sidebar-head-logo">410</div><div><div class="sidebar-head-title">ProjectRBI</div><div class="sidebar-head-sub">Barangay 410 · Manila</div></div></div><button class="sidebar-close-btn" onclick="closeSidebar()"><i class="fas fa-times"></i></button></div>
  <div style="padding:14px 12px 6px"><button onclick="openSettings()" class="sidebar-settings-btn" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.2);color:#93c5fd;font-family:Inter,sans-serif;font-size:13px;font-weight:600;cursor:pointer"><i class="fas fa-gear"></i> Settings & More<i class="fas fa-arrow-right" style="margin-left:auto;font-size:10px;opacity:.6"></i></button></div>
  <div class="sidebar-section"><div class="sidebar-label">Main</div>
    <a href="Home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-house"></i></span> Dashboard</a>
    <?php if($can_register):?><a href="Register.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-user-plus"></i></span> Register</a><?php endif;?>
    <a href="Display_List.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Residents</a>
  </div>
  <div class="sidebar-section"><div class="sidebar-label">Modules</div>
    <a href="RBI.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-clipboard-list"></i></span> RBI Report</a>
    <a href="data_tracking.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-database"></i></span> Document Tracking</a>
    <a href="../eBlotter/eblotter_home.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-shield-halved"></i></span> E-Blotter</a>
    <a href="equipment.php" class="sidebar-link active"><span class="sidebar-icon"><i class="fas fa-box-archive"></i></span> Equipment</a>
    <a href="senior_citizen.php" class="sidebar-link"><span class="sidebar-icon"><i class="fas fa-person-cane"></i></span> Senior Citizens</a>
  </div>
  <div class="sidebar-footer"></div>
</aside>

<div class="page-top">
  <div><h1><i class="fas fa-box-archive" style="margin-right:8px;opacity:.7"></i>Equipment Borrowing</h1><p>Barangay 410 · Equipment inventory and borrow tracking</p></div>
  <?php if($can_edit):?>
  <div style="display:flex;gap:8px">
    <button class="btn btn-outline" style="color:#fff;border-color:rgba(255,255,255,.2)" onclick="document.getElementById('addItemModal').classList.add('open')"><i class="fas fa-plus"></i> Add Equipment</button>
    <button class="btn btn-primary" onclick="document.getElementById('borrowModal').classList.add('open')"><i class="fas fa-hand-holding"></i> File Borrow</button>
  </div>
  <?php endif;?>
</div>

<main>
  <?php if($msg):?><div class="alert alert-success" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif;?>
  <?php if($err):?><div class="alert alert-error" style="margin-bottom:1rem"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($err)?></div><?php endif;?>

  <div class="stat-row">
    <div class="scard"><div class="scard-num" style="color:#0f172a"><?=$total_eq?></div><div class="scard-lbl">Equipment Items</div></div>
    <div class="scard"><div class="scard-num" style="color:#3b82f6"><?=$total_bor?></div><div class="scard-lbl">Total Borrows</div></div>
    <div class="scard"><div class="scard-num" style="color:#f59e0b"><?=$pending?></div><div class="scard-lbl">Pending Approval</div></div>
    <div class="scard"><div class="scard-num" style="color:#f43f5e"><?=$overdue?></div><div class="scard-lbl">Overdue Returns</div></div>
  </div>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('inventory',this)"><i class="fas fa-box-archive"></i> Inventory</button>
    <button class="tab" onclick="switchTab('borrows',this)"><i class="fas fa-hand-holding"></i> Borrow Records</button>
  </div>

  <!-- INVENTORY TAB -->
  <div id="tab-inventory" class="tab-content active">
    <?php
    $equipment_list->data_seek(0);
    if($equipment_list->num_rows===0):?>
    <div style="text-align:center;padding:3rem;color:#94a3b8"><i class="fas fa-box-open" style="font-size:48px;opacity:.2;display:block;margin-bottom:12px"></i><p>No equipment added yet.</p></div>
    <?php else:?>
    <div class="eq-grid">
    <?php while($item=$equipment_list->fetch_assoc()):?>
      <div class="eq-card">
        <span class="eq-avail <?=$item['available']>0?'avail-ok':'avail-no'?>"><?=$item['available']>0?$item['available'].' Available':'Not Available'?></span>
        <div class="eq-name"><?=htmlspecialchars($item['item_name'])?></div>
        <div style="margin:4px 0"><span class="eq-code"><?=htmlspecialchars($item['item_code'])?></span></div>
        <div style="font-size:12px;color:#64748b;margin-top:6px"><?=htmlspecialchars($item['category'])?> · <?=htmlspecialchars($item['condition_status'])?></div>
        <div style="font-size:12px;color:#94a3b8;margin-top:2px">Qty: <?=$item['quantity']?> · Available: <?=$item['available']?></div>
        <?php if($item['description']):?><div style="font-size:12px;color:#64748b;margin-top:6px;line-height:1.4"><?=htmlspecialchars($item['description'])?></div><?php endif;?>
      </div>
    <?php endwhile;?>
    </div>
    <?php endif;?>
  </div>

  <!-- BORROWS TAB -->
  <div id="tab-borrows" class="tab-content">
    <?php
    $borrows->data_seek(0);
    if($borrows->num_rows===0):?>
    <div style="text-align:center;padding:3rem;color:#94a3b8"><i class="fas fa-hand-holding" style="font-size:48px;opacity:.2;display:block;margin-bottom:12px"></i><p>No borrow records yet.</p></div>
    <?php else: while($brow=$borrows->fetch_assoc()):
      $sc=$status_colors[$brow['status']]??'#94a3b8';
    ?>
    <div class="brow-row">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
          <span style="font-size:11px;font-family:monospace;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:20px"><?=htmlspecialchars($brow['borrow_code'])?></span>
          <span class="status-pill" style="background:<?=$sc?>22;color:<?=$sc?>;border:1px solid <?=$sc?>44"><?=$brow['status']?></span>
        </div>
        <div style="font-size:13px;font-weight:700;color:#0f172a"><?=htmlspecialchars($brow['item_name'])?></div>
        <div style="font-size:12px;color:#64748b;margin-top:3px">
          <i class="fas fa-user"></i> <?=htmlspecialchars($brow['borrower_name'])?>
          <?php if($brow['sc_id']):?><span style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:20px;font-size:10px;font-weight:700;padding:1px 7px;margin-left:5px"><i class="fas fa-person-cane"></i> Senior Citizen</span><?php endif;?>
          <?php if($brow['borrower_contact']):?> · <i class="fas fa-phone"></i> <?=htmlspecialchars($brow['borrower_contact'])?><?php endif;?>
        </div>
        <div style="font-size:11px;color:#94a3b8;margin-top:3px">
          Borrow: <?=date('M d, Y',strtotime($brow['borrow_date']))?> → Return: <?=date('M d, Y',strtotime($brow['return_date']))?>
          <?php if($brow['actual_return']):?> · <span style="color:#22c55e">Returned: <?=date('M d, Y',strtotime($brow['actual_return']))?></span><?php endif;?>
        </div>
      </div>
      <?php if($can_edit && in_array($brow['status'],['Pending','Approved','Overdue'])):?>
      <button class="btn btn-outline btn-sm" onclick="openUpdateBorrow(<?=$brow['id']?>,<?=json_encode($brow['status'])?>)"><i class="fas fa-pen"></i> Update</button>
      <?php endif;?>
    </div>
    <?php endwhile; endif;?>
  </div>
</main>
<footer>&copy; <?=date('Y')?> ProjectRBI – Barangay 410 Census Management System · Manila City</footer>

<?php if($can_edit):?>
<!-- ADD ITEM MODAL -->
<div class="modal-overlay" id="addItemModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <h3><i class="fas fa-box-archive" style="color:#3b82f6;margin-right:8px"></i>Add Equipment</h3>
    <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="add_item">
      <div class="fg"><label>Item Name *</label><input type="text" name="item_name" placeholder="e.g. Megaphone, Folding Table" required></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="fg"><label>Category</label>
          <select name="category"><option>Audio/Visual</option><option>Furniture</option><option>Cleaning</option><option>Sports</option><option>Medical</option><option>Office</option><option>Others</option></select>
        </div>
        <div class="fg"><label>Quantity</label><input type="number" name="quantity" value="1" min="1"></div>
      </div>
      <div class="fg"><label>Condition</label>
        <select name="condition_status"><option>Good</option><option>Fair</option><option>Needs Repair</option><option>Retired</option></select>
      </div>
      <div class="fg"><label>Description</label><textarea name="description" rows="2" placeholder="Optional notes..."></textarea></div>
      <div style="display:flex;gap:10px;margin-top:.5rem">
        <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('addItemModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-save"></i> Add Item</button>
      </div>
    </form>
  </div>
</div>

<!-- BORROW MODAL -->
<div class="modal-overlay" id="borrowModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <h3><i class="fas fa-hand-holding" style="color:#3b82f6;margin-right:8px"></i>File Borrow Request</h3>
    <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="borrow">
      <div class="fg"><label>Equipment *</label>
        <select name="equipment_id" required>
          <option value="">-- Select equipment --</option>
          <?php $equipment_list->data_seek(0); while($item=$equipment_list->fetch_assoc()):?>
          <option value="<?=$item['id']?>" <?=$item['available']<1?'disabled':''?>><?=htmlspecialchars($item['item_name'])?> (<?=$item['available']?> avail.)</option>
          <?php endwhile;?>
        </select>
      </div>
      <div class="fg">
        <label>Borrower (Senior Citizen) *</label>
        <select name="senior_citizen_id" id="scSelect" onchange="fillContact(this)" required>
          <option value="">-- Select senior citizen --</option>
          <?php if($sc_list && $sc_list->num_rows>0): $sc_list->data_seek(0); while($sc=$sc_list->fetch_assoc()): ?>
          <option value="<?=$sc['id']?>" data-contact="<?=htmlspecialchars($sc['contact_number']??'')?>">
            <?=htmlspecialchars($sc['last_name'].', '.$sc['first_name'])?>
          </option>
          <?php endwhile; endif; ?>
        </select>
      </div>
      <div class="fg"><label>Contact No.</label><input type="text" name="borrower_contact" id="scContact" placeholder="Auto-filled from registry"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="fg"><label>Borrow Date *</label><input type="date" name="borrow_date" value="<?=date('Y-m-d')?>" required></div>
        <div class="fg"><label>Return Date *</label><input type="date" name="return_date" value="<?=date('Y-m-d',strtotime('+7 days'))?>" required></div>
      </div>
      <div class="fg"><label>Purpose</label><textarea name="purpose" rows="2" placeholder="Purpose of borrowing..."></textarea></div>
      <div style="display:flex;gap:10px;margin-top:.5rem">
        <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('borrowModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-save"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- UPDATE BORROW MODAL -->
<div class="modal-overlay" id="updateBorrowModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <h3><i class="fas fa-pen" style="color:#3b82f6;margin-right:8px"></i>Update Borrow Status</h3>
    <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="update_borrow"><input type="hidden" name="bid" id="updateBorrowId">
      <div class="fg"><label>New Status</label>
        <select name="status" id="updateBorrowStatus">
          <option>Pending</option><option>Approved</option><option>Returned</option><option>Rejected</option>
        </select>
      </div>
      <div class="fg"><label>Remarks / Notes</label><textarea name="remarks" id="updateBorrowRemarks" rows="3" placeholder="Optional notes..."></textarea></div>
      <div style="display:flex;gap:10px;margin-top:.5rem">
        <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('updateBorrowModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('menuToggle').addEventListener('click',openSidebar);
function openSettings(){}
function switchTab(t,btn){
  document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+t).classList.add('active');
  btn.classList.add('active');
}
function fillContact(sel){
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('scContact').value = opt ? (opt.dataset.contact||'') : '';
}
function openUpdateBorrow(id,status){
  document.getElementById('updateBorrowId').value=id;
  document.getElementById('updateBorrowStatus').value=status;
  document.getElementById('updateBorrowRemarks').value='';
  document.getElementById('updateBorrowModal').classList.add('open');
}
</script>
</body>
</html>