
<div class="row wrapper border-bottom white-bg page-heading">
  <div class="col-lg-10">
    <h2><?php echo $systemname?> Accountants</h2>
    <ol class="breadcrumb">
      <li> <a href="?">Home</a> </li>
      <li> <a>Users</a> </li>
      <li class="active"> <strong>Accounts</strong> </li>
    </ol>
  </div>
  <div class="col-lg-2"> </div>
</div>
<div class="wrapper wrapper-content  animated fadeInRight">
<div class="row">
  <div class="col-sm-8">
    <div class="ibox">
      <div class="ibox-content"> <span class="text-muted small pull-right">Last entry: <i class="fa fa-clock-o"></i> </span>
        <h2>Accountants</h2>
        
        <div class="clients-list">
          <ul class="nav nav-tabs">
            <?php
$q = "SELECT * FROM users WHERE acc_type = 'zyffwq'";
 $qq = $conn->query($q)or die (mysqli_error());
$number= $qq->num_rows;
echo'<span class="pull-right small text-muted">'.$number.' Accountants</span>';
							?>
            
          
           
          </ul>
          <div class="tab-content">
            <div id="tab-1" class="tab-pane active">
              <div class="full-height-scroll">
                <div class="table-responsive">
                  <table class="table table-striped table-hover">
                    <tbody>
                      <?php 
$a = "SELECT * FROM users WHERE acc_type = 'zyffwq' ORDER BY id DESC";
 $b = $conn->query($a)or die (mysqli_error());
 if ($b->num_rows < 1) {
echo'<div class="alert alert-danger">
  <strong>No Accountants to display.</strong>
</div>'; 

  
   
   }else{
	   while($d= mysqli_fetch_array($b)){
		   $std_id=$d["unique_id"];
	   if($d["verified"]=="yes"){
		$ver='<a href="deactivate?id='.$d["unique_id"].'&xts=view_accounts"><span class="label label-primary">Active</span></a>';     
	   }else{
		 $ver='<a href="activate?id='.$d["unique_id"].'&xts=view_accounts"><span class="label label-info">Not Active</span></a>'; 
	   }
  if($d["unique_id"]!=$unik){
$chatter='<a href="?view=chatwith&pr='.$d["unique_id"].'"><span class="label label-success">Chat</span></a>';
	   }else{
		$chatter="";   
	   }
//pick course
$p = "SELECT * FROM course_registration WHERE user_id = '".$std_id."' ORDER BY id DESC";
 $z = $conn->query($p)or die (mysqli_error());
 if ($z->num_rows < 1) {
 $course="N/A";
 }else{
	 $a= mysqli_fetch_array($z);
	$pp = "SELECT name FROM courses WHERE id = '".$a["course_id"]."' ORDER BY id DESC";
 $ll = $conn->query($pp)or die (mysqli_error()); 
 if ($ll->num_rows < 1) {
	$course="N/A"; 
 }else{
	 $qw= mysqli_fetch_array($ll);
	$course=$qw['name']; 
 }
 }
$dpc= $d["dp"];
if ($dpc=="default.jpg"){
					$dpp= ' <img alt="image" class="img-circle profile-top" src="images/default_p_pics/user.png" />';
					}else{
						$dpp= ' <img alt="image" class="img-circle profile-top" src="images/user_p_pics/'.$dpc.'" />';
					}	   
	   
	   echo'<tr>
                                                    <td class="client-avatar">'.$dpp.' </td>
                                                    <td><a data-toggle="tab" class="client-link" onclick="openuser(\''.$d["unique_id"].'\')">'.$d["fname"].' '.$d["lname"].'</a></td>
                                                    <td>'.$course.'</td>
                                                    <td class="contact-type"><i class="fa fa-envelope"> </i></td>
                                                    <td>'.$d["email"].'</td>
                                                    <td class="client-status">'.$ver.' '.$chatter.'</td>
                                                </tr>';
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
  <div class="col-sm-4">
    <div class="ibox ">
      <div class="ibox-content">
        <div class="tab-content" id="showit">
      </div>
    </div>
  </div>
</div>
