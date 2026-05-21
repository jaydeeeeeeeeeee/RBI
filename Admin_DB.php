<?php
$_config = __DIR__ . '/config.php';
if (file_exists($_config)) { require_once $_config; }

$host   = defined('DB_HOST') ? DB_HOST : "localhost";
$port   = defined('DB_PORT') ? DB_PORT : 3306;
$user   = defined('DB_USER') ? DB_USER : "root";
$pass   = defined('DB_PASS') ? DB_PASS : "";
$dbname = defined('DB_NAME') ? DB_NAME : "projectrbi";
$conn   = new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>