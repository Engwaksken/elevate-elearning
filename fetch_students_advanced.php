<?php

require 'connector.php';
header('Content-Type: application/json; charset=utf-8');

function send_error($msg){
    echo json_encode(['error'=>$msg]);
    exit;
}

// Read inputs and sanitize
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limitRaw = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$limit = ($limitRaw <= 0) ? 15 : $limitRaw;
if($limitRaw >= 1000000) { 
    $limit = 1000000;
}
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course = isset($_GET['course']) && $_GET['course'] !== '' ? (int)$_GET['course'] : null;
$intake = isset($_GET['intake']) && $_GET['intake'] !== '' ? (int)$_GET['intake'] : null;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
$order = isset($_GET['order']) ? trim($_GET['order']) : 'last_joined_desc';
$date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;

// Build WHERE clause
$where = ["u.acc_type = 'afgezt'"];
$params = [];
$types = '';

if($search !== '') {
    $where[] = "(u.fname LIKE CONCAT('%',?,'%') OR u.lname LIKE CONCAT('%',?,'%') OR CONCAT(u.fname,' ',u.lname) LIKE CONCAT('%',?,'%') OR u.email LIKE CONCAT('%',?,'%') OR u.phone LIKE CONCAT('%',?,'%'))";
    $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
    $types .= str_repeat('s',5);
}

if($course !== null) {
    $where[] = "u.course_id = ?";
    $params[] = $course;
    $types .= 'i';
}

if($intake !== null) {
    $where[] = "u.intake_id = ?";
    $params[] = $intake;
    $types .= 'i';
}

if($status !== null) {
    // assume status stored in 'status' column
    $where[] = "u.status = ?";
    $params[] = $status;
    $types .= 's';
}

if($date_from !== null) {
    $where[] = "DATE(u.date_created) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if($date_to !== null) {
    $where[] = "DATE(u.date_created) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = implode(' AND ', $where);

// Order mapping
$order_sql = "u.date_created DESC";
switch($order){
    case 'last_joined_asc': $order_sql = "u.date_created ASC"; break;
    case 'name_asc': $order_sql = "u.fname ASC, u.lname ASC"; break;
    case 'name_desc': $order_sql = "u.fname DESC, u.lname DESC"; break;
    case 'last_joined_desc':
    default:
        $order_sql = "u.date_created DESC";
}

// Count total
$count_sql = "SELECT COUNT(*) as cnt
              FROM users u
              WHERE {$where_sql}";
$stmt = $conn->prepare($count_sql);
if($stmt === false) send_error('DB prepare failed: count');
if(!empty($params)) {
    // bind params dynamically
    $bind_names = [];
    $bind_names[] = $types;
    for ($i=0; $i<count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}
if(!$stmt->execute()) {
    send_error('DB execute failed: count');
}
$res = $stmt->get_result();
$totalEntries = ($res && $row = $res->fetch_assoc()) ? (int)$row['cnt'] : 0;
$stmt->close();

// Select data with joins for course/intake/branch names
$select_sql = "SELECT SQL_CALC_FOUND_ROWS u.unique_id, u.fname, u.lname, u.email, u.dp, u.course_id, u.intake_id, u.status,
               c.name as course_name, c.short_code as course_code,
               i.name as intake_name, b.name AS branch_name, u.date_created
               FROM users u
               LEFT JOIN courses c ON u.course_id = c.id
               LEFT JOIN intakes i ON u.intake_id = i.id
               LEFT JOIN branches b ON i.branch_id = b.id
               WHERE {$where_sql}
               ORDER BY {$order_sql}
               LIMIT ? OFFSET ?";

$stmt = $conn->prepare($select_sql);
if($stmt === false) send_error('DB prepare failed: select');

$exec_params = $params; // copy
$exec_types = $types;

// append limit & offset ints
$exec_types .= 'ii';
$exec_params[] = $limit;
$exec_params[] = $offset;

// bind params
$bind_names = [];
$bind_names[] = $exec_types;
for ($i=0; $i<count($exec_params); $i++) {
    $bind_names[] = &$exec_params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

if(!$stmt->execute()) {
    send_error('DB execute failed: select');
}
$result = $stmt->get_result();
$students = [];
$counterStart = $offset + 1;
while($r = $result->fetch_assoc()){
    $counter = $counterStart++;
    // dp fallback
    $dp = $r['dp'] ? $r['dp'] : 'assets/img/default-avatar.png';
    $fullName = trim($r['fname'].' '.$r['lname']);
    $courseFull = $r['course_name'] ? htmlspecialchars($r['course_name']).' ('.htmlspecialchars($r['course_code']).')' : '';
    $intakeBranch = $r['intake_name'] ? htmlspecialchars($r['intake_name']).' - '.htmlspecialchars($r['branch_name']) : '';
    $statusHtml = $r['status'] === 'Active' ? '<span class="label label-primary">Active</span>' : '<span class="label label-default">'.htmlspecialchars($r['status']).'</span>';

    $students[] = [
        'counter' => $counter,
        'unique_id' => $r['unique_id'],
        'name' => htmlspecialchars($fullName),
        'email' => htmlspecialchars($r['email']),
        'dp' => $dp,
        'course_id' => (int)$r['course_id'],
        'courseFull' => $courseFull,
        'intakeBranch' => $intakeBranch,
        'status' => $r['status'],
        'statusHtml' => $statusHtml,
        'date_created' => $r['date_created']
    ];
}
$stmt->close();

// Return JSON
echo json_encode([
    'students' => $students,
    'totalEntries' => $totalEntries,
    'currentPage' => $page,
    'limit' => $limit
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
exit;
?>