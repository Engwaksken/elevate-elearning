<?php
ob_start();
session_start();
require 'scripts/connector.php';


if (((isset($_SESSION[$systemname."loggedin"]))&&($_SESSION[$systemname."loggedin"] != ''))&&((isset($_SESSION[$systemname."email"]))&&($_SESSION[$systemname."email"] != ''))){
	
	$firstname=strip_tags(trim($_SESSION[$systemname."firstname"] ?? ''));
	$lastname=strip_tags(trim($_SESSION[$systemname."lastname"] ?? ''));
	
	$unik=strip_tags(trim($_SESSION[$systemname."leido"] ?? ''));
	
	if ($unik == '') {
		header('Location: logout');
		die();
	}
	
	$checksystem = "SELECT * FROM users WHERE unique_id = '".$conn->real_escape_string($unik)."'";
	$check = $conn->query($checksystem)or die (mysqli_error($conn));

	if ($check->num_rows < 1) {
		header('Location: logout');
		die();	
	}else{
		$data= mysqli_fetch_array($check);

        /*
        |--------------------------------------------------------------------------
        | Rebuild dashboard role from users.acc_type
        |--------------------------------------------------------------------------
        | users.acc_type already stores:
        | sabrtw = Sup Admin
        | xybrtw = Admin
        | afgezt = Student
        | xtfbry = Instructor
        | zyffwq = Accounts
        |--------------------------------------------------------------------------
        */
        $acc_type = strtolower(trim($data["acc_type"] ?? ''));

        if (in_array($acc_type, array("sabrtw", "xybrtw", "afgezt", "xtfbry", "zyffwq"), true)) {
            $_SESSION[$systemname."letype"] = $acc_type;
        } else {
            $_SESSION[$systemname."letype"] = "afgezt";
        }

		/*
		|--------------------------------------------------------------------------
		| MFA / Verification Gate
		|--------------------------------------------------------------------------
		*/
		if (((int)($data["email_verified"] ?? 0) !== 1) || ((int)($data["verified"] ?? 0) !== 1)) {

			$_SESSION['pending_verify_user_id'] = (int)$data["id"];
			$_SESSION['pending_verify_email'] = $data["email"];

			if (empty($_SESSION['verify_csrf_token'])) {
				$_SESSION['verify_csrf_token'] = bin2hex(random_bytes(32));
			}

			$_SESSION[$systemname."errormsg"] = "Please verify your login before accessing the dashboard.";

			header('Location: verify');
			die();
		}

		$dp= $data["dp"];
		$studentnumber= $data["student_no"];
	   
		if ($dp=="default.jpg"){
			$dpf='images/default_p_pics/user.png';
		}else{
			$dpf= 'images/user_p_pics/'.$dp;
		}
	}
	
	if($_SESSION[$systemname."letype"]=="sabrtw"){
		$mytype="Sup Admin";
		$studentnumber="";
		
	}else if($_SESSION[$systemname."letype"]=="xybrtw"){
		$mytype="Admin";
		$studentnumber="";
		
	}
	else if($_SESSION[$systemname."letype"]=="afgezt"){
		$mytype="Student";
		$studentnumber=$studentnumber;
		
	}else if($_SESSION[$systemname."letype"]=="xtfbry"){
		$mytype="Instructor";
		$studentnumber="";
		
	}else if($_SESSION[$systemname."letype"]=="zyffwq"){
		$mytype="Accounts";
		$studentnumber="";
		
	}
	
	
}else{
	header('Location: login');
	die();	
}
?>
<!DOCTYPE html>
<html>
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/favicon.png">

    <title><?php echo $systemname; ?> | Home</title> 
   
<!-- Bootstrap -->
<link href="css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6 (CDN) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- DataTables Core + Buttons -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

<!-- Other Styles -->
<link href="css/plugins/morris/morris-0.4.3.min.css" rel="stylesheet">
<link href="js/plugins/gritter/jquery.gritter.css" rel="stylesheet">
<link href="css/animate.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
<link href="app.css?v=<?php echo (int)filemtime(__DIR__ . '/app.css'); ?>" rel="stylesheet">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

<!-- Export Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>


  <!-- Chatbot Start -->
<!-- <script src="https://chatbot.witu.org/chatbot.js" defer></script> -->

<!-- Chatbot End -->

<script src="https://cdn.userway.org/widget.js" data-account="eTQIaGVnrz"></script>
</head>



