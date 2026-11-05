<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$systemname = 'Elevate Her 360';

function sd_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo '<div class="sd-alert sd-alert-danger">Please log in. <a href="?view=login">Login</a></div>';
    exit;
}

$current_user_id = trim((string)($_SESSION[$systemname . "leido"] ?? ''));
if ($current_user_id === '') {
    echo '<div class="sd-alert sd-alert-danger">Session error. Please log in again.</div>';
    exit;
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
$get_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($assignment_id <= 0 || $submission_id <= 0) {
    echo '<div class="sd-alert sd-alert-danger">Invalid request. Missing assignment/submission IDs.</div>';
    exit;
}

$sql = "
    SELECT
        hi.id AS submission_id,
        hi.user_id,
        hi.assignment_id,
        hi.file_uploaded,
        hi.date_handed_in,
        hi.marks_deducted,
        hi.finalmarks,
        hi.feedback AS feedback_comment,
        a.title AS assignment_title,
        a.marks AS total_marks,
        a.date_due,
        a.course_unit,
        cu.name AS unit_name,
        cu.course_id AS course_id,
        c.name AS course_name,
        u.fname,
        u.lname,
        u.email,
        COALESCE(ct.name,'N/A') AS class_type_name
    FROM handed_in hi
    JOIN assignments a ON a.id = hi.assignment_id
    JOIN course_unit cu ON cu.id = a.course_unit
    JOIN courses c ON c.id = cu.course_id
    JOIN users u ON u.unique_id = hi.user_id
    LEFT JOIN class_type ct ON ct.id = u.class_type_id
    WHERE hi.id = ?
      AND hi.assignment_id = ?
      AND hi.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo '<div class="sd-alert sd-alert-danger">Query prepare failed: ' . sd_e($conn->error) . '</div>';
    exit;
}

$stmt->bind_param("iis", $submission_id, $assignment_id, $current_user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo '<div class="sd-alert sd-alert-danger">Submission not found or you are not allowed to view it.</div>';
    exit;
}

$selected_course_id = ($get_course_id > 0) ? $get_course_id : (int)($row['course_id'] ?? 0);

