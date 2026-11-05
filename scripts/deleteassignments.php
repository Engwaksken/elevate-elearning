<?php
session_start();
require 'connector.php';

// Check if required parameters exist
if (!isset($_GET['find']) || !isset($_GET['zambezia']) || !isset($_GET['bengani'])) {
    header("Location: ../");
    exit();
}

// Get and sanitize input parameters
$assignmentId = intval($_GET['find']);
$documentName = $_GET['zambezia'];
$courseId = $_GET['bengani'];
$targetDir = "../assignments/agjashdbsjdhkhsdvfh/";

// Check session variables
if (!isset($_SESSION[$systemname."letype"]) || !isset($_SESSION[$systemname."leido"])) {
    header("Location: ../");
    exit();
}

$accType = $_SESSION[$systemname."letype"];
$uid = strip_tags(trim($_SESSION[$systemname."leido"]));

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM `assignments` WHERE `id` = ?");
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows < 1) {
    $_SESSION[$systemname."errormsg"] = "Invalid Request";
    header("Location: ../?view=home");
    exit();
}

$assignment = $result->fetch_assoc();
$intake = $assignment["intake"];

// Determine redirect link based on account type
if ($accType == "xybrtw") {
    // Admin
    $redirectLink = "../index.php?view=addmaterial&course=" . urlencode($courseId);
} else if ($accType == "xtfbry") {
    // Lecturer - FIXED: Changed $ourse to $courseId
    $redirectLink = "../index.php?view=open_coursex&course=" . urlencode($courseId) . "&ausydfgasuidgipppuwqu=" . urlencode($intake);
} else {
    // Unknown account type
    $_SESSION[$systemname."errormsg"] = "Invalid account type";
    header("Location: ../");
    exit();
}

// Sanitize document name to prevent directory traversal
$safeDocumentName = basename($documentName);
$filePath = $targetDir . $safeDocumentName;

// Verify file exists and is within the target directory
if (!file_exists($filePath) || realpath($filePath) !== realpath($targetDir) . DIRECTORY_SEPARATOR . $safeDocumentName) {
    $_SESSION[$systemname."errormsg"] = "File not found or invalid path";
    header("Location: " . $redirectLink);
    exit();
}

// Attempt to delete the file
if (unlink($filePath)) {
    // Delete database record using prepared statement
    $deleteStmt = $conn->prepare("DELETE FROM `assignments` WHERE `id` = ?");
    $deleteStmt->bind_param("i", $assignmentId);
    
    if ($deleteStmt->execute()) {
        $_SESSION[$systemname."successmsg"] = "Assignment removed";
    } else {
        $_SESSION[$systemname."errormsg"] = "Database deletion failed";
    }
    $deleteStmt->close();
} else {
    $_SESSION[$systemname."errormsg"] = "Assignment delete failed";
}

$stmt->close();
header("Location: " . $redirectLink);
exit();
?>