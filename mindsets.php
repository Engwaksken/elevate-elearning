<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/scripts/connector.php';

date_default_timezone_set('Africa/Nairobi');

$systemname  = "Elevate Her 360";
$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$role        = $_SESSION[$systemname . "letype"] ?? '';

if ($user_unique === '') {
    header("Location: login");
    exit;
}

$adminRoles = ['sabrtw', 'xybrtw', 'xtfbry'];
if (!in_array($role, $adminRoles, true)) {
    header("Location: ./");
    exit;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;

    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$stmt) return false;

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();

    return $ok;
}

$module_titles = [
    1 => 'Who am I beyond my circumstances?',
    2 => 'What do I actually believe about money and work?',
    3 => 'Where am I going and why does it matter?',
    4 => 'What do I do when things get hard?',
    5 => 'My first earning experience',
    6 => 'I am an earner - what is next?',
];

$courseFilter = (int)($_GET['course_id'] ?? 0);
if (in_array($courseFilter, [4,7], true)) {
    $courseFilter = 0;
}
$intakeFilter = (int)($_GET['intake_id'] ?? 0);

$hasCourseReg = table_exists($conn, 'course_registration');
$hasCourses   = table_exists($conn, 'courses');
$hasIntakes   = table_exists($conn, 'intakes');

/* Your actual schema */
$crUserCol   = 'user_id';
$crCourseCol = 'course_id';
$crIntakeCol = 'intake';

/* =========================================================
   Load courses
========================================================= */

$courses = [];

if ($hasCourses) {
    $orderBy = column_exists($conn, 'courses', 'sort_order')
        ? "sort_order ASC, name ASC"
        : "name ASC";

  $res = $conn->query("
    SELECT id, name, short_code
    FROM courses
    WHERE id NOT IN (4,7)
      AND status = 'active'
    ORDER BY {$orderBy}
");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $courses[] = $row;
        }
    }
}

/* =========================================================
   Load intakes
========================================================= */

$intakes = [];

if ($hasIntakes) {
    $res = $conn->query("
        SELECT id, name, date_launching, date_ending
        FROM intakes
        ORDER BY date_launching DESC, id DESC
    ");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $label = trim((string)($row['name'] ?? ''));

            if ($label === '') {
                $from = !empty($row['date_launching'])
                    ? date('d M Y', strtotime($row['date_launching']))
                    : '';

                $to = !empty($row['date_ending'])
                    ? date('d M Y', strtotime($row['date_ending']))
                    : '';

                $label = trim($from . ($to !== '' ? ' - ' . $to : ''));
            }

            $row['intake_label'] = $label !== '' ? $label : 'Intake #' . $row['id'];
            $intakes[] = $row;
        }
    }
}

/* fallback from course_registration */
if (!$intakes && $hasCourseReg) {
    $res = $conn->query("
        SELECT DISTINCT cr.intake AS id,
               CONCAT('Intake #', cr.intake) AS intake_label
        FROM course_registration cr
        WHERE cr.intake IS NOT NULL
          AND cr.intake != ''
        ORDER BY cr.intake DESC
    ");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $intakes[] = $row;
        }
    }
}

/* =========================================================
   Filters
========================================================= */

$whereParts = [];
$params = [];
$types = '';



if ($courseFilter > 0 && $hasCourseReg) {
    $whereParts[] = "
        EXISTS (
            SELECT 1
            FROM course_registration crf
            LEFT JOIN users ux
                ON (
                    ux.unique_id = mp.user_unique
                    OR CAST(ux.id AS CHAR) = CAST(mp.user_unique AS CHAR)
                )
            WHERE (
                crf.user_id = mp.user_unique
                OR crf.user_id = ux.unique_id
                OR CAST(crf.user_id AS CHAR) = CAST(ux.id AS CHAR)
            )
            AND crf.course_id = ?
        )
    ";
    $params[] = $courseFilter;
    $types .= 'i';
}
if ($courseFilter > 0 && $hasCourseReg) {
    $whereParts[] = "
        EXISTS (
            SELECT 1
            FROM course_registration crf
            LEFT JOIN users ux
                ON (
                    ux.unique_id = mp.user_unique
                    OR CAST(ux.id AS CHAR) = CAST(mp.user_unique AS CHAR)
                )
            WHERE (
                crf.user_id = mp.user_unique
                OR crf.user_id = ux.unique_id
                OR CAST(crf.user_id AS CHAR) = CAST(ux.id AS CHAR)
            )
            AND crf.course_id = ?
            AND crf.course_id NOT IN (4, 7)
        )
    ";
    $params[] = $courseFilter;
    $types .= 'i';
}
$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

/* =========================================================
   Stats
========================================================= */

$statsSql = "
    SELECT
        COUNT(DISTINCT mp.user_unique) AS total_students,
        COALESCE(SUM(mp.status = 'completed'), 0) AS total_completed,
        COALESCE(SUM(mp.status = 'in_progress'), 0) AS total_in_progress,
        COALESCE(SUM(mp.points), 0) AS total_points
    FROM mindset_progress mp
    LEFT JOIN users u
        ON (
            u.unique_id = mp.user_unique
            OR CAST(u.id AS CHAR) = CAST(mp.user_unique AS CHAR)
        )
    $whereSql
";

$stmt = $conn->prepare($statsSql);

if ($stmt && $params) {
    $stmt->bind_param($types, ...$params);
}

$statsRow = [
    'total_students' => 0,
    'total_completed' => 0,
    'total_in_progress' => 0,
    'total_points' => 0,
];

if ($stmt) {
    $stmt->execute();
    $statsRow = $stmt->get_result()->fetch_assoc() ?: $statsRow;
    $stmt->close();
}

/* =========================================================
   Course / intake display joins
========================================================= */

$courseJoin = '';
$courseSelect = "'-' AS course_names";
$intakeSelect = "'-' AS intake_names";

