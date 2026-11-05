<?php
session_start();
require 'scripts/connector.php';

header('Content-Type: application/json');

$systemname = 'Elevate Her 360';
if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$current_user_id = $_SESSION[$systemname . "leido"];

if(!isset($_POST['submission_id'])){
    echo json_encode(['success'=>false,'message'=>'Missing submission ID']);
    exit;
}

$submission_id = (int)$_POST['submission_id'];

// Verify submission belongs to logged-in user's assignment
$stmt = $conn->prepare("
    SELECT hi.id FROM handed_in hi
    JOIN assignments a ON hi.assignment_id = a.id
    WHERE hi.id=? AND a.uploaded_by=?
");
$stmt->bind_param("ii", $submission_id, $current_user_id);
$stmt->execute();
if($stmt->get_result()->num_rows === 0){
    echo json_encode(['success'=>false,'message'=>'Submission not found']);
    exit;
}

// Delete submission
$del = $conn->prepare("DELETE FROM handed_in WHERE id=?");
$del->bind_param("i", $submission_id);
if($del->execute()){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to delete submission']);
}
?>