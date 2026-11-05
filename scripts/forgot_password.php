<?php
session_start();
require 'connector.php';
require 'EmailService.php';
require_once __DIR__ . '/auth_publish.php';

$systemname = "Elevate Her 360 eLearning";
$domainname = "https://www.elevateher360.org/";
$nowdate = date("Y-m-d H:i:s");

$emailService = new EmailService();

// Validate request
if (!isset($_POST['email']) || !isset($_POST['forgot'])) {
    header("Location: ../forgot_password");
    exit();
}

$email = strtolower(trim($_POST['email']));

// Check if user exists
$checkUser = $conn->prepare("SELECT * FROM users WHERE email = ?");
$checkUser->bind_param("s", $email);
$checkUser->execute();
$result = $checkUser->get_result();

if ($result->num_rows < 1) {
    $_SESSION[$systemname . "errormsg"] = "Account $email not found";
    header("Location: ../forgot_password");
    exit();
}

$data = $result->fetch_assoc();

$uid       = $data["id"];
$fullname  = $data["fname"];
$oldSalt   = $data["salt"];

// Generate new password
$newSalt = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
$pw      = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 4)), 0, 4);
$fullPW  = $newSalt . $pw;
$hashedPW = md5($fullPW);

// Update new password & salt in database
$update = $conn->prepare("UPDATE users SET salt = ?, password = ? WHERE email = ?");
$update->bind_param("sss", $newSalt, $hashedPW, $email);

if (!$update->execute()) {
    $_SESSION[$systemname . "errormsg"] = "Failed to update the password. Please try again.";
    header("Location: ../forgot_password");
    exit();
}

$publishPasswordHash = password_hash($pw, PASSWORD_DEFAULT);
if ($publishPasswordHash !== false) {
    eh_auth_publish_user([
        'email' => $email,
        'fname' => (string)($data['fname'] ?? $fullname),
        'lname' => (string)($data['lname'] ?? ''),
        'password_hash' => $publishPasswordHash,
        'source' => 'elearning',
    ], $conn);
}

/* ---------------------------
   EMAIL CONTENT (REPLACED)
---------------------------- */

$subject = "Password Reset Success";

$body = '
<html>
<head>
<title>' . $systemname . ' Password Reset Success</title>
</head>
<body style="margin:0px; background:#f8f8f8;">
<div style="max-width:700px; margin:auto; padding:20px; font-family:Arial; background:#fff;">

    <div style="text-align:center;">
        <img src="' . $domainname . '/elearning/images/syx_images/logo.png" width="100" height="150">
    </div>

    <h2 style="color:#333;">Dear ' . $fullname . ',</h2>

    <p>Your password has been successfully reset.</p>

    <p>Your new temporary password is: 
        <strong style="font-size:18px;">' . $pw . '</strong>
    </p>

    <p>Please click the button below to log in and change it immediately:</p>

    <p style="text-align:center;">
        <a href="' . $domainname . '/elearning/login?uopt=' . $oldSalt . '&email=' . $email . '"
            style="padding:12px 30px; background:#fc7f10; color:#fff; border-radius:50px; text-decoration:none;">
            Go to Login
        </a>
    </p>

    <p>Please do not mark this email as spam so we can continue sending important account notifications.</p>

    <p><strong>- ' . $systemname . ' Admin Team</strong></p>

</div>
</body>
</html>
';

/* ---------------------------
   SEND EMAIL VIA EmailService
---------------------------- */

$sent = $emailService->sendCustomEmail(
    $email,
    $fullname,
    $subject,
    $body
);

if ($sent) {
    $_SESSION[$systemname . "successmsg"] = "A new password has been sent to your email: $email.";
} else {
    $_SESSION[$systemname . "errormsg"] = "Email sending failed. Please contact support.";
}

header("Location: ../forgot_password");
exit();
?>