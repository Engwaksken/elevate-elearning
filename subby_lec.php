<?php
function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_GET['hjadvhagsdjhsagsdhg']) || (empty($_GET['piouglv']) && empty($_GET['assignment_id']))) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid Access. <a href="?view=home" class="link-maroon-bold">Return Home</a></div>';
    exit;
}

$module     = (int)$_GET['hjadvhagsdjhsagsdhg'];
$assignment = isset($_GET['piouglv']) ? (int)$_GET['piouglv'] : (int)$_GET['assignment_id'];

$s = $conn->prepare("SELECT id, name, course_id FROM course_unit WHERE id=? LIMIT 1");
$s->bind_param("i", $module);
$s->execute();
$module_data = $s->get_result()->fetch_assoc();
$s->close();

if (!$module_data) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid Module ID.</div>';
    exit;
}

$course_id = (int)$module_data['course_id'];

$s = $conn->prepare("SELECT name FROM courses WHERE id=? LIMIT 1");
$s->bind_param("i", $course_id);
$s->execute();
$course_row = $s->get_result()->fetch_assoc();
$s->close();

if (!$course_row) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid Course ID.</div>';
    exit;
}

$course_name = $course_row['name'];

$s = $conn->prepare("SELECT title FROM assignments WHERE id=? LIMIT 1");
$s->bind_param("i", $assignment);
$s->execute();
$ass_row = $s->get_result()->fetch_assoc();
$s->close();

if (!$ass_row) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid Assignment ID.</div>';
    exit;
}

$ass_name = $ass_row['title'];

$s = $conn->prepare("
    SELECT
        hi.id,
        hi.user_id,
        hi.assignment_id,
        hi.file_uploaded,
        hi.date_handed_in,
        hi.marks_deducted,
        hi.finalmarks,
        u.fname,
        u.lname
    FROM handed_in hi
    INNER JOIN users u ON u.unique_id = hi.user_id
    WHERE hi.assignment_id = ?
    ORDER BY hi.date_handed_in DESC
");
$s->bind_param("i", $assignment);
$s->execute();
$handed_result = $s->get_result();

$submissions = [];
while ($r = $handed_result->fetch_assoc()) {
    $submissions[] = $r;
}
$s->close();

$total    = count($submissions);
$graded   = count(array_filter($submissions, fn($r) => $r['finalmarks'] !== null && $r['finalmarks'] !== ''));
$ungraded = $total - $graded;
$rate     = $total > 0 ? round(($graded / $total) * 100) : 0;
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e($ass_name); ?> - Submissions</title>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">

<style>
.export-btn {
    border: none;
    border-radius: 10px;
    padding: 9px 13px;
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    gap: 7px;
    align-items: center;
    color: #fff;
}

.export-btn.csv {
    background: #198754;
}

.export-btn.pdf {
    background: #b02a37;
}

.pagination-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding: 14px 0 0;
}

.pagination-controls {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}

.pg-btn {
    border: 1px solid rgba(0,0,0,.14);
    background: #fff;
    color: #333;
    border-radius: 8px;
    padding: 7px 10px;
    font-size: .78rem;
    cursor: pointer;
}

.pg-btn.active {
    background: var(--maroon);
    color: #fff;
    border-color: var(--maroon);
}

.pg-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.per-page {
    border: 1px solid rgba(0,0,0,.14);
    border-radius: 8px;
    padding: 7px 10px;
    font-size: .78rem;
    background: #fff;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    html,
    body {
        background: #fff !important;
    }

    body * {
        visibility: hidden !important;
    }

    #pdfTableOnly,
    #pdfTableOnly * {
        visibility: visible !important;
    }

    #pdfTableOnly {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: #fff !important;
    }

    #pdfTableOnly .no-print,
    #pdfTableOnly .row-actions,
    #pdfTableOnly th:last-child,
    #pdfTableOnly td:last-child {
        display: none !important;
    }

    #pdfTableOnly table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        color: #000 !important;
    }

    #pdfTableOnly th,
    #pdfTableOnly td {
        border: 1px solid #444;
        padding: 6px;
        color: #000 !important;
        background: #fff !important;
        vertical-align: top;
    }

    #pdfTableOnly th {
        background: #f1f1f1 !important;
        font-weight: 700;
    }

    #pdfTableOnly a {
        color: #000 !important;
        text-decoration: none !important;
    }

    #pdfTableOnly i {
        display: none !important;
    }
}
</style>
</head>

