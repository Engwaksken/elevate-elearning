<?php
session_start();
require 'connector.php';

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $student_id = trim($_GET['id']);

    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("DELETE FROM course_registration WHERE user_id = ?");
        if (!$stmt1) {
            throw new Exception("Prepare failed for course_registration delete: " . $conn->error);
        }

        $stmt1->bind_param("s", $student_id);
        if (!$stmt1->execute()) {
            throw new Exception("Execute failed for course_registration delete: " . $stmt1->error);
        }
        $stmt1->close();

        $stmt2 = $conn->prepare("DELETE FROM users WHERE unique_id = ?");
        if (!$stmt2) {
            throw new Exception("Prepare failed for users delete: " . $conn->error);
        }

        $stmt2->bind_param("s", $student_id);
        if (!$stmt2->execute()) {
            throw new Exception("Execute failed for users delete: " . $stmt2->error);
        }
        $stmt2->close();

        $conn->commit();
        $conn->close();

       
        header("Location: ../?view=view_students&msg=Student+deleted+successfully");
        exit;

    } catch (Exception $e) {

        $conn->rollback();
        $conn->close();
        echo "Error deleting student: " . $e->getMessage();
    }

} else {
    echo "Invalid request. No student ID specified.";
}
?>
