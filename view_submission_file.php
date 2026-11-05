<?php
require 'scripts/connector.php';
session_start();
$systemname = 'Elevate Her 360';

if (empty($_SESSION[$systemname."loggedin"])) { http_response_code(403); exit('Login required'); }
$current_user_id = trim((string)($_SESSION[$systemname . "leido"] ?? ''));

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Invalid'); }

$sql = "
SELECT hi.file_uploaded
FROM handed_in hi
JOIN assignments a ON hi.assignment_id = a.id
WHERE hi.id = ? AND a.uploaded_by = ?
LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id, $current_user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r || empty($r['file_path'])) { http_response_code(404); exit('File not found'); }

$path = $r['file_path'];

$fullPath = __DIR__ . "assignments/agjashdbsjdhkhsdvfh/" . ltrim($path, "/");
$fullPath = $path;

if (!is_file($fullPath)) { http_response_code(404); exit('Missing file'); }

header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="'.basename($fullPath).'"');
readfile($fullPath);
exit;