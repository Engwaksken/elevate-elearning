<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'scripts/connector.php';
date_default_timezone_set('Africa/Nairobi');

$systemname = $systemname ?? 'Elevate Her 360';

function sub_e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sub_safe_date(mixed $v, string $fmt = 'd M Y, h:i a'): string
{
    if (!$v || strtotime((string)$v) === false) return '-';
    return date($fmt, strtotime((string)$v));
}

function sub_page_url(int $page): string
{
    $q = $_GET;
    $q['page_no'] = $page;
    return '?' . http_build_query($q);
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo '<div class="app-alert app-alert-danger">Database connection not available.</div>';
    return;
}

$userId = trim((string)($_SESSION[$systemname.'leido'] ?? ''));
$role   = trim((string)($_SESSION[$systemname.'letype'] ?? ''));

if ($userId === '' || !in_array($role, ['xybrtw', 'xtfbry'], true)) {
    echo '<div class="app-alert app-alert-danger">Access denied.</div>';
    return;
}

/* -------------------------------------------------------------
   Filters
------------------------------------------------------------- */
$intakeId    = max(0, (int)($_GET['intake_id'] ?? 0));
$courseId    = max(0, (int)($_GET['course_id'] ?? 0));
$unitId      = max(0, (int)($_GET['unit_id'] ?? 0));
$classTypeId = max(0, (int)($_GET['class_type_id'] ?? 0));
$status      = trim((string)($_GET['status'] ?? 'all'));
$search      = trim((string)($_GET['search'] ?? ''));

if (!in_array($status, ['all', 'pending', 'submitted', 'graded'], true)) {
    $status = 'all';
}

$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10,25,50,100], true)) $perPage = 25;
$page = max(1, (int)($_GET['page_no'] ?? 1));

/* -------------------------------------------------------------
   Filter options
------------------------------------------------------------- */
$intakes = [];
$r = $conn->query("SELECT id,name FROM intakes ORDER BY name ASC");
if ($r) while ($x = $r->fetch_assoc()) $intakes[] = $x;

$courses = [];
if ($role === 'xtfbry') {
    $s = $conn->prepare("
        SELECT DISTINCT c.id,c.name
        FROM course_registration_lecturers crl
        INNER JOIN courses c ON c.id=crl.course_id
        WHERE crl.user_id=?
        ORDER BY c.name ASC
    ");
    if ($s) {
        $s->bind_param('s', $userId);
        $s->execute();
        $rr = $s->get_result();
        while ($rr && $x = $rr->fetch_assoc()) $courses[] = $x;
        $s->close();
    }
} else {
    $r = $conn->query("SELECT id,name FROM courses ORDER BY name ASC");
    if ($r) while ($x = $r->fetch_assoc()) $courses[] = $x;
}

$units = [];
if ($courseId > 0) {
    $s = $conn->prepare("SELECT id,name FROM course_unit WHERE course_id=? ORDER BY order_number,id");
    if ($s) {
        $s->bind_param('i', $courseId);
        $s->execute();
        $rr = $s->get_result();
        while ($rr && $x = $rr->fetch_assoc()) $units[] = $x;
        $s->close();
    }
}

$classTypes = [];
$r = $conn->query("SELECT id,name FROM class_type ORDER BY name ASC");
if ($r) while ($x = $r->fetch_assoc()) $classTypes[] = $x;

/* -------------------------------------------------------------
   Base query:
   assignments X registered students, latest submission if any.
------------------------------------------------------------- */
$where = [
    "COALESCE(a.status,'active')='active'",
    "COALESCE(a.dummy,'no')='no'"
];
$params = [];
$types = '';

if ($role === 'xtfbry') {
    $where[] = "EXISTS(
        SELECT 1 FROM course_registration_lecturers crl
        WHERE crl.user_id=? AND crl.course_id=cu.course_id
    )";
    $params[] = $userId;
    $types .= 's';
}

if ($intakeId > 0) {
    $where[] = 'a.intake=?';
    $params[] = $intakeId;
    $types .= 'i';
}

if ($courseId > 0) {
    $where[] = 'cu.course_id=?';
    $params[] = $courseId;
    $types .= 'i';
}

if ($unitId > 0) {
    $where[] = 'a.course_unit=?';
    $params[] = $unitId;
    $types .= 'i';
}

if ($classTypeId > 0) {
    $where[] = 'u.class_type_id=?';
    $params[] = $classTypeId;
    $types .= 'i';
}

