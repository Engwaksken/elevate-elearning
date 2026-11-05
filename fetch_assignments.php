<?php
require 'scripts/connector.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$systemname = 'Elevate Her 360';

if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Please log in.</div>';
    exit;
}

$current_user_id = $_SESSION[$systemname . "leido"];

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$unit_id   = isset($_GET['unit_id'])   ? (int)$_GET['unit_id']   : 0;

/* -- Stats ------------------------------------------------- */
$stats_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN hi.finalmarks IS NOT NULL THEN 1 ELSE 0 END) AS graded,
        SUM(CASE WHEN hi.finalmarks IS NULL     THEN 1 ELSE 0 END) AS ungraded
    FROM handed_in hi
    INNER JOIN assignments a  ON hi.assignment_id = a.id
    INNER JOIN course_unit cu ON a.course_unit    = cu.id
    WHERE a.uploaded_by = ?";

$types  = "i";
$params = [$current_user_id];
if ($course_id) { $stats_sql .= " AND cu.course_id = ?"; $types .= "i"; $params[] = $course_id; }
if ($unit_id)   { $stats_sql .= " AND cu.id = ?";        $types .= "i"; $params[] = $unit_id; }

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total    = (int)($stats['total']    ?? 0);
$graded   = (int)($stats['graded']   ?? 0);
$ungraded = (int)($stats['ungraded'] ?? 0);
$pct      = $total > 0 ? round(($graded / $total) * 100) : 0;

/* -- Submissions ------------------------------------------- */
$sql = "
    SELECT
        hi.id           AS submission_id,
        hi.finalmarks,
        hi.marks_deducted,
        hi.feedback,
        hi.date_handed_in,
        a.title         AS assignment_title,
        a.date_due,
        cu.name         AS unit_name,
        CONCAT(u.fname,' ',u.lname) AS student_name
    FROM handed_in hi
    INNER JOIN assignments a  ON hi.assignment_id = a.id
    INNER JOIN course_unit cu ON a.course_unit     = cu.id
    INNER JOIN users u        ON hi.user_id        = u.unique_id
    WHERE a.uploaded_by = ?";

$types  = "i";
$params = [$current_user_id];
if ($course_id) { $sql .= " AND cu.course_id = ?"; $types .= "i"; $params[] = $course_id; }
if ($unit_id)   { $sql .= " AND cu.id = ?";        $types .= "i"; $params[] = $unit_id; }
$sql .= " ORDER BY hi.date_handed_in DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* -- All rows into array (needed for modal data-attrs) ----- */
$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
?>

<!-- -- Stats strip ---------------------------------------- -->
<div class="stats" style="margin-bottom:20px;">

  <div class="stat-card">
    <div class="stat-icon total"><i class="fas fa-inbox"></i></div>
    <div>
      <div class="sv"><?php echo $total ?></div>
      <div class="sl">Total</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon done"><i class="fas fa-check-circle"></i></div>
    <div>
      <div class="sv green"><?php echo $graded ?></div>
      <div class="sl">Graded</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon cancel"><i class="fas fa-hourglass-half"></i></div>
    <div>
      <div class="sv red"><?php echo $ungraded ?></div>
      <div class="sl">Ungraded</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon prog"><i class="fas fa-percentage"></i></div>
    <div>
      <div class="sv gold"><?php echo $pct ?>%</div>
      <div class="sl">Graded Rate</div>
    </div>
  </div>

</div>

