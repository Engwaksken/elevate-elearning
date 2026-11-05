<?php
$uiid=$_GET["id"];
$thisyear=date("Y", strtotime($now));
if(!isset($_GET['id'])){
echo'<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
      <tbody>
        <tr>
          <td style="background:#f44336; padding:20px; color:#fff; text-align:center;"> HACKING ATTEMPT DETECTED</td>
        </tr>
      </tbody>
    </table> ';
  
              // header("Location: index.php?home");	
}else{
?>
<div class="row wrapper border-bottom white-bg page-heading">
                <div class="col-lg-10">
                    <h2>Change Course</h2>
                    <ol class="breadcrumb">
                        <li>
                            <a href="?">Home</a>
                        </li>
                        <li>
                            <a href="?view=all_courses">Assign/ Change Course</a>
                        </li>
                        <li class="active">
                            <strong>Assign/ Change Course</strong>
                        </li>
                    </ol>
                </div>
                <div class="col-lg-2">

                </div>
            </div>
<div class="wrapper wrapper-content">
<div class="row">
<div class="middle-box animated fadeInDown" style="margin-top:0;">
                                <?php
if(isset($_SESSION[$systemname."errormsg"])) {
    $error = $_SESSION[$systemname."errormsg"];
    
    ?>
  <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
      <tbody>
        <tr>
          <td style="background:#f44336; padding:20px; color:#fff; text-align:center;"> <?php echo $error; ?></td>
        </tr>
      </tbody>
    </table>   
    <?php
    unset($_SESSION[$systemname."errormsg"]);
} else if(isset($_SESSION[$systemname."successmsg"])){
     $success = $_SESSION[$systemname."successmsg"];
    ?>
 <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
      <tbody>
        <tr>
          <td style="background:#41b54a; padding:20px; color:#fff; text-align:center;"> <?php echo $success; ?></td>
        </tr>
      </tbody>
    </table> 
<?php
  unset($_SESSION[$systemname."successmsg"]);  
}

?>
       <?php
	   $checksystem = "SELECT * FROM users WHERE unique_id = '" . $uiid. "'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 if ($check->num_rows < 1) {
echo'<table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
      <tbody>
        <tr>
          <td style="background:#f44336; padding:20px; color:#fff; text-align:center;"> Unkwown User</td>
        </tr>
      </tbody>
    </table> ';

  
   
   }else{
	    $data= mysqli_fetch_array($check);
	$typox= $data["acc_type"];	
		

		$route="scripts/changecourse_lec.php";
	
		$dpc= $data["dp"];
		$d_email= $data["email"];
		
		$d_gender= $data["gender"];
		$d_country= $data["country"];
		$d_contact= $data["contact"];
		if($d_contact==""){
			$d_contact="N/A";
		}else{
			$d_contact=$d_contact;
		}
if ($dpc=="default.jpg"){
					$dpp= ' src="images/default_p_pics/user.png"';
					}else{
						$dpp= ' src="images/user_p_pics/'.$dpc.'"';
					}	
		
				echo'<div class="">
                <div class="contact-box">
                    <a href="index.php?view=profile">
                    <div class="col-sm-4">
                        <div class="text-center">
                            <img alt="image" class="img-circle m-t-xs img-responsive" '.$dpp.'>
                            <div class="m-t-xs font-bold">'.$cou.'</div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <h3><strong>'.$data["fname"].'  '.$data["lname"].'</strong></h3>
                        <p><i class="fa fa-envelope-o"></i> '.$d_email.'</p>
                        <address>
                            <strong>Other Details</strong><br>
                            '.$d_gender.'<br>
                            '.$d_country.'<br>
                            <abbr title="Phone" class="fa fa-phone"></abbr> '.$d_contact.'
                        </address>
                    </div>
                    <div class="clearfix"></div>
                        </a>
                </div>
            </div>';
	   
   }
   
   	 $p = "SELECT * FROM course_registration_lecturers WHERE user_id = '" . $uiid . "' ORDER BY id DESC";
		
	
	echo '<div class="ibox-content">

                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Course</th>
                                <th>Module</th>
                                
                            </tr>
                            </thead>
                            <tbody>
                         
                          
                        ';
		$num=1;
		      //pick course
           
            $z = $conn->query($p) or die(mysqli_error());
            if ($z->num_rows < 1) {
                $cou = "N/A";
				
            } else {
                while($a  = mysqli_fetch_array($z)){
					$butto='<a href="scripts/removelec.php?use='.$a["id"].'&kasjdhajskdhajsdhhouy='.$a["user_id"].'" type="button" class="btn btn-danger btn-xs">Remove</a>';
                $pp = "SELECT name FROM courses WHERE id = '" . $a["course_id"] . "' ORDER BY id DESC";
                $ll = $conn->query($pp) or die(mysqli_error());
                if ($ll->num_rows < 1) {
                    
					echo'   <tr>
                                <td>1</td>
                                
                                <td>course not found</td>
                                <td></td>
                            </tr>';
				
                } else {
                    $qw     = mysqli_fetch_array($ll);
                    $cou = $qw['name'];
					$themodu = $a['intake'];
         $theyear = $a['date_launching'];
					
					
					$gg="SELECT * FROM `course_unit` WHERE `id`='".$themodu."'";
					    $ek= $conn->query($gg) or die(mysqli_error());
						$qwv     = mysqli_fetch_array($ek);
						$modex = $qwv['name'];
           
					echo'   <tr>
                                <td>'.$num.'</td>
                                <td>'.$cou.'</td>
                                <td>'.$modex.'</td>
                                <td>'.$butto.'</td>
                            </tr>';
					
                }
				
				$num++;
            }
			}
			
			echo '    </tbody>
                        </table>

                    </div>';
	   ?>
       
                 <form class="m-t" role="form" method="post" action="<?php echo $route ?>" >
                 <div class="form-group">
                 <label>Course Category *</label>
                 <select class="form-control" name="course" id="coursexx" onchange="getval(this);">
                
               <?php
			    //pick course
				
            //$p = "SELECT * FROM course_registration WHERE user_id = '" . $uiid . "' AND status!='done'";
            $z = $conn->query($p) or die(mysqli_error());
            if ($z->num_rows < 1) {
                $course = "none";
				echo' <option value="" selected="selected">...None...</option>';
				
            } else {
				$a  = mysqli_fetch_array($z);
				
				$course = $a["course_id"];
				echo' <option value="">...None...</option>';
			}
				
				
			   $query="SELECT * FROM `courses` ";
			    $cx = $conn->query($query)or die (mysqli_error());
				while( $dl= mysqli_fetch_array($cx)){
					$c_id= $dl["id"];
					$c_name= $dl["name"];
					$c_category= $dl["category"];
					

					if($c_id==$course){
					$selected='selected="selected"';
					}else{
						$selected="";
					}
					
					echo '<option id="'.$c_category.'" value="'.$c_id.'" '.$selected.'>'.$c_name.'</option>';
					
					
				} 
	   
			   ?>
               </select>
                 </div>
                 
                 
                 
                
                  <div class="form-group" id="diplomaxx">
                
                 </div>

              <input type="hidden" class="form-control" id="pjk" name="pjk" value="<?php echo $uiid ?>">
               <input type="hidden" class="form-control" id="pc" name="pc" value="<?php echo $course ?>">

                <button type="submit" name="changecourse" class="btn btn-primary block full-width m-b">Make Change</button>

             
                
            </form>
            </div>
            </div>
                </div>
                <script>
				function docheck(){
				//alert("received");	
				}
				
					function getval(sel)
{
	var diploma = document.getElementById('diplomaxx');
	    var doit=sel.value;
				$.ajax({
  type: "POST",
  data: {selected : doit},
  url: 'scripts/getselectedmodule.php',
  success: function(data) {
	  
	
		diploma.innerHTML=data; 
//return false;  
  }
});
	
	
}


window.onload = function() {
	var diploma = document.getElementById('diplomaxx');
     var e = document.getElementById("coursexx");
var text = e.options[e.selectedIndex].value;

//alert(text);

if (text!=""){
					$.ajax({
  type: "POST",
  data: {selected : text},
   url: 'scripts/getselectedmodule.php',
  success: function(data) {
	  
	
		diploma.innerHTML=data; 
//return false;  
  }
});
}
};	
</script>
                
                <?php } ?>