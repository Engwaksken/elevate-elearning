<?php
session_start();
require_once __DIR__ . '/connector.php';

$systemname = $systemname ?? "Elevate Her 360";
$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$role = $_SESSION[$systemname . "letype"] ?? '';

function back($msg, $type = 'errormsg') {
    global $systemname;
    $_SESSION[$systemname . $type] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_assignment'])) {
    back("Invalid request.");
}

$id = (int)($_POST['assignment_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['short_message'] ?? '');
$date_due = trim($_POST['date_due'] ?? '');

if ($id <= 0 || $title === '') {
    back("Assignment title is required.");
}

$stmt = $conn->prepare("SELECT document, uploaded_by FROM assignments WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    back("Assignment not found.");
}

if ($role !== "xybrtw" && $row['uploaded_by'] !== $user_unique) {
    back("You are not allowed to edit this assignment.");
}

$document = $row['document'];

if (!empty($_FILES['document']['name'])) {
    $dir = __DIR__ . '/../assignments/agjashdbsjdhkhsdvfh/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','mp3','mp4','zip'];

    if (!in_array($ext, $allowed, true)) {
        back("Invalid file type.");
    }

    $document = 'assignment_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['document']['tmp_name'], $dir . $document)) {
        back("Failed to upload the assignment document.");
    }
    require_once __DIR__ . '/upload_compressor.php';
    eh_compress_upload($dir . $document);
}

$date_due = $date_due !== '' ? date('Y-m-d H:i:s', strtotime($date_due)) : null;

$stmt = $conn->prepare("
    UPDATE assignments
    SET title = ?, short_message = ?, date_due = ?, document = ?
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("ssssi", $title, $message, $date_due, $document, $id);

if (!$stmt->execute()) {
    back("Failed to update assignment.");
}

back("Assignment updated successfully.", "successmsg");