<!-- -- Export bar + search -------------------------------- -->
<div class="tbl-card">

  <div class="tbl-toolbar">
    <span class="tbl-title">
      <i class="fas fa-tasks"></i> Assignment Submissions
      <span class="count-pill" style="margin-left:4px;"><?php echo $total ?></span>
    </span>

    <div class="toolbar-actions">
      <!-- Live search -->
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="subSearch" placeholder="Search submissions…">
      </div>
      <!-- Export buttons -->
      <button id="export-csv" class="btn-export" style="background:var(--green); box-shadow:0 2px 8px rgba(21,111,63,.25);">
        <i class="fas fa-file-csv"></i> CSV
      </button>
      <button id="export-pdf" class="btn-export">
        <i class="fas fa-file-pdf"></i> PDF
      </button>
    </div>
  </div>

  <!-- -- Table -------------------------------------------- -->
  <div class="tbl-scroll">
    <table class="app-table" id="subTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Unit</th>
          <th>Assignment</th>
          <th>Submitted</th>
          <th class="th-gold">Marks</th>
          <th>Deducted</th>
          <th>Feedback</th>
          <th class="th-gold">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="9" style="padding:48px 24px; text-align:center;">
            <div>
              <i class="fas fa-inbox" style="font-size:2rem; color:var(--maroon); opacity:.16; display:block; margin-bottom:8px;"></i>
              <p style="color:var(--muted); font-size:.85rem;">No submissions found.</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $row):
          $isGraded  = $row['finalmarks'] !== null;
          $trClass   = $isGraded ? 'row-graded' : 'row-submitted';
          $subDate   = !empty($row['date_handed_in']) ? date('M d, Y H:i', strtotime($row['date_handed_in'])) : '-';
          $feedback  = htmlspecialchars($row['feedback']      ?? '');
          $student   = htmlspecialchars($row['student_name']  ?? '');
          $unit      = htmlspecialchars($row['unit_name']     ?? '');
          $aTitle    = htmlspecialchars($row['assignment_title'] ?? '');
          $marks     = $row['finalmarks']     ?? null;
          $deducted  = $row['marks_deducted'] ?? null;
          $sid       = (int)$row['submission_id'];

          /* JSON for modal */
          $modalData = htmlspecialchars(json_encode([
            'id'           => $sid,
            'student'      => $row['student_name']     ?? '',
            'assignment'   => $row['assignment_title'] ?? '',
            'finalmarks'   => $marks,
            'marksdeducted'=> $deducted,
            'feedback'     => $row['feedback']         ?? '',
          ]), ENT_QUOTES, 'UTF-8');
        ?>
        <tr id="row-<?php echo $sid ?>" class="<?php echo $trClass ?>"
            data-search="<?php echo strtolower($student . ' ' . $unit . ' ' . $aTitle) ?>">
          <td class="cell-num"><?php echo $i + 1 ?></td>
          <td>
            <div style="display:flex; align-items:center; gap:8px;">
              <div style="width:28px; height:28px; border-radius:50%; background:var(--maroon-dim); display:grid; place-items:center; color:var(--maroon); font-size:.72rem; flex-shrink:0;">
                <i class="fas fa-user"></i>
              </div>
              <span class="cell-title" style="max-width:140px;"><?php echo $student ?></span>
            </div>
          </td>
          <td class="cell-sm"><?php echo $unit ?></td>
          <td>
            <div class="cell-title" style="max-width:160px;"><?php echo $aTitle ?></div>
          </td>
          <td class="chat-date"><?php echo $subDate ?></td>
          <td>
            <?php if ($isGraded): ?>
              <span class="marks-final finalmarks"><?php echo htmlspecialchars((string)$marks) ?></span>
            <?php else: ?>
              <span class="s-badge s-pending finalmarks">Pending</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($deducted !== null && $deducted != 0): ?>
              <span class="marks-deduct"><?php echo htmlspecialchars((string)$deducted) ?></span>
            <?php else: ?>
              <span class="cell-na">-</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($row['feedback'])): ?>
              <div class="fb-wrap">
                <span class="fb-snippet feedback_comment"><?php echo $feedback ?></span>
                <button type="button" class="btn-fb view-feedback-btn"
                        data-feedback="<?php echo $feedback ?>"
                        data-student="<?php echo $student ?>"
                        data-assignment="<?php echo $aTitle ?>">
                  <i class="fas fa-eye"></i> View
                </button>
              </div>
            <?php else: ?>
              <span class="cell-na-i feedback_comment">-</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="row-actions">
              <button type="button" class="ia-btn ia-view edit-btn" title="Grade / Edit"
                      data-modal="<?php echo $modalData ?>">
                <i class="fas fa-pen"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table footer -->
  <div class="tbl-footer">
    <div class="legend">
      <div class="legend-item"><span class="legend-dot legend-dot-green"></span> Graded</div>
      <div class="legend-item"><span class="legend-dot legend-dot-gold"></span> Submitted / Pending</div>
    </div>
    <span id="tableCount" style="font-size:.76rem; color:var(--muted);">
      <?php echo count($rows) ?> submission<?php echo count($rows) !== 1 ? 's' : '' ?>
    </span>
  </div>

</div><!-- /.tbl-card -->


<!-- ------------------------------------------------------
     GRADE / EDIT MODAL
