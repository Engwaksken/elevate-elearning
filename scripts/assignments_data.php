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

$intake_id     = isset($_GET['intake_id']) ? (int)$_GET['intake_id'] : 0;
$course_id     = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$unit_id       = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
$class_type_id = isset($_GET['class_type_id']) ? (int)$_GET['class_type_id'] : 0;

/* If no intake selected, return empty set + class types for dropdown */
$classTypes = [];
$ctRes = $conn->query("SELECT id, name FROM class_type WHERE status='Active' ORDER BY name ASC");
if ($ctRes) {
    while ($r = $ctRes->fetch_assoc()) {
        $classTypes[] = [
            'id' => (int)$r['id'],
            'name' => $r['name']
        ];
    }
}

if ($intake_id <= 0) {
    json_ok(['success' => true, 'rows' => [], 'class_types' => $classTypes]);
}

/* Build query */
$sql = "
    SELECT
        hi.id AS submission_id,
        hi.assignment_id,
        hi.file_uploaded,
        hi.date_handed_in,
        hi.marks_deducted,
        hi.finalmarks,
        hi.feedback AS feedback_comment,

        a.title AS assignment_title,
        a.course_unit,
        a.date_due,
        a.marks AS total_marks,
        a.intake,

        cu.name AS unit_name,
        c.name AS course_name,

        CONCAT(u.fname,' ',u.lname) AS student_name,
        u.unique_id AS student_id,
        u.class_type_id,
        ct.name AS class_type_name
    FROM handed_in hi
    JOIN assignments a ON hi.assignment_id = a.id
    JOIN course_unit cu ON a.course_unit = cu.id
    JOIN courses c ON cu.course_id = c.id
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
if ($class_type_id > 0) {
    $sql .= " AND u.class_type_id = ?";
    $params[] = $class_type_id;
    $types .= "i";
}

$sql .= " ORDER BY a.date_due DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) json_fail("Prepare failed: " . $conn->error, 500);

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    json_fail("Execute failed: " . $stmt->error, 500);
}

$res = $stmt->get_result();
$rows = [];

while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'submission_id'     => (int)$row['submission_id'],
        'assignment_id'     => (int)$row['assignment_id'],
        'file_uploaded'     => $row['file_uploaded'],
        'date_handed_in'    => $row['date_handed_in'],
        'marks_deducted'    => $row['marks_deducted'],
        'finalmarks'        => $row['finalmarks'],
        'feedback_comment'  => $row['feedback_comment'],

        'assignment_title'  => $row['assignment_title'],
        'course_unit'       => (int)$row['course_unit'],
        'date_due'          => $row['date_due'],
        'total_marks'       => $row['total_marks'],
        'intake'            => (int)$row['intake'],

        'unit_name'         => $row['unit_name'],
        'course_name'       => $row['course_name'],

        'student_name'      => $row['student_name'],
        'student_id'        => $row['student_id'],
        'class_type_id'     => (int)($row['class_type_id'] ?? 0),
        'class_type_name'   => $row['class_type_name'] ?? 'N/A',
    ];
}

$stmt->close();

json_ok([
    'success' => true,
    'rows' => $rows,
    'class_types' => $classTypes
]);
