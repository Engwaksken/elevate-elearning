<?php
session_start();
require 'connector.php';


if ( !isset($_POST['number'])){
	
	echo 0;
	
}else{

$number= trim($_POST['number']);



$checksystem = "SELECT * FROM `users` WHERE `acc_type`='afgezt' && `student_no`='".$number."'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 $ccc = mysqli_fetch_array( $check );
 if ($check->num_rows >0) {
	 echo "<span class='badge badge-info'>".$ccc["fname"]." ".$ccc["lname"]." FOUND</span>";
	 echo '<div class="form-group"><label class="col-lg-2 control-label">Course</label><select name="course">';
	 $userid=$ccc['unique_id'];
	 
	 $checksystemx = "SELECT * FROM `course_registration` WHERE `user_id`='".$userid."'";
	  $checkx = $conn->query($checksystemx)or die (mysqli_error());
	  if ($checkx->num_rows >0){
		  
	   while($cccx = mysqli_fetch_array($checkx)){
		   
		  echo $courseid=$cccx['course_id'];
		   
		   $z = "SELECT `name` FROM `courses` WHERE `id`='".$courseid."'";
	  $w = $conn->query($z);
	  $ww= mysqli_fetch_array($w);
	  $coursename=$ww['name'];
	  
	  echo '<option value="'.$courseid.'">'.$coursename.'</option>';
	   
	  }
	  
	
	  }else{
		  echo '<option value="">None</option>';  
	  }
	 

  echo '</select></div>';

}else{
echo 0;	
}

}




?>