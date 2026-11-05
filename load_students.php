<?php
// load_students.php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$systemname     = $systemname ?? "system";
$loggedInUserId = $_SESSION[$systemname . "leido"] ?? 0;
$unik           = $loggedInUserId;

// -- INPUT ---------------------------------------------------------------------
$q             = trim($_POST['q']               ?? '');
$course_id     = intval($_POST['course_id']     ?? 0);
$intake_id     = intval($_POST['intake_id']     ?? 0);
$class_type_id = intval($_POST['class_type_id'] ?? 0);
$status        = $_POST['status']               ?? '';
$sort          = $_POST['sort']                 ?? 'newest';
$alpha         = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_POST['alpha'] ?? ''), 0, 1));
$page          = max(1, intval($_POST['page']   ?? 1));
$per_page      = in_array(intval($_POST['per_page'] ?? 25), [10, 25, 50, 100])
                    ? intval($_POST['per_page']) : 25;
$export        = $_POST['export'] ?? '';

// -- WHERE BUILDER -------------------------------------------------------------
$where  = ["u.acc_type = 'afgezt'"];
$params = [];
$types  = '';

if ($q !== '') {
    $like     = '%' . $q . '%';
    $where[]  = "(CONCAT(u.fname,' ',u.lname) LIKE ? OR u.email LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($alpha !== '') {
    $where[]  = "u.fname LIKE ?";
    $params[] = $alpha . '%';
    $types   .= 's';
}

if ($course_id > 0) {
    $where[]  = "cr.course_id = ?";
    $params[] = $course_id;
    $types   .= 'i';
}

if ($intake_id > 0) {
    $where[]  = "cr.intake = ?";
    $params[] = $intake_id;
    $types   .= 'i';
}

if ($class_type_id > 0) {
    $where[]  = "u.class_type_id = ?";
    $params[] = $class_type_id;
    $types   .= 'i';
}

