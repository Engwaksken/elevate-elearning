<?php
session_start();
include 'connector.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
        exit;
    }
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required']);
        exit;
    }
    
    // Check if branch name already exists (excluding current branch)
    $check_sql = "SELECT id FROM branches WHERE name = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $name, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Branch name already exists']);
        exit;
    }
    
    $sql = "UPDATE branches SET name = ?, location = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $name, $location, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Branch updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update branch']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>