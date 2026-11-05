<?php
session_start();
require 'connector.php';

if ( (!isset($_POST['pass']))||(!isset($_POST['code']))){
	
	echo '<div class="alert alert-danger">
  <strong>Hacking Detected
</div>';
	
}else{
$code = strip_tags(trim($_POST['code']));
$pass = strip_tags(trim($_POST['pass']));
$unik=strip_tags(trim($_POST['unik']));



$checksystem = "SELECT * FROM chatroom WHERE code = '" . $code. "' AND pass = '" . $pass. "'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 if ($check->num_rows < 1) {
echo '<div class="alert alert-danger">
  <strong>Invalid chatroom</strong>
</div>';
 
   }else{
	   $data= mysqli_fetch_array($check);
	   $idd= $data["id"];
	   $chatroom_name= $data["chatroom_name"];
$checksystem = "SELECT * FROM chatroom_reg WHERE roomid = '" . $idd. "' AND user_id = '" . $unik. "'";
 $check = $conn->query($checksystem)or die ();
 if ($check->num_rows >0) {
echo '<div class="alert alert-danger">
  <strong>You already joined '.$chatroom_name.' </strong>
</div>';	 
 }else{
	   $sql="INSERT INTO `chatroom_reg`(`roomid`, `user_id`, `date_joined`, `last_msg_date`) VALUES ('".$idd."','".$unik."','".$nowdate."','".$nowdate."')";
	   $che = $conn->query($sql)or die ();
	   if($che){
		   echo "success";
	   }else{
		 echo '<div class="alert alert-danger">
  <strong>Unknown error</strong>
</div>';  
	   }
 }
   }


}




?>