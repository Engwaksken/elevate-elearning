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

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $exists;
}

function normalizeStatus(string $status): string
{
    return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
}

function normalizeClassMode(string $classMode): string
{
    return in_array($classMode, ['online', 'physical', 'both'], true) ? $classMode : 'both';
}

function normalizeContentType(string $contentType): string
{
    return in_array($contentType, ['file', 'url', 'existing'], true) ? $contentType : 'file';
}

function normalizeFileType(int $fileType): int
{
    return in_array($fileType, [1, 2, 3, 4], true) ? $fileType : 3;
}

function detectFileTypeFromExtension(string $ext): int
{
    $ext = strtolower($ext);

    $audio = ['mp3', 'wav', 'm4a'];
    $video = ['mp4', 'webm', 'avi'];
    $image = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    if (in_array($ext, $audio, true)) {
        return 1;
    }

    if (in_array($ext, $video, true)) {
        return 2;
    }

    if (in_array($ext, $image, true)) {
        return 4;
    }

    return 3;
}

function validateUploadedFileType(int $fileType, string $ext, string $link): void
{
    $allowedDoc = [
        'doc', 'docx', 'pdf', 'ppt', 'pptx',
        'xls', 'xlsx', 'xlsm', 'xlsb', 'csv',
        'sql', 'db', 'sqlite', 'mdb',
        'zip', 'rar', '7z', 'tar', 'gz',
        'txt', 'rtf', 'odt', 'ods', 'odp',
        'html', 'htm', 'xml', 'json', 'log'
    ];

    $allowedImage = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $allowedVideo = ['mp4', 'webm', 'avi'];
    $allowedAudio = ['mp3', 'wav', 'm4a'];

    if ($fileType === 1 && !in_array($ext, $allowedAudio, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted audio file format.');
    }

    if ($fileType === 2 && !in_array($ext, $allowedVideo, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted video file format.');
    }

    if ($fileType === 3 && !in_array($ext, $allowedDoc, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted document file format.');
    }

    if ($fileType === 4 && !in_array($ext, $allowedImage, true)) {
        redirectWithMessage($link, 'errormsg', 'Unaccepted image format.');
    }
}

function calculateDueDate(string $link): string
{
    $dateDueRaw = trim((string)($_POST['date_due'] ?? ''));

    if ($dateDueRaw !== '') {
        $dueTimestamp = strtotime($dateDueRaw);
    } else {
        $dateInput = trim((string)($_POST['date'] ?? ''));
        $timeInput = trim((string)($_POST['time'] ?? '23:59'));
        $dueTimestamp = strtotime(trim($dateInput . ' ' . $timeInput));
    }

    if ($dueTimestamp === false) {
        redirectWithMessage($link, 'errormsg', 'Invalid due date or time.');
    }

    return date('Y-m-d H:i:s', $dueTimestamp);
}

function notifyAssignmentStudents(string $course, string $intake, string $title, string $dueDate, string $message): string
{
    try {
        if ($course !== '' && $intake !== '') {
            $emailService = new EmailService();

            $emailResult = $emailService->notifyNewAssignment(
                (int)$course,
                (int)$intake,
                $title,
                $dueDate
            );

            $sent   = (int)($emailResult['sent'] ?? 0);
            $failed = (int)($emailResult['failed'] ?? 0);

            if ($sent > 0 && $failed <= 0) {
                $message .= ". Email notification sent to {$sent} student(s).";
            } elseif ($sent > 0 && $failed > 0) {
                $message .= ". Email sent to {$sent} student(s), but {$failed} failed.";
            } else {
                $message .= ". No student email notification was sent.";
            }
        }
    } catch (Throwable $e) {
        error_log("New assignment email notification failed: " . $e->getMessage());
        $message .= ". Email notification failed.";
    }

    return $message;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION[$systemname . 'leido']) ||
    empty($_SESSION[$systemname . 'letype'])
) {
    header('Location: ../?view=home');
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

$intake = trim((string)($_POST['intake'] ?? ''));

$marks = isset($_POST['marks']) && $_POST['marks'] !== ''
    ? (float)$_POST['marks']
    : 0.0;

$status = normalizeStatus(trim((string)($_POST['status'] ?? 'active')));
$class_mode = normalizeClassMode(trim((string)($_POST['class_mode'] ?? 'both')));
$content_type = normalizeContentType(trim((string)($_POST['content_type'] ?? 'file')));

$filetype = normalizeFileType((int)($_POST['filetype'] ?? 3));
$expectedfiletype = normalizeFileType((int)(
    $_POST['expectedfiletype'] ??
    $_POST['expected_type'] ??
    3
));

$existingAssignmentId = (int)(
    $_POST['existing_assignment_id'] ??
    $_POST['existing_assignment'] ??
    $_POST['assignment_existing_id'] ??
    0
);

$dummy = ($intake === '') ? 'yes' : 'no';
$link  = buildRedirectLink($acc_type, $course, $intake);

/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/
if ($module === '' || $course === '') {
    redirectWithMessage($link, 'errormsg', 'Please select course and module.');
}

if ($content_type !== 'existing' && $title === '') {
    redirectWithMessage($link, 'errormsg', 'Please enter assignment title.');
}

if ($content_type !== 'existing' && $shortnotes === '') {
    redirectWithMessage($link, 'errormsg', 'Please enter assignment instructions.');
}

if ($content_type === 'existing' && $existingAssignmentId <= 0) {
    redirectWithMessage($link, 'errormsg', 'Please select an existing assignment to assign.');
}

/*
|--------------------------------------------------------------------------
| Existing assignment reuse/copy
|--------------------------------------------------------------------------
| This copies the database row but keeps the same document / URL, avoiding
| reuploading. It assigns it to the selected module and intake.
|--------------------------------------------------------------------------
*/
if ($content_type === 'existing') {
    if ($intake === '') {
        redirectWithMessage($link, 'errormsg', 'Please select an intake before assigning an existing assignment.');
    }

    $hasContentType   = columnExists($conn, 'assignments', 'content_type');
    $hasAssignmentUrl = columnExists($conn, 'assignments', 'assignment_url');
    $hasClassMode     = columnExists($conn, 'assignments', 'class_mode');
    $hasStatus        = columnExists($conn, 'assignments', 'status');

    $selectColumns = [
        'id',
        'document',
        'title',
        'marks',
        'file_type',
        'course_unit',
        'short_message',
        'uploaded_by',
        'date_due',
        'expectedtype',
        'dummy',
        'intake'
    ];

    if ($hasContentType) {
        $selectColumns[] = 'content_type';
    }

    if ($hasAssignmentUrl) {
        $selectColumns[] = 'assignment_url';
    }

    if ($hasClassMode) {
        $selectColumns[] = 'class_mode';
    }

    if ($hasStatus) {
        $selectColumns[] = 'status';
    }

    $sqlExisting = "
        SELECT " . implode(', ', $selectColumns) . "
        FROM assignments
        WHERE id = ?
          AND (uploaded_by = ? OR ? = 'xybrtw')
        LIMIT 1
    ";

    $stmtExisting = $conn->prepare($sqlExisting);

    if (!$stmtExisting) {
        redirectWithMessage($link, 'errormsg', 'Failed to prepare existing assignment query: ' . $conn->error);
    }

    $stmtExisting->bind_param('iss', $existingAssignmentId, $uid, $acc_type);
    $stmtExisting->execute();
    $existing = $stmtExisting->get_result()->fetch_assoc();
    $stmtExisting->close();

    if (!$existing) {
        redirectWithMessage($link, 'errormsg', 'Selected assignment was not found or you do not have permission to use it.');
    }

    $copyTitle = $title !== '' ? $title : (string)($existing['title'] ?? '');
    $copyShortMessage = $shortnotes !== '' ? $shortnotes : (string)($existing['short_message'] ?? '');
    $copyMarks = isset($_POST['marks']) && $_POST['marks'] !== '' ? $marks : (float)($existing['marks'] ?? 0);
    $copyFileType = isset($_POST['filetype']) && $_POST['filetype'] !== ''
        ? $filetype
        : normalizeFileType((int)($existing['file_type'] ?? 3));

    $copyExpectedType = isset($_POST['expectedfiletype']) || isset($_POST['expected_type'])
        ? $expectedfiletype
        : normalizeFileType((int)($existing['expectedtype'] ?? 3));

    $copyClassMode = isset($_POST['class_mode'])
        ? $class_mode
        : normalizeClassMode((string)($existing['class_mode'] ?? 'both'));

    $copyStatus = isset($_POST['status'])
        ? $status
        : normalizeStatus((string)($existing['status'] ?? 'active'));

    $copyContentType = normalizeContentType((string)($existing['content_type'] ?? 'file'));
    if ($copyContentType === 'existing') {
        $copyContentType = 'file';
    }

    $copyAssignmentUrl = (string)($existing['assignment_url'] ?? '');
    $copyDocument = (string)($existing['document'] ?? '');

    $copyDueDate = trim((string)($_POST['date_due'] ?? '')) !== '' || trim((string)($_POST['date'] ?? '')) !== ''
        ? calculateDueDate($link)
        : (string)($existing['date_due'] ?? '');

    if ($copyDueDate === '' || strtotime($copyDueDate) === false) {
        $copyDueDate = date('Y-m-d H:i:s', strtotime('+7 days'));
    } else {
        $copyDueDate = date('Y-m-d H:i:s', strtotime($copyDueDate));
    }

    if ($copyTitle === '' || $copyShortMessage === '') {
        redirectWithMessage($link, 'errormsg', 'The selected assignment has missing title or instructions.');
    }

    /*
    | Prevent exact duplicate assignment for same module/intake/title/document/url.
    */
    if ($hasAssignmentUrl) {
        $dupSql = "
            SELECT id
            FROM assignments
            WHERE course_unit = ?
              AND intake = ?
              AND title = ?
              AND IFNULL(document, '') = ?
              AND IFNULL(assignment_url, '') = ?
              AND (dummy IS NULL OR dummy != 'yes')
            LIMIT 1
        ";

        $dupStmt = $conn->prepare($dupSql);
        if ($dupStmt) {
            $dupStmt->bind_param('sssss', $module, $intake, $copyTitle, $copyDocument, $copyAssignmentUrl);
            $dupStmt->execute();
            $duplicate = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();

            if ($duplicate) {
                redirectWithMessage($link, 'errormsg', 'This assignment is already assigned to the selected intake.');
            }
        }
    }

    $insertColumns = [
        'document',
        'title',
        'marks',
        'file_type',
        'course_unit',
        'short_message',
        'uploaded_by',
        'date_uploaded',
        'date_due',
        'expectedtype',
        'dummy',
        'intake'
    ];

    $insertTypes = 'ssdisssssiss';
    $insertValues = [
        $copyDocument,
        $copyTitle,
        $copyMarks,
        $copyFileType,
        $module,
        $copyShortMessage,
        $uid,
        $nowdate,
        $copyDueDate,
        $copyExpectedType,
        'no',
        $intake
    ];

    if ($hasContentType) {
        array_splice($insertColumns, 1, 0, ['content_type']);
        $insertTypes = 'ss' . substr($insertTypes, 1);
        array_splice($insertValues, 1, 0, [$copyContentType]);
    }

    if ($hasAssignmentUrl) {
        $position = $hasContentType ? 2 : 1;
        array_splice($insertColumns, $position, 0, ['assignment_url']);
        $insertTypes = substr($insertTypes, 0, $position) . 's' . substr($insertTypes, $position);
        array_splice($insertValues, $position, 0, [$copyAssignmentUrl]);
    }

    if ($hasClassMode) {
        $insertColumns[] = 'class_mode';
        $insertTypes .= 's';
        $insertValues[] = $copyClassMode;
    }

    if ($hasStatus) {
        $insertColumns[] = 'status';
        $insertTypes .= 's';
        $insertValues[] = $copyStatus;
    }

    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));

    $insertSql = "
        INSERT INTO assignments (" . implode(', ', $insertColumns) . ")
        VALUES ({$placeholders})
    ";

    $stmtInsert = $conn->prepare($insertSql);

    if (!$stmtInsert) {
        redirectWithMessage($link, 'errormsg', 'Failed to prepare assignment copy query: ' . $conn->error);
    }

    $stmtInsert->bind_param($insertTypes, ...$insertValues);

    if (!$stmtInsert->execute()) {
        $error = $stmtInsert->error;
        $stmtInsert->close();
        redirectWithMessage($link, 'errormsg', 'Failed to assign existing assignment: ' . $error);
    }

    $stmtInsert->close();

    $message = notifyAssignmentStudents(
        $course,
        $intake,
        $copyTitle,
        $copyDueDate,
        $copyTitle . ' has been assigned to the selected intake'
    );

    $conn->close();
    redirectWithMessage($link, 'successmsg', $message);
}

/*
|--------------------------------------------------------------------------
| New file / URL assignment
|--------------------------------------------------------------------------
*/
$final_datedue = calculateDueDate($link);

$document = '';
$assignment_url = '';

if ($content_type === 'url') {
    $assignment_url = cleanUrl((string)($_POST['assignment_url'] ?? ''));

    if ($assignment_url === '') {
        redirectWithMessage($link, 'errormsg', 'Please enter a valid assignment URL.');
    }

    $filetype = 3;
} else {
    if (!isset($_FILES['document']) && isset($_FILES['notesfile'])) {
        $_FILES['document'] = $_FILES['notesfile'];
    }

    if (!isset($_FILES['document']) || !is_array($_FILES['document'])) {
        redirectWithMessage($link, 'errormsg', 'Please upload an assignment file, add URL, or select an existing assignment.');
    }

    if ((int)$_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        redirectWithMessage($link, 'errormsg', 'File upload failed.');
    }

    $target_dir = '../assignments/agjashdbsjdhkhsdvfh/';

    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0775, true);
    }

    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        redirectWithMessage($link, 'errormsg', 'Upload directory is missing or not writable.');
    }

    $originalName = (string)$_FILES['document']['name'];
    $tmpName      = (string)$_FILES['document']['tmp_name'];
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($originalName === '' || $tmpName === '' || $ext === '') {
        redirectWithMessage($link, 'errormsg', 'Invalid uploaded file.');
    }

    if (!in_array($filetype, [1, 2, 3, 4], true)) {
        $filetype = detectFileTypeFromExtension($ext);
    }

    validateUploadedFileType($filetype, $ext, $link);

    try {
        $document = uniqid('asg_', true) . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    } catch (Throwable $e) {
        $document = uniqid('asg_', true) . '_' . mt_rand(10000, 99999) . '.' . $ext;
    }

    $destination = $target_dir . $document;

    if (!move_uploaded_file($tmpName, $destination)) {
        redirectWithMessage($link, 'errormsg', $title . ' assignment upload failed.');
    }
    require_once __DIR__ . '/upload_compressor.php';
    eh_compress_upload($destination);
}