<body>

<nav class="topbar no-print">
  <div class="topbar-left">
    <h1><i class="fas fa-inbox"></i> <?php echo e($course_name); ?> &mdash; Submissions</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=my_courses">My Courses</a></li>
      <li class="active">Submissions</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a"); ?></div>
</nav>

<main class="page page-wide">

  <div class="page-heading no-print">
    <div>
      <h2><em><?php echo e($ass_name); ?></em></h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-book"></i> <?php echo e($course_name); ?></span>
        <span class="meta-pill"><i class="fas fa-puzzle-piece"></i> <?php echo e($module_data['name']); ?></span>
        <span class="meta-pill"><i class="fas fa-users"></i> <?php echo $total; ?> submission<?php echo $total !== 1 ? 's' : ''; ?></span>
      </div>
    </div>
  </div>

  <div class="stats no-print" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-icon total"><i class="fas fa-inbox"></i></div>
      <div><div class="sv"><?php echo $total; ?></div><div class="sl">Total</div></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon done"><i class="fas fa-check-circle"></i></div>
      <div><div class="sv green"><?php echo $graded; ?></div><div class="sl">Graded</div></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon cancel"><i class="fas fa-hourglass-half"></i></div>
      <div><div class="sv red"><?php echo $ungraded; ?></div><div class="sl">Pending</div></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon prog"><i class="fas fa-percentage"></i></div>
      <div><div class="sv gold"><?php echo $rate; ?>%</div><div class="sl">Graded Rate</div></div>
    </div>
  </div>

  <div class="tbl-card">

    <div class="tbl-toolbar no-print">
      <span class="tbl-title">
        <i class="fas fa-list-alt"></i> Handed-In Submissions
        <span class="count-pill" style="margin-left:4px;"><?php echo $total; ?></span>
      </span>

      <div class="toolbar-actions">
        <button type="button" class="export-btn csv" onclick="exportCSV()">
          <i class="fas fa-file-csv"></i> Export CSV
        </button>

        <button type="button" class="export-btn pdf" onclick="exportPDF()">
          <i class="fas fa-file-pdf"></i> Export PDF
        </button>

        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="subSearch" placeholder="Search students...">
        </div>
      </div>
    </div>

    <div class="tbl-scroll" id="pdfTableOnly">
      <?php if (empty($submissions)): ?>

        <div class="empty" style="border:none; border-radius:0;">
          <i class="fas fa-inbox"></i>
          <p>No submissions yet for this assignment.<br>
             <a href="?view=my_courses" class="link-maroon-bold">Go to My Courses</a>
          </p>
        </div>

      <?php else: ?>

      <table class="app-table" id="subTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Uploaded File</th>
            <th>Date Submitted</th>
            <th class="th-gold">Deducted</th>
            <th class="th-gold">Final Marks</th>
            <th class="th-gold no-print">Actions</th>
          </tr>
        </thead>

        <tbody>
        <?php foreach ($submissions as $i => $sub):
          $sid         = (int)$sub['id'];
          $studentName = trim(($sub['fname'] ?? '') . ' ' . ($sub['lname'] ?? ''));
          $deducted    = (float)($sub['marks_deducted'] ?? 0);
          $finalmarks  = $sub['finalmarks'];
          $hasMarks    = ($finalmarks !== null && $finalmarks !== '');
          $filename    = trim((string)($sub['file_uploaded'] ?? ''));
          $fileLink    = $filename !== '' ? 'assignments/ahwyefgywefpiwegyfp/' . rawurlencode($filename) : '';
          $subDate     = !empty($sub['date_handed_in']) ? date('d M Y, h:i a', strtotime($sub['date_handed_in'])) : '-';
          $trClass     = $hasMarks ? 'row-graded' : 'row-submitted';

          $editJson = e(json_encode([
            'id'         => $sid,
            'student'    => $studentName,
            'module'     => $module,
            'assignment' => $assignment,
            'marks'      => $finalmarks,
          ]));
        ?>
        <tr id="row-<?php echo $sid; ?>"
            class="<?php echo $trClass; ?>"
            data-search="<?php echo strtolower(e($studentName . ' ' . $filename . ' ' . $subDate . ' ' . $finalmarks)); ?>">

          <td class="cell-num"><?php echo $i + 1; ?></td>

          <td>
            <div style="display:flex; align-items:center; gap:9px;">
              <div style="width:30px; height:30px; border-radius:50%; background:var(--maroon-dim);
                          display:grid; place-items:center; color:var(--maroon); font-size:.76rem; flex-shrink:0;">
                <i class="fas fa-user"></i>
              </div>
              <span class="cell-title" style="max-width:160px;"><?php echo e($studentName); ?></span>
            </div>
          </td>

          <td>
            <?php if ($fileLink): ?>
              <a href="<?php echo e($fileLink); ?>" target="_blank" rel="noopener" class="open-link">
                <i class="fas fa-file-download"></i>
                <?php echo e(strlen($filename) > 28 ? substr($filename, 0, 28) . '...' : $filename); ?>
              </a>
            <?php else: ?>
              <span class="cell-na-i">No file</span>
            <?php endif; ?>
          </td>

          <td class="chat-date"><?php echo e($subDate); ?></td>

          <td>
            <?php if ($deducted > 0): ?>
              <span class="marks-deduct">-<?php echo e($deducted); ?></span>
            <?php else: ?>
              <span class="s-badge s-graded" style="font-size:.65rem;">0</span>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($hasMarks): ?>
              <span class="marks-final"><?php echo e((string)$finalmarks); ?></span>
            <?php else: ?>
              <span class="s-badge s-pending">Pending</span>
            <?php endif; ?>
          </td>

          <td class="no-print">
            <div class="row-actions">
              <?php if (!$hasMarks): ?>
                <button type="button" class="ia-btn ia-dl btn-open-addmarks" title="Add Marks"
                        data-module="<?php echo $module; ?>"
                        data-assignment="<?php echo $assignment; ?>"
                        data-id="<?php echo $sid; ?>"
                        data-student="<?php echo e($studentName); ?>">
                  <i class="fas fa-plus"></i>
                </button>
              <?php else: ?>
                <button type="button" class="ia-btn ia-view btn-open-edit" title="Edit Marks"
                        data-modal="<?php echo $editJson; ?>">
                  <i class="fas fa-pen"></i>
                </button>

                <button type="button" class="ia-btn" title="Delete Marks"
                        style="border-color:var(--gold); color:var(--gold);"
                        onclick="openDeleteMarksModal(<?php echo $sid; ?>, '<?php echo e(addslashes($studentName)); ?>', <?php echo $module; ?>, <?php echo $assignment; ?>)">
                  <i class="fas fa-eraser"></i>
                </button>
              <?php endif; ?>

              <button type="button" class="ia-btn ia-del" title="Delete Submission"
                      onclick="openDeleteSubModal(<?php echo $sid; ?>, '<?php echo e(addslashes($studentName)); ?>', <?php echo $module; ?>, <?php echo $assignment; ?>)">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>

        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php endif; ?>
    </div>

    <div class="pagination-wrap no-print">
      <div>
        <select id="perPage" class="per-page">
          <option value="10">10 per page</option>
          <option value="25" selected>25 per page</option>
          <option value="50">50 per page</option>
          <option value="100">100 per page</option>
          <option value="all">All</option>
        </select>
      </div>

      <div class="pagination-controls" id="paginationControls"></div>

      <span id="tableCount" style="font-size:.76rem; color:var(--muted);">
        <?php echo $total; ?> submission<?php echo $total !== 1 ? 's' : ''; ?>
      </span>
    </div>

    <div class="tbl-footer no-print">
      <div class="legend">
        <div class="legend-item"><span class="legend-dot legend-dot-green"></span> Graded</div>
        <div class="legend-item"><span class="legend-dot legend-dot-gold"></span> Pending / Submitted</div>
      </div>
    </div>

  </div>
