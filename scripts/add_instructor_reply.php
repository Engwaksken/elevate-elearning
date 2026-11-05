<?php
session_start();

require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';

header('Content-Type: application/json; charset=utf-8');

function jsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

function jsonOk(array $data): void {
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('Invalid request method', 405);
}

if (!isset($_SESSION[$systemname . "leido"], $_SESSION[$systemname . "letype"])) {
    jsonFail('User not authenticated', 401);
}

$user_type = trim((string)$_SESSION[$systemname . "letype"]);

if ($user_type !== "xtfbry" && $user_type !== "xybrtw") {
    jsonFail('Access denied. Instructors only.', 403);
}

$user_id = trim((string)$_SESSION[$systemname . "leido"]);

$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$parent_comment_id = isset($_POST['parent_comment_id']) && $_POST['parent_comment_id'] !== ''
    ? (int)$_POST['parent_comment_id']
    : null;

$comment_text = trim((string)($_POST['comment_text'] ?? ''));

if ($note_id <= 0) {
    jsonFail('Invalid note ID');
}

if ($comment_text === '') {
    jsonFail('Reply cannot be empty');
}

if (mb_strlen($comment_text) > 1000) {
    jsonFail('Reply exceeds maximum length of 1000 characters');
}

/*
|--------------------------------------------------------------------------
| Verify note exists
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT 
        n.id,
        n.title,
        n.course_unit,
        n.intake
    FROM course_unit_notes n
    WHERE n.id = ?
    LIMIT 1
");

if (!$stmt) {
    jsonFail('Server error: failed to prepare note query');
}

$stmt->bind_param("i", $note_id);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    jsonFail('Invalid note ID');
}

/*
|--------------------------------------------------------------------------
| Verify parent comment and fetch original student
|--------------------------------------------------------------------------
*/
$student = null;

if ($parent_comment_id !== null) {
    $stmt = $conn->prepare("
        SELECT 
            nc.id,
            nc.user_id,
            u.email,
            u.fname,
            u.lname
        FROM note_comments nc
        LEFT JOIN users u 
            ON u.unique_id = nc.user_id 
            OR u.id = nc.user_id
        WHERE nc.id = ?
          AND nc.note_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonFail('Server error: failed to prepare parent comment query');
    }

    $stmt->bind_param("ii", $parent_comment_id, $note_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        jsonFail('Invalid parent comment ID');
    }
}

/*
|--------------------------------------------------------------------------
| Insert instructor reply
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    INSERT INTO note_comments
        (note_id, user_id, comment_text, created_at, parent_comment_id)
    VALUES
        (?, ?, ?, NOW(), ?)
");

if (!$stmt) {
    jsonFail('Server error: failed to prepare reply insert');
}

$stmt->bind_param(
    "issi",
    $note_id,
    $user_id,
    $comment_text,
    $parent_comment_id
);

if (!$stmt->execute()) {
    $stmt->close();
    jsonFail('Failed to post reply');
}

$comment_id = (int)$stmt->insert_id;
$stmt->close();

/*
|--------------------------------------------------------------------------
| Get instructor name
|--------------------------------------------------------------------------
*/
$author_name = 'Instructor';

$stmt = $conn->prepare("
    SELECT fname, lname
    FROM users
    WHERE unique_id = ? OR id = ?
    LIMIT 1
");

if ($stmt) {
    $userIdAsInt = is_numeric($user_id) ? (int)$user_id : 0;

    $stmt->bind_param("si", $user_id, $userIdAsInt);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $full = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));

        if ($full !== '') {
            $author_name = $full;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Insert in-app notification and email student
|--------------------------------------------------------------------------
*/
$email_notice = 'Student email notification not sent.';

if ($parent_comment_id !== null && $student) {
    $student_id = (string)($student['user_id'] ?? '');

    if ($student_id !== '') {
        $notifStmt = $conn->prepare("
            INSERT INTO notifications
                (user_id, message, type, created_at)
            VALUES
                (?, 'Instructor replied to your comment', 'comment_reply', NOW())
        ");

        if ($notifStmt) {
            $notifStmt->bind_param("s", $student_id);
            $notifStmt->execute();
            $notifStmt->close();
        }
    }

    try {
        $studentEmail = trim((string)($student['email'] ?? ''));

        if ($studentEmail !== '') {
            $studentName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
            $noteTitle   = (string)($note['title'] ?? 'Course Note');

            $safeStudent    = htmlspecialchars($studentName ?: $studentEmail, ENT_QUOTES, 'UTF-8');
            $safeInstructor = htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8');
            $safeNote       = htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8');
            $safeReply      = nl2br(htmlspecialchars($comment_text, ENT_QUOTES, 'UTF-8'));

            $body = "
                <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
                    <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:14px;
                                overflow:hidden;border:1px solid #e1e5ea;'>
                        <div style='background:#5a0e0e;color:#fff;padding:22px;border-bottom:4px solid #8b7536;'>
                            <h2 style='margin:0;'>Instructor Replied to Your Comment</h2>
                        </div>
                        <div style='padding:24px;'>
                            <p>Dear {$safeStudent},</p>

                            <p><strong>{$safeInstructor}</strong> replied to your comment on 
                               <strong>{$safeNote}</strong>.</p>

                            <div style='background:#f6f7fa;padding:16px;border-radius:10px;
                                        border-left:4px solid #5a0e0e;margin:18px 0;'>
                                {$safeReply}
                            </div>

                            <p style='font-size:13px;color:#555;margin-top:24px;'>
                                Regards,<br>
                                <strong>Elevate Her 360 eLearning Team</strong>
                            </p>
                        </div>
                    </div>
                </div>
            ";

            $emailService = new EmailService();

            $sent = $emailService->sendEmail(
                $studentEmail,
                $studentName,
                "Instructor Replied to Your Comment",
                $body
            );

            $email_notice = $sent
                ? 'Student email notification sent.'
                : 'Reply posted, but student email notification was not sent.';
        }
    } catch (Throwable $e) {
        error_log("Instructor reply email failed: " . $e->getMessage());
        $email_notice = 'Reply posted, but student email notification failed.';
    }
}

jsonOk([
    'success' => true,
    'message' => 'Reply posted successfully',
    'email_notice' => $email_notice,
    'comment' => [
        'id' => $comment_id,
        'comment_text' => $comment_text,
        'author_name' => htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8'),
        'time_ago' => 'just now',
        'is_instructor' => true
    ]
]);
?>