------------------------------------------------------- -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">

    <div class="modal-head">
      <h3><i class="fas fa-pen"></i> Grade Submission</h3>
      <button type="button" class="modal-close" onclick="closeModal('editModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form id="editForm">
      <div class="modal-body" style="display:flex; flex-direction:column; gap:14px;">
        <input type="hidden" name="submission_id" id="edit_submission_id">

        <!-- Read-only context -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="fb-meta-box">
            <div class="fb-meta-label"><i class="fas fa-user icon-gold"></i> Student</div>
            <div id="edit_student" style="font-size:.88rem; color:var(--text); font-weight:500; margin-top:2px;"></div>
          </div>
          <div class="fb-meta-box">
            <div class="fb-meta-label"><i class="fas fa-tasks icon-gold"></i> Assignment</div>
            <div id="edit_assignment" style="font-size:.84rem; color:var(--muted); margin-top:2px;"></div>
          </div>
        </div>

        <!-- Marks row -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="edit_finalmarks">
              <i class="fas fa-star"></i> Final Marks <span style="color:var(--red)">*</span>
            </label>
            <input type="number" step="0.01" min="0" id="edit_finalmarks"
                   name="finalmarks" class="form-input" placeholder="e.g. 85" required>
          </div>
          <div class="ctrl-field">
            <label class="ctrl-label" for="edit_marksdeducted">
              <i class="fas fa-minus-circle"></i> Deducted
            </label>
            <input type="number" step="0.01" min="0" id="edit_marksdeducted"
                   name="marks_deducted" class="form-input" placeholder="e.g. 5">
          </div>
        </div>

        <!-- Feedback -->
        <div class="ctrl-field">
          <label class="ctrl-label" for="edit_feedback">
            <i class="fas fa-comment-alt"></i> Feedback
          </label>
          <textarea id="edit_feedback" name="feedback" rows="4"
                    class="form-input" placeholder="Enter feedback for the student…"></textarea>
        </div>

        <div id="edit_msg" style="min-height:20px;"></div>
      </div><!-- /modal-body -->

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-export">
          <i class="fas fa-save"></i> Save Grade
          <span class="spin" id="editSpin"></span>
        </button>
      </div>
    </form>

  </div>
</div>


<!-- ------------------------------------------------------
     FEEDBACK VIEW MODAL
------------------------------------------------------- -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal-box modal-box-sm">

    <div class="modal-head">
      <h3><i class="fas fa-comment-alt"></i> Feedback</h3>
      <button type="button" class="modal-close" onclick="closeModal('feedbackModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="modal-body">
      <div class="fb-meta-grid">
        <div class="fb-meta-box">
          <div class="fb-meta-label"><i class="fas fa-user icon-gold"></i> Student</div>
          <div class="fb-meta-val" id="fb_student" style="font-size:1rem;"></div>
        </div>
        <div class="fb-meta-box">
          <div class="fb-meta-label"><i class="fas fa-tasks icon-gold"></i> Assignment</div>
          <div class="fb-meta-val" id="fb_assignment" style="font-size:1rem;"></div>
        </div>
      </div>
      <div class="fb-section-label"><i class="fas fa-comment-dots"></i> Feedback Content</div>
      <div class="fb-content" id="fb_content"></div>
    </div>

    <div class="modal-foot">
      <button type="button" class="btn-modal-cancel" onclick="closeModal('feedbackModal')">
        <i class="fas fa-times"></i> Close
      </button>
    </div>

  </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
/* -- Modal helpers --------------------------------------- */
function openModal(id) {
  document.getElementById(id).classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(function(el) {
  el.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape')
    document.querySelectorAll('.modal-overlay.is-open').forEach(function(el) { closeModal(el.id); });
});

/* -- Open Grade/Edit modal ------------------------------- */
document.querySelectorAll('.edit-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var d = JSON.parse(this.dataset.modal);
    document.getElementById('edit_submission_id').value  = d.id;
    document.getElementById('edit_student').textContent  = d.student;
    document.getElementById('edit_assignment').textContent = d.assignment;
    document.getElementById('edit_finalmarks').value     = d.finalmarks   ?? '';
    document.getElementById('edit_marksdeducted').value  = d.marksdeducted ?? '';
    document.getElementById('edit_feedback').value       = d.feedback     ?? '';
    document.getElementById('edit_msg').innerHTML        = '';
    openModal('editModal');
  });
});

