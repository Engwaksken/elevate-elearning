<?php
session_start();
require 'connector.php';


if ( (!isset($_POST['title']))||(!isset($_POST['days']))){
	
	header("Location: ../index.php?view=home");
	die();
	
}else{
$title = $_POST['title'];
$days = strip_tags(trim($_POST['days']));
$uid=strip_tags(trim($_SESSION[$systemname."leido"]));
$course=strip_tags(trim($_POST['course']));



 $sql = "INSERT INTO `course_unit`(`name`, `course_id`, `duration`, `date_added`, `added_by`, `order_number`) VALUES ('".$title."','".$course."','".$days."','".$nowdate."','".$uid."','')";
	
            $result = $conn->query($sql)or die (mysqli_error());     
            
            if($result){
			

			$_SESSION[$systemname."successmsg"] = $month." intake for ".$gg." has been added";
               header("Location: ../index.php?view=addmaterial&course=".$course."");   
	die(); 			
			}else{
				$_SESSION[$systemname."errormsg"] = "Unknown error";
                header("Location: ../index.php?view=addmaterial&course=".$course."");     
	die(); 
			}
	 
		

	   
	  
   }




?>