<?php
/**
 * ProjectRBI Role Helper
 * Include after session_start() on every page
 * Provides: $user_role, $is_captain, $is_secretary, $is_guest, $can_edit, $can_register
 */

if (!isset($_SESSION['admin'])) {
    header("Location: admin.php"); exit();
}

$user_role      = $_SESSION['role'] ?? 'guest';
$user_fullname  = $_SESSION['full_name'] ?? $_SESSION['admin'];
$is_captain     = ($user_role === 'captain');
$is_secretary   = ($user_role === 'secretary');
$is_guest       = ($user_role === 'guest');

// What each role can do
$can_edit       = $is_captain || $is_secretary;   // edit/delete residents
$can_register   = $is_captain || $is_secretary;   // register new residents
$can_manage_docs= $is_captain || $is_secretary;   // process document requests
$can_approve    = $is_captain;                     // captain-only approvals
$view_only      = $is_guest;                       // read-only mode

// Role badge info for UI
$role_badges = [
    'captain'   => ['label'=>'Brgy. Captain',  'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,.15)',  'icon'=>'fa-star'],
    'secretary' => ['label'=>'Secretary',       'color'=>'#3b82f6', 'bg'=>'rgba(59,130,246,.15)',  'icon'=>'fa-user-tie'],
    'guest'     => ['label'=>'Guest Viewer',    'color'=>'#94a3b8', 'bg'=>'rgba(148,163,184,.15)', 'icon'=>'fa-eye'],
];
$rbadge = $role_badges[$user_role];

require_once __DIR__ . '/csrf_helper.php';