<?php
session_start();

require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';

$systemname = $systemname ?? "Elevate Her 360";
$nowdate    = $nowdate ?? date('Y-m-d H:i:s');

function goBack(string $uid, string $msg, string $type = 'errormsg'): never {
    global $systemname;
    $_SESSION[$systemname . $type] = $msg;
    header("Location: ../index.php?view=change_course&id=" . urlencode($uid));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['changecourse'])) {
    goBack($_POST['pjk'] ?? '', "Invalid request.");
}

$uid    = trim((string)($_POST['pjk'] ?? ''));
$course = trim((string)($_POST['course'] ?? ''));
$intake = trim((string)($_POST['intake'] ?? ''));
$addedBy = trim((string)($_SESSION[$systemname . "leido"] ?? ''));

if ($uid === '') {
    goBack($uid, "Invalid student.");
}

/*
|--------------------------------------------------------------------------
| If course is empty, remove active course registrations
|--------------------------------------------------------------------------
*/
if ($course === '') {
    $stmt = $conn->prepare("
        DELETE FROM course_registration 
        WHERE user_id = ? 
          AND status != 'done'
    ");

    if (!$stmt) {
        goBack($uid, "Failed to prepare delete query.");
    }

    $stmt->bind_param("s", $uid);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        goBack($uid, "Course registration removed.", "successmsg");
    }

    goBack($uid, "Process failed.");
}

/*
|--------------------------------------------------------------------------
| Fetch selected course
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id, course_name, category 
    FROM courses 
    WHERE id = ? 
    LIMIT 1
");

if (!$stmt) {
    goBack($uid, "Failed to prepare course query.");
}

$stmt->bind_param("s", $course);
$stmt->execute();
$courseRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$courseRow) {
    goBack($uid, "Selected course was not found.");
}

$cat = (string)($courseRow['category'] ?? '');

/*
|--------------------------------------------------------------------------
| Check duplicate active registration
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id 
    FROM course_registration 
    WHERE user_id = ? 
      AND course_id = ? 
      AND status != 'done'
    LIMIT 1
");

if (!$stmt) {
    goBack($uid, "Failed to prepare duplicate check.");
}

$stmt->bind_param("ss", $uid, $course);
$stmt->execute();
$duplicate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($duplicate) {
    goBack($uid, "Course is already registered.");
}

/*
|--------------------------------------------------------------------------
| Prevent another active course in category 2
|--------------------------------------------------------------------------
*/
if ($cat === "2") {
    $stmt = $conn->prepare("
        SELECT cr.course_id, c.category
        FROM course_registration cr
        INNER JOIN courses c ON c.id = cr.course_id
        WHERE cr.user_id = ?
          AND cr.status != 'done'
          AND c.category = '2'
        LIMIT 1
    ");

    if (!$stmt) {
        goBack($uid, "Failed to prepare category check.");
    }

    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $existingCategory = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingCategory) {
        goBack($uid, "Can't add another course in this category.");
    }
}

/*
|--------------------------------------------------------------------------
| Assign course
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    INSERT INTO course_registration
        (course_id, user_id, date_added, added_by, intake)
    VALUES
        (?, ?, ?, ?, ?)
");

if (!$stmt) {
    goBack($uid, "Failed to prepare registration query.");
}

$stmt->bind_param(
    "sssss",
    $course,
    $uid,
    $nowdate,
    $addedBy,
    $intake
);

$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    goBack($uid, "Process failed.");
}

/*
|--------------------------------------------------------------------------
| Fetch student and intake details for email
|--------------------------------------------------------------------------
*/
$emailMsg = "Process Finalised.";

try {
    $stmt = $conn->prepare("
        SELECT 
            u.email,
            u.fname,
            u.lname,
            i.intake_name
        FROM users u
        LEFT JOIN intakes i ON i.id = ?
        WHERE u.unique_id = ? OR u.id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $uidAsInt = is_numeric($uid) ? (int)$uid : 0;

        $stmt->bind_param("isi", $intake, $uid, $uidAsInt);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($student && !empty($student['email'])) {
            $studentName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
            $courseName  = (string)$courseRow['course_name'];
            $intakeName  = (string)($student['intake_name'] ?? $intake);

            $emailService = new EmailService();

            $sent = $emailService->notifyCourseAssigned(
                $student['email'],
                $studentName,
                $courseName,
                $intakeName,
                (int)$course
            );

            $emailMsg .= $sent
                ? " Student email notification sent."
                : " Student email notification was not sent.";
        } else {
            $emailMsg .= " Student email missing, notification not sent.";
        }
    }
} catch (Throwable $e) {
    error_log("Course assigned email failed: " . $e->getMessage());
    $emailMsg .= " Email notification failed.";
}

goBack($uid, $emailMsg, "successmsg");
?>