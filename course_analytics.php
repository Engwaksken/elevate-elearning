<?php

if (($_SESSION[$systemname . 'letype'] ?? '') !== 'admin') {
    echo '<div class="app-alert app-alert-danger">
            <i class="fa fa-circle-xmark"></i>
            Access denied. Admin privileges required.
          </div>';
    exit;
}

$start_date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'] ?? '')
                    ? $_GET['start_date'] : date('Y-m-01');
$end_date      = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'] ?? '')
                    ? $_GET['end_date'] : date('Y-m-d');
$course_filter = (int)($_GET['course_id'] ?? 0);


function clickStats(mysqli $conn, string $table, string $start,
                    string $end, int $course): array {
    $sql = "SELECT COALESCE(SUM(no_clicks),0)       AS total_clicks,
                   COUNT(DISTINCT user_id)           AS unique_users
            FROM `$table`
            WHERE DATE(last_clicked) BETWEEN ? AND ?" .
           ($course > 0 ? " AND course_id = ?" : "");

    $st = $conn->prepare($sql);
    if ($course > 0) $st->bind_param('ssi', $start, $end, $course);
    else             $st->bind_param('ss',  $start, $end);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?? ['total_clicks' => 0, 'unique_users' => 0];
}

$mod_stats    = clickStats($conn, 'module_clicks',     $start_date, $end_date, $course_filter);
$notes_stats  = clickStats($conn, 'notes_clicks',      $start_date, $end_date, $course_filter);
$assign_stats = clickStats($conn, 'assignment_clicks', $start_date, $end_date, $course_filter);


$cw = $course_filter > 0 ? "AND course_id = $course_filter" : ""; // safe – already cast to int

$student_sql = "
    SELECT u.fname, u.lname, u.unique_id,
           COALESCE(mc.mc,0) AS module_clicks,
           COALESCE(nc.nc,0) AS notes_clicks,
           COALESCE(ac.ac,0) AS assignment_clicks,
           (COALESCE(mc.mc,0)+COALESCE(nc.nc,0)+COALESCE(ac.ac,0)) AS total_clicks
    FROM users u
    LEFT JOIN (SELECT user_id, SUM(no_clicks) mc FROM module_clicks
               WHERE DATE(last_clicked) BETWEEN ? AND ? $cw GROUP BY user_id) mc
           ON u.unique_id = mc.user_id
    LEFT JOIN (SELECT user_id, SUM(no_clicks) nc FROM notes_clicks
               WHERE DATE(last_clicked) BETWEEN ? AND ? $cw GROUP BY user_id) nc
           ON u.unique_id = nc.user_id
    LEFT JOIN (SELECT user_id, SUM(no_clicks) ac FROM assignment_clicks
               WHERE DATE(last_clicked) BETWEEN ? AND ? $cw GROUP BY user_id) ac
           ON u.unique_id = ac.user_id
    WHERE (mc.mc > 0 OR nc.nc > 0 OR ac.ac > 0)
    ORDER BY total_clicks DESC
    LIMIT 20";

$st = $conn->prepare($student_sql);
$st->bind_param('ssssss',
    $start_date, $end_date,
    $start_date, $end_date,
    $start_date, $end_date);
$st->execute();
$students = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();


$top_mod_sql = "
    SELECT cu.name AS module_name, c.name AS course_name,
           SUM(mc.no_clicks) AS total_clicks,
           COUNT(DISTINCT mc.user_id) AS unique_users
    FROM module_clicks mc
    JOIN course_unit cu ON mc.module_id  = cu.id
    JOIN courses     c  ON mc.course_id  = c.id
    WHERE DATE(mc.last_clicked) BETWEEN ? AND ?" .
    ($course_filter > 0 ? " AND mc.course_id = ?" : "") . "
    GROUP BY mc.module_id, mc.course_id
    ORDER BY total_clicks DESC LIMIT 10";

$st = $conn->prepare($top_mod_sql);
if ($course_filter > 0) $st->bind_param('ssi', $start_date, $end_date, $course_filter);
else                    $st->bind_param('ss',  $start_date, $end_date);
$st->execute();
$top_modules = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();


$top_notes_sql = "
    SELECT cun.title AS notes_title, c.name AS course_name,
           SUM(nc.no_clicks) AS total_clicks,
           COUNT(DISTINCT nc.user_id) AS unique_users
    FROM notes_clicks nc
    JOIN course_unit_notes cun ON nc.notes_id  = cun.id
    JOIN courses           c   ON nc.course_id = c.id
    WHERE DATE(nc.last_clicked) BETWEEN ? AND ?" .
    ($course_filter > 0 ? " AND nc.course_id = ?" : "") . "
    GROUP BY nc.notes_id, nc.course_id
    ORDER BY total_clicks DESC LIMIT 10";

