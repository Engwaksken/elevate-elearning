<link href="css/plugins/datapicker/datepicker3.css" rel="stylesheet">
        <div class="row wrapper border-bottom white-bg page-heading">
      <div class="col-lg-10">
                    <h2><?php echo $systemname?> Courses</h2>
                    <ol class="breadcrumb">
                        <li>
                            <a href="index.php">Home</a>
                        </li>
                        <li>
                            <a>Courses</a>
                        </li>
                        <li class="active">
                            <strong>Intakes</strong>
                        </li>
                    </ol>
                </div>
                <div class="col-lg-2">

                </div>
                
            </div>
                         
                          <div class="clients-list">
                            <ul class="nav nav-tabs">
                            <?php
$q = "SELECT * FROM intakes WHERE '".$now."'>`date_launching`";
 $qq = $conn->query($q)or die (mysqli_error());
$number= $qq->num_rows;
echo'<span class="pull-right small text-muted">'.$number.' intakes</span>';
							?>
                            
                            </ul>
                            <div class="tab-content">
                                <div id="tab-1" class="tab-pane active">
                                    <div class="full-height-scroll">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <tbody>
                                                <?php 
$a = "SELECT * FROM intakes WHERE '".$now."'<`date_launching` ORDER BY `date_launching` ASC";
 $b = $conn->query($a)or die (mysqli_error());
 if ($b->num_rows < 1) {
echo'<div class="alert alert-danger">
  <strong>0 intakes available.
</div>'; 

  
   
   }else{
	   $num=1;
	   while($d= mysqli_fetch_array($b)){
		   $inid=$d["id"];
		   $name=$d["name"];
		   $intake_type=$d["type"];
		   $d_launching=$d["date_launching"];
       $d_ending=$d["date_ending"];
		   if($intake_type=="1"){
			   $thetype="Short Course";
		   }else{
			   $thetype="Diploma";
		   }

	   
	   
	   echo'<tr>
	   <td>'.$num.'</td>

                                                    <td><a data-toggle="tab" class="client-link">'.$name.'</a></td>
                                                    <td>'.$thetype.'</td>
                               
                
                                                    <td class="client-status">'.$d_launching.'</td>
                                                    <td class="client-status">'.$d["date_ending"].'</td>
													<td><a href="view=intake_edit?&intake='.$inid.'&vf=intakes"><span class="label label-success">Edit Intake</span></a></td>
														<td><a href="scripts/removeintake.php?intake='.$inid.'&vf=intakes"><span class="label label-danger">Remove</span></a></td>
                                                </tr>';
												$num++;
   }
   }
	   ?>
                                               
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                               
                     
                   
                            </div>

                            </div>
                        </div>
                    </div>
                </div>
                </div>
                
              
                <div class="col-sm-4" id="editintake">
                    <div class="ibox ">

                        <div class="ibox-content">
                                       <form class="m-t" role="form" method="post" action="scripts/editintake.php" >
             
 <div class="form-group" id="data_3">
                 <label>Intake Date *</label>
                <div class="input-group date">
                                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span><input type="text" class="form-control" value="" placeholder="Intake Date" id="dob" name="dob" required="" readonly="readonly"  >
                                </div>
                                </div>
                                <div class="form-group" id="data_4">
    <label>Ending Date *</label>
    <div class="input-group date">
        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
        <input type="text" class="form-control" value="" placeholder="Ending Date" id="date_ending" name="date_ending" required readonly="readonly">
    </div>
</div>

              
                   <div class="form-group">
                 <label>Course Category *</label>
                 <select class="form-control" name="course">
                
               <?php
			 
				
            $p = "SELECT * FROM course_category";
            $z = $conn->query($p) or die(mysqli_error());
            if ($z->num_rows < 1) {
               
				echo' <option value="" selected="selected">...None...</option>';
				
            } else {
				echo' <option value="">...None...</option>';
				while($a  = mysqli_fetch_array($z)){
				
				$category = $a["name"];
				$category_id = $a["id"];
				
		
					
					echo '<option value="'.$category_id.'">'.$category.'</option>';
					
				}
				} 
	   
			   ?>
               </select>
                 </div> 
  
                 <div class="form-group">
                 <button type="submit" name="submit1" class="btn btn-primary block full-width m-b">Update Intake</button>
</div>
              <div class="form-group">

                 <button type="button" class="btn btn-white block full-width m-b" onclick="hideDiv()" >Cancel</button></div>
                
            </form>
                        
                      
                </div>
            </div>
        
       <script>
	   function changecourse(sel){
		   var doit=sel.value;
		   
		   if (doit!=""){
			$.ajax({
  type: "POST",
  dataType: "json",
  data: {id : doit},
  url: 'scripts/getcoursepipo.php',
  success: function(data) {
    alert(data);
  }
});   
		   }
		   
	   }
	   
	   
	   
	   
	   function showDiv() {
    div = document.getElementById('editintake');
    div.style.display = "block";
}
	   function hideDiv() {
    div = document.getElementById('editintake');
    div.style.display = "none";
}
$('#dob, #date_ending').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true
    });
	   </script>

        </div>
        
