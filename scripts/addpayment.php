<?php
session_start();
require 'connector.php';


if ((!isset($_POST['stdnumber']))||(!isset($_POST['amount']))||(!isset($_POST['comment']))||(!isset($_POST['balance']))){
	
	
	header("Location: ../?view=home");
	die();
	
}else{

$amount= strip_tags(trim($_POST['amount']));
$comment= strip_tags($_POST['comment']);
$stdnumber= strip_tags($_POST['stdnumber']);
$course= strip_tags($_POST['course']);
$balance= strip_tags($_POST['balance']);

$unik=strip_tags(trim($_SESSION[$systemname."leido"]));

if($amount=="" || $stdnumber=="" || $comment==""){
$_SESSION[$systemname."errormsg"] = "All fields are required";	
header("Location: ../?view=addrecord");
	die();	
}else{
$checksystem = "SELECT * FROM `users` WHERE `acc_type`='afgezt' && `student_no`='".$stdnumber."'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 $ccc = mysqli_fetch_array( $check );
 if ($check->num_rows <1) {
$_SESSION[$systemname."errormsg"] = "Student of student number ".$stdnumber." not found";	
header("Location: ../?view=addrecord");
	die();	
}else{
$sql="INSERT INTO `accounts` (`user_id`, `amount_received`, `balance`, `verified_by`, `date_received`, `follow_up_notes`, `course_id`) VALUES ('".$ccc['unique_id']."','".$amount."','".$balance."','".$unik."','".$nowdate."','".$comment."','".$course."')";
 $checkh = $conn->query($sql)or die (mysqli_error());
 if( $checkh){
	$_SESSION[$systemname."successmsg"] = "Adding Payment successful";	
header("Location: ../?view=addrecord");
	die(); 
 }else{
	$_SESSION[$systemname."errormsg"] = "Adding payment failed";	
header("Location: ../?view=addrecord");
	die(); 
 }
}
	
}


}




?>