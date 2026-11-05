<?php
require 'connector.php';

if (isset($_POST['message']))
{
    
	
	$f =  strip_tags(trim($_POST['f']));
	
	$message =  $_POST['message'];
	$myid=   strip_tags(trim($_POST['unik']));
	


		
		  $sqlq="INSERT INTO `chats`(`message`, `from_id`,  `to_id`,`datesent`) VALUES ('".$message."','".$myid."','".$f."','".$nowdate."')";
			

            $resultq = $conn->query($sqlq);   
			
			if ($resultq){
	 $sql="SELECT * FROM `chat_reg` WHERE (`user1`='".$myid."' AND `user2`='".$f."') OR (`user1`='".$f."' AND `user2`='".$myid."')";
	 $check = $conn->query($sql)or die (mysqli_error());	
	  if ($check->num_rows > 0) {
		  $ddata= mysqli_fetch_array($check);
		  $leid= $ddata["id"];
		  
  $ff="UPDATE `chat_reg` SET `last_msg_date`='".$nowdate."' WHERE id='".$leid."'";		
	 $conn->query($ff)or die (mysqli_error());	  
	  }else{
		$do="INSERT INTO `chat_reg`(`user1`, `user2`, `last_msg_date`) VALUES ('".$myid."','".$f."','".$nowdate."')";
		$conn->query($do)or die (mysqli_error());	   
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