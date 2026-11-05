<?php
date_default_timezone_set('Africa/Nairobi');

/* -- Flash messages ---------------------------------------- */
$flash_success = $_SESSION['flash_message'] ?? null;
if ($flash_success) unset($_SESSION['flash_message']);

/* -- Last entry + total ------------------------------------ */
$lastEntryQuery = $conn->query("
    SELECT CONCAT(fname,' ',lname) AS fullname, date_created
    FROM users WHERE acc_type='afgezt'
    ORDER BY date_created DESC LIMIT 1
");
$lastSeen = 'N/A';
if ($lastEntryQuery && $lastEntryQuery->num_rows > 0) {
    $lu = $lastEntryQuery->fetch_assoc();
    $lastSeen = date("d M Y, h:i a", strtotime($lu['date_created']));
}

$totalStudents = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE acc_type='afgezt'")->fetch_assoc()['c'] ?? 0);

/* -- Courses & intakes for dropdowns ----------------------- */
$courses = [];
$cRes = $conn->query("SELECT id, name, short_code FROM courses ORDER BY name ASC");
if ($cRes) while ($r = $cRes->fetch_assoc()) $courses[] = $r;

$intakes = [];
$iRes = $conn->query("
    SELECT i.id, i.name, i.date_launching, b.name AS branch_name
    FROM intakes i JOIN branches b ON i.branch_id=b.id
    ORDER BY i.date_launching DESC
");
if ($iRes) while ($r = $iRes->fetch_assoc()) $intakes[] = $r;

/* -- Only admins may bulk-activate students --------------- */
$canBulkActivate = in_array($_SESSION[$systemname . 'letype'] ?? '', ['sabrtw', 'xybrtw'], true);

/* -- Bulk upload (admins only) ----------------------------- */
$canBulkUpload = $canBulkActivate;
$classTypes = [];
if ($canBulkUpload) {
    if (empty($_SESSION['bulk_upload_csrf'])) {
        $_SESSION['bulk_upload_csrf'] = bin2hex(random_bytes(32));
    }
    $ctRes = $conn->query("SELECT id, name FROM class_type WHERE status='Active' ORDER BY name ASC");
    if ($ctRes) while ($r = $ctRes->fetch_assoc()) $classTypes[] = $r;
}
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

  <!-- -- Flash alert ---------------------------------------- -->
  <?php if ($flash_success): ?>
  <div class="app-alert app-alert-info flash-toast" id="flashAlert">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($flash_success) ?></span>
    <button onclick="document.getElementById('flashAlert').remove()"
            style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <?php endif; ?>

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2><em>Manage</em> Students</h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-users"></i> <span id="totalPill"><?php echo $totalStudents ?></span> students</span>
        <span class="meta-pill"><i class="fas fa-clock"></i> Last entry: <?php echo $lastSeen ?></span>
      </div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <?php if ($canBulkUpload): ?>
      <a href="scripts/bulk_upload_students.php?action=template" class="btn-modal-cancel" style="text-decoration:none;"
         title="Download a CSV template for uploading students">
        <i class="fas fa-file-arrow-down"></i> CSV Template
      </a>
      <a href="scripts/bulk_upload_students.php?action=reference" class="btn-modal-cancel" style="text-decoration:none;"
         title="Download course codes and intake IDs to use in the upload CSV">
        <i class="fas fa-list-ol"></i> Course &amp; Intake IDs
      </a>
      <button id="openUploadBtn" class="btn-export" style="background:var(--gold); box-shadow:0 2px 8px rgba(0,0,0,.12);">
        <i class="fas fa-file-arrow-up"></i> Upload Students
      </button>
      <?php endif; ?>
      <button id="exportCsvBtn" class="btn-export"
              style="background:var(--green); box-shadow:0 2px 8px rgba(21,111,63,.25);">
        <i class="fas fa-file-csv"></i> Export CSV
        <span class="spin" id="csvSpin"></span>
      </button>
      <button id="exportPdfBtn" class="btn-export">
        <i class="fas fa-file-pdf"></i> Export PDF
        <span class="spin" id="pdfSpin"></span>
      </button>
    </div>
  </div>

  <!-- -- Filter panel --------------------------------------- -->
  <div class="controls-bar" style="flex-wrap:wrap; margin-bottom:20px;">

    <!-- Search -->
    <div class="ctrl-field" style="flex:2; min-width:220px;">
      <label class="ctrl-label"><i class="fas fa-search"></i> Search</label>
      <div class="search-wrap" style="width:100%;">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Name, email, phone..." style="width:100%;">
      </div>
    </div>

    <!-- Course -->
    <div class="ctrl-field" style="flex:1; min-width:180px;">
      <label class="ctrl-label"><i class="fas fa-book"></i> Course</label>
      <select id="courseFilter" class="ctrl-select" style="min-width:100%;">
        <option value="">All courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?php echo (int)$c['id'] ?>">
            <?php echo htmlspecialchars($c['name']) ?> (<?php echo htmlspecialchars($c['short_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Intake -->
    <div class="ctrl-field" style="flex:1; min-width:200px;">
      <label class="ctrl-label"><i class="fas fa-calendar-alt"></i> Intake</label>
      <select id="intakeFilter" class="ctrl-select" style="min-width:100%;">
        <option value="">All intakes</option>
        <?php foreach ($intakes as $it): ?>
          <option value="<?php echo (int)$it['id'] ?>">
            <?php echo htmlspecialchars($it['name']) ?>
            (<?php echo date("Y", strtotime($it['date_launching'])) ?>)
            &mdash; <?php echo htmlspecialchars($it['branch_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Status -->
    <div class="ctrl-field" style="min-width:140px;">
      <label class="ctrl-label"><i class="fas fa-toggle-on"></i> Status</label>
      <select id="statusFilter" class="ctrl-select" style="min-width:100%;">
        <option value="">All</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Date range -->
    <div class="ctrl-field" style="min-width:200px;">
      <label class="ctrl-label"><i class="fas fa-calendar-alt"></i> Join Date Range</label>
      <div style="display:flex; gap:6px; align-items:center;">
        <input type="date" id="dateFrom" class="form-input" style="flex:1; min-width:0;">
        <span style="color:var(--muted); font-size:.78rem;">to</span>
        <input type="date" id="dateTo"   class="form-input" style="flex:1; min-width:0;">
      </div>
    </div>

    <!-- Sort + page size row -->
    <div class="ctrl-field" style="min-width:160px;">
      <label class="ctrl-label"><i class="fas fa-sort"></i> Sort</label>
      <select id="orderBy" class="ctrl-select" style="min-width:100%;">
        <option value="last_joined_desc">Newest first</option>
        <option value="last_joined_asc">Oldest first</option>
        <option value="name_asc">Name A ? Z</option>
        <option value="name_desc">Name Z ? A</option>
      </select>
    </div>

    <div class="ctrl-field" style="min-width:110px;">
      <label class="ctrl-label"><i class="fas fa-list-ol"></i> Show</label>
      <select id="pageSize" class="ctrl-select" style="min-width:100%;">
        <option value="15">15</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="250">250</option>
        <option value="500">All</option>
      </select>
    </div>

    <!-- Action buttons -->
    <div style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; padding-top:4px;">
      <button id="applyFilter" class="btn-export">
        <i class="fas fa-filter"></i> Apply
      </button>
      <button id="clearFilter" class="btn-modal-cancel">
        <i class="fas fa-times"></i> Clear
      </button>
    </div>

  </div><!-- /.controls-bar -->

  <!-- -- Students table ------------------------------------- -->
  <div class="tbl-card">

    <div class="tbl-toolbar">
      <span class="tbl-title">
        <i class="fas fa-users"></i> Student Directory
      </span>
      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <?php if ($canBulkActivate): ?>
        <button id="bulkActivateBtn" class="btn-export" disabled
                style="background:var(--green); box-shadow:0 2px 8px rgba(21,111,63,.25);"
                title="Select inactive students to activate them">
          <i class="fas fa-user-check"></i> Activate selected (<span id="selCount">0</span>)
          <span class="spin" id="bulkSpin"></span>
        </button>
        <?php endif; ?>
        <span class="count-pill" id="totalDisplay"><?php echo $totalStudents ?> students</span>
      </div>
    </div>

    <div class="tbl-scroll">
      <table class="app-table" id="studentsTable">
        <thead>
          <tr>
            <?php if ($canBulkActivate): ?>
            <th style="width:34px;"><input type="checkbox" id="selectAllInactive" title="Select all inactive students on this page"></th>
            <?php endif; ?>
            <th>#</th>
            <th>Photo</th>
            <th>Name</th>
            <th>Course</th>
            <th>Intake</th>
            <th>Email</th>
            <th class="th-gold">Actions</th>
          </tr>
        </thead>
        <tbody id="studentsBody">
          <tr>
            <td colspan="<?php echo $canBulkActivate ? 8 : 7 ?>" style="text-align:center; padding:40px; color:var(--muted);">
              <div class="spin active" style="width:24px;height:24px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 10px;"></div>
              Loading students...
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination footer -->
    <div class="tbl-footer" style="justify-content:space-between;">
      <div style="display:flex; align-items:center; gap:8px;">
        <a id="prevBtn" href="#" class="pg-arrow disabled">
          <i class="fas fa-chevron-left"></i> Prev
        </a>
        <span id="pageInfo" style="font-size:.78rem; color:var(--muted); padding:0 6px;">Page 1</span>
        <a id="nextBtn" href="#" class="pg-arrow">
          Next <i class="fas fa-chevron-right"></i>
        </a>
      </div>
      <small id="totalInfo" style="color:var(--muted); font-size:.76rem;">0 entries</small>
    </div>

  </div><!-- /.tbl-card -->

</main>


<!-- ----------------------------------------------------------
     TOAST NOTIFICATION BAR
----------------------------------------------------------- -->
<div class="toast-bar" id="toastBar"></div>

<?php if ($canBulkUpload): ?>
<!-- ----------------------------------------------------------
     BULK UPLOAD MODAL
----------------------------------------------------------- -->
<div class="modal-overlay" id="uploadModal" aria-hidden="true">
  <div class="modal-box" style="max-width:760px;">
    <div class="modal-head">
      <h3><i class="fas fa-file-arrow-up"></i> Upload Students from CSV</h3>
      <button type="button" class="modal-close" id="uploadClose" title="Close"><i class="fas fa-times"></i></button>
    </div>

    <form id="uploadForm" enctype="multipart/form-data" style="display:contents;">
      <div class="modal-body">
        <div id="uploadFormFields">
          <p style="font-size:.82rem; color:var(--muted); margin:0 0 14px;">
            Required columns: <strong>first_name, last_name, email, phone</strong>.
            Optional: other_name, gender, country, dob (YYYY-MM-DD), is_pwd (yes/no),
            <strong>course</strong> (ID or short code) and <strong>intake</strong> (ID).
            <a href="scripts/bulk_upload_students.php?action=template">Download the template</a> &middot;
            <a href="scripts/bulk_upload_students.php?action=reference">Course &amp; intake IDs</a>.
            Each student is enrolled in the course and intake on their row; rows with those cells blank use the defaults below.
            The default courses are always added too.
            Emails that already belong to a student are registered in the row's course instead of being skipped,
            so a student can appear on several rows (one per course).
            New accounts stay inactive until the student verifies their email; you can also activate them from this list.
          </p>

          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['bulk_upload_csrf']) ?>">

          <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;">
            <div class="ctrl-field">
              <label class="ctrl-label" for="upCourse"><i class="fas fa-book"></i> Default Course</label>
              <select id="upCourse" name="course_id" class="ctrl-select" style="min-width:100%;">
                <option value="">None (use CSV course column)</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?php echo (int)$c['id'] ?>"><?php echo htmlspecialchars($c['name']) ?> (<?php echo htmlspecialchars($c['short_code']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ctrl-field">
              <label class="ctrl-label" for="upIntake"><i class="fas fa-calendar-alt"></i> Default Intake</label>
              <select id="upIntake" name="intake_id" class="ctrl-select" style="min-width:100%;">
                <option value="">None (use CSV intake column)</option>
                <?php foreach ($intakes as $it): ?>
                  <option value="<?php echo (int)$it['id'] ?>">
                    <?php echo htmlspecialchars($it['name']) ?> (<?php echo date("Y", strtotime($it['date_launching'])) ?>) &mdash; <?php echo htmlspecialchars($it['branch_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ctrl-field">
              <label class="ctrl-label" for="upClass"><i class="fas fa-chalkboard"></i> Class Type *</label>
              <select id="upClass" name="class_type_id" class="ctrl-select" style="min-width:100%;" required>
                <option value="">Select class type</option>
                <?php foreach ($classTypes as $ct): ?>
                  <option value="<?php echo (int)$ct['id'] ?>"><?php echo htmlspecialchars($ct['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="ctrl-field" style="margin-top:12px;">
            <label class="ctrl-label" for="upFile"><i class="fas fa-file-csv"></i> CSV File * (max 1000 rows, 2 MB)</label>
            <input type="file" id="upFile" name="csv_file" accept=".csv,text/csv" class="form-input" required>
          </div>

          <label style="display:flex; align-items:center; gap:8px; margin-top:12px; font-size:.82rem;">
            <input type="checkbox" id="upSendEmails" checked> Email login details to each new student
          </label>
        </div>

        <div id="uploadResults" style="display:none;">
          <div class="app-alert app-alert-info" id="uploadSummary" style="margin-bottom:12px;"></div>
          <div class="tbl-scroll" style="max-height:42vh;">
            <table class="app-table">
              <thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Course / Intake</th><th>Student No.</th><th>Result</th></tr></thead>
              <tbody id="uploadResultsBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" id="uploadCancel">Close</button>
        <button type="button" class="btn-modal-cancel" id="uploadAnother" style="display:none;">
          <i class="fas fa-rotate"></i> Upload another
        </button>
        <button type="button" class="btn-export" id="uploadDownloadResults" style="display:none;">
          <i class="fas fa-file-csv"></i> Download results
        </button>
        <button type="submit" class="btn-export" id="uploadSubmit">
          <i class="fas fa-file-arrow-up"></i> Upload
          <span class="spin" id="uploadSpin"></span>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>


<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script>
(function(){

  /* -- State ------------------------------------------------ */
  var currentPage    = 1;
  var totalEntries   = 0;
  var searchTimeout  = null;
  var EXPORT_LIMIT   = 50000;
  var CAN_ACTIVATE   = <?php echo $canBulkActivate ? 'true' : 'false' ?>;
  var COLS           = CAN_ACTIVATE ? 8 : 7;

  /* -- Helpers ---------------------------------------------- */
  function getLimit() {
    var v = parseInt(document.getElementById('pageSize').value, 10);
    return isNaN(v) ? 15 : v;
  }

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function buildParams(page, forExport) {
    var p = {
      page:      page,
      limit:     forExport ? EXPORT_LIMIT : getLimit(),
      search:    val('searchInput'),
      course:    val('courseFilter'),
      intake:    val('intakeFilter'),
      status:    val('statusFilter'),
      order:     val('orderBy'),
      date_from: val('dateFrom'),
      date_to:   val('dateTo'),
    };
    if (forExport) { p.export = 1; p.page = 1; }
    return p;
  }

  function toQueryString(obj) {
    return Object.keys(obj)
      .filter(function(k){ return obj[k] !== ''; })
      .map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); })
      .join('&');
  }

  /* -- Render single table row ------------------------------ */
  function renderRow(s) {
    var dp = s.dp
      ? '<img src="' + s.dp + '" alt="' + (s.name||'') + '" style="width:34px;height:34px;object-fit:cover;border-radius:50%;border:2px solid var(--border);">'
      : '<div style="width:34px;height:34px;border-radius:50%;background:var(--maroon-dim);display:grid;place-items:center;color:var(--maroon);font-size:.78rem;"><i class="fas fa-user"></i></div>';

    var statusBadge = (s.status === 'Active')
      ? '<span class="s-badge s-graded"><i class="fas fa-circle" style="font-size:.5rem;"></i> Active</span>'
      : '<span class="s-badge s-pending">' + (s.status || 'Inactive') + '</span>';

    var selectCell = '';
    if (CAN_ACTIVATE) {
      selectCell = (s.status === 'Active')
        ? '<td></td>'
        : '<td><input type="checkbox" class="row-select" value="' + String(s.unique_id).replace(/"/g, '&quot;') + '" title="Select to activate"></td>';
    }

    return '<tr>' +
      selectCell +
      '<td class="cell-num">' + s.counter + '</td>' +
      '<td>' + dp + '</td>' +
      '<td>' +
        '<a href="?view=view_student&id=' + encodeURIComponent(s.unique_id) + '" class="cell-title" style="text-decoration:none; color:var(--text);">' +
          (s.name || '') +
        '</a>' +
        (s.phone ? '<div class="cell-sub-id"><i class="fas fa-phone" style="color:var(--gold);font-size:.6rem;"></i> ' + s.phone + '</div>' : '') +
      '</td>' +
      '<td class="cell-sm">' + (s.courseFull || '-') + '</td>' +
      '<td class="chat-date">' + (s.intakeBranch || '-') + '</td>' +
      '<td><div class="user-email"><i class="fas fa-envelope"></i>' + (s.email || '') + '</div></td>' +
      '<td>' +
        '<div class="row-actions">' +
          statusBadge +
          '<a href="?view=edit_student&id=' + encodeURIComponent(s.unique_id) + '" class="ia-btn ia-view" title="Edit"><i class="fas fa-pen"></i></a>' +
          '<a href="?view=study_plan&oiuwheilfbwehfjjasdkg=' + encodeURIComponent(s.unique_id) + '&xxpoupoquegvbm=' + encodeURIComponent(s.course_id||'') + '" class="ia-btn ia-dl" title="Study Plan"><i class="fas fa-book-open"></i></a>' +
          '<a href="?view=change_course&id=' + encodeURIComponent(s.unique_id) + '" class="ia-btn" title="Assign Course" style="border-color:var(--gold);color:var(--gold);"><i class="fas fa-exchange-alt"></i></a>' +
          '<a href="?view=delete_student&id=' + encodeURIComponent(s.unique_id) + '" class="ia-btn ia-del" title="Delete" onclick="return confirm(\'Delete this student?\')"><i class="fas fa-trash"></i></a>' +
        '</div>' +
      '</td>' +
    '</tr>';
  }

  /* -- Update pagination UI --------------------------------- */
  function updatePaging(page, total, limit) {
    totalEntries = total;
    var pages = Math.max(1, Math.ceil(total / (limit || 1)));
    currentPage  = page;

    document.getElementById('pageInfo').textContent    = 'Page ' + page + ' of ' + pages;
    document.getElementById('totalInfo').textContent   = total + ' entries';
    document.getElementById('totalDisplay').textContent = total + ' student' + (total !== 1 ? 's' : '');
    document.getElementById('totalPill').textContent    = total;

    var prev = document.getElementById('prevBtn');
    var next = document.getElementById('nextBtn');
    prev.classList.toggle('disabled', page <= 1);
    next.classList.toggle('disabled', page >= pages);
  }

  /* -- Load students ---------------------------------------- */
  function loadStudents(page) {
    page = page || 1;
    var body = document.getElementById('studentsBody');
    body.innerHTML =
      '<tr><td colspan="' + COLS + '" style="text-align:center;padding:36px;color:var(--muted);">' +
      '<div class="spin active" style="width:22px;height:22px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 8px;"></div>' +
      'Loading...</td></tr>';

    var params = buildParams(page, false);

    fetch('scripts/fetch_students_advanced.php?' + toQueryString(params))
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data && Array.isArray(data.students) && data.students.length) {
          body.innerHTML = data.students.map(renderRow).join('');
        } else {
          body.innerHTML =
            '<tr><td colspan="' + COLS + '" style="text-align:center;padding:48px 24px;">' +
            '<i class="fas fa-user-slash" style="font-size:2rem;color:var(--maroon);opacity:.16;display:block;margin-bottom:8px;"></i>' +
            '<p style="color:var(--muted);font-size:.85rem;">No students found matching your filters.</p>' +
            '</td></tr>';
        }
        updatePaging(data.currentPage || page, data.totalEntries || 0, params.limit);
        refreshSelection();
      })
      .catch(function(err) {
        console.error(err);
        body.innerHTML =
          '<tr><td colspan="' + COLS + '" style="text-align:center;padding:36px;">' +
          '<div class="app-alert app-alert-danger" style="display:inline-flex;margin:0;"><i class="fas fa-exclamation-circle"></i> Error loading data. Please try again.</div>' +
          '</td></tr>';
      });
  }

  loadStudents(1);

  /* -- Filter / pagination events --------------------------- */
  document.getElementById('applyFilter').addEventListener('click', function(){ loadStudents(1); });

  document.getElementById('clearFilter').addEventListener('click', function(){
    ['searchInput','courseFilter','intakeFilter','statusFilter','dateFrom','dateTo'].forEach(function(id){
      var el = document.getElementById(id); if (el) el.value = '';
    });
    document.getElementById('orderBy').value  = 'last_joined_desc';
    document.getElementById('pageSize').value = '15';
    loadStudents(1);
  });

  document.getElementById('prevBtn').addEventListener('click', function(e){
    e.preventDefault();
    if (currentPage > 1) loadStudents(currentPage - 1);
  });
  document.getElementById('nextBtn').addEventListener('click', function(e){
    e.preventDefault();
    var pages = Math.max(1, Math.ceil(totalEntries / (getLimit() || 1)));
    if (currentPage < pages) loadStudents(currentPage + 1);
  });

  document.getElementById('searchInput').addEventListener('input', function(){
    clearTimeout(searchTimeout);
    var v = this.value.trim();
    if (v.length >= 3 || v.length === 0) {
      searchTimeout = setTimeout(function(){ loadStudents(1); }, 400);
    }
  });

  /* -- Toast helper ----------------------------------------- */
  function showToast(msg, type) {
    var bar  = document.getElementById('toastBar');
    var cls  = type === 'success' ? 'toast-success' : 'toast-error';
    var icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    var el   = document.createElement('div');
    el.className = 'toast ' + cls;
    el.innerHTML = '<i class="fas ' + icon + '"></i> ' + msg;
    bar.appendChild(el);
    setTimeout(function(){ el.remove(); }, 5000);
  }

  /* -- Set loading state on export button ------------------- */
  function setBtnLoading(btnId, spinId, loading) {
    var btn  = document.getElementById(btnId);
    var spin = document.getElementById(spinId);
    btn.disabled = loading;
    spin.classList.toggle('active', loading);
  }

  /* -- CSV Export ------------------------------------------- */
  document.getElementById('exportCsvBtn').addEventListener('click', function(){
    setBtnLoading('exportCsvBtn', 'csvSpin', true);
    fetch('scripts/fetch_students_advanced.php?' + toQueryString(buildParams(1, true)))
      .then(function(r){ return r.json(); })
      .then(function(data) {
        setBtnLoading('exportCsvBtn', 'csvSpin', false);
        if (!data || !data.students || !data.students.length) {
          showToast('No data to export.', 'error'); return;
        }
        var csv = '\ufeff#,Name,Email,Course,Intake,Branch,Status,Date Joined\n';
        data.students.forEach(function(s, i){
          csv += [i+1, s.name, s.email, s.courseFull, s.intake, s.branch, s.status, s.dateJoined]
            .map(function(f){ return '"' + String(f||'').replace(/"/g,'""') + '"'; })
            .join(',') + '\n';
        });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'}));
        a.download = 'students_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        showToast('Exported ' + data.students.length + ' students to CSV.', 'success');
      })
      .catch(function(){ setBtnLoading('exportCsvBtn','csvSpin',false); showToast('Export failed.','error'); });
  });

  /* -- PDF Export ------------------------------------------- */
  document.getElementById('exportPdfBtn').addEventListener('click', function(){
    setBtnLoading('exportPdfBtn','pdfSpin',true);
    fetch('scripts/fetch_students_advanced.php?' + toQueryString(buildParams(1, true)))
      .then(function(r){ return r.json(); })
      .then(function(data) {
        setBtnLoading('exportPdfBtn','pdfSpin',false);
        if (!data || !data.students || !data.students.length) {
          showToast('No data to export.', 'error'); return;
        }
        try {
          var jsPDF = window.jspdf.jsPDF;
          var doc   = new jsPDF('l','mm','a4');

          /* Header */
          doc.setFillColor(90,14,14);
          doc.rect(0,0,297,20,'F');
          doc.setTextColor(255,255,255);
          doc.setFontSize(14); doc.setFont(undefined,'bold');
          doc.text('Students Report', 14, 13);
          doc.setFontSize(9); doc.setFont(undefined,'normal');
          doc.text('Generated: ' + new Date().toLocaleString(), 297-14, 13, {align:'right'});

          doc.setTextColor(0,0,0);
          doc.setFontSize(9);
          doc.text('Total: ' + data.students.length + ' students', 14, 26);

          var tableData = data.students.map(function(s, i){
            return [i+1, s.name||'', s.email||'', s.courseFull||'', s.intakeBranch||'', s.status||''];
          });

          doc.autoTable({
            head: [['#','Name','Email','Course','Intake / Branch','Status']],
            body: tableData,
            startY: 30,
            theme: 'striped',
            headStyles: { fillColor: [90,14,14], textColor: 255, fontStyle: 'bold', fontSize: 9 },
            alternateRowStyles: { fillColor: [246,247,250] },
            styles: { fontSize: 8, cellPadding: 3, overflow: 'linebreak' },
            columnStyles: {
              0: { cellWidth: 10, halign: 'center' },
              1: { cellWidth: 55 },
              2: { cellWidth: 65 },
              3: { cellWidth: 65 },
              4: { cellWidth: 55 },
              5: { cellWidth: 22, halign: 'center' }
            },
            margin: { left: 14, right: 14 },
            didDrawPage: function(d) {
              var h = doc.internal.pageSize.height;
              doc.setFontSize(8); doc.setTextColor(150);
              doc.text(
                'Page ' + d.pageNumber,
                14, h - 8
              );
              doc.text(
                '© ' + new Date().getFullYear() + ' ' + '<?php echo htmlspecialchars($systemname) ?>',
                297/2, h - 8, {align:'center'}
              );
            }
          });

          doc.save('students_' + new Date().toISOString().slice(0,10) + '.pdf');
          showToast('Exported ' + data.students.length + ' students to PDF.', 'success');
        } catch(err) {
          console.error(err);
          showToast('PDF generation failed: ' + err.message, 'error');
        }
      })
      .catch(function(){ setBtnLoading('exportPdfBtn','pdfSpin',false); showToast('Export failed.','error'); });
  });

  /* -- Bulk activation (admins only) ------------------------ */
  function selectedIds() {
    return Array.prototype.map.call(
      document.querySelectorAll('#studentsBody .row-select:checked'),
      function(cb){ return cb.value; }
    );
  }

  function refreshSelection() {
    if (!CAN_ACTIVATE) return;
    var all   = document.querySelectorAll('#studentsBody .row-select');
    var count = selectedIds().length;
    document.getElementById('selCount').textContent = count;
    document.getElementById('bulkActivateBtn').disabled = count === 0;
    var master = document.getElementById('selectAllInactive');
    master.checked       = all.length > 0 && count === all.length;
    master.indeterminate = count > 0 && count < all.length;
    master.disabled      = all.length === 0;
  }

  if (CAN_ACTIVATE) {
    document.getElementById('studentsBody').addEventListener('change', function(e){
      if (e.target.classList.contains('row-select')) refreshSelection();
    });

    document.getElementById('selectAllInactive').addEventListener('change', function(){
      var on = this.checked;
      document.querySelectorAll('#studentsBody .row-select').forEach(function(cb){ cb.checked = on; });
      refreshSelection();
    });

    document.getElementById('bulkActivateBtn').addEventListener('click', function(){
      var ids = selectedIds();
      if (!ids.length) return;
      if (!confirm('Activate ' + ids.length + ' selected student' + (ids.length !== 1 ? 's' : '') + '?')) return;

      setBtnLoading('bulkActivateBtn', 'bulkSpin', true);
      fetch('scripts/bulk_activate_students.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids })
      })
        .then(function(r){ return r.json(); })
        .then(function(res){
          setBtnLoading('bulkActivateBtn', 'bulkSpin', false);
          if (res && res.status === 'success') {
            showToast(res.message || 'Students activated.', 'success');
            loadStudents(currentPage);
          } else {
            showToast((res && res.message) || 'Activation failed.', 'error');
            refreshSelection();
          }
        })
        .catch(function(){
          setBtnLoading('bulkActivateBtn', 'bulkSpin', false);
          showToast('Activation failed. Please try again.', 'error');
          refreshSelection();
        });
    });
  }

  /* -- Bulk upload (admins only) ---------------------------- */
  var uploadModal = document.getElementById('uploadModal');
  if (uploadModal) {
    var uploadForm  = document.getElementById('uploadForm');
    var lastResults = [];

    function escHtml(v) {
      return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
      });
    }

    function showUploadStep(showResults) {
      document.getElementById('uploadFormFields').style.display      = showResults ? 'none' : '';
      document.getElementById('uploadResults').style.display         = showResults ? '' : 'none';
      document.getElementById('uploadSubmit').style.display          = showResults ? 'none' : '';
      document.getElementById('uploadDownloadResults').style.display = showResults ? '' : 'none';
      document.getElementById('uploadAnother').style.display         = showResults ? '' : 'none';
    }

    function openUpload() {
      showUploadStep(false);
      uploadModal.classList.add('is-open');
      uploadModal.setAttribute('aria-hidden', 'false');
    }

    function closeUpload() {
      uploadModal.classList.remove('is-open');
      uploadModal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('openUploadBtn').addEventListener('click', openUpload);
    document.getElementById('uploadClose').addEventListener('click', closeUpload);
    document.getElementById('uploadCancel').addEventListener('click', closeUpload);
    uploadModal.addEventListener('click', function(e){ if (e.target === uploadModal) closeUpload(); });
    document.getElementById('uploadAnother').addEventListener('click', function(){
      document.getElementById('upFile').value = '';
      showUploadStep(false);
    });

    uploadForm.addEventListener('submit', function(e){
      e.preventDefault();
      if (!uploadForm.reportValidity()) return;

      var fd = new FormData(uploadForm);
      fd.set('send_emails', document.getElementById('upSendEmails').checked ? '1' : '0');

      setBtnLoading('uploadSubmit', 'uploadSpin', true);
      fetch('scripts/bulk_upload_students.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          setBtnLoading('uploadSubmit', 'uploadSpin', false);
          if (!res || res.status !== 'success') {
            showToast(escHtml((res && res.message) || 'Upload failed.'), 'error');
            return;
          }

          lastResults = res.results || [];
          document.getElementById('uploadSummary').innerHTML =
            '<i class="fas fa-info-circle"></i> ' + escHtml(res.message) +
            (res.default_course || res.default_intake
              ? ' Defaults for blank cells: course <strong>' + escHtml(res.default_course || 'none') +
                '</strong>, intake <strong>' + escHtml(res.default_intake || 'none') + '</strong>.'
              : '');

          document.getElementById('uploadResultsBody').innerHTML = lastResults.map(function(r){
            var badge = {
              created:    '<span class="s-badge s-graded">Created &amp; registered</span>',
              registered: '<span class="s-badge s-graded">Registered</span>',
              already:    '<span class="s-badge s-submitted">Already registered</span>'
            }[r.status] || '<span class="s-badge s-pending">Skipped</span>';
            return '<tr>' +
              '<td class="cell-num">' + escHtml(r.line) + '</td>' +
              '<td>' + escHtml(r.name) + '</td>' +
              '<td>' + escHtml(r.email) + '</td>' +
              '<td>' + escHtml(r.course || '-') + '<div class="cell-sub-id">' + escHtml(r.intake || '') + '</div></td>' +
              '<td>' + escHtml(r.student_no || '-') + '</td>' +
              '<td>' + badge + ' <div class="cell-sub-id">' + escHtml(r.message) + '</div></td>' +
            '</tr>';
          }).join('');

          showUploadStep(true);
          var changed = (res.created || 0) + (res.registered || 0);
          showToast(escHtml(res.message), changed > 0 ? 'success' : 'error');
          if (changed > 0) loadStudents(1);
        })
        .catch(function(){
          setBtnLoading('uploadSubmit', 'uploadSpin', false);
          showToast('Upload failed. Please try again.', 'error');
        });
    });

    document.getElementById('uploadDownloadResults').addEventListener('click', function(){
      if (!lastResults.length) return;
      var csv = '\ufeffRow,Name,Email,Course,Intake,Student No,Status,Temporary Password,Message\n';
      lastResults.forEach(function(r){
        csv += [r.line, r.name, r.email, r.course, r.intake, r.student_no, r.status, r.password, r.message]
          .map(function(f){ return '"' + String(f == null ? '' : f).replace(/"/g, '""') + '"'; })
          .join(',') + '\n';
      });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv;charset=utf-8;'}));
      a.download = 'student_upload_results_' + new Date().toISOString().slice(0,10) + '.csv';
      a.click();
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && uploadModal.classList.contains('is-open')) closeUpload();
    });
  }

  /* -- Auto-dismiss flash ----------------------------------- */
  var flashEl = document.getElementById('flashAlert');
  if (flashEl) setTimeout(function(){ flashEl.remove(); }, 5000);

})();
</script>