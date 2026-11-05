<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'connector.php';
header('Content-Type: application/json; charset=utf-8');

function ok(array $data): void {
    echo json_encode($data);
    exit;
}
function fail(string $m, int $c = 500): void {
    http_response_code($c);
    echo json_encode(['success' => false, 'message' => $m]);
    exit;
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

/* -------- Intakes + Branch -------- */
$intakes = [];
$q1 = $conn->query("
    SELECT i.id AS intake_id, i.name AS intake_name, b.name AS branch_name
    FROM intakes i
    JOIN branches b ON b.id = i.branch_id
    ORDER BY i.name ASC
");
if (!$q1) fail("Failed to load intakes: " . $conn->error);
while ($r = $q1->fetch_assoc()) {
    $intakes[] = [
        'intake_id'   => (int)$r['intake_id'],
        'intake_name' => $r['intake_name'],
        'branch_name' => $r['branch_name'],
    ];
}

/* -------- Courses -------- */
$courses = [];
$q2 = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
if (!$q2) fail("Failed to load courses: " . $conn->error);
while ($r = $q2->fetch_assoc()) {
    $courses[] = [
        'id'   => (int)$r['id'],
        'name' => $r['name'],
    ];
}

/* -------- Units (for selected course) -------- */
$units = [];
if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT id, name FROM course_unit WHERE course_id = ? ORDER BY name ASC");
    if (!$stmt) fail("Prepare units failed: " . $conn->error);

    $stmt->bind_param("i", $course_id);

    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Unknown error';
        $stmt->close();
        fail("Execute units failed: " . $err);
    }

    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $units[] = [
            'id'   => (int)$r['id'],
            'name' => $r['name'],
        ];
    }
    $stmt->close();
}

/* -------- Class Types -------- */
$class_types = [];
$q3 = $conn->query("SELECT id, name FROM class_type WHERE status='Active' ORDER BY name ASC");
if (!$q3) fail("Failed to load class types: " . $conn->error);
while ($r = $q3->fetch_assoc()) {
    $class_types[] = [
        'id'   => (int)$r['id'],
        'name' => $r['name'],
    ];
}

ok([
    'success'     => true,
    'intakes'     => $intakes,
    'courses'     => $courses,
    'units'       => $units,
    'class_types' => $class_types
]);
