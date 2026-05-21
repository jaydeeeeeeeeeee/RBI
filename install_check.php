<?php
/**
 * Landing page after first launch.
 * Redirects to setup wizard if not yet configured, otherwise to Home.
 */
session_start();

$lock   = __DIR__ . '/install.lock';
$config = __DIR__ . '/config.php';

if (!file_exists($lock) || !file_exists($config)) {
    header('Location: install.php');
} else {
    header('Location: admin.php');
}
exit();
