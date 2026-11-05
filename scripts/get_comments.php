<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require 'connector.php'; // must define $conn and $systemname

header('Content-Type: application/json; charset=utf-8');

function jsonFail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function jsonOk(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonFail('Invalid request method', 405);
}

// Auth
$current_user_id = $_SESSION[$systemname . "leido"] ?? '';
$letype = $_SESSION[$systemname . "letype"] ?? '';
if ($current_user_id === '' || $letype === '') {
    jsonFail('User not authenticated', 401);
}

// Input
$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
if ($note_id <= 0) {
    jsonFail('Invalid note_id parameter');
}

// Optional: verify note exists
$noteStmt = $conn->prepare("SELECT id FROM course_unit_notes WHERE id = ? LIMIT 1");
if (!$noteStmt) jsonFail('Server error: failed to prepare note check', 500);

$noteStmt->bind_param("i", $note_id);
$noteStmt->execute();
$noteRes = $noteStmt->get_result();
$noteStmt->close();

if (!$noteRes || $noteRes->num_rows < 1) {
    jsonFail('Invalid note ID');
}

// Fetch comments
$stmt = $conn->prepare("
    SELECT 
        nc.id,
        nc.comment_text,
        nc.created_at,
        nc.user_id,
        u.fname,
        u.lname
    FROM note_comments nc
    JOIN users u ON nc.user_id = u.unique_id
    WHERE nc.note_id = ?
    ORDER BY nc.created_at DESC
");
if (!$stmt) jsonFail('Server error: failed to prepare comments query', 500);

$stmt->bind_param("i", $note_id);
$stmt->execute();
$res = $stmt->get_result();

$comments = [];
while ($row = $res->fetch_assoc()) {
    $author_name = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
    if ($author_name === '') $author_name = 'Unknown';

    $created_at = $row['created_at'] ?? null;

    $comments[] = [
        'id' => (int)($row['id'] ?? 0),
        'comment_text' => (string)($row['comment_text'] ?? ''),
        'author_name' => $author_name,
        'created_at' => $created_at,
        'time_ago' => timeAgo($created_at),
        'is_own_comment' => ((string)($row['user_id'] ?? '') === (string)$current_user_id),
    ];
}
$stmt->close();

jsonOk([
    'success' => true,
    'comments' => $comments,
    'total_comments' => count($comments),
]);

/* -------------------- Helpers -------------------- */
function timeAgo(?string $datetime): string {
    if (!$datetime) return 'just now';

    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'just now';

    $diff = time() - $timestamp;
    if ($diff < 0) $diff = 0;

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
        $count = (int) floor($diff / $seconds);
        if ($count >= 1) {
            return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}
