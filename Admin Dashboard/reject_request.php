<?php
// admin/reject_request.php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if ($id) {
    // Update status to Rejected
    $stmt = $pdo->prepare("UPDATE certificate_requests SET status = 'Rejected' WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: requests.php?success=rejected');
    exit();
} else {
    header('Location: requests.php');
    exit();
}
?>