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
// DETERMINE WHAT TO MARK AS READ
// ============================================================================

// Option 1: Mark all notifications as read
if (isset($_POST['mark_all']) && $_POST['mark_all'] === 'true') {
    $updateQuery = "
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE user_id = '$user_id'
        AND is_read = 0
        AND is_deleted = 0
    ";
    
    if ($conn->query($updateQuery)) {
        $affected_rows = $conn->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => "Marked all notifications as read",
            'count' => $affected_rows
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $conn->close();
    exit;
}

// Option 2: Mark single notification as read
if (isset($_POST['notification_id']) && !empty($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    
    $updateQuery = "
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE id = $notification_id
        AND user_id = '$user_id'
    ";
    
    if ($conn->query($updateQuery)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read',
                'notification_id' => $notification_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found or already read'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $conn->close();
    exit;
}

// Option 3: Mark multiple notifications as read
if (isset($_POST['notification_ids']) && is_array($_POST['notification_ids'])) {
    $notification_ids = array_map('intval', $_POST['notification_ids']);
    
    if (empty($notification_ids)) {
        echo json_encode(['success' => false, 'message' => 'No notification IDs provided']);
        exit;
    }
    
    $ids_string = implode(',', $notification_ids);
    
    $updateQuery = "
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE id IN ($ids_string)
        AND user_id = '$user_id'
    ";
    
    if ($conn->query($updateQuery)) {
        $affected_rows = $conn->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => "Marked $affected_rows notification(s) as read",
            'count' => $affected_rows
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $conn->close();
    exit;
}

// No valid parameters provided
echo json_encode([
    'success' => false,
    'message' => 'Invalid parameters. Provide notification_id, notification_ids, or mark_all.'
]);

$conn->close();
?>