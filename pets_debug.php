<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin.php"); exit(); }
include 'Residents_DB.php';

$pets_raw = $conn->query("SELECT id, resident_id, pet_name, pet_type, pet_sex, pet_color, pet_age FROM pets ORDER BY id DESC LIMIT 50");
$summary  = $conn->query("SELECT pet_type, COUNT(*) AS c FROM pets GROUP BY pet_type ORDER BY c DESC");
$total    = (int)$conn->query("SELECT COUNT(*) AS t FROM pets")->fetch_assoc()['t'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8">
<title>Pets Debug – ProjectRBI</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,sans-serif;background:#f1f5f9;padding:2rem;color:#0f172a}
h2{margin-bottom:1rem}
.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1.5rem;margin-bottom:1.5rem}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#f8fafc;padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0}
td{padding:9px 12px;border-bottom:1px solid #f1f5f9}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#dbeafe;color:#1e40af}
.num{font-size:2rem;font-weight:800;color:#3b82f6}
a{color:#3b82f6;text-decoration:none;font-size:13px}
</style>
</head><body>

<h2>Pets Database Debug</h2>

<div class="card">
  <p>Total pets in <code>pets</code> table: <span class="num"><?= $total ?></span></p>
  <?php if ($total === 0): ?>
    <p style="color:#ef4444;font-weight:600">⚠ No pets found. Pets registered through the form may not be saving correctly.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-bottom:.75rem">Summary by pet_type</h3>
  <table>
    <thead><tr><th>pet_type (stored value)</th><th>Count</th></tr></thead>
    <tbody>
    <?php if ($summary && $summary->num_rows > 0): while ($r = $summary->fetch_assoc()): ?>
      <tr><td><span class="badge"><?= htmlspecialchars($r['pet_type'] ?: '(empty)') ?></span></td><td><?= $r['c'] ?></td></tr>
    <?php endwhile; else: ?>
      <tr><td colspan="2" style="color:#94a3b8">No data</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 style="margin-bottom:.75rem">Last 50 pet records</h3>
  <table>
    <thead><tr><th>#</th><th>Resident ID</th><th>Pet Name</th><th>Type</th><th>Sex</th><th>Color</th><th>Age</th></tr></thead>
    <tbody>
    <?php if ($pets_raw && $pets_raw->num_rows > 0): while ($p = $pets_raw->fetch_assoc()): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= $p['resident_id'] ?></td>
        <td><?= htmlspecialchars($p['pet_name']) ?></td>
        <td><span class="badge"><?= htmlspecialchars($p['pet_type'] ?: '(empty)') ?></span></td>
        <td><?= htmlspecialchars($p['pet_sex']) ?></td>
        <td><?= htmlspecialchars($p['pet_color']) ?></td>
        <td><?= htmlspecialchars($p['pet_age']) ?></td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="7" style="color:#94a3b8;text-align:center;padding:2rem">No pet records found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<a href="Home.php">← Back to Dashboard</a>
</body></html>
