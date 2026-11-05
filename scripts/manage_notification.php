<?php

session_start();
require 'connector.php'; 

header('Content-Type: application/json');

// ============================================================================
// VALIDATE REQUEST METHOD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ============================================================================
// VALIDATE USER SESSION
// ============================================================================
if (!isset($_SESSION[$systemname . "leido"])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $conn->real_escape_string($_SESSION[$systemname . "leido"]);

// ============================================================================
// VALIDATE INPUT PARAMETERS
// ============================================================================
if (!isset($_POST['notification_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$notification_id = intval($_POST['notification_id']);
$action = $_POST['action'];

// Validate action
if ($action !== 'delete' && $action !== 'archive') {
    echo json_encode(['success' => false, 'message' => 'Invalid action. Use "delete" or "archive"']);
    exit;
}

// ============================================================================
// PERFORM ACTION
// ============================================================================

if ($action === 'delete') {
    // Soft delete - mark as deleted
    $updateQuery = "
        UPDATE notifications
        SET is_deleted = 1
        WHERE id = $notification_id
        AND user_id = '$user_id'
    ";
    
    if ($conn->query($updateQuery)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted',
                'notification_id' => $notification_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found or already deleted'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
} else if ($action === 'archive') {
    // Archive notification
    $updateQuery = "
        UPDATE notifications
        SET is_archived = 1
        WHERE id = $notification_id
        AND user_id = '$user_id'
    ";
    
    if ($conn->query($updateQuery)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification archived',
                'notification_id' => $notification_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found or already archived'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

$conn->close();
?>