<?php
$role = $_SESSION[$systemname . "letype"] ?? '';
$user_unique = $_SESSION[$systemname . "leido"] ?? '';

if (!in_array($role, ['sabrtw', 'xybrtw', 'xtfbry'], true)) {
    echo "<div class='app-alert app-alert-danger'><i class='fa fa-ban'></i> Access denied.</div>";
    return;
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rc_column_exists(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}

function rc_table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

$hasBranches = rc_table_exists($conn, 'branches') && rc_column_exists($conn, 'intakes', 'branch_id');

$intakeSelectSql = $hasBranches
    ? "
        SELECT
            i.id,
            i.name,
            i.date_launching,
            i.date_ending,
            b.id AS branch_id,
            b.name AS branch_name
        FROM intakes i
        LEFT JOIN branches b ON b.id = i.branch_id
        ORDER BY i.date_launching DESC
    "
    : "
        SELECT
            i.id,
            i.name,
            i.date_launching,
            i.date_ending,
            NULL AS branch_id,
            NULL AS branch_name
        FROM intakes i
        ORDER BY i.date_launching DESC
    ";

function rc_intake_label(array $i): string {
    $parts = [];

    $name = trim((string)($i['name'] ?? ''));
    $parts[] = $name !== '' ? $name : ('Intake #' . (int)($i['id'] ?? 0));

    if (!empty($i['branch_name'])) {
        $parts[] = 'Branch: ' . trim((string)$i['branch_name']);
    }

    if (!empty($i['date_ending'])) {
        $parts[] = 'Ends: ' . date('d M Y', strtotime((string)$i['date_ending']));
    }

    return implode(' - ', $parts);
}

$course_id = (int)($_GET['course_id'] ?? 0);
$intake_id = (int)($_GET['intake_id'] ?? 0);
$search    = trim($_GET['search'] ?? '');
$page      = max(1, (int)($_GET['page_no'] ?? 1));
$limit     = 20;
$offset    = ($page - 1) * $limit;

$totalRows = 0;
$totalPages = 1;
$students = [];

$courses = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
$intakes = $conn->query($intakeSelectSql);

if ($course_id > 0 && $intake_id > 0) {
    $searchSql = "";
    $searchParam = "%{$search}%";

    if ($search !== '') {
        $searchSql = "
            AND (
                u.fname LIKE ?
                OR u.other_name LIKE ?
                OR u.lname LIKE ?
                OR u.email LIKE ?
                OR u.student_no LIKE ?
            )
        ";
    }

    $countSql = "
        SELECT COUNT(DISTINCT cr.id) AS total
        FROM course_registration cr
        INNER JOIN users u ON cr.user_id = u.unique_id
        WHERE cr.course_id = ?
          AND cr.intake = ?
          AND u.acc_type = 'afgezt'
          {$searchSql}
    ";

    $countStmt = $conn->prepare($countSql);

    if ($countStmt) {
        if ($search !== '') {
            $countStmt->bind_param(
                "iisssss",
                $course_id,
                $intake_id,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam
            );
        } else {
            $countStmt->bind_param("ii", $course_id, $intake_id);
        }

        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalRows = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }

    $totalPages = max(1, (int)ceil($totalRows / $limit));

    $sql = "
        SELECT 
            cr.id AS registration_id,
            cr.user_id,
            cr.status,
            u.fname,
            u.other_name,
            u.lname,
            u.email,
            u.student_no,
            c.name AS course_name,
            i.name AS intake_name,

            COUNT(DISTINCT a.id) AS total_assignments,
            COUNT(DISTINCT hi.id) AS submitted_assignments,
            ROUND(AVG(CASE WHEN hi.id IS NOT NULL THEN COALESCE(hi.finalmarks, 0) END), 2) AS avg_score,

            cert.id AS certificate_id,
            rec.status AS recommendation_status

        FROM course_registration cr
        INNER JOIN users u ON cr.user_id = u.unique_id
        INNER JOIN courses c ON cr.course_id = c.id
        INNER JOIN intakes i ON cr.intake = i.id

        LEFT JOIN course_unit cu 
            ON cu.course_id = cr.course_id

        LEFT JOIN assignments a 
            ON a.course_unit = cu.id 
            AND a.intake = cr.intake 
            AND a.dummy != 'yes'

        LEFT JOIN handed_in hi 
            ON hi.assignment_id = a.id 
            AND hi.user_id = cr.user_id

        LEFT JOIN certificates cert 
            ON cert.user_id = cr.user_id 
            AND cert.course_id = cr.course_id 
            AND cert.intake_id = cr.intake
            AND cert.status = 'active'

        LEFT JOIN certificate_recommendations rec
            ON rec.user_id = cr.user_id
            AND rec.course_id = cr.course_id
            AND rec.intake_id = cr.intake

        WHERE cr.course_id = ?
          AND cr.intake = ?
          AND u.acc_type = 'afgezt'
          {$searchSql}

        GROUP BY cr.id
        ORDER BY u.fname ASC, u.other_name ASC, u.lname ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo "<div class='app-alert app-alert-danger'><i class='fa fa-triangle-exclamation'></i> SQL Error: " . e($conn->error) . "</div>";
    } else {
        if ($search !== '') {
            $stmt->bind_param(
                "iisssssii",
                $course_id,
                $intake_id,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
                $limit,
                $offset
            );
        } else {
            $stmt->bind_param("iiii", $course_id, $intake_id, $limit, $offset);
        }

        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $students[] = $row;
        }

        $stmt->close();
    }
}

