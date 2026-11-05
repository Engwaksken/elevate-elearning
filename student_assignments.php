<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';
date_default_timezone_set('Africa/Nairobi');

$systemname = $systemname ?? 'Elevate Her 360';

function ma_e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function ma_date(mixed $v): string
{
    return (!$v || strtotime((string)$v)===false) ? '-' : date('d M Y, h:i a', strtotime((string)$v));
}
function ma_page_url(int $page): string
{
    $q=$_GET; $q['page_no']=$page; return '?'.http_build_query($q);
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    echo '<div class="app-alert app-alert-danger">Database connection unavailable.</div>';
    return;
}

$uid = trim((string)($_SESSION[$systemname.'leido'] ?? ''));
$role = trim((string)($_SESSION[$systemname.'letype'] ?? ''));

if ($uid==='' || $role!=='afgezt') {
    echo '<div class="app-alert app-alert-danger">Access denied.</div>';
    return;
}

/* User */
$s=$conn->prepare("SELECT u.fname,u.lname,COALESCE(ct.name,'N/A') class_type_name FROM users u LEFT JOIN class_type ct ON ct.id=u.class_type_id WHERE u.unique_id=? LIMIT 1");
if(!$s){echo '<div class="app-alert app-alert-danger">Unable to load student.</div>';return;}
$s->bind_param('s',$uid);$s->execute();$user=$s->get_result()->fetch_assoc();$s->close();
if(!$user){echo '<div class="app-alert app-alert-danger">Student not found.</div>';return;}

/* Courses */
$s=$conn->prepare("SELECT DISTINCT c.id,c.name FROM course_registration cr JOIN courses c ON c.id=cr.course_id WHERE cr.user_id=? ORDER BY c.name");
$s->bind_param('s',$uid);$s->execute();$rr=$s->get_result();$courses=[];while($rr&&$x=$rr->fetch_assoc())$courses[]=$x;$s->close();
if(!$courses){echo '<div class="app-alert app-alert-info">You are not registered in any course.</div>';return;}

$courseId = max(0,(int)($_GET['course_id'] ?? 0));
if($courseId===0)$courseId=(int)$courses[0]['id'];
$unitId=max(0,(int)($_GET['unit_id'] ?? 0));
$status=trim((string)($_GET['status'] ?? 'all'));
$search=trim((string)($_GET['search'] ?? ''));
if(!in_array($status,['all','pending','submitted','graded'],true))$status='all';

$units=[];
$s=$conn->prepare("SELECT id,name FROM course_unit WHERE course_id=? ORDER BY order_number,id");
if($s){$s->bind_param('i',$courseId);$s->execute();$rr=$s->get_result();while($rr&&$x=$rr->fetch_assoc())$units[]=$x;$s->close();}

$perPage=(int)($_GET['per_page']??25);
if(!in_array($perPage,[10,25,50,100],true))$perPage=25;
$page=max(1,(int)($_GET['page_no']??1));

$where=["cu.course_id=?","cr.user_id=?","COALESCE(a.status,'active')='active'","COALESCE(a.dummy,'no')='no'"];
$params=[$courseId,$uid];$types='is';

if($unitId>0){$where[]='a.course_unit=?';$params[]=$unitId;$types.='i';}
if($search!==''){$like='%'.$search.'%';$where[]='(a.title LIKE ? OR cu.name LIKE ?)';array_push($params,$like,$like);$types.='ss';}
if($status==='pending')$where[]='hi.id IS NULL';
elseif($status==='submitted')$where[]='hi.id IS NOT NULL AND (hi.finalmarks IS NULL OR hi.finalmarks="")';
elseif($status==='graded')$where[]='hi.id IS NOT NULL AND hi.finalmarks IS NOT NULL AND hi.finalmarks!=""';

$whereSql=implode(' AND ',$where);

