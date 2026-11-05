<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION[$systemname . "leido"])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION[$systemname . "leido"];

// Pre-fetch all rows
$stmt = $conn->prepare("SELECT * FROM course_registration_lecturers WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$all_rows = $progress_rows = $done_rows = $cancelled_rows = [];

while ($dc = $result->fetch_assoc()) {
    $course_id = $dc['course_id'];
    $status    = $dc['status'];
    $intake_id = $dc['intake'];

    // Course details
    $sc = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $sc->bind_param("i", $course_id);
    $sc->execute();
    $cr = $sc->get_result();
    if ($cr->num_rows < 1) { $sc->close(); continue; }
    $course_data = $cr->fetch_assoc();
    $sc->close();

    // Category
    $stc = $conn->prepare("SELECT name FROM course_category WHERE id = ?");
    $stc->bind_param("i", $course_data['category']);
    $stc->execute();
    $cr2 = $stc->get_result();
    $cat_name = ($cr2->num_rows > 0) ? htmlspecialchars($cr2->fetch_assoc()['name']) : 'Uncategorised';
    $stc->close();

    // Intake / module
    $stm = $conn->prepare("SELECT name FROM course_unit WHERE id = ?");
    $stm->bind_param("i", $intake_id);
    $stm->execute();
    $cr3 = $stm->get_result();
    $intake_name = ($cr3->num_rows > 0) ? htmlspecialchars($cr3->fetch_assoc()['name']) : 'N/A';
    $stm->close();

    $entry = [
        'course_id'   => $course_id,
        'status'      => $status,
        'name'        => $course_data['name'],
        'short_code'  => $course_data['short_code'],
        'duration'    => $course_data['duration'],
        'category'    => $cat_name,
        'intake'      => $intake_name,
    ];

    $all_rows[] = $entry;
    if ($status === 'complete')       $done_rows[]      = $entry;
    elseif ($status === 'cancelled')  $cancelled_rows[] = $entry;
    else                              $progress_rows[]  = $entry;
}
$stmt->close();

$fa_icons = ['fa-book','fa-flask','fa-laptop-code','fa-chart-bar',
             'fa-globe','fa-landmark','fa-palette','fa-cogs',
             'fa-pen-nib','fa-microscope'];

function lec_icon($cat){
    global $fa_icons;
    return $fa_icons[abs(crc32($cat)) % count($fa_icons)];
}

function lec_render_cards($list){
    if (empty($list)) {
        echo '<div class="empty">
                <i class="fas fa-folder-open"></i>
                <p>No courses in this category.</p>
              </div>';
        return;
    }
    echo '<div class="course-grid">';
    $d = 0;
    foreach ($list as $e):
        $icon = lec_icon($e['category']);
        $s    = $e['status'];
        if ($s === 'complete')
            $badge = '<span class="status-badge badge-done"><i class="fas fa-check"></i> Completed</span>';
        elseif ($s === 'cancelled')
            $badge = '<span class="status-badge badge-cancel"><i class="fas fa-ban"></i> Cancelled</span>';
        else
            $badge = '<span class="status-badge badge-progress"><i class="fas fa-circle-notch fa-spin"></i> In Progress</span>';
?>
      <a href="?view=open_coursex&course=<?php echo urlencode($e['course_id']) ?>"
         class="course-card"
         style="animation-delay:<?php echo $d * 50 ?>ms">

        <div class="card-top">
          <span class="course-icon"><i class="fas <?php echo $icon ?>"></i></span>
          <?php echo $badge ?>
        </div>

        <div>
          <div class="course-name"><?php echo htmlspecialchars($e['name']) ?></div>
          <div class="course-code"><?php echo htmlspecialchars($e['short_code']) ?></div>
        </div>

        <div class="meta-row">
          <span class="meta-item">
            <i class="fas fa-layer-group"></i>
            <?php echo $e['intake'] ?>
          </span>
          <span class="meta-item">
            <i class="fas fa-clock"></i>
            <?php echo htmlspecialchars($e['duration']) ?>
          </span>
        </div>

        <div class="card-divider"></div>

        <div class="card-footer">
          <span class="cat-tag"><i class="fas fa-tag"></i><?php echo $e['category'] ?></span>
          <span class="open-link">Open <i class="fas fa-chevron-right"></i></span>
        </div>

      </a>
<?php
        $d++;
    endforeach;
    echo '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
</head>
<body>

<!-- Topbar -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($systemname) ?> - Instructor Courses</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="#">Courses</a></li>
      <li class="active">My Courses</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- Instructor banner -->
  <div class="instructor-badge">
    <i class="fas fa-user-tie"></i>
    <span>Instructor view - <strong>Assigned courses only</strong></span>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <span class="stat-icon total"><i class="fas fa-book-open"></i></span>
      <div><div class="sv"><?php echo count($all_rows) ?></div><div class="sl">Total Assigned</div></div>
    </div>
    <div class="stat-card">
      <span class="stat-icon prog"><i class="fas fa-spinner"></i></span>
      <div><div class="sv gold"><?php echo count($progress_rows) ?></div><div class="sl">In Progress</div></div>
    </div>
    <div class="stat-card">
      <span class="stat-icon done"><i class="fas fa-check-circle"></i></span>
      <div><div class="sv green"><?php echo count($done_rows) ?></div><div class="sl">Completed</div></div>
    </div>
    <div class="stat-card">
      <span class="stat-icon cancel"><i class="fas fa-times-circle"></i></span>
      <div><div class="sv red"><?php echo count($cancelled_rows) ?></div><div class="sl">Cancelled</div></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('all', this)">
      <i class="fas fa-layer-group"></i> All
      <span class="pill"><?php echo count($all_rows) ?></span>
    </button>
    <button class="tab-btn" onclick="switchTab('progress', this)">
      <i class="fas fa-spinner"></i> In Progress
      <span class="pill"><?php echo count($progress_rows) ?></span>
    </button>
    <button class="tab-btn" onclick="switchTab('done', this)">
      <i class="fas fa-check-circle"></i> Completed
      <span class="pill"><?php echo count($done_rows) ?></span>
    </button>
    <button class="tab-btn" onclick="switchTab('cancelled', this)">
      <i class="fas fa-ban"></i> Cancelled
      <span class="pill"><?php echo count($cancelled_rows) ?></span>
    </button>
  </div>

  <!-- Tab panels -->
  <div id="tab-all"       class="tab-panel active"><?php lec_render_cards($all_rows) ?></div>
  <div id="tab-progress"  class="tab-panel">       <?php lec_render_cards($progress_rows) ?></div>
  <div id="tab-done"      class="tab-panel">       <?php lec_render_cards($done_rows) ?></div>
  <div id="tab-cancelled" class="tab-panel">       <?php lec_render_cards($cancelled_rows) ?></div>

</main>

<script>
function switchTab(name, btn){
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>