if ($hasCourseReg) {
    $courseJoin = "
        LEFT JOIN course_registration cr
            ON (
                cr.user_id = mp.user_unique
                OR cr.user_id = u.unique_id
                OR CAST(cr.user_id AS CHAR) = CAST(u.id AS CHAR)
            )
    ";

    if ($hasCourses) {
        $courseJoin .= "
           LEFT JOIN courses c
    ON c.id = cr.course_id
   AND c.id NOT IN (4,7)
        ";

        $courseSelect = "
            COALESCE(
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
                '-'
            ) AS course_names
        ";
    } else {
        $courseSelect = "
            COALESCE(
                GROUP_CONCAT(DISTINCT CONCAT('Course #', cr.course_id) ORDER BY cr.course_id SEPARATOR ', '),
                '-'
            ) AS course_names
        ";
    }

    if ($hasIntakes) {
        $courseJoin .= "
            LEFT JOIN intakes i
                ON i.id = cr.intake
        ";

        $intakeSelect = "
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT
                    CASE
                        WHEN i.name IS NOT NULL AND i.name != '' THEN i.name
                        WHEN i.date_launching IS NOT NULL AND i.date_ending IS NOT NULL THEN CONCAT(i.date_launching, ' - ', i.date_ending)
                        WHEN i.date_launching IS NOT NULL THEN i.date_launching
                        ELSE CONCAT('Intake #', i.id)
                    END
                    ORDER BY i.id DESC SEPARATOR ', '
                ),
                '-'
            ) AS intake_names
        ";
    } else {
        $intakeSelect = "
            COALESCE(
                GROUP_CONCAT(DISTINCT CONCAT('Intake #', cr.intake) ORDER BY cr.intake DESC SEPARATOR ', '),
                '-'
            ) AS intake_names
        ";
    }
}

/* =========================================================
   Answers
========================================================= */

$answersSql = "
    SELECT
        mp.user_unique,
        TRIM(CONCAT_WS(' ', u.fname, u.lname)) AS student_name,
        u.email AS student_email,
        $courseSelect,
        $intakeSelect,

        COUNT(DISTINCT mp.id) AS total_modules_started,
        COALESCE(SUM(mp.status = 'completed'), 0) AS completed_modules,
        COALESCE(SUM(mp.status = 'in_progress'), 0) AS in_progress_modules,
        COALESCE(SUM(mp.points), 0) AS total_points,
        MAX(mp.updated_at) AS last_updated,

        JSON_ARRAYAGG(
            JSON_OBJECT(
                'progress_id', mp.id,
                'module_id', mp.module_id,
                'status', mp.status,
                'points', mp.points,
                'step_index', mp.step_index,
                'responses', mp.responses,
                'completed_at', mp.completed_at,
                'updated_at', mp.updated_at
            )
        ) AS modules_json

    FROM mindset_progress mp
    LEFT JOIN users u
        ON (
            u.unique_id = mp.user_unique
            OR CAST(u.id AS CHAR) = CAST(mp.user_unique AS CHAR)
        )
    $courseJoin
    $whereSql
    GROUP BY
        mp.user_unique,
        student_name,
        student_email
    ORDER BY last_updated DESC
";

$stmt = $conn->prepare($answersSql);

if ($stmt && $params) {
    $stmt->bind_param($types, ...$params);
}

$answersResult = null;

if ($stmt) {
    $stmt->execute();
    $answersResult = $stmt->get_result();
} else {
    echo "<div class='alert alert-danger'>SQL Error: " . h($conn->error) . "</div>";
}

/* =========================================================
   Question counts
========================================================= */

