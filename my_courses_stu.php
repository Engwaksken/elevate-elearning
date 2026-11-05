<?php
$q     = "SELECT * FROM course_registration WHERE `user_id`='" . $_SESSION[$systemname."leido"] . "'";
$qq    = $conn->query($q) or die(mysqli_error());
$total = $qq->num_rows;

$qw  = "SELECT * FROM course_registration
        WHERE `user_id`='" . $_SESSION[$systemname."leido"] . "'
        ORDER BY `id` DESC";
$qqw = $conn->query($qw) or die(mysqli_error());

// Pre-fetch all rows + resolve course/category data
$done_rows = $progress_rows = $cancelled_rows = [];
$all_rows  = [];

while ($r = mysqli_fetch_array($qqw)) {
    $cy         = $r['course_id'];
    $date_added = $r['date_added'];
    $status     = $r['status'];

    $a = "SELECT * FROM courses WHERE `id`='" . $cy . "'";
    $b = $conn->query($a) or die(mysqli_error());
    if ($b->num_rows < 1) continue;
    $d = mysqli_fetch_array($b);

    $p = "SELECT * FROM course_category WHERE id='" . $d['category'] . "'";
    $z = $conn->query($p) or die(mysqli_error());
    $cat_name = ($z->num_rows > 0) ? mysqli_fetch_array($z)['name'] : 'Uncategorised';

    $entry = [
        'course_id'  => $cy,
        'date_added' => $date_added,
        'status'     => $status,
        'name'       => $d['name'],
        'short_code' => $d['short_code'],
        'duration'   => $d['duration'],
        'category'   => $cat_name,
    ];

    $all_rows[] = $entry;
    if ($status === 'done')          $done_rows[]     = $entry;
    elseif ($status === 'cancelled') $cancelled_rows[] = $entry;
    else                             $progress_rows[]  = $entry;
}

$fa_icons = ['fa-book','fa-flask','fa-laptop-code','fa-chart-bar',
             'fa-globe','fa-landmark','fa-palette','fa-cogs',
             'fa-pen-nib','fa-microscope'];

function course_icon($cat){ 
    global $fa_icons;
    return $fa_icons[abs(crc32($cat)) % count($fa_icons)];
}

function render_cards($list, $delay_start = 0){
    if(empty($list)){
        echo '<div class="empty">
                <i class="fas fa-folder-open"></i>
                <p>No courses in this category yet.</p>
              </div>';
        return;
    }
    echo '<div class="course-grid">';
    $d = $delay_start;
    foreach($list as $e):
        $icon = course_icon($e['category']);
        $s    = $e['status'];
        if ($s === 'done')
            $badge = '<span class="status-badge badge-done"><i class="fas fa-check"></i> Completed</span>';
        elseif ($s === 'cancelled')
            $badge = '<span class="status-badge badge-cancel"><i class="fas fa-ban"></i> Cancelled</span>';
        else
            $badge = '<span class="status-badge badge-progress"><i class="fas fa-circle-notch fa-spin"></i> In Progress</span>';
    ?>
      <a href="?view=open_course&course=<?php echo $e['course_id'] ?>"
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
          <span class="meta-item"><i class="fas fa-clock"></i><?php echo htmlspecialchars($e['duration']) ?></span>
          <span class="meta-item"><i class="fas fa-calendar-alt"></i><?php echo date('M d, Y', strtotime($e['date_added'])) ?></span>
        </div>

        <div class="card-divider"></div>

        <div class="card-footer">
          <span class="cat-tag"><i class="fas fa-tag"></i><?php echo htmlspecialchars($e['category']) ?></span>
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

<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-graduation-cap"></i> <?php echo $systemname ?> - Courses</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="#">Courses</a></li>
      <li class="active">My Courses</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <span class="stat-icon total"><i class="fas fa-book-open"></i></span>
      <div><div class="sv"><?php echo $total ?></div><div class="sl">Total</div></div>
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

  <!-- Tab bar -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('all', this)">
      <i class="fas fa-layer-group"></i> All Courses
      <span class="pill"><?php echo $total ?></span>
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

  <!-- Tab: All -->
  <div id="tab-all" class="tab-panel active">
    <?php render_cards($all_rows) ?>
  </div>

  <!-- Tab: In Progress -->
  <div id="tab-progress" class="tab-panel">
    <?php render_cards($progress_rows) ?>
  </div>

  <!-- Tab: Completed -->
  <div id="tab-done" class="tab-panel">
    <?php render_cards($done_rows) ?>
  </div>

  <!-- Tab: Cancelled -->
  <div id="tab-cancelled" class="tab-panel">
    <?php render_cards($cancelled_rows) ?>
  </div>

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