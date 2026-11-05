<?php
session_start();
require 'connector.php';

if (!isset($_POST['dob']) || !isset($_POST['course'])) {
    header("Location: ../?view=intakes");
    die();
} else {
    $date = trim($_POST['dob']);
    $date = date("Y-m-d", strtotime($date)); 
    $course = strip_tags(trim($_POST['course']));
    $uid = strip_tags(trim($_SESSION[$systemname . "leido"]));
    $nowdate = date("Y-m-d"); 
    $endDate = date("Y-m-d", strtotime($date . " +100 days")); 

    $thisyear = date("Y", strtotime($date));
    $month = date("F", strtotime($date));

    $gg = ($course == 1) ? "Short Course" : (($course == 2) ? "Diploma" : "Unknown");

    $checksystem = "SELECT * FROM `intakes` WHERE YEAR(date_launching) = '$thisyear' AND type = '$course'";
    $check = $conn->query($checksystem) or die(mysqli_error($conn));

    if ($course == "1" && $check->num_rows >= 20) {
        $_SESSION[$systemname . "errormsg"] = "Can't add any more Short Course intakes to the year $thisyear";
        header("Location: ../?view=intakes");
        die();
    } elseif ($course == "2" && $check->num_rows >= 2) {
        $_SESSION[$systemname . "errormsg"] = "Can't add any more Diploma intakes to the year $thisyear";
        header("Location: ../?view=intakes");
        die();
    } else {
        $sql = "INSERT INTO `intakes` (`name`, `type`, `date_added`, `date_launching`, `date_ending`, `added_by`) 
                VALUES ('$month', '$course', '$nowdate', '$date', '$endDate', '$uid')";

        $result = $conn->query($sql) or die(mysqli_error($conn));

        if ($result) {
            $_SESSION[$systemname . "successmsg"] = "$month intake for $gg has been added";
            header("Location: ../?view=intakes");
            die();
        } else {
            $_SESSION[$systemname . "errormsg"] = "Unknown error happened";
            header("Location: ../?view=intakes");
            die();
        }
    }
}
?>
