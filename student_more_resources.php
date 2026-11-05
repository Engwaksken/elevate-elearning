<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* -- Auth ------------------------------------------------- */
if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-lock"></i> Please log in. <a href="?view=login">Login</a></div>';
    exit;
}
$current_user_id = $_SESSION[$systemname . "leido"];

/* -- User ------------------------------------------------- */
$us = $conn->prepare("SELECT fname, lname FROM users WHERE unique_id=? LIMIT 1");
$us->bind_param("s", $current_user_id); $us->execute();
$uData = $us->get_result()->fetch_assoc(); $us->close();
if (!$uData) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> User not found.</div>';
    exit;
}
$student_name = e($uData['fname'] . ' ' . $uData['lname']);

/* -- Registered courses ----------------------------------- */
$cs = $conn->prepare("SELECT c.id, c.name FROM course_registration cr JOIN courses c ON cr.course_id=c.id WHERE cr.user_id=? AND cr.status!='done' ORDER BY c.name ASC");
$cs->bind_param("s", $current_user_id); $cs->execute();
$courses = $cs->get_result()->fetch_all(MYSQLI_ASSOC); $cs->close();

if (empty($courses)) {
    echo '<div class="app-alert app-alert-info"><i class="fas fa-info-circle"></i> You are not registered in any active course.</div>';
    exit;
}

/* -- Selected course -------------------------------------- */
$selected_course_id = isset($_GET['course_id']) && is_numeric($_GET['course_id'])
    ? (int)$_GET['course_id'] : (int)$courses[0]['id'];

// Confirm selected course belongs to this student
$valid_ids = array_column($courses, 'id');
if (!in_array($selected_course_id, $valid_ids)) $selected_course_id = (int)$courses[0]['id'];

$selected_course_name = '';
foreach ($courses as $c) { if ((int)$c['id'] === $selected_course_id) { $selected_course_name = $c['name']; break; } }