/* -- Submit grade form ----------------------------------- */
document.getElementById('editForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var spin    = document.getElementById('editSpin');
  var msgEl   = document.getElementById('edit_msg');
  var submitBtn = this.querySelector('button[type="submit"]');

  spin.classList.add('active');
  submitBtn.disabled = true;
  msgEl.innerHTML    = '';

  var fd = new FormData(this);

  fetch('lec_edit_submission.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      spin.classList.remove('active');
      submitBtn.disabled = false;
      if (res.success) {
        var sid  = res.submission_id;
        var row  = document.getElementById('row-' + sid);
        if (row) {
          row.querySelector('.finalmarks').innerHTML =
            '<span class="marks-final">' + res.finalmarks + '</span>';
          var fbEl = row.querySelector('.feedback_comment');
          if (fbEl) fbEl.textContent = res.feedback || '-';
          row.classList.remove('row-submitted');
          row.classList.add('row-graded');
        }
        closeModal('editModal');
      } else {
        msgEl.innerHTML =
          '<div class="app-alert app-alert-danger" style="margin:0;"><i class="fas fa-times-circle"></i> ' +
          (res.message || 'Update failed.') + '</div>';
      }
    })
    .catch(function() {
      spin.classList.remove('active');
      submitBtn.disabled = false;
      msgEl.innerHTML =
        '<div class="app-alert app-alert-danger" style="margin:0;"><i class="fas fa-wifi"></i> Network error.</div>';
    });
});

/* -- View feedback modal --------------------------------- */
document.querySelectorAll('.view-feedback-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('fb_student').textContent    = this.dataset.student;
    document.getElementById('fb_assignment').textContent = this.dataset.assignment;
    document.getElementById('fb_content').textContent    = this.dataset.feedback;
    openModal('feedbackModal');
  });
});

/* -- Live search ----------------------------------------- */
document.getElementById('subSearch').addEventListener('input', function() {
  var q   = this.value.toLowerCase().trim();
  var vis = 0;
  document.querySelectorAll('#subTable tbody tr[data-search]').forEach(function(r) {
    var match = !q || r.dataset.search.includes(q);
    r.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  document.getElementById('tableCount').textContent =
    vis + ' submission' + (vis !== 1 ? 's' : '');
});

/* -- Export helpers -------------------------------------- */
function getTableData() {
  var data    = [['#','Student','Unit','Assignment','Submitted','Marks','Deducted','Feedback']];
  document.querySelectorAll('#subTable tbody tr[data-search]').forEach(function(tr) {
    if (tr.style.display === 'none') return;
    var cells = tr.querySelectorAll('td');
    var row   = [];
    for (var i = 0; i < 8; i++) row.push((cells[i] ? cells[i].innerText.trim() : ''));
    data.push(row);
  });
  return data;
}

/* CSV export */
document.getElementById('export-csv').addEventListener('click', function() {
  var data = getTableData();
  if (data.length <= 1) { alert('No data to export.'); return; }
  var csv = data.map(function(row) {
    return row.map(function(f) {
      return (f.includes(',') || f.includes('"')) ? '"' + f.replace(/"/g, '""') + '"' : f;
    }).join(',');
  }).join('\n');
  var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  var a    = document.createElement('a');
  a.href   = URL.createObjectURL(blob);
  a.download = 'Assignments_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
});

/* PDF export */
document.getElementById('export-pdf').addEventListener('click', function() {
  var data = getTableData();
  if (data.length <= 1) { alert('No data to export.'); return; }
  var { jsPDF } = window.jspdf;
  var doc = new jsPDF({ orientation: 'landscape' });

  doc.setFontSize(14);
  doc.setTextColor(122, 25, 25);
  doc.text('Assignments Report', 14, 14);
  doc.setFontSize(9);
  doc.setTextColor(107, 114, 128);
  doc.text('Exported: ' + new Date().toLocaleDateString(), 14, 20);

  var colW = [8, 32, 28, 40, 30, 14, 14, 40];
  var y    = 28;
  var lh   = 6;

  data.forEach(function(row, ri) {
    var x = 14;
    if (ri === 0) {
      doc.setFillColor(90, 14, 14);
      doc.rect(14, y - 4, colW.reduce(function(a,b){ return a+b; }, 0), lh + 1, 'F');
      doc.setTextColor(255, 255, 255);
      doc.setFontSize(8);
    } else {
      doc.setTextColor(ri % 2 === 0 ? 246 : 255, ri % 2 === 0 ? 247 : 255, ri % 2 === 0 ? 250 : 255);
      if (ri % 2 === 0) doc.setFillColor(246, 247, 250);
      else              doc.setFillColor(255, 255, 255);
      doc.rect(14, y - 4, colW.reduce(function(a,b){ return a+b; }, 0), lh + 1, 'F');
      doc.setTextColor(24, 24, 46);
      doc.setFontSize(8);
    }
    row.forEach(function(cell, ci) {
      if (ci < colW.length) {
        doc.text(String(cell).substring(0, 20), x + 1, y);
        x += colW[ci];
      }
    });
    y += lh;
    if (y > doc.internal.pageSize.height - 20) { doc.addPage(); y = 20; }
  });

  doc.save('Assignments_' + new Date().toISOString().slice(0,10) + '.pdf');
});
</script>