<?php
session_start();
include 'connector.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
        exit;
    }
    
    // Check if branch is being used in intakes
    $check_sql = "SELECT COUNT(*) as count FROM intakes WHERE branch_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete branch. It is being used in intakes.']);
        exit;
    }
    
    $sql = "DELETE FROM branches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Branch deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete branch']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>