<?php
session_start();
require 'connector.php'; 

// ---------- helpers ----------
function redirect_with_msg($url, $key, $msg){
    header("Location: {$url}");
    exit;
}

// ---------- basic guards ----------
$systemname = isset($systemname) ? $systemname : ''; 

if (!isset($_GET['find'], $_GET['zambezia'], $_GET['bengani'])) {
    header("Location: ../index.php");
    exit;
}

$notes    = (int) $_GET['find'];
$document = basename((string)$_GET['zambezia']); 
$course   = trim((string)$_GET['bengani']);

$target_dir = "../notes/";


$acc_type = $_SESSION[$systemname . "letype"] ?? '';
$uid      = trim(strip_tags($_SESSION[$systemname . "leido"] ?? ''));

// Validate required values
if ($notes <= 0 || $document === '' || $course === '') {
    $_SESSION[$systemname . "errormsg"] = "Invalid Request";
    header("Location: ../index.php?view=home");
    exit;
}

// Ensure DB connection exists
if (!isset($conn) || !($conn instanceof mysqli)) {
    $_SESSION[$systemname . "errormsg"] = "Database connection not available.";
    header("Location: ../index.php?view=home");
    exit;
}

// ---------- fetch note record ----------
$stmt = $conn->prepare("SELECT intake FROM course_unit_notes WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $notes);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows < 1) {
    $_SESSION[$systemname . "errormsg"] = "Invalid Request";
    header("Location: ../index.php?view=home");
    exit;
}

$row    = $res->fetch_assoc();
$intake = $row['intake'] ?? '';

// ---------- build redirect link safely ----------
$link = "../index.php?view=home";
if ($acc_type === "xybrtw") {
    // admin
    $link = "../index.php?view=addmaterial&course=" . urlencode($course);
} elseif ($acc_type === "xtfbry") {
    // lecturer
    $link = "../index.php?view=open_coursex&course=" . urlencode($course)
          . "&ausydfgasuidgipppuwqu=" . urlencode($intake);
}

// ---------- delete file (if it exists) ----------
$file_path = $target_dir . $document;

if (file_exists($file_path) && is_file($file_path)) {
    if (!@unlink($file_path)) {
        $_SESSION[$systemname . "errormsg"] = "File delete failed";
        header("Location: {$link}");
        exit;
    }
}

// ---------- delete DB record ----------
$del = $conn->prepare("DELETE FROM course_unit_notes WHERE id = ? LIMIT 1");
$del->bind_param("i", $notes);

if ($del->execute()) {
    $_SESSION[$systemname . "successmsg"] = "File removed";
} else {
    $_SESSION[$systemname . "errormsg"] = "Failed to delete record.";
}

header("Location: {$link}");
exit;
