<?php
session_start();
require 'connector.php';


if ( !isset($_GET['use'])){
	
	header("Location: ../index.php?view=home");
	die();
	
}else{
$use = $_GET['use'];
$lec = $_GET['kasjdhajskdhajsdhhouy'];

$fg="DELETE FROM `course_registration` WHERE `id`='".$use."'";
 $re = $conn->query($fg)or die (mysqli_error()); 


	 if($re){
		$_SESSION[$systemname."successmsg"] = "Course registration removed";
              header("Location: ../index.php?view=change_course&id=".$lec);  
	die(); 	 
	 }else{
				$_SESSION[$systemname."errormsg"] = "Unknown error";
             header("Location: ../index.php?view=change_course&id=".$lec);     
	die(); 
			}
 
	  
   }




?>