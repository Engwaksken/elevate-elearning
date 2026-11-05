<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'connector.php';

$intake_id     = isset($_GET['intake_id']) ? (int)$_GET['intake_id'] : 0;
$course_id     = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$unit_id       = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
$class_type_id = isset($_GET['class_type_id']) ? (int)$_GET['class_type_id'] : 0;

if ($intake_id <= 0) {
    header("Content-Type: text/plain; charset=utf-8");
    echo "Please select an intake first.";
    exit;
}

$sql = "
    SELECT
        CONCAT(u.fname,' ',u.lname) AS student,
        COALESCE(ct.name,'N/A') AS class_type,
        c.name AS course,
        cu.name AS unit,
        a.title AS assignment,
        a.date_due AS due_date,
        hi.date_handed_in AS submitted_at,
        COALESCE(hi.finalmarks, '') AS marks,
        a.marks AS out_of,
        COALESCE(hi.marks_deducted, '') AS deducted,
        COALESCE(hi.feedback, '') AS feedback
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

if ($course_id > 0) { $sql .= " AND cu.course_id = ?"; $params[] = $course_id; $types .= "i"; }
if ($unit_id > 0)   { $sql .= " AND cu.id = ?";        $params[] = $unit_id;   $types .= "i"; }
if ($class_type_id > 0) { $sql .= " AND u.class_type_id = ?"; $params[] = $class_type_id; $types .= "i"; }

$sql .= " ORDER BY a.date_due DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$filename = "assignments_export_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Student','Class Type','Course','Unit','Assignment','Due Date','Submitted','Marks','Out Of','Deducted','Feedback']);

while ($r = $res->fetch_assoc()) {
    fputcsv($out, [
        $r['student'], $r['class_type'], $r['course'], $r['unit'], $r['assignment'],
        $r['due_date'], $r['submitted_at'], $r['marks'], $r['out_of'], $r['deducted'], $r['feedback']
    ]);
}

fclose($out);
$stmt->close();
exit;
