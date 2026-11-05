<?php
require 'connector.php';
header('Content-Type: application/json; charset=utf-8');


$DEBUG = true;

function fail($msg, $debug = false, $stmt = null) {
    $error = ['error' => $msg];
    if ($debug && $stmt) $error['sql_error'] = $stmt->error;
    echo json_encode($error);
    exit;
}

/* ----------------------- INPUTS ----------------------- */
$isExport = isset($_GET['export']) && (string)$_GET['export'] === '1';

$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limitRaw = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;

// caps
$normalMax = 5000;
$exportMax = 50000;

// pagination decision
if ($isExport) {
    $page = 1;
    $offset = 0;
    // allow big fetch for export (but still capped)
    $limit = ($limitRaw >= 1 && $limitRaw <= $exportMax) ? $limitRaw : $exportMax;
} else {
    $limit = ($limitRaw >= 1 && $limitRaw <= $normalMax) ? $limitRaw : 15;
    $offset = ($page - 1) * $limit;
}

$search    = trim($_GET['search'] ?? '');
$course    = ($_GET['course'] ?? '') !== '' ? (int)$_GET['course'] : null;
$intake    = ($_GET['intake'] ?? '') !== '' ? (int)$_GET['intake'] : null;
$classType = ($_GET['class_type_id'] ?? '') !== '' ? (int)$_GET['class_type_id'] : null;
$order     = trim($_GET['order'] ?? 'last_joined_desc');
$date_from = ($_GET['date_from'] ?? '') ?: null;
$date_to   = ($_GET['date_to'] ?? '') ?: null;
$status    = strtolower(trim((string)($_GET['status'] ?? '')));
$status    = in_array($status, ['active', 'inactive'], true) ? $status : '';

$excludedCourses = [4, 7];
$excludedCoursesStr = implode(',', array_map('intval', $excludedCourses));

/* ----------------------- WHERE BUILD ----------------------- */
$where  = ["u.acc_type = 'afgezt'"];
$params = [];
$types  = "";

// search
if ($search !== '' && mb_strlen($search) >= 3) {
    $where[] = "(u.fname LIKE CONCAT('%',?,'%')
            OR u.lname LIKE CONCAT('%',?,'%')
            OR CONCAT(u.fname,' ',u.lname) LIKE CONCAT('%',?,'%')
            OR u.email LIKE CONCAT('%',?,'%'))";
    $params = array_merge($params, [$search, $search, $search, $search]);
    $types .= "ssss";
}

// class type
if ($classType !== null) {
    $where[] = "u.class_type_id = ?";
    $params[] = $classType;
    $types .= "i";
}

// account status (matches the Active/Inactive label shown in the list)
if ($status === 'active') {
    $where[] = "u.verified IN ('yes','Active')";
} elseif ($status === 'inactive') {
    $where[] = "(u.verified IS NULL OR u.verified NOT IN ('yes','Active'))";
}

// dates
if ($date_from !== null) {
    $where[] = "DATE(u.date_created) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to !== null) {
    $where[] = "DATE(u.date_created) <= ?";
    $params[] = $date_to;
    $types .= "s";
}


$subquery_course_filter = "";
$subquery_intake_filter = "";
$subquery_exclusion     = "AND course_id NOT IN ($excludedCoursesStr)";

// subquery filter used for "latest" course/intake display
if ($course !== null && !in_array($course, $excludedCourses, true)) {
    $subquery_course_filter = "AND course_id = " . (int)$course;
}
if ($intake !== null) {
    $subquery_intake_filter = "AND intake = " . (int)$intake;
}

// main filter (EXISTS)
if ($course !== null || $intake !== null) {
    $reg_where = "1=1";

    if ($course !== null && !in_array($course, $excludedCourses, true)) {
        $reg_where .= " AND course_id = " . (int)$course;
    } else {
        $reg_where .= " AND course_id NOT IN ($excludedCoursesStr)";
    }

    if ($intake !== null) {
        $reg_where .= " AND intake = " . (int)$intake;
    }

    $where[] = "EXISTS (
        SELECT 1
        FROM course_registration cr_filter
        WHERE cr_filter.user_id = u.unique_id
          AND $reg_where
    )";
} else {
    $where[] = "EXISTS (
        SELECT 1
        FROM course_registration cr_filter
        WHERE cr_filter.user_id = u.unique_id
    )";
}

$where_sql = implode(" AND ", $where);

/* ----------------------- ORDER ----------------------- */
switch ($order) {
    case 'last_joined_asc':
        $order_sql = "u.date_created ASC";
        break;
    case 'name_asc':
        $order_sql = "u.fname ASC, u.lname ASC";
        break;
    case 'name_desc':
        $order_sql = "u.fname DESC, u.lname DESC";
        break;
    default:
        $order_sql = "u.date_created DESC";
        break;
}