<body>



    <div id="wrapper">
<?php
	if($_SESSION[$systemname."letype"]=="sabrtw"){
		include "menu_sup_admin.php";
		
	}
  else if($_SESSION[$systemname."letype"]=="xybrtw"){
		include "menu_admin.php";
		
	}
  else if($_SESSION[$systemname."letype"]=="afgezt"){
		include "menu_student.php";
		
	}else if($_SESSION[$systemname."letype"]=="xtfbry"){
		include "menu_instructor.php";
		
	}else if($_SESSION[$systemname."letype"]=="zyffwq"){
		include "menu_accounts.php";
		
	}
?>

        <div id="page-wrapper" class="gray-bg">
        <div class="row border-bottom">
        <nav class="navbar navbar-static-top white-bg" role="navigation" style="margin-bottom: 0">
        <div class="navbar-header">
            <a class="navbar-minimalize minimalize-styl-2 btn btn-primary " href="#"><i class="fa fa-bars"></i> </a>
        </div>
            <ul class="nav navbar-top-links navbar-right">
                <li>
                    <a href="../" class="m-r-sm text-muted welcome-message">Return to site</a>
                </li>
               

                <li>
                    <a href="logout">
                        <i class="fa fa-sign-out"></i> Log out
                    </a>
                </li>
                <li>
                    <a class="right-sidebar-toggle">
                        <i class="fa fa-tasks"></i>
                    </a>
                </li>
            </ul>

        </nav>
        </div>
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
			 $page = isset($_GET['view']) ? $_GET['view'] : '';
    switch ($page) {
    
     case "logins":
            include "login_logs.php";
            break;
        case "profile":
            include "profile.php";
            break;
		case "create":
            include "create_user.php";
            break;
            
           	case "all_branches":
            include "branches.php";
            break;
             	case "add_branch":
            include "add_branch.php";
            break;
             	case "edit_branch":
            include "scripts/edit_branch.php";
            break;
            case "certificate_templates":
    include "certificate_templates.php";
    break;
    case "recommend_certificates":
    include "recommend_certificates.php";
    break;
    
			case "create_course":
            include "create_course.php";
            break;
            
            
            case "mindsets":
            include "mindsets.php";
            break;
			case "todo":
            include "todolist.php";
            break;
			case "editcourse":
            include "editcourse.php";
            break;
            
            case "update_course":
            include "edit_course.php";
            break;
			
			case "change_pic":
            include "change_dp.php";
            break;
			case "subby":
            include "subby_lec.php";
            break;
			case "addrecord":
            include "addpayment.php";
            break;
			case "viewrecord":
            include "viewuserrecord.php";
            break;
			case "viewpayments":
            include "payments_person.php";
            break;
			case "change_course":
            include "change_course.php";
            break;
			case "change_courselec":
            include "change_courselec.php";
            break;
			case "intakes":
            include "intakes_admin.php";
            break;
			case "add_intake":
            include "add_intake.php";
            break;
            
            case "intake_edit":
            include "intake_edit.php";
            break;
            
			case "editintake":
            include "scripts/editintake.php";
            break;
            
            case "edit_student":
            include "edit_student.php";
            break;
            case "edit_instructor":
            include "edit_instructor.php";
            break;
            
            
			case "all_courses":
            include "all_courses.php";
            break;
           
             
			case "study_plan":
            include "study_plan.php";
            break;
            
            	case "submit_ass":
            include "scripts/submitassignment.php";
            break;
           //free courses 
              
			case "other_resources":
            include "other_resources.php";
            break;
            
  	case "insert_free_courses":
            include "insert_courses.php";
            break;
            
   case "view_more_resources":
            include "view_free_courses.php";
            break;
   case "update_resource":
            include "update_resource.php";
            break;
   case "student_more_resources":
            include "student_more_resources.php";
            break;
             case "announcements":
            include "announcements.php";
            break;
         
   case "more_resources":
            include "more_resources.php";
            break;
         case "delete_course":
            include "delete_course.php";
            break;
          
            
			case "open_course":
            include "curr_course.php";
            break;
			case "open_coursex":
            include "curr_courselec.php";
            break;
            case "addmarks":
            include "addmarks.php";
            break;
            
   case "delete_marks":
            include "scripts/delete_marks.php";
            break;
			case "addmaterial":
            include "edit_course.php";
            break;
			case "addmaterialx":
            include "edit_coursex.php";
            break;
			case "view_admins":
            include "users_admins.php";
            break;
			case "view_instructors":
            include "users_lecturers.php";
            break;
              case "add_announcement":
            include "add_announcement.php";
            break;
              case "my_announcements":
            include "instructor_announcements.php";
            break;
            case 'quiz_builder':
    include 'quiz_builder.php';
    break;

			case "my_courses":
   if($_SESSION[$systemname."letype"]=="xtfbry"){
		include "my_courses_lec.php";
		
	}else{
		include "my_courses_stu.php";
		
	}
            break;
            	case "view_sup_admins":
            include "users_sup_admins.php";
            break;
			case "view_admin":
            include "users_admins.php";
            break;
			case "view_students":
            include "users_students.php";
            break;
			case "view_accounts":
            include "users_accounts.php";
            break;
            
            case "edit_admin":
            include "edit_admin.php";
            break;
            
            
             case "all_assignments":
            include "all_assignments.php";
            break;
            
             case "edit_submission":
            include "edit_submission.php";
            break;
             case "graded_submissions":
            include "graded_submissions.php";
            break;
            
             case "ungraded_submissions":
            include "ungraded_submissions.php";
            break;
            
            case "study_plan":
            include "study_plan.php";
            break;
            
            case "my_students":
            include "my_students.php";
            break;
            
            case "download_file":
            include "download_file.php";
            break;
            
             case "submission_details":
            include "submission_details.php";
            break;
        
			case "chatroom":
      
            
			  if($_SESSION[$systemname."letype"]=="xtfbry"){
		include "chatroom_lec.php";
		
	}
  else{
		include "chatroom.php";
		
	}
            break;
			case "chat_ad":
            include "chat_admin.php";
            break;
			case "chatwith":
            include "chatwith.php";
            break;
			case "chatroom_ad":
            include "chatroom_admin.php";
            break;
			case "open_chatroom":
            include "open_chatroom.php";
            break;
			case "create_chatroom":
            include "create_chatroom.php";
            break;
		case "study_plan":
            include "study_plan.php";
            break;

	case "my_assignment":
            include "student_assignments.php";
            break;
            	case "delete_student":
            include "delete_student.php";
            break;
            
            case "my_certificates":
    include "my_certificates.php";
    break;
      
            case "mindset":
    include "mindset.php";
    break;
    case 'take_quiz':
    include 'take_quiz.php';
    break;

case 'material_engagement':
    include 'material_engagement.php';
    break;
            
        default:
		if($_SESSION[$systemname."letype"]=="sabrtw"){
		 include "home_sup_admin.php";
		
	}
 else if($_SESSION[$systemname."letype"]=="xybrtw"){
		 include "home_admin.php";
		
	}
  
  else if($_SESSION[$systemname."letype"]=="afgezt"){
		include "home_student.php";
		
	}else if($_SESSION[$systemname."letype"]=="xtfbry"){
		include "home_lecturer.php";
	//	include "my_courses_lec.php";
		
	}else if($_SESSION[$systemname."letype"]=="zyffwq"){
		include "home_accounts.php";
		
	}
           
    }

			?>
            
    
        </div>
    </div>
    
    

        </div>
    <!-- Mainly scripts -->
  
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins/metisMenu/jquery.metisMenu.js"></script>
    <script src="js/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    
    <script src="js/plugins/nestable/jquery.nestable.js"></script>

    <!-- Flot -->
    <script src="js/plugins/flot/jquery.flot.js"></script>
    <script src="js/plugins/flot/jquery.flot.tooltip.min.js"></script>
    <script src="js/plugins/flot/jquery.flot.spline.js"></script>
    <script src="js/plugins/flot/jquery.flot.resize.js"></script>
    <script src="js/plugins/flot/jquery.flot.pie.js"></script>
    <script src="js/plugins/flot/jquery.flot.symbol.js"></script>
    <script src="js/plugins/flot/jquery.flot.time.js"></script>

    <!-- Peity -->
    <script src="js/plugins/peity/jquery.peity.min.js"></script>
    <script src="js/demo/peity-demo.js"></script>

    <!-- Custom and plugin javascript -->
    <script src="js/inspinia.js"></script>
    <script src="js/plugins/pace/pace.min.js"></script>

    <!-- jQuery UI -->
    <script src="js/plugins/jquery-ui/jquery-ui.min.js"></script>



    <!-- Sparkline -->
    <script src="js/plugins/sparkline/jquery.sparkline.min.js"></script>

    <!-- Sparkline demo data  -->
    <script src="js/demo/sparkline-demo.js"></script>
    
    <!-- Data picker -->
   <script src="js/plugins/datapicker/bootstrap-datepicker.js"></script>
   <!-- Date range picker -->
    <script src="js/plugins/daterangepicker/daterangepicker.js"></script>

    <script>
	  $(document).ready(function(){
	/*  Array.prototype.move = function (from, to) {
  this.splice(to, 0, this.splice(from, 1)[0]);
};		
			var ar = [1,2,3,4,5];
ar.move(4,1);
alert(ar); */

            var $image = $(".image-crop > img")
            $($image).cropper({
                aspectRatio: 1.1,
                preview: ".img-preview",
                done: function(data) {
                    // Output the result data for cropping image.
                }
            });

            var $inputImage = $("#inputImage");
            if (window.FileReader) {
                $inputImage.change(function() {
                    var fileReader = new FileReader(),
                            files = this.files,
                            file;

                    if (!files.length) {
                        return;
                    }

                    file = files[0];

                    if (/^image\/\w+$/.test(file.type)) {
                        fileReader.readAsDataURL(file);
                        fileReader.onload = function () {
                            $inputImage.val("");
                            $image.cropper("reset", true).cropper("replace", this.result);
                        };
                    } else {
                        showMessage("Please choose an image file.");
                    }
                });
            } else {
                $inputImage.addClass("hide");
            }

            $("#download").click(function() {
                //window.open($image.cropper("getDataURL"));
$.ajax({  
    type: 'POST',  
    url: 'scripts/change_d_pic.php', 
    data: { image: $image.cropper("getDataURL")},
    success: function(response) {
        //alert(response);
		location.reload();
    }
});
            });

            $("#zoomIn").click(function() {
                $image.cropper("zoom", 0.1);
            });

            $("#zoomOut").click(function() {
                $image.cropper("zoom", -0.1);
            });

            $("#rotateLeft").click(function() {
                $image.cropper("rotate", 45);
            });

            $("#rotateRight").click(function() {
                $image.cropper("rotate", -45);
            });

            $("#setDrag").click(function() {
                $image.cropper("setDragMode", "crop");
            });
 $('#data_3 .input-group.date').datepicker({
                startView: 1,
                todayBtn: "linked",
                keyboardNavigation: false,
                forceParse: false,
				 dateFormat: "mm/dd/yyyy",
				   minDate: new Date('5/5/2021'),
                autoclose: true
            });
			
	});		
			function changec(sel){
	
var doit=sel.value;
 if (doit!=""){
			   
			$.ajax({
  type: "POST",
  data: {id : doit},
  url: 'scripts/getcoursepipo.php',
  success: function(data) {
	  var docourse = document.getElementById('docourse');
	  docourse.innerHTML = data;
    
  }
});   
  }
 }



			function openuser(sel){

			$.ajax({
  type: "POST",
  data: {id : sel},
  url: 'scripts/getuserdetails.php',
  success: function(data) {
	  var showit = document.getElementById('showit');
	  showit.innerHTML = data;
    
  }
});   
  
 }		

	
		

    </script>
    
  <script>
         $(document).ready(function(){

             var updateOutput = function (e) {
                 var list = e.length ? e : $(e.target),
                         output = list.data('output');
                 if (window.JSON) {
                     output.val(window.JSON.stringify(list.nestable('serialize')));//, null, 2));
                 } else {
                     output.val('JSON browser support required for this demo.');
                 }
             };
             // activate Nestable for list 1
             $('#nestable').nestable({
                 group: 1
             }).on('change', updateOutput);

            

             // output initial serialised data
             updateOutput($('#nestable').data('output', $('#nestable-outputxx')));
             updateOutput($('#nestable2').data('output', $('#nestable2-output')));

             $('#nestable-menu').on('click', function (e) {
                 var target = $(e.target),
                         action = target.data('action');
                 if (action === 'expand-all') {
                     $('.dd').nestable('expandAll');
                 }
                 if (action === 'collapse-all') {
                     $('.dd').nestable('collapseAll');
                 }
             });
         });
    </script>  

    <script src="js/plugins/cropper/cropper.min.js"></script>

</body>



</html>
