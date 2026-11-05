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
 echo'<label>Intake *</label>
                 <select class="form-control" name="intake">';
                
               
				$p = "
    SELECT intakes.id, intakes.name AS intake_name, intakes.date_launching, branches.name AS branch_name 
    FROM intakes 
    JOIN branches ON intakes.branch_id = branches.id 
    WHERE intakes.type = '".$cat."' 
    AND YEAR(intakes.date_launching) >= '" . $thisyear . "' 
    ORDER BY intakes.date_launching ASC
";

$z = $conn->query($p) or die(mysqli_error($conn));

if ($z->num_rows < 1) {
    $course = "none";
    echo '<option value="" selected="selected">...None...</option>';
} else {
    while ($dl = mysqli_fetch_array($z)) {
        $c_id = $dl["id"];
        $c_name = $dl["intake_name"];
        $theyear = date("Y", strtotime($dl["date_launching"]));
        $branch_name = $dl["branch_name"];

        echo '<option value="'.$c_id.'">'.$c_name.' - '.$theyear.' - '.$branch_name.'</option>';
					
					
				} 
			}
			   echo'   </select>';

	}

}

?>