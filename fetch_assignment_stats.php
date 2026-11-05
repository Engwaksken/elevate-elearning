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
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

$sql = "
    SELECT 
        COUNT(*) AS total_submissions,
        SUM(CASE WHEN hi.finalmarks IS NOT NULL AND hi.finalmarks>0 THEN 1 ELSE 0 END) AS graded_submissions,
        SUM(CASE WHEN hi.finalmarks IS NULL OR hi.finalmarks<=0 THEN 1 ELSE 0 END) AS ungraded_submissions
    FROM handed_in hi
    JOIN assignments a ON a.id = hi.assignment_id
    JOIN course_unit cu ON cu.id = a.course_unit
    WHERE a.uploaded_by=? AND cu.course_id=?
";
$params = [$current_user_id, $course_id];
$types = "ii";

if($unit_id>0){
    $sql .= " AND cu.id=?";
    $types .= "i";
    $params[] = $unit_id;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['success'=>true, 'total_submissions'=>$result['total_submissions'],'graded_submissions'=>$result['graded_submissions'],'ungraded_submissions'=>$result['ungraded_submissions']]);
?>