if ($search !== '') {
    $like = '%'.$search.'%';
    $where[] = "(CONCAT_WS(' ',u.fname,u.lname) LIKE ? OR u.email LIKE ? OR a.title LIKE ? OR c.name LIKE ? OR cu.name LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($status === 'pending') {
    $where[] = 'hi.id IS NULL';
} elseif ($status === 'submitted') {
    $where[] = 'hi.id IS NOT NULL AND (hi.finalmarks IS NULL OR hi.finalmarks="")';
} elseif ($status === 'graded') {
    $where[] = 'hi.id IS NOT NULL AND hi.finalmarks IS NOT NULL AND hi.finalmarks!=""';
}

$whereSql = implode(' AND ', $where);

$fromSql = "
    FROM assignments a
    INNER JOIN course_unit cu ON cu.id=a.course_unit
    INNER JOIN courses c ON c.id=cu.course_id
    INNER JOIN course_registration cr
        ON cr.course_id=cu.course_id
       AND cr.intake=a.intake
    INNER JOIN users u ON u.unique_id=cr.user_id
    LEFT JOIN class_type ct ON ct.id=u.class_type_id
    LEFT JOIN handed_in hi
        ON hi.id=(
            SELECT MAX(hi2.id)
            FROM handed_in hi2
            WHERE hi2.assignment_id=a.id
              AND hi2.user_id=cr.user_id
        )
    WHERE {$whereSql}
";

/* Count */
$s = $conn->prepare("SELECT COUNT(*) total {$fromSql}");
if (!$s) {
    echo '<div class="app-alert app-alert-danger">Unable to load submissions: '.sub_e($conn->error).'</div>';
    return;
}
if ($types !== '') $s->bind_param($types, ...$params);
$s->execute();
$totalRows = (int)($s->get_result()->fetch_assoc()['total'] ?? 0);
$s->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

/* Stats with current filters */
$statsSql = "
    SELECT
        COUNT(*) total,
        SUM(CASE WHEN hi.id IS NULL THEN 1 ELSE 0 END) pending,
        SUM(CASE WHEN hi.id IS NOT NULL AND (hi.finalmarks IS NULL OR hi.finalmarks='') THEN 1 ELSE 0 END) submitted,
        SUM(CASE WHEN hi.id IS NOT NULL AND hi.finalmarks IS NOT NULL AND hi.finalmarks!='' THEN 1 ELSE 0 END) graded
    {$fromSql}
";
$s = $conn->prepare($statsSql);
$stats = ['total'=>$totalRows,'pending'=>0,'submitted'=>0,'graded'=>0];
if ($s) {
    if ($types !== '') $s->bind_param($types, ...$params);
    $s->execute();
    $stats = array_merge($stats, $s->get_result()->fetch_assoc() ?: []);
    $s->close();
}

/* Data */
$dataSql = "
    SELECT
        a.id assignment_id,
        a.title assignment_title,
        a.marks,
        a.date_due,
        c.name course_name,
        cu.id unit_id,
        cu.name unit_name,
        u.unique_id student_id,
        u.fname,
        u.lname,
        u.email,
        COALESCE(ct.name,'N/A') class_type_name,
        hi.id submission_id,
        hi.date_handed_in,
        hi.finalmarks,
        hi.marks_deducted,
        hi.feedback,
        hi.file_uploaded
    {$fromSql}
    ORDER BY a.date_due DESC, a.id DESC, u.fname, u.lname
    LIMIT ? OFFSET ?
";
$dataParams = [...$params, $perPage, $offset];
$dataTypes = $types.'ii';