/* -- Fetch resources for selected course ------------------ */
$stmt = $conn->prepare("
    SELECT DISTINCT cf.course_image, cf.course_title, cf.course_description, cf.course_link
    FROM course_free cf
    INNER JOIN courses c  ON cf.course_title = c.name
    INNER JOIN course_registration cr ON c.id = cr.course_id
    WHERE cr.user_id = ? AND c.id = ?
    ORDER BY cf.id DESC
    LIMIT 20
");
$stmt->bind_param("si", $current_user_id, $selected_course_id);
$stmt->execute();
$resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* -- Fetch ALL resources (for "Explore" tab) -------------- */
$allStmt = $conn->prepare("
    SELECT DISTINCT cf.course_image, cf.course_title, cf.course_description, cf.course_link
    FROM course_free cf
    INNER JOIN courses c  ON cf.course_title = c.name
    INNER JOIN course_registration cr ON c.id = cr.course_id
    WHERE cr.user_id = ?
    ORDER BY cf.id DESC
    LIMIT 9
");
$allStmt->bind_param("s", $current_user_id);
$allStmt->execute();
$allResources = $allStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allStmt->close();
$conn->close();
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
    <h1><i class="fas fa-folder-open"></i> <?php echo e($systemname) ?> &mdash; My Resources</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li class="active">My Resources</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2>My <em>Resources</em></h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-user-graduate"></i> <?php echo $student_name ?></span>
        <span class="meta-pill"><i class="fas fa-layer-group"></i> <?php echo count($courses) ?> course<?php echo count($courses) !== 1 ? 's' : '' ?></span>
      </div>
    </div>
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="resourceSearch" placeholder="Search resources...">
    </div>
  </div>

  <!-- -- Course filter bar ---------------------------------- -->
  <form method="GET" id="filterForm">
    <input type="hidden" name="view" value="my_resources">
    <div class="res-filter-bar">
      <div class="ctrl-field">
        <label class="ctrl-label" for="course_id">
          <i class="fas fa-book-open"></i> Filter by Course
        </label>
        <select name="course_id" id="course_id" class="ctrl-select"
                onchange="document.getElementById('filterForm').submit()">
          <?php foreach ($courses as $c): ?>
            <option value="<?php echo (int)$c['id'] ?>"
              <?php echo (int)$c['id'] === $selected_course_id ? 'selected' : '' ?>>
              <?php echo e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="resources-count">
        <i class="fas fa-layer-group"></i>
        <strong><?php echo count($resources) ?></strong> resource<?php echo count($resources) !== 1 ? 's' : '' ?>
        found for <strong><?php echo e($selected_course_name) ?></strong>
      </div>
    </div>
  </form>

  <!-- -- Tabs: Course Resources | Explore All --------------- -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('tab-course', this)">
      <i class="fas fa-book-open"></i> Course Resources
      <span class="pill"><?php echo count($resources) ?></span>
    </button>
    <button class="tab-btn" onclick="switchTab('tab-explore', this)">
      <i class="fas fa-compass"></i> Explore All
      <span class="pill"><?php echo count($allResources) ?></span>
    </button>
  </div>

  <!-- -- Tab: Course Resources ------------------------------ -->
  <div id="tab-course" class="tab-panel active">

    <div class="res-section-head">
      <span class="res-section-title">
        <i class="fas fa-book-open"></i>
        Resources for <em class="em-gold"><?php echo e($selected_course_name) ?></em>
      </span>
      <span class="count-pill"><?php echo count($resources) ?> items</span>
    </div>

    <?php if (empty($resources)): ?>
      <div class="res-no-courses">
        <i class="fas fa-folder-open"></i>
        <p>No resources linked to this course yet.</p>
      </div>
    <?php else: ?>
      <div class="resources-grid" id="resourceGrid">
        <?php foreach ($resources as $idx => $row):
          $title = e($row['course_title'] ?? '');
          $desc  = e(trim($row['course_description'] ?? ''));
          $img   = e($row['course_image'] ?? '');
          $link  = e($row['course_link'] ?? '#');
          $short = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc;
        ?>
        <a href="<?php echo $link ?>" target="_blank" rel="noopener" class="res-card"
           data-delay="<?php echo ($idx % 20) * 40 ?>"
           data-title="<?php echo strtolower($title) ?>"
           data-desc="<?php echo strtolower($desc) ?>">

          <div class="res-card-img-wrap">
            <?php if (!empty($row['course_image'])): ?>
              <img src="<?php echo $img ?>" alt="<?php echo $title ?>" loading="lazy">
            <?php else: ?>
              <div class="res-card-img-placeholder"><i class="fas fa-graduation-cap"></i></div>
            <?php endif; ?>
            <span class="res-card-tag"><i class="fas fa-certificate"></i> Free</span>
          </div>

          <div class="res-card-body">
            <div class="res-card-title"><?php echo $title ?></div>
            <?php if ($short): ?>
              <div class="res-card-desc"><?php echo $short ?></div>
            <?php endif; ?>
          </div>

          <div class="res-card-footer">
            <span class="res-card-link-hint"><i class="fas fa-external-link-alt"></i> Open Resource</span>
            <span class="res-card-ext-icon"><i class="fas fa-arrow-right"></i></span>
          </div>

        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <!-- -- Tab: Explore All ----------------------------------- -->
  <div id="tab-explore" class="tab-panel">

    <div class="res-section-head">
      <span class="res-section-title">
        <i class="fas fa-compass"></i> Explore Resources Across Your Courses
      </span>
      <span class="count-pill"><?php echo count($allResources) ?> items</span>
    </div>

    <?php if (empty($allResources)): ?>
      <div class="res-no-courses">
        <i class="fas fa-folder-open"></i>
        <p>No resources available for your enrolled courses.</p>
      </div>
    <?php else: ?>
      <div class="resources-grid">
        <?php foreach ($allResources as $idx => $row):
          $title = e($row['course_title'] ?? '');
          $desc  = e(trim($row['course_description'] ?? ''));
          $img   = e($row['course_image'] ?? '');
          $link  = e($row['course_link'] ?? '#');
          $short = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc;
        ?>
        <a href="<?php echo $link ?>" target="_blank" rel="noopener" class="res-card"
           data-delay="<?php echo ($idx % 20) * 40 ?>">

          <div class="res-card-img-wrap">
            <?php if (!empty($row['course_image'])): ?>
              <img src="<?php echo $img ?>" alt="<?php echo $title ?>" loading="lazy">
            <?php else: ?>
              <div class="res-card-img-placeholder"><i class="fas fa-graduation-cap"></i></div>
            <?php endif; ?>
            <span class="res-card-tag"><i class="fas fa-certificate"></i> Free</span>
          </div>

          <div class="res-card-body">
            <div class="res-card-title"><?php echo $title ?></div>
            <?php if ($short): ?>
              <div class="res-card-desc"><?php echo $short ?></div>
            <?php endif; ?>
          </div>

          <div class="res-card-footer">
            <span class="res-card-link-hint"><i class="fas fa-external-link-alt"></i> Open Resource</span>
            <span class="res-card-ext-icon"><i class="fas fa-arrow-right"></i></span>
          </div>

        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <!-- -- Explore more banner -------------------------------- -->
  <div class="res-explore-banner">
    <div class="res-explore-banner-text">
      <h3><i class="fas fa-globe"></i> Want more learning materials?</h3>
      <p>Browse the full free course library available to all students.</p>
    </div>
    <a href="?view=get_free_courses" class="btn-explore-more">
      <i class="fas fa-arrow-right"></i> Explore More Resources
    </a>
  </div>

</main>

<script>
/* -- Tab switching ---------------------------------------- */
function switchTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b)   { b.classList.remove('active'); });
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}

/* -- Apply stagger animation delays ---------------------- */
document.querySelectorAll('.res-card[data-delay]').forEach(function(el) {
  el.style.animationDelay = el.dataset.delay + 'ms';
});

/* -- Search filter (course tab only) ---------------------- */
document.getElementById('resourceSearch')?.addEventListener('input', function() {
  var q     = this.value.toLowerCase().trim();
  var cards = document.querySelectorAll('#resourceGrid .res-card');
  var vis   = 0;
  cards.forEach(function(c) {
    var match = !q || (c.dataset.title || '').includes(q) || (c.dataset.desc || '').includes(q);
    c.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  var cnt = document.querySelector('.resources-count strong');
  if (cnt) cnt.textContent = vis;
});
</script>
</body>
</html>