$qCountsRaw = $conn->query("
    SELECT module_id, COUNT(*) AS cnt
    FROM mindset_questions
    WHERE is_active = 1
    GROUP BY module_id
");

$qCounts = [];

if ($qCountsRaw) {
    while ($r = $qCountsRaw->fetch_assoc()) {
        $qCounts[(int)$r['module_id']] = (int)$r['cnt'];
    }
}
?>

<link rel="stylesheet" href="app.css">

<div class="msa-page">

    <div class="msa-header">
        <div class="msa-header-left">
            <h1><i class="fa-solid fa-seedling"></i> Mindset Curriculum - Admin</h1>
            <p>Manage participant progress, answers, and curriculum questions for all 6 modules.</p>
        </div>

        <div class="msa-header-right">
            <span class="ms-badge" style="background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.18)">
                <i class="fa fa-users"></i> <?= (int)($statsRow['total_students'] ?? 0) ?> students
            </span>

            <span class="ms-badge" style="background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.18)">
                <i class="fa fa-check-circle"></i> <?= (int)($statsRow['total_completed'] ?? 0) ?> completions
            </span>
        </div>
    </div>

    <div class="msa-page-tabs">
        <button class="msa-page-tab msa-active" onclick="switchTab('answers', this)">
            <i class="fa fa-table-list"></i> Participant Answers
            <span class="msa-tab-pill" id="answersPill"><?= $answersResult ? (int)$answersResult->num_rows : 0 ?></span>
        </button>

        <button class="msa-page-tab" onclick="switchTab('questions', this)">
            <i class="fa fa-list-check"></i> Manage Questions
            <span class="msa-tab-pill" id="questionsPill"><?= array_sum($qCounts) ?></span>
        </button>
    </div>

    <div id="tab-answers" class="msa-tab-panel msa-active">

        <form method="GET" class="msa-toolbar">
            <div class="msa-toolbar-left">
                <div class="search-wrap">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="text" id="searchInput"
                           placeholder="Search participant, email, course, intake, module..."
                           oninput="filterAnswers()">
                </div>

                <div class="ctrl-field">
                    <select class="ctrl-select" id="statusFilter" onchange="filterAnswers()">
                        <option value="">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="in_progress">In Progress</option>
                    </select>
                </div>

                <div class="ctrl-field">
                    <select class="ctrl-select" id="moduleFilter" onchange="filterAnswers()">
                        <option value="">All Modules</option>
                        <?php for ($m = 1; $m <= 6; $m++): ?>
                            <option value="<?= $m ?>">Module <?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="ctrl-field">
                    <select class="ctrl-select" name="course_id" onchange="this.form.submit()">
                        <option value="0">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int)$course['id'] ?>" <?= $courseFilter === (int)$course['id'] ? 'selected' : '' ?>>
                                <?= h($course['name']) ?><?= !empty($course['short_code']) ? ' (' . h($course['short_code']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ctrl-field">
                    <select class="ctrl-select" name="intake_id" onchange="this.form.submit()">
                        <option value="0">All Intakes</option>
                        <?php foreach ($intakes as $intake): ?>
                            <option value="<?= (int)$intake['id'] ?>" <?= $intakeFilter === (int)$intake['id'] ? 'selected' : '' ?>>
                                <?= h($intake['intake_label'] ?? ('Intake #' . $intake['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($courseFilter > 0 || $intakeFilter > 0): ?>
                    <a href="mindsets" class="btn-modal-cancel" style="text-decoration:none;">
                        <i class="fa fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>

            <div class="msa-toolbar-right">
                <span class="pag-info" id="answersCount">
                    <i class="fa fa-circle-info"></i>
                    <span></span>
                </span>
            </div>
        </form>

        <div class="msa-tbl-card">
            <div class="msa-tbl-head">
                <span class="msa-tbl-title">
                    <i class="fa fa-table"></i> Participant Progress & Responses
                </span>
            </div>

            <div class="msa-tbl-scroll">
                <table class="app-table" id="answersTable">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Participant</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Intake</th>
                        <th>Modules Progress</th>
                        <th>Completed</th>
                        <th>In Progress</th>
                        <th>Total Points</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if ($answersResult && $answersResult->num_rows > 0): ?>
                        <?php
                        $rowNum = 0;

                        while ($row = $answersResult->fetch_assoc()):
                            $rowNum++;

                            $studentUniqueRaw = trim((string)($row['user_unique'] ?? ''));
                            $studentUnique    = h($studentUniqueRaw !== '' ? $studentUniqueRaw : '-');

                            $studentNameRaw = trim((string)($row['student_name'] ?? ''));
                            $studentNameRaw = $studentNameRaw !== ''
                                ? $studentNameRaw
                                : ($studentUniqueRaw !== '' ? $studentUniqueRaw : 'Unknown participant');

                            $studentName  = h($studentNameRaw);
                            $studentEmail = h((string)($row['student_email'] ?? '-'));
                            $courseNames  = h((string)($row['course_names'] ?? '-'));
                            $intakeNames  = h((string)($row['intake_names'] ?? '-'));

                            $modules = [];
                            if (!empty($row['modules_json'])) {
                                $decoded = json_decode((string)$row['modules_json'], true);
                                if (is_array($decoded)) {
                                    $modules = $decoded;
                                }
                            }

                          usort($modules, function ($a, $b) {
    $dateA = strtotime((string)($a['updated_at'] ?? $a['completed_at'] ?? '')) ?: 0;
    $dateB = strtotime((string)($b['updated_at'] ?? $b['completed_at'] ?? '')) ?: 0;

    if ($dateA === $dateB) {
        return ((int)($b['module_id'] ?? 0)) <=> ((int)($a['module_id'] ?? 0));
    }

    return $dateB <=> $dateA;
});

$latestModule = $modules[0] ?? null;

$allModulesSorted = $modules;
usort($allModulesSorted, function ($a, $b) {
    return ((int)($a['module_id'] ?? 0)) <=> ((int)($b['module_id'] ?? 0));
});

                            $rowSearchText = strtolower(
                                $studentNameRaw . ' ' .
                                ($row['student_email'] ?? '') . ' ' .
                                ($row['course_names'] ?? '') . ' ' .
                                ($row['intake_names'] ?? '') . ' ' .
                                json_encode($modules)
                            );

                            $moduleIds = array_map(fn($m) => (string)((int)($m['module_id'] ?? 0)), $modules);
                            $statuses  = array_map(fn($m) => (string)($m['status'] ?? ''), $modules);

                           $modulesJson = h(json_encode($allModulesSorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        ?>
                            <tr data-search="<?= h($rowSearchText) ?>"
                                data-modules="<?= h(implode(',', $moduleIds)) ?>"
                                data-statuses="<?= h(implode(',', $statuses)) ?>">

                                <td class="cell-num"><?= $rowNum ?></td>

                                <td>
                                    <div style="font-weight:600;font-size:1.5rem"><?= $studentName ?></div>
                                    <?php if ($studentUniqueRaw !== ''): ?>
                                        <div style="font-size:1.4rem;color:var(--muted)">
                                            ID: <?= $studentUnique ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td style="font-size:1.4rem"><?= $studentEmail ?></td>

                                <td style="font-size:1.4rem;min-width:160px;"><?= $courseNames ?></td>

                                <td style="font-size:1.4rem;min-width:130px;"><?= $intakeNames ?></td>

                               <td>
    <?php if ($latestModule): ?>
        <?php
        $mid        = (int)($latestModule['module_id'] ?? 0);
        $status     = (string)($latestModule['status'] ?? 'in_progress');
        $badgeClass = $status === 'completed' ? 's-graded' : 's-submitted';
        $badgeText  = $status === 'completed' ? 'Completed' : 'In Progress';
        $title      = $module_titles[$mid] ?? ('Module ' . $mid);
        ?>
        <div class="module-progress-line latest-module-line">
            <strong>Latest: Module <?= $mid ?></strong>
            <span class="s-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            <small>
                Step <?= (int)($latestModule['step_index'] ?? 0) ?> |
                <?= (int)($latestModule['points'] ?? 0) ?> pts
            </small>
            <div style="font-size:1.2rem;color:var(--muted)">
                <?= h($title) ?>
            </div>
            <div style="font-size:1.1rem;color:var(--muted);margin-top:4px;">
                Updated: <?= h($latestModule['updated_at'] ?? '-') ?>
            </div>
        </div>
    <?php else: ?>
        <span style="color:var(--muted)">No module progress</span>
    <?php endif; ?>
</td>
                                <td class="cell-num"><?= (int)$row['completed_modules'] ?></td>

                                <td class="cell-num"><?= (int)$row['in_progress_modules'] ?></td>

                                <td style="font-family:var(--font-serif);font-size:1rem;color:var(--gold)">
                                    <?= (int)$row['total_points'] ?>
                                </td>

                                <td style="font-size:1.4rem;color:var(--muted);white-space:nowrap">
                                    <?= h($row['last_updated'] ?? '-') ?>
                                </td>

                                <td>
                                    <button type="button"
                                            class="ia-btn ia-view"
                                            onclick="openStudentModulesModal(this)"
                                            data-student="<?= $studentName ?>"
                                            data-email="<?= $studentEmail ?>"
                                            data-user="<?= $studentUnique ?>"
                                            data-modules="<?= $modulesJson ?>">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align:center;padding:48px 24px;color:var(--muted)">
                                <i class="fa fa-inbox" style="font-size:2rem;opacity:.15;display:block;margin-bottom:8px"></i>
                                No mindset responses found for the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="tab-questions" class="msa-tab-panel">

        <div class="msa-q-layout">

            <aside class="msa-module-sidebar">
                <div class="msa-module-sidebar-head">
                    <h3><i class="fa fa-layer-group"></i> Modules</h3>
                </div>

                <ul class="msa-module-nav" id="moduleSidebarNav">
                    <?php foreach ($module_titles as $mid => $mtitle): ?>
                        <li>
                            <button class="msa-module-nav-btn <?= $mid === 1 ? 'msa-active' : '' ?>"
                                    onclick="loadModule(<?= $mid ?>, this)"
                                    data-module="<?= $mid ?>">
                                <i class="fa fa-chevron-right"></i>
                                <span class="msa-nav-num"><?= $mid ?></span>
                                <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.76rem">
                                    <?= h(mb_substr($mtitle, 0, 28)) . (mb_strlen($mtitle) > 28 ? '...' : '') ?>
                                </span>
                                <span class="count-pill" id="sidebarCount<?= $mid ?>">
                                    <?= $qCounts[$mid] ?? 0 ?>
                                </span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <section class="msa-q-main">
                <div class="msa-q-main-head">
                    <span class="msa-q-main-title" id="qPanelTitle">
                        <i class="fa fa-list-check"></i>
                        <span>Module 1 - Questions</span>
                    </span>

                    <button class="btn-export" onclick="openAddModal()">
                        <i class="fa fa-plus"></i> Add Question
                    </button>
                </div>

                <div id="qLoadingSkeleton" style="padding:14px">
                    <?php for ($s = 0; $s < 4; $s++): ?>
                        <div class="msa-skeleton-card">
                            <span class="msa-skeleton" style="width:60px;margin-bottom:8px"></span>
                            <span class="msa-skeleton" style="width:80%"></span>
                            <span class="msa-skeleton" style="width:50%;margin-top:6px"></span>
                        </div>
                    <?php endfor; ?>
                </div>

                <div id="qList" class="msa-q-list" style="display:none"></div>
            </section>

        </div>
    </div>

</div>

<div class="msa-overlay" id="responseOverlay" onclick="overlayClose('responseOverlay', event)">
    <div class="msa-modal" style="max-width:950px">
        <div class="msa-modal-head">
            <h3><i class="fa fa-eye"></i> Participant Module Responses</h3>
            <button class="msa-modal-close" onclick="closeModal('responseOverlay')">
                <i class="fa fa-xmark"></i>
            </button>
        </div>

        <div class="msa-modal-body">
            <div class="response-summary-grid">
                <div><strong>Participant:</strong> <span id="rvStudent"></span></div>
                <div><strong>Email:</strong> <span id="rvEmail"></span></div>
                <div><strong>Course:</strong> <span id="rvUser"></span></div>
            </div>

            <hr style="margin:18px 0;border:0;border-top:1px solid rgba(0,0,0,.08)">

            <div id="rvResponses" class="response-view-list"></div>
        </div>

        <div class="msa-modal-foot">
            <button class="btn-modal-cancel" onclick="closeModal('responseOverlay')">Close</button>
        </div>
    </div>
</div>

<div class="msa-overlay" id="qFormOverlay" onclick="overlayClose('qFormOverlay', event)">
    <div class="msa-modal">
        <div class="msa-modal-head">
            <h3 id="qFormTitle"><i class="fa fa-pencil"></i> Add Question</h3>
            <button class="msa-modal-close" onclick="closeModal('qFormOverlay')">
                <i class="fa fa-xmark"></i>
            </button>
        </div>

        <div class="msa-modal-body" id="qFormBody">
            <input type="hidden" id="qId">

            <div class="msa-field-row">
                <div class="msa-field">
                    <label class="msa-label"><i class="fa fa-layer-group"></i> Module <span class="req">*</span></label>
                    <select class="msa-select" id="qModuleId">
                        <?php foreach ($module_titles as $mid => $mtitle): ?>
                            <option value="<?= $mid ?>">Module <?= $mid ?> - <?= h(mb_substr($mtitle, 0, 35)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="msa-field">
                    <label class="msa-label"><i class="fa fa-sort"></i> Display Order</label>
                    <input class="msa-input" type="number" id="qStepOrder" min="0" max="999" placeholder="e.g. 10">
                </div>
            </div>

            <div class="msa-field">
                <label class="msa-label"><i class="fa fa-shapes"></i> Step Type <span class="req">*</span></label>
                <select class="msa-select" id="qStepType" onchange="onTypeChange()">
                    <optgroup label="Content">
                        <option value="intro">intro - Opening quote + body text</option>
                        <option value="data">data - Statistics display</option>
                        <option value="task">task - Task brief</option>
                        <option value="review">review - Shows past responses</option>
                        <option value="complete">complete - Completion screen</option>
                    </optgroup>
                    <optgroup label="Input">
                        <option value="textarea" selected>textarea - Free-text answer</option>
                        <option value="rewrite">rewrite - Belief rewrite box</option>
                        <option value="sentence">sentence - Sentence starter + input</option>
                        <option value="story">story - Story + reflection</option>
                        <option value="quiz">quiz - 5-question Likert quiz</option>
                        <option value="goalmap">goalmap - Goal + milestones</option>
                        <option value="scenarios">scenarios - Scenario reframes</option>
                        <option value="list">list - Resilience list</option>
                        <option value="transcription">transcription - Two-part transcription</option>
                        <option value="earning">earning - Earning reflection</option>
                        <option value="earner">earner - Earner identity form</option>
                    </optgroup>
                </select>
            </div>

            <div class="msa-field">
                <label class="msa-label"><i class="fa fa-heading"></i> Step Title <span class="req">*</span></label>
                <input class="msa-input" type="text" id="qTitle" maxlength="255">
            </div>

            <div class="msa-modal-section"><i class="fa fa-sliders"></i> Type-specific content</div>

            <div class="msa-conditional" id="cond-intro">
                <div class="msa-field msa-field-full">
                    <label class="msa-label"><i class="fa fa-quote-left"></i> Quote</label>
                    <textarea class="msa-textarea" id="qQuoteText" rows="2"></textarea>
                </div>

                <div class="msa-field msa-field-full">
                    <label class="msa-label"><i class="fa fa-align-left"></i> Body Text</label>
                    <textarea class="msa-textarea" id="qBodyText"></textarea>
                </div>
            </div>

            <div class="msa-conditional" id="cond-complete">
                <div class="msa-field msa-field-full">
                    <label class="msa-label"><i class="fa fa-align-left"></i> Completion Message</label>
                    <textarea class="msa-textarea" id="qBodyText2"></textarea>
                </div>
            </div>

            <div class="msa-conditional" id="cond-textarea">
                <div class="msa-field msa-field-full">
                    <label class="msa-label"><i class="fa fa-question"></i> Prompt / Question</label>
                    <textarea class="msa-textarea" id="qPrompt"></textarea>
                </div>

                <div class="msa-field msa-field-full">
                    <label class="msa-label"><i class="fa fa-lightbulb"></i> Hint</label>
                    <input class="msa-input" type="text" id="qHint" maxlength="500">
                </div>
            </div>

            <div class="msa-conditional" id="cond-sentence">
                <div class="msa-field msa-field-full">
                    <label class="msa-label"><i class="fa fa-pen-nib"></i> Sentence Starter</label>
                    <input class="msa-input" type="text" id="qStarterText" maxlength="255">
                </div>
            </div>

            <div class="msa-conditional" id="cond-input-opts">
                <div class="msa-field-row">
                    <div class="msa-field">
                        <label class="msa-label"><i class="fa fa-asterisk"></i> Required?</label>
                        <select class="msa-select" id="qIsRequired">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <div class="msa-field">
                        <label class="msa-label"><i class="fa fa-text-width"></i> Min Length</label>
                        <input class="msa-input" type="number" id="qMinLength" min="0" max="5000" value="0">
                    </div>
                </div>

                <div class="msa-field-row">
                    <div class="msa-field">
                        <label class="msa-label"><i class="fa fa-list-ol"></i> Min Items</label>
                        <input class="msa-input" type="number" id="qMinItems" min="0" max="10" value="0">
                    </div>

                    <div class="msa-field">
                        <label class="msa-label"><i class="fa fa-star"></i> Points</label>
                        <input class="msa-input" type="number" id="qPoints" min="0" max="500" value="0">
                    </div>
                </div>
            </div>

            <div class="msa-form-error" id="qFormError">
                <i class="fa fa-circle-exclamation"></i>
                <span id="qFormErrorText"></span>
            </div>
        </div>

        <div class="msa-modal-foot">
            <button class="btn-modal-cancel" onclick="closeModal('qFormOverlay')">Cancel</button>
            <button class="btn-export" id="qFormSubmitBtn" onclick="submitQuestionForm()">
                <span class="msa-spin" id="qFormSpin"></span>
                <i class="fa fa-save" id="qFormSaveIcon"></i>
                <span id="qFormBtnLabel">Save Question</span>
            </button>
        </div>
    </div>
</div>

<div class="msa-overlay" id="qDeleteOverlay" onclick="overlayClose('qDeleteOverlay', event)">
    <div class="msa-modal msa-modal-sm">
        <div class="msa-modal-head">
            <h3><i class="fa fa-trash-can"></i> Delete Question</h3>
            <button class="msa-modal-close" onclick="closeModal('qDeleteOverlay')">
                <i class="fa fa-xmark"></i>
            </button>
        </div>

        <div class="msa-modal-body">
            <div class="msa-del-warn">
                <i class="fa fa-triangle-exclamation"></i>
                <p>You are about to delete: <strong id="delQuestionTitle">...</strong></p>
            </div>
        </div>

        <div class="msa-modal-foot">
            <button class="btn-modal-cancel" onclick="closeModal('qDeleteOverlay')">Cancel</button>
            <button class="btn-modal-delete" id="delConfirmBtn" onclick="confirmDelete()">
                <span class="msa-spin" id="delSpin"></span>
                <i class="fa fa-trash-can" id="delIcon"></i>
                Delete
            </button>
        </div>
    </div>
</div>

<div class="msa-toast-bar" id="msaToastBar"></div>

<style>
.module-progress-line {
    margin-bottom:8px;
    padding:8px 10px;
    background:#f9fafb;
    border:1px solid #e5e7eb;
    border-radius:10px;
}

.module-progress-line strong {
    font-size:1.4rem;
}

.module-progress-line small {
    display:inline-block;
    margin-left:6px;
    color:var(--muted);
}

.msa-overlay.msa-open {
    display:flex;
    align-items:center;
    justify-content:center;
    padding:18px;
    overflow-y:auto;
}

.msa-modal {
    width:min(950px, 96vw);
    max-height:92vh;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}

.msa-modal-head {
    flex:0 0 auto;
}

.msa-modal-body {
    flex:1 1 auto;
    overflow-y:auto;
    max-height:calc(92vh - 165px);
    padding-bottom:24px;
}

.msa-modal-foot {
    flex:0 0 auto;
    position:sticky;
    bottom:0;
    z-index:10;
    background:#f8fafc;
    border-top:1px solid #e5e7eb;
}

.response-summary-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:14px;
    font-size:1.4rem;
}

.response-view-list {
    display:flex;
    flex-direction:column;
    gap:16px;
}

.response-module-card {
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:14px;
}

.response-module-head {
    display:flex;
    justify-content:space-between;
    gap:10px;
    align-items:center;
    margin-bottom:10px;
}

.response-module-title {
    font-weight:800;
    font-size:1.5rem;
}

.response-module-meta {
    font-size:1.4rem;
    color:#6b7280;
    margin-bottom:12px;
}

.response-view-item {
    background:#fff;
    border:1px solid #e5e7eb;
    border-left:4px solid #047857;
    border-radius:12px;
    padding:12px;
    margin-bottom:10px;
}

.response-view-key {
    font-size:1.4rem;
    font-weight:800;
    color:#047857;
    text-transform:uppercase;
    margin-bottom:6px;
}

.response-view-value {
    white-space:pre-wrap;
    font-size:1.5rem;
    line-height:1.5;
    color:#111827;
    background:#f9fafb;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:10px;
}

@media(max-width:768px){
    .response-summary-grid {
        grid-template-columns:1fr;
    }

    .msa-modal {
        max-height:95vh;
        width:96vw;
    }

    .msa-modal-body {
        max-height:calc(95vh - 160px);
    }
}
</style>

<script>
const ADMIN_ENDPOINT = 'scripts/process-mindset-admin.php';
const MODULE_TITLES = <?= json_encode($module_titles, JSON_UNESCAPED_UNICODE) ?>;

const TEXT_TYPES     = ['textarea','rewrite','story','earning'];
const SENTENCE_TYPES = ['sentence'];
const INTRO_TYPES    = ['intro'];
const COMPLETE_TYPES = ['complete'];

const INPUT_TYPES = [
    'textarea','rewrite','sentence','story',
    'quiz','goalmap','scenarios','list',
    'transcription','earning','earner'
];

let activeModule   = 1;
let questionCache  = {};
let deleteTargetId = null;
let editMode       = false;

function escHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

async function safeFetch(url, options = {}) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal,
            headers: {
                'Accept': 'application/json',
                ...(options.headers || {})
            }
        });

        clearTimeout(timeout);

        const rawText = await response.text();

        if (!rawText || !rawText.trim()) {
            throw new Error('Empty response from backend.');
        }

        let data;
        try {
            data = JSON.parse(rawText);
        } catch (e) {
            console.error(rawText);
            throw new Error('Backend returned invalid JSON.');
        }

        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }

        return data;

    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error('Request timeout.');
        }
        throw error;
    }
}

