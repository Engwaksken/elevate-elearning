<?php
session_start();
require 'connector.php';

if ((!isset($_POST['course_name'])) || (!isset($_POST['course_code'])) || (!isset($_POST['update']))) {
    header("Location: ../?view=update_course");
    die();
} else {
    $course_name = strip_tags(trim($_POST['course_name']));
    $course_code = strip_tags(trim($_POST['course_code']));
    $duration = strip_tags($_POST['duration']);
    $fees = strip_tags(trim($_POST['fees']));
    $shortnotes = $_POST['shortnotes'];
    $category = $_POST['category'];

    // Check if course already exists with the same course code
    $checksystem = "SELECT * FROM courses WHERE short_code = '" . $course_code . "'";
    $check = $conn->query($checksystem) or die(mysqli_error($conn));

    if ($check->num_rows == 0) {
        $_SESSION[$systemname . "errormsg"] = "Course with code '" . $course_code . "' does not exist.";
        header("Location: ../?view=update_course");
        die();
    } else {
        // Prepare the update SQL statement
        $sql = "UPDATE `courses` SET `name` = '" . $course_name . "', `duration` = '" . $duration . "', `fees` = '" . $fees . "', `short_notes` = '" . $shortnotes . "', `category` = '" . $category . "' WHERE `short_code` = '" . $course_code . "'";

        // Execute the update query
        $result = $conn->query($sql) or die(mysqli_error($conn));

        if ($result) {
            $_SESSION[$systemname . "successmsg"] = "Course '" . $course_name . "' has been updated.";
            header("Location: ../?view=update_course");
            die();
        }
    }
}
?>
