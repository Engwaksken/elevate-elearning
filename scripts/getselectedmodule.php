<?php
session_start();
require 'connector.php';

$thisyear=date("Y", strtotime($now));

if (!isset($_POST['selected'])) {

} else {
	
	$id=$_POST['selected'];
	
	if($id==""){
echo"";		
	}else{
   $p = "SELECT * FROM courses WHERE id = '" . $id . "'";
            $z = $conn->query($p) or die(mysqli_error());
			$a  = mysqli_fetch_array($z);
			$cat = $a["category"];
 echo'<label>Module *</label>
                 <select class="form-control" name="module">';
                
               
		$moduleget="SELECT * FROM `course_unit` WHERE `course_id`='".$id."' ORDER BY `order_number`";
    $gett = $conn->query($moduleget)or die (mysqli_error());
	$modules=$gett->num_rows;
	
	//$fgh="SELECT * FROM `course_registration_lecturers` WHERE `user_id`='".."'";
	
            if ($modules< 1) {
                $course = "none";
				echo' <option value="" selected="selected">...None...</option>';
				
            } else {
			
		
				while( $dl= mysqli_fetch_array($gett)){
					$c_id= $dl["id"];
					$c_name= $dl["name"];
					
					
					echo '<option value="'.$c_id.'">'.$c_name.'</option>';
					
					
				} 
			}
			   echo'   </select>';

	}

}

?>