</main>

<!-- ADD MARKS MODAL -->
<div class="modal-overlay" id="addMarksModal">
  <div class="modal-box modal-box-sm">
    <form method="POST" action="scripts/addmarks.php">
      <div class="modal-head">
        <h3><i class="fas fa-star"></i> Award Marks</h3>
        <button type="button" class="modal-close" onclick="closeModal('addMarksModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body" style="display:flex; flex-direction:column; gap:14px;">
        <div class="info-badge" style="margin-bottom:0;">
          <i class="fas fa-tasks"></i>
          <span><?php echo e($ass_name); ?> &mdash; <strong id="addMarksStudentName" class="text-maroon"></strong></span>
        </div>

        <input type="hidden" name="themod" id="themod">
        <input type="hidden" name="theass" id="theass">
        <input type="hidden" name="theaid" id="theaid">

        <div class="ctrl-field">
          <label class="ctrl-label" for="addMarksInput">
            <i class="fas fa-star"></i> Marks <span style="color:var(--red)">*</span>
          </label>
          <input type="number" id="addMarksInput" name="marks" class="form-input" min="1" max="100" placeholder="e.g. 75" required>
        </div>

        <div class="ctrl-field">
          <label class="ctrl-label" for="addMarksComment">
            <i class="fas fa-comment-alt"></i> Comment <span style="color:var(--red)">*</span>
          </label>
          <textarea id="addMarksComment" name="shortnotes" rows="3" class="form-input" placeholder="Enter feedback for the student..." required></textarea>
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeModal('addMarksModal')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-export">
          <i class="fas fa-check"></i> Submit Marks
        </button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MARKS MODAL -->
