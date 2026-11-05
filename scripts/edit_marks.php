<?php
require 'connector.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['marks'], $_POST['mymodule'], $_POST['assignment_id'])) {
    $id = intval($_POST['id']); 
    $marks = floatval($_POST['marks']); 
    $mymodule = intval($_POST['mymodule']);
    $assignment_id = intval($_POST['assignment_id']); 
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    try {
        $stmt = $conn->prepare("UPDATE handed_in SET finalmarks = ?, feedback = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("dsi", $marks, $comment, $id);
        $stmt->execute();

        // Redirect back to previous page with module and assignment IDs
        header("Location: ../index.php?view=subby&hjadvhagsdjhsagsdhg=$mymodule&piouglv=$assignment_id");
        exit;
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    die("Invalid request. Make sure all required fields are provided.");
}
?>
