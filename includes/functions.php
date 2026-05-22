<?php
/**
 * Bridge functions for the Admin Dashboard (new system) to work
 * with the existing ProjectRBI session and auth.
 */

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['admin'])) {
        // Redirect to the main system login, two directories up
        header('Location: ../admin.php');
        exit();
    }

    // Ensure full_name is populated (role_helper.php sets it on main pages)
    if (empty($_SESSION['full_name'])) {
        $_SESSION['full_name'] = $_SESSION['admin'];
    }

    // Provide a user_id placeholder so pages that reference it don't error
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 0;
    }

    // role stays as-is ('captain' / 'secretary') — settings.php check is patched to match
}
