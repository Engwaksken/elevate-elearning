<?php
// -- DB helpers ----------------------------------------------------------------
function fetchOneAssoc(mysqli $conn, string $sql): array {
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_assoc() ?: [];
}

// -- Student counts ------------------------------------------------------------
$totalRow = fetchOneAssoc($conn, "
    SELECT COUNT(*) AS total FROM users WHERE acc_type='afgezt'
");
$totalStudents = (int)($totalRow['total'] ?? 0);

$inactiveRow = fetchOneAssoc($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE acc_type='afgezt'
      AND (verified IS NULL OR verified <> 'yes')
");
$inactiveStudents = (int)($inactiveRow['total'] ?? 0);

$graduatedRow = fetchOneAssoc($conn, "
    SELECT COUNT(DISTINCT u.unique_id) AS total
    FROM users u
    JOIN course_registration cr ON cr.user_id = u.unique_id
    WHERE u.acc_type='afgezt' AND cr.status = 'done'
");
$graduatedStudents = (int)($graduatedRow['total'] ?? 0);

$enrolledRow = fetchOneAssoc($conn, "
    SELECT COUNT(DISTINCT u.unique_id) AS total
    FROM users u
    JOIN course_registration cr ON cr.user_id = u.unique_id
    WHERE u.acc_type='afgezt'
      AND (cr.status IS NULL OR cr.status <> 'done')
");
$enrolledStudents = (int)($enrolledRow['total'] ?? 0);

// -- Staff counts --------------------------------------------------------------
$supRow    = fetchOneAssoc($conn, "SELECT COUNT(*) AS total FROM users WHERE acc_type='sabrtw'");
$adminRow  = fetchOneAssoc($conn, "SELECT COUNT(*) AS total FROM users WHERE acc_type='xybrtw'");
$lectRow   = fetchOneAssoc($conn, "SELECT COUNT(*) AS total FROM users WHERE acc_type='xtfbry'");

$count_sup      = (int)($supRow['total']   ?? 0);
$count_admin    = (int)($adminRow['total'] ?? 0);
$count_lecturer = (int)($lectRow['total']  ?? 0);

// -- Chart payload -------------------------------------------------------------
$otherStudents = max(0, $totalStudents - ($enrolledStudents + $graduatedStudents + $inactiveStudents));
$chartData = [
    'total'     => $totalStudents,
    'enrolled'  => $enrolledStudents,
    'graduated' => $graduatedStudents,
    'inactive'  => $inactiveStudents,
    'other'     => $otherStudents,
];
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
<!-- -- Hero ------------------------------------------------------------------- -->
<div class="dash-hero">
    <div class="dash-hero-left">
        <h2><i class="fa fa-gauge-high" style="color:var(--gold-lt);font-size:1.2rem;"></i>
            Super Admin <em>Dashboard</em>
        </h2>
        <p>
            <i class="fa fa-circle-info"></i>
            Overview of all students, staff and system accounts.
        </p>
    </div>

    <div class="dash-support">
        <div class="dash-support-item">
            <span class="dash-support-tag">Students</span>
            <span class="dash-support-val">
                <?php echo number_format($totalStudents); ?> total &mdash;
                <?php echo number_format($enrolledStudents); ?> enrolled,
                <?php echo number_format($graduatedStudents); ?> graduated
            </span>
        </div>
        <div class="dash-support-item">
            <span class="dash-support-tag">Staff</span>
            <span class="dash-support-val">
                <?php echo number_format($count_lecturer); ?> instructors &bull;
                <?php echo number_format($count_admin); ?> admins &bull;
                <?php echo number_format($count_sup); ?> sup&nbsp;admins
            </span>
        </div>
    </div>
</div>

<!-- -- Student stat cards ------------------------------------------------------ -->
<div class="section-header">
    <h2><i class="fa fa-users"></i> Student Overview</h2>
    <span class="count-pill"><?php echo number_format($totalStudents); ?> total</span>
</div>

<div class="dash-stats" style="margin-bottom:26px;">

    <!-- Total -->
    <a href="index.php?view=view_students" class="dash-stat" style="text-decoration:none;">
        <div class="dash-stat-header">
            <span class="dash-stat-label">All Students</span>
            <span class="dash-stat-tag dst-maroon">Total</span>
        </div>
        <div class="dash-stat-num"><?php echo number_format($totalStudents); ?></div>
        <div class="dash-stat-sub">Registered accounts</div>
    </a>

    <!-- Enrolled -->
    <a href="index.php?view=view_students&filter=enrolled" class="dash-stat" style="text-decoration:none;">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Enrolled</span>
            <span class="dash-stat-tag dst-gold">Active</span>
        </div>
        <div class="dash-stat-num" style="color:var(--gold);"><?php echo number_format($enrolledStudents); ?></div>
        <div class="dash-stat-sub">Course not yet done</div>
    </a>

    <!-- Graduated -->
    <a href="index.php?view=view_students&filter=graduated" class="dash-stat" style="text-decoration:none;">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Graduated</span>
            <span class="dash-stat-tag dst-green">Done</span>
        </div>
        <div class="dash-stat-num" style="color:var(--green);"><?php echo number_format($graduatedStudents); ?></div>
        <div class="dash-stat-sub">Status = completed</div>
    </a>

    <!-- Inactive -->
    <a href="index.php?view=view_students&filter=inactive" class="dash-stat" style="text-decoration:none;">
        <div class="dash-stat-header">
            <span class="dash-stat-label">Inactive</span>
            <span class="dash-stat-tag dst-red">Unverified</span>
        </div>
        <div class="dash-stat-num" style="color:var(--red);"><?php echo number_format($inactiveStudents); ?></div>
        <div class="dash-stat-sub">verified &ne; yes</div>
    </a>

</div>

<!-- -- Charts ------------------------------------------------------------------ -->
<div class="chart-grid" style="margin-bottom:28px;">

    <div class="chart-box">
        <div class="chart-box-title">
            <i class="fa fa-chart-pie"></i> Distribution
        </div>
        <canvas id="studentsDonut" height="180"></canvas>
        <div class="chart-legend">
            <span class="chart-legend-item"><span class="chart-legend-dot cld-maroon"></span>Enrolled</span>
            <span class="chart-legend-item"><span class="chart-legend-dot cld-green"></span>Graduated</span>
            <span class="chart-legend-item"><span class="chart-legend-dot cld-red"></span>Inactive</span>
            <span class="chart-legend-item"><span class="chart-legend-dot cld-gold"></span>Other</span>
        </div>
    </div>

    <div class="chart-box">
        <div class="chart-box-title">
            <i class="fa fa-chart-bar"></i> Enrolled vs Graduated vs Inactive
        </div>
        <canvas id="studentsBar" height="180"></canvas>
    </div>

</div>

<!-- -- Staff stat cards -------------------------------------------------------- -->
<div class="section-header">
    <h2><i class="fa fa-id-badge"></i> Staff Accounts</h2>
</div>

<div class="stats" style="margin-bottom:28px;">

    <!-- Instructors -->
    <a href="index.php?view=view_instructors" class="stat-card" style="text-decoration:none;">
        <div class="stat-icon prog"><i class="fa fa-chalkboard-teacher"></i></div>
        <div>
            <div class="sv gold"><?php echo number_format($count_lecturer); ?></div>
            <div class="sl">Instructors</div>
        </div>
    </a>

    <!-- Admins -->
    <a href="index.php?view=view_admin" class="stat-card" style="text-decoration:none;">
        <div class="stat-icon cancel"><i class="fa fa-user-shield"></i></div>
        <div>
            <div class="sv red"><?php echo number_format($count_admin); ?></div>
            <div class="sl">Admins</div>
        </div>
    </a>

    <!-- Sup Admins -->
    <a href="index.php?view=view_sup_admins" class="stat-card" style="text-decoration:none;">
        <div class="stat-icon total"><i class="fa fa-crown"></i></div>
        <div>
            <div class="sv"><?php echo number_format($count_sup); ?></div>
            <div class="sl">Sup Admins</div>
        </div>
    </a>

</div>

<!-- -- Chart.js ---------------------------------------------------------------- -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    const stats = <?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE); ?>;

    // Read CSS variables for brand-accurate colours
    const style  = getComputedStyle(document.documentElement);
    const maroon = style.getPropertyValue('--maroon').trim()    || '#7a1919';
    const green  = style.getPropertyValue('--green').trim()     || '#156f3f';
    const red    = style.getPropertyValue('--red').trim()       || '#a81515';
    const gold   = style.getPropertyValue('--gold-lt').trim()   || '#c9a84c';
    const border = style.getPropertyValue('--border').trim()    || '#e1e5ea';
    const muted  = style.getPropertyValue('--muted').trim()     || '#6b7280';
    const text   = style.getPropertyValue('--text').trim()      || '#222222';

    Chart.defaults.font.family = "'Arial', system-ui, sans-serif";
    Chart.defaults.color       = muted;

    // -- Donut ----------------------------------------------------------------
    new Chart(document.getElementById('studentsDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Enrolled', 'Graduated', 'Inactive', 'Other'],
            datasets: [{
                data: [stats.enrolled, stats.graduated, stats.inactive, stats.other],
                backgroundColor: [maroon, green, red, gold],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 8,
            }],
        },
        options: {
            responsive: true,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed.toLocaleString()}`,
                    },
                },
            },
        },
    });

    // -- Bar ------------------------------------------------------------------
    new Chart(document.getElementById('studentsBar'), {
        type: 'bar',
        data: {
            labels: ['Enrolled', 'Graduated', 'Inactive'],
            datasets: [{
                label: 'Students',
                data: [stats.enrolled, stats.graduated, stats.inactive],
                backgroundColor: [
                    'rgba(122,25,25,.80)',
                    'rgba(21,111,63,.80)',
                    'rgba(168,21,21,.80)',
                ],
                borderColor:  [maroon, green, red],
                borderWidth:  2,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toLocaleString()} students`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: border },
                    ticks: { color: muted },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: border },
                    ticks: {
                        precision: 0,
                        color: muted,
                    },
                },
            },
        },
    });
})();
</script>