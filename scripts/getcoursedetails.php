<?php
session_start();
require 'connector.php';
$thisyear=date("Y", strtotime($now));


if (!isset($_POST['id'])) {
    echo '<div class="alert alert-danger">
  <strong>Danger!</strong> Hacking Attempt detected.
</div>';
    die();
} else {
	
	$courseid=$_POST['id'];
	$aA = "SELECT * FROM courses WHERE `id` = '".$courseid."'";
    $bA = $conn->query($aA) or die(mysqli_error());
	 if ($bA->num_rows < 1) {
        echo '<div class="alert alert-danger">
  <strong>Invalid Course.
</div>';
        
        
        
    }else{
		       
           echo'
          <div id="contact-1" class="tab-pane active">
            <div class="row m-b-lg">';
			
	  while ($dX = mysqli_fetch_array($bA)) {
		  
		
		  
		  $coucategory=$dX["category"];
		  
		  $az = "SELECT * FROM users WHERE unique_id ='".$dX["added_by"]."'";
		  $bz = $conn->query($az) or die(mysqli_error());
	$azz  = mysqli_fetch_array($bz);
		  
		  echo' <div class="client-detail">
              <div class="full-height-scroll"> <strong>'.$dX["name"].' ('.$dX["short_code"].')</strong>
                <ul class="list-group clear-list">
                  <li class="list-group-item fist-item"> <span class="pull-right"> '.$dX["duration"].'</span> Duration </li>
                  <li class="list-group-item"> <span class="pull-right"> '.$dX["date_added"].' </span> Date Created</li>
				   <li class="list-group-item"> <span class="pull-right"> '.$azz['fname'].' '.$azz['lname'].' </span> Created By</li>
                  
                </ul>
				
				';
		  
		    $p = "SELECT * FROM intakes WHERE `type`='".$coucategory."' AND year(date_launching) >= '" . $thisyear. "' ORDER BY `date_launching` ASC";
            $z = $conn->query($p) or die(mysqli_error());
           $zenum=$z->num_rows;
		   echo' <strong>'.$zenum.' INTAKES</strong>
                <ul class="list-group clear-list">';
		   while( $dl= mysqli_fetch_array($z)){
			$c_name= $dl["name"];
				$intakeid= $dl["id"];
			$theyear=date("Y", strtotime($dl["date_launching"]));  
			
			  $a = "SELECT * FROM course_registration WHERE course_id ='".$dX["id"]."' AND intake='".$intakeid."'";
    $b = $conn->query($a) or die(mysqli_error());
    $number=$b->num_rows; 
	
	echo' <li class="list-group-item fist-item"> <span class="pull-right"> '.$c_name.'- '.$theyear.'</span> '.$number.' students </li>
     
            ';
		   }
	
  echo'    </ul>
            
			<a href="?view=addmaterial&course='.$courseid.'" type="button" class="btn btn-w-m btn-success">View Course Material</a> <br><br>
			<a href="?view=addmaterialx&course='.$courseid.'" type="button" class="btn btn-w-m btn-info">Add Course Module</a> 
		<!--	<a href="?view=view_notes&course='.$courseid.'" type="button" class="btn btn-w-m btn-success">View Course Material</a> <br><br> -->
            </div>
          </div> ';
	}
	echo '</div>';
	}
}
?>