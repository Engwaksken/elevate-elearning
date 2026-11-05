<?php
session_start();
require 'scripts/connector.php';

if ( !isset($_GET['id'])){
	
	header("Location: ./?view=view_students");
	die();
	
}else{
$uid = strip_tags(trim($_GET['id']));
$page = trim($_GET['xts']);
$checksystem = "SELECT * FROM users WHERE unique_id = '" . $uid. "'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 if ($check->num_rows < 1) {
$_SESSION[$systemname."errormsg"] = "Invalid User";
               header("Location: ./?view=".$page."");   
	die(); 

  
   
   }else{
	   $data= mysqli_fetch_array($check);
$verify = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);	   
	
	$q = "UPDATE users SET verified='".$verify."' WHERE unique_id='".$uid."'";
 $qq = $conn->query($q)or die (mysqli_error());
 if($qq){
	 
	 				 $to = $data["email"];
$subject = $systemname." Confirmation Email";

$message = '
<html>
<head>
<title>'.$systemname.' Account Activation</title>
</head>
<body style="margin:0px; background: #f8f8f8; ">
<div width="100%" style="background: #f8f8f8; padding: 0px 0px; font-family:arial; line-height:28px; height:100%;  width: 100%; color: #514d6a;">
  <div style="max-width: 700px; padding:50px 0;  margin: 0px auto; font-size: 14px">
    <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 20px">
      <tbody>
         <tr>
          <td style="vertical-align: top; padding-bottom:30px;" align="center"><br/>
            <img src="'.$domainname.'/images/logo.png" alt="'.$domainname.' Logo" style="border:none"></a> </td>
        </tr>
      </tbody>
    </table>
    <div style="padding: 40px; background: #fff;">
      <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
        <tbody>
          <tr>
            <td><b>Dear '.$data["fname"].',</b>
              <p>This is to inform you that, Your account is currently deactivated. Please click the button below to Activate.</p>
			  
			  
              <a href="'.$domainname.'/elearning/verifyme?gen='.$verify.'&&email='.$to.'" style="display: inline-block; padding: 11px 30px; margin: 20px 0px 30px; font-size: 15px; color: #fff; background: #1e88e5; border-radius: 60px; text-decoration:none;"> Verify Email </a>
              <p>Please do not mark this email as spam, we would like to keep you notified in as far your account with us is concerned.</p>
              <b>- Thanks ( '.$systemname.' Admin team)</b> </td>
          </tr>
        </tbody>
      </table>
    </div>
    
  </div>
</div>
</body>
</html>
';

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <'.$systememail.'>' . "\r\n";


mail($to,$subject,$message,$headers);
	 
	 
$_SESSION[$systemname."successmsg"] = "User ".$data["fname"]." ".$data["lname"]." has been DEACTIVATED";
               header("Location: ./?view=".$page."");   
	die();	 
 }else{
	$_SESSION[$systemname."errormsg"] = "Unknown Error";
               header("Location: ./?view=".$page."");   
	die(); 
 }
   }
	
}

?>