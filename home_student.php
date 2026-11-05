<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$userId    = $_SESSION[$systemname . "leido"] ?? '';
$firstname = $firstname ?? '';
$lastname  = $lastname  ?? '';

/* -- Course stats ----------------------------------------- */
$courseStats = ['total_courses' => 0, 'completed_courses' => 0, 'not_done_courses' => 0];
$cs = $conn->prepare("
    SELECT
        COUNT(*) AS total_courses,
        SUM(CASE WHEN cr.status='done' THEN 1 ELSE 0 END) AS completed_courses,
        SUM(CASE WHEN cr.status<>'done' OR cr.status IS NULL THEN 1 ELSE 0 END) AS not_done_courses
    FROM course_registration cr WHERE cr.user_id = ?
");
$cs->bind_param("s", $userId); $cs->execute();
$courseStats = $cs->get_result()->fetch_assoc() ?: $courseStats;
$cs->close();

/* -- Assignment stats ------------------------------------- */
$assStats = ['assigned' => 0, 'submitted' => 0, 'graded' => 0, 'pending' => 0];
$as = $conn->prepare("
    SELECT
        (SELECT COUNT(DISTINCT a.id) FROM assignments a
         JOIN course_unit cu ON cu.id=a.course_unit
         WHERE cu.course_id IN (SELECT cr2.course_id FROM course_registration cr2 WHERE cr2.user_id=?)) AS assigned,
        (SELECT COUNT(DISTINCT hi.id) FROM handed_in hi
         JOIN assignments a2 ON a2.id=hi.assignment_id
         JOIN course_unit cu2 ON cu2.id=a2.course_unit
         WHERE hi.user_id=? AND cu2.course_id IN (SELECT cr3.course_id FROM course_registration cr3 WHERE cr3.user_id=?)) AS submitted,
        (SELECT COUNT(DISTINCT hi2.id) FROM handed_in hi2
         JOIN assignments a3 ON a3.id=hi2.assignment_id
         JOIN course_unit cu3 ON cu3.id=a3.course_unit
         WHERE hi2.user_id=? AND hi2.finalmarks IS NOT NULL
         AND cu3.course_id IN (SELECT cr4.course_id FROM course_registration cr4 WHERE cr4.user_id=?)) AS graded
");
$as->bind_param("sssss", $userId, $userId, $userId, $userId, $userId);
$as->execute();
$row = $as->get_result()->fetch_assoc(); $as->close();
if ($row) {
    $assStats['assigned']  = (int)$row['assigned'];
    $assStats['submitted'] = (int)$row['submitted'];
    $assStats['graded']    = (int)$row['graded'];
    $assStats['pending']   = max(0, $assStats['submitted'] - $assStats['graded']);
}
$assNotSubmitted = max(0, $assStats['assigned'] - $assStats['submitted']);

/* -- Login logs ------------------------------------------- */
$loginLogs = [];
$logsStmt = $conn->prepare("SELECT login_time, ip_address, status FROM login_logs WHERE email=? ORDER BY login_time DESC LIMIT 10");
if ($logsStmt) {
    $email = $_SESSION[$systemname . "email"] ?? '';
    $logsStmt->bind_param("s", $email); $logsStmt->execute();
    $res = $logsStmt->get_result();
    while ($r = $res->fetch_assoc()) $loginLogs[] = $r;
    $logsStmt->close();
}

/* -- Todos ------------------------------------------------ */
$todos = [];
$todoStmt = $conn->prepare("SELECT id, title, details FROM todolist WHERE user_id=? AND valid='yes' ORDER BY id DESC LIMIT 5");
$todoStmt->bind_param("s", $userId); $todoStmt->execute();
$todoRes = $todoStmt->get_result();
while ($r = $todoRes->fetch_assoc()) $todos[] = $r;
$todoStmt->close();

/* -- Announcements ---------------------------------------- */
$announcements = [];
$annRes = $conn->query("SELECT id, post_name, description, post_image, post_link, created_at FROM annouments ORDER BY created_at DESC LIMIT 9");
if ($annRes) while ($r = $annRes->fetch_assoc()) $announcements[] = $r;
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

<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Dashboard</h1>
    <ol class="breadcrumb">
      <li class="active">Home</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Welcome hero --------------------------------------- -->
  <div class="dash-hero">
    <div class="dash-hero-left">
      <h2>Welcome back, <em><?php echo htmlspecialchars($firstname . ' ' . $lastname) ?></em></h2>
      <p><i class="fas fa-calendar-alt"></i> <?php echo date("l, d F Y") ?></p>
    </div>
    <div class="dash-support">
      <div class="dash-support-item">
        <span class="dash-support-tag"><i class="fas fa-phone-alt"></i> Support</span>
        <span class="dash-support-val">(+256) 392 177 980</span>
      </div>
      <div class="dash-support-item">
        <span class="dash-support-tag"><i class="fas fa-envelope"></i> Email</span>
        <span class="dash-support-val">info@witu.org &nbsp;/&nbsp; support@elevateher360.org</span>
      </div>
    </div>
  </div>

  <!-- -- Stats strip ---------------------------------------- -->
  <div class="dash-stats">
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Total Courses</span>
        <span class="dash-stat-tag dst-maroon"><i class="fas fa-book-open"></i> Courses</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$courseStats['total_courses'] ?></div>
      <div class="dash-stat-sub">Registered courses</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Completed</span>
        <span class="dash-stat-tag dst-green"><i class="fas fa-check-circle"></i> Courses</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$courseStats['completed_courses'] ?></div>
      <div class="dash-stat-sub">Status = done</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">In Progress</span>
        <span class="dash-stat-tag dst-gold"><i class="fas fa-spinner"></i> Courses</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$courseStats['not_done_courses'] ?></div>
      <div class="dash-stat-sub">Not yet completed</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Assigned</span>
        <span class="dash-stat-tag dst-gold"><i class="fas fa-tasks"></i> Tasks</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$assStats['assigned'] ?></div>
      <div class="dash-stat-sub">Assignments in your courses</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Submitted</span>
        <span class="dash-stat-tag dst-green"><i class="fas fa-paper-plane"></i> Tasks</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$assStats['submitted'] ?></div>
      <div class="dash-stat-sub">Total submissions</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Graded</span>
        <span class="dash-stat-tag dst-green"><i class="fas fa-star"></i> Marks</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$assStats['graded'] ?></div>
      <div class="dash-stat-sub">Final marks awarded</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Pending Grading</span>
        <span class="dash-stat-tag dst-gold"><i class="fas fa-hourglass-half"></i> Marks</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$assStats['pending'] ?></div>
      <div class="dash-stat-sub">Submitted but not graded</div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Not Submitted</span>
        <span class="dash-stat-tag dst-red"><i class="fas fa-exclamation-circle"></i> Tasks</span>
      </div>
      <div class="dash-stat-num"><?php echo (int)$assNotSubmitted ?></div>
      <div class="dash-stat-sub">Assigned minus submitted</div>
    </div>
  </div>

  <!-- -- Main grid ------------------------------------------ -->
  <div class="dash-grid">

    <!-- LEFT: tabbed content card -->
    <div>
      <div class="dash-content-card">

        <!-- Tab bar -->
        <div class="dash-tab-bar">
          <button class="dash-tab-btn active" onclick="dTab('ann', this)">
            <i class="fas fa-bullhorn"></i> Announcements
            <span class="dash-pill"><?php echo count($announcements) ?></span>
          </button>
          <button class="dash-tab-btn" onclick="dTab('assign-chart', this)">
            <i class="fas fa-tasks"></i> Assignments Progress
          </button>
          <button class="dash-tab-btn" onclick="dTab('course-chart', this)">
            <i class="fas fa-graduation-cap"></i> Courses Progress
          </button>
        </div>

        <!-- -- Tab: Announcements ---------------------------- -->
        <div id="tab-ann" class="dash-tab-panel active">
          <div class="ann-toolbar">
            <span class="ann-toolbar-title">
              <i class="fas fa-bullhorn"></i> Latest Announcements
            </span>
            <a href="?view=announcements" class="btn-view-all">
              <i class="fas fa-arrow-right"></i> View All
            </a>
          </div>

          <?php if (empty($announcements)): ?>
            <div class="empty">
              <i class="fas fa-bell-slash"></i>
              <p>No announcements at the moment.</p>
            </div>
          <?php else: ?>
            <div class="ann-grid">
              <?php foreach ($announcements as $idx => $a): ?>
              <div class="ann-card" <?php echo $idx >= 6 ? 'class="ann-card d-none" data-extra' : 'class="ann-card"' ?>>
                <?php if (!empty($a['post_image'])): ?>
                  <img src="<?php echo htmlspecialchars($a['post_image']) ?>" class="ann-card-img" alt="Announcement">
                <?php else: ?>
                  <div class="ann-card-img-placeholder"><i class="fas fa-bullhorn"></i></div>
                <?php endif; ?>
                <div class="ann-card-body">
                  <div class="ann-card-title"><?php echo htmlspecialchars($a['post_name']) ?></div>
                  <div class="ann-card-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo !empty($a['created_at']) ? date('M d, Y', strtotime($a['created_at'])) : '' ?>
                  </div>
                  <div class="ann-card-desc">
                    <?php
                      $desc = htmlspecialchars(trim((string)$a['description']));
                      echo strlen($desc) > 110 ? substr($desc, 0, 110) . '...' : $desc;
                    ?>
                  </div>
                </div>
                <?php if (!empty($a['post_link'])): ?>
                <div class="ann-card-footer">
                  <a href="<?php echo htmlspecialchars($a['post_link']) ?>" target="_blank" class="btn-ann-link">
                    <i class="fas fa-external-link-alt"></i> View Details
                  </a>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- -- Tab: Assignments Progress -------------------- -->
        <div id="tab-assign-chart" class="dash-tab-panel">
          <div class="chart-grid">
            <div class="chart-box">
              <div class="chart-box-title"><i class="fas fa-chart-pie"></i> Submission Breakdown</div>
              <canvas id="assignmentsChart" height="200"></canvas>
              <div class="chart-legend">
                <span class="chart-legend-item"><span class="chart-legend-dot" class="chart-legend-dot cld-green"></span>Graded</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" class="chart-legend-dot cld-gold"></span>Submitted (awaiting)</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" class="chart-legend-dot cld-red"></span>Not Submitted</span>
              </div>
            </div>
            <div class="chart-box">
              <div class="chart-box-title"><i class="fas fa-chart-bar"></i> Submission Summary</div>
              <canvas id="assBarChart" height="200"></canvas>
              <div class="chart-legend">
                <span class="chart-legend-item"><span class="chart-legend-dot" class="chart-legend-dot cld-maroon"></span>Count</span>
              </div>
            </div>
          </div>
        </div>

        <!-- -- Tab: Courses Progress ------------------------- -->
        <div id="tab-course-chart" class="dash-tab-panel">
          <div class="chart-grid">
            <div class="chart-box">
              <div class="chart-box-title"><i class="fas fa-chart-pie"></i> Course Status</div>
              <canvas id="coursesDoughnut" height="200"></canvas>
              <div class="chart-legend">
                <span class="chart-legend-item"><span class="chart-legend-dot" class="chart-legend-dot cld-green"></span>Completed</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" class="chart-legend-dot cld-gold"></span>In Progress</span>
              </div>
            </div>
            <div class="chart-box">
              <div class="chart-box-title"><i class="fas fa-chart-bar"></i> Courses Bar</div>
              <canvas id="coursesChart" height="200"></canvas>
            </div>
          </div>
        </div>

      </div><!-- /.dash-content-card -->
    </div><!-- /left col -->

    <!-- RIGHT: sidebar widgets -->
    <div>

      <!-- Todo widget -->
      <div class="todo-widget">
        <div class="todo-widget-head">
          <h3><i class="fas fa-check-square"></i> My To-Do List</h3>
          <a href="?view=todo" class="btn-todo-all"><i class="fas fa-arrow-right"></i> All</a>
        </div>
        <div class="todo-list-body">
          <?php if (empty($todos)): ?>
            <div class="todo-empty">
              <i class="fas fa-inbox"></i>
              To-do list is empty.
            </div>
          <?php else: ?>
            <?php foreach ($todos as $t): ?>
            <div class="todo-item">
              <span class="todo-dot"></span>
              <div class="todo-body">
                <div class="todo-title"><?php echo htmlspecialchars($t['title']) ?></div>
                <div class="todo-detail"><?php echo htmlspecialchars($t['details']) ?></div>
              </div>
              <a href="scripts/deletenote.php?note=<?php echo (int)$t['id'] ?>" class="todo-del" title="Delete">
                <i class="fas fa-trash-alt"></i>
              </a>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick links -->
      <div class="quick-links">
        <div class="quick-links-head"><i class="fas fa-bolt"></i> Quick Links</div>
        <a href="?view=courses"       class="quick-link-item"><i class="fas fa-book-open"></i><span>My Courses</span><i class="fas fa-chevron-right ql-arrow"></i></a>
        <a href="?view=my_assignment" class="quick-link-item"><i class="fas fa-tasks"></i><span>My Assignments</span><i class="fas fa-chevron-right ql-arrow"></i></a>
        <a href="?view=resources"     class="quick-link-item"><i class="fas fa-folder-open"></i><span>Resources</span><i class="fas fa-chevron-right ql-arrow"></i></a>
        <a href="?view=stickynotes"   class="quick-link-item"><i class="fas fa-sticky-note"></i><span>Sticky Notes</span><i class="fas fa-chevron-right ql-arrow"></i></a>
        <a href="?view=chatrooms"     class="quick-link-item"><i class="fas fa-comments"></i><span>Chatrooms</span><i class="fas fa-chevron-right ql-arrow"></i></a>
        <a href="?view=announcements" class="quick-link-item"><i class="fas fa-bullhorn"></i><span>All Announcements</span><i class="fas fa-chevron-right ql-arrow"></i></a>
      </div>

    </div><!-- /right col -->

  </div><!-- /.dash-grid -->

</main>

<!-- -- Chart.js --------------------------------------------- -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* -- Tab switching ---------------------------------------- */
function dTab(name, btn) {
  document.querySelectorAll('.dash-tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.dash-tab-btn').forEach(function(b)   { b.classList.remove('active'); });
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
  /* Resize charts when their panel becomes visible */
  if (name === 'assign-chart' || name === 'course-chart') {
    window.dispatchEvent(new Event('resize'));
  }
}

/* -- Chart data ------------------------------------------- */
const GRADED        = <?php echo (int)$assStats['graded'] ?>;
const PENDING       = <?php echo (int)$assStats['pending'] ?>;
const NOT_SUBMITTED = <?php echo (int)$assNotSubmitted ?>;
const SUBMITTED     = <?php echo (int)$assStats['submitted'] ?>;
const ASSIGNED      = <?php echo (int)$assStats['assigned'] ?>;
const C_TOTAL       = <?php echo (int)$courseStats['total_courses'] ?>;
const C_DONE        = <?php echo (int)$courseStats['completed_courses'] ?>;
const C_PROG        = <?php echo (int)$courseStats['not_done_courses'] ?>;

const MAROON  = '#7a1919';
const GOLD    = '#c9a84c';
const GREEN   = '#156f3f';
const RED     = '#a81515';
const SURFACE = '#f7f8fa';

const BASE_OPTS = {
  responsive: true,
  animation: { duration: 500 },
  plugins: { legend: { display: false } }
};

document.addEventListener('DOMContentLoaded', function () {

  /* 1. Assignments doughnut */
  new Chart(document.getElementById('assignmentsChart'), {
    type: 'doughnut',
    data: {
      labels: ['Graded', 'Submitted (awaiting)', 'Not Submitted'],
      datasets: [{ data: [GRADED, PENDING, NOT_SUBMITTED],
        backgroundColor: [GREEN, GOLD, RED],
        borderColor: '#fff', borderWidth: 3,
        hoverOffset: 8
      }]
    },
    options: { ...BASE_OPTS, cutout: '65%' }
  });

  /* 2. Assignments bar */
  new Chart(document.getElementById('assBarChart'), {
    type: 'bar',
    data: {
      labels: ['Assigned', 'Submitted', 'Graded', 'Pending', 'Not Submitted'],
      datasets: [{
        label: 'Count',
        data: [ASSIGNED, SUBMITTED, GRADED, PENDING, NOT_SUBMITTED],
        backgroundColor: [MAROON, GOLD, GREEN, '#b07d20', RED],
        borderRadius: 5, borderSkipped: false
      }]
    },
    options: {
      ...BASE_OPTS,
      scales: {
        y: { beginAtZero: true, grid: { color: SURFACE }, ticks: { precision: 0, font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
      }
    }
  });

  /* 3. Courses doughnut */
  new Chart(document.getElementById('coursesDoughnut'), {
    type: 'doughnut',
    data: {
      labels: ['Completed', 'In Progress'],
      datasets: [{ data: [C_DONE, C_PROG],
        backgroundColor: [GREEN, GOLD],
        borderColor: '#fff', borderWidth: 3,
        hoverOffset: 8
      }]
    },
    options: { ...BASE_OPTS, cutout: '65%' }
  });

  /* 4. Courses bar */
  new Chart(document.getElementById('coursesChart'), {
    type: 'bar',
    data: {
      labels: ['Total', 'Completed', 'In Progress'],
      datasets: [{
        label: 'Courses',
        data: [C_TOTAL, C_DONE, C_PROG],
        backgroundColor: [MAROON, GREEN, GOLD],
        borderRadius: 5, borderSkipped: false
      }]
    },
    options: {
      ...BASE_OPTS,
      scales: {
        y: { beginAtZero: true, grid: { color: SURFACE }, ticks: { precision: 0, font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
      }
    }
  });

});
</script>
</body>
</html>