function switchTab(name, btn) {
    document.querySelectorAll('.msa-tab-panel').forEach(el => el.classList.remove('msa-active'));
    document.querySelectorAll('.msa-page-tab').forEach(el => el.classList.remove('msa-active'));

    document.getElementById('tab-' + name).classList.add('msa-active');
    btn.classList.add('msa-active');

    if (name === 'questions' && !questionCache[activeModule]) {
        loadModule(activeModule, document.querySelector(`.msa-module-nav-btn[data-module="${activeModule}"]`));
    }
}

function filterAnswers() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const module = document.getElementById('moduleFilter').value;
    const rows   = document.querySelectorAll('#answersTable tbody tr');

    let visible = 0;

    rows.forEach(row => {
        const rowSearch  = row.dataset.search || row.innerText.toLowerCase();
        const rowModules = row.dataset.modules || '';
        const rowStatus  = row.dataset.statuses || '';

        const show =
            (!search || rowSearch.includes(search)) &&
            (!status || rowStatus.split(',').includes(status)) &&
            (!module || rowModules.split(',').includes(module));

        row.style.display = show ? '' : 'none';

        if (show) visible++;
    });

    const counter = document.querySelector('#answersCount span');
    if (counter) {
        counter.textContent = `${visible} student${visible !== 1 ? 's' : ''}`;
    }
}

