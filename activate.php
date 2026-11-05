<?php
session_start();
require 'scripts/connector.php';

if ( !isset($_GET['id'])){
	
	header("Location: index.php?view=view_students");
	die();
	
}else{
$uid = strip_tags(trim($_GET['id']));
$page = trim($_GET['xts']);
$checksystem = "SELECT * FROM users WHERE unique_id = '" . $uid. "'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 if ($check->num_rows < 1) {
$_SESSION[$systemname."errormsg"] = "Invalid User";
               header("Location: index.php?view=".$page."");   
	die(); 

  
   
   }else{
	   $data= mysqli_fetch_array($check);
	   
	
	$q = "UPDATE users SET verified='yes' WHERE unique_id='".$uid."'";
 $qq = $conn->query($q)or die (mysqli_error());
 if($qq){
$_SESSION[$systemname."successmsg"] = "User ".$data["fname"]." ".$data["lname"]." has been ACTIVATED";
               header("Location: index.php?view=".$page."");   
	die();	 
 }else{
	$_SESSION[$systemname."errormsg"] = "Unknown Error";
               header("Location: index.php?view=".$page."");   
	die(); 
 }
   }
	
}

?>