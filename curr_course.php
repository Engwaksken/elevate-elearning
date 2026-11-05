<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'scripts/connector.php';

date_default_timezone_set('Africa/Nairobi');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo '<div class="app-alert app-alert-danger">Database connection not available.</div>';
    return;
}

$systemname = $systemname ?? '';

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('safeDate')) {
    function safeDate(mixed $value, string $format = 'd M Y, h:i a'): string
    {
        if (!$value || strtotime((string)$value) === false) {
            return '-';
        }

        return date($format, strtotime((string)$value));
    }
}

if (!function_exists('tableExists')) {
    function tableExists(mysqli $conn, string $table): bool
    {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('materialIcon')) {
    function materialIcon(string $type): string
    {
        return match ($type) {
            'video' => 'fa-circle-play',
            'audio' => 'fa-headphones',
            'image' => 'fa-image',
            'link' => 'fa-up-right-from-square',
            'text' => 'fa-align-left',
            'powerpoint' => 'fa-file-powerpoint',
            default => 'fa-file-lines',
        };
    }
}

$courseId = (int)($_GET['course'] ?? 0);

if ($courseId <= 0) {
    echo '<div class="app-alert app-alert-danger">Invalid course.</div>';
    return;
}

$userId = trim((string)(
    $_SESSION[$systemname . 'leido']
    ?? $_SESSION['unique_id']
    ?? ''
));

$role = trim((string)(
    $_SESSION[$systemname . 'letype']
    ?? $_SESSION['role']
    ?? ''
));

if ($userId === '' || $role !== 'afgezt') {
    echo '<div class="app-alert app-alert-danger">Access denied.</div>';
    return;
}

/*
|--------------------------------------------------------------------------
| Student registration
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        cr.intake,
        COALESCE(NULLIF(cr.class_mode, ''), 'both') AS class_mode,
        COALESCE(cr.branch_id, i.branch_id, 0) AS branch_id
    FROM course_registration cr
    LEFT JOIN intakes i ON i.id = cr.intake
    WHERE cr.user_id = ?
      AND cr.course_id = ?
    LIMIT 1
");

if (!$stmt) {
    echo '<div class="app-alert app-alert-danger">Registration query failed: ' . h($conn->error) . '</div>';
    return;
}

$stmt->bind_param('si', $userId, $courseId);
$stmt->execute();
$registration = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$registration) {
    echo '<div class="app-alert app-alert-danger">You are not registered for this course.</div>';
    return;
}

$intakeId = (int)$registration['intake'];
$classMode = (string)($registration['class_mode'] ?: 'both');
$branchId = (int)$registration['branch_id'];

if (!in_array($classMode, ['online', 'physical', 'both'], true)) {
    $classMode = 'both';
}

/*
|--------------------------------------------------------------------------
| Course
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM courses
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    echo '<div class="app-alert app-alert-danger">Course query failed.</div>';
    return;
}

$stmt->bind_param('i', $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    echo '<div class="app-alert app-alert-danger">Course not found.</div>';
    return;
}

/*
|--------------------------------------------------------------------------
| Intake
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM intakes
    WHERE id = ?
    LIMIT 1
");

$intake = null;

if ($stmt) {
    $stmt->bind_param('i', $intakeId);
    $stmt->execute();
    $intake = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($branchId <= 0 && $intake) {
    $branchId = (int)($intake['branch_id'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Modules
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM course_unit
    WHERE course_id = ?
    ORDER BY order_number ASC, id ASC
");

if (!$stmt) {
    echo '<div class="app-alert app-alert-danger">Unable to load course modules.</div>';
    return;
}

$stmt->bind_param('i', $courseId);
$stmt->execute();
$result = $stmt->get_result();

$modules = [];

while ($result && ($row = $result->fetch_assoc())) {
    $modules[] = $row;
}

$stmt->close();

if (!$modules) {
    echo '<div class="app-alert app-alert-warning">No modules are available for this course.</div>';
    return;
}

$progressReady = tableExists($conn, 'topic_material_progress');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">

<style>
.student-course{
    font-size:1rem;
}
.student-course .course-hero{
    background:var(--surface);
    border:1px solid var(--border);
    border-left:5px solid var(--maroon);
    border-radius:12px;
    padding:18px 20px;
    margin-bottom:18px;
}
.student-course .course-hero h2{
    font-size:1.35rem;
    color:var(--maroon);
    margin:0 0 5px;
}
.student-course .hero-meta{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin-top:10px;
}
.student-course .hero-meta span{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:.92rem;
    color:var(--muted);
}
.module-tabs{
    display:flex;
    overflow-x:auto;
    gap:4px;
    border-bottom:1px solid var(--border);
    background:var(--surface);
    border-radius:10px 10px 0 0;
    padding:5px 5px 0;
}
.module-tab{
    border:0;
    background:transparent;
    color:var(--muted);
    padding:11px 15px;
    font-size:.94rem;
    font-weight:600;
    cursor:pointer;
    border-bottom:3px solid transparent;
    white-space:nowrap;
}
.module-tab.active{
    color:var(--maroon);
    border-bottom-color:var(--maroon);
}
.module-panel{
    display:none;
    border:1px solid var(--border);
    border-top:0;
    padding:18px;
    border-radius:0 0 10px 10px;
    background:var(--surface);
}
.module-panel.active{display:block;}
.kind-tabs{
    display:flex;
    gap:5px;
    flex-wrap:wrap;
    margin-bottom:16px;
}
.kind-tab{
    border:1px solid var(--border);
    background:var(--surface2);
    padding:8px 13px;
    border-radius:8px;
    font-size:.92rem;
    cursor:pointer;
    font-weight:600;
    color:var(--muted);
}
.kind-tab.active{
    background:var(--maroon);
    border-color:var(--maroon);
    color:#fff;
}
.kind-panel{display:none;}
.kind-panel.active{display:block;}
.topic-card{
    border:1px solid var(--border);
    border-left:4px solid var(--gold);
    border-radius:10px;
    padding:15px;
    margin-bottom:14px;
    background:var(--surface);
}
.topic-title{
    font-size:1.06rem;
    font-weight:700;
    color:var(--text);
}
.topic-desc{
    font-size:.93rem;
    color:var(--muted);
    margin-top:5px;
    line-height:1.55;
}
.material-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(245px,1fr));
    gap:10px;
    margin-top:12px;
}
.material-card{
    border:1px solid var(--border);
    border-radius:9px;
    padding:12px;
    background:var(--surface2);
}
.material-head{
    display:flex;
    align-items:flex-start;
    gap:9px;
}
.material-icon{
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:8px;
    background:var(--maroon-dim);
    color:var(--maroon);
    flex:0 0 38px;
}
.material-title{
    font-size:.98rem;
    font-weight:700;
    line-height:1.35;
}
.material-type{
    font-size:.88rem;
    color:var(--muted);
    margin-top:3px;
}
.progress-line{
    display:flex;
    justify-content:space-between;
    gap:10px;
    font-size:.86rem;
    margin-top:10px;
}
.progress-track{
    height:8px;
    border-radius:5px;
    background:var(--border);
    overflow:hidden;
    margin-top:5px;
}
.progress-fill{
    height:100%;
    background:var(--gold);
}
.status-badge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:3px 8px;
    border-radius:12px;
    font-size:.82rem;
    font-weight:700;
}
.status-not_started{background:#f1f3f5;color:#6b7280;}
.status-started{background:#fff4d6;color:#9a6700;}
.status-completed{background:#e8f7ee;color:#198754;}
.material-actions,
.assignment-actions{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin-top:11px;
}
.sc-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    border:0;
    border-radius:7px;
    padding:8px 12px;
    font-size:.9rem;
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
}
.sc-primary{background:var(--maroon);color:#fff;}
.sc-gold{background:var(--gold);color:#fff;}
.sc-green{background:#198754;color:#fff;}
.sc-soft{background:#fff;color:var(--text);border:1px solid var(--border);}
.assignment-card,
.quiz-card{
    border:1px solid var(--border);
    border-left:4px solid var(--maroon);
    border-radius:10px;
    padding:14px;
    margin-bottom:12px;
    background:var(--surface);
}
.quiz-card{border-left-color:#4267b2;}
.card-title{
    font-size:1rem;
    font-weight:700;
}
.card-meta{
    font-size:.9rem;
    color:var(--muted);
    line-height:1.5;
    margin-top:5px;
}

/* Viewer */
.viewer-overlay{
    position:fixed;
    inset:0;
    z-index:120000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:12px;
    background:rgba(15,23,42,.72);
}
.viewer-overlay.is-open{display:flex;}
.viewer-box{
    width:min(1000px,98vw);
    height:min(92vh,900px);
    display:flex;
    flex-direction:column;
    background:#fff;
    border-radius:13px;
    overflow:hidden;
    box-shadow:0 25px 70px rgba(0,0,0,.35);
}
.viewer-head{
    flex:0 0 auto;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 16px;
    background:var(--maroon);
    color:#fff;
}
.viewer-title{
    font-size:1rem;
    font-weight:700;
}
.viewer-close{
    width:36px;
    height:36px;
    border:0;
    border-radius:7px;
    background:#fff;
    color:var(--maroon);
    cursor:pointer;
}
.viewer-progress{
    flex:0 0 auto;
    padding:9px 14px;
    background:#202020;
    color:#fff;
}
.viewer-progress-row{
    display:flex;
    justify-content:space-between;
    gap:10px;
    font-size:.88rem;
}
.viewer-track{
    height:7px;
    background:#444;
    border-radius:5px;
    margin-top:6px;
    overflow:hidden;
}
.viewer-fill{
    height:100%;
    background:#1ab394;
}
.viewer-body{
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    background:#404040;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:14px;
}
.viewer-body canvas{
    max-width:100%;
    background:#fff;
    box-shadow:0 4px 20px rgba(0,0,0,.5);
}
.viewer-body iframe{
    width:100%;
    height:100%;
    border:0;
    background:#fff;
}
.viewer-body video{
    max-width:100%;
    max-height:100%;
}
.viewer-body audio{
    width:min(700px,95%);
}
.viewer-body img{
    max-width:100%;
    max-height:100%;
}
.viewer-reading{
    width:min(820px,100%);
    min-height:100%;
    background:#fff;
    padding:28px;
    align-self:flex-start;
    color:#222;
    font-size:1rem;
    line-height:1.75;
}
.viewer-foot{
    flex:0 0 auto;
    display:flex;
    justify-content:space-between;
    gap:8px;
    flex-wrap:wrap;
    padding:10px 14px;
    border-top:1px solid var(--border);
    background:#fff;
}
.pdf-nav{
    display:flex;
    align-items:center;
    gap:8px;
}

/* custom popup */
.popup-overlay{
    position:fixed;
    inset:0;
    z-index:130000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(15,23,42,.62);
}
.popup-overlay.is-open{display:flex;}
.popup-box{
    width:min(430px,100%);
    background:#fff;
    border-radius:13px;
    overflow:hidden;
    box-shadow:0 25px 70px rgba(0,0,0,.3);
}
.popup-head{
    padding:17px 20px 8px;
    font-size:1.05rem;
    font-weight:700;
}
.popup-body{
    padding:5px 20px 18px;
    font-size:.95rem;
    color:var(--muted);
    line-height:1.55;
}
.popup-foot{
    display:flex;
    justify-content:flex-end;
    padding:13px 20px 17px;
    background:var(--surface2);
    border-top:1px solid var(--border);
}

@media(max-width:650px){
    .module-panel{padding:12px;}
    .material-grid{grid-template-columns:1fr;}
    .viewer-overlay{padding:4px;}
    .viewer-box{height:97vh;}
}

/* Assignment layout: 4 per row on desktop */
.student-course .assignment-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.student-course .assignment-grid .assignment-card{min-width:0}

/* My Courses tab */
.student-course .my-courses-tab{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:9px 14px;
    border:1px solid var(--border);
    border-radius:8px;
    background:var(--surface);
    color:var(--maroon);
    text-decoration:none;
    font-size:.94rem;
    font-weight:700;
    white-space:nowrap;
}
@media(max-width:1200px){.student-course .assignment-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:900px){.student-course .assignment-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.student-course .assignment-grid{grid-template-columns:1fr}}


/* Clickable module tabs */
.student-course .module-tab{
    cursor:pointer !important;
    pointer-events:auto !important;
    user-select:none;
}
.student-course .module-tab:hover{
    background:var(--surface2);
    color:var(--maroon);
}
.student-course .module-tab:focus-visible{
    outline:2px solid var(--gold);
    outline-offset:2px;
}


/* Assignment submission modal */
.assignment-modal-overlay{
    position:fixed;
    inset:0;
    z-index:125000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:18px;
    background:rgba(15,23,42,.68);
    backdrop-filter:blur(2px);
    overflow-y:auto;
}
.assignment-modal-overlay.is-open{display:flex;}
.assignment-modal-box{
    width:min(720px,98vw);
    max-height:calc(100vh - 36px);
    display:flex;
    flex-direction:column;
    overflow:hidden;
    background:#fff;
    border-radius:14px;
    box-shadow:0 24px 70px rgba(0,0,0,.32);
}
.assignment-modal-head{
    flex:0 0 auto;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:17px 20px;
    background:var(--maroon);
    color:#fff;
}
.assignment-modal-title{
    font-size:1.08rem;
    font-weight:800;
    line-height:1.35;
}
.assignment-modal-sub{
    margin-top:5px;
    font-size:.9rem;
    opacity:.86;
}
.assignment-modal-close{
    width:36px;
    height:36px;
    flex:0 0 36px;
    border:0;
    border-radius:8px;
    background:#fff;
    color:var(--maroon);
    cursor:pointer;
    font-size:1rem;
}
.assignment-modal-body{
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto;
    padding:18px 20px;
}
.assignment-summary{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:9px;
    margin-bottom:15px;
}
.assignment-summary-item{
    padding:9px 11px;
    border:1px solid var(--border);
    border-radius:8px;
    background:var(--surface2);
}
.assignment-summary-item span{
    display:block;
    font-size:.8rem;
    color:var(--muted);
    margin-bottom:2px;
}
.assignment-summary-item strong{
    display:block;
    font-size:.92rem;
    color:var(--text);
}
.assignment-instructions{
    padding:13px 14px;
    margin-bottom:15px;
    border-left:4px solid var(--maroon);
    border-radius:8px;
    background:var(--surface2);
}
.assignment-instructions strong{
    display:block;
    margin-bottom:6px;
    font-size:.94rem;
}
.assignment-instructions div{
    font-size:.93rem;
    line-height:1.6;
    color:var(--muted);
}
.assignment-resource-list{
    display:grid;
    gap:8px;
    margin:8px 0 16px;
}
.assignment-resource{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:9px 11px;
    border:1px solid var(--border);
    border-radius:8px;
    background:#fff;
}
.assignment-resource-name{
    min-width:0;
    font-size:.9rem;
    font-weight:700;
    overflow-wrap:anywhere;
}
.assignment-resource-type{
    display:block;
    margin-top:2px;
    font-size:.79rem;
    font-weight:500;
    color:var(--muted);
}
.assignment-form-field{margin-bottom:14px;}
.assignment-form-field label{
    display:block;
    margin-bottom:6px;
    font-size:.91rem;
    font-weight:700;
    color:var(--text);
}
.assignment-form-field input,
.assignment-form-field textarea{
    width:100%;
    padding:10px 11px;
    border:1px solid var(--border);
    border-radius:8px;
    background:#fff;
    color:var(--text);
    font-size:.94rem;
    font-family:inherit;
}
.assignment-form-field textarea{
    min-height:125px;
    resize:vertical;
}
.assignment-submit-help{
    margin-top:5px;
    font-size:.8rem;
    line-height:1.45;
    color:var(--muted);
}
.assignment-modal-foot{
    flex:0 0 auto;
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding:13px 20px;
    border-top:1px solid var(--border);
    background:var(--surface2);
}
.assignment-warning{
    display:none;
    padding:10px 12px;
    margin-bottom:14px;
    border-radius:8px;
    background:#fff4d6;
    color:#8a6200;
    font-size:.88rem;
    line-height:1.5;
}
.assignment-warning.show{display:block;}
.assignment-submit-status{
    display:none;
    padding:10px 12px;
    margin-bottom:14px;
    border-radius:8px;
    font-size:.9rem;
    line-height:1.5;
}
.assignment-submit-status.show{display:block;}
.assignment-submit-status.error{background:#fdecec;color:#b42318;}
.assignment-submit-status.success{background:#e8f7ee;color:#198754;}
@media(max-width:650px){
    .assignment-modal-overlay{padding:5px;}
    .assignment-modal-box{max-height:97vh;}
    .assignment-summary{grid-template-columns:1fr;}
}


/* Assignment details / file viewer */
.assignment-viewer-wrap{
    margin-bottom:16px;
}
.assignment-file-preview{
    border:1px solid var(--border);
    border-radius:10px;
    overflow:hidden;
    background:var(--surface2);
}
.assignment-file-preview iframe{
    width:100%;
    min-height:520px;
    border:0;
    background:#fff;
}
.assignment-file-preview .preview-empty{
    padding:22px;
    text-align:center;
    color:var(--muted);
    font-size:.92rem;
}
.assignment-detail-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:12px;
}
.assignment-detail-meta{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px;
    margin-bottom:14px;
}
.assignment-detail-meta .meta-box{
    border:1px solid var(--border);
    border-radius:8px;
    padding:9px 10px;
    background:var(--surface2);
}
.assignment-detail-meta .meta-box span{
    display:block;
    color:var(--muted);
    font-size:.78rem;
    margin-bottom:2px;
}
.assignment-detail-meta .meta-box strong{
    display:block;
    font-size:.9rem;
}
.assignment-view-btn{
    background:var(--surface);
    color:var(--maroon);
    border:1px solid var(--maroon);
}
@media(max-width:800px){
    .assignment-detail-meta{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:520px){
    .assignment-detail-meta{
        grid-template-columns:1fr;
    }
}


/* Quizzes: 4 per row on desktop */
.student-course .quiz-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    align-items:stretch;
}

.student-course .quiz-grid .quiz-card{
    min-width:0;
    height:100%;
    margin-bottom:0;
    display:flex;
    flex-direction:column;
}

.student-course .quiz-grid .assignment-actions{
    margin-top:auto;
}

@media(max-width:1200px){
    .student-course .quiz-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}

@media(max-width:900px){
    .student-course .quiz-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:650px){
    .student-course .quiz-grid{
        grid-template-columns:1fr;
    }
}

</style>

<nav class="topbar">
    <div class="topbar-left">
        <h1>
            <i class="fas fa-book-open"></i>
            <?php echo h($course['name']); ?>
        </h1>
    </div>
    <div class="topbar-right">
        <i class="fas fa-clock"></i>
        <?php echo date('h:i a'); ?>
    </div>
</nav>

<main class="page student-course">
    <div style="margin-bottom:12px;">
        <a class="my-courses-tab" href="?view=courses">
            <i class="fas fa-arrow-left"></i> My Courses
        </a>
    </div>

    <div class="course-hero">
        <h2><?php echo h($course['name']); ?></h2>

        <div class="hero-meta">
            <span>
                <i class="fas fa-layer-group"></i>
                <?php echo count($modules); ?> module(s)
            </span>

            <?php if ($intake): ?>
                <span>
                    <i class="fas fa-calendar"></i>
                    <?php echo h($intake['name'] ?? ''); ?>
                </span>
            <?php endif; ?>

            <span>
                <i class="fas fa-users"></i>
                <?php echo h(ucfirst($classMode)); ?> class
            </span>
        </div>
    </div>

    <?php if (!$progressReady): ?>
        <div class="app-alert app-alert-warning" style="margin-bottom:15px;">
            Material progress tracking is not yet enabled. Run
            <strong>sql/material_engagement.sql</strong>.
        </div>
    <?php endif; ?>

    <div class="module-tabs">
        <?php foreach ($modules as $index => $module): ?>
            <button
                type="button"
                class="module-tab <?php echo $index === 0 ? 'active' : ''; ?>"
                data-module-index="<?php echo $index; ?>"
                data-module-id="<?php echo (int)$module['id']; ?>"
                onclick="showModule(<?php echo $index; ?>, this, <?php echo (int)$module['id']; ?>)"
                title="Open <?php echo h($module['name']); ?>"
                aria-controls="module-<?php echo $index; ?>"
                aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
            >
                <i class="fas fa-cube"></i>
                <span><?php echo h($module['name']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($modules as $index => $module): ?>
        <?php
        $moduleId = (int)$module['id'];

        /*
        |--------------------------------------------------------------------------
        | Topics and materials
        |--------------------------------------------------------------------------
        */

        $topics = [];

        $stmt = $conn->prepare("
            SELECT *
            FROM course_topics
            WHERE course_unit_id = ?
              AND COALESCE(intake_id, 0) IN (0, ?)
              AND COALESCE(status, 'active') = 'active'
              AND (
                    COALESCE(class_mode, 'both') = 'both'
                    OR COALESCE(class_mode, 'both') = ?
                    OR ? = 'both'
              )
            ORDER BY order_number ASC, id ASC
        ");

        if ($stmt) {
            $stmt->bind_param(
                'iiss',
                $moduleId,
                $intakeId,
                $classMode,
                $classMode
            );

            $stmt->execute();
            $topicResult = $stmt->get_result();

            while ($topicResult && ($topic = $topicResult->fetch_assoc())) {
                $topic['materials'] = [];

                $topicId = (int)$topic['id'];

                $materialSql = $progressReady
                    ? "
                        SELECT
                            tm.*,
                            COALESCE(mp.progress_percentage, 0) AS student_progress,
                            COALESCE(mp.status, 'not_started') AS student_status,
                            COALESCE(mp.total_views, 0) AS student_views,
                            COALESCE(mp.total_downloads, 0) AS student_downloads
                        FROM topic_materials tm
                        LEFT JOIN topic_material_progress mp
                          ON mp.material_id = tm.id
                         AND mp.user_id = ?
                        WHERE tm.topic_id = ?
                          AND COALESCE(tm.status, 'active') = 'active'
                        ORDER BY tm.order_number ASC, tm.id ASC
                    "
                    : "
                        SELECT
                            tm.*,
                            0 AS student_progress,
                            'not_started' AS student_status,
                            0 AS student_views,
                            0 AS student_downloads
                        FROM topic_materials tm
                        WHERE tm.topic_id = ?
                          AND COALESCE(tm.status, 'active') = 'active'
                        ORDER BY tm.order_number ASC, tm.id ASC
                    ";

                $materialStmt = $conn->prepare($materialSql);

                if ($materialStmt) {
                    if ($progressReady) {
                        $materialStmt->bind_param('si', $userId, $topicId);
                    } else {
                        $materialStmt->bind_param('i', $topicId);
                    }

                    $materialStmt->execute();
                    $materialResult = $materialStmt->get_result();

                    while ($materialResult && ($material = $materialResult->fetch_assoc())) {
                        $topic['materials'][] = $material;
                    }

                    $materialStmt->close();
                }

                $topics[] = $topic;
            }

            $stmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | Assignments
        |--------------------------------------------------------------------------
        */

        $assignments = [];

        $stmt = $conn->prepare("
            SELECT
                a.*,
                (
                    SELECT COUNT(*)
                    FROM handed_in hi
                    WHERE hi.assignment_id = a.id
                      AND hi.user_id = ?
                ) AS my_submissions
            FROM assignments a
            WHERE a.course_unit = ?
              AND a.intake = ?
              AND COALESCE(a.dummy, 'no') = 'no'
              AND COALESCE(a.status, 'active') = 'active'
              AND (
                    COALESCE(a.class_mode, 'both') = 'both'
                    OR COALESCE(a.class_mode, 'both') = ?
                    OR ? = 'both'
              )
            ORDER BY a.id DESC
        ");

        if ($stmt) {
            $stmt->bind_param(
                'siiss',
                $userId,
                $moduleId,
                $intakeId,
                $classMode,
                $classMode
            );

            $stmt->execute();
            $assignmentResult = $stmt->get_result();

            while ($assignmentResult && ($assignment = $assignmentResult->fetch_assoc())) {
                $assignments[] = $assignment;
            }

            $stmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | Quizzes
        |--------------------------------------------------------------------------
        */

        $quizzes = [];

        $stmt = $conn->prepare("
            SELECT
                q.*,
                (
                    SELECT MAX(qa.percentage)
                    FROM quiz_attempts qa
                    WHERE qa.quiz_id = q.id
                      AND qa.user_id = ?
                ) AS best_percentage
            FROM module_quizzes q
            WHERE q.course_unit_id = ?
              AND COALESCE(q.intake_id, 0) IN (0, ?)
              AND COALESCE(q.status, 'active') = 'active'
              AND (
                    COALESCE(q.class_mode, 'both') = 'both'
                    OR COALESCE(q.class_mode, 'both') = ?
                    OR ? = 'both'
              )
            ORDER BY q.id DESC
        ");

        if ($stmt) {
            $stmt->bind_param(
                'siiss',
                $userId,
                $moduleId,
                $intakeId,
                $classMode,
                $classMode
            );

            $stmt->execute();
            $quizResult = $stmt->get_result();

            while ($quizResult && ($quiz = $quizResult->fetch_assoc())) {
                $quizzes[] = $quiz;
            }

            $stmt->close();
        }
        ?>

        <section
            class="module-panel <?php echo $index === 0 ? 'active' : ''; ?>"
            id="module-<?php echo $index; ?>"
        >

            <div class="kind-tabs">
                <button
                    type="button"
                    class="kind-tab active"
                    onclick="showKind(<?php echo $index; ?>, 'topics', this)"
                >
                    <i class="fas fa-book-open"></i>
                    Topics (<?php echo count($topics); ?>)
                </button>

                <button
                    type="button"
                    class="kind-tab"
                    onclick="showKind(<?php echo $index; ?>, 'assignments', this)"
                >
                    <i class="fas fa-tasks"></i>
                    Assignments (<?php echo count($assignments); ?>)
                </button>

                <button
                    type="button"
                    class="kind-tab"
                    onclick="showKind(<?php echo $index; ?>, 'quizzes', this)"
                >
                    <i class="fas fa-circle-question"></i>
                    Quizzes (<?php echo count($quizzes); ?>)
                </button>
            </div>

            <div
                class="kind-panel active"
                id="kind-<?php echo $index; ?>-topics"
            >
                <?php if (!$topics): ?>
                    <div class="empty">
                        <i class="fas fa-book"></i>
                        <p>No learning topics are available in this module yet.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($topics as $topic): ?>
                    <div class="topic-card">
                        <div class="topic-title">
                            <?php echo h($topic['title']); ?>
                        </div>

                        <?php if (!empty($topic['description'])): ?>
                            <div class="topic-desc">
                                <?php echo nl2br(h($topic['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="material-grid">
                            <?php foreach ($topic['materials'] as $material): ?>
                                <?php
                                $materialId = (int)$material['id'];
                                $type = (string)$material['material_type'];
                                $progress = (float)$material['student_progress'];
                                $status = (string)$material['student_status'];

                                if (!in_array($status, ['not_started', 'started', 'completed'], true)) {
                                    $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'started' : 'not_started');
                                }

                                $ext = strtolower(
                                    pathinfo(
                                        (string)($material['document'] ?? ''),
                                        PATHINFO_EXTENSION
                                    )
                                );

                                $archives = [
                                    'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2'
                                ];

                                $downloadOnly = in_array($ext, $archives, true);
                                ?>

                                <div
                                    class="material-card"
                                    id="material-card-<?php echo $materialId; ?>"
                                >
                                    <div class="material-head">
                                        <span class="material-icon">
                                            <i class="fas <?php echo h(materialIcon($type)); ?>"></i>
                                        </span>

                                        <div>
                                            <div class="material-title">
                                                <?php echo h($material['title']); ?>
                                            </div>

                                            <div class="material-type">
                                                <?php echo h(ucwords(str_replace('_', ' ', $type))); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="progress-line">
                                        <span
                                            class="status-badge status-<?php echo h($status); ?>"
                                            id="status-<?php echo $materialId; ?>"
                                        >
                                            <?php
                                            echo match ($status) {
                                                'completed' => 'Completed',
                                                'started' => 'In Progress',
                                                default => 'Not Started',
                                            };
                                            ?>
                                        </span>

                                        <strong id="pct-<?php echo $materialId; ?>">
                                            <?php echo (int)round($progress); ?>%
                                        </strong>
                                    </div>

                                    <div class="progress-track">
                                        <div
                                            class="progress-fill"
                                            id="bar-<?php echo $materialId; ?>"
                                            style="width:<?php echo max(0, min(100, $progress)); ?>%;"
                                        ></div>
                                    </div>

                                    <div class="material-actions">

                                        <?php if ($downloadOnly): ?>
                                            <a
                                                class="sc-btn sc-green"
                                                href="scripts/material_file.php?id=<?php echo $materialId; ?>&mode=download"
                                                onclick="recordDownload(<?php echo $materialId; ?>)"
                                            >
                                                <i class="fas fa-download"></i>
                                                Download
                                            </a>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                class="sc-btn sc-primary"
                                                onclick='openMaterial(<?php
                                                    echo json_encode([
                                                        'id' => $materialId,
                                                        'title' => (string)$material['title'],
                                                        'type' => $type,
                                                        'url' => (string)($material['external_url'] ?? ''),
                                                        'content' => (string)($material['reading_content'] ?? ''),
                                                        'ext' => $ext,
                                                        'progress' => $progress,
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                                ?>)'
                                            >
                                                <i class="fas fa-eye"></i>
                                                View
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($status !== 'completed'): ?>
                                            <button
                                                type="button"
                                                class="sc-btn sc-soft"
                                                id="complete-btn-<?php echo $materialId; ?>"
                                                onclick="markMaterialComplete(<?php echo $materialId; ?>)"
                                            >
                                                <i class="fas fa-check"></i>
                                                Mark Complete
                                            </button>
                                        <?php endif; ?>

                                    </div>
                                </div>

                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div
                class="kind-panel"
                id="kind-<?php echo $index; ?>-assignments"
            >
                <?php if (!$assignments): ?>
                    <div class="empty">
                        <p>No assignments are available in this module.</p>
                    </div>
                <?php endif; ?>

                <?php if ($assignments): ?>
                    <div class="assignment-grid">
                <?php endif; ?>

                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $assignmentId = (int)$assignment['id'];
                    $submissionType = (string)($assignment['submission_type'] ?? 'file');

                    if (!in_array(
                        $submissionType,
                        ['file', 'text', 'link', 'file_text', 'any'],
                        true
                    )) {
                        $submissionType = 'file';
                    }

                    $maxAttempts = max(1, (int)($assignment['max_attempts'] ?? 1));
                    $myAttempts = (int)($assignment['my_submissions'] ?? 0);

                    $allowLate = (int)($assignment['allow_late_submission'] ?? 0) === 1;
                    $required = (int)($assignment['required_for_completion'] ?? 0) === 1;

                    $dueTimestamp = !empty($assignment['date_due'])
                        ? strtotime((string)$assignment['date_due'])
                        : false;

                    $isLate = $dueTimestamp !== false && $dueTimestamp < time();
                    $attemptLimitReached = $myAttempts >= $maxAttempts;
                    $submissionClosed = $attemptLimitReached || ($isLate && !$allowLate);

                    $resources = [];

                    if (tableExists($conn, 'assignment_resources')) {
                        $resourceStmt = $conn->prepare("
                            SELECT *
                            FROM assignment_resources
                            WHERE assignment_id = ?
                            ORDER BY id ASC
                        ");

                        if ($resourceStmt) {
                            $resourceStmt->bind_param('i', $assignmentId);
                            $resourceStmt->execute();
                            $resourceResult = $resourceStmt->get_result();

                            while ($resourceResult && ($resource = $resourceResult->fetch_assoc())) {
                                $resourceType = (string)(
                                    $resource['resource_type']
                                    ?? $resource['type']
                                    ?? 'file'
                                );

                                $resourceDocument = (string)(
                                    $resource['document']
                                    ?? $resource['resource_file']
                                    ?? ''
                                );

                                $resourceUrl = (string)(
                                    $resource['external_url']
                                    ?? $resource['resource_url']
                                    ?? ''
                                );

                                $resourceText = (string)(
                                    $resource['resource_text']
                                    ?? $resource['text_content']
                                    ?? ''
                                );

                                $resourceHref = '';

                                if ($resourceType === 'link') {
                                    $resourceHref = $resourceUrl;
                                } elseif ($resourceType === 'file' && $resourceDocument !== '') {
                                    $resourceHref =
                                        'course_materials/' .
                                        rawurlencode(basename($resourceDocument));
                                }

                                $resources[] = [
                                    'title' => (string)(
                                        $resource['title']
                                        ?? $resource['resource_title']
                                        ?? 'Assignment Resource'
                                    ),
                                    'type' => $resourceType,
                                    'href' => $resourceHref,
                                    'text' => $resourceText,
                                ];
                            }

                            $resourceStmt->close();
                        }
                    }

                    $assignmentContentType = (string)($assignment['content_type'] ?? 'file');
                    $assignmentDocument = (string)($assignment['document'] ?? '');
                    $assignmentUrl = trim((string)($assignment['assignment_url'] ?? ''));

                    $assignmentFileUrl = '';
                    if ($assignmentDocument !== '') {
                        $assignmentFileUrl =
                            '/elearning/assignments/agjashdbsjdhkhsdvfh/' .
                            rawurlencode($assignmentDocument) .
                            '?assignment_id=' . $assignmentId;
                    }

                    $assignmentPayload = [
                        'id' => $assignmentId,
                        'module_id' => $moduleId,
                        'course_id' => $courseId,
                        'title' => (string)$assignment['title'],
                        'instructions' => (string)($assignment['short_message'] ?? ''),
                        'points' => (string)($assignment['marks'] ?? 0),
                        'due' => safeDate($assignment['date_due'] ?? null),
                        'due_raw' => (string)($assignment['date_due'] ?? ''),
                        'submission_type' => $submissionType,
                        'max_attempts' => $maxAttempts,
                        'my_attempts' => $myAttempts,
                        'allow_late' => $allowLate,
                        'required' => $required,
                        'late' => $isLate,
                        'closed' => $submissionClosed,
                        'resources' => $resources,
                        'content_type' => $assignmentContentType,
                        'document' => $assignmentDocument,
                        'file_url' => $assignmentFileUrl,
                        'assignment_url' => $assignmentUrl,
                    ];
                    ?>

                    <div class="assignment-card">
                        <div class="card-title">
                            <?php echo h($assignment['title']); ?>
                        </div>

                        <div class="card-meta">
                            <?php echo h((string)$assignment['marks']); ?> points
                            . Due <?php echo h(safeDate($assignment['date_due'] ?? null)); ?>
                        </div>

                        <div class="card-meta">
                            Submission:
                            <strong>
                                <?php
                                echo h(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' or ',
                                            $submissionType
                                        )
                                    )
                                );
                                ?>
                            </strong>
                            . Attempts:
                            <strong>
                                <?php echo $myAttempts; ?>/<?php echo $maxAttempts; ?>
                            </strong>
                        </div>

                        <?php if ($required): ?>
                            <div class="card-meta">
                                <i class="fas fa-circle-check"></i>
                                Required for module completion
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($assignment['short_message'])): ?>
                            <div class="card-meta">
                                <?php echo nl2br(h($assignment['short_message'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="assignment-actions">
                            <button
                                type="button"
                                class="sc-btn assignment-view-btn"
                                onclick='openAssignmentDetails(<?php
                                    echo json_encode(
                                        $assignmentPayload,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_AMP |
                                        JSON_HEX_QUOT
                                    );
                                ?>)'
                            >
                                <i class="fas fa-eye"></i>
                                View Assignment
                            </button>

                            <?php if (!$submissionClosed): ?>
                                <button
                                    type="button"
                                    class="sc-btn sc-primary"
                                    onclick='openAssignmentModal(<?php
                                        echo json_encode(
                                            $assignmentPayload,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_AMP |
                                            JSON_HEX_QUOT
                                        );
                                    ?>)'
                                >
                                    <i class="fas fa-upload"></i>
                                    Submit Assignment
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($assignments): ?>
                    </div>
                <?php endif; ?>
            </div>

            <div
                class="kind-panel"
                id="kind-<?php echo $index; ?>-quizzes"
            >
                <?php if (!$quizzes): ?>
                    <div class="empty">
                        <p>No quizzes are available in this module.</p>
                    </div>
                <?php endif; ?>

                <?php if ($quizzes): ?>
                    <div class="quiz-grid">
                <?php endif; ?>

                <?php foreach ($quizzes as $quiz): ?>
                    <?php
                    $best = $quiz['best_percentage'];
                    $passed = $best !== null
                        && (float)$best >= (float)$quiz['passing_score'];
                    ?>

                    <div class="quiz-card">
                        <div class="card-title">
                            <?php echo h($quiz['title']); ?>
                        </div>

                        <div class="card-meta">
                            <?php echo h((string)$quiz['total_points']); ?> points
                            . Pass <?php echo h((string)$quiz['passing_score']); ?>%
                            . Maximum <?php echo (int)$quiz['max_attempts']; ?> attempt(s)
                        </div>

                        <?php if ($best !== null): ?>
                            <div class="card-meta">
                                Best score:
                                <strong><?php echo h((string)$best); ?>%</strong>
                                <?php echo $passed ? '. Passed' : '. Not passed yet'; ?>
                            </div>
                        <?php endif; ?>

                        <div class="assignment-actions">
                            <a
                                class="sc-btn sc-primary"
                                href="?view=take_quiz&quiz_id=<?php echo (int)$quiz['id']; ?>"
                            >
                                <i class="fas fa-circle-question"></i>
                                <?php echo $best === null ? 'Take Quiz' : 'Open Quiz'; ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($quizzes): ?>
                    </div>
                <?php endif; ?>
            </div>

        </section>
    <?php endforeach; ?>

</main>



<!-- Assignment Details Modal -->
<div
    class="assignment-modal-overlay"
    id="assignmentDetailsModal"
    aria-hidden="true"
>
    <div
        class="assignment-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="assignmentDetailsTitle"
    >
        <div class="assignment-modal-head">
            <div>
                <div
                    class="assignment-modal-title"
                    id="assignmentDetailsTitle"
                >
                    Assignment Details
                </div>

                <div
                    class="assignment-modal-sub"
                    id="assignmentDetailsSub"
                ></div>
            </div>

            <button
                type="button"
                class="assignment-modal-close"
                onclick="closeAssignmentDetails()"
                aria-label="Close"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="assignment-modal-body">
            <div class="assignment-detail-meta">
                <div class="meta-box">
                    <span>Points</span>
                    <strong id="detailsPoints">0</strong>
                </div>

                <div class="meta-box">
                    <span>Due Date</span>
                    <strong id="detailsDue">-</strong>
                </div>

                <div class="meta-box">
                    <span>Submission Type</span>
                    <strong id="detailsSubmissionType">File</strong>
                </div>

                <div class="meta-box">
                    <span>Attempts</span>
                    <strong id="detailsAttempts">0 / 1</strong>
                </div>
            </div>

            <div class="assignment-instructions">
                <strong>
                    <i class="fas fa-list-check"></i>
                    Instructions
                </strong>

                <div id="detailsInstructions">
                    No instructions provided.
                </div>
            </div>

            <div
                id="detailsResourcesWrap"
                style="display:none;"
            >
                <strong style="font-size:.94rem;">
                    <i class="fas fa-paperclip"></i>
                    Assignment Resources
                </strong>

                <div
                    class="assignment-resource-list"
                    id="detailsResources"
                ></div>
            </div>

            <div
                class="assignment-viewer-wrap"
                id="assignmentMainFileWrap"
                style="display:none;"
            >
                <strong style="display:block;font-size:.94rem;margin-bottom:8px;">
                    <i class="fas fa-file"></i>
                    Assignment File / Link
                </strong>

                <div
                    class="assignment-file-preview"
                    id="assignmentMainPreview"
                ></div>
            </div>

            <div
                class="assignment-warning"
                id="detailsWarning"
            ></div>
        </div>

        <div class="assignment-modal-foot">
            <button
                type="button"
                class="sc-btn sc-soft"
                onclick="closeAssignmentDetails()"
            >
                Close
            </button>

            <button
                type="button"
                class="sc-btn sc-primary"
                id="detailsSubmitButton"
                onclick="submitFromDetails()"
            >
                <i class="fas fa-upload"></i>
                Submit Assignment
            </button>
        </div>
    </div>
</div>

<!-- Assignment Submission Modal -->
<div
    class="assignment-modal-overlay"
    id="assignmentSubmitModal"
    aria-hidden="true"
>
    <div
        class="assignment-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="assignmentModalTitle"
    >
        <div class="assignment-modal-head">
            <div>
                <div
                    class="assignment-modal-title"
                    id="assignmentModalTitle"
                >
                    Submit Assignment
                </div>

                <div
                    class="assignment-modal-sub"
                    id="assignmentModalSubtitle"
                ></div>
            </div>

            <button
                type="button"
                class="assignment-modal-close"
                onclick="closeAssignmentModal()"
                aria-label="Close"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form
            id="studentAssignmentForm"
            enctype="multipart/form-data"
        >
            <div class="assignment-modal-body">

                <div
                    class="assignment-submit-status"
                    id="assignmentSubmitStatus"
                ></div>

                <div class="assignment-summary">
                    <div class="assignment-summary-item">
                        <span>Points</span>
                        <strong id="assignmentPoints">0</strong>
                    </div>

                    <div class="assignment-summary-item">
                        <span>Due Date</span>
                        <strong id="assignmentDue">-</strong>
                    </div>

                    <div class="assignment-summary-item">
                        <span>Submission Type</span>
                        <strong id="assignmentSubmissionType">File</strong>
                    </div>

                    <div class="assignment-summary-item">
                        <span>Attempts</span>
                        <strong id="assignmentAttempts">0 / 1</strong>
                    </div>
                </div>

                <div class="assignment-instructions">
                    <strong>
                        <i class="fas fa-list-check"></i>
                        Instructions
                    </strong>

                    <div id="assignmentInstructions">
                        No instructions provided.
                    </div>
                </div>

                <div
                    id="assignmentResourcesWrap"
                    style="display:none;"
                >
                    <strong style="font-size:.94rem;">
                        <i class="fas fa-paperclip"></i>
                        Assignment Resources
                    </strong>

                    <div
                        class="assignment-resource-list"
                        id="assignmentResources"
                    ></div>
                </div>

                <div
                    class="assignment-warning"
                    id="assignmentWarning"
                ></div>

                <input
                    type="hidden"
                    name="assignment_id"
                    id="submitAssignmentId"
                >

                <input
                    type="hidden"
                    name="course_id"
                    value="<?php echo $courseId; ?>"
                >

                <input
                    type="hidden"
                    name="course"
                    value="<?php echo $courseId; ?>"
                >

                <input
                    type="hidden"
                    name="course_unit_id"
                    id="submitModuleId"
                >

                <input
                    type="hidden"
                    name="themodule"
                    id="submitTheModule"
                >

                <input
                    type="hidden"
                    name="submission_type"
                    id="submitType"
                >

                <div
                    class="assignment-form-field"
                    id="fileSubmissionField"
                    style="display:none;"
                >
                    <label for="assignmentFile">
                        <i class="fas fa-file-arrow-up"></i>
                        Upload File
                    </label>

                    <input
                        type="file"
                        name="submission_file"
                        id="assignmentFile"
                    >

                    <!-- Legacy field name for existing handlers -->
                    <input
                        type="file"
                        name="notesfile"
                        id="assignmentFileLegacy"
                        style="display:none;"
                    >

                    <div class="assignment-submit-help">
                        Upload the completed assignment file requested by the instructor.
                    </div>
                </div>

                <div
                    class="assignment-form-field"
                    id="textSubmissionField"
                    style="display:none;"
                >
                    <label for="assignmentText">
                        <i class="fas fa-align-left"></i>
                        Text Response
                    </label>

                    <textarea
                        name="text_response"
                        id="assignmentText"
                        placeholder="Type your assignment response here..."
                    ></textarea>
                </div>

                <div
                    class="assignment-form-field"
                    id="linkSubmissionField"
                    style="display:none;"
                >
                    <label for="assignmentLink">
                        <i class="fas fa-link"></i>
                        Submission Link
                    </label>

                    <input
                        type="url"
                        name="submission_url"
                        id="assignmentLink"
                        placeholder="https://..."
                    >
                </div>

                <input
                    type="hidden"
                    name="expectedfiletype"
                    id="expectedFileType"
                    value=""
                >
            </div>

            <div class="assignment-modal-foot">
                <button
                    type="button"
                    class="sc-btn sc-soft"
                    onclick="closeAssignmentModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="sc-btn sc-primary"
                    id="assignmentSubmitButton"
                >
                    <i class="fas fa-paper-plane"></i>
                    Submit Assignment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Material Viewer -->
<div class="viewer-overlay" id="materialViewer" aria-hidden="true">
    <div class="viewer-box">
        <div class="viewer-head">
            <div class="viewer-title" id="viewerTitle">
                Learning Material
            </div>

            <button
                type="button"
                class="viewer-close"
                onclick="closeViewer()"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="viewer-progress">
            <div class="viewer-progress-row">
                <span id="viewerStatus">In Progress</span>
                <strong id="viewerPct">0%</strong>
            </div>

            <div class="viewer-track">
                <div
                    class="viewer-fill"
                    id="viewerBar"
                    style="width:0%;"
                ></div>
            </div>
        </div>

        <div class="viewer-body" id="viewerBody"></div>

        <div class="viewer-foot">
            <div class="pdf-nav" id="pdfNav" style="display:none;">
                <button
                    type="button"
                    class="sc-btn sc-soft"
                    id="prevPage"
                >
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </button>

                <span id="pageInfo">Page 1 / 1</span>

                <button
                    type="button"
                    class="sc-btn sc-soft"
                    id="nextPage"
                >
                    Next
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <button
                type="button"
                class="sc-btn sc-green"
                onclick="completeCurrentMaterial()"
            >
                <i class="fas fa-check-circle"></i>
                Mark as Complete
            </button>
        </div>
    </div>
</div>

<!-- Custom Popup -->
<div class="popup-overlay" id="messagePopup">
    <div class="popup-box">
        <div class="popup-head" id="popupTitle">Success</div>
        <div class="popup-body" id="popupMessage"></div>
        <div class="popup-foot">
            <button
                type="button"
                class="sc-btn sc-primary"
                onclick="closePopup()"
            >
                OK
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>
'use strict';

if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

let currentMaterial = null;
let currentPdf = null;
let currentPdfPage = 1;
let pdfRendering = false;
let heartbeatTimer = null;
let lastProgressSent = 0;


let currentAssignment = null;

function humanSubmissionType(type) {
    const labels = {
        file: 'File Upload',
        text: 'Text Response',
        link: 'Link',
        file_text: 'File or Text',
        any: 'File, Text or Link'
    };

    return labels[type] || 'File Upload';
}

function escapeAttribute(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}


let assignmentDetailsCurrent = null;

function openAssignmentDetails(assignment) {
    assignmentDetailsCurrent = assignment;

    document.getElementById('assignmentDetailsTitle').textContent =
        assignment.title || 'Assignment Details';

    document.getElementById('assignmentDetailsSub').textContent =
        'Review the assignment before submitting your work.';

    document.getElementById('detailsPoints').textContent =
        assignment.points + ' point(s)';

    document.getElementById('detailsDue').textContent =
        assignment.due || '-';

    document.getElementById('detailsSubmissionType').textContent =
        humanSubmissionType(assignment.submission_type);

    document.getElementById('detailsAttempts').textContent =
        assignment.my_attempts + ' / ' + assignment.max_attempts;

    document.getElementById('detailsInstructions').textContent =
        assignment.instructions || 'No instructions provided.';

    renderDetailsResources(assignment.resources || []);
    renderAssignmentMainFile(assignment);

    const warning = document.getElementById('detailsWarning');
    warning.className = 'assignment-warning';
    warning.textContent = '';

    if (assignment.my_attempts >= assignment.max_attempts) {
        warning.textContent =
            'You have reached the maximum number of submission attempts.';
        warning.classList.add('show');
    } else if (assignment.late && !assignment.allow_late) {
        warning.textContent =
            'The submission deadline has passed and late submissions are not allowed.';
        warning.classList.add('show');
    } else if (assignment.late && assignment.allow_late) {
        warning.textContent =
            'The due date has passed, but the instructor allows late submissions.';
        warning.classList.add('show');
    }

    const submitButton = document.getElementById('detailsSubmitButton');
    submitButton.style.display = assignment.closed ? 'none' : 'inline-flex';

    const modal = document.getElementById('assignmentDetailsModal');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function renderDetailsResources(resources) {
    const wrap = document.getElementById('detailsResourcesWrap');
    const list = document.getElementById('detailsResources');

    list.innerHTML = '';

    if (!Array.isArray(resources) || resources.length === 0) {
        wrap.style.display = 'none';
        return;
    }

    resources.forEach(resource => {
        const row = document.createElement('div');
        row.className = 'assignment-resource';

        const left = document.createElement('div');
        left.className = 'assignment-resource-name';

        const title = document.createElement('div');
        title.textContent = resource.title || 'Assignment Resource';
        left.appendChild(title);

        const type = document.createElement('span');
        type.className = 'assignment-resource-type';
        type.textContent =
            String(resource.type || 'file')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, char => char.toUpperCase());

        left.appendChild(type);
        row.appendChild(left);

        if (resource.href) {
            const link = document.createElement('a');
            link.className = 'sc-btn sc-soft';
            link.href = resource.href;
            link.target = '_blank';
            link.rel = 'noopener';
            link.innerHTML = '<i class="fas fa-eye"></i> View';
            row.appendChild(link);
        } else if (resource.text) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sc-btn sc-soft';
            button.innerHTML = '<i class="fas fa-eye"></i> Read';
            button.addEventListener('click', () => {
                showPopup(resource.text, resource.title || 'Resource');
            });
            row.appendChild(button);
        }

        list.appendChild(row);
    });

    wrap.style.display = '';
}

function renderAssignmentMainFile(assignment) {
    const wrap = document.getElementById('assignmentMainFileWrap');
    const preview = document.getElementById('assignmentMainPreview');

    preview.innerHTML = '';
    wrap.style.display = 'none';

    if (assignment.content_type === 'url' && assignment.assignment_url) {
        wrap.style.display = '';

        preview.innerHTML =
            '<div class="preview-empty">' +
            '<p>This assignment uses an external resource.</p>' +
            '<a class="sc-btn sc-primary" target="_blank" rel="noopener" href="' +
            escapeAttribute(assignment.assignment_url) +
            '"><i class="fas fa-up-right-from-square"></i> Open Assignment Link</a>' +
            '</div>';

        return;
    }

    if (!assignment.file_url) {
        return;
    }

    wrap.style.display = '';

    const ext = String(assignment.document || '')
        .split('.')
        .pop()
        .toLowerCase();

    const previewable = ['pdf','txt','html','htm','jpg','jpeg','png','gif','webp'];

    if (previewable.includes(ext)) {
        const iframe = document.createElement('iframe');
        iframe.src = assignment.file_url;
        iframe.title = assignment.title || 'Assignment file';
        preview.appendChild(iframe);
    } else {
        preview.innerHTML =
            '<div class="preview-empty">' +
            '<p>This assignment file cannot be previewed directly in the browser.</p>' +
            '<a class="sc-btn sc-primary" target="_blank" rel="noopener" href="' +
            escapeAttribute(assignment.file_url) +
            '"><i class="fas fa-eye"></i> Open Assignment File</a>' +
            '</div>';
    }
}

function closeAssignmentDetails() {
    const modal = document.getElementById('assignmentDetailsModal');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    document.getElementById('assignmentMainPreview').innerHTML = '';

    if (
        !document.getElementById('assignmentSubmitModal').classList.contains('is-open')
        && !document.getElementById('materialViewer').classList.contains('is-open')
        && !document.getElementById('messagePopup').classList.contains('is-open')
    ) {
        document.body.style.overflow = '';
    }
}

function submitFromDetails() {
    if (!assignmentDetailsCurrent || assignmentDetailsCurrent.closed) {
        return;
    }

    const assignment = assignmentDetailsCurrent;

    closeAssignmentDetails();

    setTimeout(function() {
        openAssignmentModal(assignment);
    }, 120);
}

document.getElementById('assignmentDetailsModal').addEventListener(
    'click',
    function(event) {
        if (event.target === this) {
            closeAssignmentDetails();
        }
    }
);


function openAssignmentModal(assignment) {
    currentAssignment = assignment;

    const modal = document.getElementById('assignmentSubmitModal');
    const form = document.getElementById('studentAssignmentForm');
    const status = document.getElementById('assignmentSubmitStatus');
    const warning = document.getElementById('assignmentWarning');
    const button = document.getElementById('assignmentSubmitButton');

    form.reset();

    status.className = 'assignment-submit-status';
    status.textContent = '';

    warning.className = 'assignment-warning';
    warning.textContent = '';

    document.getElementById('assignmentModalTitle').textContent =
        assignment.closed
            ? 'Assignment Details'
            : 'Submit Assignment';

    document.getElementById('assignmentModalSubtitle').textContent =
        assignment.title || 'Assignment';

    document.getElementById('assignmentPoints').textContent =
        assignment.points + ' point(s)';

    document.getElementById('assignmentDue').textContent =
        assignment.due || '-';

    document.getElementById('assignmentSubmissionType').textContent =
        humanSubmissionType(assignment.submission_type);

    document.getElementById('assignmentAttempts').textContent =
        assignment.my_attempts + ' / ' + assignment.max_attempts;

    document.getElementById('assignmentInstructions').textContent =
        assignment.instructions || 'No instructions provided.';

    document.getElementById('submitAssignmentId').value =
        assignment.id;

    document.getElementById('submitModuleId').value =
        assignment.module_id;

    document.getElementById('submitTheModule').value =
        assignment.module_id;

    document.getElementById('submitType').value =
        assignment.submission_type;

    renderAssignmentResources(assignment.resources || []);
    configureAssignmentFields(assignment.submission_type);

    if (assignment.late && assignment.allow_late && !assignment.closed) {
        warning.textContent =
            'The due date has passed, but the instructor allows late submissions.';

        warning.classList.add('show');
    }

    if (assignment.my_attempts >= assignment.max_attempts) {
        warning.textContent =
            'You have reached the maximum number of submission attempts for this assignment.';

        warning.classList.add('show');
    } else if (assignment.late && !assignment.allow_late) {
        warning.textContent =
            'The submission deadline has passed and late submissions are not allowed.';

        warning.classList.add('show');
    }

    button.style.display = assignment.closed ? 'none' : 'inline-flex';

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function renderAssignmentResources(resources) {
    const wrap = document.getElementById('assignmentResourcesWrap');
    const list = document.getElementById('assignmentResources');

    list.innerHTML = '';

    if (!Array.isArray(resources) || resources.length === 0) {
        wrap.style.display = 'none';
        return;
    }

    resources.forEach(resource => {
        const row = document.createElement('div');
        row.className = 'assignment-resource';

        const left = document.createElement('div');
        left.className = 'assignment-resource-name';

        const title = document.createElement('div');
        title.textContent = resource.title || 'Assignment Resource';
        left.appendChild(title);

        const type = document.createElement('span');
        type.className = 'assignment-resource-type';
        type.textContent =
            String(resource.type || 'file')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, char => char.toUpperCase());

        left.appendChild(type);
        row.appendChild(left);

        if (resource.href) {
            const link = document.createElement('a');
            link.className = 'sc-btn sc-soft';
            link.href = resource.href;
            link.target = '_blank';
            link.rel = 'noopener';
            link.innerHTML =
                '<i class="fas fa-eye"></i> Open';

            row.appendChild(link);
        } else if (resource.text) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sc-btn sc-soft';
            button.innerHTML =
                '<i class="fas fa-eye"></i> Read';

            button.addEventListener('click', () => {
                showPopup(resource.text, resource.title || 'Resource');
            });

            row.appendChild(button);
        }

        list.appendChild(row);
    });

    wrap.style.display = '';
}

function configureAssignmentFields(type) {
    const fileField = document.getElementById('fileSubmissionField');
    const textField = document.getElementById('textSubmissionField');
    const linkField = document.getElementById('linkSubmissionField');

    const file = document.getElementById('assignmentFile');
    const text = document.getElementById('assignmentText');
    const link = document.getElementById('assignmentLink');

    fileField.style.display = 'none';
    textField.style.display = 'none';
    linkField.style.display = 'none';

    file.required = false;
    text.required = false;
    link.required = false;

    if (type === 'file') {
        fileField.style.display = '';
        file.required = true;
    } else if (type === 'text') {
        textField.style.display = '';
        text.required = true;
    } else if (type === 'link') {
        linkField.style.display = '';
        link.required = true;
    } else if (type === 'file_text') {
        fileField.style.display = '';
        textField.style.display = '';
    } else {
        fileField.style.display = '';
        textField.style.display = '';
        linkField.style.display = '';
    }
}

function closeAssignmentModal() {
    const modal = document.getElementById('assignmentSubmitModal');

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    document.getElementById('studentAssignmentForm').reset();
    currentAssignment = null;

    if (
        !document.getElementById('materialViewer').classList.contains('is-open')
        && !document.getElementById('messagePopup').classList.contains('is-open')
    ) {
        document.body.style.overflow = '';
    }
}

function assignmentSubmissionIsValid(type) {
    const file = document.getElementById('assignmentFile');
    const text = document.getElementById('assignmentText').value.trim();
    const link = document.getElementById('assignmentLink').value.trim();

    const hasFile = file.files && file.files.length > 0;
    const hasText = text !== '';
    const hasLink = link !== '';

    if (type === 'file') {
        return hasFile;
    }

    if (type === 'text') {
        return hasText;
    }

    if (type === 'link') {
        return hasLink;
    }

    if (type === 'file_text') {
        return hasFile || hasText;
    }

    return hasFile || hasText || hasLink;
}

document.getElementById('studentAssignmentForm').addEventListener(
    'submit',
    async function(event) {
        event.preventDefault();

        if (!currentAssignment || currentAssignment.closed) {
            return;
        }

        const type = currentAssignment.submission_type;

        if (!assignmentSubmissionIsValid(type)) {
            const status = document.getElementById('assignmentSubmitStatus');

            status.textContent =
                type === 'file_text'
                    ? 'Please upload a file or enter a text response.'
                    : type === 'any'
                        ? 'Please provide a file, text response, or link.'
                        : 'Please provide the required assignment submission.';

            status.className =
                'assignment-submit-status error show';

            return;
        }

        const form = event.currentTarget;
        const button = document.getElementById('assignmentSubmitButton');
        const status = document.getElementById('assignmentSubmitStatus');

        const oldHtml = button.innerHTML;

        button.disabled = true;
        button.innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        status.className = 'assignment-submit-status';
        status.textContent = '';

        try {
            const formData = new FormData(form);

            /*
             * The visible file field is submission_file.
             * submitassignment.php supports the new field directly.
             */
            const response = await fetch(
                'scripts/submitassignment.php',
                {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const raw = await response.text();

            let result;

            try {
                result = JSON.parse(raw);
            } catch (error) {
                throw new Error(
                    raw
                        ? 'Server returned an invalid response: ' +
                          raw.substring(0, 180)
                        : 'Server returned an empty response.'
                );
            }

            if (!result.success) {
                throw new Error(
                    result.message || 'Unable to submit assignment.'
                );
            }

            status.textContent =
                result.message || 'Assignment submitted successfully.';

            status.className =
                'assignment-submit-status success show';

            setTimeout(function() {
                closeAssignmentModal();

                showPopup(
                    result.message || 'Assignment submitted successfully.',
                    'Assignment Submitted'
                );

                setTimeout(function() {
                    window.location.reload();
                }, 900);
            }, 700);

        } catch (error) {
            status.textContent =
                error.message || 'Unable to submit assignment.';

            status.className =
                'assignment-submit-status error show';

            button.disabled = false;
            button.innerHTML = oldHtml;
        }
    }
);

document.getElementById('assignmentSubmitModal').addEventListener(
    'click',
    function(event) {
        if (event.target === this) {
            closeAssignmentModal();
        }
    }
);


function showModule(index, button, moduleId) {
    document.querySelectorAll('.module-panel').forEach(panel => {
        panel.classList.remove('active');
    });

    document.querySelectorAll('.module-tab').forEach(tab => {
        tab.classList.remove('active');
        tab.setAttribute('aria-selected', 'false');
    });

    const panel = document.getElementById('module-' + index);

    if (panel) {
        panel.classList.add('active');

        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    if (button) {
        button.classList.add('active');
        button.setAttribute('aria-selected', 'true');
    }

    if (moduleId) {
        const url = new URL(window.location.href);
        url.searchParams.set('module_id', moduleId);
        history.replaceState(null, '', url);
    }
}

function openStudentModuleFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const moduleId = params.get('module_id');

    if (!moduleId) {
        return;
    }

    const button = document.querySelector(
        '.module-tab[data-module-id="' + CSS.escape(moduleId) + '"]'
    );

    if (button) {
        showModule(
            Number(button.dataset.moduleIndex),
            button,
            Number(button.dataset.moduleId)
        );
    }
}

document.addEventListener('DOMContentLoaded', openStudentModuleFromUrl);

function showKind(index, kind, button) {
    const panel = button.closest('.module-panel');

    panel.querySelectorAll('.kind-panel').forEach(item => {
        item.classList.remove('active');
    });

    panel.querySelectorAll('.kind-tab').forEach(item => {
        item.classList.remove('active');
    });

    document
        .getElementById('kind-' + index + '-' + kind)
        ?.classList.add('active');

    button.classList.add('active');
}

async function saveMaterialProgress(
    materialId,
    action,
    progress = 0,
    seconds = 0
) {
    const formData = new FormData();

    formData.append('material_id', String(materialId));
    formData.append('action', action);
    formData.append('progress', String(progress));
    formData.append('seconds', String(seconds));

    try {
        const response = await fetch(
            'scripts/update_material_progress.php',
            {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        );

        const raw = await response.text();

        let data;

        try {
            data = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        if (data.success) {
            updateMaterialCard(
                materialId,
                Number(data.progress || progress),
                String(data.status || 'started')
            );
        }

        return data;
    } catch (error) {
        return null;
    }
}

function updateMaterialCard(materialId, progress, status) {
    progress = Math.max(0, Math.min(100, Number(progress || 0)));

    const pct = document.getElementById('pct-' + materialId);
    const bar = document.getElementById('bar-' + materialId);
    const badge = document.getElementById('status-' + materialId);
    const completeBtn = document.getElementById(
        'complete-btn-' + materialId
    );

    if (pct) {
        pct.textContent = Math.round(progress) + '%';
    }

    if (bar) {
        bar.style.width = progress + '%';
    }

    if (badge) {
        badge.className =
            'status-badge status-' + status;

        badge.textContent =
            status === 'completed'
                ? 'Completed'
                : status === 'started'
                    ? 'In Progress'
                    : 'Not Started';
    }

    if (status === 'completed' && completeBtn) {
        completeBtn.remove();
    }
}

function updateViewerProgress(progress) {
    progress = Math.max(0, Math.min(100, Number(progress || 0)));

    document.getElementById('viewerPct').textContent =
        Math.round(progress) + '%';

    document.getElementById('viewerBar').style.width =
        progress + '%';

    document.getElementById('viewerStatus').textContent =
        progress >= 100 ? 'Completed' : 'In Progress';
}

async function openMaterial(material) {
    currentMaterial = material;
    lastProgressSent = Number(material.progress || 0);

    document.getElementById('viewerTitle').textContent =
        material.title || 'Learning Material';

    document.getElementById('viewerBody').innerHTML =
        '<div style="color:#fff;font-size:.95rem;">Loading...</div>';

    document.getElementById('pdfNav').style.display = 'none';

    updateViewerProgress(lastProgressSent);

    document
        .getElementById('materialViewer')
        .classList.add('is-open');

    document.body.style.overflow = 'hidden';

    await saveMaterialProgress(
        material.id,
        'open',
        Math.max(1, lastProgressSent)
    );

    startHeartbeat();

    const type = String(material.type || 'document').toLowerCase();

    if (type === 'link') {
        document.getElementById('viewerBody').innerHTML =
            '<div class="viewer-reading">' +
            '<h3>External Learning Resource</h3>' +
            '<p>This resource opens in a new browser tab. ' +
            'After reviewing it, return here and mark the material as complete.</p>' +
            '<p><a class="sc-btn sc-primary" target="_blank" rel="noopener" href="' +
            escapeHtml(material.url) +
            '"><i class="fas fa-up-right-from-square"></i> Open Resource</a></p>' +
            '</div>';

        return;
    }

    if (type === 'text') {
        const reading = document.createElement('div');

        reading.className = 'viewer-reading';
        reading.innerHTML =
            '<h3>' + escapeHtml(material.title || 'Reading') + '</h3>' +
            '<div>' +
            escapeHtml(material.content || 'No reading content available.')
                .replace(/\n/g, '<br>') +
            '</div>';

        const body = document.getElementById('viewerBody');

        body.innerHTML = '';
        body.appendChild(reading);

        body.addEventListener(
            'scroll',
            handleReadingScroll,
            { passive: true }
        );

        return;
    }

    if (type === 'video') {
        const video = document.createElement('video');

        video.controls = true;
        video.playsInline = true;
        video.src =
            'scripts/material_file.php?id=' +
            encodeURIComponent(material.id) +
            '&mode=inline';

        video.addEventListener('timeupdate', function () {
            if (!video.duration) {
                return;
            }

            const progress =
                (video.currentTime / video.duration) * 100;

            sendProgressThrottled(progress);
        });

        video.addEventListener('ended', function () {
            sendProgressThrottled(100, true);
        });

        const body = document.getElementById('viewerBody');

        body.innerHTML = '';
        body.appendChild(video);

        return;
    }

    if (type === 'audio') {
        const audio = document.createElement('audio');

        audio.controls = true;
        audio.src =
            'scripts/material_file.php?id=' +
            encodeURIComponent(material.id) +
            '&mode=inline';

        audio.addEventListener('timeupdate', function () {
            if (!audio.duration) {
                return;
            }

            const progress =
                (audio.currentTime / audio.duration) * 100;

            sendProgressThrottled(progress);
        });

        audio.addEventListener('ended', function () {
            sendProgressThrottled(100, true);
        });

        const body = document.getElementById('viewerBody');

        body.innerHTML = '';
        body.appendChild(audio);

        return;
    }

    if (type === 'image') {
        const image = document.createElement('img');

        image.src =
            'scripts/material_file.php?id=' +
            encodeURIComponent(material.id) +
            '&mode=inline';

        image.alt = material.title || 'Learning material';

        const body = document.getElementById('viewerBody');

        body.innerHTML = '';
        body.appendChild(image);

        return;
    }

    if (
        material.ext === 'pdf'
        && window.pdfjsLib
    ) {
        loadPdfMaterial(material.id);
        return;
    }

    // Office/document preview using the server preview endpoint already added.
    const iframe = document.createElement('iframe');

    iframe.src =
        'scripts/preview_material.php?id=' +
        encodeURIComponent(material.id);

    iframe.title = material.title || 'Document preview';

    const body = document.getElementById('viewerBody');

    body.innerHTML = '';
    body.appendChild(iframe);
}

async function loadPdfMaterial(materialId) {
    const url =
        'scripts/material_file.php?id=' +
        encodeURIComponent(materialId) +
        '&mode=inline';

    try {
        currentPdf = await pdfjsLib.getDocument(url).promise;
        currentPdfPage = 1;

        document.getElementById('pdfNav').style.display = 'flex';

        renderPdfPage(1);
    } catch (error) {
        document.getElementById('viewerBody').innerHTML =
            '<div class="viewer-reading">' +
            '<h3>Unable to display PDF</h3>' +
            '<p>Please try downloading or reopening the material.</p>' +
            '</div>';
    }
}

async function renderPdfPage(pageNumber) {
    if (!currentPdf || pdfRendering) {
        return;
    }

    pdfRendering = true;

    try {
        const page = await currentPdf.getPage(pageNumber);

        const body = document.getElementById('viewerBody');
        const natural = page.getViewport({ scale: 1 });

        const availableWidth =
            Math.max(300, body.clientWidth - 32);

        const scale = availableWidth / natural.width;
        const viewport = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        canvas.width = viewport.width;
        canvas.height = viewport.height;

        body.innerHTML = '';
        body.appendChild(canvas);

        await page.render({
            canvasContext: context,
            viewport
        }).promise;

        currentPdfPage = pageNumber;

        document.getElementById('pageInfo').textContent =
            'Page ' + currentPdfPage +
            ' / ' + currentPdf.numPages;

        const progress =
            (currentPdfPage / currentPdf.numPages) * 100;

        sendProgressThrottled(progress, currentPdfPage === currentPdf.numPages);
    } finally {
        pdfRendering = false;
    }
}

document.getElementById('prevPage').addEventListener('click', function () {
    if (currentPdf && currentPdfPage > 1) {
        renderPdfPage(currentPdfPage - 1);
    }
});

document.getElementById('nextPage').addEventListener('click', function () {
    if (currentPdf && currentPdfPage < currentPdf.numPages) {
        renderPdfPage(currentPdfPage + 1);
    }
});

function handleReadingScroll() {
    if (!currentMaterial) {
        return;
    }

    const body = document.getElementById('viewerBody');

    const maxScroll =
        body.scrollHeight - body.clientHeight;

    let progress = 100;

    if (maxScroll > 0) {
        progress =
            (body.scrollTop / maxScroll) * 100;
    }

    sendProgressThrottled(progress, progress >= 99);
}

function sendProgressThrottled(progress, force = false) {
    progress = Math.max(1, Math.min(100, progress));

    if (
        !force
        && progress < lastProgressSent + 5
    ) {
        return;
    }

    lastProgressSent = progress;
    updateViewerProgress(progress);

    if (currentMaterial) {
        saveMaterialProgress(
            currentMaterial.id,
            'progress',
            progress
        );
    }
}

function startHeartbeat() {
    stopHeartbeat();

    heartbeatTimer = setInterval(function () {
        if (currentMaterial) {
            saveMaterialProgress(
                currentMaterial.id,
                'heartbeat',
                lastProgressSent,
                15
            );
        }
    }, 15000);
}

function stopHeartbeat() {
    if (heartbeatTimer) {
        clearInterval(heartbeatTimer);
        heartbeatTimer = null;
    }
}

async function markMaterialComplete(materialId) {
    const result = await saveMaterialProgress(
        materialId,
        'complete',
        100
    );

    if (result && result.success) {
        showPopup(
            'This learning material has been marked as completed.',
            'Completed'
        );
    }
}

async function completeCurrentMaterial() {
    if (!currentMaterial) {
        return;
    }

    await markMaterialComplete(currentMaterial.id);
    lastProgressSent = 100;
    updateViewerProgress(100);
}

function recordDownload(materialId) {
    saveMaterialProgress(
        materialId,
        'download',
        0
    );
}

function closeViewer() {
    stopHeartbeat();

    const overlay = document.getElementById('materialViewer');

    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');

    document.getElementById('viewerBody').innerHTML = '';
    document.getElementById('pdfNav').style.display = 'none';

    currentMaterial = null;
    currentPdf = null;
    currentPdfPage = 1;

    document.body.style.overflow = '';
}

function showPopup(message, title = 'Notice') {
    document.getElementById('popupTitle').textContent = title;
    document.getElementById('popupMessage').textContent = message;

    document
        .getElementById('messagePopup')
        .classList.add('is-open');
}

function closePopup() {
    document
        .getElementById('messagePopup')
        .classList.remove('is-open');
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

document.getElementById('materialViewer').addEventListener('click', function (event) {
    if (event.target === this) {
        closeViewer();
    }
});

document.getElementById('messagePopup').addEventListener('click', function (event) {
    if (event.target === this) {
        closePopup();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
        return;
    }

    if (document.getElementById('assignmentDetailsModal').classList.contains('is-open')) {
        closeAssignmentDetails();
        return;
    }

    if (document.getElementById('assignmentSubmitModal').classList.contains('is-open')) {
        closeAssignmentModal();
        return;
    }

    if (document.getElementById('messagePopup').classList.contains('is-open')) {
        closePopup();
        return;
    }

    if (document.getElementById('materialViewer').classList.contains('is-open')) {
        closeViewer();
    }
});

window.addEventListener('beforeunload', function () {
    stopHeartbeat();
});
</script>
