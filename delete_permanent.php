<?php
session_start();include 'Residents_DB.php';
if(!isset($_SESSION['admin'])||!isset($_GET['id'])){header("Location: Display_List.php");exit();}
$id=intval($_GET['id']);
mysqli_query($conn,"DELETE FROM pets WHERE resident_id=$id");
mysqli_query($conn,"DELETE FROM residents WHERE id=$id");
header("Location: Display_List.php");exit();
