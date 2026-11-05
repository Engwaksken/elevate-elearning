<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/connector.php';

$systemname = $systemname ?? '';

function setMsg(string $key, string $msg): void
{
    global $systemname;
    $_SESSION[$systemname . $key] = $msg;
}

function redirectTo(string $url): never
{
    header("Location: {$url}");
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

if (!isset($conn) || !($conn instanceof mysqli)) {
    setMsg('errormsg', 'Database connection not available.');
    redirectTo('../index.php?view=home');
}

$acc_type = (string)($_SESSION[$systemname . 'letype'] ?? '');
$uid      = trim((string)($_SESSION[$systemname . 'leido'] ?? ''));

$noteId = 0;
$course = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noteId = (int)(
        $_POST['note_id'] ??
        $_POST['id'] ??
        0
    );

    $course = trim((string)(
        $_POST['course_id'] ??
        $_POST['course'] ??
        ''
    ));
} else {
    $noteId = (int)($_GET['find'] ?? 0);

    $course = trim((string)(
        $_GET['bengani'] ??
        $_GET['course'] ??
        ''
    ));
}

if ($noteId <= 0) {
    setMsg('errormsg', 'Invalid note selected.');
    redirectTo('../index.php?view=home');
}

$stmt = $conn->prepare("
    SELECT
        n.id,
        n.document,
        n.content_type,
        n.notes_url,
        n.uploaded_by,
        n.course_unit,
        n.intake,
        cu.course_id
    FROM course_unit_notes n
    LEFT JOIN course_unit cu ON cu.id = n.course_unit
    WHERE n.id = ?
    LIMIT 1
");

if (!$stmt) {
    setMsg('errormsg', 'Failed to prepare note query: ' . $conn->error);
    redirectTo('../index.php?view=home');
}

$stmt->bind_param("i", $noteId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    setMsg('errormsg', 'Note not found.');
    redirectTo('../index.php?view=home');
}

if ($role = $acc_type) {
    // keep role value available
}

if ($acc_type !== 'xybrtw' && (string)$row['uploaded_by'] !== $uid) {
    setMsg('errormsg', 'You are not allowed to delete this note.');
    $redirectCourse = $course !== '' ? $course : (string)($row['course_id'] ?? '');
    redirectTo(buildRedirectLink($acc_type, $redirectCourse, (string)($row['intake'] ?? '')));
}

$redirectCourse = $course !== '' ? $course : (string)($row['course_id'] ?? '');
$link = buildRedirectLink($acc_type, $redirectCourse, (string)($row['intake'] ?? ''));

$document = basename((string)($row['document'] ?? ''));
$contentType = (string)($row['content_type'] ?? 'file');

if ($contentType !== 'url' && $document !== '') {
    $filePath = __DIR__ . '/../notes/' . $document;

    if (is_file($filePath) && !@unlink($filePath)) {
        setMsg('errormsg', 'File delete failed.');
        redirectTo($link);
    }
}

$del = $conn->prepare("DELETE FROM course_unit_notes WHERE id = ? LIMIT 1");

if (!$del) {
    setMsg('errormsg', 'Failed to prepare delete query: ' . $conn->error);
    redirectTo($link);
}

$del->bind_param("i", $noteId);

if ($del->execute()) {
    setMsg('successmsg', 'Note removed successfully.');
} else {
    setMsg('errormsg', 'Failed to delete note record.');
}

$del->close();
$conn->close();

redirectTo($link);