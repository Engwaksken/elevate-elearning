<?php
session_start();
require 'connector.php';

if ( (!isset($_POST['name']))||(!isset($_POST['code']))||(!isset($_POST['create']))){
	
	header("Location: ../?view=create_chatroom");
	die();
	
}else{
$name = $_POST['name'];
$code = strip_tags(trim($_POST['code']));
$pass = strip_tags(trim($_POST['pass']));
$uid=strip_tags(trim($_SESSION[$systemname."leido"]));


$qq = "SELECT * FROM chatroom WHERE code = '" . $code. "' AND pass='".$pass."'";
 $cv = $conn->query($qq)or die (mysqli_error());

$checksystem = "SELECT * FROM chatroom WHERE chatroom_name = '" . $name. "'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 if ($check->num_rows > 0) {
$_SESSION[$systemname."errormsg"] = "Chatroom '". $name."' already exists";
               header("Location: ../?view=create_chatroom");   
	die(); 
 
   }else if ($cv->num_rows > 0) {
$_SESSION[$systemname."errormsg"] = "Chatroom  with CODE '". $code."' and PASS '".$pass."' already exists";
               header("Location: ../?view=create_chatroom");   
	die(); 
 
   }else{
 $sql = "INSERT INTO `chatroom`(`chatroom_name`, `date_created`, `created_by`,`code`, `pass`) VALUES ('".$name."','".$nowdate."','".$uid."','".$code."','".$pass."')";
	
            $result = $conn->query($sql)or die (mysqli_error());     
            
            if($result){
		$_SESSION[$systemname."successmsg"] = "Chatroom '". $name."' has been created";
               header("Location: ../?view=create_chatroom");   
	die(); 		
			}
   }


}




?>