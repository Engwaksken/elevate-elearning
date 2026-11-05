<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';
date_default_timezone_set('Africa/Nairobi');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo '<div class="app-alert app-alert-danger">Database connection not available.</div>';
    return;
}

$systemname = $systemname ?? '';

if (!function_exists('h')) {
    function h(mixed $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('safeDate')) {
    function safeDate(?string $v): string {
        return (!$v || strtotime($v) === false) ? '-' : date('d M Y, h:i a', strtotime($v));
    }
}
if (!function_exists('pageUrl')) {
    function pageUrl(int $page): string {
        $q = $_GET;
        $q['page_no'] = $page;
        return '?' . http_build_query($q);
    }
}

$role = trim((string)($_SESSION[$systemname.'letype'] ?? $_SESSION['role'] ?? ''));
$uid  = trim((string)($_SESSION[$systemname.'leido'] ?? $_SESSION['unique_id'] ?? ''));

if ($uid === '' || !in_array($role, ['xybrtw','xtfbry'], true)) {
    echo '<div class="app-alert app-alert-danger">Access denied.</div>';
    return;
}

$materialId = (int)($_GET['material_id'] ?? 0);
if ($materialId <= 0) {
    echo '<div class="app-alert app-alert-danger">Invalid material.</div>';
    return;
}

/* Material */
$stmt = $conn->prepare("
    SELECT tm.*, ct.course_id, ct.course_unit_id, ct.intake_id,
           ct.title topic_title, c.name course_name, cu.name module_name
    FROM topic_materials tm
    JOIN course_topics ct ON ct.id = tm.topic_id
    JOIN courses c ON c.id = ct.course_id
    JOIN course_unit cu ON cu.id = ct.course_unit_id
    WHERE tm.id = ? LIMIT 1
");
if (!$stmt) {
    echo '<div class="app-alert app-alert-danger">Unable to load material: '.h($conn->error).'</div>';
    return;
}
$stmt->bind_param('i', $materialId);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    echo '<div class="app-alert app-alert-danger">Material not found.</div>';
    return;
}

$courseId = (int)$material['course_id'];
$intakeId = (int)($material['intake_id'] ?? 0);

/* Lecturer access */
if ($role === 'xtfbry') {
    $s = $conn->prepare("SELECT 1 FROM course_registration_lecturers WHERE user_id=? AND course_id=? LIMIT 1");
    if (!$s) {
        echo '<div class="app-alert app-alert-danger">Unable to validate lecturer access.</div>';
        return;
    }
    $s->bind_param('si', $uid, $courseId);
    $s->execute();
    $ok = $s->get_result()->num_rows > 0;
    $s->close();
    if (!$ok) {
        echo '<div class="app-alert app-alert-danger">Access denied.</div>';
        return;
    }
}

/* Filters */
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$period = trim((string)($_GET['period'] ?? 'all'));
$from   = trim((string)($_GET['from'] ?? ''));
$to     = trim((string)($_GET['to'] ?? ''));

if (!in_array($status, ['all','not_started','started','completed'], true)) $status = 'all';
if (!in_array($period, ['all','today','week','month','range'], true)) $period = 'all';

$start = null;
$end   = null;
$now   = new DateTimeImmutable('now');

if ($period === 'today') {
    $start = $now->setTime(0,0,0);
    $end   = $now->setTime(23,59,59);
} elseif ($period === 'week') {
    $start = $now->modify('monday this week')->setTime(0,0,0);
    $end   = $now->modify('sunday this week')->setTime(23,59,59);
} elseif ($period === 'month') {
    $start = $now->modify('first day of this month')->setTime(0,0,0);
    $end   = $now->modify('last day of this month')->setTime(23,59,59);
} elseif ($period === 'range') {
    if ($from !== '' && strtotime($from) !== false) $start = (new DateTimeImmutable($from))->setTime(0,0,0);
    if ($to !== '' && strtotime($to) !== false) $end = (new DateTimeImmutable($to))->setTime(23,59,59);
}

/* Pagination */
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10,25,50,100], true)) $perPage = 25;
$page = max(1, (int)($_GET['page_no'] ?? 1));

