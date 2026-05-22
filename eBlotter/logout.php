<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Clear the eBlotter session bridge so it gets rebuilt cleanly on next visit
unset($_SESSION['eb_user']);
// Return to ProjectRBI dashboard (full logout is handled by ProjectRBI's own logout)
header('Location: ../Home.php');
exit();
