<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$loggedInUserId = $_SESSION[$systemname . "leido"] ?? 0;

/* -- Last entry -------------------------------------------- */
$last = $conn->query("SELECT CONCAT(fname,' ',lname) AS fullname, date_created FROM users WHERE acc_type='afgezt' ORDER BY date_created DESC LIMIT 1");
$lastSeen = 'N/A';
if ($last && $last->num_rows > 0) {
    $lu = $last->fetch_assoc();
    $lastSeen = date("d M Y, h:i a", strtotime($lu['date_created']));
}

/* -- Filter data ------------------------------------------- */
$courses    = [];
$crs = $conn->query("SELECT id, name, short_code FROM courses ORDER BY name ASC");
if ($crs) while ($r = $crs->fetch_assoc()) $courses[] = $r;

$intakes    = [];
$its = $conn->query("SELECT id, name, date_launching FROM intakes ORDER BY date_launching DESC");
if ($its) while ($r = $its->fetch_assoc()) $intakes[] = $r;

$classTypes = [];
$cts = $conn->query("SELECT id, name FROM class_type ORDER BY name ASC");
if ($cts) while ($r = $cts->fetch_assoc()) $classTypes[] = $r;
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
    <h1><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($systemname) ?> - Students</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a>Users</a></li>
      <li class="active">Students</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page page-wide">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2><em>Manage</em> Students</h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-clock"></i> Last entry: <?php echo $lastSeen ?></span>
        <span class="meta-pill"><i class="fas fa-users"></i> <span id="totalPill">-</span></span>
      </div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button id="btnExportCSV" class="btn-export"
              style="background:var(--green); box-shadow:0 2px 8px rgba(21,111,63,.25);">
        <i class="fas fa-file-csv"></i> Export CSV
      </button>
      <button id="btnExportPDF" class="btn-export">
        <i class="fas fa-file-pdf"></i> Export PDF
      </button>
    </div>
  </div>

  <!-- -- Filter panel --------------------------------------- -->
  <div class="controls-bar" style="flex-wrap:wrap; margin-bottom:20px;">

    <!-- Search -->
    <div class="ctrl-field" style="flex:2; min-width:200px;">
      <label class="ctrl-label"><i class="fas fa-search"></i> Search</label>
      <div class="search-wrap" style="width:100%;">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearch"
               placeholder="Name or email..." style="width:100%;">
      </div>
    </div>

    <!-- Course -->
    <div class="ctrl-field" style="flex:1; min-width:170px;">
      <label class="ctrl-label"><i class="fas fa-book"></i> Course</label>
      <select id="filterCourse" class="ctrl-select" style="min-width:100%;">
        <option value="">All courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?php echo (int)$c['id'] ?>">
            <?php echo htmlspecialchars($c['name']) ?> (<?php echo htmlspecialchars($c['short_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Intake -->
    <div class="ctrl-field" style="flex:1; min-width:160px;">
      <label class="ctrl-label"><i class="fas fa-calendar-alt"></i> Intake</label>
      <select id="filterIntake" class="ctrl-select" style="min-width:100%;">
        <option value="">All intakes</option>
        <?php foreach ($intakes as $it): ?>
          <option value="<?php echo (int)$it['id'] ?>">
            <?php echo htmlspecialchars($it['name']) ?> - <?php echo date('Y', strtotime($it['date_launching'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Class type -->
    <div class="ctrl-field" style="flex:1; min-width:140px;">
      <label class="ctrl-label"><i class="fas fa-chalkboard"></i> Class Type</label>
      <select id="filterClassType" class="ctrl-select" style="min-width:100%;">
        <option value="">All types</option>
        <?php foreach ($classTypes as $ct): ?>
          <option value="<?php echo (int)$ct['id'] ?>"><?php echo htmlspecialchars($ct['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Status -->
    <div class="ctrl-field" style="min-width:120px;">
      <label class="ctrl-label"><i class="fas fa-toggle-on"></i> Status</label>
      <select id="filterStatus" class="ctrl-select" style="min-width:100%;">
        <option value="">All</option>
        <option value="yes">Active</option>
        <option value="no">Inactive</option>
      </select>
    </div>

    <!-- Sort -->
    <div class="ctrl-field" style="min-width:150px;">
      <label class="ctrl-label"><i class="fas fa-sort"></i> Sort</label>
      <select id="sortBy" class="ctrl-select" style="min-width:100%;">
        <option value="newest">Newest first</option>
        <option value="oldest">Oldest first</option>
        <option value="alpha">Name A ? Z</option>
      </select>
    </div>

    <!-- Per page -->
    <div class="ctrl-field" style="min-width:110px;">
      <label class="ctrl-label"><i class="fas fa-list-ol"></i> Show</label>
      <select id="perPage" class="ctrl-select" style="min-width:100%;">
        <option value="10">10</option>
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>

    <!-- Apply / Clear -->
    <div style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; padding-top:4px;">
      <button id="btnSearch" class="btn-export">
        <i class="fas fa-filter"></i> Apply
      </button>
      <button id="btnClear" class="btn-modal-cancel">
        <i class="fas fa-times"></i> Clear
      </button>
    </div>

  </div><!-- /.controls-bar -->

  <!-- -- A–Z alphabet filter -------------------------------- -->
  <div style="display:flex; flex-wrap:wrap; align-items:center; gap:4px; margin-bottom:16px;
              background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
              padding:10px 14px; box-shadow:var(--shadow-xs);">
    <span style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
                 color:var(--muted); margin-right:4px;">A–Z</span>
    <?php foreach (range('A', 'Z') as $ch): ?>
      <button class="alpha-btn" data-letter="<?php echo $ch ?>"
              style="width:26px; height:26px; border-radius:5px; border:1px solid var(--border);
                     background:transparent; font-size:.76rem; font-weight:600; cursor:pointer;
                     color:var(--muted); transition:all .15s; font-family:var(--font-sans);">
        <?php echo $ch ?>
      </button>
    <?php endforeach; ?>
    <button id="alphaClear"
            style="padding:4px 10px; border-radius:5px; border:1.5px solid var(--red);
                   background:transparent; font-size:.74rem; font-weight:600; cursor:pointer;
                   color:var(--red); margin-left:6px; font-family:var(--font-sans); transition:all .15s;">
      <i class="fas fa-times"></i> Clear
    </button>
  </div>

  <!-- -- Table card ----------------------------------------- -->
  <div class="tbl-card">

    <div class="tbl-toolbar">
      <span class="tbl-title">
        <i class="fas fa-users"></i> Student Directory
      </span>
      <span class="count-pill" id="resultCount">Loading...</span>
    </div>

    <div class="tbl-scroll">
      <table class="app-table">
        <colgroup>
          <col style="width:42px;">
          <col style="width:46px;">
          <col style="width:22%;">
          <col style="width:20%;">
          <col style="width:110px;">
          <col style="width:18%;">
          <col style="width:130px;">
        </colgroup>
        <thead>
          <tr>
            <th>#</th>
            <th></th>
            <th>Name</th>
            <th>Main Course</th>
            <th>Class Type</th>
            <th>Email</th>
            <th class="th-gold">Actions</th>
          </tr>
        </thead>
        <tbody id="studentList">
          <tr>
            <td colspan="7" style="text-align:center; padding:40px; color:var(--muted);">
              <div class="spin active"
                   style="width:22px;height:22px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 10px;"></div>
              Loading students...
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination footer -->
    <div class="tbl-footer">
      <div id="paginationInfo" style="font-size:.76rem; color:var(--muted);"></div>
      <div class="pag-buttons" id="paginationLinks"></div>
    </div>

  </div><!-- /.tbl-card -->

</main>

<script>
(function(){

  /* -- Elements ------------------------------------------ */
  var gs      = document.getElementById('globalSearch');
  var fc      = document.getElementById('filterCourse');
  var fi      = document.getElementById('filterIntake');
  var fct     = document.getElementById('filterClassType');
  var fs      = document.getElementById('filterStatus');
  var sb      = document.getElementById('sortBy');
  var pp      = document.getElementById('perPage');
  var tbody   = document.getElementById('studentList');
  var pagInfo = document.getElementById('paginationInfo');
  var pagLinks= document.getElementById('paginationLinks');
  var countEl = document.getElementById('resultCount');
  var pillEl  = document.getElementById('totalPill');

  /* -- State --------------------------------------------- */
  var alpha       = '';
  var currentPage = 1;

  /* -- Alpha buttons ------------------------------------- */
  document.querySelectorAll('.alpha-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      alpha = this.dataset.letter;
      document.querySelectorAll('.alpha-btn').forEach(function(b){
        b.style.background  = 'transparent';
        b.style.color       = 'var(--muted)';
        b.style.borderColor = 'var(--border)';
      });
      this.style.background  = 'var(--maroon)';
      this.style.color       = '#fff';
      this.style.borderColor = 'var(--maroon)';
      currentPage = 1; load();
    });
  });
  document.getElementById('alphaClear').addEventListener('click', function(e){
    e.preventDefault();
    alpha = '';
    document.querySelectorAll('.alpha-btn').forEach(function(b){
      b.style.background  = 'transparent';
      b.style.color       = 'var(--muted)';
      b.style.borderColor = 'var(--border)';
    });
    currentPage = 1; load();
  });

  /* -- Filter events ------------------------------------- */
  document.getElementById('btnSearch').addEventListener('click', function(){ currentPage=1; load(); });
  document.getElementById('btnClear').addEventListener('click', function(){
    gs.value=''; fc.value=''; fi.value=''; fct.value=''; fs.value='';
    sb.value='newest'; pp.value='25'; alpha='';
    document.querySelectorAll('.alpha-btn').forEach(function(b){
      b.style.background='transparent'; b.style.color='var(--muted)'; b.style.borderColor='var(--border)';
    });
    currentPage=1; load();
  });
  gs.addEventListener('keyup', function(e){ if(e.key==='Enter'){ currentPage=1; load(); } });
  [fc, fi, fct, fs, sb, pp].forEach(function(el){
    el.addEventListener('change', function(){ currentPage=1; load(); });
  });

  /* -- Build params -------------------------------------- */
  function buildParams(extra) {
    var p = new URLSearchParams();
    p.append('q',             gs.value.trim());
    p.append('course_id',     fc.value);
    p.append('intake_id',     fi.value);
    p.append('class_type_id', fct.value);
    p.append('status',        fs.value);
    p.append('sort',          sb.value);
    p.append('alpha',         alpha);
    p.append('page',          currentPage);
    p.append('per_page',      pp.value);
    if (extra) Object.keys(extra).forEach(function(k){ p.append(k, extra[k]); });
    return p.toString();
  }

  /* -- Load students ------------------------------------- */
  function load() {
    tbody.innerHTML =
      '<tr><td colspan="7" style="text-align:center;padding:36px;color:var(--muted);">' +
      '<div class="spin active" style="width:22px;height:22px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 8px;"></div>' +
      'Loading...</td></tr>';
    pagLinks.innerHTML = '';
    pagInfo.textContent = '';
    countEl.textContent = 'Loading...';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'load_students.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function(){
      if (xhr.status === 200) {
        try {
          var res = JSON.parse(xhr.responseText);
          tbody.innerHTML = res.html ||
            '<tr><td colspan="7" style="text-align:center;padding:48px 24px;">' +
            '<i class="fas fa-user-slash" style="font-size:2rem;color:var(--maroon);opacity:.16;display:block;margin-bottom:8px;"></i>' +
            '<p style="color:var(--muted);font-size:.85rem;">No students match your filters.</p></td></tr>';
          renderPagination(res.total, res.per_page, res.page);
          var t = res.total || 0;
          countEl.textContent = t + ' student' + (t !== 1 ? 's' : '');
          if (pillEl) pillEl.textContent = t + ' student' + (t !== 1 ? 's' : '');
        } catch(e) {
          tbody.innerHTML =
            '<tr><td colspan="7"><div class="app-alert app-alert-danger" style="margin:16px;">' +
            '<i class="fas fa-exclamation-circle"></i>' +
            '<span><strong>Parse error.</strong> ' + escHtml(xhr.responseText.substring(0,400)) + '</span>' +
            '</div></td></tr>';
        }
      } else {
        tbody.innerHTML =
          '<tr><td colspan="7"><div class="app-alert app-alert-danger" style="margin:16px;">' +
          '<i class="fas fa-exclamation-circle"></i> HTTP ' + xhr.status + ' - server error.' +
          '</div></td></tr>';
      }
    };
    xhr.onerror = function(){
      tbody.innerHTML =
        '<tr><td colspan="7"><div class="app-alert app-alert-danger" style="margin:16px;">' +
        '<i class="fas fa-wifi"></i> Network error. Please try again.' +
        '</div></td></tr>';
    };
    xhr.send(buildParams());
  }

  /* -- Render pagination --------------------------------- */
  function renderPagination(total, pp_val, page) {
    var pp_n     = parseInt(pp_val) || 25;
    var total_n  = parseInt(total)  || 0;
    var pages    = Math.ceil(total_n / pp_n) || 1;
    var from     = total_n === 0 ? 0 : (page-1)*pp_n + 1;
    var to       = Math.min(page*pp_n, total_n);

    pagInfo.textContent = total_n > 0
      ? 'Showing ' + from + '–' + to + ' of ' + total_n + ' students'
      : 'No students found';

    pagLinks.innerHTML = '';
    if (pages <= 1) return;

    function makeBtn(label, disabled, active, onClick) {
      var a = document.createElement('a');
      a.href = '#';
      a.innerHTML = label;
      a.className = 'pg-arrow';
      if (disabled) { a.className += ' disabled'; }
      else if (active) {
        a.className = 'pg-num active';
      } else {
        a.className = typeof label === 'number' ? 'pg-num' : 'pg-arrow';
        a.addEventListener('click', function(e){ e.preventDefault(); onClick(); });
      }
      return a;
    }

    /* Prev */
    var prev = document.createElement('a');
    prev.href = '#'; prev.innerHTML = '<i class="fas fa-chevron-left"></i> Prev';
    prev.className = 'pg-arrow' + (page===1?' disabled':'');
    if (page > 1) prev.addEventListener('click', function(e){ e.preventDefault(); currentPage=page-1; load(); });
    pagLinks.appendChild(prev);

    /* Numbers */
    var start = Math.max(1, page-2);
    var end   = Math.min(pages, page+2);
    if (end - start < 4) {
      if (start === 1) end = Math.min(pages, 5);
      else             start = Math.max(1, end-4);
    }
    if (start > 1) {
      var a1 = document.createElement('a'); a1.href='#'; a1.textContent='1'; a1.className='pg-num';
      a1.addEventListener('click', function(e){ e.preventDefault(); currentPage=1; load(); });
      pagLinks.appendChild(a1);
      if (start > 2) { var ell=document.createElement('span'); ell.className='pg-ellipsis'; ell.textContent='...'; pagLinks.appendChild(ell); }
    }
    for (var i=start; i<=end; i++) {
      (function(num){
        var a = document.createElement('a'); a.href='#'; a.textContent=num;
        a.className = 'pg-num' + (num===page?' active':'');
        if (num !== page) a.addEventListener('click', function(e){ e.preventDefault(); currentPage=num; load(); });
        pagLinks.appendChild(a);
      })(i);
    }
    if (end < pages) {
      if (end < pages-1) { var ell=document.createElement('span'); ell.className='pg-ellipsis'; ell.textContent='...'; pagLinks.appendChild(ell); }
      var aLast=document.createElement('a'); aLast.href='#'; aLast.textContent=pages; aLast.className='pg-num';
      aLast.addEventListener('click', function(e){ e.preventDefault(); currentPage=pages; load(); });
      pagLinks.appendChild(aLast);
    }

    /* Next */
    var next = document.createElement('a');
    next.href = '#'; next.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
    next.className = 'pg-arrow' + (page===pages?' disabled':'');
    if (page < pages) next.addEventListener('click', function(e){ e.preventDefault(); currentPage=page+1; load(); });
    pagLinks.appendChild(next);
  }

  /* -- Export -------------------------------------------- */
  function exportData(type) {
    var form    = document.createElement('form');
    form.method = 'POST';
    form.action = 'load_students';
    form.style.display = 'none';

    var params = buildParams({ export: type });
    params.split('&').forEach(function(pair){
      var parts = pair.split('=');
      var k = decodeURIComponent(parts[0]);
      var v = decodeURIComponent(parts[1] || '');
      var inp = document.createElement('input');
      inp.type='hidden'; inp.name=k; inp.value=v;
      form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
    setTimeout(function(){ document.body.removeChild(form); }, 2000);
  }

  document.getElementById('btnExportCSV').addEventListener('click', function(){ exportData('csv'); });
  document.getElementById('btnExportPDF').addEventListener('click', function(){ exportData('pdf'); });

  /* -- Util ---------------------------------------------- */
  function escHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  /* -- Public -------------------------------------------- */
  window.reloadStudents = load;

  load();

})();
</script>