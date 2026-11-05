<?php
session_start();
require 'connector.php';

if ( (!isset($_POST['course']))||(!isset($_POST['changecourse']))){
	
	header("Location: ../index.php?view=change_courselec&id=".$_POST['pjk']."");
	die();
	
}else{
$uid = strip_tags(trim($_POST['pjk']));
$course = strip_tags(trim($_POST['course']));
$intake = strip_tags(trim($_POST['module']));
$pc = strip_tags(trim($_POST['pc']));

if($uid==""){
	
	
	header("Location: ../index.php?view=change_courselec&id=".$_POST['pjk']."");
	die();
	

}else{
	if($course==""){
	//$query="DELETE FROM `course_registration_lecturers` WHERE user_id='".$uid."' AND status!='done'";
	}else{
if($pc!=""){
	
		$query="INSERT INTO `course_registration_lecturers`(`course_id`, `user_id`, `date_added`, `added_by`,`intake`) VALUES ('".$course."','".$uid."','".$nowdate."','".$_SESSION[$systemname."leido"]."','".$intake."')";		
}else{
		$query="INSERT INTO `course_registration_lecturers`(`course_id`, `user_id`, `date_added`, `added_by`,`intake`) VALUES ('".$course."','".$uid."','".$nowdate."','".$_SESSION[$systemname."leido"]."','".$intake."')";	
}
		
	}
	$q="DELETE FROM `course_registration_lecturers` WHERE user_id='".$uid."' AND course_id='".$course."' AND intake='".$intake."'";
	$conn->query($q);

$check = $conn->query($query)or die (mysqli_error());

if ($check) {
    $_SESSION[$systemname."successmsg"] = "Course module Added";
  
               	header("Location: ../index.php?view=change_courselec&id=".$_POST['pjk']."");
  
}else{
$_SESSION[$systemname."errormsg"] = "Course Module not Added";
  
               	header("Location: ../index.php?view=change_courselec&id=".$_POST['pjk']."");	
}
	
}
}



?>