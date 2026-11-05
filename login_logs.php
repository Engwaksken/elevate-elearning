<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'scripts/connector.php';

if (
    !isset($_SESSION[$systemname . 'loggedin']) ||
    $_SESSION[$systemname . 'loggedin'] === ''
) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION[$systemname . 'letype'] ?? '';

if (!in_array($role, ['sabrtw', 'xybrtw'], true)) {
    $_SESSION[$systemname . 'errormsg'] = 'You do not have permission to access Login Logs.';
    header('Location: index.php');
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
    $stmt->execute();

    return $stmt->get_result();
}

function pagUrl(int $p): string
{
    $q = array_merge($_GET, [
        'page' => $p,
        'view' => 'logins'
    ]);

    return h($_SERVER['PHP_SELF'] . '?' . http_build_query($q));
}

$records_per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$status_filter = trim($_GET['status'] ?? '');
$course_filter = trim($_GET['course'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$offset = ($page - 1) * $records_per_page;

$need_course_join = $course_filter !== '';

$joins = "LEFT JOIN users u ON ll.email = u.email";

if ($need_course_join) {
    $joins .= "
        LEFT JOIN course_registration cr ON cr.user_id = u.unique_id
        LEFT JOIN courses c ON cr.course_id = c.id
    ";
}

$where_parts = [];
$filter_params = [];
$filter_types = '';

if ($status_filter !== '') {
    $where_parts[] = 'll.status = ?';
    $filter_params[] = $status_filter;
    $filter_types .= 's';
}

if ($need_course_join) {
    $where_parts[] = 'c.name LIKE ?';
    $filter_params[] = '%' . $course_filter . '%';
    $filter_types .= 's';
}

if ($date_from !== '') {
    $where_parts[] = 'DATE(ll.login_time) >= ?';
    $filter_params[] = $date_from;
    $filter_types .= 's';
}

if ($date_to !== '') {
    $where_parts[] = 'DATE(ll.login_time) <= ?';
    $filter_params[] = $date_to;
    $filter_types .= 's';
}

$where_clause = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$count_sql = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT ll.id
        FROM login_logs ll
        $joins
        $where_clause
        GROUP BY ll.id
    ) sub
";

$count_result = runQuery($conn, $count_sql, $filter_types, $filter_params);
$count_row = $count_result ? $count_result->fetch_assoc() : ['total' => 0];

$total_records = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $records_per_page;

$stats_sql = "
    SELECT
        COUNT(DISTINCT ll.id) AS total,
        SUM(CASE WHEN ll.status = 'success' THEN 1 ELSE 0 END) AS successful,
        SUM(CASE WHEN ll.status = 'failed' THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN ll.status = 'error' THEN 1 ELSE 0 END) AS errors,
        SUM(CASE WHEN DATE(ll.login_time) = CURDATE() THEN 1 ELSE 0 END) AS today
    FROM login_logs ll
    $joins
    $where_clause
";

$stats_result = runQuery($conn, $stats_sql, $filter_types, $filter_params);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

$data_sql = "
    SELECT
        ll.id,
        ll.email,
        ll.login_time,
        ll.ip_address,
        ll.status,
        u.fname,
        u.lname,
        (
            SELECT GROUP_CONCAT(DISTINCT c2.name ORDER BY c2.name SEPARATOR ', ')
            FROM course_registration cr2
            LEFT JOIN courses c2 ON cr2.course_id = c2.id
            WHERE cr2.user_id = u.unique_id
        ) AS course_name
    FROM login_logs ll
    $joins
    $where_clause
    GROUP BY ll.id
    ORDER BY ll.login_time DESC
    LIMIT ? OFFSET ?
";

$data_params = array_merge($filter_params, [$records_per_page, $offset]);
$data_types = $filter_types . 'ii';

$result = runQuery($conn, $data_sql, $data_types, $data_params);

$courses_result = $conn->query("SELECT DISTINCT name FROM courses WHERE name IS NOT NULL AND name != '' ORDER BY name ASC");
$available_courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2>User Login Logs</h2>
        <ol class="breadcrumb">
            <li>
                <a href="index.php">Home</a>
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

                <div class="col-md-3">
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

                <div class="col-md-3">
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
            <h5>Login Records</h5>
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