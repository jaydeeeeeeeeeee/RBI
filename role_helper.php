<?php
/**
 * ProjectRBI Role Helper — v2
 * Roles: captain (monitor/approve), secretary (main admin)
 * Guest role has been removed.
 *
 * Include after session_start() on every page.
 * Provides: $user_role, $is_captain, $is_secretary, and permission flags.
 */

if (!isset($_SESSION['admin'])) {
    header("Location: admin.php"); exit();
}

$user_role     = $_SESSION['role'] ?? 'secretary';
$user_fullname = $_SESSION['full_name'] ?? $_SESSION['admin'];

// ── Role flags ────────────────────────────────────────────────────────────
$is_captain   = ($user_role === 'captain');    // Chairman — monitor/approve only
$is_secretary = ($user_role === 'secretary');  // Secretary — main daily admin

// Redirect unknown roles back to login
if (!$is_captain && !$is_secretary) {
    session_destroy();
    header("Location: admin.php?reason=invalid_role"); exit();
}

// ── Permission flags ──────────────────────────────────────────────────────

// CRUD operations — Secretary only
$can_edit          = $is_secretary;   // edit/delete residents
$can_register      = $is_secretary;   // register new residents
$can_manage_docs   = $is_secretary;   // process document requests
$can_manage_equip  = $is_secretary;   // manage equipment
$can_import        = $is_secretary;   // import CSV

// View/monitoring — both roles
$can_view          = true;            // view residents, reports, blotter

// Approval — Captain only (e.g. blotter case resolution acknowledgement)
$can_acknowledge   = $is_captain;     // acknowledge/supervise blotter resolutions

// Audit logs — Captain can view, Secretary cannot
$can_view_logs     = $is_captain;

// Account management — NEITHER (Super Admin only via sa_panel.php)
$can_manage_accounts = false;

// ── Role badge UI info ────────────────────────────────────────────────────
$role_badges = [
    'captain'   => [
        'label' => 'Brgy. Captain',
        'color' => '#f59e0b',
        'bg'    => 'rgba(245,158,11,.15)',
        'icon'  => 'fa-star',
        'desc'  => 'Monitoring & Approval',
    ],
    'secretary' => [
        'label' => 'Secretary',
        'color' => '#3b82f6',
        'bg'    => 'rgba(59,130,246,.15)',
        'icon'  => 'fa-user-tie',
        'desc'  => 'System Administrator',
    ],
];
$rbadge = $role_badges[$user_role] ?? $role_badges['secretary'];

require_once __DIR__ . '/csrf_helper.php';