<?php
/* -- Fetch data -------------------------------------------- */
$total_q = $conn->query("SELECT COUNT(*) AS c FROM users WHERE acc_type='xtfbry'");
$totalInstructors = (int)($total_q->fetch_assoc()['c'] ?? 0);

/* Courses for "By Course" tab */
$coursesRes = $conn->query("SELECT id, name, short_code FROM courses ORDER BY name ASC");
$courses = [];
if ($coursesRes) while ($r = $coursesRes->fetch_assoc()) $courses[] = $r;

/* Helper: build instructor rows for a given ORDER BY */
function buildInstructorRows(mysqli $conn, string $orderBy, string $unik): string {
    $rows = '';
    $res  = $conn->query("SELECT * FROM users WHERE acc_type='xtfbry' ORDER BY $orderBy");
    if (!$res || $res->num_rows < 1) {
        return '<tr><td colspan="5"><div class="empty" style="border:none;"><i class="fas fa-chalkboard-teacher"></i><p>No instructors to display.</p></div></td></tr>';
    }

    $i = 1;
    while ($d = $res->fetch_assoc()) {
        $uid  = $d['unique_id'];
        $name = htmlspecialchars(trim(($d['fname']??'').' '.($d['lname']??'')));

        /* Avatar */
        $dp   = ($d['dp'] ?? 'default.jpg');
        $dpSrc = ($dp === 'default.jpg' || empty($dp))
            ? 'images/default_p_pics/user.png'
            : 'images/user_p_pics/' . $dp;

        /* Verified badge */
        $verBadge = ($d['verified'] === 'yes')
            ? '<span class="s-badge s-graded"><i class="fas fa-circle" style="font-size:.45rem;"></i> Active</span>'
            : '<span class="s-badge s-pending"><i class="fas fa-circle" style="font-size:.45rem;"></i> Inactive</span>';

        /* Status toggle link */
        $toggleHref = ($d['verified'] === 'yes')
            ? 'deactivate.php?id='.$uid.'&xts=view_instructors'
            : 'activate.php?id='.$uid.'&xts=view_instructors';

        /* Assigned courses */
        $courseHtml = '';
        $lecRes = $conn->query("SELECT * FROM course_registration_lecturers WHERE user_id='".$conn->real_escape_string($uid)."' ORDER BY id DESC");
        if (!$lecRes || $lecRes->num_rows < 1) {
            $courseHtml = '<span class="cell-na-i">Not assigned</span>';
        } else {
            while ($lec = $lecRes->fetch_assoc()) {
                $cRes = $conn->query("SELECT name FROM courses WHERE id='".(int)$lec['course_id']."' LIMIT 1");
                $cName = ($cRes && $cRes->num_rows > 0) ? htmlspecialchars($cRes->fetch_assoc()['name']) : 'Unknown';
                $courseHtml .= '<span class="s-badge s-class" style="margin:1px 2px; display:inline-flex;">'
                    . '<i class="fas fa-book" style="font-size:.55rem;"></i>&nbsp;' . $cName
                    . '</span>';
            }
        }

        /* Row */
        $rows .= '
        <tr data-search="'.strtolower($name.' '.($d['email']??'')).'" id="lec-'.$uid.'">
          <td class="cell-num">'.$i.'</td>
          <td>
            <img src="'.htmlspecialchars($dpSrc).'"
                 alt="'.htmlspecialchars($name).'"
                 style="width:34px;height:34px;object-fit:cover;border-radius:50%;border:2px solid var(--border);"
                 onerror="this.src=\'images/default_p_pics/user.png\'">
          </td>
          <td>
            <a href="#" class="cell-title" style="text-decoration:none;"
               onclick="openLecDetail(\''.htmlspecialchars(addslashes($uid)).'\'); return false;">
              '.$name.'
            </a>
            <div class="cell-sub-id">'.(htmlspecialchars($d['email']??'')).'</div>
          </td>
          <td style="max-width:220px; white-space:normal; line-height:1.6;">'.$courseHtml.'</td>
          <td>
            <div class="row-actions">
              '.$verBadge.'
              <a href="'.$toggleHref.'" class="ia-btn '.($d['verified']==='yes'?'ia-del':'ia-dl').'"
                 title="'.($d['verified']==='yes'?'Deactivate':'Activate').'"
                 onclick="return confirm(\''.($d['verified']==='yes'?'Deactivate':'Activate').' this instructor?\')">
                <i class="fas fa-'.($d['verified']==='yes'?'ban':'check').'"></i>
              </a>
              <a href="?view=change_courselec&id='.$uid.'" class="ia-btn" title="Assign Course"
                 style="border-color:var(--gold);color:var(--gold);">
                <i class="fas fa-exchange-alt"></i>
              </a>
              <a href="?view=edit_instructor&id='.$uid.'" class="ia-btn ia-view" title="Edit">
                <i class="fas fa-pen"></i>
              </a>
              '.($uid !== $unik
                  ? '<a href="?view=chatwith&pr='.$uid.'" class="ia-btn ia-dl" title="Chat"><i class="fas fa-comment"></i></a>'
                  : '').'
            </div>
          </td>
        </tr>';
        $i++;
    }
    return $rows;
}