$student_name     = sd_e(trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')));
$student_email    = sd_e($row['email'] ?? '');
$course_name      = sd_e($row['course_name'] ?? '');
$unit_name        = sd_e($row['unit_name'] ?? '');
$assignment_title = sd_e($row['assignment_title'] ?? '');
$class_type_name  = sd_e($row['class_type_name'] ?? 'N/A');

$date_due = $row['date_due']
    ? date('M d, Y  g:i A', strtotime($row['date_due']))
    : '-';

$date_handed_in = $row['date_handed_in']
    ? date('M d, Y  g:i A', strtotime($row['date_handed_in']))
    : '-';

$total_marks    = $row['total_marks'] ?? '-';
$finalmarks     = $row['finalmarks'];
$marks_deducted = $row['marks_deducted'];
$feedback       = trim((string)($row['feedback_comment'] ?? ''));

$graded = ($finalmarks !== null && $finalmarks !== '');
$submitted = !empty($row['submission_id']);
$is_late = (
    $row['date_due'] &&
    $row['date_handed_in'] &&
    strtotime($row['date_handed_in']) > strtotime($row['date_due'])
);

$file_uploaded = $row['file_uploaded'] ?? '';
$file_base = $file_uploaded ? basename($file_uploaded) : '';
$file_ext = $file_base ? strtolower(pathinfo($file_base, PATHINFO_EXTENSION)) : '';

$score_pct = 0;
if ($graded && is_numeric($total_marks) && (float)$total_marks > 0) {
    $score_pct = min(100, round(((float)$finalmarks / (float)$total_marks) * 100));
}

$ring_color = $score_pct >= 70 ? '#2d7a4f' : ($score_pct >= 50 ? '#b07d20' : '#c8522a');
$ring_dash = round(2 * M_PI * 40);
$ring_fill = round($ring_dash * $score_pct / 100);

if ($graded) {
    $pill = 'graded';
    $label = 'Graded';
} elseif ($submitted) {
    $pill = 'submitted';
    $label = 'Submitted';
} else {
    $pill = 'pending';
    $label = 'Pending';
}

$md_val = ($marks_deducted !== null && $marks_deducted !== '') ? (float)$marks_deducted : 0;

$ext_class = 'ext-other';
$ext_icon = 'fa-file';

if (in_array($file_ext, ['pdf'])) {
    $ext_class = 'ext-pdf';
    $ext_icon = 'fa-file-pdf';
} elseif (in_array($file_ext, ['doc', 'docx'])) {
    $ext_class = 'ext-doc';
    $ext_icon = 'fa-file-word';
} elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $ext_class = 'ext-img';
    $ext_icon = 'fa-file-image';
} elseif (in_array($file_ext, ['zip', 'rar', '7z'])) {
    $ext_class = 'ext-zip';
    $ext_icon = 'fa-file-archive';
} elseif (in_array($file_ext, ['xls', 'xlsx', 'csv'])) {
    $ext_class = 'ext-doc';
    $ext_icon = 'fa-file-excel';
} elseif (in_array($file_ext, ['ppt', 'pptx'])) {
    $ext_class = 'ext-other';
    $ext_icon = 'fa-file-powerpoint';
} elseif (in_array($file_ext, ['txt', 'md'])) {
    $ext_class = 'ext-other';
    $ext_icon = 'fa-file-alt';
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">

<style>
.sd-wrap {
  --c-brand:#c8522a;
  --c-brand-lt:#f4a261;
  --c-deep:#12100e;
  --c-mid:#3b1f10;
  --c-muted:#8a6a58;
  --c-surface:#fdf9f6;
  --c-card:#ffffff;
  --c-border:#ecddd1;
  --c-hover:#f7ede3;
  --c-graded:#2d7a4f;
  --c-graded-bg:#e8f5ee;
  --c-pending:#c8522a;
  --c-pending-bg:#fdeee8;
  --c-submitted:#8a6200;
  --c-submitted-bg:#fdf3d6;
  --ff-display:'Playfair Display', Georgia, serif;
  --ff-body:'Lato', system-ui, sans-serif;
  --r-sm:6px;
  --r-md:14px;
  --r-lg:22px;
  --r-xl:30px;
  --sh-sm:0 1px 4px rgba(18,16,14,.07);
  --sh-md:0 4px 24px rgba(18,16,14,.10);
  --ease:.2s cubic-bezier(.4,0,.2,1);

  font-family:var(--ff-body);
  color:var(--c-deep);
  line-height:1.6;
  padding:28px 24px 60px;
  max-width:1100px;
  margin:0 auto;
  box-sizing:border-box;
}

.sd-wrap *,
.sd-wrap *::before,
.sd-wrap *::after {
  box-sizing:border-box;
}

.sd-alert {
  padding:13px 18px;
  border-radius:var(--r-md);
  font-size:12px;
  margin-bottom:18px;
  border:1px solid transparent;
}

.sd-alert-danger {
  background:var(--c-pending-bg);
  color:var(--c-pending);
  border-color:rgba(200,82,42,.2);
}

.sd-breadcrumb {
  display:flex;
  align-items:center;
  gap:6px;
  font-size:.78rem;
  color:var(--c-muted);
  margin-bottom:26px;
  flex-wrap:wrap;
}

.sd-breadcrumb a {
  color:var(--c-brand);
  text-decoration:none;
  font-weight:700;
}

.sd-breadcrumb a:hover {
  color:var(--c-brand-lt);
}

.sd-breadcrumb .sep {
  opacity:.4;
}

.sd-hero {
  background:var(--c-deep);
  border-radius:var(--r-xl);
  padding:32px 36px;
  margin-bottom:24px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:24px;
  flex-wrap:wrap;
  position:relative;
  overflow:hidden;
}

.sd-hero::before {
  content:'';
  position:absolute;
  top:-60px;
  right:-60px;
  width:260px;
  height:260px;
  background:radial-gradient(circle, rgba(200,82,42,.25) 0%, transparent 70%);
  pointer-events:none;
}

.sd-hero-left {
  flex:1;
  min-width:220px;
  position:relative;
  z-index:1;
}

.sd-hero-eyebrow {
  font-size:.72rem;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--c-brand-lt);
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:7px;
}

.sd-hero-title {
  font-family:var(--ff-display);
  font-size:1.75rem;
  font-weight:400;
  color:#fff;
  line-height:1.25;
  margin-bottom:14px;
}