if ($status === 'yes' || $status === 'no') {
    $where[]  = "u.verified = ?";
    $params[] = $status;
    $types   .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// -- ORDER ---------------------------------------------------------------------
$orderSQL = match ($sort) {
    'oldest' => 'ORDER BY u.date_created ASC',
    'alpha'  => 'ORDER BY u.fname ASC, u.lname ASC',
    default  => 'ORDER BY u.date_created DESC',
};

// -- BASE FROM + JOINS ---------------------------------------------------------
$baseSQL = "
    FROM users u
    LEFT JOIN course_registration cr ON cr.user_id = u.unique_id
    LEFT JOIN courses c              ON c.id        = cr.course_id
    LEFT JOIN intakes i              ON i.id        = cr.intake
    LEFT JOIN class_type ct          ON ct.id       = u.class_type_id
    $whereSQL
";

// -- HELPER: prepare + bind + execute -----------------------------------------
function runQuery(mysqli $conn, string $sql, string $types, array $params): mysqli_result|false {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// -- EXPORT: skip pagination, stream file -------------------------------------
if ($export === 'csv' || $export === 'pdf') {
    $exportSQL = "
        SELECT DISTINCT
            u.unique_id, u.fname, u.lname, u.email, u.contact,
            u.country, u.verified, u.date_created,
            c.name  AS course_name,
            i.name  AS intake_name, i.date_launching,
            ct.name AS class_type_name
        $baseSQL $orderSQL
    ";
    $result = runQuery($conn, $exportSQL, $types, $params);
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    ($export === 'csv') ? exportCSV($rows) : exportPDF($rows);
    exit;
}

// -- COUNT ---------------------------------------------------------------------
$countResult = runQuery($conn, "SELECT COUNT(DISTINCT u.unique_id) AS total $baseSQL", $types, $params);
$total       = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;

// -- PAGINATED SELECT ----------------------------------------------------------
$offset      = ($page - 1) * $per_page;
$pagedParams = array_merge($params, [$per_page, $offset]);
$pagedTypes  = $types . 'ii';

$selectSQL = "
    SELECT DISTINCT
        u.unique_id, u.fname, u.lname, u.email,
        u.dp, u.verified, u.date_created,
        c.name  AS course_name,
        i.name  AS intake_name, i.date_launching,
        ct.name AS class_type_name
    $baseSQL $orderSQL
    LIMIT ? OFFSET ?
";

$result   = runQuery($conn, $selectSQL, $pagedTypes, $pagedParams);
$students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// -- RENDER ROWS ---------------------------------------------------------------
ob_start();

if (empty($students)) {
    echo '<tr><td colspan="8"><div class="alert alert-warning text-center"><strong>No students found.</strong></div></td></tr>';
} else {
    $rowNum = ($page - 1) * $per_page + 1;

    foreach ($students as $d) {
        $stdId = htmlspecialchars($d['unique_id']);
        $fname = htmlspecialchars(trim($d['fname'] . ' ' . $d['lname']));
        $email = htmlspecialchars($d['email'] ?? '');

        // Avatar
        $dp    = $d['dp'] ?? 'default.jpg';
        $dpImg = ($dp === 'default.jpg')
            ? '<img alt="image" class="img-circle profile-top" src="images/default_p_pics/user.png" style="width:34px;height:34px;">'
            : '<img alt="image" class="img-circle profile-top" src="images/user_p_pics/' . htmlspecialchars($dp) . '" style="width:34px;height:34px;">';

        // Course + Intake label
        $courseText = 'N/A';
        if (!empty($d['course_name'])) {
            $year       = !empty($d['date_launching']) ? date('Y', strtotime($d['date_launching'])) : '';
            $intakePart = !empty($d['intake_name']) ? ' (' . htmlspecialchars($d['intake_name']) . ' ' . $year . ')' : '';
            $courseText = htmlspecialchars($d['course_name']) . $intakePart;
        }

        // Class type
        $classType = htmlspecialchars($d['class_type_name'] ?? 'N/A');

        // Status toggle
        $isActive    = ($d['verified'] === 'yes');
        $statusLabel = $isActive
            ? '<a href="deactivate?id=' . $stdId . '&xts=view_students"><span class="label label-primary">Active</span></a>'
            : '<a href="activate?id='   . $stdId . '&xts=view_students"><span class="label label-info">Not Active</span></a>';

        // Action links
        $editLink   = '<a href="?view=edit_student&id='  . $stdId . '"><span class="label label-warning">Edit</span></a>';
        $assignLink = '<a href="?view=change_course&id=' . $stdId . '"><span class="label label-default">Assign</span></a>';
        $chat       = ($stdId != $unik)
            ? '<a href="?view=chatwith&pr=' . $stdId . '"><span class="label label-success">Chat</span></a>'
            : '';

        echo "
        <tr>
          <td>{$rowNum}</td>
          <td class='client-avatar'>{$dpImg}</td>
          <td><a class='client-link' onclick=\"openuser('{$stdId}')\" style='cursor:pointer;'>{$fname}</a></td>
          <td>{$courseText}</td>
          <td>{$classType}</td>
          <td class='contact-type'><i class='fa fa-envelope'></i> {$email}</td>
          <td class='client-status'>{$statusLabel} {$assignLink} {$editLink} {$chat}</td>
        </tr>";

        $rowNum++;
    }
}

$html = ob_get_clean();

// -- JSON RESPONSE -------------------------------------------------------------
header('Content-Type: application/json');
echo json_encode([
    'html'     => $html,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $per_page,
]);
exit;

// -----------------------------------------------------------------------------
// EXPORT FUNCTIONS
// -----------------------------------------------------------------------------

function exportCSV(array $rows): void {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Full Name', 'Email', 'Contact', 'Country', 'Course', 'Intake', 'Class Type', 'Status', 'Date Joined']);

    $i = 1;
    foreach ($rows as $s) {
        $year   = !empty($s['date_launching']) ? date('Y', strtotime($s['date_launching'])) : '';
        $intake = trim(($s['intake_name'] ?? '') . ' ' . $year);
        fputcsv($out, [
            $i++,
            trim($s['fname'] . ' ' . $s['lname']),
            $s['email']           ?? '',
            $s['contact']         ?? '',
            $s['country']         ?? '',
            $s['course_name']     ?? '',
            $intake,
            $s['class_type_name'] ?? '',
            ($s['verified'] === 'yes') ? 'Active' : 'Inactive',
            !empty($s['date_created']) ? date('d M Y', strtotime($s['date_created'])) : '',
        ]);
    }
    fclose($out);
}

function exportPDF(array $rows): void {
    header('Content-Type: text/html; charset=utf-8');
    $total     = count($rows);
    $generated = date('d F Y, h:i A');
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>Students Export</title>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body  { font-family: Arial, sans-serif; font-size: 11px; color: #333; padding: 20px; }
        h2    { text-align: center; font-size: 15px; margin-bottom: 4px; }
        .meta { text-align: center; color: #777; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th    { background: #333; color: #fff; padding: 6px 7px; text-align: left; }
        td    { padding: 5px 7px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) td { background: #f9f9f9; }
        .active   { background: #27ae60; color: #fff; padding: 1px 7px; border-radius: 8px; }
        .inactive { background: #999;    color: #fff; padding: 1px 7px; border-radius: 8px; }
        .print-btn { display: block; margin: 0 auto 16px; padding: 7px 20px;
                     background: #333; color: #fff; border: none; border-radius: 4px;
                     font-size: 13px; cursor: pointer; }
        @media print { .print-btn { display: none; } }
      </style>
    </head>
    <body>
      <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
      <h2>Students Report</h2>
      <p class="meta">Generated: <?= $generated ?> &nbsp;|&nbsp; Total: <?= $total ?> student(s)</p>
      <table>
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Contact</th>
            <th>Course</th><th>Intake</th><th>Class Type</th><th>Status</th><th>Date Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($rows as $s):
            $year    = !empty($s['date_launching']) ? date('Y', strtotime($s['date_launching'])) : '';
            $intake  = htmlspecialchars(trim(($s['intake_name'] ?? '') . ' ' . $year));
            $active  = ($s['verified'] === 'yes');
            $badge   = $active ? '<span class="active">Active</span>' : '<span class="inactive">Inactive</span>';
            $joined  = !empty($s['date_created']) ? date('d M Y', strtotime($s['date_created'])) : 'N/A';
          ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars(trim($s['fname'] . ' ' . $s['lname'])) ?></td>
            <td><?= htmlspecialchars($s['email']           ?? '') ?></td>
            <td><?= htmlspecialchars($s['contact']         ?? '') ?></td>
            <td><?= htmlspecialchars($s['course_name']     ?? '') ?></td>
            <td><?= $intake ?></td>
            <td><?= htmlspecialchars($s['class_type_name'] ?? '') ?></td>
            <td><?= $badge ?></td>
            <td><?= $joined ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </body>
    </html>
    <?php
}