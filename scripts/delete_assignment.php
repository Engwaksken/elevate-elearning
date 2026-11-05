<?php
session_start();
require_once __DIR__ . '/connector.php';

$systemname = $systemname ?? "Elevate Her 360";

function back_msg(string $msg, string $type = "errormsg"): void {
    global $systemname;
    $_SESSION[$systemname . $type] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
    exit;
}

$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$role = $_SESSION[$systemname . "letype"] ?? '';

if ($user_unique === '') {
    back_msg("Session expired. Please login again.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_msg("Invalid request.");
}

$assignment_id = (int)($_POST['assignment_id'] ?? $_POST['del_ass_id'] ?? 0);
$document = trim($_POST['document'] ?? $_POST['del_ass_doc'] ?? '');

if ($assignment_id <= 0) {
    back_msg("Invalid assignment selected.");
}

$stmt = $conn->prepare("
    SELECT id, title, document, uploaded_by
    FROM assignments
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    back_msg("Failed to prepare assignment query.");
}

$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$assignment) {
    back_msg("Assignment not found.");
}

if ($role !== "xybrtw" && (string)$assignment['uploaded_by'] !== (string)$user_unique) {
    back_msg("You are not allowed to delete this assignment.");
}


$hasDummy = false;
$colCheck = $conn->query("SHOW COLUMNS FROM assignments LIKE 'dummy'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasDummy = true;
}

if ($hasDummy) {
    $stmt = $conn->prepare("UPDATE assignments SET dummy = 'yes' WHERE id = ? LIMIT 1");
} else {
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? LIMIT 1");
}

if (!$stmt) {
    back_msg("Failed to prepare delete request.");
}

$stmt->bind_param("i", $assignment_id);

if (!$stmt->execute()) {
    $stmt->close();
    back_msg("Failed to delete assignment.");
}

$stmt->close();


$fileName = $assignment['document'] ?: $document;

if ($fileName !== '') {
    $fileName = basename($fileName);
    $filePath = __DIR__ . '/../assignments/agjashdbsjdhkhsdvfh/' . $fileName;

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

back_msg("Assignment deleted successfully.", "successmsg");