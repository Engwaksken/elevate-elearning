<?php
session_start();
require 'connector.php';

header('Content-Type: application/json');

$systemname = $systemname ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

if (!isset($_SESSION[$systemname . "leido"], $_SESSION[$systemname . "letype"])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_type = (string)$_SESSION[$systemname . "letype"];
if ($user_type !== "xtfbry" && $user_type !== "xybrtw") {
    echo json_encode(['success' => false, 'message' => 'Access denied. Instructors only.']);
    exit;
}

$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
if ($note_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid note_id parameter']);
    exit;
}

$current_user_id = (string)$_SESSION[$systemname . "leido"];

$sql = "
    SELECT
        nc.id,
        nc.comment_text,
        nc.created_at,
        nc.user_id,
        nc.parent_comment_id,
        u.fname,
        u.lname,
        u.acc_type AS commenter_type
    FROM note_comments nc
    INNER JOIN users u ON u.unique_id = nc.user_id
    WHERE nc.note_id = ?
      AND nc.is_deleted = 0
    ORDER BY nc.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $note_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$comments = [];
while ($row = $res->fetch_assoc()) {
    $author_name = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));

    $created_at = $row['created_at'] ?? null;
    $time_ago = $created_at ? getTimeAgo($created_at) : 'just now';

    $commenter_type = (string)($row['commenter_type'] ?? '');
    $is_instructor = ($commenter_type === 'xtfbry' || $commenter_type === 'xybrtw');

    $comments[] = [
        'id' => (int)$row['id'],
        'comment_text' => (string)($row['comment_text'] ?? ''),
        'author_name' => $author_name,
        'created_at' => (string)$created_at,
        'time_ago' => $time_ago,
        'is_instructor' => $is_instructor,
        'parent_comment_id' => isset($row['parent_comment_id']) ? (int)$row['parent_comment_id'] : null,
        'is_own_comment' => ((string)$row['user_id'] === $current_user_id)
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'comments' => $comments,
    'total_comments' => count($comments)
]);

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'just now';

    $diff = time() - $timestamp;
    if ($diff < 1) return 'just now';

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
        $count = (int)floor($diff / $seconds);
        if ($count >= 1) {
            return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}
?>