/* ----------------------- COUNT (ALWAYS FULL FILTERED) ----------------------- */
$count_sql = "
    SELECT COUNT(DISTINCT u.unique_id) AS total
    FROM users u
    WHERE $where_sql
";

$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) fail("Failed to prepare COUNT statement", $DEBUG);

if (!empty($params)) {
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    $count_stmt->bind_param($types, ...$refs);
}

if (!$count_stmt->execute()) fail("Failed to execute COUNT query", $DEBUG, $count_stmt);

$totalEntries = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

/* ----------------------- SELECT (EXPORT = NO LIMIT/OFFSET) ----------------------- */
$base_select = "
    SELECT
        u.unique_id,
        u.fname,
        u.lname,
        u.email,
        u.dp,
        u.verified,
        u.date_created,
        u.class_type_id,
        ct.name AS class_type_name,

        (SELECT course_id
         FROM course_registration
         WHERE user_id = u.unique_id
         $subquery_exclusion
         $subquery_course_filter
         $subquery_intake_filter
         ORDER BY id DESC
         LIMIT 1) AS course_id,

        (SELECT intake
         FROM course_registration
         WHERE user_id = u.unique_id
         $subquery_exclusion
         $subquery_course_filter
         $subquery_intake_filter
         ORDER BY id DESC
         LIMIT 1) AS intake_id,

        (SELECT c.name
         FROM course_registration cr_sub
         JOIN courses c ON cr_sub.course_id = c.id
         WHERE cr_sub.user_id = u.unique_id
           AND cr_sub.course_id NOT IN ($excludedCoursesStr)
           " . ($subquery_course_filter ? str_replace('course_id', 'cr_sub.course_id', $subquery_course_filter) : '') . "
           " . ($subquery_intake_filter ? str_replace('intake', 'cr_sub.intake', $subquery_intake_filter) : '') . "
         ORDER BY cr_sub.id DESC
         LIMIT 1) AS course_name,

        (SELECT c.short_code
         FROM course_registration cr_sub
         JOIN courses c ON cr_sub.course_id = c.id
         WHERE cr_sub.user_id = u.unique_id
           AND cr_sub.course_id NOT IN ($excludedCoursesStr)
           " . ($subquery_course_filter ? str_replace('course_id', 'cr_sub.course_id', $subquery_course_filter) : '') . "
           " . ($subquery_intake_filter ? str_replace('intake', 'cr_sub.intake', $subquery_intake_filter) : '') . "
         ORDER BY cr_sub.id DESC
         LIMIT 1) AS course_short_code,

        (SELECT i.name
         FROM course_registration cr_sub
         LEFT JOIN intakes i ON cr_sub.intake = i.id
         WHERE cr_sub.user_id = u.unique_id
           AND cr_sub.course_id NOT IN ($excludedCoursesStr)
           " . ($subquery_course_filter ? str_replace('course_id', 'cr_sub.course_id', $subquery_course_filter) : '') . "
           " . ($subquery_intake_filter ? str_replace('intake', 'cr_sub.intake', $subquery_intake_filter) : '') . "
         ORDER BY cr_sub.id DESC
         LIMIT 1) AS intake_name,

        (SELECT i.date_launching
         FROM course_registration cr_sub
         LEFT JOIN intakes i ON cr_sub.intake = i.id
         WHERE cr_sub.user_id = u.unique_id
           AND cr_sub.course_id NOT IN ($excludedCoursesStr)
           " . ($subquery_course_filter ? str_replace('course_id', 'cr_sub.course_id', $subquery_course_filter) : '') . "
           " . ($subquery_intake_filter ? str_replace('intake', 'cr_sub.intake', $subquery_intake_filter) : '') . "
         ORDER BY cr_sub.id DESC
         LIMIT 1) AS date_launching,

        (SELECT b.name
         FROM course_registration cr_sub
         LEFT JOIN intakes i ON cr_sub.intake = i.id
         LEFT JOIN branches b ON i.branch_id = b.id
         WHERE cr_sub.user_id = u.unique_id
           AND cr_sub.course_id NOT IN ($excludedCoursesStr)
           " . ($subquery_course_filter ? str_replace('course_id', 'cr_sub.course_id', $subquery_course_filter) : '') . "
           " . ($subquery_intake_filter ? str_replace('intake', 'cr_sub.intake', $subquery_intake_filter) : '') . "
         ORDER BY cr_sub.id DESC
         LIMIT 1) AS branch_name,

        (SELECT COUNT(*)
         FROM course_registration
         WHERE user_id = u.unique_id) AS total_registrations,

        (SELECT COUNT(*)
         FROM course_registration
         WHERE user_id = u.unique_id
           AND course_id NOT IN ($excludedCoursesStr)) AS valid_registrations

    FROM users u
    LEFT JOIN class_type ct ON ct.id = u.class_type_id
    WHERE $where_sql
    ORDER BY $order_sql
