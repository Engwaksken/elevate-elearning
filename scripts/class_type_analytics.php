<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'connector.php';
header('Content-Type: application/json; charset=utf-8');

function json_ok(array $payload): void {
    echo json_encode($payload);
    exit;
}
function json_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$intake_id = isset($_GET['intake_id']) ? (int)$_GET['intake_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$unit_id   = isset($_GET['unit_id'])   ? (int)$_GET['unit_id']   : 0;

if ($intake_id <= 0) {
    json_ok(['success' => true, 'data' => []]);
}


$sql = "
    SELECT
        COALESCE(ct.name, 'N/A') AS class_type,
        COUNT(*) AS total,
        SUM(CASE WHEN hi.finalmarks IS NOT NULL THEN 1 ELSE 0 END) AS graded,
        SUM(CASE WHEN hi.finalmarks IS NULL THEN 1 ELSE 0 END) AS pending
    FROM handed_in hi
    JOIN assignments a ON hi.assignment_id = a.id
    JOIN course_unit cu ON a.course_unit = cu.id
    JOIN users u ON hi.user_id = u.unique_id
    LEFT JOIN class_type ct ON ct.id = u.class_type_id
    WHERE a.intake = ?
";

$params = [$intake_id];
$types  = "i";

if ($course_id > 0) {
    $sql .= " AND cu.course_id = ?";
    $params[] = $course_id;
    $types .= "i";
}

if ($unit_id > 0) {
    $sql .= " AND cu.id = ?";
    $params[] = $unit_id;
    $types .= "i";
}

$sql .= " GROUP BY COALESCE(ct.name, 'N/A') ORDER BY total DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_fail("Prepare failed: " . $conn->error, 500);
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    json_fail("Execute failed: " . $stmt->error, 500);
}

$res = $stmt->get_result();
$data = [];
while ($r = $res->fetch_assoc()) {
    // force ints for frontend
    $data[] = [
        'class_type' => $r['class_type'],
        'total'      => (int)$r['total'],
        'graded'     => (int)$r['graded'],
        'pending'    => (int)$r['pending'],
    ];
}

$stmt->close();

json_ok(['success' => true, 'data' => $data]);
