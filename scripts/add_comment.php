<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';

header('Content-Type: application/json; charset=utf-8');

function jsonFail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $msg
    ]);
    exit;
}

function jsonOk(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('Invalid request method', 405);
}

$user_id = $_SESSION[$systemname . "leido"] ?? '';
$letype  = $_SESSION[$systemname . "letype"] ?? '';

if ($user_id === '' || $letype === '') {
    jsonFail('User not authenticated', 401);
}

$note_id      = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$comment_text = trim((string)($_POST['comment_text'] ?? ''));

if ($note_id <= 0) {
    jsonFail('Invalid note ID');
}

if ($comment_text === '') {
    jsonFail('Comment cannot be empty');
}

if (mb_strlen($comment_text) > 1000) {
    jsonFail('Comment exceeds maximum length of 1000 characters');
}

/*
|--------------------------------------------------------------------------
| Fetch note with course details
|--------------------------------------------------------------------------
*/
$noteStmt = $conn->prepare("
    SELECT 
        n.id,
        n.title,
        n.course_id,
        n.intake_id,
        c.course_name
    FROM course_unit_notes n
    LEFT JOIN courses c ON c.id = n.course_id
    WHERE n.id = ?
    LIMIT 1
");

if (!$noteStmt) {
    jsonFail('Server error: failed to prepare note check');
}

$noteStmt->bind_param("i", $note_id);
$noteStmt->execute();
$note = $noteStmt->get_result()->fetch_assoc();
$noteStmt->close();

if (!$note) {
    jsonFail('Invalid note ID');
}

/*
|--------------------------------------------------------------------------
| Insert comment
|--------------------------------------------------------------------------
*/
$insStmt = $conn->prepare("
    INSERT INTO note_comments 
        (note_id, user_id, comment_text, created_at)
    VALUES 
        (?, ?, ?, NOW())
");

if (!$insStmt) {
    jsonFail('Server error: failed to prepare insert');
}

$insStmt->bind_param("iss", $note_id, $user_id, $comment_text);
$ok = $insStmt->execute();
$comment_id = $insStmt->insert_id;
$insStmt->close();

if (!$ok) {
    jsonFail('Failed to post comment');
}

/*
|--------------------------------------------------------------------------
| Get commenter name
|--------------------------------------------------------------------------
*/
$name = 'You';

$userStmt = $conn->prepare("
    SELECT fname, lname 
    FROM users 
    WHERE unique_id = ? OR id = ?
    LIMIT 1
");

if ($userStmt) {
    $userIdAsInt = is_numeric($user_id) ? (int)$user_id : 0;

    $userStmt->bind_param("si", $user_id, $userIdAsInt);
    $userStmt->execute();
    $u = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if ($u) {
        $fname = trim((string)($u['fname'] ?? ''));
        $lname = trim((string)($u['lname'] ?? ''));
        $full  = trim($fname . ' ' . $lname);

        if ($full !== '') {
            $name = $full;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Notify instructor by email
|--------------------------------------------------------------------------
*/
$email_notice = 'Instructor notification not sent.';

try {
    $courseId  = (int)($note['course_id'] ?? 0);
    $noteTitle = (string)($note['title'] ?? 'Course Note');

    if ($courseId > 0) {
        $emailService = new EmailService();

        $sent = $emailService->notifyInstructorNoteComment(
            $courseId,
            $name,
            $noteTitle,
            $comment_text
        );

        $email_notice = $sent
            ? 'Instructor notified successfully.'
            : 'Comment posted, but instructor notification was not sent.';
    }
} catch (Throwable $e) {
    error_log("Instructor note comment email failed: " . $e->getMessage());
    $email_notice = 'Comment posted, but instructor notification failed.';
}

jsonOk([
    'success' => true,
    'message' => 'Comment posted successfully',
    'email_notice' => $email_notice,
    'comment' => [
        'id' => (int)$comment_id,
        'note_id' => (int)$note_id,
        'comment_text' => $comment_text,
        'author_name' => $name,
        'time_ago' => 'just now',
        'is_own_comment' => true
    ]
]);