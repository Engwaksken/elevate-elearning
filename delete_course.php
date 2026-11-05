<?php
//session_start();
require 'scripts/connector.php';

function redirectToResources() {
    header("Location: index.php?view=view_more_resources");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM course_free WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['successmsg'] = "Course deleted successfully.";
        } else {
            $_SESSION['errormsg'] = "Error deleting record: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $_SESSION['errormsg'] = "Failed to prepare statement.";
    }

    $conn->close();
    redirectToResources();
} else {
    $_SESSION['errormsg'] = "Invalid request.";
    redirectToResources();
}
?>
