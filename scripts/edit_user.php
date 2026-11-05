<?php
session_start();
require 'scripts/connector.php';


    $email = strip_tags(trim($_POST['email']));
    $fname = strip_tags(trim($_POST['fname']));
    $lname = strip_tags(trim($_POST['lname']));
    $oname = strip_tags(trim($_POST['oname']));
    $contact = strip_tags(trim($_POST['contact']));
    $gender = strip_tags(trim($_POST['gender']));
    $role = strip_tags(trim($_POST['type']));

    $dob = $_POST['dob'];
    $country = $_POST['country'];

    $checksystem = "SELECT * FROM users WHERE email = '" . $email . "'";
    $check = $conn->query($checksystem) or die(mysqli_error($conn));

    if ($check->num_rows === 0) {
        $_SESSION[$systemname . "errormsg"] = "Account with email " . $email . " does not exist.";
        header("Location: ../index.php?view=edit_user");
        die();
    } else {
        $newdob = date("Y-m-d", strtotime($dob));

        $sql = "UPDATE users 
                SET fname = '" . $fname . "',
                    lname = '" . $lname . "',
                    other_name = '" . $oname . "',
                    contact = '" . $contact . "',
                    gender = '" . $gender . "',
                    acc_type = '" . $role . "',
                    country = '" . $country . "',
                    dob = '" . $newdob . "'
                WHERE email = '" . $email . "'";

        $result = $conn->query($sql) or die(mysqli_error($conn));

        if ($result) {
            $_SESSION[$systemname . "successmsg"] = "Account for " . $fname . " " . $lname . " has been successfully updated.";
            header("Location: ../index.php?view=edit_user");
            die();
        } else {
            $_SESSION[$systemname . "errormsg"] = "Failed to update account for " . $email . ". Unknown error.";
            header("Location: ../index.php?view=edit_user");
            die();
        }
    }
}
?>
