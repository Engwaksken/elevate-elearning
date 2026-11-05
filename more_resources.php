<?php
require 'scripts/connector.php';

$sql = "SELECT cf.course_image, cf.course_title, cf.course_description, cf.course_link
        FROM course_free cf
        INNER JOIN courses c ON cf.course_title = c.name
        ORDER BY cf.id DESC";

$result = $conn->query($sql);
$courses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $courses[] = $row;
}

$perPage    = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalItems = count($courses);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$page       = min($page, $totalPages);
$start      = ($page - 1) * $perPage;
$display    = array_slice($courses, $start, $perPage);
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
    <h1><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; More Resources</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li class="active">More Resources</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Heading -------------------------------------------- -->
  <div class="resources-heading">
    <h2>More <em>Resources</em></h2>
    <p><i class="fas fa-info-circle"></i> Browse free courses and learning materials available to you.</p>
  </div>

  <!-- -- Toolbar: search + count ---------------------------- -->
  <div class="resources-toolbar">
    <div class="resources-count">
      <i class="fas fa-layer-group"></i>
      Showing <strong><?php echo count($display) ?></strong> of <strong><?php echo $totalItems ?></strong> resources
      &mdash; Page <?php echo $page ?> of <?php echo $totalPages ?>
    </div>
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="resourceSearch" placeholder="Search resources...">
    </div>
  </div>

  <!-- -- Course grid ---------------------------------------- -->
  <?php if (empty($display)): ?>
    <div class="resources-empty">
      <i class="fas fa-folder-open"></i>
      <p>No resources available at the moment.</p>
    </div>
  <?php else: ?>
    <div class="resources-grid" id="resourceGrid">
      <?php foreach ($display as $idx => $course):
        $title = htmlspecialchars($course['course_title'] ?? '');
        $desc  = htmlspecialchars(trim((string)($course['course_description'] ?? '')));
        $img   = htmlspecialchars($course['course_image'] ?? '');
        $link  = htmlspecialchars($course['course_link'] ?? '#');
        $short = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc;
        $delay = ($idx % $perPage) * 40;
      ?>
      <a href="<?php echo $link ?>" target="_blank" rel="noopener" class="res-card"
         class="res-card" <?php echo $delay ? "data-delay="{$delay}"" : "" ?> data-title="<?php echo strtolower($title) ?>" data-desc="<?php echo strtolower($desc) ?>">

        <div class="res-card-img-wrap">
          <?php if (!empty($course['course_image'])): ?>
            <img src="<?php echo $img ?>" alt="<?php echo $title ?>">
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

    <!-- -- Pagination ---------------------------------------- -->
    <div class="resources-pagination">
      <button class="pg-btn"
        onclick="window.location='?page=<?php echo max(1, $page - 1) ?>'"
        <?php echo $page <= 1 ? 'disabled' : '' ?>>
        <i class="fas fa-chevron-left"></i> Previous
      </button>

      <span class="pg-info">Page <?php echo $page ?> / <?php echo $totalPages ?></span>

      <button class="pg-btn"
        onclick="window.location='?page=<?php echo min($totalPages, $page + 1) ?>'"
        <?php echo $page >= $totalPages ? 'disabled' : '' ?>>
        Next <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  <?php endif; ?>

</main>

<script>
/* -- Client-side search filter ------------------------------ */
document.getElementById('resourceSearch')?.addEventListener('input', function () {
  var q = this.value.toLowerCase().trim();
  var cards = document.querySelectorAll('#resourceGrid .res-card');
  var visible = 0;
  cards.forEach(function (c) {
    var match = !q
      || (c.dataset.title || '').includes(q)
      || (c.dataset.desc  || '').includes(q);
    c.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  var cnt = document.querySelector('.resources-count strong');
  if (cnt) cnt.closest('.resources-count').querySelector('strong').textContent = visible;
});

/* -- Apply stagger animation delays from data attribute ----- */
document.querySelectorAll('.res-card[data-delay]').forEach(function (el) {
  el.style.animationDelay = el.dataset.delay + 'ms';
});
</script>
</body>
</html>