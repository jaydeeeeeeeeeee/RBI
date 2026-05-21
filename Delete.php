<?php
session_start();if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
include 'Residents_DB.php';
if(isset($_GET['id'])){$id=intval($_GET['id']);mysqli_query($conn,"UPDATE residents SET is_hidden=1 WHERE id=$id");}
header("Location: Display_List.php");exit();
