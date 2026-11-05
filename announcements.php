<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$systemname = 'Elevate Her 360';

// Check login status
if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Please log in. <a href="?view=login">Login</a></div>';
    exit;
}

$current_user_id = $_SESSION[$systemname . "leido"];

// Get user full name
$user_stmt = $conn->prepare("SELECT fname, lname FROM users WHERE unique_id = ?");
$user_stmt->bind_param("s", $current_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows === 0) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> User not found.</div>';
    exit;
}
$user_data     = $user_result->fetch_assoc();
$student_name  = htmlspecialchars($user_data['fname'] . ' ' . $user_data['lname']);
$user_stmt->close();

// Fetch user's registered courses
$courses_stmt = $conn->prepare("
    SELECT c.id, c.name
    FROM course_registration cr
    JOIN courses c ON cr.course_id = c.id
    WHERE cr.user_id = ? AND cr.status != 'done'
");
$courses_stmt->bind_param("s", $current_user_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$courses = [];
while ($course = $courses_result->fetch_assoc()) $courses[] = $course;
$courses_stmt->close();

if (empty($courses)) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> You are not registered in any course.</div>';
    exit;
}

// Pagination
$per_page     = 9;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// Count total
$count_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT a.post_name, a.created_at) AS cnt
    FROM annouments a
    INNER JOIN courses c ON a.post_name = c.name
    INNER JOIN course_registration cr ON c.id = cr.course_id
    WHERE cr.user_id = ?
