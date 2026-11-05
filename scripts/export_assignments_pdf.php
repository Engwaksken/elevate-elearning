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

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    header("Content-Type: text/plain; charset=utf-8");
    echo "PDF export requires Dompdf. Run: composer require dompdf/dompdf";
    exit;
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

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
        COALESCE(hi.marks_deducted, '') AS deducted
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

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

/* Simple HTML */
$html = '<html><head><meta charset="utf-8">
<style>
body{font-family:DejaVu Sans, sans-serif;font-size:12px;}
h2{margin:0 0 10px 0;}
table{width:100%;border-collapse:collapse;}
th,td{border:1px solid #ccc;padding:6px;vertical-align:top;}
th{background:#f2f2f2;}
</style></head><body>';

$html .= '<h2>Assignments Export</h2>';
$html .= '<p>Generated: '.date('Y-m-d H:i').'</p>';

$html .= '<table><thead><tr>
<th>#</th><th>Student</th><th>Class Type</th><th>Course</th><th>Unit</th><th>Assignment</th>
<th>Due Date</th><th>Submitted</th><th>Marks</th><th>Out Of</th><th>Deducted</th>
</tr></thead><tbody>';

$i = 1;
foreach ($rows as $r) {
    $html .= '<tr>
        <td>'.$i++.'</td>
        <td>'.htmlspecialchars($r['student']).'</td>
        <td>'.htmlspecialchars($r['class_type']).'</td>
        <td>'.htmlspecialchars($r['course']).'</td>
        <td>'.htmlspecialchars($r['unit']).'</td>
        <td>'.htmlspecialchars($r['assignment']).'</td>
        <td>'.htmlspecialchars($r['due_date']).'</td>
        <td>'.htmlspecialchars($r['submitted_at'] ?? '-').'</td>
        <td>'.htmlspecialchars($r['marks'] === '' ? 'Pending' : $r['marks']).'</td>
        <td>'.htmlspecialchars($r['out_of']).'</td>
        <td>'.htmlspecialchars($r['deducted'] === '' ? '-' : $r['deducted']).'</td>
    </tr>';
}

$html .= '</tbody></table></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = "assignments_export_" . date('Ymd_His') . ".pdf";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');

echo $dompdf->output();
exit;
