<?php

session_start();
require 'connector.php'; 

header('Content-Type: application/json');

// ============================================================================
// VALIDATE USER SESSION
// ============================================================================
if (!isset($_SESSION[$systemname . "leido"])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $conn->real_escape_string($_SESSION[$systemname . "leido"]);

// ============================================================================
// GET QUERY PARAMETERS
// ============================================================================
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Validate limit
if ($limit > 100) $limit = 100;
if ($limit < 1) $limit = 20;

// ============================================================================
// BUILD QUERY
// ============================================================================
$whereConditions = [
    "n.user_id = '$user_id'",
    "n.is_deleted = 0",
    "(n.expires_at IS NULL OR n.expires_at > NOW())"
];

// Filter by type if specified
if (!empty($type)) {
    $whereConditions[] = "n.type = '$type'";
}

// Filter by read status
if ($unread_only) {
    $whereConditions[] = "n.is_read = 0";
}

$whereClause = implode(' AND ', $whereConditions);

/* ============================================================================
   FETCH NOTIFICATIONS WITH SENDER INFORMATION
   ============================================================================ */
$notificationsQuery = "
    SELECT 
        n.id,
        n.title,
        n.message,
        n.type,
        n.priority,
        n.related_type,
        n.related_id,
        n.course_id,
        n.action_url,
        n.is_read,
        n.is_archived,
        n.created_at,
        n.read_at,
        u.fname as sender_fname,
        u.lname as sender_lname
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.unique_id
    WHERE $whereClause
    ORDER BY n.created_at DESC
    LIMIT $limit OFFSET $offset
";

$result = $conn->query($notificationsQuery);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

// ============================================================================
// GET TOTAL COUNT (for pagination)
// ============================================================================
$countQuery = "
    SELECT COUNT(*) as total
    FROM notifications n
    WHERE $whereClause
";

$countResult = $conn->query($countQuery);
$countData = $countResult->fetch_assoc();
$total_count = $countData['total'];

// ============================================================================
// GET UNREAD COUNT BY TYPE
// ============================================================================
$unreadCountQuery = "
    SELECT 
        type,
        COUNT(*) as count
    FROM notifications
    WHERE user_id = '$user_id'
    AND is_read = 0
    AND is_deleted = 0
    AND (expires_at IS NULL OR expires_at > NOW())
    GROUP BY type
";

$unreadCountResult = $conn->query($unreadCountQuery);
$unread_by_type = [];

while ($row = $unreadCountResult->fetch_assoc()) {
    $unread_by_type[$row['type']] = intval($row['count']);
}

// Calculate total unread
$total_unread = array_sum($unread_by_type);

// ============================================================================
// PROCESS NOTIFICATIONS
// ============================================================================
$notifications = [];

while ($row = $result->fetch_assoc()) {
    // Format time ago
    $time_ago = getTimeAgo($row['created_at']);
    
    // Get sender name
    $sender_name = null;
    if ($row['sender_fname'] && $row['sender_lname']) {
        $sender_name = htmlspecialchars($row['sender_fname'] . ' ' . $row['sender_lname']);
    }
    
    // Determine icon based on type
    $icon = getNotificationIcon($row['type']);
    
    // Determine color based on priority
    $color = getNotificationColor($row['priority']);
    
    $notifications[] = [
        'id' => $row['id'],
        'title' => htmlspecialchars($row['title']),
        'message' => htmlspecialchars($row['message']),
        'type' => $row['type'],
        'priority' => $row['priority'],
        'related_type' => $row['related_type'],
        'related_id' => $row['related_id'],
        'course_id' => $row['course_id'],
        'action_url' => $row['action_url'],
        'is_read' => (bool)$row['is_read'],
        'is_archived' => (bool)$row['is_archived'],
        'created_at' => $row['created_at'],
        'read_at' => $row['read_at'],
        'time_ago' => $time_ago,
        'sender_name' => $sender_name,
        'icon' => $icon,
        'color' => $color
    ];
}

// ============================================================================
// RETURN RESPONSE
// ============================================================================
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'pagination' => [
        'total' => $total_count,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + $limit) < $total_count
    ],
    'unread_counts' => [
        'total' => $total_unread,
        'by_type' => $unread_by_type
    ]
]);

$conn->close();

/* ============================================================================
   HELPER FUNCTIONS
   ============================================================================ */

/**
 * Convert datetime to "time ago" format
 */
function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $timestamp;
    
    $periods = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
        1        => 'second'
    ];
    
    foreach ($periods as $seconds => $label) {
        $difference = floor($time_difference / $seconds);
        if ($difference >= 1) {
            $plural = ($difference > 1) ? 's' : '';
            return $difference . ' ' . $label . $plural . ' ago';
        }
    }
    
    return 'just now';
}

/**
 * Get Font Awesome icon based on notification type
 */
function getNotificationIcon($type) {
    $icons = [
        'comment_reply' => 'fa-comment',
        'new_assignment' => 'fa-tasks',
        'new_note' => 'fa-file-text',
        'graded' => 'fa-check-circle',
        'enrollment' => 'fa-graduation-cap',
        'announcement' => 'fa-bullhorn'
    ];
    
    return $icons[$type] ?? 'fa-bell';
}

/**
 * Get color based on notification priority
 */
function getNotificationColor($priority) {
    $colors = [
        'low' => '#95a5a6',
        'normal' => '#3498db',
        'high' => '#f39c12',
        'urgent' => '#e74c3c'
    ];
    
    return $colors[$priority] ?? '#3498db';
}
?>