$st = $conn->prepare($top_notes_sql);
if ($course_filter > 0) $st->bind_param('ssi', $start_date, $end_date, $course_filter);
else                    $st->bind_param('ss',  $start_date, $end_date);
$st->execute();
$top_notes = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();


$top_assign_sql = "
    SELECT a.title AS assignment_title, c.name AS course_name,
           SUM(ac.no_clicks) AS total_clicks,
           COUNT(DISTINCT ac.user_id) AS unique_users
    FROM assignment_clicks ac
    JOIN assignments a ON ac.assignment_id = a.id
    JOIN courses     c ON ac.course_id     = c.id
    WHERE DATE(ac.last_clicked) BETWEEN ? AND ?" .
    ($course_filter > 0 ? " AND ac.course_id = ?" : "") . "
    GROUP BY ac.assignment_id, ac.course_id
    ORDER BY total_clicks DESC LIMIT 10";

$st = $conn->prepare($top_assign_sql);
if ($course_filter > 0) $st->bind_param('ssi', $start_date, $end_date, $course_filter);
else                    $st->bind_param('ss',  $start_date, $end_date);
$st->execute();
$top_assignments = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();


$courses = $conn->query("SELECT id, name FROM courses ORDER BY name ASC")
                ?->fetch_all(MYSQLI_ASSOC) ?? [];

$export_qs = http_build_query([
    'start_date' => $start_date,
    'end_date'   => $end_date,
    'course_id'  => $course_filter,
]);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
</head>
<body>

<div class="page-heading">
    <div>
        <h2>Course <em>Analytics</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="?" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill"
                  style="background:var(--maroon-dim);color:var(--maroon);
                         border-color:rgba(122,25,25,.18);">
                <i class="fa fa-chart-bar"></i> Click Analytics
            </span>
        </div>
    </div>
    <span style="font-size:.72rem;color:var(--muted);
                 display:flex;align-items:center;gap:4px;">
        <i class="fa fa-clock" style="color:var(--gold);font-size:.65rem;"></i>
        <?php echo date('h:i a'); ?>
    </span>
</div>

<!-- -- Filters ----------------------------------------------------------------- -->
<form method="GET">
    <input type="hidden" name="view" value="course_analytics">

    <div class="controls-bar" style="margin-bottom:24px;">

        <div class="ctrl-field">
            <label class="ctrl-label" for="start_date">
                <i class="fa fa-calendar-day"></i> From
            </label>
            <input type="date" id="start_date" name="start_date"
                   class="form-input" style="min-width:160px;"
                   value="<?php echo htmlspecialchars($start_date); ?>">
        </div>

        <div class="ctrl-field">
            <label class="ctrl-label" for="end_date">
                <i class="fa fa-calendar-check"></i> To
            </label>
            <input type="date" id="end_date" name="end_date"
                   class="form-input" style="min-width:160px;"
                   value="<?php echo htmlspecialchars($end_date); ?>">
        </div>

        <div class="ctrl-field">
            <label class="ctrl-label" for="course_id">
                <i class="fa fa-book"></i> Course
            </label>
            <select id="course_id" name="course_id"
                    class="ctrl-select" style="min-width:200px;">
                <option value="0">All Courses</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"
                        <?php echo (int)$c['id'] === $course_filter
                                   ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ctrl-field" style="flex-direction:row;
                    gap:8px;align-items:flex-end;">
            <button type="submit" class="btn-export">
                <i class="fa fa-filter"></i> Apply
            </button>
            <a href="?view=course_analytics" class="btn-modal-cancel">
                Clear
            </a>
        </div>

    </div>
</form>

<!-- -- Summary stat strip ------------------------------------------------------- -->
<div class="section-header" style="margin-bottom:14px;">
    <h2><i class="fa fa-chart-pie"></i> Click Summary</h2>
    <span class="count-pill">
        <?php echo date('M j', strtotime($start_date))
              . ' – ' . date('M j, Y', strtotime($end_date)); ?>
    </span>
</div>