$rowsNewest = buildInstructorRows($conn, 'id DESC', $unik ?? '');
$rowsAlpha  = buildInstructorRows($conn, 'fname ASC', $unik ?? '');
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">



<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Instructors</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a>Users</a></li>
      <li class="active">Instructors</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page page-wide">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2><em>Manage</em> Instructors</h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-chalkboard-teacher"></i> <?php echo $totalInstructors ?> instructor<?php echo $totalInstructors !== 1 ? 's' : '' ?></span>
      </div>
    </div>
    <a href="?view=create_user" class="btn-export">
      <i class="fas fa-user-plus"></i> Add Instructor
    </a>
  </div>

  <!-- -- Two-column layout ---------------------------------- -->
  <div style="display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start;">

    <!-- -- Left: instructor table card ----------------------- -->
    <div class="tbl-card">

      <!-- Toolbar -->
      <div class="tbl-toolbar">
        <span class="tbl-title" id="tabTitle">
          <i class="fas fa-list"></i> All Instructors
        </span>
        <div class="toolbar-actions">
          <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="instrSearch" placeholder="Search name or email...">
          </div>
          <span class="count-pill" id="instrCount"><?php echo $totalInstructors ?></span>
        </div>
      </div>

      <!-- Tab bar -->
      <div class="tab-bar" style="width:100%; border-radius:0; border-left:none; border-right:none; border-top:none; padding:0 14px; background:var(--surface2);">
        <button class="tab-btn active" id="tab-btn-newest" onclick="switchTab('newest')">
          <i class="fas fa-clock"></i> Last Joined
        </button>
        <button class="tab-btn" id="tab-btn-alpha" onclick="switchTab('alpha')">
          <i class="fas fa-sort-alpha-down"></i> Alphabetical
        </button>
        <button class="tab-btn" id="tab-btn-course" onclick="switchTab('course')">
          <i class="fas fa-book"></i> By Course
        </button>
      </div>

      <!-- -- Tab: Last Joined -------------------------------- -->
      <div class="tab-panel active" id="tab-panel-newest">
        <div class="tbl-scroll">
          <table class="app-table">
            <thead>
              <tr>
                <th>#</th>
                <th></th>
                <th>Instructor</th>
                <th>Assigned Courses</th>
                <th class="th-gold">Actions</th>
              </tr>
            </thead>
            <tbody id="tbody-newest">
              <?php echo $rowsNewest ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- -- Tab: Alphabetical ------------------------------- -->
      <div class="tab-panel" id="tab-panel-alpha">
        <div class="tbl-scroll">
          <table class="app-table">
            <thead>
              <tr>
                <th>#</th>
                <th></th>
                <th>Instructor</th>
                <th>Assigned Courses</th>
                <th class="th-gold">Actions</th>
              </tr>
            </thead>
            <tbody id="tbody-alpha">
              <?php echo $rowsAlpha ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- -- Tab: By Course ---------------------------------- -->
      <div class="tab-panel" id="tab-panel-course">
        <div style="padding:16px 20px 12px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="courseSelect">
              <i class="fas fa-book"></i> Filter by Course
            </label>
            <select id="courseSelect" class="ctrl-select" style="min-width:100%;"
                    onchange="loadByCourse(this)">
              <option value="">Select a course </option>
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo (int)$c['id'] ?>">
                  <?php echo htmlspecialchars($c['name']) ?> (<?php echo htmlspecialchars($c['short_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="tbl-scroll">
          <table class="app-table">
            <thead>
              <tr>
                <th>#</th>
                <th></th>
                <th>Instructor</th>
                <th>Email</th>
                <th class="th-gold">Actions</th>
              </tr>
            </thead>
            <tbody id="docourse">
              <tr>
                <td colspan="5" style="text-align:center; padding:40px; color:var(--muted);">
                  <i class="fas fa-book" style="font-size:1.8rem; opacity:.16; display:block; margin-bottom:8px; color:var(--maroon);"></i>
                  Select a course above to filter instructors.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Table footer -->
      <div class="tbl-footer">
        <div class="legend">
          <div class="legend-item"><span class="legend-dot legend-dot-green"></span> Active</div>
          <div class="legend-item"><span class="legend-dot" style="background:var(--red);"></span> Inactive</div>
        </div>
        <span style="font-size:.76rem; color:var(--muted);" id="visibleCount">
          <?php echo $totalInstructors ?> instructor<?php echo $totalInstructors !== 1 ? 's' : '' ?>
        </span>
      </div>

    </div><!-- /.tbl-card left -->

    <!-- -- Right: instructor detail panel --------------------- -->
    <div class="tbl-card" id="detailPanel">
      <div class="tbl-toolbar">
        <span class="tbl-title"><i class="fas fa-user-circle"></i> Instructor Detail</span>
      </div>
      <div id="showit" style="padding:20px;">
        <div class="empty" style="border:none; padding:32px 16px;">
          <i class="fas fa-user-circle"></i>
          <p>Click an instructor's name to view their details here.</p>
        </div>
      </div>
    </div>

  </div><!-- /grid -->

</main>


<script>
/* -- Tab switching --------------------------------------- */
var tabTitles = {
  newest: '<i class="fas fa-clock"></i> Last Joined',
  alpha:  '<i class="fas fa-sort-alpha-down"></i> Alphabetical',
  course: '<i class="fas fa-book"></i> By Course'
};

function switchTab(name) {
  ['newest','alpha','course'].forEach(function(t) {
    document.getElementById('tab-btn-'   + t).classList.toggle('active', t === name);
    document.getElementById('tab-panel-' + t).classList.toggle('active', t === name);
  });
  document.getElementById('tabTitle').innerHTML = tabTitles[name];
  /* Re-apply search to newly visible tab */
  applySearch(document.getElementById('instrSearch').value);
}

/* -- Live search (applies to active tbody) --------------- */
function applySearch(q) {
  q = q.toLowerCase().trim();
  var active = document.querySelector('.tab-panel.active');
  if (!active) return;
  var rows = active.querySelectorAll('tbody tr[data-search]');
  var vis  = 0;
  rows.forEach(function(r) {
    var match = !q || (r.dataset.search || '').includes(q);
    r.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  document.getElementById('visibleCount').textContent =
    vis + ' instructor' + (vis !== 1 ? 's' : '');
  document.getElementById('instrCount').textContent = vis;
}

document.getElementById('instrSearch').addEventListener('input', function() {
  applySearch(this.value);
});

/* -- Open instructor detail ------------------------------ */
function openLecDetail(uid) {
  var panel = document.getElementById('showit');
  panel.innerHTML =
    '<div style="text-align:center;padding:28px;color:var(--muted);">' +
    '<div class="spin active" style="width:22px;height:22px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 10px;"></div>' +
    'Loading...</div>';

  /* Highlight active row */
  document.querySelectorAll('.tbl-card tr').forEach(function(r){ r.style.background=''; });
  var row = document.getElementById('lec-' + uid);
  if (row) row.style.background = 'var(--maroon-dim)';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'scripts/getlecdetails.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    panel.innerHTML = xhr.status === 200 ? xhr.responseText :
      '<div class="app-alert app-alert-danger" style="margin:0;"><i class="fas fa-exclamation-circle"></i> Failed to load details.</div>';
  };
  xhr.onerror = function() {
    panel.innerHTML = '<div class="app-alert app-alert-danger" style="margin:0;"><i class="fas fa-wifi"></i> Network error.</div>';
  };
  xhr.send('id=' + encodeURIComponent(uid));
}

/* -- Load by course -------------------------------------- */
function loadByCourse(sel) {
  var courseId = sel.value;
  var tbody    = document.getElementById('docourse');
  if (!courseId) {
    tbody.innerHTML =
      '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">' +
      '<i class="fas fa-book" style="font-size:1.8rem;opacity:.16;display:block;margin-bottom:8px;color:var(--maroon);"></i>' +
      'Select a course above to filter instructors.</td></tr>';
    return;
  }

  tbody.innerHTML =
    '<tr><td colspan="5" style="text-align:center;padding:28px;color:var(--muted);">' +
    '<div class="spin active" style="width:20px;height:20px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 8px;"></div>' +
    'Loading...</td></tr>';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'scripts/getcoursepipolec.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    tbody.innerHTML = xhr.status === 200 ? xhr.responseText :
      '<tr><td colspan="5"><div class="app-alert app-alert-danger" style="margin:8px;"><i class="fas fa-exclamation-circle"></i> Failed to load.</div></td></tr>';
  };
  xhr.onerror = function() {
    tbody.innerHTML =
      '<tr><td colspan="5"><div class="app-alert app-alert-danger" style="margin:8px;"><i class="fas fa-wifi"></i> Network error.</div></td></tr>';
  };
  xhr.send('id=' + encodeURIComponent(courseId));
}


window.openlec   = openLecDetail;
window.changexp  = function(sel) { loadByCourse(sel); };
</script>