function openStudentModulesModal(btn) {
    document.getElementById('rvStudent').textContent = btn.dataset.student || '-';
    document.getElementById('rvEmail').textContent   = btn.dataset.email || '-';
    document.getElementById('rvUser').textContent    = btn.dataset.user || '-';

    let modules = [];

    try {
        modules = JSON.parse(btn.dataset.modules || '[]');
    } catch (e) {
        modules = [];
    }

    modules.sort((a, b) => parseInt(a.module_id || 0) - parseInt(b.module_id || 0));

    const box = document.getElementById('rvResponses');

    if (!modules.length) {
        box.innerHTML = `
            <div style="padding:24px;text-align:center;color:#6b7280;background:#f9fafb;border-radius:12px">
                No module progress found.
            </div>
        `;
    } else {
        box.innerHTML = modules.map(mod => {
            let responses = {};

            try {
                responses = typeof mod.responses === 'string'
                    ? JSON.parse(mod.responses || '{}')
                    : (mod.responses || {});
            } catch (e) {
                responses = {};
            }

            const status = mod.status === 'completed' ? 'Completed' : 'In Progress';
            const badgeClass = mod.status === 'completed' ? 's-graded' : 's-submitted';
            const moduleTitle = MODULE_TITLES[mod.module_id] || '';

            const responseHtml = Object.entries(responses).map(([key, value]) => {
                if (typeof value === 'object' && value !== null) {
                    value = JSON.stringify(value, null, 2);
                }

                return `
                    <div class="response-view-item">
                        <div class="response-view-key">${escHtml(key)}</div>
                        <div class="response-view-value">${escHtml(value)}</div>
                    </div>
                `;
            }).join('');

            return `
                <div class="response-module-card">
                    <div class="response-module-head">
                        <div>
                            <div class="response-module-title">
                                Module ${escHtml(mod.module_id)} - ${escHtml(moduleTitle)}
                            </div>
                        </div>

                        <span class="s-badge ${badgeClass}">${status}</span>
                    </div>

                    <div class="response-module-meta">
                        Points: ${escHtml(mod.points || 0)} |
                        Step: ${escHtml(mod.step_index || 0)} |
                        Completed: ${escHtml(mod.completed_at || '-')} |
                        Updated: ${escHtml(mod.updated_at || '-')}
                    </div>

                    ${responseHtml || '<p style="color:#6b7280">No responses yet.</p>'}
                </div>
            `;
        }).join('');
    }

    openModal('responseOverlay');
}