$s = $conn->prepare($dataSql);
if (!$s) {
    echo '<div class="app-alert app-alert-danger">Unable to load rows: '.sub_e($conn->error).'</div>';
    return;
}
$s->bind_param($dataTypes, ...$dataParams);
$s->execute();
$rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
<style>
.sub-page{font-family:'DM Sans',sans-serif}
.sub-page .ctrl-label,.sub-page .ctrl-select,.sub-page input{font-size:.9rem}
.sub-page .app-table th,.sub-page .app-table td{font-size:.88rem;padding:10px}
.filters-grid{display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:10px;align-items:end}
.search-field{grid-column:span 2}
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:12px;font-size:.8rem;font-weight:700}
.s-pending{background:var(--red-bg);color:var(--red)}
.s-submitted{background:rgba(139,117,54,.12);color:var(--gold)}
.s-graded{background:var(--green-bg);color:var(--green)}
.row-actions{display:flex;gap:6px;flex-wrap:wrap}
.ia-btn{width:32px;height:32px;border:0;border-radius:7px;display:grid;place-items:center;cursor:pointer;text-decoration:none}
.ia-view{background:var(--maroon-dim);color:var(--maroon)}
.ia-dl{background:var(--green-bg);color:var(--green)}
.pagination{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
.page-link{min-width:34px;height:34px;padding:0 9px;border:1px solid var(--border);border-radius:7px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:var(--text);font-size:.85rem}
.page-link.active{background:var(--maroon);color:#fff;border-color:var(--maroon)}
.page-link.disabled{opacity:.4;pointer-events:none}
@media(max-width:1100px){.filters-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.filters-grid{grid-template-columns:1fr}.search-field{grid-column:auto}}
</style>
</head>
<body>
<main class="page page-wide sub-page">

<div class="page-heading">
  <div>
    <h2>All <em>Assignments</em></h2>
    <div class="heading-meta">
      <span class="meta-pill"><i class="fa fa-house"></i><a href="?" style="text-decoration:none;color:inherit">Home</a></span>
      <span class="meta-pill"><i class="fa fa-pen-to-square"></i> Submissions</span>
    </div>
  </div>
  <span class="count-pill"><?php echo $totalRows; ?> records</span>
</div>

<form method="GET" class="controls-bar filters-grid" style="margin-bottom:22px">
  <input type="hidden" name="view" value="submissions">

  <div class="ctrl-field">
    <label class="ctrl-label">Intake</label>
    <select class="ctrl-select" name="intake_id">
      <option value="0">All Intakes</option>
      <?php foreach($intakes as $i): ?><option value="<?php echo (int)$i['id']; ?>" <?php echo $intakeId===(int)$i['id']?'selected':''; ?>><?php echo sub_e($i['name']); ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="ctrl-field">
    <label class="ctrl-label">Course</label>
    <select class="ctrl-select" name="course_id" onchange="this.form.submit()">
      <option value="0">All Courses</option>
      <?php foreach($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $courseId===(int)$c['id']?'selected':''; ?>><?php echo sub_e($c['name']); ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="ctrl-field">
    <label class="ctrl-label">Unit</label>
    <select class="ctrl-select" name="unit_id">
      <option value="0">All Units</option>
      <?php foreach($units as $u): ?><option value="<?php echo (int)$u['id']; ?>" <?php echo $unitId===(int)$u['id']?'selected':''; ?>><?php echo sub_e($u['name']); ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="ctrl-field">
    <label class="ctrl-label">Class Type</label>
    <select class="ctrl-select" name="class_type_id">
      <option value="0">All Class Types</option>
      <?php foreach($classTypes as $ct): ?><option value="<?php echo (int)$ct['id']; ?>" <?php echo $classTypeId===(int)$ct['id']?'selected':''; ?>><?php echo sub_e($ct['name']); ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="ctrl-field">
    <label class="ctrl-label">Status</label>
    <select class="ctrl-select" name="status">
      <option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
      <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
      <option value="submitted" <?php echo $status==='submitted'?'selected':''; ?>>Submitted</option>
      <option value="graded" <?php echo $status==='graded'?'selected':''; ?>>Graded</option>
    </select>
  </div>

  <div class="ctrl-field">
    <label class="ctrl-label">Rows</label>
    <select class="ctrl-select" name="per_page">
      <?php foreach([10,25,50,100] as $n): ?><option value="<?php echo $n; ?>" <?php echo $perPage===$n?'selected':''; ?>><?php echo $n; ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="ctrl-field search-field">
    <label class="ctrl-label">Search</label>
    <input class="ctrl-select" type="text" name="search" value="<?php echo sub_e($search); ?>" placeholder="Student, email, assignment, course...">
  </div>

  <div style="display:flex;gap:8px">
    <button class="btn-export" type="submit"><i class="fas fa-filter"></i> Apply</button>
    <a class="btn-export" href="?view=submissions"><i class="fas fa-rotate-left"></i> Reset</a>
  </div>
</form>

<div class="stats" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon total"><i class="fas fa-list"></i></div><div><div class="sv"><?php echo (int)$stats['total']; ?></div><div class="sl">Total</div></div></div>
  <div class="stat-card"><div class="stat-icon cancel"><i class="fas fa-clock"></i></div><div><div class="sv red"><?php echo (int)$stats['pending']; ?></div><div class="sl">Pending</div></div></div>
  <div class="stat-card"><div class="stat-icon prog"><i class="fas fa-paper-plane"></i></div><div><div class="sv gold"><?php echo (int)$stats['submitted']; ?></div><div class="sl">Submitted</div></div></div>
  <div class="stat-card"><div class="stat-icon done"><i class="fas fa-check-circle"></i></div><div><div class="sv green"><?php echo (int)$stats['graded']; ?></div><div class="sl">Graded</div></div></div>
</div>

<div class="tbl-card">
  <div class="tbl-toolbar">
    <span class="tbl-title"><i class="fas fa-table-list"></i> Assignment Submissions</span>
    <span style="font-size:.82rem;color:var(--muted)">Showing <?php echo $totalRows?($offset+1):0; ?>-<?php echo min($offset+$perPage,$totalRows); ?> of <?php echo $totalRows; ?></span>
  </div>

  <div class="tbl-scroll">
    <table class="app-table">
      <thead><tr>
        <th>#</th><th>Student</th><th>Class</th><th>Course</th><th>Unit</th><th>Assignment</th><th>Due</th><th>Submitted</th><th>Status</th><th>Marks</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="11" style="text-align:center;padding:30px;color:var(--muted)">No assignments match the selected filters.</td></tr>
      <?php endif; ?>
      <?php foreach($rows as $idx=>$row):
        $graded = $row['submission_id'] && $row['finalmarks'] !== null && $row['finalmarks'] !== '';
        $submitted = !empty($row['submission_id']);
        $st = $graded ? 'graded' : ($submitted ? 'submitted' : 'pending');
        $label = ucfirst($st);
      ?>
        <tr>
          <td><?php echo $offset+$idx+1; ?></td>
          <td><strong><?php echo sub_e(trim(($row['fname']??'').' '.($row['lname']??''))); ?></strong><div style="font-size:.78rem;color:var(--muted)"><?php echo sub_e($row['email']); ?></div></td>
          <td><?php echo sub_e($row['class_type_name']); ?></td>
          <td><?php echo sub_e($row['course_name']); ?></td>
          <td><?php echo sub_e($row['unit_name']); ?></td>
          <td><strong><?php echo sub_e($row['assignment_title']); ?></strong></td>
          <td><?php echo sub_e(sub_safe_date($row['date_due'])); ?></td>
          <td><?php echo $submitted?sub_e(sub_safe_date($row['date_handed_in'])):'-'; ?></td>
          <td><span class="status-badge s-<?php echo $st; ?>"><?php echo $label; ?></span></td>
          <td><?php echo $graded?sub_e($row['finalmarks']).'/'.sub_e($row['marks']):'-'; ?></td>
          <td>
            <div class="row-actions">
              <?php if($submitted): ?>
                <a class="ia-btn ia-view" title="View" href="?view=subby&hjadvhagsdjhsagsdhg=<?php echo (int)$row['unit_id']; ?>&piouglv=<?php echo (int)$row['assignment_id']; ?>"><i class="fas fa-eye"></i></a>
                <?php if(!empty($row['file_uploaded'])): ?><a class="ia-btn ia-dl" title="Download" href="?view=download_file&submission_id=<?php echo (int)$row['submission_id']; ?>"><i class="fas fa-download"></i></a><?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="tbl-footer">
    <div class="legend">
      <span class="legend-item"><span class="legend-dot legend-dot-green"></span> Graded</span>
      <span class="legend-item"><span class="legend-dot legend-dot-gold"></span> Submitted</span>
      <span class="legend-item"><span class="legend-dot legend-dot-muted"></span> Pending</span>
    </div>
    <div class="pagination">
      <a class="page-link <?php echo $page<=1?'disabled':''; ?>" href="<?php echo sub_e(sub_page_url(max(1,$page-1))); ?>"><i class="fas fa-chevron-left"></i></a>
      <?php $sp=max(1,$page-2);$ep=min($totalPages,$page+2); for($p=$sp;$p<=$ep;$p++): ?>
        <a class="page-link <?php echo $p===$page?'active':''; ?>" href="<?php echo sub_e(sub_page_url($p)); ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
      <a class="page-link <?php echo $page>=$totalPages?'disabled':''; ?>" href="<?php echo sub_e(sub_page_url(min($totalPages,$page+1))); ?>"><i class="fas fa-chevron-right"></i></a>
    </div>
  </div>
</div>

</main>
</body>
</html>