<div class="modal-overlay" id="editMarksModal">
  <div class="modal-box modal-box-sm">
    <form method="POST" action="scripts/edit_marks.php">
      <div class="modal-head">
        <h3><i class="fas fa-pen"></i> Edit Marks</h3>
        <button type="button" class="modal-close" onclick="closeModal('editMarksModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body" style="display:flex; flex-direction:column; gap:14px;">
        <div class="info-badge" style="margin-bottom:0;">
          <i class="fas fa-user"></i>
          Editing marks for <strong id="editMarksStudentName" class="text-maroon"></strong>
        </div>

        <input type="hidden" name="id" id="editMarksId">
        <input type="hidden" name="mymodule" id="editMarksModule">
        <input type="hidden" name="assignment_id" id="editMarksAssignment">

        <div class="ctrl-field">
          <label class="ctrl-label" for="editMarksInput">
            <i class="fas fa-star"></i> New Marks <span style="color:var(--red)">*</span>
          </label>
          <input type="number" id="editMarksInput" name="marks" class="form-input" min="0" placeholder="Enter new marks" required>
        </div>

        <div class="ctrl-field">
          <label class="ctrl-label" for="editMarksComment">
            <i class="fas fa-comment-alt"></i> New Comment
          </label>
          <input type="text" id="editMarksComment" name="comment" class="form-input" placeholder="Enter updated comment">
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeModal('editMarksModal')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-export">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE MARKS MODAL -->
<div class="modal-overlay" id="deleteMarksModal">
  <div class="modal-box modal-box-sm">
    <form method="POST" action="scripts/delete_marks.php">
      <div class="modal-head">
        <h3><i class="fas fa-eraser"></i> Delete Marks</h3>
        <button type="button" class="modal-close" onclick="closeModal('deleteMarksModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="del-warn">
          <i class="fas fa-exclamation-triangle del-warn-icon"></i>
          <div class="del-warn-text">
            Are you sure you want to delete marks for
            <strong id="delMarksStudentName"></strong>?
          </div>
        </div>

        <p class="del-note">
          <i class="fas fa-info-circle"></i> This removes the marks record. The submission file remains intact.
        </p>

        <input type="hidden" name="id" id="delMarksId">
        <input type="hidden" name="assignment_id" id="delMarksAssignment">
        <input type="hidden" name="mymodule" id="delMarksModule">
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteMarksModal')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-modal-delete">
          <i class="fas fa-eraser"></i> Delete Marks
        </button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE SUBMISSION MODAL -->
