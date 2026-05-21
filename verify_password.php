<?php
session_start();include 'Admin_DB.php';
$password=$_POST['admin_password']??$_POST['password']??null;
if($password&&isset($_SESSION['admin'])){
  $username=$_SESSION['admin'];
  $stmt=$conn->prepare("SELECT password FROM admins WHERE username=?");
  $stmt->bind_param("s",$username);$stmt->execute();$stmt->bind_result($hp);$stmt->fetch();$stmt->close();
  echo($hp&&password_verify($password,$hp))?"success":"failure";
}else{echo "unauthorized";}
?>
