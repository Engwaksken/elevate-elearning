<?php
session_start();
require 'connector.php';

        
if (isset($_GET['uopt'])) {
            
            
            $uopt =  $_GET['uopt'];
            $email =  $_GET['email'];
            
            
            $getUname = "SELECT * FROM users WHERE email = '" . $email . "' AND salt = '" . $uopt . "'";
        $resultUname = $conn->query($getUname);
        
        if ($resultUname->num_rows < 1) {
            $_SESSION[$systemname."errormsg"] = "Invalid Request";
               header("Location:../forgot_password.php");
    exit;
        }else {
            $data= mysqli_fetch_array($resultUname);
            $uid= $data["id"];
            $salt= $data["salt"];
            $verified= $data["verified"];
            $passwordx= $data["password"];
            $fullname=$data["fname"];
            $email=$data["email"];
            
           
    $salt2 = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
        $pw = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 8)), 0, 8);
		$newpassword=$salt2.$pw;
		$newpassword=md5($newpassword);
		
		
		 $sql =  "UPDATE users SET salt = '".$salt2."',password = '".$newpassword."' WHERE email = '".$email."'";
	
            $result = $conn->query($sql); 
	if($result){	
		    
    $to = $email;
$subject = $systemname." Password Reset Success";

$message = '
<html>
<head>
<title>'.$systemname.' Password Reset Success</title>
</head>
<body style="margin:0px; background: #f8f8f8; ">
<div width="100%" style="background: #f8f8f8; padding: 0px 0px; font-family:arial; line-height:28px; height:100%;  width: 100%; color: #514d6a;">
  <div style="max-width: 700px; padding:50px 0;  margin: 0px auto; font-size: 14px">
    <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 20px">
      <tbody>
        <tr>
          <td style="vertical-align: top; padding-bottom:30px;" align="center"><br/>
            <img src="'.$domainname.'/elearning/images/syx_images/logo.png" alt="" style="border:none"></a> </td>
        </tr>
      </tbody>
    </table>
    <div style="padding: 40px; background: #fff;">
      <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
        <tbody>
          <tr>
            <td><b>Dear '.$fullname.',</b>
              <p>Your Password Has been Reset</p>
              <p>Your new password is:     '.$pw.'</p>
              <p><a href="'.$domainname.'/elearning/login.php" style="display: inline-block; padding: 11px 30px; margin: 20px 0px 30px; font-size: 15px; color: #fff; background: #1e88e5; border-radius: 60px; text-decoration:none;"> Go to Login </a></p>
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


              
 $_SESSION[$systemname."successmsg"] = "Password reset successfully. Login with your new password";
  
               header("Location:https://witi.ac.ug/elearning/login.php");
  
  }else{
  $_SESSION[$systemname."errormsg"] = "Unknown Error";
  
  header("Location:https://witi.ac.ug/elearning/forgot_password.php");
    exit;
  
  }  
    
    
    
}
 
            //echo(" we are here");


}else {
    header("Location:https://witi.ac.ug/elearning/forgot_password.php");
    exit;
}