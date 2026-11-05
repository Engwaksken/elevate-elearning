<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';

date_default_timezone_set('Africa/Nairobi');

$systemname  = $systemname ?? "Elevate Her 360";
$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$role        = $_SESSION[$systemname . "letype"] ?? '';

function back(string $msg, string $type = 'errormsg'): never
{
    global $systemname;

    $_SESSION[$systemname . $type] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
    exit;
}

function cleanUrl(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    back("Database connection not available.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_note'])) {
    back("Invalid request.");
}

$id      = (int)($_POST['note_id'] ?? 0);
$title   = trim((string)($_POST['title'] ?? ''));
$message = trim((string)($_POST['short_message'] ?? ''));

$status = trim((string)($_POST['status'] ?? 'active'));
if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

$class_mode = trim((string)($_POST['class_mode'] ?? 'both'));
if (!in_array($class_mode, ['online', 'physical', 'both'], true)) {
    $class_mode = 'both';
}

$content_type = trim((string)($_POST['content_type'] ?? 'file'));
if (!in_array($content_type, ['file', 'url'], true)) {
    $content_type = 'file';
}

if ($id <= 0 || $title === '') {
    back("Note title is required.");
}

/*
|--------------------------------------------------------------------------
| Fetch note
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT 
        n.id,
        n.title,
        n.short_message,
        n.document,
        n.content_type,
        n.notes_url,
        n.uploaded_by,
        n.course_unit,
        n.intake
    FROM course_unit_notes n
    WHERE n.id = ?
    LIMIT 1
");

if (!$stmt) {
    back("Failed to prepare note query: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    back("Note not found.");
}

if ($role !== "xybrtw" && (string)$row['uploaded_by'] !== (string)$user_unique) {
    back("You are not allowed to edit this note.");
}

$document  = (string)($row['document'] ?? '');
$notes_url = (string)($row['notes_url'] ?? '');

if ($content_type === 'url') {
    $newUrl = cleanUrl((string)($_POST['notes_url'] ?? ''));

    if ($newUrl === '') {
        back("Please enter a valid notes URL.");
    }

    if ($document !== '') {
        $oldPath = __DIR__ . '/../notes/' . basename($document);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $document  = '';
    $notes_url = $newUrl;
} else {
    $notes_url = '';

    if (!empty($_FILES['document']['name'])) {
        $dir = __DIR__ . '/../notes/';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));

        $allowed = [
            'pdf', 'doc', 'docx', 'ppt', 'pptx',
            'xls', 'xlsx', 'jpg', 'jpeg', 'png',
            'gif', 'webp', 'mp3', 'wav', 'm4a',
            'mp4', 'webm', 'avi', 'zip', 'rar',
            '7z', 'txt', 'csv'
        ];

        if (!in_array($ext, $allowed, true)) {
            back("Invalid file type.");
        }

        if ((int)$_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            back("File upload failed. Please try again.");
        }

        $newDocument = 'note_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;

        if (!move_uploaded_file($_FILES['document']['tmp_name'], $dir . $newDocument)) {
            back("Failed to upload note document.");
        }
        require_once __DIR__ . '/upload_compressor.php';
        eh_compress_upload($dir . $newDocument);

        if ($document !== '') {
            $oldPath = $dir . basename($document);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $document = $newDocument;
    }

    if ($document === '') {
        back("Please upload a note file or select URL.");
    }
}

/*
|--------------------------------------------------------------------------
| Update note
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    UPDATE course_unit_notes
    SET 
        title = ?,
        short_message = ?,
        document = ?,
        content_type = ?,
        notes_url = ?,
        status = ?,
        class_mode = ?
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    back("Failed to prepare update query: " . $conn->error);
}

$stmt->bind_param(
    "sssssssi",
    $title,
    $message,
    $document,
    $content_type,
    $notes_url,
    $status,
    $class_mode,
    $id
);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    back("Failed to update note: " . $error);
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Email notification
|--------------------------------------------------------------------------
| Your old code used course_id/intake_id columns, but this notes table uses
| course_unit and intake. We resolve course_id from course_unit.
|--------------------------------------------------------------------------
*/
$courseId = 0;
$intakeId = (int)($row['intake'] ?? 0);
$courseUnit = (int)($row['course_unit'] ?? 0);

if ($courseUnit > 0) {
    $stmtCourse = $conn->prepare("SELECT course_id FROM course_unit WHERE id = ? LIMIT 1");
    if ($stmtCourse) {
        $stmtCourse->bind_param("i", $courseUnit);
        $stmtCourse->execute();
        $courseRow = $stmtCourse->get_result()->fetch_assoc();
        $stmtCourse->close();

        $courseId = (int)($courseRow['course_id'] ?? 0);
    }
}

if ($courseId > 0 && $intakeId > 0) {
    try {
        $emailService = new EmailService();

        $emailResult = $emailService->notifyUpdatedNote(
            $courseId,
            $intakeId,
            $title
        );

        $sent   = (int)($emailResult['sent'] ?? 0);
        $failed = (int)($emailResult['failed'] ?? 0);

        if ($sent > 0 && $failed <= 0) {
            back("Note updated successfully. Email notification sent to {$sent} student(s).", "successmsg");
        }

        if ($sent > 0 && $failed > 0) {
            back("Note updated successfully. Email sent to {$sent} student(s), but {$failed} failed.", "successmsg");
        }

        back("Note updated successfully. No student email notification was sent.", "successmsg");
    } catch (Throwable $e) {
        error_log("Updated note email notification failed: " . $e->getMessage());
        back("Note updated successfully, but email notification failed.", "successmsg");
    }
}

back("Note updated successfully.", "successmsg");