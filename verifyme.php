<?php
session_start();
require 'scripts/connector.php';

if (isset($_GET['gen'], $_GET['email']))
{
    $usernamex = $_GET['gen'];
    $email = $_GET['email'];

    $sqlz = "UPDATE users SET verified = 'yes' WHERE email = ?";
    $stmt = $conn->prepare($sqlz);
    $stmt->bind_param("s", $email);
    $result = $stmt->execute();

    if ($result) {
        $_SESSION[$systemname . "successmsg"] = "You Have Been Verified <br> You may Login Now";
        header("Location: login");
        exit;
    }

    $stmt->close();
    $conn->close();
}
else
{
    header("Location: login");
    exit;
}
?>