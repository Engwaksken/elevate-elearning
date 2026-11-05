<?php
session_start();
require 'connector.php';


if ( !isset($_GET['note'])){
	
	header("Location: ../?view=todo");
	die();
	
}else{
$note = $_GET['note'];

$uid=strip_tags(trim($_SESSION[$systemname."leido"]));

$sql="SELECT * FROM `todolist` WHERE `id`='".$note."' AND `user_id`='".$uid."'";
 $re = $conn->query($sql)or die (mysqli_error());  
 if ($re->num_rows < 1) {
	 	$_SESSION[$systemname."errormsg"] = "Invalid Note";
              header("Location: ../?view=todo");  
	die();
 
 }else{
	$new="UPDATE `todolist` SET `valid`='no' WHERE `id`='".$note."'"; 
	 $zzz = $conn->query($new)or die (mysqli_error()); 
	 if($zzz){
		$_SESSION[$systemname."successmsg"] = "Note has been removed";
              header("Location: ../?view=todo");  
	die(); 	 
	 }else{
				$_SESSION[$systemname."errormsg"] = "Unknown error";
             header("Location: ../?view=todo");     
	die(); 
			}
 }  
	  
   }




?>