<div class="stats" style="margin-bottom:28px;">

    <div class="stat-card">
        <div class="stat-icon total"><i class="fa fa-cubes"></i></div>
        <div>
            <div class="sv"><?php echo number_format($mod_stats['total_clicks']); ?></div>
            <div class="sl">Module Clicks</div>
            <div style="font-size:.67rem;color:var(--muted);margin-top:3px;">
                <i class="fa fa-users" style="color:var(--gold);font-size:.6rem;"></i>
                <?php echo number_format($mod_stats['unique_users']); ?> unique users
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon done"><i class="fa fa-file-lines"></i></div>
        <div>
            <div class="sv green"><?php echo number_format($notes_stats['total_clicks']); ?></div>
            <div class="sl">Notes Clicks</div>
            <div style="font-size:.67rem;color:var(--muted);margin-top:3px;">
                <i class="fa fa-users" style="color:var(--gold);font-size:.6rem;"></i>
                <?php echo number_format($notes_stats['unique_users']); ?> unique users
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon prog"><i class="fa fa-pen-to-square"></i></div>
        <div>
            <div class="sv gold"><?php echo number_format($assign_stats['total_clicks']); ?></div>
            <div class="sl">Assignment Clicks</div>
            <div style="font-size:.67rem;color:var(--muted);margin-top:3px;">
                <i class="fa fa-users" style="color:var(--gold);font-size:.6rem;"></i>
                <?php echo number_format($assign_stats['unique_users']); ?> unique users
            </div>
        </div>
    </div>

    <?php
    $grand_total = (int)$mod_stats['total_clicks']
                 + (int)$notes_stats['total_clicks']
                 + (int)$assign_stats['total_clicks'];
    ?>
    <div class="stat-card" style="border-top-color:var(--gold);">
        <div class="stat-icon submitted" style="background:var(--gold-dim);color:var(--gold);">
            <i class="fa fa-chart-bar"></i>
        </div>
        <div>
            <div class="sv gold"><?php echo number_format($grand_total); ?></div>
            <div class="sl">Total Clicks</div>
            <div style="font-size:.67rem;color:var(--muted);margin-top:3px;">
                All content types combined
            </div>
        </div>
    </div>

</div>

<!-- -- Most active students ----------------------------------------------------- -->
<div class="section-header" style="margin-bottom:14px;">
    <h2><i class="fa fa-users"></i> Most Active Students</h2>
    <span class="count-pill">Top <?php echo count($students); ?></span>
</div>