");
$count_stmt->bind_param("s", $current_user_id);
$count_stmt->execute();
$total_rows   = (int)($count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$total_pages  = (int)ceil($total_rows / $per_page) ?: 1;
$count_stmt->close();

// Fetch announcements (paginated)
$stmt = $conn->prepare("
    SELECT DISTINCT a.post_image, a.post_name, a.description, a.post_link, a.created_at
    FROM annouments a
    INNER JOIN courses c ON a.post_name = c.name
    INNER JOIN course_registration cr ON c.id = cr.course_id
    WHERE cr.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("sii", $current_user_id, $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Helper: build paginated URL
function pgUrl(int $page): string {
    $params = array_merge($_GET, ['page' => $page]);
    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($systemname) ?> — Announcements</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
<style>
  /* -- Pagination bar --------------------------------------- */
  .pag-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 28px;
  }
  .pag-info {
    font-size: .76rem;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .pag-info i { color: var(--maroon); font-size: .7rem; }
  .pag-buttons { display: flex; align-items: center; gap: 6px; }
  .pg-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    border: 1.5px solid var(--border);
    color: var(--muted);
    background: var(--surface);
    transition: border-color .15s, color .15s, background .15s;
  }
  .pg-num:hover { border-color: var(--maroon); color: var(--maroon); }
  .pg-num.active {
    background: var(--maroon);
    border-color: var(--maroon);
    color: #fff;
    box-shadow: 0 2px 6px rgba(122,25,25,.25);
  }
  .pg-ellipsis { font-size: .78rem; color: var(--muted); padding: 0 2px; user-select: none; }
  .pg-arrow {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 13px;
    border-radius: 6px;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    border: 1.5px solid var(--maroon);
    color: var(--maroon);
    background: transparent;
    transition: background .15s, color .15s;
  }
  .pg-arrow:hover { background: var(--maroon); color: #fff; }
  .pg-arrow.disabled {
    border-color: var(--border);
    color: var(--muted);
    pointer-events: none;
    cursor: not-allowed;
  }
  .pg-arrow i { font-size: .72rem; }
</style>
</head>
<body>

<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Announcements</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li class="active">Announcements</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2><em>Latest</em> Announcements</h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-user"></i> <?php echo $student_name ?></span>
        <span class="meta-pill"><i class="fas fa-book"></i> <?php echo count($courses) ?> active course<?php echo count($courses) !== 1 ? 's' : '' ?></span>
        <?php if ($total_rows > 0): ?>
        <span class="meta-pill"><i class="fas fa-bell"></i> <?php echo $total_rows ?> announcement<?php echo $total_rows !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
      </div>
    </div>
    <a href="?view=more_announcements" class="btn-view-all">
      <i class="fas fa-list"></i> View All
    </a>
  </div>

  <!-- -- Info badge ----------------------------------------- -->
  <div class="info-badge">
    <i class="fas fa-info-circle"></i>
    Showing announcements for <strong><?php echo count($courses) ?> registered course<?php echo count($courses) !== 1 ? 's' : '' ?></strong>.
    Only active enrolments are included.
  </div>

  <!-- -- Announcements grid --------------------------------- -->
  <?php if ($result->num_rows > 0): ?>

  <div class="section-header">
    <h2><i class="fas fa-bullhorn"></i> Recent Posts</h2>
    <span class="count-pill">
      Page <?php echo $current_page ?> of <?php echo $total_pages ?>
    </span>
  </div>

  <div class="ann-grid">
    <?php while ($row = $result->fetch_assoc()):
      $post_link   = htmlspecialchars($row['post_link']   ?? '', ENT_QUOTES, 'UTF-8');
      $post_image  = htmlspecialchars($row['post_image']  ?? '', ENT_QUOTES, 'UTF-8');
      $post_name   = htmlspecialchars($row['post_name']   ?? '', ENT_QUOTES, 'UTF-8');
      $description = htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8');
      $created_at  = date('M d, Y', strtotime($row['created_at']));
      $has_link    = !empty($post_link);
      $tag         = $open = $close = '';

      if ($has_link) {
          $tag   = 'a';
          $open  = "<a href='{$post_link}' class='ann-card' target='_blank' rel='noopener noreferrer'>";
          $close = "</a>";
      } else {
          $tag   = 'div';
          $open  = "<div class='ann-card'>";
          $close = "</div>";
      }
    ?>
    <?php echo $open ?>

      <!-- Card image -->
      <?php if (!empty($post_image)): ?>
        <img class="ann-card-img"
             src="<?php echo $post_image ?>"
             alt="<?php echo $post_name ?>"
             loading="lazy">
      <?php else: ?>
        <div class="ann-card-img-placeholder">
          <i class="fas fa-bullhorn"></i>
        </div>
      <?php endif; ?>

      <!-- Card body -->
      <div class="ann-card-body">
        <div class="ann-card-title"><?php echo $post_name ?></div>
        <div class="ann-card-date">
          <i class="fas fa-calendar-alt"></i> <?php echo $created_at ?>
        </div>
        <p class="ann-card-desc"><?php echo $description ?></p>
      </div>

      <!-- Card footer -->
      <?php if ($has_link): ?>
      <div class="ann-card-footer">
        <span class="btn-ann-link">
          <i class="fas fa-external-link-alt"></i> Read More
        </span>
      </div>
      <?php endif; ?>

    <?php echo $close ?>
    <?php endwhile; ?>
  </div>

  <!-- -- Pagination ----------------------------------------- -->
  <?php if ($total_pages > 1): ?>
  <div class="pag-bar">
    <div class="pag-info">
      <i class="fas fa-bell"></i>
      Showing <?php echo $offset + 1 ?>–<?php echo min($current_page * $per_page, $total_rows) ?> of <?php echo $total_rows ?> announcements
    </div>
    <div class="pag-buttons">

      <!-- Prev -->
      <a href="<?php echo $current_page > 1 ? pgUrl($current_page - 1) : '#' ?>"
         class="pg-arrow <?php echo $current_page <= 1 ? 'disabled' : '' ?>">
        <i class="fas fa-chevron-left"></i> Prev
      </a>

      <!-- Number pills -->
      <?php
      $range = 2;
      $prev_shown = null;
      for ($p = 1; $p <= $total_pages; $p++):
        if ($p === 1 || $p === $total_pages || abs($p - $current_page) <= $range):
          if ($prev_shown !== null && $p - $prev_shown > 1): ?>
            <span class="pg-ellipsis">&hellip;</span>
      <?php  endif; ?>
          <a href="<?php echo pgUrl($p) ?>"
             class="pg-num <?php echo $p === $current_page ? 'active' : '' ?>">
            <?php echo $p ?>
          </a>
      <?php
          $prev_shown = $p;
        endif;
      endfor;
      ?>

      <!-- Next -->
      <a href="<?php echo $current_page < $total_pages ? pgUrl($current_page + 1) : '#' ?>"
         class="pg-arrow <?php echo $current_page >= $total_pages ? 'disabled' : '' ?>">
        Next <i class="fas fa-chevron-right"></i>
      </a>

    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <!-- -- Empty state ---------------------------------------- -->
  <div class="empty">
    <i class="fas fa-bullhorn"></i>
    <p>No announcements available for your registered courses yet.<br>Check back soon.</p>
  </div>
  <?php endif; ?>

</main>

<?php
$stmt->close();
$conn->close();
?>
</body>
</html>