";

if ($isExport) {
    // Export: return everything filtered (but still apply a cap to protect server)
    $sql = $base_select . " LIMIT " . (int)$limit;
} else {
    // Normal: paginated
    $sql = $base_select . " LIMIT ? OFFSET ?";
}

$stmt = $conn->prepare($sql);
if (!$stmt) fail("Failed to prepare SELECT", $DEBUG);

// bind params
if ($isExport) {
    // only filter params
    if (!empty($params)) {
        $refs = [];
        foreach ($params as $k => $v) $refs[$k] = &$params[$k];
        $stmt->bind_param($types, ...$refs);
    }
} else {
    // filter params + limit/offset
    $params2 = $params;
    $params2[] = $limit;
    $params2[] = $offset;
    $types2 = $types . "ii";

    $refs2 = [];
    foreach ($params2 as $k => $v) $refs2[$k] = &$params2[$k];
    $stmt->bind_param($types2, ...$refs2);
}

if (!$stmt->execute()) fail("Failed to execute SELECT", $DEBUG, $stmt);

$result = $stmt->get_result();
$students = [];
$counter = $offset + 1;

while ($row = $result->fetch_assoc()) {
    $dp = (!$row['dp'] || $row['dp'] === 'default.jpg')
        ? 'images/default_p_pics/user.png'
        : 'images/user_p_pics/' . $row['dp'];

    $statusText = ($row['verified'] === 'Active' || $row['verified'] === 'yes') ? 'Active' : 'Inactive';

    $year = $row['date_launching'] ? date("Y", strtotime($row['date_launching'])) : "";

    $classTypeName = $row['class_type_name'] ? $row['class_type_name'] : 'N/A';

    $courseFull = 'Not Assigned';
    $intakeBranch = 'Not Assigned';
    $courseId = 0;

    $validRegistrations = (int)($row['valid_registrations'] ?? 0);
    $totalRegistrations = (int)($row['total_registrations'] ?? 0);

    if (!empty($row['course_name'])) {
        $courseFull = htmlspecialchars($row['course_name']);

        if (!empty($row['course_short_code'])) {
            $courseFull .= " (" . htmlspecialchars($row['course_short_code']) . ")";
        }

        if (!empty($row['intake_name']) && $year) {
            $courseFull .= " - " . htmlspecialchars($row['intake_name']) . " " . $year;
        } elseif (!empty($row['intake_name'])) {
            $courseFull .= " - " . htmlspecialchars($row['intake_name']);
        }

        $courseId = (int)($row['course_id'] ?? 0);
    } else {
        if ($totalRegistrations > 0 && $validRegistrations === 0) {
            $courseFull = 'Not Assigned (Non-main courses only)';
        }
    }

    if (!empty($row['intake_name'])) {
        $intakeBranch = htmlspecialchars($row['intake_name']);
        if ($year) $intakeBranch .= " " . $year;
        if (!empty($row['branch_name'])) {
            $intakeBranch .= " (" . htmlspecialchars($row['branch_name']) . ")";
        }
    }

    $dateJoined = $row['date_created'] ? date("d M Y", strtotime($row['date_created'])) : 'N/A';

    $students[] = [
        'counter'         => $counter++,
        'unique_id'       => $row['unique_id'],
        'dp'              => $dp,
        'name'            => htmlspecialchars(trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''))),
        'email'           => htmlspecialchars($row['email'] ?? ''),
        'status'          => $statusText,

        'class_type_id'   => (int)($row['class_type_id'] ?? 0),
        'class_type_name' => htmlspecialchars($classTypeName),

        'course_id'       => $courseId,
        'courseFull'      => $courseFull,
        'intake'          => !empty($row['intake_name']) ? htmlspecialchars($row['intake_name']) : 'N/A',
        'branch'          => !empty($row['branch_name']) ? htmlspecialchars($row['branch_name']) : 'N/A',
        'intakeBranch'    => $intakeBranch,
        'dateJoined'      => $dateJoined,
        'date_created'    => $row['date_created'],

        // optional debug
        'total_registrations' => $totalRegistrations,
        'valid_registrations' => $validRegistrations
    ];
}

$stmt->close();

echo json_encode([
    "students"     => $students,
    "totalEntries" => $totalEntries,
    "currentPage"  => $page,
    "limit"        => $limit,
    "isExport"     => $isExport ? 1 : 0,
    "debug"        => $DEBUG ? [
        "excluded_courses" => $excludedCourses,
        "filters_applied" => [
            "search" => $search,
            "course" => $course,
            "intake" => $intake,
            "class_type" => $classType,
            "date_range" => [$date_from, $date_to],
            "order" => $order
        ],
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "offset" => $offset,
            "mode" => $isExport ? "export" : "normal"
        ]
    ] : null
], JSON_UNESCAPED_UNICODE);
exit;
