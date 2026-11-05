<?php
//session_start();
require 'scripts/connector.php';

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $student_id = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE unique_id = ?");
    $stmt->bind_param("s", $student_id);

    if ($stmt->execute()) {
      
        $_SESSION['flash_message'] = "Student deleted successfully";
        header("Location: ./?view=view_students");
        exit;
    } else {
        $_SESSION['flash_message'] = "Error deleting student: " . $conn->error;
        header("Location: ./?view=view_students");
        exit;
    }

    $stmt->close();
} else {
    $_SESSION['flash_message'] = "Invalid request.";
    header("Location: ./?view=view_students");
    exit;
}

$conn->close();
?>
