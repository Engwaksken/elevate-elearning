<?php
session_start();
require 'connector.php';

$sessionKey = $systemname . 'leido';

if ( (!isset($_POST['title']))||(!isset($_POST['details']))||(!isset($_POST['datedue']))){
	
	header("Location: ../?view=todo");
	die();
	
}else{
$title = $_POST['title'];
$details = $_POST['details'];
$datedue = $_POST['datedue'];
$uid = trim(strip_tags((string)$_SESSION[$sessionKey]));




 $sql = "INSERT INTO `todolist`( `title`, `details`, `date_due`, `date_added`, `valid`, `user_id`) VALUES ('".$title."','".$details."','".$datedue."','".$nowdate."','yes','".$uid."')";
	
            $result = $conn->query($sql)or die (mysqli_error());     
            
            if($result){
			

			$_SESSION[$systemname."successmsg"] = $title." has been saved";
              header("Location: ../?view=todo");  
	die(); 			
			}else{
				$_SESSION[$systemname."errormsg"] = "Unknown error";
             header("Location: ../?view=todo");     
	die(); 
			}
	 
		

	   
	  
   }




?>