<div class="modal-overlay" id="deleteSubModal">
  <div class="modal-box modal-box-sm">
    <form method="POST" action="scripts/delete_submission.php">
      <div class="modal-head">
        <h3><i class="fas fa-trash-alt"></i> Delete Submission</h3>
        <button type="button" class="modal-close" onclick="closeModal('deleteSubModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="del-warn">
          <i class="fas fa-exclamation-triangle del-warn-icon"></i>
          <div class="del-warn-text">
            Are you sure you want to delete the submission for
            <strong id="delSubStudentName"></strong>?
          </div>
        </div>

        <p class="del-note">
          <i class="fas fa-info-circle"></i>
          This removes the uploaded file so the student can re-submit.
        </p>

        <input type="hidden" name="id" id="delSubId">
        <input type="hidden" name="assignment_id" id="delSubAssignment">
        <input type="hidden" name="mymodule" id="delSubModule">
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteSubModal')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-modal-delete">
          <i class="fas fa-trash-alt"></i> Delete Submission
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add('is-open');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('is-open');
  document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(function(el) {
  el.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.is-open').forEach(function(el) {
      closeModal(el.id);
    });
  }
});

document.querySelectorAll('.btn-open-addmarks').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('themod').value = this.dataset.module;
    document.getElementById('theass').value = this.dataset.assignment;
    document.getElementById('theaid').value = this.dataset.id;
    document.getElementById('addMarksStudentName').textContent = this.dataset.student;
    document.getElementById('addMarksInput').value = '';
    document.getElementById('addMarksComment').value = '';
    openModal('addMarksModal');
  });
});

document.querySelectorAll('.btn-open-edit').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var d = JSON.parse(this.dataset.modal);

    document.getElementById('editMarksId').value = d.id;
    document.getElementById('editMarksModule').value = d.module;
    document.getElementById('editMarksAssignment').value = d.assignment;
    document.getElementById('editMarksStudentName').textContent = d.student;
    document.getElementById('editMarksInput').value = d.marks ?? '';
    document.getElementById('editMarksComment').value = '';

    openModal('editMarksModal');
  });
});

function openDeleteMarksModal(sid, student, module, assignment) {
  document.getElementById('delMarksStudentName').textContent = student;
  document.getElementById('delMarksId').value = sid;
  document.getElementById('delMarksModule').value = module;
  document.getElementById('delMarksAssignment').value = assignment;
  openModal('deleteMarksModal');
}

function openDeleteSubModal(sid, student, module, assignment) {
  document.getElementById('delSubStudentName').textContent = student;
  document.getElementById('delSubId').value = sid;
  document.getElementById('delSubModule').value = module;
  document.getElementById('delSubAssignment').value = assignment;
  openModal('deleteSubModal');
}

/* Search + Pagination */
let currentPage = 1;
let filteredRows = [];

const table = document.getElementById('subTable');
const searchEl = document.getElementById('subSearch');
const perPageEl = document.getElementById('perPage');
const paginationControls = document.getElementById('paginationControls');
const tableCount = document.getElementById('tableCount');

function getAllRows() {
  if (!table) return [];
  return Array.from(table.querySelectorAll('tbody tr'));
}

function applyFilters() {
  const q = searchEl ? searchEl.value.toLowerCase().trim() : '';
  const rows = getAllRows();

  filteredRows = rows.filter(function(row) {
    return !q || row.dataset.search.includes(q);
  });

  currentPage = 1;
  renderTable();
}

