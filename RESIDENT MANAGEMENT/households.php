<?php
// admin/households.php - Main Household Categories (All White Theme)
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

// Get all categories (distinct household_name)
$categories = $pdo->query("
    SELECT DISTINCT household_name 
    FROM residents 
    WHERE household_name IS NOT NULL 
    ORDER BY household_name
")->fetchAll();

$total_residents = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
$total_households = $pdo->query("SELECT COUNT(DISTINCT address) FROM residents WHERE address IS NOT NULL")->fetchColumn();

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-dark"><i class="fas fa-folder-tree me-2"></i>Households (RBI)</h1>
        <a href="household_category_add.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Category
        </a>
    </div>
    
    <!-- STATISTICS CARDS -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white border-0 shadow">
                <div class="card-body">
                    <h5>Total Categories</h5>
                    <h2><?= count($categories) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white border-0 shadow">
                <div class="card-body">
                    <h5>Total Households</h5>
                    <h2><?= $total_households ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white border-0 shadow">
                <div class="card-body">
                    <h5>Total Residents</h5>
                    <h2><?= $total_residents ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- CATEGORIES WITH THEIR HOUSEHOLDS -->
    <?php if (empty($categories)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 bg-white">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h4 class="text-dark">No Categories Yet</h4>
                <p class="text-muted">Click "Add New Category" to start recording RBI data.</p>
                <a href="household_category_add.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus me-2"></i>Create Category
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $cat): 
            // Get all households under this category
            $households = $pdo->prepare("
                SELECT address, COUNT(*) as member_count
                FROM residents 
                WHERE household_name = ? 
                GROUP BY address
                ORDER BY address
            ");
            $households->execute([$cat['household_name']]);
            $household_list = $households->fetchAll();
            
            // Calculate total members in this category
            $total_members = array_sum(array_column($household_list, 'member_count'));
        ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom" style="cursor: pointer;" onclick="toggleCategory(this)">
                <div class="d-flex justify-content-between align-items-center py-2">
                    <div>
                        <i class="fas fa-folder text-warning me-2"></i>
                        <strong class="text-dark"><?= htmlspecialchars($cat['household_name']) ?></strong>
                        <span class="badge bg-info ms-2"><?= count($household_list) ?> households</span>
                        <span class="badge bg-success ms-1"><?= $total_members ?> residents</span>
                    </div>
                    <i class="fas fa-chevron-down toggle-icon text-muted"></i>
                </div>
            </div>
            <div class="card-body category-content bg-white" style="display: none;">
                <?php if (empty($household_list)): ?>
                    <div class="alert alert-info text-center">
                        No households yet. Click "Add Household" to add.
                    </div>
                <?php else: ?>
                    <?php foreach ($household_list as $index => $h): 
                        // Get members of this household
                        $members = $pdo->prepare("
                            SELECT * FROM residents 
                            WHERE household_name = ? AND address = ?
                            ORDER BY is_head DESC
                        ");
                        $members->execute([$cat['household_name'], $h['address']]);
                        $member_list = $members->fetchAll();
                    ?>
                    <div class="card mb-3 border">
                        <div class="card-header bg-light border-bottom" style="cursor: pointer;" onclick="toggleHousehold(this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-home text-primary me-2"></i>
                                    <strong class="text-dark">Household <?= $index + 1 ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= $h['member_count'] ?> members</span>
                                </div>
                                <div>
                                    <a href="household_add.php?category=<?= urlencode($cat['household_name']) ?>" class="btn btn-sm btn-success me-2" onclick="event.stopPropagation()">
                                        <i class="fas fa-plus"></i> Add Member
                                    </a>
                                    <i class="fas fa-chevron-down toggle-icon-small text-muted"></i>
                                </div>
                            </div>
                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars(substr($h['address'], 0, 80)) ?></small>
                        </div>
                        <div class="card-body household-content bg-white" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Relation</th>
                                            <th>Birth Date</th>
                                            <th>Age</th>
                                            <th>Sex</th>
                                            <th>Civil Status</th>
                                            <th>Occupation</th>
                                            <th>Employment Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($member_list as $m): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($m['last_name'] . ', ' . $m['first_name'] . ' ' . ($m['middle_name'] ? substr($m['middle_name'],0,1) . '.' : '')) ?>
                                                <?php if ($m['is_head']): ?> <span class="badge bg-primary">Head</span><?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($m['relation_to_head'] ?: 'Member') ?></td>
                                            <td><?= $m['birth_date'] ? date('Y-m-d', strtotime($m['birth_date'])) : '—' ?></td>
                                            <td><?= $m['age'] ?></td>
                                            <td><?= $m['gender'] ?></td>
                                            <td><?= htmlspecialchars($m['civil_status']) ?></td>
                                            <td><?= htmlspecialchars($m['occupation'] ?: '—') ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars($m['employment_status'] ?: '—') ?></span>
                                            </td>
                                            <td>
                                                <a href="household_edit.php?id=<?= $m['id'] ?>&category=<?= urlencode($cat['household_name']) ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="household_add.php?category=<?= urlencode($cat['household_name']) ?>" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add New Household to <?= htmlspecialchars($cat['household_name']) ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    function toggleCategory(element) {
        const content = element.closest('.card').querySelector('.category-content');
        const icon = element.querySelector('.toggle-icon');
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
    
    function toggleHousehold(element) {
        const content = element.closest('.card').querySelector('.household-content');
        const icon = element.querySelector('.toggle-icon-small');
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            if (icon) icon.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            if (icon) icon.style.transform = 'rotate(0deg)';
        }
    }
</script>

<style>
    .toggle-icon, .toggle-icon-small {
        transition: transform 0.3s;
        cursor: pointer;
    }
    .category-content, .household-content {
        padding: 20px;
    }
    .card-header {
        cursor: pointer;
    }
    .card-header a {
        cursor: pointer;
    }
    .table th, .table td {
        vertical-align: middle;
    }
    .text-dark {
        color: #2c3e50 !important;
    }
</style>

<?php include '../includes/footer.php'; ?>