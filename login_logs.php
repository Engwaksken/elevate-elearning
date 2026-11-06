<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'scripts/connector.php';

if (
    !isset($_SESSION[$systemname . 'loggedin']) ||
    $_SESSION[$systemname . 'loggedin'] === ''
) {
    header('Location: login');
    exit;
}

$role = $_SESSION[$systemname . 'letype'] ?? '';

if (!in_array($role, ['sabrtw', 'xybrtw'], true)) {
    $_SESSION[$systemname . 'errormsg'] = 'You do not have permission to access Login Logs.';
    header('Location: ./');
    exit;
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function runQuery(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result|false
{
    if ($types === '') {
        return $conn->query($sql);
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        error_log('Login logs query failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// Older installations write ip/created_at while newer schemas use
// ip_address/login_time. Resolve the actual columns once and alias them below.
$loginLogColumns = [];
$columnResult = $conn->query('SHOW COLUMNS FROM login_logs');
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) $loginLogColumns[] = $column['Field'];
}
$timeColumn = in_array('login_time', $loginLogColumns, true) ? 'login_time' : 'created_at';
$ipColumn = in_array('ip_address', $loginLogColumns, true) ? 'ip_address' : 'ip';
if (!in_array($timeColumn, $loginLogColumns, true) || !in_array($ipColumn, $loginLogColumns, true)) {
    error_log('Login logs table is missing a supported timestamp or IP column.');
    echo '<div class="app-alert app-alert-danger">Login records are temporarily unavailable because the login log table is not configured correctly.</div>';
    return;
}
$timeSql = '`' . $timeColumn . '`';
$ipSql = '`' . $ipColumn . '`';

function pagUrl(int $p): string
{
    $q = array_merge($_GET, [
        'page' => $p,
        'view' => 'logins'
    ]);

    return h($_SERVER['PHP_SELF'] . '?' . http_build_query($q));
}

/*
 * Performance: the login log grows with every sign-in, so this page
 *  - shows the last 30 days unless another range or dates are chosen,
 *  - counts and summarises in ONE pass over login_logs only (no joins),
 *  - picks the 15 rows for the page first and only then looks up names and
 *    courses for those rows,
 *  - filters dates on the raw column so an index on login_time can be used
 *    (run scripts/cli/add_login_log_indexes.php once to create the indexes).
 */
$records_per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$status_filter = trim($_GET['status'] ?? '');
$course_filter = trim($_GET['course'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$valid_date = static fn(string $d): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
if ($date_from !== '' && !$valid_date($date_from)) $date_from = '';
if ($date_to !== '' && !$valid_date($date_to)) $date_to = '';

// Quick range; ignored when explicit dates are given. Default: last 30 days.
$range_options = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];
$range = (string)($_GET['range'] ?? '30');
if (!isset($range_options[$range])) $range = '30';
if ($date_from === '' && $date_to === '' && $range !== 'all') {
    $range_from = date('Y-m-d 00:00:00', strtotime('-' . ((int)$range - 1) . ' days'));
} else {
    $range_from = '';
}

if (!in_array($status_filter, ['', 'success', 'failed', 'error'], true)) $status_filter = '';

$where_parts = [];
$filter_params = [];
$filter_types = '';

if ($status_filter !== '') {
    $where_parts[] = 'll.status = ?';
    $filter_params[] = $status_filter;
    $filter_types .= 's';
}

if ($course_filter !== '') {
    // EXISTS instead of joins: no duplicate rows, so no GROUP BY needed.
    $where_parts[] = 'EXISTS (
        SELECT 1
        FROM users uf
        JOIN course_registration crf ON crf.user_id = uf.unique_id
        JOIN courses cf ON cf.id = crf.course_id
        WHERE uf.email = ll.email AND cf.name = ?
    )';
    $filter_params[] = $course_filter;
    $filter_types .= 's';
}

// Range conditions on the raw column (index-friendly), never DATE(column).
if ($range_from !== '') {
        $where_parts[] = "ll.$timeSql >= ?";
    $filter_params[] = $range_from;
    $filter_types .= 's';
}

if ($date_from !== '') {
    $where_parts[] = "ll.$timeSql >= ?";
    $filter_params[] = $date_from . ' 00:00:00';
    $filter_types .= 's';
}

if ($date_to !== '') {
    $where_parts[] = "ll.$timeSql < ?";
    $filter_params[] = date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day'));
    $filter_types .= 's';
}

$where_clause = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// One pass: total for pagination + the summary cards.
$today_start = date('Y-m-d 00:00:00');
$stats_sql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(ll.status = 'success'), 0) AS successful,
        COALESCE(SUM(ll.status = 'failed'), 0)  AS failed,
        COALESCE(SUM(ll.status = 'error'), 0)   AS errors,
        COALESCE(SUM(ll.$timeSql >= ?), 0)    AS today
    FROM login_logs ll
    $where_clause
";

$stats_result = runQuery($conn, $stats_sql, 's' . $filter_types, array_merge([$today_start], $filter_params));
$stats = $stats_result ? ($stats_result->fetch_assoc() ?: []) : [];

$total_records = (int)($stats['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $records_per_page;

// Page rows first (login_logs only), then names/courses for just those rows.
$data_sql = "
    SELECT
        p.id,
        p.email,
        p.login_time,
        p.ip_address,
        p.status,
        u.fname,
        u.lname,
        (
            SELECT GROUP_CONCAT(DISTINCT c2.name ORDER BY c2.name SEPARATOR ', ')
            FROM course_registration cr2
            JOIN courses c2 ON cr2.course_id = c2.id
            WHERE cr2.user_id = u.unique_id
        ) AS course_name
    FROM (
        SELECT ll.id, ll.email, ll.$timeSql AS login_time, ll.$ipSql AS ip_address, ll.status
        FROM login_logs ll
        $where_clause
        ORDER BY ll.$timeSql DESC, ll.id DESC
        LIMIT ? OFFSET ?
    ) p
    -- one user per email, so duplicate user records never duplicate a log row
    LEFT JOIN users u ON u.id = (SELECT MIN(u1.id) FROM users u1 WHERE u1.email = p.email)
    ORDER BY p.login_time DESC, p.id DESC
";

$data_params = array_merge($filter_params, [$records_per_page, $offset]);
$data_types = $filter_types . 'ii';

$result = $total_records > 0 ? runQuery($conn, $data_sql, $data_types, $data_params) : false;

$courses_result = $conn->query("SELECT DISTINCT name FROM courses WHERE name IS NOT NULL AND name != '' ORDER BY name ASC");
$available_courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];

if ($date_from !== '' || $date_to !== '') {
    $period_label = ($date_from !== '' ? date('d M Y', strtotime($date_from)) : 'the beginning')
        . ' to ' . ($date_to !== '' ? date('d M Y', strtotime($date_to)) : 'today');
} else {
    $period_label = $range_options[$range];
}
?>

<div class="login-logs-page">
<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2>User Login Logs</h2>
        <ol class="breadcrumb">
            <li>
                <a href="./">Home</a>
            </li>
            <li class="active">
                <strong>Login Logs</strong>
            </li>
        </ol>
    </div>
</div>

<div class="wrapper wrapper-content animated fadeInRight">

    <form method="GET" action="<?= h($_SERVER['PHP_SELF']); ?>" class="ibox">
        <input type="hidden" name="view" value="logins">

        <div class="ibox-title">
            <h5>Filter Login Logs</h5>
        </div>

        <div class="ibox-content">
            <div class="row">

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course" class="form-control">
                            <option value="">All Courses</option>
                            <?php foreach ($available_courses as $c): ?>
                                <option value="<?= h($c['name']); ?>" <?= $course_filter === $c['name'] ? 'selected' : ''; ?>>
                                    <?= h($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Period</label>
                        <select name="range" class="form-control" title="Used when no From/To dates are set">
                            <?php foreach ($range_options as $value => $label): ?>
                                <option value="<?= h($value); ?>" <?= $range === (string)$value ? 'selected' : ''; ?>><?= h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="success" <?= $status_filter === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="failed" <?= $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="error" <?= $status_filter === 'error' ? 'selected' : ''; ?>>Error</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= h($date_from); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= h($date_to); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group" style="margin-top:24px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-filter"></i> Filter
                        </button>

                        <a href="<?= h($_SERVER['PHP_SELF']); ?>?view=logins" class="btn btn-default">
                            Clear
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>

    <div class="row">

        <div class="col-md-3">
            <div class="ibox">
                <div class="ibox-content text-center">
                    <h1 class="no-margins"><?= number_format((int)($stats['total'] ?? 0)); ?></h1>
                    <small>Total Logins</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="ibox">
                <div class="ibox-content text-center">
                    <h1 class="no-margins text-success"><?= number_format((int)($stats['successful'] ?? 0)); ?></h1>
                    <small>Successful</small>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="ibox">
                <div class="ibox-content text-center">
                    <h1 class="no-margins text-danger"><?= number_format((int)($stats['failed'] ?? 0)); ?></h1>
                    <small>Failed</small>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="ibox">
                <div class="ibox-content text-center">
                    <h1 class="no-margins text-warning"><?= number_format((int)($stats['errors'] ?? 0)); ?></h1>
                    <small>Errors</small>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="ibox">
                <div class="ibox-content text-center">
                    <h1 class="no-margins text-info"><?= number_format((int)($stats['today'] ?? 0)); ?></h1>
                    <small>Today</small>
                </div>
            </div>
        </div>

    </div>

    <div class="ibox">
        <div class="ibox-title">
            <h5>Login Records &mdash; <?= h($period_label); ?></h5>
            <div class="ibox-tools">
                <span class="label label-primary"><?= number_format($total_records); ?> records</span>
            </div>
        </div>

        <div class="ibox-content">

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Course(s)</th>
                            <th>Login Time</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php
                            $row_num = $offset + 1;
                            while ($row = $result->fetch_assoc()):
                                $status = strtolower((string)($row['status'] ?? ''));

                                if ($status === 'success') {
                                    $label_class = 'label-success';
                                } elseif ($status === 'failed') {
                                    $label_class = 'label-danger';
                                } else {
                                    $label_class = 'label-warning';
                                }

                                $full_name = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
                            ?>
                                <tr>
                                    <td><?= $row_num++; ?></td>

                                    <td>
                                        <?= h($full_name ?: 'Unknown User'); ?>
                                    </td>

                                    <td>
                                        <i class="fa fa-envelope"></i>
                                        <?= h($row['email']); ?>
                                    </td>

                                    <td>
                                        <?= h($row['course_name'] ?: '-'); ?>
                                    </td>

                                    <td style="white-space:nowrap;">
                                        <?php
                                        if (!empty($row['login_time'])) {
                                            echo h(date('d M Y, H:i', strtotime($row['login_time'])));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <code><?= h($row['ip_address'] ?: '-'); ?></code>
                                    </td>

                                    <td>
                                        <span class="label <?= $label_class; ?>">
                                            <?= h(ucfirst($status ?: 'Unknown')); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:30px;">
                                    <i class="fa fa-search"></i>
                                    No login records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p class="text-muted">
                            Showing
                            <?= number_format($offset + 1); ?>
                            -
                            <?= number_format(min($offset + $records_per_page, $total_records)); ?>
                            of
                            <?= number_format($total_records); ?>
                            records
                        </p>
                    </div>

                    <div class="col-md-6 text-right">
                        <ul class="pagination" style="margin:0;">

                            <?php if ($page > 1): ?>
                                <li>
                                    <a href="<?= pagUrl($page - 1); ?>">
                                        <i class="fa fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="disabled">
                                    <span><i class="fa fa-chevron-left"></i></span>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);

                            if ($start > 1):
                            ?>
                                <li><a href="<?= pagUrl(1); ?>">1</a></li>
                                <?php if ($start > 2): ?>
                                    <li class="disabled"><span>...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="<?= $i === $page ? 'active' : ''; ?>">
                                    <a href="<?= pagUrl($i); ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <li class="disabled"><span>...</span></li>
                                <?php endif; ?>
                                <li><a href="<?= pagUrl($total_pages); ?>"><?= $total_pages; ?></a></li>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <li>
                                    <a href="<?= pagUrl($page + 1); ?>">
                                        <i class="fa fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="disabled">
                                    <span><i class="fa fa-chevron-right"></i></span>
                                </li>
                            <?php endif; ?>

                        </ul>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</div>
