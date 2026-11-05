<?php
//session_start();
require 'scripts/connector.php';



if((isset($_GET['oiuwheilfbwehfjjasdkg']))&&(isset($_GET['xxpoupoquegvbm']))&&($_GET['oiuwheilfbwehfjjasdkg']!="")&&($_GET['xxpoupoquegvbm']!="")){
	
	$userid=$_GET['oiuwheilfbwehfjjasdkg'];
	$course=$_GET['xxpoupoquegvbm'];
	
	$a = "SELECT * FROM users WHERE `unique_id` = '".$userid."' ORDER BY id DESC";
 $b = $conn->query($a)or die (mysqli_error());
 $d= mysqli_fetch_array($b);
 $fname=$d['fname'];
  $lname=$d['lname'];
  
  $fg="SELECT * FROM `course_registration` WHERE `course_id`='".$course."' && `user_id`='".$userid."' ORDER BY `id` DESC LIMIT 1";
   $bxx = $conn->query($fg)or die (mysqli_error());
   if ($bxx->num_rows > 0) {
   $dxx= mysqli_fetch_array($bxx);
   $intake=$dxx['intake'];
   
   $fgp="SELECT * FROM `courses` WHERE `id`='".$course."'";
   $bxxp= $conn->query($fgp)or die (mysqli_error());
   $dxxp= mysqli_fetch_array($bxxp);
   $coursename=$dxxp['name'];
   $shortnotes=$dxxp['short_notes'];
   
   
   $fgq="SELECT * FROM `intakes` WHERE `id`='".$intake."'";
   $bxxq = $conn->query($fgq)or die (mysqli_error());
   $dxxq= mysqli_fetch_array($bxxq);
   $datelaunch=$dxxq['date_launching'];
  
   $monthname=$dxxq['name'];
   $theyear=date("Y", strtotime($dxxq['date_launching']));
   
   
   
   }else{
	header("Location: ./");	   
   }
	
}else{
 header("Location: ./");	
	
}

?>

<!DOCTYPE html>
<html>



<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">

    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Elevate Her 360 | Course Outline</title>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">

    <!-- Toastr style -->
    <link href="css/plugins/toastr/toastr.min.css" rel="stylesheet">

    <link href="css/animate.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<!-- <body style="background-color:#f3f3f4;">

    <div id="wrapperx">
      <div id="page-wrapperx" class="gray-bg">
     
            <div class="row wrapper border-bottom white-bg page-heading" style="padding-left:20px; padding-right:20px;">
                <div class="col-lg-10">
                  <a href="../"> <h2>  <img src="images/syx_images/logo.png" style="width:50px;"/> Elevate Her 360</h2></a>
                    <ol class="breadcrumb">
                        <li>
                            <a href="./">Home</a>
                        </li>
                        
                        <li class="active">
                            <strong>My Course Outline</strong>
                        </li>
                    </ol>
                </div>
                <div class="col-lg-2">

                </div>
            </div> -->

        <div class="wrapper wrapper-content  animated fadeInRight article">
            <div class="row">
                <div class="col-lg-10 col-lg-offset-1">
                    <div class="ibox">
                        <div class="ibox-content">
                           <!-- <div class="pull-right">
                                <a href="https://elevateher360.org/"class="btn btn-primary btn-xs" type="button">Go to Site</a>
                              <a href="./" class="btn btn-success btn-xs" type="button">Go to Elearning</a>
                                
                            </div>-->
                            <div class="text-center article-title">
                            <span class="text-muted"><i class="fa fa-clock-o"></i> <?php echo date("d/m/Y h:i a") ?></span>
                                <h1>
                                    Study Plan for <a href="./elearning/"style="color: #fd9017;
    font-weight: 400;
    font-style: italic;"><?php echo $fname.' '.$lname?></a>
                                </h1>
                                <br>
                                <h2><?php echo $coursename ?></h2>
                                  <h4>( <?php echo $monthname.' '.$theyear ?> intake )</h4>
                            </div>
                            <h4 style="color: #690303;">COURSES</h4>
                            <?php
							 $fgqc="SELECT * FROM `course_unit` WHERE `course_id`='".$course."' ORDER BY `order_number` ASC";
   $bxxqc = $conn->query($fgqc)or die (mysqli_error());
   $startdate=$datelaunch;
   while($dxxqc= mysqli_fetch_array($bxxqc)){
	 $modulename=$dxxqc['name'];  
	 $moduleduration=$dxxqc['duration']; 
	 
	 if($startdate==$datelaunch){
		$beg=$startdate; 
	 }else{
	$beg = date('d/m/Y', strtotime($startdate . " + 1 day")); 
	 }
	 
	 $end = date('d/m/Y', strtotime($beg . " + ".$moduleduration." day")); 
	 $startdate=$end;
	 
	 echo '<li style="display: block;
    position: relative;
    margin: 0;
    padding: 0;
    min-height: 20px;
    font-size: 15px;
    line-height: 20px;    border: 1px dashed #e7eaec;
    background: #f3f3f4;
    padding: 10px;
}"> <strong>'.$beg.' - '.$end.'</strong> &nbsp; &nbsp;'.$modulename.'</li>';
	 //$Date2 = date('Y-m-d', strtotime($Date1 . " + 1 day"));
   }
   

							
							
							?>
                            <p>
                            <br></br>
                            <h4 style="color: #690303;">About <?php echo $coursename ?></h4>
                          <?php echo $shortnotes ?></p>
                            
                            <hr>
                            <div class="row"></div>

                            <div class="row"></div>


                        </div>
                    </div>
                </div>
            </div>


        </div>
        

        </div>
        </div>



    <!-- Mainly scripts -->
    <script src="js/jquery-2.1.1.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src=".js/plugins/metisMenu/jquery.metisMenu.js"></script>
    <script src="js/plugins/slimscroll/jquery.slimscroll.min.js"></script>

    <!-- Custom and plugin javascript -->
    <script src="js/inspinia.js"></script>
    <script src="js/plugins/pace/pace.min.js"></script>

</body>



</html>