/*
|--------------------------------------------------------------------------
| Save new assignment
|--------------------------------------------------------------------------
*/
$hasContentType   = columnExists($conn, 'assignments', 'content_type');
$hasAssignmentUrl = columnExists($conn, 'assignments', 'assignment_url');
$hasClassMode     = columnExists($conn, 'assignments', 'class_mode');
$hasStatus        = columnExists($conn, 'assignments', 'status');

$insertColumns = [
    'document',
    'title',
    'marks',
    'file_type',
    'course_unit',
    'short_message',
    'uploaded_by',
    'date_uploaded',
    'date_due',
    'expectedtype',
    'dummy',
    'intake'
];

$insertTypes = 'ssdisssssiss';
$insertValues = [
    $document,
    $title,
    $marks,
    $filetype,
    $module,
    $shortnotes,
    $uid,
    $nowdate,
    $final_datedue,
    $expectedfiletype,
    $dummy,
    $intake
];

if ($hasContentType) {
    array_splice($insertColumns, 1, 0, ['content_type']);
    $insertTypes = 'ss' . substr($insertTypes, 1);
    array_splice($insertValues, 1, 0, [$content_type]);
}

if ($hasAssignmentUrl) {
    $position = $hasContentType ? 2 : 1;
    array_splice($insertColumns, $position, 0, ['assignment_url']);
    $insertTypes = substr($insertTypes, 0, $position) . 's' . substr($insertTypes, $position);
    array_splice($insertValues, $position, 0, [$assignment_url]);
}

