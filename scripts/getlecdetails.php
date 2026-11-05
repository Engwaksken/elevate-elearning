<?php
session_start();
require 'connector.php';



if (!isset($_POST['id'])) {
    echo '<div class="alert alert-danger">
  <strong>Danger!</strong> Hacking Attempt detected.
</div>';
    die();
} else {
	
	$id=$_POST['id'];
 
	 
	
    $a = "SELECT * FROM users WHERE unique_id ='".$id."'";
    $b = $conn->query($a) or die(mysqli_error());
    if ($b->num_rows < 1) {
        echo '<div class="alert alert-danger">
  <strong>Invalid Selection.
</div>';
        
        
        
    } else {
        while ($d = mysqli_fetch_array($b)) {
            $std_id = $d["unique_id"];
            if ($d["verified"] == "yes") {
                $ver = '<a href="deactivate.php?id=' . $d["unique_id"] . '"><span class="label label-primary">Active</span></a>';
            } else {
                $ver = '<a href="activate.php?id=' . $d["unique_id"] . '"><span class="label label-info">Not Active</span></a>';
            }
            
			
				if($d["acc_type"]=="xybrtw"){
		$mtype="Admin";
		//$p = "SELECT * FROM course_registration WHERE user_id = '" . $std_id . "' ORDER BY id DESC";
	}else if($d["acc_type"]=="afgezt"){
		$mtype="Student";
		$p = "SELECT * FROM course_registration WHERE user_id = '" . $std_id . "' ORDER BY id DESC";
		
	}else if($d["acc_type"]=="xtfbry"){
		$mtype="Lecturer";
		$p = "SELECT * FROM course_registration_lecturers WHERE user_id = '" . $std_id . "' ORDER BY id DESC";
		
	}else if($d["acc_type"]=="zyffwq"){
		$mtype="Accounts";
		//$p = "SELECT * FROM course_registration WHERE user_id = '" . $std_id . "' ORDER BY id DESC";
	}
	//$p=$p;
	
            //pick course
           //$p = "SELECT * FROM course_registration WHERE user_id = '" . $std_id . "' ORDER BY id DESC"; 
            $z = $conn->query($p) or die(mysqli_error());
            if ($z->num_rows < 1) {
                $course = "N/A";
				$button='<a href="?view=change_courselec&id='.$d["unique_id"].'" class="label label-primary">Assign Course</a>';
				$fty="N/A";
            } else {
                $a  = mysqli_fetch_array($z);
				$fty=$a["added_by"];
				$az = "SELECT * FROM users WHERE unique_id ='".$fty."'";
    $bz = $conn->query($az) or die(mysqli_error());
	$azz  = mysqli_fetch_array($bz);
	
                $pp = "SELECT name FROM courses WHERE id = '" . $a["course_id"] . "' ORDER BY id DESC";
                $ll = $conn->query($pp) or die(mysqli_error());
                if ($ll->num_rows < 1) {
                    $course = "N/A";
					$button='<a href="?view=change_courselec&id='.$d["unique_id"].'" class="label label-primary">Edit Courses</a>';
                } else {
                    $qw     = mysqli_fetch_array($ll);
                    $course = $qw['name'];
					$button='<a href="?view=change_courselec&id='.$d["unique_id"].'" class="label label-warning">Edit Courses</a>';
                }
            }
            
			
			  $dpt= $d["dp"];
	   
	   if ($dpt=="default.jpg"){
					$dpft='images/default_p_pics/user.png';
					}else{
						$dpft= 'images/user_p_pics/'.$dpt;
					}


	            
           echo'
          <div id="contact-1" class="tab-pane active">
            <div class="row m-b-lg">
              <div class="col-lg-4 text-center">
                <h2>'.$d["fname"].' '.$d["lname"].' '.$d["other_name"].'</h2>
                <div class="m-b-sm"> <img alt="image" class="img-circle" src="'.$dpft.'"
                                                     style="width: 62px"> </div>
              </div>
              <div class="col-lg-8"> <strong> '.$mtype.' </strong>
                
                <a href="?view=chatwith&pr='.$std_id.'" type="button" class="btn btn-primary btn-sm btn-block"><i
                                                    class="fa fa-envelope"></i> Chat </a>
              </div>
            </div>
            <div class="client-detail">
              <div class="full-height-scroll"> <strong>Account Details</strong>
                <ul class="list-group clear-list">
                  <li class="list-group-item fist-item"> <span class="pull-right"> '.$d["dob"].'</span> D.O.B </li>
                  <li class="list-group-item"> <span class="pull-right"> '.$d["date_created"].' </span> Date Created</li>
                  <li class="list-group-item"> <span class="pull-right">  '.$d["email"].'  </span>Email </li>
                  <li class="list-group-item"> <span class="pull-right"> '.$d["contact"].' </span> Contact </li>
                  <li class="list-group-item"> <span class="pull-right"> '.$d["gender"].' </span> Gender </li>
				    <li class="list-group-item"> <span class="pull-right"> '.$azz["fname"].' </span> Added By </li>
                </ul>
                <strong>Course</strong>
                <p> '.$course.'</p>
				 <p> '.$button.'</p>
                <hr/>
             
            </div>
          </div>
         
        </div>';
        }
    }
    



}

?>