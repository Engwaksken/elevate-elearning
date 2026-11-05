<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';

date_default_timezone_set('Africa/Nairobi');

$nowdate = date('Y-m-d H:i:s');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    exit('Database connection failed.');
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function redirectWithMessage(string $link, string $sessionKey, string $message): never
{
    global $systemname;

    $_SESSION[$systemname . $sessionKey] = $message;
    header('Location: ' . $link);
    exit;
}

function buildRedirectLink(string $accType, string $course, string $intake = ''): string
{
    $course = urlencode($course);
    $intake = trim($intake);

    if ($accType === 'xybrtw') {
        $base = "../index.php?view=addmaterial&course={$course}";
    } elseif ($accType === 'xtfbry') {
        $base = "../index.php?view=open_coursex&course={$course}";
    } else {
        $base = "../index.php?view=home";
    }

    if ($intake !== '') {
        $base .= '&ausydfgasuidgipppuwqu=' . urlencode($intake);
    }

    return $base;
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

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$key] = ((int)($row['total'] ?? 0) > 0);
    return $cache[$key];
}

function normalizeStatus(string $status): string
{
    $status = trim($status);
    return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
}

function normalizeClassMode(string $classMode): string
{
    $classMode = trim($classMode);
    return in_array($classMode, ['online', 'physical', 'both'], true) ? $classMode : 'both';
}

function normalizeContentType(string $contentType): string
{
    $contentType = trim($contentType);
    return in_array($contentType, ['file', 'url', 'existing'], true) ? $contentType : 'file';
}

function detectFileTypeFromExtension(string $ext): int
{
    $ext = strtolower($ext);

    $audio = ['mp3', 'wav', 'm4a'];
    $video = ['mp4', 'webm', 'avi'];
    $image = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    if (in_array($ext, $audio, true)) return 1;
    if (in_array($ext, $video, true)) return 2;
    if (in_array($ext, $image, true)) return 4;

    return 3;
}

