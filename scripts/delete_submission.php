<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../scripts/connector.php';

$systemname = 'Elevate Her 360';


if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    $_SESSION['error_msg'] = "Please log in.";
    header("Location: ../index.php");
    exit;
}

$lecturer_id = trim((string)($_SESSION[$systemname . "leido"] ?? ''));
if ($lecturer_id === '') {
    $_SESSION['error_msg'] = "Invalid session.";
    header("Location: ../index.php");
    exit;
}


$submission_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$mymodule      = isset($_POST['mymodule']) ? (int)$_POST['mymodule'] : 0;

if ($submission_id <= 0) {
    $_SESSION['error_msg'] = "Invalid submission.";
    header("Location: ../index.php");
    exit;
}


$chk = $conn->prepare("
    SELECT hi.id
    FROM handed_in hi
    INNER JOIN assignments a ON a.id = hi.assignment_id
    WHERE hi.id = ? AND a.uploaded_by = ?
    LIMIT 1
");
$chk->bind_param("is", $submission_id, $lecturer_id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    $_SESSION['error_msg'] = "Submission not found or not allowed.";
    header("Location: ../index.php");
    exit;
}


$del = $conn->prepare("DELETE FROM handed_in WHERE id = ? LIMIT 1");
$del->bind_param("i", $submission_id);
$del->execute();
$del->close();


$_SESSION['success_msg'] = "Submission deleted successfully.";


header("Location: ../index.php?view=subby&hjadvhagsdjhsagsdhg=$mymodule&piouglv=$assignment_id");
exit;