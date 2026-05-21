<?php
session_start();
if(!isset($_SESSION['admin'])){header("Location: admin.php");exit();}
include 'role_helper.php';
if($is_guest){header("Location: Home.php?denied=eblotter");exit();}
header("Location: ../eBlotter/eblotter_home.php");
exit();