if ($hasClassMode) {
    $insertColumns[] = 'class_mode';
    $insertTypes .= 's';
    $insertValues[] = $class_mode;
}

if ($hasStatus) {
    $insertColumns[] = 'status';
    $insertTypes .= 's';
    $insertValues[] = $status;
}

$placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));

$sql = "
    INSERT INTO assignments (" . implode(', ', $insertColumns) . ")
    VALUES ({$placeholders})
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    if ($document !== '') {
        @unlink('../assignments/agjashdbsjdhkhsdvfh/' . $document);
    }

    redirectWithMessage($link, 'errormsg', 'Failed to prepare assignment query: ' . $conn->error);
}

$stmt->bind_param($insertTypes, ...$insertValues);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();

    if ($document !== '') {
        @unlink('../assignments/agjashdbsjdhkhsdvfh/' . $document);
    }

    redirectWithMessage($link, 'errormsg', 'Failed to save assignment: ' . $error);
}

$stmt->close();

$emailMessage = $content_type === 'url'
    ? $title . ' Assignment URL has been saved'
    : $title . ' Assignment has been uploaded';

$emailMessage = notifyAssignmentStudents(
    $course,
    $intake,
    $title,
    $final_datedue,
    $emailMessage
);

$conn->close();

redirectWithMessage($link, 'successmsg', $emailMessage);
