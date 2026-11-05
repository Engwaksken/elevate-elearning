<?php
session_start();
require 'connector.php';

if ( (!isset($_POST['course_name']))||(!isset($_POST['course_code']))||(!isset($_POST['create']))){
	
	header("Location: ../index.php?view=create_course");
	die();
	
}else{
$course_name = strip_tags(trim($_POST['course_name']));
$course_code = strip_tags(trim($_POST['course_code']));
$dartion = strip_tags($_POST['duration']);
$fees = strip_tags(trim($_POST['fees']));
$shortnotes = $_POST['shortnotes'];
$category = $_POST['category'];


$checksystem = "SELECT * FROM courses WHERE name = '" . $course_name. "' OR short_code = '" . $course_code. "'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 if ($check->num_rows > 0) {
$_SESSION[$systemname."errormsg"] = "Course '". $course_name." / ".$course_code."' already exists";
               header("Location: ../index.php?view=create_course");   
	die(); 
 
   }else{
 $sql = "INSERT INTO `courses`(`name`, `short_code`, `duration`, `fees`, `added_by`, `date_added`, `short_notes`, `category`) VALUES ('".$course_name."','".$course_code."','".$dartion."','".$fees."','".$_SESSION[$systemname."leido"]."','".$nowdate."','".$shortnotes."','".$category."')";
	
            $result = $conn->query($sql)or die (mysqli_error());     
            
            if($result){
		$_SESSION[$systemname."successmsg"] = "Course '". $course_name." / ".$course_code."' Has been created";
               header("Location: ../index.php?view=create_course");   
	die();		
			}
   }


}




?>