function uploadNoteFile(string $link, int $filetype): array
{
    if (!isset($_FILES['document']) && isset($_FILES['notesfile'])) {
        $_FILES['document'] = $_FILES['notesfile'];
    }

    if (!isset($_FILES['document']) || !is_array($_FILES['document'])) {
        redirectWithMessage($link, 'errormsg', 'Please upload a notes file, enter a URL, or select an existing note.');
    }

    if ((int)($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        redirectWithMessage($link, 'errormsg', 'File upload failed.');
    }

    $target_dir = '../notes/';

    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0775, true);
    }

    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        redirectWithMessage($link, 'errormsg', 'Upload directory is missing or not writable.');
    }

    $allowed_doc = [
        'doc', 'docx', 'pdf', 'ppt', 'pptx',
        'xls', 'xlsx', 'xlsm', 'xlsb', 'csv',
        'sql', 'db', 'sqlite', 'mdb',
        'zip', 'rar', '7z', 'tar', 'gz',
        'txt', 'rtf', 'odt', 'ods', 'odp',
        'html', 'htm', 'xml', 'json', 'log'
    ];

    $allowed_image = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $allowed_video = ['mp4', 'webm', 'avi'];
    $allowed_audio = ['mp3', 'wav', 'm4a'];

    $originalName = (string)($_FILES['document']['name'] ?? '');
    $tmpName      = (string)($_FILES['document']['tmp_name'] ?? '');
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($originalName === '' || $tmpName === '' || $ext === '') {
        redirectWithMessage($link, 'errormsg', 'Invalid uploaded file.');
    }

    if (!in_array($filetype, [1, 2, 3, 4], true)) {
        $filetype = detectFileTypeFromExtension($ext);
    }

    if ($filetype === 1 && !in_array($ext, $allowed_audio, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted audio file format.');
    }

    if ($filetype === 2 && !in_array($ext, $allowed_video, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted video file format.');
    }

    if ($filetype === 3 && !in_array($ext, $allowed_doc, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted document file format.');
    }

    if ($filetype === 4 && !in_array($ext, $allowed_image, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted image format.');
    }

    try {
        $document = uniqid('note_', true) . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    } catch (Throwable $e) {
        $document = uniqid('note_', true) . '_' . mt_rand(10000, 99999) . '.' . $ext;
    }

    $destination = $target_dir . $document;

    if (!move_uploaded_file($tmpName, $destination)) {
        redirectWithMessage($link, 'errormsg', 'File upload failed.');
    }

    return [$document, $filetype];
}

function sendNoteNotification(string $course, string $intake, string $title): string
{
    if ($course === '' || $intake === '') {
        return '';
    }

    try {
        $emailService = new EmailService();
        $emailResult  = $emailService->notifyNewNote((int)$course, (int)$intake, $title);

        $sent   = (int)($emailResult['sent'] ?? 0);
        $failed = (int)($emailResult['failed'] ?? 0);

        if ($sent > 0 && $failed <= 0) {
            return " Email notification sent to {$sent} student(s).";
        }

        if ($sent > 0 && $failed > 0) {
            return " Email sent to {$sent} student(s), but {$failed} failed.";
        }

        return ' No student email notification was sent.';
    } catch (Throwable $e) {
        error_log('New note email notification failed: ' . $e->getMessage());
        return ' Email notification failed.';
    }
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
if (empty($_SESSION[$systemname . 'leido']) || empty($_SESSION[$systemname . 'letype'])) {
    header('Location: ../index.php?view=home');
    exit;
}

$uid      = trim((string)$_SESSION[$systemname . 'leido']);
$acc_type = trim((string)$_SESSION[$systemname . 'letype']);

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/
$title = trim((string)($_POST['title'] ?? ''));

$module = trim((string)(
    $_POST['course_unit'] ??
    $_POST['themodule'] ??
    ''
));

$course = trim((string)(
    $_POST['course_id'] ??
    $_POST['course'] ??
    ''
));

$shortnotes = trim((string)(
    $_POST['short_message'] ??
    $_POST['shortnotes'] ??
    ''
));

$intake       = trim((string)($_POST['intake'] ?? ''));
$status       = normalizeStatus((string)($_POST['status'] ?? 'active'));
$class_mode   = normalizeClassMode((string)($_POST['class_mode'] ?? 'both'));
$content_type = normalizeContentType((string)($_POST['content_type'] ?? 'file'));
$filetype     = (int)($_POST['filetype'] ?? 3);
$existing_id  = (int)(
    $_POST['existing_note_id'] ??
    $_POST['reuse_note_id'] ??
    $_POST['source_note_id'] ??
    0
);

$dummy = ($intake === '') ? 'yes' : 'no';
$link  = buildRedirectLink($acc_type, $course, $intake);

if (!isset($_POST['add_note'])) {
    redirectWithMessage($link, 'errormsg', 'Invalid request.');
}

if ($module === '' || $course === '') {
    redirectWithMessage($link, 'errormsg', 'Course and module are required.');
}

if ($content_type !== 'existing' && $title === '') {
    redirectWithMessage($link, 'errormsg', 'Please enter the note title.');
}

if ($content_type !== 'existing' && $shortnotes === '') {
    redirectWithMessage($link, 'errormsg', 'Please enter a short message.');
}

/*
|--------------------------------------------------------------------------
| Existing note reuse
|--------------------------------------------------------------------------
| This copies the existing database record to the selected module/intake.
| It does not copy or re-upload the physical file because the existing file/URL
| is already available in the database.
*/
if ($content_type === 'existing') {
    if ($existing_id <= 0) {
        redirectWithMessage($link, 'errormsg', 'Please select an existing note to assign.');
    }

    if ($intake === '') {
        redirectWithMessage($link, 'errormsg', 'Please select an intake before assigning an existing note.');
    }

    $sqlExisting = "
        SELECT
            id,
            document,
            IFNULL(content_type, 'file') AS content_type,
            IFNULL(notes_url, '') AS notes_url,
            title,
            IFNULL(file_type, 3) AS file_type,
            short_message,
            uploaded_by,
            IFNULL(class_mode, 'both') AS class_mode,
            IFNULL(status, 'active') AS status
        FROM course_unit_notes
        WHERE id = ?
          AND course_unit = ?
          AND (dummy IS NULL OR dummy != 'deleted')
        LIMIT 1
    ";

    $stmtExisting = $conn->prepare($sqlExisting);

    if (!$stmtExisting) {
        redirectWithMessage($link, 'errormsg', 'Failed to prepare existing note query: ' . $conn->error);
    }

    $stmtExisting->bind_param('is', $existing_id, $module);
    $stmtExisting->execute();
    $existing = $stmtExisting->get_result()->fetch_assoc();
    $stmtExisting->close();

    if (!$existing) {
        redirectWithMessage($link, 'errormsg', 'Selected existing note was not found for this module.');
    }

    $sourceContentType = normalizeContentType((string)($existing['content_type'] ?? 'file'));
    if ($sourceContentType === 'existing') {
        $sourceContentType = 'file';
    }

    $document       = (string)($existing['document'] ?? '');
    $notes_url      = (string)($existing['notes_url'] ?? '');
    $titleToSave    = $title !== '' ? $title : (string)($existing['title'] ?? 'Untitled Note');
    $messageToSave  = $shortnotes !== '' ? $shortnotes : (string)($existing['short_message'] ?? '');
    $filetypeToSave = (int)($existing['file_type'] ?? 3);
    $classToSave    = normalizeClassMode($class_mode !== '' ? $class_mode : (string)($existing['class_mode'] ?? 'both'));
    $statusToSave   = normalizeStatus($status !== '' ? $status : (string)($existing['status'] ?? 'active'));

    $dupSql = "
        SELECT id
        FROM course_unit_notes
        WHERE course_unit = ?
          AND intake = ?
          AND title = ?
          AND document = ?
          AND IFNULL(notes_url, '') = ?
          AND (dummy IS NULL OR dummy = 'no')
        LIMIT 1
    ";

    $stmtDup = $conn->prepare($dupSql);

    if ($stmtDup) {
        $stmtDup->bind_param('sssss', $module, $intake, $titleToSave, $document, $notes_url);
        $stmtDup->execute();
        $dupRow = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($dupRow) {
            redirectWithMessage($link, 'errormsg', 'This note has already been assigned to the selected intake.');
        }
    }

    $insertSql = "
        INSERT INTO course_unit_notes (
            document,
            content_type,
            notes_url,
            title,
            file_type,
            course_unit,
            short_message,
            uploaded_by,
            date_uploaded,
            dummy,
            intake,
            class_mode,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'no', ?, ?, ?)
    ";

    $stmtInsert = $conn->prepare($insertSql);

    if (!$stmtInsert) {
        redirectWithMessage($link, 'errormsg', 'Failed to prepare note assignment query: ' . $conn->error);
    }

    $stmtInsert->bind_param(
        'ssssisssssss',
        $document,
        $sourceContentType,
        $notes_url,
        $titleToSave,
        $filetypeToSave,
        $module,
        $messageToSave,
        $uid,
        $nowdate,
        $intake,
        $classToSave,
        $statusToSave
    );

    if (!$stmtInsert->execute()) {
        $error = $stmtInsert->error;
        $stmtInsert->close();
        redirectWithMessage($link, 'errormsg', 'Failed to assign existing note: ' . $error);
    }

    $stmtInsert->close();

    $successMessage = $titleToSave . ' has been assigned to the selected intake.';
    $successMessage .= sendNoteNotification($course, $intake, $titleToSave);

    $conn->close();
    redirectWithMessage($link, 'successmsg', $successMessage);
}

/*
|--------------------------------------------------------------------------
| File / URL handling for new notes
|--------------------------------------------------------------------------
*/
$document  = '';
$notes_url = '';

if ($content_type === 'url') {
    $notes_url = cleanUrl((string)($_POST['notes_url'] ?? ''));

    if ($notes_url === '') {
        redirectWithMessage($link, 'errormsg', 'Please enter a valid notes URL.');
    }

    $filetype = 3;
} else {
    [$document, $filetype] = uploadNoteFile($link, $filetype);
    $content_type = 'file';
}

/*
|--------------------------------------------------------------------------
| Save new note
|--------------------------------------------------------------------------
*/
$sql = "
    INSERT INTO course_unit_notes (
        document,
        content_type,
        notes_url,
        title,
        file_type,
        course_unit,
        short_message,
        uploaded_by,
        date_uploaded,
        dummy,
        intake,
        class_mode,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    if ($document !== '') {
        @unlink('../notes/' . $document);
    }

    redirectWithMessage($link, 'errormsg', 'Failed to prepare note query: ' . $conn->error);
}

$stmt->bind_param(
    'ssssissssssss',
    $document,
    $content_type,
    $notes_url,
    $title,
    $filetype,
    $module,
    $shortnotes,
    $uid,
    $nowdate,
    $dummy,
    $intake,
    $class_mode,
    $status
);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();

    if ($document !== '') {
        @unlink('../notes/' . $document);
    }

    redirectWithMessage($link, 'errormsg', 'Failed to save note: ' . $error);
}

$stmt->close();

$successMessage = $content_type === 'url'
    ? $title . ' URL has been saved.'
    : $title . ' file has been uploaded.';

$successMessage .= sendNoteNotification($course, $intake, $title);

$conn->close();

redirectWithMessage($link, 'successmsg', $successMessage);
?>