/* Dynamic WHERE */
$where = ['cr.course_id = ?'];
$params = [$materialId, $courseId];
$types = 'ii';

if ($intakeId > 0) {
    $where[] = 'cr.intake = ?';
    $params[] = $intakeId;
    $types .= 'i';
}

if ($search !== '') {
    $like = '%'.$search.'%';
    $where[] = "(CONCAT_WS(' ',u.fname,u.lname) LIKE ? OR u.fname LIKE ? OR u.lname LIKE ? OR u.unique_id LIKE ?)";
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($status === 'not_started') {
    $where[] = "(mp.id IS NULL OR COALESCE(mp.status,'not_started')='not_started')";
} elseif ($status !== 'all') {
    $where[] = "COALESCE(mp.status,'not_started') = ?";
    $params[] = $status;
    $types .= 's';
}

if ($start) {
    $where[] = 'mp.last_viewed_at >= ?';
    $params[] = $start->format('Y-m-d H:i:s');
    $types .= 's';
}
if ($end) {
    $where[] = 'mp.last_viewed_at <= ?';
    $params[] = $end->format('Y-m-d H:i:s');
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

/* Count */
$countSql = "
    SELECT COUNT(*) total
    FROM course_registration cr
    JOIN users u ON u.unique_id = cr.user_id
    LEFT JOIN topic_material_progress mp
      ON mp.user_id = cr.user_id AND mp.material_id = ?
    WHERE {$whereSql}
";
$s = $conn->prepare($countSql);
if (!$s) {
    echo '<div class="app-alert app-alert-danger">Unable to count engagement records: '.h($conn->error).'</div>';
    return;
}
$s->bind_param($types, ...$params);
$s->execute();
$totalRows = (int)($s->get_result()->fetch_assoc()['total'] ?? 0);
$s->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

/* Data */
$dataSql = "
    SELECT u.unique_id,u.fname,u.lname,
           COALESCE(mp.progress_percentage,0) progress_percentage,
           COALESCE(mp.total_views,0) total_views,
           COALESCE(mp.seconds_spent,0) seconds_spent,
           COALESCE(mp.status,'not_started') progress_status,
           mp.first_opened_at,mp.last_viewed_at,mp.completed_at,mp.downloaded_at,
           COALESCE(mp.total_downloads,0) total_downloads
    FROM course_registration cr
    JOIN users u ON u.unique_id = cr.user_id
    LEFT JOIN topic_material_progress mp
      ON mp.user_id = cr.user_id AND mp.material_id = ?
    WHERE {$whereSql}
    ORDER BY (mp.last_viewed_at IS NULL), mp.last_viewed_at DESC, u.fname, u.lname
    LIMIT ? OFFSET ?
";
$dataParams = [...$params, $perPage, $offset];
$dataTypes = $types.'ii';

$s = $conn->prepare($dataSql);
if (!$s) {
    echo '<div class="app-alert app-alert-danger">Unable to load engagement records: '.h($conn->error).'</div>';
    return;
}
$s->bind_param($dataTypes, ...$dataParams);
$s->execute();
$res = $s->get_result();
$students = [];
while ($res && $r = $res->fetch_assoc()) $students[] = $r;
$s->close();

/* Stats for filtered dataset */
$statsSql = "
    SELECT COUNT(*) total_students,
           SUM(CASE WHEN mp.id IS NOT NULL AND COALESCE(mp.total_views,0)>0 THEN 1 ELSE 0 END) opened_students,
           SUM(CASE WHEN COALESCE(mp.status,'not_started')='completed' THEN 1 ELSE 0 END) completed_students,
           SUM(CASE WHEN COALESCE(mp.status,'not_started')='started' THEN 1 ELSE 0 END) started_students
    FROM course_registration cr
    JOIN users u ON u.unique_id = cr.user_id
    LEFT JOIN topic_material_progress mp
      ON mp.user_id = cr.user_id AND mp.material_id = ?
    WHERE {$whereSql}
";
$s = $conn->prepare($statsSql);
$stats = ['total_students'=>$totalRows,'opened_students'=>0,'completed_students'=>0,'started_students'=>0];
if ($s) {
    $s->bind_param($types, ...$params);
    $s->execute();
    $stats = array_merge($stats, $s->get_result()->fetch_assoc() ?: []);
    $s->close();
}

$total = (int)$stats['total_students'];
$opened = (int)$stats['opened_students'];
$completed = (int)$stats['completed_students'];
$started = (int)$stats['started_students'];
?>

<link rel="stylesheet" href="app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.engage-page{font-size:1rem}
.engage-page .page-heading h2{font-size:1.45rem}
.engage-page .heading-meta,.engage-page .meta-pill{font-size:.92rem}
.quick-periods{display:flex;gap:7px;flex-wrap:wrap;margin:0 0 14px}
.quick-periods a{padding:7px 11px;border:1px solid var(--border);border-radius:18px;background:#fff;color:var(--muted);text-decoration:none;font-size:.86rem;font-weight:700}
.quick-periods a.active{background:var(--maroon);border-color:var(--maroon);color:#fff}
.engagement-filters{display:grid;grid-template-columns:minmax(220px,1.5fr) repeat(3,minmax(150px,.7fr)) auto;gap:10px;align-items:end;padding:14px;margin-bottom:16px;background:var(--surface);border:1px solid var(--border);border-radius:10px}
.ef label{display:block;margin-bottom:5px;font-size:.88rem;font-weight:700}
.ef input,.ef select{width:100%;min-height:40px;padding:8px 10px;border:1px solid var(--border);border-radius:7px;background:#fff;font-size:.9rem}
.range-wrap{display:none;grid-column:1/-1;grid-template-columns:1fr 1fr;gap:10px}
.range-wrap.show{display:grid}
.filter-actions{display:flex;gap:7px;flex-wrap:wrap}
.fbtn{min-height:40px;padding:8px 13px;border:0;border-radius:7px;font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.fbtn.primary{background:var(--maroon);color:#fff}.fbtn.soft{background:var(--surface2);border:1px solid var(--border);color:var(--text)}
.status-pill{display:inline-flex;padding:4px 8px;border-radius:12px;font-size:.82rem;font-weight:700}
.status-not_started{background:#f1f3f5;color:#6b7280}.status-started{background:#fff4d6;color:#9a6700}.status-completed{background:#e8f7ee;color:#198754}
.engage-page .app-table th,.engage-page .app-table td{font-size:.9rem;padding:10px;vertical-align:middle}
.pagination-wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px}
.pagination-pages{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.page-btn{min-width:34px;height:34px;padding:0 9px;border:1px solid var(--border);border-radius:7px;background:#fff;color:var(--text);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;font-size:.86rem;font-weight:700}
.page-btn.active{background:var(--maroon);border-color:var(--maroon);color:#fff}.page-btn.disabled{pointer-events:none;opacity:.4}
.pagination-info{font-size:.86rem;color:var(--muted)}
.empty-filter{text-align:center;padding:28px;color:var(--muted)}
@media(max-width:1100px){.engagement-filters{grid-template-columns:1fr 1fr}}
@media(max-width:650px){.engagement-filters,.range-wrap{grid-template-columns:1fr}}
</style>

<main class="page page-wide engage-page">
  <div class="page-heading">
    <div>
      <h2><i class="fas fa-chart-line"></i> Material Engagement</h2>
      <div class="heading-meta">
        <span class="meta-pill"><?php echo h($material['title']); ?></span>
        <span class="meta-pill"><?php echo h($material['course_name']); ?></span>
        <span class="meta-pill"><?php echo h($material['module_name']); ?></span>
        <span class="meta-pill"><?php echo h($material['topic_title']); ?></span>
      </div>
    </div>
    <button type="button" class="fbtn soft" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
  </div>

  <div class="quick-periods">
    <?php foreach (['all'=>'All Time','today'=>'Today','week'=>'This Week','month'=>'This Month','range'=>'Custom Range'] as $pv=>$pl):
        $q=$_GET; $q['period']=$pv; unset($q['page_no']);
        if($pv!=='range'){unset($q['from'],$q['to']);}
    ?>
      <a class="<?php echo $period===$pv?'active':''; ?>" href="?<?php echo h(http_build_query($q)); ?>"><?php echo h($pl); ?></a>
    <?php endforeach; ?>
  </div>

  <form method="GET" class="engagement-filters" id="engagementFilterForm">
    <input type="hidden" name="view" value="material_engagement">
    <input type="hidden" name="material_id" value="<?php echo $materialId; ?>">

    <div class="ef">
      <label for="search"><i class="fas fa-search"></i> Search Student</label>
      <input id="search" name="search" value="<?php echo h($search); ?>" placeholder="Name or student ID...">
    </div>

    <div class="ef">
      <label for="status"><i class="fas fa-filter"></i> Status</label>
      <select id="status" name="status">
        <option value="all" <?php echo $status==='all'?'selected':''; ?>>All Statuses</option>
        <option value="not_started" <?php echo $status==='not_started'?'selected':''; ?>>Not Started</option>
        <option value="started" <?php echo $status==='started'?'selected':''; ?>>In Progress</option>
        <option value="completed" <?php echo $status==='completed'?'selected':''; ?>>Completed</option>
      </select>
    </div>

    <div class="ef">
      <label for="period"><i class="fas fa-calendar"></i> Period</label>
      <select id="period" name="period" onchange="toggleRange()">
        <option value="all" <?php echo $period==='all'?'selected':''; ?>>All Time</option>
        <option value="today" <?php echo $period==='today'?'selected':''; ?>>Today</option>
        <option value="week" <?php echo $period==='week'?'selected':''; ?>>This Week</option>
        <option value="month" <?php echo $period==='month'?'selected':''; ?>>This Month</option>
        <option value="range" <?php echo $period==='range'?'selected':''; ?>>Custom Range</option>
      </select>
    </div>

    <div class="ef">
      <label for="per_page"><i class="fas fa-list"></i> Rows</label>
      <select id="per_page" name="per_page">
        <?php foreach([10,25,50,100] as $n): ?>
          <option value="<?php echo $n; ?>" <?php echo $perPage===$n?'selected':''; ?>><?php echo $n; ?> per page</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-actions">
      <button class="fbtn primary" type="submit"><i class="fas fa-filter"></i> Apply</button>
      <a class="fbtn soft" href="?view=material_engagement&material_id=<?php echo $materialId; ?>"><i class="fas fa-rotate-left"></i> Reset</a>
    </div>

    <div id="rangeWrap" class="range-wrap <?php echo $period==='range'?'show':''; ?>">
      <div class="ef"><label>From</label><input type="date" name="from" value="<?php echo h($from); ?>"></div>
      <div class="ef"><label>To</label><input type="date" name="to" value="<?php echo h($to); ?>"></div>
    </div>
  </form>

  <div class="stats" style="margin-bottom:18px">
    <div class="stat-card"><div class="stat-icon total"><i class="fas fa-users"></i></div><div><div class="sv"><?php echo $total; ?></div><div class="sl">Students</div></div></div>
    <div class="stat-card"><div class="stat-icon prog"><i class="fas fa-eye"></i></div><div><div class="sv"><?php echo $opened; ?></div><div class="sl">Opened</div></div></div>
    <div class="stat-card"><div class="stat-icon done"><i class="fas fa-check-circle"></i></div><div><div class="sv"><?php echo $completed; ?></div><div class="sl">Completed</div></div></div>
    <div class="stat-card"><div class="stat-icon cancel"><i class="fas fa-hourglass-half"></i></div><div><div class="sv"><?php echo $started; ?></div><div class="sl">In Progress</div></div></div>
  </div>

  <div class="tbl-card">
    <div class="tbl-toolbar">
      <span class="tbl-title"><i class="fas fa-list"></i> Student Engagement <span class="count-pill"><?php echo $totalRows; ?></span></span>
    </div>

    <div class="tbl-scroll">
      <?php if(!$students): ?>
        <div class="empty-filter"><i class="fas fa-search fa-2x"></i><p>No students match the selected filters.</p></div>
      <?php else: ?>
      <table class="app-table">
        <thead>
          <tr>
            <th>#</th><th>Student</th><th>Progress</th><th>Status</th><th>Views</th><th>Time Spent</th><th>First Opened</th><th>Last Viewed</th><th>Downloads</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($students as $i=>$s):
            $st = in_array($s['progress_status'], ['not_started','started','completed'], true) ? $s['progress_status'] : 'not_started';
            $label = $st==='completed'?'Completed':($st==='started'?'In Progress':'Not Started');
            $sec=(int)$s['seconds_spent'];
            $time=$sec>=3600?floor($sec/3600).' hr '.floor(($sec%3600)/60).' min':($sec>=60?floor($sec/60).' min':$sec.' sec');
        ?>
          <tr>
            <td><?php echo $offset+$i+1; ?></td>
            <td><strong><?php echo h(trim(($s['fname']??'').' '.($s['lname']??''))); ?></strong><div style="font-size:.8rem;color:var(--muted)"><?php echo h($s['unique_id']); ?></div></td>
            <td><strong><?php echo (int)round((float)$s['progress_percentage']); ?>%</strong></td>
            <td><span class="status-pill status-<?php echo h($st); ?>"><?php echo h($label); ?></span></td>
            <td><?php echo (int)$s['total_views']; ?></td>
            <td><?php echo h($time); ?></td>
            <td><?php echo h(safeDate($s['first_opened_at']??null)); ?></td>
            <td><?php echo h(safeDate($s['last_viewed_at']??null)); ?></td>
            <td><?php echo (int)$s['total_downloads']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div class="pagination-wrap">
      <div class="pagination-info">
        <?php if($totalRows>0): ?>
          Showing <?php echo $offset+1; ?> - <?php echo min($offset+$perPage,$totalRows); ?> of <?php echo $totalRows; ?>
        <?php else: ?>0 students<?php endif; ?>
      </div>

      <div class="pagination-pages">
        <a class="page-btn <?php echo $page<=1?'disabled':''; ?>" href="<?php echo h(pageUrl(max(1,$page-1))); ?>"><i class="fas fa-chevron-left"></i></a>
        <?php
          $sp=max(1,$page-2); $ep=min($totalPages,$page+2);
          if($sp>1){ echo '<a class="page-btn" href="'.h(pageUrl(1)).'">1</a>'; if($sp>2) echo '<span>...</span>'; }
          for($p=$sp;$p<=$ep;$p++) echo '<a class="page-btn '.($p===$page?'active':'').'" href="'.h(pageUrl($p)).'">'.$p.'</a>';
          if($ep<$totalPages){ if($ep<$totalPages-1) echo '<span>...</span>'; echo '<a class="page-btn" href="'.h(pageUrl($totalPages)).'">'.$totalPages.'</a>'; }
        ?>
        <a class="page-btn <?php echo $page>=$totalPages?'disabled':''; ?>" href="<?php echo h(pageUrl(min($totalPages,$page+1))); ?>"><i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
  </div>
</main>

<script>
function toggleRange(){
  const p=document.getElementById('period');
  const r=document.getElementById('rangeWrap');
  if(!p||!r)return;
  r.classList.toggle('show', p.value==='range');
}
</script>
