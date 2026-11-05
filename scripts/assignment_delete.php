<?php
require 'connector.php';

header("Content-Type: application/json; charset=utf-8");

$systemname = $systemname ?? '';
$role    = $_SESSION[$systemname . "letype"] ?? '';
$user_id = (int)($_SESSION[$systemname . "leido"] ?? 0);
$is_admin = ($role === "xybrtw");

$id = (int)($_POST['assignment_id'] ?? 0);
if ($user_id <= 0 || $id <= 0) {
  http_response_code(400);
  echo json_encode(["success"=>false,"message"=>"Bad request"]);
  exit;
}

$stmtMe = $conn->prepare("SELECT unique_id FROM users WHERE id=? LIMIT 1");
$stmtMe->bind_param("i", $user_id);
$stmtMe->execute();
$my_unique = (string)(($stmtMe->get_result()->fetch_assoc()['unique_id'] ?? ''));
$stmtMe->close();

$stmt = $conn->prepare("SELECT uploaded_by, document FROM assignments WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(["success"=>false,"message"=>"Not found"]);
  exit;
}

if (!$is_admin && (string)$row['uploaded_by'] !== $my_unique) {
  http_response_code(403);
  echo json_encode(["success"=>false,"message"=>"Not authorized"]);
  exit;
}

/* soft delete recommended; if you prefer hard delete do DELETE */
$stmtD = $conn->prepare("UPDATE assignments SET dummy='yes' WHERE id=? LIMIT 1");
$stmtD->bind_param("i", $id);
$ok = $stmtD->execute();
$stmtD->close();

/* optionally remove file from disk here */

echo json_encode(["success"=>$ok, "message"=>$ok ? "Deleted" : "Failed to delete"]);
