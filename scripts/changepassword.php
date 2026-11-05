<?php
session_start();
require 'connector.php';
require_once __DIR__ . '/auth_publish.php';

if ( (!isset($_POST['oldpassword']))||(!isset($_POST['password1']))||(!isset($_POST['password2']))){
	
	header("Location: ../index.php?view=profile");
	die();
	
}else{
$oldpassword = $_POST['oldpassword'];
$password1 = $_POST['password1'];
$password2 = $_POST['password2'];
$unik=strip_tags(trim($_SESSION[$systemname."leido"]));


if($oldpassword=="" || $password1=="" || $password2==""){
$_SESSION[$systemname."errormsg"] = "All fields are required";
header("Location: ../index.php?view=profile");
	die();	
}else if($password1!=$password2){
	
$_SESSION[$systemname."errormsg"] = "Passwords Do not match";
header("Location: ../index.php?view=profile");
	die();		
	
}else{

$checksystemq = "SELECT * FROM users WHERE unique_id = '".$unik."'";
 $checkq = $conn->query($checksystemq)or die (mysqli_error());
$dataz= mysqli_fetch_array($checkq);

$salta= $dataz["salt"];
$passwordx= $dataz["password"];

$newpassword=$salta.$oldpassword;
		$newpassword=md5($newpassword);
		
if ($newpassword != $passwordx) {
    $_SESSION[$systemname."errormsg"] = "Invalid Old Password";
  header("Location: ../index.php?view=profile");
	die();
  
}else{
	
	$salt = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
	$freshpass=$salt.$password1;
	$encpass=md5($freshpass);
	
	$df="UPDATE `users` SET `salt`='".$salt."',`password`='".$encpass."' WHERE `unique_id`='".$unik."'";
	 $c = $conn->query($df)or die (mysqli_error());
	 if($c){
		$publishPasswordHash = password_hash($password1, PASSWORD_DEFAULT);
		if ($publishPasswordHash !== false) {
			eh_auth_publish_user([
				'email' => (string)($dataz['email'] ?? ''),
				'fname' => (string)($dataz['fname'] ?? ''),
				'lname' => (string)($dataz['lname'] ?? ''),
				'password_hash' => $publishPasswordHash,
				'source' => 'elearning',
			], $conn);
		}
		 $_SESSION[$systemname."successmsg"] = "Password has been Changed";
  header("Location: ../index.php?view=profile");
	die(); 
	 }else{
		 $_SESSION[$systemname."errormsg"] = "Unknown Error";
  header("Location: ../index.php?view=profile");
	die();  
		 
	 }
	
}
}


}



?>