function showToast(message, type = 'ok') {
    const bar = document.getElementById('msaToastBar');
    const el  = document.createElement('div');

    el.className = `msa-toast msa-toast-${type}`;
    el.innerHTML = `<i class="fa ${type === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>${escHtml(message)}`;

    bar.appendChild(el);

    setTimeout(() => {
        el.classList.add('msa-leaving');
        setTimeout(() => el.remove(), 400);
    }, 3500);
}

function setBusy(spinId, iconId, btnId, busy) {
    const spin = document.getElementById(spinId);
    const icon = document.getElementById(iconId);
    const btn  = document.getElementById(btnId);

    if (spin) spin.classList.toggle('msa-active', busy);
    if (icon) icon.style.display = busy ? 'none' : '';
    if (btn)  btn.disabled = busy;
}

async function loadModule(moduleId, btn = null) {
    activeModule = moduleId;

    document.querySelectorAll('.msa-module-nav-btn').forEach(el => el.classList.remove('msa-active'));

    if (btn) btn.classList.add('msa-active');

    document.getElementById('qPanelTitle').innerHTML =
        `<i class="fa fa-list-check"></i> Module ${moduleId} - ${escHtml(MODULE_TITLES[moduleId] || '')}`;

    document.getElementById('qLoadingSkeleton').style.display = '';
    document.getElementById('qList').style.display = 'none';

    try {
        const data = await safeFetch(`${ADMIN_ENDPOINT}?action=list_questions&module_id=${moduleId}`);
        questionCache[moduleId] = data.questions || [];
    } catch (error) {
        showToast('Failed to load questions: ' + error.message, 'err');
        questionCache[moduleId] = [];
    }

    renderQuestions(moduleId);
}

