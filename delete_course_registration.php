<?php
session_start();
require 'scripts/connector.php';


if (isset($_GET['course_reg_id']) && !empty($_GET['course_reg_id'])) {
    $student_id = $_GET['id'];
    $course_id = $_GET['course_id'];
    $course_reg_id = $_GET['course_reg_id'];


    $stmt = $conn->prepare("DELETE FROM course_registration WHERE user_id = ?, course_id=? AND course_reg_id=?");
    $stmt->bind_param("ssi", $student_id, $course_id , $course_reg_id );

    if ($stmt->execute()) {
        header("Location:index.php?view=view_students&msg=course_reg+deleted+successfully");
        exit;
    } else {
        echo "Error deleting Course Registration: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "Invalid request.";
}

$conn->close();
?>
