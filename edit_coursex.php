<?php
if(!isset($_GET['course']) || $_GET['course']=="" ){

echo'<div class="alert alert-danger">
                                No course has been specified <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>';
    
}else{
$cx=$_GET['course'];

$intaken=$_GET['ausydfgasuidgipppuwqu'];
if($intaken==""){
$zegge="(INTAKE NOT SET)";
}


	$qv= "SELECT * FROM courses WHERE `id`='".$cx."'";
 $qqv = $conn->query($qv)or die (mysqli_error());
 $n= $qqv->num_rows;
 if($n<1){
	echo'<div class="alert alert-danger">
                                Invalid Course selection <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>'; 
 }else{
	 $dvv= mysqli_fetch_array($qqv);
	$catego=$dvv["category"];
	 
	 $qvx= "SELECT * FROM intakes WHERE `type`='".$catego."' ORDER BY `date_launching` DESC";
 $qqvx = $conn->query($qvx)or die (mysqli_error());
  echo '<div class="form-group col-lg-6">
  <label for="intaken">Select Intake</label>
  <select class="form-control" id="intaken" onchange="changexps (this);">';
  echo ' <option value="">...select intake...</option>';
 while($dvvx= mysqli_fetch_array($qqvx)){
	 
	 $in_name=$dvvx["name"];
	 $datezz=date_create($dvvx["date_launching"]);
	  $in_launch=date_format($datezz,"d/m/Y");
	  $in_year=date_format($datezz,"Y");
	   $in_id=$dvvx["id"];
	   if($in_id==$intaken){
		   $selecta="selected";
		   $zegge='('.$in_name.' '.$in_year.')';
	   }else{
		$selecta="";   
	   }
	   
	   echo ' <option value="'.$in_id.'" '.$selecta.'>'.$in_name.' ('.$in_launch.')</option>';
	 
 }
echo '</select>
</div>';
	 
	 
	
   
   
  
	 
	 
	 
	 
 }
	


$valid="no";
if(in_array($_SESSION[$systemname."letype"] ?? '', ["sabrtw", "xybrtw"], true)){
    //for admin
        $valid="yes";
        
    }else if($_SESSION[$systemname."letype"]=="xtfbry"){
        //for lecturer
$q = "SELECT * FROM course_registration_lecturers WHERE `user_id`='".$_SESSION[$systemname."leido"]."' AND `course_id`='".$cx."'";
 $qq = $conn->query($q)or die (mysqli_error());
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
    <h2><?php echo $d["name"]; echo ' '.$zegge?></h2>
    <ol class="breadcrumb">
      <li> <a href="?">Home</a> </li>
      <li> <a>Courses</a> </li>
      <li class="active"> <strong>Edit Course</strong> </li>
    </ol>
  </div>
  <div class="col-lg-2"> </div>
</div>
<div class="wrapper wrapper-content  animated fadeInRight">
<div class="row" id="cbb">
<div class="col-sm-8">

<?php if($intaken==""){ 
echo'<div class="alert alert-danger">
                                Select Intake.
                            </div>';
}else{?>

  <div class="ibox">
    <div class="ibox-content">
   <!-- <button data-toggle="modal" data-target="#myModal2" type="button" class="btn btn-w-m btn-success">Add Module</button> -->
      <div class="clients-list">
        <ul class="nav nav-tabs">
          <span class="pull-right small text-muted"><?php echo $modules?> Courses </span>
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
									echo '  <li '.$classssss.'><a data-toggle="tab" href="#tab-'.$num.'"> Course </a></li>';
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
			  
			  $querry="SELECT * FROM `course_unit_notes` WHERE `course_unit`='".$axez["id"]."' && `dummy`='no' && `intake`='".$intaken."'";
			   $qq = $conn->query($querry)or die (mysqli_error());
			   $mo=$qq->num_rows;
			    if($mo<1){
					$querry="SELECT * FROM `course_unit_notes` WHERE `course_unit`='".$axez["id"]."' && `dummy`!='no'";
			   $qq = $conn->query($querry)or die (mysqli_error());
			   $mo=$qq->num_rows;
			   $typezito="(dummy)";
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
					 
					 $options='<a href="scripts/deletenotes.php?find='.$f_id.'&zambezia='.$thedocx.'&bengani='.$cx.'" type="button" class="btn btn-danger btn-xs">Delete</a> &nbsp; <a href="notes/'.$thedocx.'" type="button" class="btn btn-success btn-xs">View Notes</a>';
					echo '
					<div class="">
                                    <div class="panel panel-default">
                                        <div class="panel-heading style="font-weight: bold;">
                                            <span class="fa '.$theicon.'"></span> '.$acc["title"].' '.$typezito.'
                                        </div>
                                        <div class="panel-body">
                                            <p>'.$acc["short_message"].' </p>
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
			   	  $querry="SELECT * FROM `assignments` WHERE `course_unit`='".$axez["id"]."' && `dummy`='no' && `intake`='".$intaken."'";
			   $qq = $conn->query($querry)or die (mysqli_error());
			   $mo=$qq->num_rows;
			   if($mo>0){
				   $hg=1;
				 while($acc= mysqli_fetch_array($qq)){
					 
					 $originalDate = $acc['date_uploaded'];
					 $date_due= $acc['date_due'];
					  $thedocx = $acc['document'];
					  
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
					 
					 $options='<a href="scripts/deleteassignments.php?find='.$acc["id"].'&zambezia='.$thedocx.'&bengani='.$cx.'" type="button" class="btn btn-danger btn-xs">Delete</a>  &nbsp; <a href="assignments/agjashdbsjdhkhsdvfh/'.$thedocx.'" type="button" class="btn btn-success btn-xs">View Notes</a> &nbsp; <a href="assignments/agjashdbsjdhkhsdvfh/'.$thedocx.'" type="button" class="btn btn-success btn-xs">View Notes</a>';
					echo '
					<div class="">
                                    <div class="panel panel-info">
                                        <div class="panel-heading" style="font-weight: bold;">
                                            <span class="fa '.$theicon.'"></span>  ASSIGNMENT: '.$acc["title"].'
                                            </div>
                                        <div class="panel-body">
                                            <p>'.$acc["short_message"].'</p>
											<span style="font-size:12px;   color:#23c6c8; font-weight: bold;">HAND IN BY: '.$thed_due.' </span><br>
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
			  ?>
			  
			  <?php
			  echo'
			         

                    </div>
                </div>
				<button onclick="setnotesmodule(\''.$axez["id"].'\',\'Mod '.$numb.'\',\''.$axez["name"].'\')" type="button" class="btn btn-w-m btn-info">Add Notes</button>
				<button onclick="setassignmentmodule(\''.$axez["id"].'\',\'Mod '.$numb.'\',\''.$axez["name"].'\')" type="button" class="btn btn-w-m btn-danger">Add Assignment</button>
			   </div>
            </div>
          </div>';
									$numb++;
								 }
								?>
       
       
         
        </div>
      </div>
       <?php } ?>
    </div>
  </div>
</div>
<div class="col-sm-4">
  <div class="ibox ">
    <div class="ibox-content">
   

                            <p class="m-b-lg">
                              You can drag and drop to rearrange the Modules.
                            </p>

                            <div class="dd" id="nestable">
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
                           
                            <input id="nestable-outputxx" class="form-control" type="hidden" />
                            
                            <div id="report"></div>
                            
                              <button type="button" onclick="editlist()" class="btn btn-primary">Submit Changes</button>

                       
    </div>
  </div>
</div>
<?php
}
}

?>
<div class="modal inmodal" id="myModal2" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                <div class="modal-dialog">
                                    <div class="modal-content animated flipInY">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                                            <h4 class="modal-title"><?php echo $d["name"]; ?></h4>
                                            <small class="font-bold">Add module</small>
                                        </div>
                                        <div class="modal-body" style="background-color:#1c84c6;">
                                            <p>The module will automatically be added to the end of the list. You can always reorder the modules</p>
                                       
                                        <form method="POST" role="form" action="scripts/addmodule.php">
                                        <div class="form-group"><label class="control-label">Module Title</label>

                                    <input type="text" placeholder="Title" name="title" class="form-control"> 
                                    </div>
                                     <div class="form-group"><label class="control-label">Duration (days)</label>

                                    <input type="number" min="1" name="days" placeholder="Number of days" onkeypress="return isNumberKey(event)" class="form-control">
                                    <input type="hidden" name="course" value="<?php echo $cx ?>"  class="form-control"/>
                                   
                                    </div>
                                       <button type="submit" class="btn btn-primary">Add Module</button>
                                       </form>
                                        </div>
                                       
                                
                                        <div class="modal-footer">
                                         <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
                                            
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
<!--end of modal one-->

<div class="modal inmodal" id="myModal6" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                <div class="modal-dialog">
                                    <div class="modal-content animated flipInY">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                                            <h4 class="modal-title"><?php echo $d["name"]; ?></h4>
                                            <small class="font-bold">Add Notes</small>
                                            <h4 id="hghg" style="color: #c21033;"></h4>
                                        </div>
                                        <div class="modal-body" style="background-color:#09b8ba;">
                       
                                       
                                        <form method="POST" role="form" enctype="multipart/form-data" action="scripts/addnotes.php">
                                        <div class="form-group"><label class="control-label">Add Title</label>

                                    <input type="text" placeholder="Title" name="title" class="form-control" required="required"> 
                                    </div>
                                    <input type="hidden" value=""  name="themodule" id="themodule"/>
                                     <div class="form-group"><label class="control-label">Short Notes</label>

                                    <textarea name="shortnotes" class="form-control" required="required"></textarea>
                                    <input type="hidden" name="course" value="<?php echo $cx ?>"  class="form-control"/> 
                                     <input type="hidden" name="intake" value="<?php echo $intaken ?>"  class="form-control"/>
                                    </div>
                                    <div class="form-group">
                                      <div class="form-group">
                                      <label class="control-label">File type</label>
    <select class="form-control" name="filetype" required>
    <?php
	$quen="SELECT * FROM `filetypes` ORDER BY `name` ASC";
	  $get = $conn->query($quen)or die (mysqli_error());
								
								 while($ppp= mysqli_fetch_array($get)){
									 
									 if($ppp["id"]==3){
										 echo '  <option selected="selected" value="'.$ppp["id"].'">'.$ppp["name"].'</option>';
										 
									 }else{
										  echo '  <option value="'.$ppp["id"].'">'.$ppp["name"].'</option>';
									 }
									 
								 }
	?>

    </select>
   
  </div>
                                    </div>
                                      <div class="form-group">
                                    <input type="file" name="notesfile" required="required" class="form-control-file"/>
                                    </div>
                                       <button type="submit" class="btn btn-primary">Submit Notes</button>
                                       </form>
                                        </div>
                                       
                                
                                        <div class="modal-footer">
                                         <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
                                            
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                            <!--end of modal 2-->
                          <div class="modal inmodal" id="myModal7" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                <div class="modal-dialog">
                                    <div class="modal-content animated flipInY">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                                            <h4 class="modal-title"><?php echo $d["name"]; ?></h4>
                                            <small class="font-bold">Add Assignment</small>
                                            <h4 id="hxhx" style="color: #c21033;"></h4>
                                        </div>
                                        <div class="modal-body" style="background-color:#ed5565;">
                       
                                       
                                        <form method="POST" role="form" enctype="multipart/form-data" action="scripts/addassignment.php">
                                        <div class="form-group"><label class="control-label">Assignment Title</label>

                                    <input type="text" placeholder="Title" name="title" class="form-control" required="required"> 
                                    </div>
                                  
                                    <input type="hidden" value=""  name="themodule" id="theass"/>
                                     <div class="form-group"><label class="control-label">Short Notes</label>

                                    <textarea name="shortnotes" class="form-control" required="required"></textarea>
                                    <input type="hidden" name="course" value="<?php echo $cx ?>"  class="form-control"/> 
                                     <input type="hidden" name="intake" value="<?php echo $intaken ?>"  class="form-control"/>
                                    </div>
                                    
                                    <div class="form-inline">
                                    <div class="form-group">
                                    <label for="exampleInputEmail2">Date Due</label>
                                    <input type="date" min="<?php echo $now ?>" placeholder="Date" name="date" id="exampleInputEmail2" class="form-control" required="required">
                                </div>
                                 <div class="form-group">
                                    <label for="time" >Time (eg. 03:54 PM)</label>
                                    <input type="time" placeholder="Time" id="time" name="time" class="form-control" required="required">
                                </div>
                                </div>
                                     <div class="form-group">
                                      <label class="control-label">Expected File type</label>
    <select class="form-control" name="expectedfiletype" required>
    <?php
	$quen="SELECT * FROM `filetypes` ORDER BY `name` ASC";
	  $get = $conn->query($quen)or die (mysqli_error());
								
								 while($ppp= mysqli_fetch_array($get)){
									 
									 if($ppp["id"]==3){
										 echo '  <option selected="selected" value="'.$ppp["id"].'">'.$ppp["name"].'</option>';
										 
									 }else{
										  echo '  <option value="'.$ppp["id"].'">'.$ppp["name"].'</option>';
									 }
									 
								 }
	?>

    </select>
   
  </div>
                                  
                                    
                                    
                                    <div class="form-group">
                                      <div class="form-group">
                                      <label class="control-label">File type</label>
    <select class="form-control" name="filetype" required>
    <?php
	$quen="SELECT * FROM `filetypes` ORDER BY `name` ASC";
	  $get = $conn->query($quen)or die (mysqli_error());
								
								 while($ppp= mysqli_fetch_array($get)){
									 
									 if($ppp["id"]==3){
										 echo '  <option selected="selected" value="'.$ppp["id"].'">'.$ppp["name"].'</option>';
										 
									 }else{
										  echo '  <option value="'.$ppp["id"].'">'.$ppp["name"].'</option>';
									 }
									 
								 }
	?>

    </select>
   
  </div>
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


function editlist(){
 var a = document.getElementById("nestable-outputxx").value;
 a = a.split('{"id":').join('');
 a = a.split('}').join('');
 a = a.split('[').join('');
 a = a.split(']').join('');
  
 
var array = JSON.parse("[" + a + "]");
 
var length;

for(length=1;length<=array.length; length++){
var position=length;
var theid = array[length-1];	

$.ajax({
  type: "POST",
  data: {position : position, theid:theid},
  url: 'scripts/updateposition.php',
  success: function(data) {
	   
  }
});
}
 var report = document.getElementById("report");
 report.innerHTML='<div class="alert alert-success alert-dismissable"><button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>Process Successful</div>';
}

function setnotesmodule(thexxx,mod,tit){
	//alert(thexxx);
	$("#myModal6").modal()
document.getElementById("themodule").value=thexxx;
document.getElementById("hghg").innerHTML=mod+" ("+tit+")";
}

function setassignmentmodule(thexxx,mod,tit){
	//alert(thexxx);
	$("#myModal7").modal()
document.getElementById("theass").value=thexxx;
document.getElementById("hxhx").innerHTML=mod+" ("+tit+")";
}

function uploadnotes(){
	var notestitle = document.forms["uploadnotes"]["title"].value;
	var notesdetails = document.forms["uploadnotes"]["details"].value;
	var filename=$('#notesfile')[0].files[0].name;
	
	if (notestitle!="" && notesdetails!="" && filename!=""){
		return true;
		
	}else{
	return false;	
	}
	
	
}

 		function changexps(sel){
	
var doit=sel.value;
 if (doit!=""){
	 
	 //window.location.replace("<?php echo $domainname ?>elearning/index.php?view=addmaterialx&course=3&ausydfgasuidgipppuwqu="+doit);
	window.location.replace("<?php echo $domainname ?>/elearning/?view=addmaterialx&course=3&ausydfgasuidgipppuwqu="+doit);
			   
	   
  }
 }
</script>


