<?php
session_start();
require 'connector.php';


if ( (!isset($_POST['position']))||(!isset($_POST['theid']))){
	
echo 'notdone';
	
}else{
$position= strip_tags(trim($_POST['position']));
$theid = strip_tags(trim($_POST['theid']));

if($position!="" && $theid!=""){
$checksystem = "UPDATE `course_unit` SET `order_number`='".$position."' WHERE `id`='".$theid."'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 echo'done';
 	
}else{
echo "notdone";	
}

}

?>