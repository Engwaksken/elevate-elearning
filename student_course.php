<?php
if(!isset($_GET['course']) || $_GET['course']=="" ){

echo'<div class="alert alert-danger">
                                No course has been specified <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>';
    
}else{
$cx=$_GET['course'];
$valid="no";
if($_SESSION[$systemname."letype"]=="afgezt"){
        //for student
$q = "SELECT * FROM course_registration WHERE `user_id`='".$_SESSION[$systemname."leido"]."' AND `course_id`='".$cx."'";
 $qq = $conn->query($q)or die (mysqli_error());
  $dvc= mysqli_fetch_array($qq);
  $intaken=$dvc["intake"];
$number= $qq->num_rows;
if($number>0){
    $valid="yes";
}else{
$valid="no";    
}
        
    }
if ($valid!="yes"){
echo'<div class="alert alert-danger">
                                Invalid course selection <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>';    
}else{

$a = "SELECT * FROM courses WHERE `id`='".$cx."'";
 $b = $conn->query($a)or die (mysqli_error());
 if ($b->num_rows < 1) {
echo'<div class="alert alert-danger">
                                Course not available <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>';

   }else{
       $d= mysqli_fetch_array($b);
         $cat=$d["category"];
        
        
//pick course
$p = "SELECT * FROM course_category WHERE id = '".$cat."'";
 $z = $conn->query($p)or die (mysqli_error());
 if ($z->num_rows < 1) {
 $course="N/A";
 }else{
     $a= mysqli_fetch_array($z);

    $course=$a['name']; 
 
 }
 
 
   }
   
   $moduleget="SELECT * FROM `course_unit` WHERE `course_id`='".$cx."' ORDER BY `order_number`";
    $gett = $conn->query($moduleget)or die (mysqli_error());
	$modules=$gett->num_rows;

?>

<div class="row wrapper border-bottom white-bg page-heading">
  <div class="col-lg-10">
    <h2><?php echo $d["name"]; ?></h2>
    <ol class="breadcrumb">
      <li> <a href="?">Home</a> </li>
      <li> <a>Courses</a> </li>
      <li class="active"> <strong>My courses</strong> </li>
    </ol>
  </div>
  <div class="col-lg-2"> </div>
</div>
<div class="wrapper wrapper-content  animated fadeInRight">
<div class="row">
<div class="col-sm-8">

  <div class="ibox">
    <div class="ibox-content">
    
      <div class="clients-list">
        <ul class="nav nav-tabs">
          <span class="pull-right small text-muted"><?php echo $modules?> Modules</span>
            <?php
			$modulegetq="SELECT * FROM `course_unit` WHERE `course_id`='".$cx."' ORDER BY `order_number`";
    $gettq = $conn->query($modulegetq)or die (mysqli_error());
								$num=1;
								 while($axe= mysqli_fetch_array($gettq)){
									if($num==1){
									$classssss='class="active"';	
									}else{
									$classssss='';	
									}
									echo '  <li '.$classssss.'><a data-toggle="tab" href="#tab-'.$num.'"> Mod '.$num.'</a></li>';
									$num++;
								 }
								?>
        
          
        </ul>
        <div class="tab-content">
            <?php
								$numb=1;
								 while($axez= mysqli_fetch_array($gett)){
									
									if ($numb==1){
										$classex="active";
									}else{
									$classex="";	
									}
									echo '   <div id="tab-'.$numb.'" class="tab-pane '.$classex.'">
            <div class="full-height-scroll">
              <div class="table-responsive"> 
			 
			  <div class="ibox float-e-margins">
                    <div class="ibox-title">
                        <h5>'.$axez["name"].'</h5>
                        <div class="ibox-tools">
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                            
                           
                        </div>
                    </div>
                    <div class="ibox-content">

                       
                          
                          
                     
			  ';
			  
			  $querry="SELECT * FROM `course_unit_notes` WHERE `course_unit`='".$axez["id"]."'  && `dummy`='no' && `intake`='".$intaken."'";
			   $qq = $conn->query($querry)or die (mysqli_error());
			   $mo=$qq->num_rows;
			     if($mo<1){
					$querry="SELECT * FROM `course_unit_notes` WHERE `course_unit`='".$axez["id"]."' && `dummy`!='no'";
			   $qq = $conn->query($querry)or die (mysqli_error());
			   $mo=$qq->num_rows;
			   $typezito="(standard)";
				}else{
				 $typezito="";	
				}
			   if($mo>0){
				   $hg=1;
				 while($acc= mysqli_fetch_array($qq)){
					 
					 $originalDate = $acc['date_uploaded'];
					 $thedocx = $acc['document'];
					  $thefiletype = $acc['file_type'];
					  if($thefiletype=="1"){
						  //audio
						  $theicon="fa-file-audio-o";
						  
					  }else  if($thefiletype=="2"){
						  //video
						  $theicon="fa-file-video-o";
						  
					  }else  if($thefiletype=="3"){
						  //document
						  $theicon="fa-file-text-o";
						  
					  }else  if($thefiletype=="4"){
						  //image
						  $theicon="fa-file-image-o";
						  
					  }
					 $f_id = $acc['id'];
$newDate = date("l d-m-Y h:i a", strtotime($originalDate));

$newq="SELECT `fname`, `lname` FROM `users` WHERE `unique_id`='".$acc['uploaded_by']."'";
$qqxc = $conn->query($newq);
$accxx= mysqli_fetch_array($qqxc);

$name=$accxx['fname'].' '.$accxx['lname'];
					 
        $options = '<a href="notes/' . $thedocx . '" target="_blank" class="btn btn-success btn-xs">View Notes</a>';

					echo '
					<div class="">
                                    <div class="panel panel-default">
                                        <div class="panel-heading style="font-weight: bold;">
                                            <span class="fa '.$theicon.'"></span> '.$acc["title"].' '.$typezito.'
                                        </div>
                                        <div class="panel-body">
                                            <p>'.$acc["short_message"].'</p>
											<span style="font-size:9px;  font-style: italic; color:#940101;">'.$newDate.' </span>
                                        </div>
										<div class="panel-footer">
                                          <span class="badge">added by '.$name.'</span>  <span style="float:right;">'.$options.'</span>
                                        </div>

                                    </div>
                                </div>
					
					';
							$hg++;
				 }
			   }
			   
			   
			   
			   
			   ///for assignments
			   	  $querry="SELECT * FROM `assignments` WHERE `course_unit`='".$axez["id"]."'  && `dummy`='no' && `intake`='".$dvc."'";
			   $qq = $conn->query($querry)or die (mysqli_error());
			   $mo=$qq->num_rows;
			   if($mo>0){
				   $hg=1;
				 while($acc= mysqli_fetch_array($qq)){
					 
					 $originalDate = $acc['date_uploaded'];
					 $date_due= $acc['date_due'];
					 $ass_id= $acc['id'];
					  $thedocx = $acc['document'];
					  
					  $thefiletype = $acc['file_type'];
					  if($thefiletype=="1"){
						  //audio
						  $theicon="fa-file-audio-o";
						  
					  }else  if($thefiletype=="2"){
						  //video
						  $theicon="fa-file-video-o";
						  
					  }else  if($thefiletype=="3"){
						  //document
						  $theicon="fa-file-text-o";
						  
					  }else  if($thefiletype=="4"){
						  //image
						  $theicon="fa-file-image-o";
						  
					  }
$newDate = date("l d-m-Y h:i a", strtotime($originalDate));

$thed_due = date("l d-m-Y h:i a", strtotime($date_due));

$newq="SELECT `fname`, `lname` FROM `users` WHERE `unique_id`='".$acc['uploaded_by']."'";
$qqxc = $conn->query($newq);
$accxx= mysqli_fetch_array($qqxc);

$name=$accxx['fname'].' '.$accxx['lname'];
					 
					// $options='<a href="assignments/agjashdbsjdhkhsdvfh/'.$thedocx.'" type="button" class="btn btn-success btn-xs">View Assignments</a>';
					
					if ($modidx==$mymodule){
										 $options='<a href="assignments/agjashdbsjdhkhsdvfh/'.$thedocx.'" type="button" class="btn btn-success btn-xs">View Assignment</a>';
									}else{
									  $options='<a href="assignments/agjashdbsjdhkhsdvfh/'.$thedocx.'" type="button" class="btn btn-success btn-xs">Download</a>';
									}
				
					echo '
					<div class="">
                                    <div class="panel panel-info">
                                        <div class="panel-heading" style="font-weight: bold;">
                                            <span class="fa '.$theicon.'"></span> ASSIGNMENT: '.$acc["title"].'
                                        </div>
                                        <div class="panel-body">
                                            <p>'.$acc["short_message"].'</p>
											<span style="font-size:12px;   color:#23c6c8; font-weight: bold;">HAND IN BY: '.$thed_due.' </span><br>
											<span style="font-size:9px;  font-style: italic; color:#940101;">'.$newDate.' </span>
											
											<br><br><br><button onclick="setassignmentmodule(\''.$ass_id.'\',\'Mod '.$numb.'\',\''.$acc["title"].'\',\''.$acc["expectedtype"].'\')" type="button" class="btn btn-w-m btn-info">Submit Assignment</button>
                                        </div>
										<div class="panel-footer">
                                          <span class="badge">Submitted by '.$name.'</span>  <span style="float:right;">'.$options.'</span>
                                        </div>

                                    </div>
                                </div>
					
					';
							$hg++;
				 }
			   }
			  ?>
			  
			  <?php
			  echo'
			         

                    </div>
                </div>
			
				
			   </div>
            </div>
          </div>';
									$numb++;
								 }
								?>
        
         
        </div>
      </div>
      
    </div>
  </div>
</div>
<div class="col-sm-4">
  <div class="ibox ">
    <div class="ibox-content">
   

                         

                            <div class="dd" >
                                <ol class="dd-list">
                                <?php
								$module="SELECT * FROM `course_unit` WHERE `course_id`='".$cx."' ORDER BY `order_number`";
    $ge = $conn->query($module)or die (mysqli_error());
								$num=1;
								 while($axes= mysqli_fetch_array($ge)){
									echo ' <li class="dd-item" data-id="'.$axes["id"].'">
                                        <div class="dd-handle">'.$num.' - '.$axes["name"].' ('.$axes["duration"].' days)</div>
                                    </li>'; 
									$num++;
								 }
								?>
                                   
                                  
                                </ol>
                            </div>
                           
                            
                            
                          
                            
                              

                       
    </div>
  </div>
</div>
<?php
}
}
?>


                            <!--end of modal 2-->
                          <div class="modal inmodal" id="myModal7" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                <div class="modal-dialog">
                                    <div class="modal-content animated flipInY">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                                            <h4 class="modal-title"><?php echo $d["name"]; ?></h4>
                                            <small class="font-bold">Submit Assignment</small>
                                            <h4 id="hxhx" style="color: #c21033;"></h4>
                                        </div>
                                        <div class="modal-body" style="background-color:#23c6c8;">
                       
                                       
                                        <form method="POST" role="form" enctype="multipart/form-data" action="scripts/submitassignment.php">
                                      
                                    <input type="hidden" value=""  name="themodule" id="theass"/>
                                    <input type="hidden" name="course" value="<?php echo $cx ?>"  class="form-control"/> 
                                    
                                    
                                 
                                     <div class="form-group">
                                      <label class="control-label">Expected File type</label>
    <select class="form-control" name="expectedfiletype" id="ninga" required>


    </select>
   
  </div>
                                  
                                    
                                    
                                  
                                      <div class="form-group">
                                    <input type="file" name="notesfile" required="required" class="form-control-file"/>
                                    </div>
                                       <button type="submit" class="btn btn-primary">Upload Assignment</button>
                                       </form>
                                        </div>
                                       
                                
                                        <div class="modal-footer">
                                         <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
                                            
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>

<script>
function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode;
    if ((charCode < 48 || charCode > 57))
        return false;

    return true;
}




function setnotesmodule(thexxx,mod,tit){
	//alert(thexxx);
	$("#myModal6").modal()
document.getElementById("themodule").value=thexxx;
document.getElementById("hghg").innerHTML=mod+" ("+tit+")";
}

function setassignmentmodule(thexxx,mod,tit,expected){
	//alert(thexxx);
	$("#myModal7").modal()
	var thesel,thename;
	if(expected=="1"){
		thename='Audio';
	thesel='<option selected="selected" value="'+thesel+'">'+thename+'</option>';	
	}else if(expected=="2"){
		thename='Video';
	thesel='<option selected="selected" value="'+thesel+'">'+thename+'</option>';	
	}else if(expected=="3"){
		thename='Document';
	thesel='<option selected="selected" value="'+thesel+'">'+thename+'</option>';	
	}else if(expected=="4"){
		thename='Image';
	thesel='<option selected="selected" value="'+thesel+'">'+thename+'</option>';	
	}
document.getElementById("theass").value=thexxx;
document.getElementById("hxhx").innerHTML=mod+" ("+tit+")";
document.getElementById("ninga").innerHTML=thesel;
}



</script>