function renderTable() {
  const rows = getAllRows();
  rows.forEach(row => row.style.display = 'none');

  let perPage = perPageEl ? perPageEl.value : '25';
  const total = filteredRows.length;

  if (perPage === 'all') {
    filteredRows.forEach(row => row.style.display = '');
    renderPagination(1, 1);
  } else {
    perPage = parseInt(perPage, 10);

    const totalPages = Math.max(1, Math.ceil(total / perPage));

    if (currentPage > totalPages) {
      currentPage = totalPages;
    }

    const start = (currentPage - 1) * perPage;
    const end = start + perPage;

    filteredRows.slice(start, end).forEach(row => row.style.display = '');
    renderPagination(totalPages, currentPage);
  }

  if (tableCount) {
    tableCount.textContent = total + ' submission' + (total !== 1 ? 's' : '');
  }
}

function renderPagination(totalPages, activePage) {
  if (!paginationControls) return;

  paginationControls.innerHTML = '';

  const prev = document.createElement('button');
  prev.className = 'pg-btn';
  prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
  prev.disabled = activePage <= 1;
  prev.onclick = function() {
    currentPage--;
    renderTable();
  };
  paginationControls.appendChild(prev);

  for (let i = 1; i <= totalPages; i++) {
    if (totalPages > 7 && i !== 1 && i !== totalPages && Math.abs(i - activePage) > 1) {
      if (i === 2 || i === totalPages - 1) {
        const dots = document.createElement('span');
        dots.textContent = '...';
        dots.style.padding = '0 5px';
        paginationControls.appendChild(dots);
      }
      continue;
    }

    const btn = document.createElement('button');
    btn.className = 'pg-btn' + (i === activePage ? ' active' : '');
    btn.textContent = i;
    btn.onclick = function() {
      currentPage = i;
      renderTable();
    };
    paginationControls.appendChild(btn);
  }

  const next = document.createElement('button');
  next.className = 'pg-btn';
  next.innerHTML = '<i class="fas fa-chevron-right"></i>';
  next.disabled = activePage >= totalPages;
  next.onclick = function() {
    currentPage++;
    renderTable();
  };
  paginationControls.appendChild(next);
}

if (searchEl) {
  searchEl.addEventListener('input', applyFilters);
}

if (perPageEl) {
  perPageEl.addEventListener('change', function() {
    currentPage = 1;
    renderTable();
  });
}

filteredRows = getAllRows();
renderTable();

/* CSV Export */
function exportCSV() {
  if (!table) return;

  const rows = filteredRows.length ? filteredRows : getAllRows();

  let csv = [];
  csv.push([
    'No.',
    'Student',
    'Uploaded File',
    'Date Submitted',
    'Deducted',
    'Final Marks'
  ]);

  rows.forEach(function(row, index) {
    const cells = row.querySelectorAll('td');

    csv.push([
      index + 1,
      cleanText(cells[1]?.innerText || ''),
      cleanText(cells[2]?.innerText || ''),
      cleanText(cells[3]?.innerText || ''),
      cleanText(cells[4]?.innerText || ''),
      cleanText(cells[5]?.innerText || '')
    ]);
  });

  const csvContent = csv.map(r => r.map(csvEscape).join(',')).join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });

  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);

  link.href = url;
  link.download = 'assignment-submissions-<?php echo date("Ymd-His"); ?>.csv';

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  URL.revokeObjectURL(url);
}

/* Landscape PDF - Table Only */
function exportPDF() {
  if (!table) return;

  const rows = getAllRows();
  const oldDisplay = rows.map(row => row.style.display);
  const oldTitle = document.title;

  rows.forEach(function(row) {
    if (filteredRows.includes(row)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });

  document.title = 'assignment-submissions-landscape';

  window.print();

  setTimeout(function() {
    rows.forEach(function(row, i) {
      row.style.display = oldDisplay[i];
    });

    document.title = oldTitle;
    renderTable();
  }, 500);
}

function cleanText(text) {
  return String(text || '').replace(/\s+/g, ' ').trim();
}

function csvEscape(value) {
  value = String(value ?? '');
  value = value.replace(/"/g, '""');
  return '"' + value + '"';
}
</script>

</body>
</html>