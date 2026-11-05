<?php
session_start();
require_once 'connector.php';

if ($_POST) {
    $user_id = intval($_POST['user_id']);
    $course_id = intval($_POST['course_id']);
    $assignment_id = intval($_POST['assignment_id']);
    $timestamp = $_POST['timestamp'];
    
    // Check if record exists
    $check_query = "SELECT id, no_clicks FROM assignment_clicks WHERE user_id = ? AND course_id = ? AND assignment_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("iii", $user_id, $course_id, $assignment_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $new_clicks = $row['no_clicks'] + 1;
        
        $update_query = "UPDATE assignment_clicks SET no_clicks = ?, last_clicked = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $new_clicks, $row['id']);
        
        if ($update_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Assignment click updated', 'clicks' => $new_clicks]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update click count']);
        }
    } else {
        // Insert new record
        $insert_query = "INSERT INTO assignment_clicks (user_id, course_id, assignment_id, no_clicks, first_clicked, last_clicked) VALUES (?, ?, ?, 1, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iii", $user_id, $course_id, $assignment_id);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Assignment click recorded', 'clicks' => 1]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to record click']);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>