$courseStats = [];
$courseStatsRes = $conn->query("
    SELECT
        c.id,
        c.name,
        COUNT(DISTINCT rec.id) AS recommended_count,
        COUNT(DISTINCT cert.id) AS generated_count
    FROM courses c
    LEFT JOIN certificate_recommendations rec ON rec.course_id = c.id
    LEFT JOIN certificates cert ON cert.course_id = c.id AND cert.status = 'active'
    GROUP BY c.id, c.name
    HAVING recommended_count > 0 OR generated_count > 0
    ORDER BY recommended_count DESC, c.name ASC
");

if ($courseStatsRes) {
    while ($row = $courseStatsRes->fetch_assoc()) {
        $courseStats[] = $row;
    }
}

$statsScopedSql = "";
$statsScopedParams = [];
$statsScopedTypes = "";

if ($course_id > 0 && $intake_id > 0) {
    $statsScopedSql = "WHERE course_id = ? AND intake_id = ?";
    $statsScopedParams = [$course_id, $intake_id];
    $statsScopedTypes = "ii";
}

$recCount = 0;
$genCount = 0;

$recSql = "SELECT COUNT(*) AS c FROM certificate_recommendations" .
    ($course_id > 0 && $intake_id > 0 ? " WHERE course_id = ? AND intake_id = ?" : "");
$recStmt = $conn->prepare($recSql);
if ($recStmt) {
    if ($course_id > 0 && $intake_id > 0) {
        $recStmt->bind_param("ii", $course_id, $intake_id);
    }
    $recStmt->execute();
    $recCount = (int)($recStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $recStmt->close();
}

$genSql = "SELECT COUNT(*) AS c FROM certificates WHERE status = 'active'" .
    ($course_id > 0 && $intake_id > 0 ? " AND course_id = ? AND intake_id = ?" : "");
$genStmt = $conn->prepare($genSql);
if ($genStmt) {
    if ($course_id > 0 && $intake_id > 0) {
        $genStmt->bind_param("ii", $course_id, $intake_id);
    }
    $genStmt->execute();
    $genCount = (int)($genStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $genStmt->close();
}

$uniqueViewers = 0;
$uniqueDownloaders = 0;
$totalViews = 0;
$totalDownloads = 0;

$actStatsSql = "
    SELECT
        COUNT(DISTINCT CASE WHEN action = 'view' THEN user_id END) AS unique_viewers,
        COUNT(DISTINCT CASE WHEN action = 'download' THEN user_id END) AS unique_downloaders,
        SUM(CASE WHEN action = 'view' THEN 1 ELSE 0 END) AS total_views,
        SUM(CASE WHEN action = 'download' THEN 1 ELSE 0 END) AS total_downloads
    FROM certificate_activity
    {$statsScopedSql}
";
$actStatsStmt = $conn->prepare($actStatsSql);
if ($actStatsStmt) {
    if ($statsScopedTypes !== '') {
        $actStatsStmt->bind_param($statsScopedTypes, ...$statsScopedParams);
    }
    $actStatsStmt->execute();
    $actRow = $actStatsStmt->get_result()->fetch_assoc() ?: [];
    $uniqueViewers     = (int)($actRow['unique_viewers'] ?? 0);
    $uniqueDownloaders = (int)($actRow['unique_downloaders'] ?? 0);
    $totalViews        = (int)($actRow['total_views'] ?? 0);
    $totalDownloads    = (int)($actRow['total_downloads'] ?? 0);
    $actStatsStmt->close();
}


$act_course_id = (int)($_GET['act_course_id'] ?? 0);
$act_intake_id = (int)($_GET['act_intake_id'] ?? 0);
$act_type      = $_GET['act_type'] ?? '';
$act_type      = in_array($act_type, ['view', 'download'], true) ? $act_type : '';
$act_page      = max(1, (int)($_GET['act_page'] ?? 1));
$act_limit     = 20;
$act_offset    = ($act_page - 1) * $act_limit;

$activityRows = [];
$activityTotal = 0;
$activityTotalPages = 1;

$actWhere = [];
$actParams = [];
$actTypes = "";

if ($act_course_id > 0) {
    $actWhere[] = "ca.course_id = ?";
    $actParams[] = $act_course_id;
    $actTypes .= "i";
}
if ($act_intake_id > 0) {
    $actWhere[] = "ca.intake_id = ?";
    $actParams[] = $act_intake_id;
    $actTypes .= "i";
}
if ($act_type !== '') {
    $actWhere[] = "ca.action = ?";
    $actParams[] = $act_type;
    $actTypes .= "s";
}

$actWhereSql = $actWhere ? ("WHERE " . implode(" AND ", $actWhere)) : "";

$actCountSql = "
    SELECT COUNT(*) AS total
    FROM certificate_activity ca
    {$actWhereSql}
";
$actCountStmt = $conn->prepare($actCountSql);
if ($actCountStmt) {
    if ($actTypes !== '') {
        $actCountStmt->bind_param($actTypes, ...$actParams);
    }
    $actCountStmt->execute();
    $activityTotal = (int)($actCountStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $actCountStmt->close();
}
$activityTotalPages = max(1, (int)ceil($activityTotal / $act_limit));

$actListSql = "
    SELECT
        ca.id,
        ca.certificate_id,
        ca.action,
        ca.created_at,
        u.fname,
        u.other_name,
        u.lname,
        u.email,
        u.student_no,
        c.name AS course_name,
        i.name AS intake_name,
        i.date_ending AS intake_date_ending," .
        ($hasBranches ? "
        b.name AS branch_name" : "
        NULL AS branch_name") . "
    FROM certificate_activity ca
    LEFT JOIN users u ON u.unique_id = ca.user_id
    LEFT JOIN courses c ON c.id = ca.course_id
    LEFT JOIN intakes i ON i.id = ca.intake_id" .
    ($hasBranches ? "
    LEFT JOIN branches b ON b.id = i.branch_id" : "") . "
    {$actWhereSql}
    ORDER BY ca.created_at DESC
    LIMIT ? OFFSET ?
";
$actListStmt = $conn->prepare($actListSql);
if ($actListStmt) {
    $listTypes = $actTypes . "ii";
    $listParams = array_merge($actParams, [$act_limit, $act_offset]);
    $actListStmt->bind_param($listTypes, ...$listParams);
    $actListStmt->execute();
    $actListRes = $actListStmt->get_result();
    while ($row = $actListRes->fetch_assoc()) {
        $activityRows[] = $row;
    }
    $actListStmt->close();
}

// Re-fetch dropdown result sets since they were already consumed above for the filter form.
$coursesForActivity = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
$intakesForActivity = $conn->query($intakeSelectSql);

function build_activity_link(int $course_id, int $intake_id, string $type, int $page): string {
    return "?view=recommend_certificates&act_course_id={$course_id}&act_intake_id={$intake_id}&act_type=" . urlencode($type) . "&act_page={$page}";
}

function build_student_link(int $course_id, int $intake_id, string $search, int $page): string {
    return "?view=recommend_certificates&course_id={$course_id}&intake_id={$intake_id}&search=" . urlencode($search) . "&page_no={$page}";
}
?>

<div class="page-heading">
    <div>
        <h2><i class="fa fa-certificate"></i> Recommend Students for <em>Certificates</em></h2>
        <div class="heading-meta">
            <span class="meta-pill"><i class="fa fa-house"></i> Home</span>
            <span class="meta-pill"><i class="fa fa-chevron-right"></i> Certificate Recommendations</span>
        </div>
    </div>
</div>

<?php if (isset($_SESSION[$systemname."successmsg"])): ?>
    <div class="app-alert app-alert-success"><i class="fa fa-circle-check"></i> <?= e($_SESSION[$systemname."successmsg"]); ?></div>
    <?php unset($_SESSION[$systemname."successmsg"]); ?>
<?php endif; ?>

<?php if (isset($_SESSION[$systemname."errormsg"])): ?>
    <div class="app-alert app-alert-danger"><i class="fa fa-triangle-exclamation"></i> <?= e($_SESSION[$systemname."errormsg"]); ?></div>
    <?php unset($_SESSION[$systemname."errormsg"]); ?>
<?php endif; ?>

<!-- ===================== TOP-LEVEL STAT CARDS ===================== -->
<div class="dash-stats stats-section">
    <div class="dash-stat">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Recommended</span>
            <span class="dash-stat-tag dst-gold"><?= ($course_id > 0 && $intake_id > 0) ? 'Filtered' : 'All'; ?></span>
        </div>
        <div class="dash-stat-num"><?= (int)$recCount; ?></div>
        <div class="dash-stat-sub">Students recommended for certification</div>
    </div>

    <div class="dash-stat">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Generated</span>
            <span class="dash-stat-tag dst-maroon"><?= ($course_id > 0 && $intake_id > 0) ? 'Filtered' : 'All'; ?></span>
        </div>
        <div class="dash-stat-num"><?= (int)$genCount; ?></div>
        <div class="dash-stat-sub">Certificates generated</div>
    </div>

    <div class="dash-stat">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Viewed</span>
            <span class="dash-stat-tag dst-info"><?= (int)$totalViews; ?> total</span>
        </div>
        <div class="dash-stat-num"><?= (int)$uniqueViewers; ?></div>
        <div class="dash-stat-sub">Unique students who viewed a certificate</div>
    </div>

    <div class="dash-stat">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Downloaded</span>
            <span class="dash-stat-tag dst-green"><?= (int)$totalDownloads; ?> total</span>
        </div>
        <div class="dash-stat-num"><?= (int)$uniqueDownloaders; ?></div>
        <div class="dash-stat-sub">Unique students who downloaded a certificate</div>
    </div>
</div>

<!-- ===================== RECOMMENDED PER COURSE ===================== -->
<div class="section-header">
    <h2><i class="fa fa-graduation-cap"></i> Recommended per Course</h2>
</div>

<?php if (empty($courseStats)): ?>
    <div class="empty">
        <i class="fa fa-graduation-cap"></i>
        <p>No recommendations or certificates recorded yet.</p>
    </div>
<?php else: ?>
    <div class="cert-course-grid mb-3">
        <?php foreach ($courseStats as $cs): ?>
            <div class="cert-course-card">
                <div class="cert-course-name"><?= e($cs['name']); ?></div>
                <div class="cert-chip-row">
                    <span class="cert-chip cert-chip-rec"><i class="fa fa-thumbs-up"></i> Recommended: <?= (int)$cs['recommended_count']; ?></span>
                    <span class="cert-chip cert-chip-gen"><i class="fa fa-certificate"></i> Generated: <?= (int)$cs['generated_count']; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ===================== STUDENT FILTER ===================== -->
<div class="section-header">
    <h2><i class="fa fa-filter"></i> Filter Students</h2>
</div>

<form method="get" class="controls-bar">
    <input type="hidden" name="view" value="recommend_certificates">

    <div class="ctrl-field">
        <label class="ctrl-label"><i class="fa fa-book"></i> Course</label>
        <select name="course_id" class="ctrl-select" required>
            <option value="">Select Course</option>
            <?php if ($courses): ?>
                <?php while ($c = $courses->fetch_assoc()): ?>
                    <option value="<?= (int)$c['id']; ?>" <?= $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?= e($c['name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="ctrl-field">
        <label class="ctrl-label"><i class="fa fa-calendar"></i> Intake</label>
        <select name="intake_id" class="ctrl-select cert-select" required>
            <option value="">Select Intake</option>
            <?php if ($intakes): ?>
                <?php while ($i = $intakes->fetch_assoc()): ?>
                    <option value="<?= (int)$i['id']; ?>" <?= $intake_id === (int)$i['id'] ? 'selected' : ''; ?>>
                        <?= e(rc_intake_label($i)); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="ctrl-field">
        <label class="ctrl-label"><i class="fa fa-magnifying-glass"></i> Search Student</label>
        <div class="search-wrap">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" name="search" value="<?= e($search); ?>" placeholder="Name, email, or student number">
        </div>
    </div>

    <div class="ctrl-field">
        <label class="ctrl-label">&nbsp;</label>
        <button type="submit" class="btn-export"><i class="fa fa-magnifying-glass"></i> Load</button>
    </div>
</form>

<?php if ($course_id > 0 && $intake_id > 0): ?>
    <div class="tbl-card mb-3">
        <div class="tbl-toolbar">
            <span class="tbl-title"><i class="fa fa-users"></i> Student Assignment Performance</span>
            <span class="count-pill"><?= (int)$totalRows; ?> record<?= $totalRows == 1 ? '' : 's'; ?></span>
        </div>

        <?php if (empty($students)): ?>
            <div class="empty">
                <i class="fa fa-user-slash"></i>
                <p>No students found for selected course/intake/search.</p>
            </div>
        <?php else: ?>
            <form method="post" action="scripts/process_certificate_recommendations.php">
                <input type="hidden" name="course_id" value="<?= (int)$course_id; ?>">
                <input type="hidden" name="intake_id" value="<?= (int)$intake_id; ?>">

                <div class="modal-body" style="padding: 16px 20px 0;">
                    <div class="ctrl-field cert-reason-field" style="max-width: 640px;">
                        <label class="ctrl-label"><i class="fa fa-pen"></i> Recommendation Reason</label>
                        <textarea name="reason" class="form-input" rows="3" required>Recommended based on assignment performance.</textarea>
                    </div>
                </div>

                <div class="tbl-scroll">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="cert-check-col"><input type="checkbox" id="checkAll"></th>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Total Assignments</th>
                                <th>Submitted</th>
                                <th>Submission Rate</th>
                                <th>Average Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($students as $s): ?>
                            <?php
                                $total = (int)($s['total_assignments'] ?? 0);
                                $submitted = (int)($s['submitted_assignments'] ?? 0);
                                $avg = is_null($s['avg_score']) ? 0 : (float)$s['avg_score'];
                                $rate = $total > 0 ? round(($submitted / $total) * 100, 1) : 0;

                                $hasCert = !empty($s['certificate_id']);
                                $recommended = !empty($s['recommendation_status']);

                                if ($hasCert) {
                                    $badge = "<span class='s-badge s-class'><i class='fa fa-certificate'></i> Certificate Generated</span>";
                                } elseif ($recommended) {
                                    $badge = "<span class='s-badge s-submitted'><i class='fa fa-thumbs-up'></i> " . e($s['recommendation_status']) . "</span>";
                                } else {
                                    $badge = "<span class='s-badge s-cert-pending'><i class='fa fa-clock'></i> Pending</span>";
                                }

                                if ($avg >= 70) {
                                    $scoreClass = 's-graded';
                                } elseif ($avg >= 50) {
                                    $scoreClass = 's-submitted';
                                } else {
                                    $scoreClass = 's-pending';
                                }
                            ?>
                            <tr>
                                <td class="cert-check-col">
                                    <?php if (!$hasCert): ?>
                                        <input type="checkbox" name="students[]" value="<?= e($s['user_id']); ?>">
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="cell-title"><?= e(trim(implode(' ', array_filter([
                                        trim((string)($s['fname'] ?? '')),
                                        trim((string)($s['other_name'] ?? '')),
                                        trim((string)($s['lname'] ?? '')),
                                    ], static fn($part) => $part !== '')))); ?></div>
                                    <span class="cell-sub-id"><?= e($s['student_no'] ?? ''); ?></span>
                                </td>

                                <td class="cell-muted"><?= e($s['email'] ?? ''); ?></td>
                                <td class="cell-num"><?= $total; ?></td>
                                <td class="cell-num"><?= $submitted; ?></td>
                                <td class="cell-num"><?= number_format($rate, 1); ?>%</td>

                                <td>
                                    <span class="s-badge <?= $scoreClass; ?>"><?= number_format($avg, 2); ?>%</span>
                                </td>

                                <td>
                                    <div class="row-actions" style="flex-wrap:wrap;gap:6px;">
                                        <?= $badge; ?>
                                        <?php if ($hasCert): ?>
                                            <?php
                                                $certId   = (int)$s['certificate_id'];
                                                $certName = trim(implode(' ', array_filter([
                                                    trim((string)($s['fname'] ?? '')),
                                                    trim((string)($s['other_name'] ?? '')),
                                                    trim((string)($s['lname'] ?? '')),
                                                ], static fn($part) => $part !== '')));
                                            ?>
                                            <button type="button" class="ia-btn ia-view js-cert-preview" title="Preview certificate"
                                                    data-url="certificate.php?id=<?= $certId; ?>&amp;action=view"
                                                    data-download="certificate.php?id=<?= $certId; ?>&amp;action=download"
                                                    data-name="<?= e($certName); ?>">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <a class="ia-btn ia-dl" title="Download certificate"
                                               href="certificate?id=<?= $certId; ?>&amp;action=download">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pag-bar">
                        <div class="pag-info">
                            <i class="fa fa-list"></i> Page <?= $page; ?> of <?= $totalPages; ?>
                        </div>
                        <div class="pag-buttons">
                            <a class="pg-arrow <?= $page <= 1 ? 'disabled' : ''; ?>"
                               href="<?= build_student_link($course_id, $intake_id, $search, max(1, $page - 1)); ?>">
                                <i class="fa fa-chevron-left"></i> Prev
                            </a>

                            <?php
                            $start = max(1, $page - 3);
                            $end = min($totalPages, $page + 3);
                            ?>

                            <?php if ($start > 1): ?>
                                <a class="pg-num" href="<?= build_student_link($course_id, $intake_id, $search, 1); ?>">1</a>
                                <?php if ($start > 2): ?><span class="pg-ellipsis">&hellip;</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <a class="pg-num <?= $p == $page ? 'active' : ''; ?>"
                                   href="<?= build_student_link($course_id, $intake_id, $search, $p); ?>"><?= $p; ?></a>
                            <?php endfor; ?>

                            <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?><span class="pg-ellipsis">&hellip;</span><?php endif; ?>
                                <a class="pg-num" href="<?= build_student_link($course_id, $intake_id, $search, $totalPages); ?>"><?= $totalPages; ?></a>
                            <?php endif; ?>

                            <a class="pg-arrow <?= $page >= $totalPages ? 'disabled' : ''; ?>"
                               href="<?= build_student_link($course_id, $intake_id, $search, min($totalPages, $page + 1)); ?>">
                                Next <i class="fa fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal-body" style="padding: 0 20px 20px;">
                    <div class="cert-form-actions">
                        <button type="submit" name="recommend_only" class="btn-cert-recommend">
                            <i class="fa fa-thumbs-up"></i> Recommend Selected
                        </button>

                        <button type="submit" name="recommend_and_generate" class="btn-cert-generate"
                                onclick="return confirm('Generate certificates for selected students now?');">
                            <i class="fa fa-certificate"></i> Recommend &amp; Generate Certificates
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ===================== CERTIFICATE ACTIVITY ===================== -->
<div class="section-header">
    <h2><i class="fa fa-eye"></i> Certificate Activity &mdash; Views &amp; Downloads</h2>
</div>

<form method="get" class="controls-bar">
    <input type="hidden" name="view" value="recommend_certificates">

    <div class="ctrl-field">
        <label class="ctrl-label"><i class="fa fa-book"></i> Course</label>
        <select name="act_course_id" class="ctrl-select">
            <option value="">All Courses</option>
            <?php if ($coursesForActivity): ?>
                <?php while ($c = $coursesForActivity->fetch_assoc()): ?>
                    <option value="<?= (int)$c['id']; ?>" <?= $act_course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?= e($c['name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="ctrl-field">
        <label class="ctrl-label"><i class="fa fa-calendar"></i> Intake</label>
        <select name="act_intake_id" class="ctrl-select cert-select">
            <option value="">All Intakes</option>
            <?php if ($intakesForActivity): ?>
                <?php while ($i = $intakesForActivity->fetch_assoc()): ?>
                    <option value="<?= (int)$i['id']; ?>" <?= $act_intake_id === (int)$i['id'] ? 'selected' : ''; ?>>
                        <?= e(rc_intake_label($i)); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="ctrl-field">
        <label class="ctrl-label"><i class="fa fa-arrow-right-arrow-left"></i> Action</label>
        <select name="act_type" class="ctrl-select">
            <option value="">View + Download</option>
            <option value="view" <?= $act_type === 'view' ? 'selected' : ''; ?>>Viewed Only</option>
            <option value="download" <?= $act_type === 'download' ? 'selected' : ''; ?>>Downloaded Only</option>
        </select>
    </div>

    <div class="ctrl-field">
        <label class="ctrl-label">&nbsp;</label>
        <button type="submit" class="btn-export"><i class="fa fa-filter"></i> Filter Activity</button>
    </div>
</form>

<?php if (empty($activityRows)): ?>
    <div class="empty">
        <i class="fa fa-eye-slash"></i>
        <p>
            No certificate view/download activity recorded<?= ($act_course_id || $act_intake_id || $act_type) ? ' for this filter' : ' yet'; ?>.
        </p>
        <span class="cert-empty-hint">Activity is logged automatically from <code>certificate.php</code> whenever a student views or downloads a certificate.</span>
    </div>
<?php else: ?>
    <div class="tbl-card">
        <div class="tbl-scroll">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Intake</th>
                        <th>Branch</th>
                        <th>Intake Ends</th>
                        <th>Action</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activityRows as $a): ?>
                        <tr>
                            <td>
                                <div class="cell-title"><?= e(trim(implode(' ', array_filter([
                                    trim((string)($a['fname'] ?? '')),
                                    trim((string)($a['other_name'] ?? '')),
                                    trim((string)($a['lname'] ?? '')),
                                ], static fn($part) => $part !== '')))); ?></div>
                                <span class="cell-sub-id"><?= e($a['student_no'] ?? ''); ?></span>
                            </td>
                            <td class="cell-muted"><?= e($a['email'] ?? ''); ?></td>
                            <td><?= e($a['course_name'] ?? '-'); ?></td>
                            <td><?= e($a['intake_name'] ?? '-'); ?></td>
                            <td class="cell-muted"><?= e($a['branch_name'] ?? '-'); ?></td>
                            <td class="cell-muted">
                                <?= !empty($a['intake_date_ending'])
                                    ? e(date('d M Y', strtotime((string)$a['intake_date_ending'])))
                                    : '-'; ?>
                            </td>
                            <td>
                                <?php if ($a['action'] === 'view'): ?>
                                    <span class="s-badge s-viewed"><i class="fa fa-eye"></i> Viewed</span>
                                <?php else: ?>
                                    <span class="s-badge s-downloaded"><i class="fa fa-download"></i> Downloaded</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-muted"><?= e(date('d M Y, H:i', strtotime($a['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($activityTotalPages > 1): ?>
            <div class="pag-bar">
                <div class="pag-info">
                    <i class="fa fa-list"></i> Page <?= $act_page; ?> of <?= $activityTotalPages; ?>
                </div>
                <div class="pag-buttons">
                    <a class="pg-arrow <?= $act_page <= 1 ? 'disabled' : ''; ?>"
                       href="<?= build_activity_link($act_course_id, $act_intake_id, $act_type, max(1, $act_page - 1)); ?>">
                        <i class="fa fa-chevron-left"></i> Prev
                    </a>

                    <?php
                    $aStart = max(1, $act_page - 3);
                    $aEnd = min($activityTotalPages, $act_page + 3);
                    ?>

                    <?php if ($aStart > 1): ?>
                        <a class="pg-num" href="<?= build_activity_link($act_course_id, $act_intake_id, $act_type, 1); ?>">1</a>
                        <?php if ($aStart > 2): ?><span class="pg-ellipsis">&hellip;</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $aStart; $p <= $aEnd; $p++): ?>
                        <a class="pg-num <?= $p == $act_page ? 'active' : ''; ?>"
                           href="<?= build_activity_link($act_course_id, $act_intake_id, $act_type, $p); ?>"><?= $p; ?></a>
                    <?php endfor; ?>

                    <?php if ($aEnd < $activityTotalPages): ?>
                        <?php if ($aEnd < $activityTotalPages - 1): ?><span class="pg-ellipsis">&hellip;</span><?php endif; ?>
                        <a class="pg-num" href="<?= build_activity_link($act_course_id, $act_intake_id, $act_type, $activityTotalPages); ?>"><?= $activityTotalPages; ?></a>
                    <?php endif; ?>

                    <a class="pg-arrow <?= $act_page >= $activityTotalPages ? 'disabled' : ''; ?>"
                       href="<?= build_activity_link($act_course_id, $act_intake_id, $act_type, min($activityTotalPages, $act_page + 1)); ?>">
                        Next <i class="fa fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ===================== CERTIFICATE PREVIEW MODAL ===================== -->
<div class="modal-overlay" id="certPreviewModal" aria-hidden="true">
    <div class="modal-box" style="max-width:1000px;height:90vh;">
        <div class="modal-head">
            <h3><i class="fa fa-certificate"></i> <span id="certPreviewTitle">Certificate Preview</span></h3>
            <button type="button" class="modal-close" id="certPreviewClose" title="Close"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:0;background:#525659;">
            <iframe id="certPreviewFrame" title="Certificate preview" style="width:100%;height:100%;min-height:60vh;border:0;display:block;"></iframe>
        </div>
        <div class="modal-foot">
            <a id="certPreviewNewTab" class="btn-modal-cancel" href="#" target="_blank" rel="noopener"><i class="fa fa-up-right-from-square"></i> Open in new tab</a>
            <a id="certPreviewDownload" class="btn-export" href="#"><i class="fa fa-download"></i> Download</a>
        </div>
    </div>
</div>

<script>
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('input[name="students[]"]').forEach(cb => {
        cb.checked = this.checked;
    });
});

(function () {
    const modal = document.getElementById('certPreviewModal');
    const frame = document.getElementById('certPreviewFrame');
    if (!modal || !frame) return;

    function closePreview() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        frame.src = 'about:blank';
    }

    document.querySelectorAll('.js-cert-preview').forEach(btn => {
        btn.addEventListener('click', () => {
            const name = btn.dataset.name || '';
            document.getElementById('certPreviewTitle').textContent = 'Certificate Preview' + (name ? ' — ' + name : '');
            document.getElementById('certPreviewNewTab').href = btn.dataset.url;
            document.getElementById('certPreviewDownload').href = btn.dataset.download;
            frame.src = btn.dataset.url;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.getElementById('certPreviewClose').addEventListener('click', closePreview);
    modal.addEventListener('click', e => { if (e.target === modal) closePreview(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('is-open')) closePreview(); });
})();
</script>