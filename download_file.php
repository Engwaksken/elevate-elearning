<?php
// download_file.php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$systemname = 'Elevate Her 360';

/* -------------------- AUTH -------------------- */
if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    http_response_code(401);
    echo "Please log in.";
    exit;
}

$current_user_id = $_SESSION[$systemname . "leido"] ?? '';
if ($current_user_id === '') {
    http_response_code(401);
    echo "Session error. Please log in again.";
    exit;
}

/* -------------------- INPUT -------------------- */
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
if ($submission_id <= 0) {
    http_response_code(400);
    echo "Invalid submission ID.";
    exit;
}

/* -------------------- PERMISSION CHECK --------------------
   Default: student can only download their own file.
   If you have roles (admin/instructor), you can allow them too.
------------------------------------------------------------ */
$userRole = $_SESSION[$systemname . "role"] ?? ''; // adjust if your session key differs
$isPrivileged = in_array($userRole, ['admin', 'instructor'], true); // adjust roles if needed

/* -------------------- FETCH FILE PATH -------------------- */
if ($isPrivileged) {
    // admin/instructor can download any submission file
    $stmt = $conn->prepare("
        SELECT file_uploaded
        FROM handed_in
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) { http_response_code(500); echo "Prepare failed."; exit; }
    $stmt->bind_param("i", $submission_id);
} else {
    // student: only own
    $stmt = $conn->prepare("
        SELECT file_uploaded
        FROM handed_in
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    if (!$stmt) { http_response_code(500); echo "Prepare failed."; exit; }
    $stmt->bind_param("is", $submission_id, $current_user_id);
}

$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['file_uploaded'])) {
    http_response_code(404);
    echo "File not found or not allowed.";
    exit;
}

$storedPath = trim((string)$row['file_uploaded']);

$UPLOAD_DIR = __DIR__ . '/assignments/ahwyefgywefpiwegyfp/';  

$filename = basename($storedPath);                
$fullPath = $UPLOAD_DIR . $filename;

$realBase = realpath($UPLOAD_DIR);
$realFile = realpath($fullPath);

if (!$realBase) {
    http_response_code(500);
    echo "Upload directory not found.";
    exit;
}

if (!$realFile || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
    http_response_code(404);
    echo "File does not exist.";
    exit;
}

/* -------------------- SEND FILE -------------------- */
$mime = function_exists('mime_content_type') ? (mime_content_type($realFile) ?: 'application/octet-stream') : 'application/octet-stream';
$size = filesize($realFile);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (ob_get_length()) ob_end_clean();
readfile($realFile);
exit;
