<?php
require 'connector.php';



header("Content-Type: application/json; charset=utf-8");

$systemname = $systemname ?? '';
$role    = $_SESSION[$systemname . "letype"] ?? '';
$user_id = (int)($_SESSION[$systemname . "leido"] ?? 0);
$is_admin = ($role === "xybrtw");

if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode(["success"=>false,"message"=>"Not logged in"]);
  exit;
}

$id = (int)($_POST['assignment_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$msg = trim($_POST['short_message'] ?? '');
$date_due = $_POST['date_due'] ?? null;

if ($id <= 0 || $title === '' || $msg === '') {
  http_response_code(400);
  echo json_encode(["success"=>false,"message"=>"Missing required fields"]);
  exit;
}

/* current user's unique_id */
$my_unique = '';
$stmtMe = $conn->prepare("SELECT unique_id FROM users WHERE id=? LIMIT 1");
$stmtMe->bind_param("i", $user_id);
$stmtMe->execute();
$r = $stmtMe->get_result()->fetch_assoc();
$my_unique = (string)($r['unique_id'] ?? '');
$stmtMe->close();

/* load assignment owner */
$stmt = $conn->prepare("SELECT uploaded_by, document FROM assignments WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(["success"=>false,"message"=>"Assignment not found"]);
  exit;
}

$owner = (string)($row['uploaded_by'] ?? '');
if (!$is_admin && $owner !== $my_unique) {
  http_response_code(403);
  echo json_encode(["success"=>false,"message"=>"Not authorized"]);
  exit;
}

/* TODO: handle file upload if provided, else keep old document */
$newDoc = $row['document'];
if (!empty($_FILES['document']) && $_FILES['document']['error'] === 0) {
  // implement your upload logic here, then set $newDoc
}

$stmtU = $conn->prepare("UPDATE assignments SET title=?, short_message=?, date_due=?, document=? WHERE id=? LIMIT 1");
$stmtU->bind_param("ssssi", $title, $msg, $date_due, $newDoc, $id);
$ok = $stmtU->execute();
$stmtU->close();

echo json_encode(["success"=>$ok, "message"=>$ok ? "Updated" : "Failed to update"]);
