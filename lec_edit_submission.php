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

if(!isset($_POST['submission_id'], $_POST['finalmarks'], $_POST['marks_deducted'], $_POST['feedback'])){
    echo json_encode(['success'=>false,'message'=>'Missing data']);
    exit;
}

$submission_id = (int)$_POST['submission_id'];
$finalmarks = (float)$_POST['finalmarks'];
$marks_deducted = (float)$_POST['marks_deducted'];
$feedback = trim($_POST['feedback']);

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

// Update submission
$update = $conn->prepare("UPDATE handed_in SET finalmarks=?, marks_deducted=?, feedback=? WHERE id=?");
$update->bind_param("ddsi", $finalmarks, $marks_deducted, $feedback, $submission_id);
if($update->execute()){
    echo json_encode([
        'success'=>true,
        'submission_id'=>$submission_id,
        'finalmarks'=>$finalmarks,
        'marks_deducted'=>$marks_deducted,
        'feedback'=>$feedback
    ]);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to update']);
}
?>