$fromSql="
FROM assignments a
JOIN course_unit cu ON cu.id=a.course_unit
JOIN course_registration cr ON cr.course_id=cu.course_id AND cr.intake=a.intake
LEFT JOIN handed_in hi ON hi.id=(
    SELECT MAX(hi2.id) FROM handed_in hi2
    WHERE hi2.assignment_id=a.id AND hi2.user_id=cr.user_id
)
WHERE {$whereSql}
";

$s=$conn->prepare("SELECT COUNT(*) total {$fromSql}");
if(!$s){echo '<div class="app-alert app-alert-danger">Unable to load assignments: '.ma_e($conn->error).'</div>';return;}
$s->bind_param($types,...$params);$s->execute();$totalRows=(int)($s->get_result()->fetch_assoc()['total']??0);$s->close();

$totalPages=max(1,(int)ceil($totalRows/$perPage));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$perPage;

$stats=['total'=>$totalRows,'pending'=>0,'submitted'=>0,'graded'=>0];
$s=$conn->prepare("SELECT COUNT(*) total,
SUM(CASE WHEN hi.id IS NULL THEN 1 ELSE 0 END) pending,
SUM(CASE WHEN hi.id IS NOT NULL AND (hi.finalmarks IS NULL OR hi.finalmarks='') THEN 1 ELSE 0 END) submitted,
SUM(CASE WHEN hi.id IS NOT NULL AND hi.finalmarks IS NOT NULL AND hi.finalmarks!='' THEN 1 ELSE 0 END) graded {$fromSql}");
if($s){$s->bind_param($types,...$params);$s->execute();$stats=array_merge($stats,$s->get_result()->fetch_assoc()?:[]);$s->close();}

$sql="SELECT a.id assignment_id,a.title assignment_title,a.short_message,a.marks,a.date_due,a.submission_type,a.max_attempts,a.allow_late_submission,
cu.id unit_id,cu.name module_name,
hi.id submission_id,hi.file_uploaded,hi.date_handed_in,hi.finalmarks,hi.marks_deducted,hi.feedback
{$fromSql}
ORDER BY a.date_due ASC,a.id DESC
LIMIT ? OFFSET ?";
$dataParams=[...$params,$perPage,$offset];$dataTypes=$types.'ii';
$s=$conn->prepare($sql);
if(!$s){echo '<div class="app-alert app-alert-danger">Unable to load assignment rows: '.ma_e($conn->error).'</div>';return;}
$s->bind_param($dataTypes,...$dataParams);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
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
.ma-page{font-family:'DM Sans',sans-serif}
.ma-filters{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;align-items:end;margin-bottom:20px}
.ma-card-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.ma-card{border:1px solid var(--border);border-left:4px solid var(--maroon);border-radius:10px;background:var(--surface);padding:14px;display:flex;flex-direction:column;min-width:0}
.ma-title{font-size:1rem;font-weight:700;line-height:1.4}
.ma-meta{font-size:.87rem;color:var(--muted);line-height:1.5;margin-top:5px}
.ma-desc{font-size:.9rem;color:var(--muted);line-height:1.55;margin-top:8px}
.ma-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:auto;padding-top:12px}
.ma-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 11px;border-radius:7px;border:0;text-decoration:none;cursor:pointer;font-size:.87rem;font-weight:700}
.ma-primary{background:var(--maroon);color:#fff}.ma-soft{background:var(--surface2);color:var(--text);border:1px solid var(--border)}.ma-green{background:var(--green);color:#fff}
.ma-status{display:inline-flex;padding:4px 8px;border-radius:12px;font-size:.8rem;font-weight:700;margin-top:8px}
.ma-pending{background:var(--red-bg);color:var(--red)}.ma-submitted{background:rgba(139,117,54,.12);color:var(--gold)}.ma-graded{background:var(--green-bg);color:var(--green)}
.pagination{display:flex;gap:5px;justify-content:flex-end;align-items:center;flex-wrap:wrap;margin-top:18px}
.page-link{min-width:34px;height:34px;padding:0 9px;border:1px solid var(--border);border-radius:7px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:var(--text);font-size:.85rem}
.page-link.active{background:var(--maroon);color:#fff;border-color:var(--maroon)}.page-link.disabled{opacity:.4;pointer-events:none}
@media(max-width:1200px){.ma-card-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.ma-card-grid{grid-template-columns:repeat(2,1fr)}.ma-filters{grid-template-columns:repeat(2,1fr)}}
@media(max-width:650px){.ma-card-grid,.ma-filters{grid-template-columns:1fr}}
</style>
</head>
<body>
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-clipboard-list"></i> <?php echo ma_e($systemname); ?> — My Assignments</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=courses">Courses</a></li>
      <li class="active">My Assignments</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date('h:i a'); ?></div>
</nav>

<main class="page page-wide ma-page">
<div class="page-heading">
  <div>
    <h2>My <em>Assignments</em></h2>
    <div class="heading-meta">
      <span class="meta-pill"><i class="fas fa-user-circle"></i> <?php echo ma_e(trim(($user['fname']??'').' '.($user['lname']??''))); ?></span>
      <span class="meta-pill"><i class="fas fa-layer-group"></i> <?php echo ma_e($user['class_type_name']); ?></span>
    </div>
  </div>
  <a class="btn-export" href="?view=courses"><i class="fas fa-arrow-left"></i> My Courses</a>
</div>

<form method="GET" class="controls-bar ma-filters">
  <input type="hidden" name="view" value="my_assignment">
  <div class="ctrl-field"><label class="ctrl-label">Course</label><select class="ctrl-select" name="course_id" onchange="this.form.submit()"><?php foreach($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $courseId===(int)$c['id']?'selected':''; ?>><?php echo ma_e($c['name']); ?></option><?php endforeach; ?></select></div>
  <div class="ctrl-field"><label class="ctrl-label">Unit</label><select class="ctrl-select" name="unit_id"><option value="0">All Units</option><?php foreach($units as $u): ?><option value="<?php echo (int)$u['id']; ?>" <?php echo $unitId===(int)$u['id']?'selected':''; ?>><?php echo ma_e($u['name']); ?></option><?php endforeach; ?></select></div>
  <div class="ctrl-field"><label class="ctrl-label">Status</label><select class="ctrl-select" name="status"><option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option><option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option><option value="submitted" <?php echo $status==='submitted'?'selected':''; ?>>Submitted</option><option value="graded" <?php echo $status==='graded'?'selected':''; ?>>Graded</option></select></div>
  <div class="ctrl-field"><label class="ctrl-label">Search</label><input class="ctrl-select" type="text" name="search" value="<?php echo ma_e($search); ?>" placeholder="Assignment or unit..."></div>
  <div style="display:flex;gap:8px"><button class="btn-export" type="submit"><i class="fas fa-filter"></i> Apply</button><a class="btn-export" href="?view=my_assignment&course_id=<?php echo $courseId; ?>"><i class="fas fa-rotate-left"></i> Reset</a></div>
</form>

<div class="stats" style="margin-bottom:22px">
  <div class="stat-card"><div class="stat-icon total"><i class="fas fa-clipboard-list"></i></div><div><div class="sv"><?php echo (int)$stats['total']; ?></div><div class="sl">Total</div></div></div>
  <div class="stat-card"><div class="stat-icon cancel"><i class="fas fa-clock"></i></div><div><div class="sv red"><?php echo (int)$stats['pending']; ?></div><div class="sl">Pending</div></div></div>
  <div class="stat-card"><div class="stat-icon prog"><i class="fas fa-paper-plane"></i></div><div><div class="sv gold"><?php echo (int)$stats['submitted']; ?></div><div class="sl">Submitted</div></div></div>
  <div class="stat-card"><div class="stat-icon done"><i class="fas fa-check-circle"></i></div><div><div class="sv green"><?php echo (int)$stats['graded']; ?></div><div class="sl">Graded</div></div></div>
</div>

<?php if(!$rows): ?>
<div class="empty"><i class="fas fa-inbox"></i><p>No assignments match the selected filters.</p></div>
<?php else: ?>
<div class="ma-card-grid">
<?php foreach($rows as $row):
  $graded=$row['submission_id'] && $row['finalmarks']!==null && $row['finalmarks']!=='';
  $submitted=!empty($row['submission_id']);
  $st=$graded?'graded':($submitted?'submitted':'pending');
  $label=ucfirst($st);
  $late=!empty($row['date_due']) && strtotime((string)$row['date_due'])<time() && !$submitted;
?>
  <article class="ma-card">
    <div class="ma-title"><?php echo ma_e($row['assignment_title']); ?></div>
    <div class="ma-meta"><?php echo ma_e($row['module_name']); ?> . <?php echo ma_e((string)$row['marks']); ?> points</div>
    <div class="ma-meta">Due <?php echo ma_e(ma_date($row['date_due'])); ?> <?php if($late): ?>. <span style="color:var(--red);font-weight:700">Overdue</span><?php endif; ?></div>
    <span class="ma-status ma-<?php echo $st; ?>"><?php echo $label; ?></span>
    <?php if(!empty($row['short_message'])): ?><div class="ma-desc"><?php echo nl2br(ma_e($row['short_message'])); ?></div><?php endif; ?>

    <?php if($submitted): ?>
      <div class="ma-meta">Submitted <?php echo ma_e(ma_date($row['date_handed_in'])); ?></div>
      <?php if($graded): ?><div class="ma-meta"><strong>Score: <?php echo ma_e($row['finalmarks']); ?>/<?php echo ma_e($row['marks']); ?></strong></div><?php endif; ?>
    <?php endif; ?>

    <div class="ma-actions">
      <a class="ma-btn ma-soft" href="?view=cur_course&course=<?php echo $courseId; ?>&module_id=<?php echo (int)$row['unit_id']; ?>"><i class="fas fa-eye"></i> View Assignment</a>

      <?php if(!$submitted): ?>
        <a class="ma-btn ma-primary" href="?view=cur_course&course=<?php echo $courseId; ?>&module_id=<?php echo (int)$row['unit_id']; ?>"><i class="fas fa-upload"></i> Submit</a>
      <?php else: ?>
        <a class="ma-btn ma-primary" href="?view=submission_details&assignment_id=<?php echo (int)$row['assignment_id']; ?>&submission_id=<?php echo (int)$row['submission_id']; ?>&course_id=<?php echo $courseId; ?>"><i class="fas fa-eye"></i> Submission</a>
        <?php if(!empty($row['file_uploaded'])): ?><a class="ma-btn ma-green" href="?view=download_file&submission_id=<?php echo (int)$row['submission_id']; ?>"><i class="fas fa-download"></i> Download</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>

<div class="pagination">
  <a class="page-link <?php echo $page<=1?'disabled':''; ?>" href="<?php echo ma_e(ma_page_url(max(1,$page-1))); ?>"><i class="fas fa-chevron-left"></i></a>
  <?php $sp=max(1,$page-2);$ep=min($totalPages,$page+2);for($p=$sp;$p<=$ep;$p++): ?><a class="page-link <?php echo $p===$page?'active':''; ?>" href="<?php echo ma_e(ma_page_url($p)); ?>"><?php echo $p; ?></a><?php endfor; ?>
  <a class="page-link <?php echo $page>=$totalPages?'disabled':''; ?>" href="<?php echo ma_e(ma_page_url(min($totalPages,$page+1))); ?>"><i class="fas fa-chevron-right"></i></a>
</div>
<?php endif; ?>

</main>
</body>
</html>
