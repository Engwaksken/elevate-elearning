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

if (
    empty($_SESSION[$systemname . 'leido']) ||
    empty($_SESSION[$systemname . 'letype'])
) {
    header('Location: ../index.php?view=home');
    exit;
}

$uid      = trim((string)$_SESSION[$systemname . 'leido']);
$acc_type = trim((string)$_SESSION[$systemname . 'letype']);

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
    : 0;

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

$filetype = (int)($_POST['filetype'] ?? 3);
$expectedfiletype = (int)(
    $_POST['expectedfiletype'] ??
    $_POST['expected_type'] ??
    3
);

$dummy = ($intake === '') ? 'yes' : 'no';
$link  = buildRedirectLink($acc_type, $course, $intake);

if ($title === '' || $module === '' || $course === '') {
    redirectWithMessage($link, 'errormsg', 'Please fill in all required fields.');
}

if ($shortnotes === '') {
    redirectWithMessage($link, 'errormsg', 'Please enter assignment instructions.');
}

$dateDueRaw = trim((string)(
    $_POST['date_due'] ??
    ''
));

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

$final_datedue = date('Y-m-d H:i:s', $dueTimestamp);

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
        redirectWithMessage($link, 'errormsg', 'Please upload an assignment file or select URL.');
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

    $originalName = (string)$_FILES['document']['name'];
    $tmpName      = (string)$_FILES['document']['tmp_name'];
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($originalName === '' || $tmpName === '' || $ext === '') {
        redirectWithMessage($link, 'errormsg', 'Invalid uploaded file.');
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

    if (!in_array($filetype, [1, 2, 3, 4], true)) {
        redirectWithMessage($link, 'errormsg', 'Invalid file type selected.');
    }

    try {
        $document = uniqid('asg_', true) . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    } catch (Throwable $e) {
        $document = uniqid('asg_', true) . '_' . mt_rand(10000, 99999) . '.' . $ext;
    }

    $destination = $target_dir . $document;

    if (!move_uploaded_file($tmpName, $destination)) {
        redirectWithMessage($link, 'errormsg', $title . ' assignment upload failed.');
    }
}

$sql = "
    INSERT INTO assignments (
        document,
        content_type,
        assignment_url,
        title,
        marks,
        file_type,
        course_unit,
        short_message,
        uploaded_by,
        date_uploaded,
        date_due,
        expectedtype,
        dummy,
        intake,
        class_mode,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    if ($document !== '') {
        @unlink('../assignments/agjashdbsjdhkhsdvfh/' . $document);
    }

    redirectWithMessage($link, 'errormsg', 'Failed to prepare assignment query: ' . $conn->error);
}

$stmt->bind_param(
    'ssssdisssssissss',
    $document,
    $content_type,
    $assignment_url,
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
    $intake,
    $class_mode,
    $status
);

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

try {
    if ($course !== '' && $intake !== '') {
        $emailService = new EmailService();

        $emailResult = $emailService->notifyNewAssignment(
            (int)$course,
            (int)$intake,
            $title,
            $final_datedue
        );

        $sent   = (int)($emailResult['sent'] ?? 0);
        $failed = (int)($emailResult['failed'] ?? 0);

        if ($sent > 0 && $failed <= 0) {
            $emailMessage .= ". Email notification sent to {$sent} student(s).";
        } elseif ($sent > 0 && $failed > 0) {
            $emailMessage .= ". Email sent to {$sent} student(s), but {$failed} failed.";
        } else {
            $emailMessage .= ". No student email notification was sent.";
        }
    }
} catch (Throwable $e) {
    error_log("New assignment email notification failed: " . $e->getMessage());
    $emailMessage .= ". Email notification failed.";
}

$conn->close();

redirectWithMessage($link, 'successmsg', $emailMessage);