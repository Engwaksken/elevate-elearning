<?php
session_start();
require 'connector.php';

header('Content-Type: application/json');

$systemname = $systemname ?? ''; 

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate session
if (!isset($_SESSION[$systemname . "leido"], $_SESSION[$systemname . "letype"])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_type = (string)$_SESSION[$systemname . "letype"];
if ($user_type !== "xtfbry" && $user_type !== "xybrtw") {
    echo json_encode(['success' => false, 'message' => 'Access denied. Instructors only.']);
    exit;
}

// Validate input
$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
if ($note_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid note_id parameter']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

/* ============================================================================
   NOTE INFO (course_id + intake)
   ============================================================================ */
$noteInfoSql = "
    SELECT cun.id, cun.intake, cu.course_id
    FROM course_unit_notes cun
    JOIN course_unit cu ON cun.course_unit = cu.id
    WHERE cun.id = ?
    LIMIT 1
";
$stmt = $conn->prepare($noteInfoSql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $note_id);
$stmt->execute();
$noteInfoRes = $stmt->get_result();

if (!$noteInfoRes || $noteInfoRes->num_rows < 1) {
    echo json_encode(['success' => false, 'message' => 'Note not found']);
    exit;
}

$noteInfo  = $noteInfoRes->fetch_assoc();
$course_id = (int)$noteInfo['course_id'];
$intake_id = (int)$noteInfo['intake'];
$stmt->close();

/* ============================================================================
   ALL REGISTERED STUDENTS FOR COURSE + INTAKE
   ============================================================================ */
$studentsSql = "
    SELECT DISTINCT u.unique_id, u.fname, u.lname
    FROM users u
    INNER JOIN course_registration cr ON u.unique_id = cr.user_id
    WHERE cr.course_id = ?
      AND cr.intake = ?
    ORDER BY u.fname, u.lname
";
$stmt = $conn->prepare($studentsSql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ii", $course_id, $intake_id);
$stmt->execute();
$studentsRes = $stmt->get_result();

if (!$studentsRes) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$all_students = [];
while ($row = $studentsRes->fetch_assoc()) {
    $uid = (string)$row['unique_id'];
    $all_students[$uid] = [
        'user_id'            => $uid,
        'student_name'       => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
        'reading_percentage' => 0.0,
        'last_accessed'      => null
    ];
}
$stmt->close();

/* ============================================================================
   READING PROGRESS
   ============================================================================ */
$progressSql = "
    SELECT user_id, reading_percentage, last_accessed
    FROM note_reading_progress
    WHERE note_id = ?
";
$stmt = $conn->prepare($progressSql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $note_id);
$stmt->execute();
$progressRes = $stmt->get_result();

if (!$progressRes) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

while ($p = $progressRes->fetch_assoc()) {
    $uid = (string)$p['user_id'];
    if (!isset($all_students[$uid])) continue;

    $perc = is_numeric($p['reading_percentage']) ? (float)$p['reading_percentage'] : 0.0;
    $all_students[$uid]['reading_percentage'] = round($perc, 1);

    $last = $p['last_accessed'];
    if (!empty($last) && $last !== '0000-00-00 00:00:00') {
        $ts = strtotime($last);
        $all_students[$uid]['last_accessed'] = $ts ? date('d/m/Y h:i A', $ts) : null;
    }
}
$stmt->close();

/* ============================================================================
   STATISTICS
   ============================================================================ */
$total_students    = count($all_students);
$total_progress    = 0.0;
$completed_count   = 0;
$not_started_count = 0;
$in_progress_count = 0;

foreach ($all_students as $s) {
    $percentage = (float)$s['reading_percentage'];
    $total_progress += $percentage;

    if ($percentage >= 100) $completed_count++;
    elseif ($percentage > 0) $in_progress_count++;
    else $not_started_count++;
}

$avg_progress = $total_students > 0 ? round($total_progress / $total_students, 1) : 0.0;

// Convert to indexed
$student_progress = array_values($all_students);

// Sort desc by progress
usort($student_progress, function($a, $b){
    $pa = (float)$a['reading_percentage'];
    $pb = (float)$b['reading_percentage'];
    if ($pa === $pb) return 0;
    return ($pa < $pb) ? 1 : -1;
});

/* ============================================================================
   RESPONSE
   ============================================================================ */
echo json_encode([
    'success' => true,
    'data' => [
        'statistics' => [
            'total_students'    => $total_students,
            'avg_progress'      => $avg_progress,
            'completed_count'   => $completed_count,
            'in_progress_count' => $in_progress_count,
            'not_started_count' => $not_started_count
        ],
        'student_progress' => $student_progress
    ]
]);

$conn->close();
