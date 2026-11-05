<?php
// Dashboard cards + student status statistics + charts
// Requirements:
// - $conn is mysqli connection
// - Bootstrap + FontAwesome already in your template
// - Chart.js loaded (added below)

if (session_status() === PHP_SESSION_NONE) session_start();

function countUsersByType(mysqli $conn, string $accType): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE acc_type = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("s", $accType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

/**
 * Student status logic (customize to match your DB):
 * - Inactive: users.verified != 'yes' (or = 'no')
 * - Graduated: has a course_registration row with status='done'
 * - Enrolled: has a course_registration row with status!='done'
 *
 * If you use different fields, tell me the exact columns and I’ll adjust.
 */
function getStudentStatusStats(mysqli $conn): array {
    $sql = "
        SELECT
            COUNT(DISTINCT u.unique_id) AS total_students,

            /* Inactive students */
            COUNT(DISTINCT CASE WHEN (u.verified IS NULL OR u.verified <> 'yes') THEN u.unique_id END) AS inactive_students,

            /* Graduated: at least one registration marked done */
            COUNT(DISTINCT CASE WHEN EXISTS (
                SELECT 1
                FROM course_registration crg
                WHERE crg.user_id = u.unique_id
                  AND crg.status = 'done'
            ) THEN u.unique_id END) AS graduated_students,

            /* Enrolled: at least one registration not done */
            COUNT(DISTINCT CASE WHEN EXISTS (
                SELECT 1
                FROM course_registration cre
                WHERE cre.user_id = u.unique_id
                  AND (cre.status IS NULL OR cre.status <> 'done')
            ) THEN u.unique_id END) AS enrolled_students
        FROM users u
        WHERE u.acc_type = 'afgezt'
    ";

    $res = $conn->query($sql);
    if (!$res) {
        return [
            'total_students' => 0,
            'inactive_students' => 0,
            'graduated_students' => 0,
            'enrolled_students' => 0,
        ];
    }
    $row = $res->fetch_assoc() ?: [];
    return [
        'total_students' => (int)($row['total_students'] ?? 0),
        'inactive_students' => (int)($row['inactive_students'] ?? 0),
        'graduated_students' => (int)($row['graduated_students'] ?? 0),
        'enrolled_students' => (int)($row['enrolled_students'] ?? 0),
    ];
}

/* ---------- counts for cards ---------- */
$count_student  = countUsersByType($conn, 'afgezt');
$count_lecturer = countUsersByType($conn, 'xtfbry');
$count_admin    = countUsersByType($conn, 'xybrtw'); // optional
$count_account  = countUsersByType($conn, 'zyffwq'); // optional

/* ---------- student status stats ---------- */
$stats = getStudentStatusStats($conn);

// If your enrolled+graduated overlaps (a user could be in both), you can also compute "other" if needed.
?>
<style>
  .stat-card { cursor:pointer; }
  .chart-wrap { background:#fff; padding:15px; border-radius:6px; box-shadow:0 0 6px rgba(0,0,0,0.06); }
  .chart-title { font-weight:600; margin:0 0 10px; }
</style>

<div class="wrapper wrapper-content">
  <!-- Top cards -->
  <div class="row">
    <div class="col-lg-3">
      <div class="ibox float-e-margins dang stat-card" onclick="window.location='index.php?view=view_students'">
        <div class="ibox-title panel panel-success">
          <span class="label panel-heading label-success pull-right">Student accounts</span>
          <h5>Students</h5>
        </div>
        <div class="ibox-content">
          <h1 class="no-margins"><?php echo number_format($count_student); ?></h1>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="ibox float-e-margins dang stat-card" onclick="window.location='index.php?view=view_instructors'">
        <div class="ibox-title panel panel-info">
          <span class="label label-info pull-right">Instructor accounts</span>
          <h5>Instructors</h5>
        </div>
        <div class="ibox-content">
          <h1 class="no-margins"><?php echo number_format($count_lecturer); ?></h1>
        </div>
      </div>
    </div>

    <?php if (!empty($showAdminCards)) : ?>
      <div class="col-lg-3">
        <div class="ibox float-e-margins dang stat-card" onclick="window.location='index.php?view=view_admin'">
          <div class="ibox-title panel panel-primary">
            <span class="label label-primary pull-right">Administrator accounts</span>
            <h5>Admins</h5>
          </div>
          <div class="ibox-content">
            <h1 class="no-margins"><?php echo number_format($count_admin); ?></h1>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($showAccountCards)) : ?>
      <div class="col-lg-3">
        <div class="ibox float-e-margins dang stat-card" onclick="window.location='index.php?view=view_accounts'">
          <div class="ibox-title panel panel-danger">
            <span class="label label-danger pull-right">Accountant accounts</span>
            <h5>Accounts</h5>
          </div>
          <div class="ibox-content">
            <h1 class="no-margins"><?php echo number_format($count_account); ?></h1>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Student status summary cards -->
  <div class="row" style="margin-top:15px;">
    <div class="col-lg-3">
      <div class="ibox float-e-margins dang">
        <div class="ibox-title panel panel-warning">
          <span class="label label-warning pull-right">Total</span>
          <h5>All Students</h5>
        </div>
        <div class="ibox-content">
          <h1 class="no-margins"><?php echo number_format($stats['total_students']); ?></h1>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="ibox float-e-margins dang">
        <div class="ibox-title panel panel-success">
          <span class="label label-success pull-right">Enrolled</span>
          <h5>Enrolled Students</h5>
        </div>
        <div class="ibox-content">
          <h1 class="no-margins"><?php echo number_format($stats['enrolled_students']); ?></h1>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="ibox float-e-margins dang">
        <div class="ibox-title panel panel-info">
          <span class="label label-info pull-right">Graduated</span>
          <h5>Graduated Students</h5>
        </div>
        <div class="ibox-content">
          <h1 class="no-margins"><?php echo number_format($stats['graduated_students']); ?></h1>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="ibox float-e-margins dang">
        <div class="ibox-title panel panel-danger">
          <span class="label label-danger pull-right">Inactive</span>
          <h5>Inactive Students</h5>
        </div>
        <div class="ibox-content">
          <h1 class="no-margins"><?php echo number_format($stats['inactive_students']); ?></h1>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="row" style="margin-top:15px;">
    <div class="col-lg-6">
      <div class="chart-wrap">
        <div class="chart-title">Student Status Distribution</div>
        <canvas id="studentsPie" height="140"></canvas>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="chart-wrap">
        <div class="chart-title">Enrolled vs Graduated vs Inactive</div>
        <canvas id="studentsBar" height="140"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function () {
  const stats = {
    total: <?php echo (int)$stats['total_students']; ?>,
    enrolled: <?php echo (int)$stats['enrolled_students']; ?>,
    graduated: <?php echo (int)$stats['graduated_students']; ?>,
    inactive: <?php echo (int)$stats['inactive_students']; ?>
  };

  // PIE
  const pieCtx = document.getElementById('studentsPie');
  if (pieCtx) {
    new Chart(pieCtx, {
      type: 'pie',
      data: {
        labels: ['Enrolled', 'Graduated', 'Inactive'],
        datasets: [{
          data: [stats.enrolled, stats.graduated, stats.inactive]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                const value = ctx.raw || 0;
                const total = (stats.enrolled + stats.graduated + stats.inactive) || 1;
                const pct = Math.round((value / total) * 100);
                return ` ${ctx.label}: ${value} (${pct}%)`;
              }
            }
          }
        }
      }
    });
  }

  // BAR
  const barCtx = document.getElementById('studentsBar');
  if (barCtx) {
    new Chart(barCtx, {
      type: 'bar',
      data: {
        labels: ['Enrolled', 'Graduated', 'Inactive'],
        datasets: [{
          label: 'Students',
          data: [stats.enrolled, stats.graduated, stats.inactive]
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }
})();
</script>