.sd-hero-tags {
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.sd-tag {
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:4px 12px;
  border-radius:99px;
  font-size:11px;
  font-weight:700;
  border:1px solid rgba(255,255,255,.15);
  color:rgba(255,255,255,.8);
  background:rgba(255,255,255,.08);
}

.sd-pill {
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:5px 14px;
  border-radius:99px;
  font-size:.78rem;
  font-weight:700;
  letter-spacing:.03em;
}

.sd-pill-graded {
  background:var(--c-graded-bg);
  color:var(--c-graded);
}

.sd-pill-submitted {
  background:var(--c-submitted-bg);
  color:var(--c-submitted);
}

.sd-pill-pending {
  background:var(--c-pending-bg);
  color:var(--c-pending);
}

.sd-pill-dot {
  width:7px;
  height:7px;
  border-radius:50%;
  background:currentColor;
}

.sd-score-ring {
  position:relative;
  z-index:1;
  flex-shrink:0;
  text-align:center;
}

.sd-score-ring svg {
  display:block;
}

.sd-score-number {
  font-family:var(--ff-display);
  font-size:1.6rem;
  font-weight:600;
  fill:#fff;
}

.sd-score-sub {
  font-size:.65rem;
  fill:rgba(255,255,255,.5);
}

.sd-score-label {
  font-size:.7rem;
  font-weight:700;
  letter-spacing:.1em;
  text-transform:uppercase;
  color:rgba(255,255,255,.5);
}

.sd-tabs-shell {
  background:var(--c-card);
  border:1px solid var(--c-border);
  border-radius:var(--r-lg);
  box-shadow:var(--sh-sm);
  overflow:hidden;
  margin-bottom:22px;
}

.sd-tabs-nav {
  display:flex;
  gap:0;
  overflow-x:auto;
  background:var(--c-surface);
  border-bottom:1px solid var(--c-border);
}

.sd-tab-btn {
  appearance:none;
  border:0;
  background:transparent;
  color:var(--c-muted);
  font-family:var(--ff-body);
  font-size:.84rem;
  font-weight:700;
  padding:16px 20px;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:8px;
  border-bottom:3px solid transparent;
  white-space:nowrap;
  transition:all var(--ease);
}

.sd-tab-btn:hover {
  background:var(--c-hover);
  color:var(--c-mid);
}

.sd-tab-btn.active {
  color:var(--c-brand);
  background:#fff;
  border-bottom-color:var(--c-brand);
}

.sd-tab-panel {
  display:none;
  padding:24px;
  animation:sd-fadein .25s ease both;
}

.sd-tab-panel.active {
  display:block;
}

.sd-section-title {
  font-family:var(--ff-display);
  color:var(--c-mid);
  font-size:1.15rem;
  font-weight:600;
  margin:0 0 18px;
  display:flex;
  align-items:center;
  gap:10px;
}

.sd-section-title span {
  width:36px;
  height:36px;
  border-radius:12px;
  background:rgba(200,82,42,.1);
  color:var(--c-brand);
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:.9rem;
}

.sd-info-list {
  border:1px solid var(--c-border);
  border-radius:var(--r-md);
  overflow:hidden;
}

.sd-info-row {
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  padding:13px 16px;
  border-bottom:1px solid var(--c-border);
  font-size:.875rem;
}

.sd-info-row:last-child {
  border-bottom:none;
}

.sd-info-label {
  color:var(--c-muted);
  font-weight:700;
  font-size:.78rem;
  text-transform:uppercase;
  letter-spacing:.05em;
  white-space:nowrap;
}

.sd-info-value {
  color:var(--c-deep);
  text-align:right;
  word-break:break-word;
}

.sd-info-value.bold {
  font-weight:700;
}

.sd-info-value.danger {
  color:var(--c-pending);
  font-weight:700;
}

.sd-late-tag {
  display:inline-block;
  padding:2px 8px;
  border-radius:4px;
  background:var(--c-pending-bg);
  color:var(--c-pending);
  font-size:.7rem;
  font-weight:700;
  margin-left:6px;
}

.sd-marks-grid {
  display:grid;
  grid-template-columns:repeat(3, 1fr);
  gap:14px;
}

.sd-mark-box {
  background:var(--c-surface);
  border:1px solid var(--c-border);
  border-radius:var(--r-md);
  padding:18px 16px;
  text-align:center;
}

.sd-mark-box .mb-label {
  font-size:.68rem;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.09em;
  color:var(--c-muted);
  margin-bottom:8px;
}

.sd-mark-box .mb-value {
  font-family:var(--ff-display);
  font-size:2rem;
  font-weight:600;
  line-height:1;
}

.mb-total .mb-value {
  color:var(--c-muted);
}

.mb-final .mb-value {
  color:#4285f4;
}

.mb-deducted .mb-value {
  color:var(--c-pending);
}

.sd-mark-box .mb-sub {
  font-size:.72rem;
  color:var(--c-muted);
  margin-top:6px;
}

.sd-progress-wrap {
  margin-top:18px;
}

.sd-progress-label {
  display:flex;
  justify-content:space-between;
  font-size:.78rem;
  color:var(--c-muted);
  margin-bottom:6px;
}

.sd-progress-track {
  height:8px;
  background:var(--c-border);
  border-radius:99px;
  overflow:hidden;
}

.sd-progress-fill {
  height:100%;
  border-radius:99px;
  transition:width .6s cubic-bezier(.4,0,.2,1);
}

.sd-file-card {
  display:flex;
  align-items:center;
  gap:16px;
  background:var(--c-surface);
  border:1.5px solid var(--c-border);
  border-radius:var(--r-md);
  padding:16px 18px;
}

.sd-file-icon {
  width:44px;
  height:44px;
  border-radius:var(--r-sm);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.2rem;
  flex-shrink:0;
}

.ext-pdf {
  background:rgba(220,53,69,.12);
  color:#dc3545;
}

.ext-doc {
  background:rgba(66,133,244,.12);
  color:#4285f4;
}

.ext-img {
  background:rgba(45,122,79,.12);
  color:var(--c-graded);
}

.ext-zip {
  background:rgba(138,98,0,.12);
  color:var(--c-submitted);
}

.ext-other {
  background:rgba(138,106,88,.12);
  color:var(--c-muted);
}

.sd-file-info {
  flex:1;
  min-width:0;
}

.sd-file-name {
  font-weight:700;
  font-size:12px;
  color:var(--c-deep);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.sd-file-meta {
  font-size:11px;
  color:var(--c-muted);
  margin-top:2px;
}

.sd-btn {
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 18px;
  border-radius:var(--r-sm);
  font-family:var(--ff-body);
  font-size:.82rem;
  font-weight:700;
  cursor:pointer;
  border:none;
  text-decoration:none;
  transition:all var(--ease);
  white-space:nowrap;
}

.sd-btn-primary {
  background:linear-gradient(135deg, var(--c-brand), var(--c-brand-lt));
  color:#fff;
  box-shadow:0 2px 10px rgba(200,82,42,.28);
}

.sd-btn-primary:hover {
  transform:translateY(-1px);
  box-shadow:0 4px 18px rgba(200,82,42,.38);
}

.sd-btn-secondary {
  background:var(--c-surface);
  color:var(--c-mid);
  border:1.5px solid var(--c-border);
}

.sd-btn-secondary:hover {
  background:var(--c-hover);
}

.sd-no-file,
.sd-no-feedback {
  color:var(--c-muted);
  font-style:italic;
  font-size:.88rem;
  padding:10px 0;
}

.sd-feedback-box {
  background:var(--c-surface);
  border:1px solid var(--c-border);
  border-left:3px solid var(--c-brand);
  border-radius:0 var(--r-md) var(--r-md) 0;
  padding:18px 20px;
  font-size:12px;
  line-height:1.8;
  color:#3b2515;
  white-space:pre-wrap;
  word-break:break-word;
  max-height:340px;
  overflow-y:auto;
}

.sd-back-row {
  display:flex;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
}

@keyframes sd-fadein {
  from {
    opacity:0;
    transform:translateY(10px);
  }
  to {
    opacity:1;
    transform:translateY(0);
  }
}

@media (max-width:700px) {
  .sd-wrap {
    padding:20px 14px 50px;
  }

  .sd-hero {
    padding:22px 20px;
  }

  .sd-hero-title {
    font-size:1.35rem;
  }

  .sd-score-ring {
    display:none;
  }

  .sd-tab-panel {
    padding:18px 14px;
  }

  .sd-marks-grid {
    grid-template-columns:1fr;
  }

  .sd-info-row {
    flex-direction:column;
    gap:4px;
  }

  .sd-info-value {
    text-align:left;
  }

  .sd-file-card {
    flex-direction:column;
    align-items:flex-start;
  }
}
</style>

<div class="sd-wrap">

  <div class="sd-breadcrumb">
    <a href="index.php?view=my_assignment<?= $selected_course_id ? '&course_id=' . $selected_course_id : ''; ?>">
      <i class="fas fa-arrow-left"></i> My Assignments
    </a>
    <span class="sep">/</span>
    <span><?= $assignment_title; ?></span>
  </div>

  <div class="sd-hero">
    <div class="sd-hero-left">
      <div class="sd-hero-eyebrow">
        <i class="fas fa-file-alt"></i>
        Submission Detail
      </div>

      <div class="sd-hero-title">
        <?= $assignment_title; ?>
      </div>

      <div class="sd-hero-tags">
        <span class="sd-pill sd-pill-<?= $pill; ?>" style="background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.25);">
          <span class="sd-pill-dot" style="background:<?= $graded ? '#4ade80' : ($submitted ? '#fbbf24' : '#f87171'); ?>;"></span>
          <?= $label; ?>
        </span>

        <span class="sd-tag">
          <i class="fas fa-book"></i> <?= $course_name; ?>
        </span>

        <span class="sd-tag">
          <i class="fas fa-layer-group"></i> <?= $unit_name; ?>
        </span>

        <?php if ($is_late): ?>
          <span class="sd-tag" style="border-color:rgba(248,113,113,.4);color:#fca5a5;">
            <i class="fas fa-clock"></i> Late Submission
          </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($graded): ?>
      <div class="sd-score-ring">
        <svg width="100" height="100" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="8"/>
          <circle cx="50" cy="50" r="40" fill="none"
                  stroke="<?= sd_e($ring_color); ?>"
                  stroke-width="8"
                  stroke-linecap="round"
                  stroke-dasharray="<?= $ring_fill; ?> <?= $ring_dash - $ring_fill; ?>"
                  stroke-dashoffset="<?= round($ring_dash * 0.25); ?>"
                  transform="rotate(-90 50 50)"/>
          <text x="50" y="46" text-anchor="middle" class="sd-score-number"><?= $score_pct; ?>%</text>
          <text x="50" y="60" text-anchor="middle" class="sd-score-sub"><?= sd_e($finalmarks); ?>/<?= sd_e($total_marks); ?></text>
        </svg>
        <div class="sd-score-label">Score</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="sd-tabs-shell">

    <div class="sd-tabs-nav" role="tablist" aria-label="Submission detail tabs">
      <button type="button" class="sd-tab-btn active" data-tab="student">
        <i class="fas fa-user-graduate"></i> Student
      </button>

      <button type="button" class="sd-tab-btn" data-tab="assignment">
        <i class="fas fa-clipboard-list"></i> Assignment
      </button>

      <button type="button" class="sd-tab-btn" data-tab="marks">
        <i class="fas fa-star-half-alt"></i> Marks
      </button>

      <button type="button" class="sd-tab-btn" data-tab="file">
        <i class="fas fa-paperclip"></i> File
      </button>

      <button type="button" class="sd-tab-btn" data-tab="feedback">
        <i class="fas fa-comment-dots"></i> Feedback
      </button>
    </div>

    <div class="sd-tab-panel active" id="tab-student">
      <h3 class="sd-section-title">
        <span><i class="fas fa-user-graduate"></i></span>
        Student Information
      </h3>

      <div class="sd-info-list">
        <div class="sd-info-row">
          <span class="sd-info-label">Name</span>
          <span class="sd-info-value bold"><?= $student_name; ?></span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Email</span>
          <span class="sd-info-value"><?= $student_email; ?></span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Class Type</span>
          <span class="sd-info-value"><?= $class_type_name; ?></span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Course</span>
          <span class="sd-info-value"><?= $course_name; ?></span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Unit / Module</span>
          <span class="sd-info-value"><?= $unit_name; ?></span>
        </div>
      </div>
    </div>

    <div class="sd-tab-panel" id="tab-assignment">
      <h3 class="sd-section-title">
        <span><i class="fas fa-clipboard-list"></i></span>
        Assignment Details
      </h3>

      <div class="sd-info-list">
        <div class="sd-info-row">
          <span class="sd-info-label">Submission ID</span>
          <span class="sd-info-value">#<?= (int)$row['submission_id']; ?></span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Due Date</span>
          <span class="sd-info-value <?= ($row['date_due'] && !$graded && strtotime($row['date_due']) < time()) ? 'danger' : ''; ?>">
            <?= sd_e($date_due); ?>
          </span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Submitted On</span>
          <span class="sd-info-value">
            <?= sd_e($date_handed_in); ?>
            <?php if ($is_late): ?>
              <span class="sd-late-tag">Late</span>
            <?php endif; ?>
          </span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Status</span>
          <span class="sd-info-value">
            <span class="sd-pill sd-pill-<?= $pill; ?>">
              <span class="sd-pill-dot"></span>
              <?= $label; ?>
            </span>
          </span>
        </div>

        <div class="sd-info-row">
          <span class="sd-info-label">Max Marks</span>
          <span class="sd-info-value bold"><?= sd_e($total_marks); ?></span>
        </div>
      </div>
    </div>

    <div class="sd-tab-panel" id="tab-marks">
      <h3 class="sd-section-title">
        <span><i class="fas fa-star-half-alt"></i></span>
        Marks Breakdown
      </h3>

      <div class="sd-marks-grid">
        <div class="sd-mark-box mb-total">
          <div class="mb-label">
            <i class="fas fa-bullseye"></i> Max Marks
          </div>
          <div class="mb-value"><?= sd_e($total_marks); ?></div>
          <div class="mb-sub">Total available</div>
        </div>

        <div class="sd-mark-box mb-final">
          <div class="mb-label">
            <i class="fas fa-check"></i> Final Marks
          </div>
          <div class="mb-value"><?= $graded ? sd_e($finalmarks) : '-'; ?></div>
          <div class="mb-sub"><?= $graded ? 'After deductions' : 'Not graded yet'; ?></div>
        </div>

        <div class="sd-mark-box mb-deducted">
          <div class="mb-label">
            <i class="fas fa-minus"></i> Deducted
          </div>
          <div class="mb-value">
            <?= $md_val > 0 ? '-' . sd_e($marks_deducted) : '0'; ?>
          </div>
          <div class="mb-sub"><?= $md_val > 0 ? 'Marks removed' : 'No deduction'; ?></div>
        </div>
      </div>

      <?php if ($graded && is_numeric($total_marks) && (float)$total_marks > 0): ?>
        <div class="sd-progress-wrap">
          <div class="sd-progress-label">
            <span>Score</span>
            <span><?= $score_pct; ?>%</span>
          </div>

          <div class="sd-progress-track">
            <div class="sd-progress-fill" style="width:<?= $score_pct; ?>%; background:<?= sd_e($ring_color); ?>;"></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="sd-tab-panel" id="tab-file">
      <h3 class="sd-section-title">
        <span><i class="fas fa-paperclip"></i></span>
        Submitted File
      </h3>

      <?php if ($file_base): ?>
        <div class="sd-file-card">
          <div class="sd-file-icon <?= $ext_class; ?>">
            <i class="fas <?= $ext_icon; ?>"></i>
          </div>

          <div class="sd-file-info">
            <div class="sd-file-name" title="<?= sd_e($file_base); ?>">
              <?= sd_e($file_base); ?>
            </div>

            <div class="sd-file-meta">
              <?= strtoupper($file_ext ?: 'File'); ?> &middot; Submitted <?= sd_e($date_handed_in); ?>
            </div>
          </div>

          <div class="sd-file-actions">
            <a href="index.php?view=download_file&submission_id=<?= (int)$row['submission_id']; ?>"
               class="sd-btn sd-btn-primary">
              <i class="fas fa-download"></i> Download
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="sd-no-file">
          <i class="fas fa-folder-open"></i>
          No file was attached to this submission.
        </div>
      <?php endif; ?>
    </div>

    <div class="sd-tab-panel" id="tab-feedback">
      <h3 class="sd-section-title">
        <span><i class="fas fa-comment-dots"></i></span>
        Instructor Feedback
      </h3>

      <?php if ($graded && $feedback !== ''): ?>
        <div class="sd-feedback-box"><?= sd_e($feedback); ?></div>
      <?php elseif ($graded): ?>
        <p class="sd-no-feedback">No written feedback was provided for this submission.</p>
      <?php else: ?>
        <p class="sd-no-feedback">Feedback will appear here once your submission has been graded.</p>
      <?php endif; ?>
    </div>

  </div>

  <div class="sd-back-row">
    <a href="index.php?view=my_assignment<?= $selected_course_id ? '&course_id=' . $selected_course_id : ''; ?>"
       class="sd-btn sd-btn-secondary">
      <i class="fas fa-arrow-left"></i> Back to Assignments
    </a>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const tabButtons = document.querySelectorAll('.sd-tab-btn');
  const tabPanels = document.querySelectorAll('.sd-tab-panel');

  tabButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const target = button.getAttribute('data-tab');

      tabButtons.forEach(function (btn) {
        btn.classList.remove('active');
      });

      tabPanels.forEach(function (panel) {
        panel.classList.remove('active');
      });

      button.classList.add('active');

      const activePanel = document.getElementById('tab-' + target);
      if (activePanel) {
        activePanel.classList.add('active');
      }
    });
  });
});
</script>