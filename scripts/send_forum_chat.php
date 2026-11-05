<?php
require 'connector.php';

if (isset($_POST['message']))
{
    
	
	$f =  strip_tags(trim($_POST['f']));
	$roomid=hex2bin($f);
	$message =  $_POST['message'];
	$myid=   strip_tags(trim($_POST['unik']));
	


		
		  $sqlq="INSERT INTO `chatroom_messages`( `roomid`, `message`, `from_id`, `datesent`) VALUES ('".$roomid."','".$message."','".$myid."','".$nowdate."')";
			

            $resultq = $conn->query($sqlq);   
			
			if ($resultq){
	 $sql="SELECT * FROM `chatroom_messages` WHERE `roomid`='".$roomid."' AND `message`='".$message."' AND `from_id`='".$myid."' AND `datesent`='".$nowdate."'";
	 $check = $conn->query($sql)or die (mysqli_error());	
	  if ($check->num_rows > 0) {
		  $ddata= mysqli_fetch_array($check);
		  $leid= $ddata["id"];
		  
  $ff="UPDATE `chatroom_reg` SET `last_msg_id`='".$leid."',`last_msg_date`='".$nowdate."',`hasread`='no' WHERE roomid='".$roomid."'";		
	 $conn->query($ff)or die (mysqli_error());	  
	  }
		echo "done";
			}else{
			echo 'notdone';	
			}
            
 

}
else
{
   echo "notdone"; 
}
        
	

?>      