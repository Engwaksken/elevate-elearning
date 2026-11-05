<?php
session_start();
include 'connector.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required']);
        exit;
    }
    
    // Check if branch already exists
    $check_sql = "SELECT id FROM branches WHERE name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Branch name already exists']);
        exit;
    }
    
    $sql = "INSERT INTO branches (name, location) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $location);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Branch added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add branch']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>