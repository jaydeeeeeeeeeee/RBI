<?php
// Database connection for the Senior Citizen Information System
// Connects to the same projectrbi database used by the main app.

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'projectrbi';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die('<div style="font-family:sans-serif;padding:20px;color:#991b1b;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:20px;">
        <strong>Database connection failed:</strong> ' . mysqli_connect_error() . '
    </div>');
}

mysqli_set_charset($conn, 'utf8mb4');
