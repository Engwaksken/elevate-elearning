<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$systemname = $systemname ?? "Elevate Her 360";
$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$user_id     = $_SESSION[$systemname . "leid"] ?? '';

if ($user_unique === '' && $user_id === '') {
    echo "<div class='alert alert-danger'>Session expired. Please login again.</div>";
    return;
}

function cert_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function cert_table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function cert_column_exists(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "<div class='alert alert-danger'>Database connection not found.</div>";
    return;
}

if (!cert_table_exists($conn, 'certificates')) {
    echo "<div class='alert alert-warning'><i class='fa fa-info-circle'></i> Certificates table was not found.</div>";
    return;
}

$whereParts = [];
$params = [];
$types = '';

if (cert_column_exists($conn, 'certificates', 'user_id') && $user_unique !== '') {
    $whereParts[] = "user_id = ?";
    $params[] = $user_unique;
    $types .= 's';
}

if (cert_column_exists($conn, 'certificates', 'student_id') && $user_id !== '') {
    $whereParts[] = "student_id = ?";
    $params[] = $user_id;
    $types .= 's';
}

if (cert_column_exists($conn, 'certificates', 'student_unique') && $user_unique !== '') {
    $whereParts[] = "student_unique = ?";
    $params[] = $user_unique;
    $types .= 's';
}

if (!$whereParts) {
    echo "<div class='alert alert-danger'>Could not match certificates to your account.</div>";
    return;
}

$statusWhere = cert_column_exists($conn, 'certificates', 'status')
    ? "AND status IN ('active','issued','generated','approved')"
    : "";

$select = [
    "id",
    cert_column_exists($conn, 'certificates', 'certificate_no') ? "certificate_no" : "'' AS certificate_no",
    cert_column_exists($conn, 'certificates', 'course_name') ? "course_name" : "'' AS course_name",
    cert_column_exists($conn, 'certificates', 'intake_name') ? "intake_name" : "'' AS intake_name",
    cert_column_exists($conn, 'certificates', 'completed_on') ? "completed_on" : "NULL AS completed_on",
    cert_column_exists($conn, 'certificates', 'course_date_from') ? "course_date_from" : "NULL AS course_date_from",
    cert_column_exists($conn, 'certificates', 'course_date_to') ? "course_date_to" : "NULL AS course_date_to",
    cert_column_exists($conn, 'certificates', 'created_at') ? "created_at" : "NULL AS created_at",
];

$sql = "
    SELECT " . implode(", ", $select) . "
    FROM certificates
    WHERE (" . implode(" OR ", $whereParts) . ")
    {$statusWhere}
    ORDER BY COALESCE(completed_on, course_date_to, created_at) DESC, id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "<div class='alert alert-danger'><strong>SQL Error:</strong> " . cert_e($conn->error) . "</div>";
    return;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
.cert-page-title {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    flex-wrap:wrap;
}

.cert-card {
    border:1px solid #e5e7eb;
    border-radius:14px;
    background:#fff;
    box-shadow:0 8px 25px rgba(15,23,42,.06);
    overflow:hidden;
}

.cert-card-header {
    padding:20px 24px;
    background:linear-gradient(135deg,#fff7ed,#ffffff);
    border-bottom:1px solid #e5e7eb;
}

.cert-card-header h3 {
    margin:0;
    font-weight:700;
    color:#1f2937;
}

.cert-summary {
    color:#6b7280;
    margin-top:5px;
}

.cert-table {
    margin-bottom:0;
}

.cert-table thead th {
    background:#f9fafb;
    color:#374151;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    border-bottom:1px solid #e5e7eb !important;
}

.cert-no {
    font-weight:700;
    color:#f86505;
}

.cert-course {
    font-weight:600;
    color:#111827;
}

.cert-badge {
    display:inline-block;
    padding:5px 10px;
    border-radius:999px;
    background:#ecfdf5;
    color:#047857;
    font-size:12px;
    font-weight:700;
}

.cert-actions {
    display:flex;
    gap:7px;
    flex-wrap:wrap;
}

.cert-actions .btn {
    border-radius:8px;
}

.cert-empty {
    padding:45px 20px;
    text-align:center;
    color:#6b7280;
}

.cert-empty i {
    font-size:45px;
    color:#f59e0b;
    margin-bottom:12px;
}
</style>

<div class="wrapper wrapper-content animated fadeInRight">

    <div class="row wrapper border-bottom white-bg page-heading" style="margin-bottom:20px;">
        <div class="col-lg-12">
            <div class="cert-page-title">
                <div>
                    <h2><i class="fa fa-certificate text-warning"></i> My Certificates</h2>
                    <ol class="breadcrumb">
                        <li><a href="?">Home</a></li>
                        <li class="active"><strong>My Certificates</strong></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="cert-card">
        <div class="cert-card-header">
            <h3><i class="fa fa-award"></i> Issued Certificates</h3>
            <div class="cert-summary">
                View your certificate professionally or download it as PDF.
            </div>
        </div>

        <div class="ibox-content" style="padding:0;">

            <?php if (!$result || $result->num_rows < 1): ?>

                <div class="cert-empty">
                    <i class="fa fa-certificate"></i>
                    <h3>No certificates available yet</h3>
                    <p>Your certificates will appear here after they are issued.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover cert-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Certificate No.</th>
                            <th>Course</th>
                            <th>Intake</th>
                            <th>Training Period</th>
                            <th>Issued On</th>
                            <th width="210">Action</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php $i = 1; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            $certId = (int)$row['id'];

                            $from = !empty($row['course_date_from']) ? date('d M Y', strtotime($row['course_date_from'])) : '';
                            $to   = !empty($row['course_date_to']) ? date('d M Y', strtotime($row['course_date_to'])) : '';

                            if ($from && $to) {
                                $period = $from . ' - ' . $to;
                            } elseif (!empty($row['completed_on'])) {
                                $period = date('d M Y', strtotime($row['completed_on']));
                            } else {
                                $period = '-';
                            }

                            $issuedOn = !empty($row['created_at'])
                                ? date('d M Y', strtotime($row['created_at']))
                                : '-';

                            $viewUrl = "certificate.php?id={$certId}&action=view";
                            $downloadUrl = "certificate.php?id={$certId}&action=download";
                            ?>

                            <tr>
                                <td><?= $i++; ?></td>

                                <td>
                                    <span class="cert-no">
                                        <?= cert_e($row['certificate_no'] ?: 'N/A'); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="cert-course">
                                        <?= cert_e($row['course_name'] ?: '-'); ?>
                                    </div>
                                </td>

                                <td><?= cert_e($row['intake_name'] ?: '-'); ?></td>

                                <td><?= cert_e($period); ?></td>

                                <td>
                                    <span class="cert-badge">
                                        <?= cert_e($issuedOn); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="cert-actions">
                                        <a href="<?= cert_e($viewUrl); ?>" target="_blank" class="btn btn-primary btn-sm">
                                            <i class="fa fa-eye"></i> View
                                        </a>

                                        <a href="<?= cert_e($downloadUrl); ?>" class="btn btn-success btn-sm">
                                            <i class="fa fa-download"></i> PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php $stmt->close(); ?>