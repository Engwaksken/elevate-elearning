<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$user_id = $_SESSION[$systemname . "leido"] ?? null;
if (!$user_id) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Session expired. Please <a href="?view=login">login again</a>.</div>';
    exit;
}

function oneInt(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($types !== '' && !empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

$total_students = oneInt($conn,
    "SELECT COUNT(*) AS total FROM users WHERE acc_type='afgezt'"
);
$total_assigned_courses = oneInt($conn,
    "SELECT COUNT(DISTINCT course_id) AS total FROM course_registration_lecturers WHERE user_id=?",
    "s", [$user_id]
);
$students_in_assigned_courses = oneInt($conn,
    "SELECT COUNT(DISTINCT cr.user_id) AS total
     FROM course_registration cr
     WHERE EXISTS (
       SELECT 1 FROM course_registration_lecturers crl
       WHERE crl.course_id=cr.course_id AND crl.user_id=?
     )",
    "s", [$user_id]
);
$total_assignments = oneInt($conn,
    "SELECT COUNT(*) AS total
     FROM assignments a
     JOIN course_unit cu ON cu.id=a.course_unit
     WHERE EXISTS (
       SELECT 1 FROM course_registration_lecturers crl
       WHERE crl.course_id=cu.course_id AND crl.user_id=?
     )",
    "s", [$user_id]
);
$graduated_students = oneInt($conn,
    "SELECT COUNT(DISTINCT cr.user_id) AS total
     FROM course_registration cr
     WHERE cr.status='done'
       AND EXISTS (
         SELECT 1 FROM course_registration_lecturers crl
         WHERE crl.course_id=cr.course_id AND crl.user_id=?
       )",
    "s", [$user_id]
);
$not_done_students = oneInt($conn,
    "SELECT COUNT(DISTINCT cr.user_id) AS total
     FROM course_registration cr
     WHERE (cr.status IS NULL OR cr.status <> 'done')
       AND EXISTS (
         SELECT 1 FROM course_registration_lecturers crl
         WHERE crl.course_id=cr.course_id AND crl.user_id=?
       )",
    "s", [$user_id]
);
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
<div class="page" style="padding-top:0;">

  <!-- -- Stats strip -------------------------------------- -->
  <div class="dash-stats" style="margin-bottom:20px;">

    <a href="./?view=my_students" class="dash-stat" style="text-decoration:none; cursor:pointer;">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Total Students</span>
        <span class="dash-stat-tag dst-maroon"><i class="fas fa-users"></i></span>
      </div>
      <div class="dash-stat-num"><?php echo number_format($total_students) ?></div>
      <div class="dash-stat-sub">All students in system</div>
    </a>

    <a href="./?view=my_students" class="dash-stat" style="text-decoration:none; cursor:pointer;">
      <div class="dash-stat-header">
        <span class="dash-stat-label">My Students</span>
        <span class="dash-stat-tag dst-gold"><i class="fas fa-user-graduate"></i></span>
      </div>
      <div class="dash-stat-num" style="color:var(--gold);"><?php echo number_format($students_in_assigned_courses) ?></div>
      <div class="dash-stat-sub">Enrolled in my courses</div>
    </a>

    <a href="./?view=all_assignments" class="dash-stat" style="text-decoration:none; cursor:pointer;">
      <div class="dash-stat-header">
        <span class="dash-stat-label">Assignments</span>
        <span class="dash-stat-tag dst-maroon"><i class="fas fa-tasks"></i></span>
      </div>
      <div class="dash-stat-num"><?php echo number_format($total_assignments) ?></div>
      <div class="dash-stat-sub">Under my courses</div>
    </a>

    <div class="dash-stat">
      <div class="dash-stat-header">
        <span class="dash-stat-label">My Courses</span>
        <span class="dash-stat-tag dst-gold"><i class="fas fa-book"></i></span>
      </div>
      <div class="dash-stat-num" style="color:var(--gold);"><?php echo number_format($total_assigned_courses) ?></div>
      <div class="dash-stat-sub">Assigned courses</div>
    </div>

  </div>

  <!-- -- Second stats row --------------------------------- -->
  <div class="stats" style="margin-bottom:24px;">

    <div class="stat-card">
      <div class="stat-icon done"><i class="fas fa-graduation-cap"></i></div>
      <div>
        <div class="sv green"><?php echo number_format($graduated_students) ?></div>
        <div class="sl">Graduated</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon cancel"><i class="fas fa-spinner"></i></div>
      <div>
        <div class="sv red"><?php echo number_format($not_done_students) ?></div>
        <div class="sl">In Progress</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon prog"><i class="fas fa-percentage"></i></div>
      <div>
        <?php
        $total_registered = $graduated_students + $not_done_students;
        $completion_rate  = $total_registered > 0 ? round(($graduated_students / $total_registered) * 100) : 0;
        ?>
        <div class="sv gold"><?php echo $completion_rate ?>%</div>
        <div class="sl">Completion Rate</div>
      </div>
    </div>

  </div>

  <!-- -- Charts card with tabs ---------------------------- -->
  <div class="dash-content-card">

    <!-- Tab bar -->
    <div class="dash-tab-bar" role="tablist">
      <button class="dash-tab-btn active" id="tab-btn-pie" role="tab"
              onclick="switchTab('pie')" aria-selected="true">
        <i class="fas fa-chart-pie"></i> Completion Breakdown
        <span class="dash-pill"><?php echo $total_registered ?></span>
      </button>
      <button class="dash-tab-btn" id="tab-btn-bar" role="tab"
              onclick="switchTab('bar')" aria-selected="false">
        <i class="fas fa-chart-bar"></i> Dashboard Summary
        <span class="dash-pill">4</span>
      </button>
    </div>

    <!-- Tab: Pie / Doughnut ------------------------------- -->
    <div class="dash-tab-panel active" id="tab-panel-pie" role="tabpanel">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center;">

        <!-- Chart -->
        <div style="max-width:300px; margin:0 auto; width:100%;">
          <canvas id="myStudentsPie"></canvas>
        </div>

        <!-- Legend + detail -->
        <div>
          <div class="chart-box-title" style="margin-bottom:16px;">
            <i class="fas fa-chart-pie"></i> My Course Students
          </div>

          <div style="display:flex; flex-direction:column; gap:12px;">

            <div style="display:flex; align-items:center; justify-content:space-between;
                        background:var(--green-bg); border:1px solid rgba(21,111,63,.15);
                        border-radius:var(--radius); padding:12px 16px;">
              <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:12px;height:12px;border-radius:50%;background:var(--green);display:inline-block;flex-shrink:0;"></span>
                <div>
                  <div style="font-size:.82rem; font-weight:600; color:var(--text);">Graduated</div>
                  <div style="font-size:.72rem; color:var(--muted);">Status = done</div>
                </div>
              </div>
              <div style="font-family:var(--font-serif); font-size:1.6rem; color:var(--green); line-height:1;">
                <?php echo number_format($graduated_students) ?>
              </div>
            </div>

            <div style="display:flex; align-items:center; justify-content:space-between;
                        background:var(--red-bg); border:1px solid rgba(168,21,21,.15);
                        border-radius:var(--radius); padding:12px 16px;">
              <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:12px;height:12px;border-radius:50%;background:var(--red);display:inline-block;flex-shrink:0;"></span>
                <div>
                  <div style="font-size:.82rem; font-weight:600; color:var(--text);">In Progress</div>
                  <div style="font-size:.72rem; color:var(--muted);">Status &ne; done</div>
                </div>
              </div>
              <div style="font-family:var(--font-serif); font-size:1.6rem; color:var(--red); line-height:1;">
                <?php echo number_format($not_done_students) ?>
              </div>
            </div>

            <div style="background:var(--surface2); border:1px solid var(--border);
                        border-radius:var(--radius); padding:10px 16px; text-align:center;">
              <div style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin-bottom:4px;">Completion Rate</div>
              <div style="font-family:var(--font-serif); font-size:2rem; color:var(--maroon); line-height:1;"><?php echo $completion_rate ?>%</div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Bar / Summary -------------------------------- -->
    <div class="dash-tab-panel" id="tab-panel-bar" role="tabpanel">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:start;">

        <!-- Chart -->
        <div>
          <canvas id="mySummaryBar"></canvas>
        </div>

        <!-- Detail cards -->
        <div style="display:flex; flex-direction:column; gap:10px;">

          <?php
          $summary_items = [
            ['label' => 'Total Students',      'value' => $total_students,                  'icon' => 'fa-users',          'cls' => 'maroon', 'bg' => 'var(--maroon-dim)', 'color' => 'var(--maroon)'],
            ['label' => 'Students in Courses', 'value' => $students_in_assigned_courses,    'icon' => 'fa-user-graduate',  'cls' => 'gold',   'bg' => 'var(--gold-dim)',   'color' => 'var(--gold)'],
            ['label' => 'My Assignments',      'value' => $total_assignments,               'icon' => 'fa-tasks',          'cls' => 'maroon', 'bg' => 'var(--maroon-dim)', 'color' => 'var(--maroon)'],
            ['label' => 'My Courses',          'value' => $total_assigned_courses,          'icon' => 'fa-book',           'cls' => 'gold',   'bg' => 'var(--gold-dim)',   'color' => 'var(--gold)'],
          ];
          foreach ($summary_items as $item): ?>
          <div style="display:flex; align-items:center; gap:12px; background:var(--surface2);
                      border:1px solid var(--border); border-radius:var(--radius); padding:11px 14px;">
            <div style="width:34px;height:34px;border-radius:8px;background:<?php echo $item['bg'] ?>;
                        display:grid;place-items:center;flex-shrink:0;color:<?php echo $item['color'] ?>;font-size:.88rem;">
              <i class="fas <?php echo $item['icon'] ?>"></i>
            </div>
            <div style="flex:1;">
              <div style="font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; font-weight:700;"><?php echo $item['label'] ?></div>
            </div>
            <div style="font-family:var(--font-serif); font-size:1.5rem; color:<?php echo $item['color'] ?>; line-height:1;">
              <?php echo number_format($item['value']) ?>
            </div>
          </div>
          <?php endforeach; ?>

        </div>
      </div>
    </div>

  </div><!-- /.dash-content-card -->

</div><!-- /.page -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  /* -- Data from PHP ------------------------------------ */
  const graduated   = <?php echo (int)$graduated_students ?>;
  const notDone     = <?php echo (int)$not_done_students ?>;
  const totalStud   = <?php echo (int)$total_students ?>;
  const myStud      = <?php echo (int)$students_in_assigned_courses ?>;
  const assignments = <?php echo (int)$total_assignments ?>;
  const courses     = <?php echo (int)$total_assigned_courses ?>;

  /* -- CSS variable helper ------------------------------ */
  function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }

  /* -- Shared Chart.js defaults ------------------------- */
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.font.size   = 12;

  /* -- Doughnut: Graduated vs In Progress --------------- */
  new Chart(document.getElementById('myStudentsPie'), {
    type: 'doughnut',
    data: {
      labels: ['Graduated', 'In Progress'],
      datasets: [{
        data: [graduated, notDone],
        backgroundColor: [cssVar('--green'), cssVar('--red')],
        borderColor:     ['#fff', '#fff'],
        borderWidth: 3,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      cutout: '65%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              var val = ctx.raw || 0;
              var sum = (graduated + notDone) || 1;
              var pct = Math.round((val / sum) * 100);
              return ' ' + ctx.label + ': ' + val + ' (' + pct + '%)';
            }
          }
        }
      }
    }
  });

  /* -- Bar: Dashboard Summary --------------------------- */
  new Chart(document.getElementById('mySummaryBar'), {
    type: 'bar',
    data: {
      labels: ['Total\nStudents', 'My\nStudents', 'Assignments', 'Courses'],
      datasets: [{
        label: 'Count',
        data: [totalStud, myStud, assignments, courses],
        backgroundColor: [
          cssVar('--maroon-dim'),
          cssVar('--gold-dim'),
          cssVar('--maroon-dim'),
          cssVar('--gold-dim')
        ],
        borderColor: [
          cssVar('--maroon'),
          cssVar('--gold'),
          cssVar('--maroon'),
          cssVar('--gold')
        ],
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx) { return ' ' + ctx.dataset.label + ': ' + ctx.raw.toLocaleString(); }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0, color: cssVar('--muted') },
          grid: { color: cssVar('--border') }
        },
        x: {
          ticks: { color: cssVar('--muted') },
          grid: { display: false }
        }
      }
    }
  });

  /* -- Tab switching ------------------------------------ */
  window.switchTab = function(tab) {
    ['pie','bar'].forEach(function(t) {
      var btn   = document.getElementById('tab-btn-'   + t);
      var panel = document.getElementById('tab-panel-' + t);
      var active = (t === tab);
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active);
      panel.classList.toggle('active', active);
    });
  };
})();
</script>