<div class="tbl-card" style="margin-bottom:28px;">
    <div class="tbl-toolbar">
        <span class="tbl-title">
            <i class="fa fa-ranking-star tbl-title-icon"></i>
            Student Engagement
        </span>
    </div>
    <div class="tbl-scroll">
        <table class="app-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Name</th>
                    <th class="th-gold"><i class="fa fa-cubes"></i> Modules</th>
                    <th class="th-gold"><i class="fa fa-file-lines"></i> Notes</th>
                    <th class="th-gold"><i class="fa fa-pen-to-square"></i> Assignments</th>
                    <th class="th-gold">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$students): ?>
                <tr>
                    <td colspan="6" style="padding:0;">
                        <div class="empty" style="border:none;border-radius:0;">
                            <i class="fa fa-users"></i>
                            <p>No activity recorded for this period.</p>
                        </div>
                    </td>
                </tr>
            <?php else:
                $max = (int)($students[0]['total_clicks'] ?? 1);
                foreach ($students as $i => $s):
                    $pct = $max > 0 ? round(($s['total_clicks'] / $max) * 100) : 0;
            ?>
                <tr>
                    <td class="cell-num"><?php echo $i + 1; ?></td>
                    <td>
                        <div class="fw-600" style="color:var(--text);font-size:.86rem;">
                            <?php echo htmlspecialchars($s['fname'] . ' ' . $s['lname']); ?>
                        </div>
                        <!-- Mini progress bar -->
                        <div style="height:3px;background:var(--border);
                                    border-radius:2px;margin-top:5px;width:120px;">
                            <div style="height:100%;width:<?php echo $pct; ?>%;
                                        background:var(--maroon);border-radius:2px;
                                        transition:width .4s ease;"></div>
                        </div>
                    </td>
                    <td class="cell-sm"><?php echo number_format($s['module_clicks']); ?></td>
                    <td class="cell-sm"><?php echo number_format($s['notes_clicks']); ?></td>
                    <td class="cell-sm"><?php echo number_format($s['assignment_clicks']); ?></td>
                    <td>
                        <span class="marks-final">
                            <?php echo number_format($s['total_clicks']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($students): ?>
    <div class="tbl-footer">
        <div class="legend">
            <span class="legend-item">
                <span class="legend-dot" style="background:var(--maroon);"></span>
                Bar = relative to top student
            </span>
        </div>
        <span>Showing top <?php echo count($students); ?> students</span>
    </div>
    <?php endif; ?>
</div>

<!-- -- Top content: modules + notes side-by-side ------------------------------- -->
<div class="section-header" style="margin-bottom:14px;">
    <h2><i class="fa fa-fire"></i> Top Content</h2>
</div>

<div class="chart-grid" style="margin-bottom:28px;">

    <!-- Top modules -->
    <div class="tbl-card">
        <div class="tbl-toolbar">
            <span class="tbl-title">
                <i class="fa fa-cubes tbl-title-icon"></i> Most Clicked Modules
            </span>
            <span class="count-pill">Top 10</span>
        </div>
        <div class="tbl-scroll">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Course</th>
                        <th class="th-gold">Clicks</th>
                        <th class="th-gold">Users</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$top_modules): ?>
                    <tr><td colspan="4" style="padding:0;">
                        <div class="empty" style="border:none;border-radius:0;padding:28px;">
                            <i class="fa fa-cubes"></i>
                            <p>No data for this period.</p>
                        </div>
                    </td></tr>
                <?php else: foreach ($top_modules as $i => $m): ?>
                    <tr>
                        <td>
                            <span class="fw-600" style="font-size:.84rem;color:var(--text);">
                                <?php echo htmlspecialchars($m['module_name']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="cat-tag">
                                <i class="fa fa-book"></i>
                                <?php echo htmlspecialchars($m['course_name']); ?>
                            </span>
                        </td>
                        <td><span class="marks-final"><?php echo number_format($m['total_clicks']); ?></span></td>
                        <td class="cell-sm"><?php echo number_format($m['unique_users']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top notes -->
    <div class="tbl-card">
        <div class="tbl-toolbar">
            <span class="tbl-title">
                <i class="fa fa-file-lines tbl-title-icon"></i> Most Clicked Notes
            </span>
            <span class="count-pill">Top 10</span>
        </div>
        <div class="tbl-scroll">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Note Title</th>
                        <th>Course</th>
                        <th class="th-gold">Clicks</th>
                        <th class="th-gold">Users</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$top_notes): ?>
                    <tr><td colspan="4" style="padding:0;">
                        <div class="empty" style="border:none;border-radius:0;padding:28px;">
                            <i class="fa fa-file-lines"></i>
                            <p>No data for this period.</p>
                        </div>
                    </td></tr>
                <?php else: foreach ($top_notes as $note): ?>
                    <tr>
                        <td>
                            <span class="fw-600" style="font-size:.84rem;color:var(--text);">
                                <?php echo htmlspecialchars($note['notes_title']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="cat-tag">
                                <i class="fa fa-book"></i>
                                <?php echo htmlspecialchars($note['course_name']); ?>
                            </span>
                        </td>
                        <td><span class="marks-final"><?php echo number_format($note['total_clicks']); ?></span></td>
                        <td class="cell-sm"><?php echo number_format($note['unique_users']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- -- Top assignments (full-width) -------------------------------------------- -->
<div class="tbl-card" style="margin-bottom:28px;">
    <div class="tbl-toolbar">
        <span class="tbl-title">
            <i class="fa fa-pen-to-square tbl-title-icon"></i>
            Most Clicked Assignments
        </span>
        <span class="count-pill">Top 10</span>
    </div>
    <div class="tbl-scroll">
        <table class="app-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Assignment Title</th>
                    <th>Course</th>
                    <th class="th-gold">Total Clicks</th>
                    <th class="th-gold">Unique Users</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$top_assignments): ?>
                <tr><td colspan="5" style="padding:0;">
                    <div class="empty" style="border:none;border-radius:0;">
                        <i class="fa fa-pen-to-square"></i>
                        <p>No assignment click data for this period.</p>
                    </div>
                </td></tr>
            <?php else: foreach ($top_assignments as $i => $a): ?>
                <tr>
                    <td class="cell-num"><?php echo $i + 1; ?></td>
                    <td>
                        <span class="fw-600" style="font-size:.84rem;color:var(--text);">
                            <?php echo htmlspecialchars($a['assignment_title']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="cat-tag">
                            <i class="fa fa-book"></i>
                            <?php echo htmlspecialchars($a['course_name']); ?>
                        </span>
                    </td>
                    <td><span class="marks-final"><?php echo number_format($a['total_clicks']); ?></span></td>
                    <td class="cell-sm"><?php echo number_format($a['unique_users']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- -- Export ------------------------------------------------------------------ -->
<div class="tbl-card">
    <div class="tbl-toolbar">
        <span class="tbl-title">
            <i class="fa fa-file-export tbl-title-icon"></i> Export Data
        </span>
        <span style="font-size:.72rem;color:var(--muted);">
            Period: <?php echo htmlspecialchars($start_date);
                    echo ' - ';
                    echo htmlspecialchars($end_date); ?>
        </span>
    </div>
    <div style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:10px;">
        <a href="export_analytics.php?type=modules&<?php echo $export_qs; ?>"
           class="btn-export">
            <i class="fa fa-cubes"></i> Export Modules
        </a>
        <a href="export_analytics.php?type=notes&<?php echo $export_qs; ?>"
           class="btn-export" style="background:var(--green);">
            <i class="fa fa-file-lines"></i> Export Notes
        </a>
        <a href="export_analytics.php?type=assignments&<?php echo $export_qs; ?>"
           class="btn-export" style="background:var(--gold);">
            <i class="fa fa-pen-to-square"></i> Export Assignments
        </a>
    </div>
</div>