function renderQuestions(moduleId) {
    document.getElementById('qLoadingSkeleton').style.display = 'none';

    const list = document.getElementById('qList');
    list.style.display = '';

    const qs = questionCache[moduleId] || [];

    if (!qs.length) {
        list.innerHTML = `
            <div class="msa-q-empty">
                <i class="fa fa-layer-group"></i>
                <p>No questions yet for this module.<br>Click <strong>Add Question</strong>.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = qs.map(q => `
        <div class="msa-q-item" data-id="${escHtml(q.id)}">
            <span class="msa-q-drag"><i class="fa fa-grip-vertical"></i></span>

            <div class="msa-q-body">
                <span class="msa-q-type-badge">${escHtml(q.step_type)}</span>
                <div class="msa-q-title">${escHtml(q.title)}</div>
                ${q.prompt ? `<div class="msa-q-prompt">${escHtml(q.prompt)}</div>` : ''}
            </div>

            <div class="msa-q-actions">
                <button class="ia-btn ia-view" onclick="openEditModal(${parseInt(q.id)})">
                    <i class="fa fa-pencil"></i>
                </button>

                <button class="ia-btn ia-del" onclick="openDeleteModal(${parseInt(q.id)}, '${escHtml(q.title)}')">
                    <i class="fa fa-trash-can"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function onTypeChange() {
    const type = document.getElementById('qStepType').value;

    document.querySelectorAll('.msa-conditional').forEach(el => el.style.display = 'none');

    if (INTRO_TYPES.includes(type)) document.getElementById('cond-intro').style.display = '';
    if (COMPLETE_TYPES.includes(type)) document.getElementById('cond-complete').style.display = '';

    if (TEXT_TYPES.includes(type) || type === 'story') {
        document.getElementById('cond-textarea').style.display = '';
    }

    if (SENTENCE_TYPES.includes(type)) {
        document.getElementById('cond-sentence').style.display = '';
        document.getElementById('cond-textarea').style.display = '';
    }

    if (INPUT_TYPES.includes(type)) {
        document.getElementById('cond-input-opts').style.display = '';
    }
}

function openAddModal() {
    editMode = false;
    resetForm();

    document.getElementById('qId').value = '';
    document.getElementById('qModuleId').value = activeModule;

    document.getElementById('qFormTitle').innerHTML = '<i class="fa fa-plus"></i> Add Question';
    document.getElementById('qFormBtnLabel').textContent = 'Save Question';

    onTypeChange();
    openModal('qFormOverlay');
}

async function openEditModal(id) {
    editMode = true;
    resetForm();

    const qs = questionCache[activeModule] || [];
    const q  = qs.find(item => String(item.id) === String(id));

    if (!q) {
        showToast('Question not found. Reload module and try again.', 'err');
        return;
    }

    document.getElementById('qFormTitle').innerHTML = '<i class="fa fa-pencil"></i> Edit Question';
    document.getElementById('qFormBtnLabel').textContent = 'Update Question';

    document.getElementById('qId').value          = q.id || '';
    document.getElementById('qModuleId').value    = q.module_id || activeModule;
    document.getElementById('qStepOrder').value   = q.step_order || '';
    document.getElementById('qStepType').value    = q.step_type || 'textarea';
    document.getElementById('qTitle').value       = q.title || '';
    document.getElementById('qPrompt').value      = q.prompt || '';
    document.getElementById('qHint').value        = q.hint || '';
    document.getElementById('qQuoteText').value   = q.quote_text || '';
    document.getElementById('qStarterText').value = q.starter_text || '';
    document.getElementById('qIsRequired').value  = q.is_required || 1;
    document.getElementById('qMinLength').value   = q.min_length || 0;
    document.getElementById('qMinItems').value    = q.min_items || 0;
    document.getElementById('qPoints').value      = q.points || 0;

    if (COMPLETE_TYPES.includes(q.step_type)) {
        document.getElementById('qBodyText2').value = q.body_text || '';
    } else {
        document.getElementById('qBodyText').value = q.body_text || '';
    }

    onTypeChange();
    openModal('qFormOverlay');
}

async function submitQuestionForm() {
    clearFormError();

    const title    = document.getElementById('qTitle').value.trim();
    const stepType = document.getElementById('qStepType').value;
    const moduleId = parseInt(document.getElementById('qModuleId').value);

    if (!title) {
        showFormError('Step title is required.');
        return;
    }

    const bodyTextEl = COMPLETE_TYPES.includes(stepType)
        ? document.getElementById('qBodyText2')
        : document.getElementById('qBodyText');

    const params = new URLSearchParams({
        action:       editMode ? 'edit_question' : 'add_question',
        id:           document.getElementById('qId').value,
        module_id:    moduleId,
        step_order:   document.getElementById('qStepOrder').value || 0,
        step_type:    stepType,
        title:        title,
        prompt:       document.getElementById('qPrompt').value,
        hint:         document.getElementById('qHint').value,
        quote_text:   document.getElementById('qQuoteText').value,
        body_text:    bodyTextEl.value,
        starter_text: document.getElementById('qStarterText').value,
        is_required:  document.getElementById('qIsRequired').value,
        min_length:   document.getElementById('qMinLength').value || 0,
        min_items:    document.getElementById('qMinItems').value || 0,
        points:       document.getElementById('qPoints').value || 0
    });

    setBusy('qFormSpin', 'qFormSaveIcon', 'qFormSubmitBtn', true);

    try {
        const data = await safeFetch(ADMIN_ENDPOINT, {
            method: 'POST',
            body: params
        });

        showToast(data.message || 'Saved successfully.', 'ok');
        closeModal('qFormOverlay');

        delete questionCache[moduleId];

        await loadModule(
            moduleId,
            document.querySelector(`.msa-module-nav-btn[data-module="${moduleId}"]`)
        );

    } catch (error) {
        showFormError(error.message);
    } finally {
        setBusy('qFormSpin', 'qFormSaveIcon', 'qFormSubmitBtn', false);
    }
}

function openDeleteModal(id, title) {
    deleteTargetId = id;
    document.getElementById('delQuestionTitle').textContent = `"${title}"`;
    openModal('qDeleteOverlay');
}

async function confirmDelete() {
    if (!deleteTargetId) return;

    setBusy('delSpin', 'delIcon', 'delConfirmBtn', true);

    try {
        const params = new URLSearchParams({
            action: 'delete_question',
            id: deleteTargetId
        });

        const data = await safeFetch(ADMIN_ENDPOINT, {
            method: 'POST',
            body: params
        });

        showToast(data.message || 'Deleted.', 'ok');
        closeModal('qDeleteOverlay');

        delete questionCache[activeModule];

        await loadModule(
            activeModule,
            document.querySelector(`.msa-module-nav-btn[data-module="${activeModule}"]`)
        );

    } catch (error) {
        showToast(error.message, 'err');
    } finally {
        setBusy('delSpin', 'delIcon', 'delConfirmBtn', false);
        deleteTargetId = null;
    }
}

function openModal(id) {
    document.getElementById(id).classList.add('msa-open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('msa-open');
}

function overlayClose(id, e) {
    if (e.target.id === id) closeModal(id);
}

function showFormError(msg) {
    document.getElementById('qFormErrorText').textContent = msg;
    document.getElementById('qFormError').classList.add('msa-visible');
}

function clearFormError() {
    document.getElementById('qFormError').classList.remove('msa-visible');
}

function resetForm() {
    [
        'qTitle',
        'qPrompt',
        'qHint',
        'qQuoteText',
        'qBodyText',
        'qBodyText2',
        'qStarterText'
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    document.getElementById('qStepOrder').value  = '';
    document.getElementById('qIsRequired').value = '1';
    document.getElementById('qMinLength').value  = '0';
    document.getElementById('qMinItems').value   = '0';
    document.getElementById('qPoints').value     = '0';
    document.getElementById('qStepType').value   = 'textarea';

    clearFormError();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.msa-conditional').forEach(el => el.style.display = 'none');
    onTypeChange();
    filterAnswers();
    loadModule(1, document.querySelector('.msa-module-nav-btn[data-module="1"]'));
});
</script>