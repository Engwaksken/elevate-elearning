<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Database connection not available.</div>';
    return;
}

$cx      = isset($cx) ? (int)$cx : (isset($_GET['course']) ? (int)$_GET['course'] : 0);
$intaken = isset($intaken) ? (int)$intaken : (isset($_GET['ausydfgasuidgipppuwqu']) ? (int)$_GET['ausydfgasuidgipppuwqu'] : 0);

$systemname = $systemname ?? '';
$user_uid   = (string)($_SESSION[$systemname . "leido"] ?? ($_SESSION['unique_id'] ?? ''));

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| Existing notes and assignments for reuse
|--------------------------------------------------------------------------
| These are used when instructor chooses "Use Existing" instead of uploading.
| The backend add_notes.php / add_assignments.php should copy the selected row
| into the selected intake.
*/
$existingNotes = [];
$existingAssignments = [];

if ($cx > 0 && $user_uid !== '') {
    $stmtEN = $conn->prepare("
        SELECT 
            n.id,
            n.title,
            n.course_unit,
            n.intake,
            n.document,
            IFNULL(n.content_type, 'file') AS content_type,
            IFNULL(n.notes_url, '') AS notes_url,
            IFNULL(cu.name, 'Module') AS module_name,
            IFNULL(i.name, 'No Intake') AS intake_name
        FROM course_unit_notes n
        INNER JOIN course_unit cu ON cu.id = n.course_unit
        LEFT JOIN intakes i ON i.id = n.intake
        WHERE cu.course_id = ?
          AND n.uploaded_by = ?
          AND (n.dummy IS NULL OR n.dummy != 'yes')
        ORDER BY n.id DESC
        LIMIT 300
    ");
    if ($stmtEN) {
        $stmtEN->bind_param("is", $cx, $user_uid);
        $stmtEN->execute();
        $resEN = $stmtEN->get_result();
        while ($resEN && $row = $resEN->fetch_assoc()) {
            $existingNotes[] = $row;
        }
        $stmtEN->close();
    }

    $stmtEA = $conn->prepare("
        SELECT 
            a.id,
            a.title,
            a.course_unit,
            a.intake,
            a.document,
            IFNULL(a.content_type, 'file') AS content_type,
            IFNULL(a.assignment_url, '') AS assignment_url,
            IFNULL(cu.name, 'Module') AS module_name,
            IFNULL(i.name, 'No Intake') AS intake_name
        FROM assignments a
        INNER JOIN course_unit cu ON cu.id = a.course_unit
        LEFT JOIN intakes i ON i.id = a.intake
        WHERE cu.course_id = ?
          AND a.uploaded_by = ?
          AND (a.dummy IS NULL OR a.dummy != 'yes')
        ORDER BY a.id DESC
        LIMIT 300
    ");
    if ($stmtEA) {
        $stmtEA->bind_param("is", $cx, $user_uid);
        $stmtEA->execute();
        $resEA = $stmtEA->get_result();
        while ($resEA && $row = $resEA->fetch_assoc()) {
            $existingAssignments[] = $row;
        }
        $stmtEA->close();
    }
}
?>

<!-- ADD NOTE MODAL -->
<div class="modal-overlay" id="addNoteModal" aria-hidden="true">
  <div class="modal-box">
    <form method="post" action="scripts/add_notes.php" enctype="multipart/form-data">
      <div class="modal-head">
        <h3><i class="fas fa-file-alt"></i> Add Notes</h3>
        <button type="button" class="modal-close" data-close-modal="addNoteModal"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="course_unit" id="add_note_unit">
        <input type="hidden" name="course_id" value="<?php echo (int)$cx; ?>">
        <input type="hidden" name="intake" value="<?php echo (int)$intaken; ?>">

        <div class="form-field mb-3">
          <label><i class="fas fa-heading"></i> Note Title</label>
          <input type="text" name="title" id="add_note_title" class="form-input">
          <div class="field-hint">Required for new upload or URL. Existing notes can keep their original title.</div>
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-align-left"></i> Short Message</label>
          <textarea name="short_message" id="add_note_message" class="form-input" rows="5"></textarea>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-universal-access"></i> Note Status</legend>
            <label class="radio-card"><input type="radio" name="status" value="active" checked> <span><strong>Active</strong> - Students can access this note</span></label>
            <label class="radio-card"><input type="radio" name="status" value="inactive"> <span><strong>Inactive</strong> - Hide this note from students</span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-users"></i> Student Class Mode</legend>
            <label class="radio-card"><input type="radio" name="class_mode" value="online"> <span><strong>Online</strong> - Only online students</span></label>
            <label class="radio-card"><input type="radio" name="class_mode" value="physical"> <span><strong>Physical</strong> - Only physical students</span></label>
            <label class="radio-card"><input type="radio" name="class_mode" value="both" checked> <span><strong>Both</strong> - Online and physical students</span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-link"></i> Notes Source</legend>
            <label class="radio-card"><input type="radio" name="content_type" value="file" checked data-content-toggle="note"> <span><strong>Upload File</strong></span></label>
            <label class="radio-card"><input type="radio" name="content_type" value="url" data-content-toggle="note"> <span><strong>Add URL</strong></span></label>
            <label class="radio-card"><input type="radio" name="content_type" value="existing" data-content-toggle="note"> <span><strong>Use Existing Note</strong> - Assign already uploaded note to this intake</span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3 note-file-wrap">
          <label><i class="fas fa-file"></i> Upload File</label>
          <input type="file" name="document" class="form-input">
        </div>

        <div class="form-field mb-3 note-url-wrap" style="display:none;">
          <label><i class="fas fa-link"></i> Notes URL</label>
          <input type="url" name="notes_url" class="form-input" placeholder="https://example.com/notes">
        </div>

        <div class="form-field mb-3 note-existing-wrap" style="display:none;">
          <label><i class="fas fa-database"></i> Select Existing Note</label>
          <select name="existing_note_id" class="form-input">
            <option value="">- Select existing note -</option>
            <?php foreach ($existingNotes as $n): ?>
              <option value="<?php echo (int)$n['id']; ?>">
                <?php echo h($n['title']); ?> - <?php echo h($n['module_name']); ?> / <?php echo h($n['intake_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($existingNotes)): ?>
            <div class="field-hint text-danger"><i class="fas fa-info-circle"></i> No existing notes found for this course.</div>
          <?php else: ?>
            <div class="field-hint"><i class="fas fa-info-circle"></i> This will copy the selected note record into the currently selected intake.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" data-close-modal="addNoteModal"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" name="add_note" class="btn-export"><i class="fas fa-save"></i> Save Note</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD ASSIGNMENT MODAL -->
<div class="modal-overlay" id="addAssignmentModal" aria-hidden="true">
  <div class="modal-box">
    <form method="post" action="scripts/add_assignments.php" enctype="multipart/form-data">
      <div class="modal-head">
        <h3><i class="fas fa-tasks"></i> Add Assignment</h3>
        <button type="button" class="modal-close" data-close-modal="addAssignmentModal"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="course_unit" id="add_assignment_unit">
        <input type="hidden" name="course_id" value="<?php echo (int)$cx; ?>">
        <input type="hidden" name="intake" value="<?php echo (int)$intaken; ?>">

        <div class="form-field mb-3">
          <label><i class="fas fa-heading"></i> Assignment Title</label>
          <input type="text" name="title" id="add_assignment_title" class="form-input">
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-align-left"></i> Instructions</label>
          <textarea name="short_message" id="add_assignment_message" class="form-input" rows="5"></textarea>
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-calendar"></i> Due Date</label>
          <input type="datetime-local" name="date_due" id="add_assignment_due" class="form-input">
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-star"></i> Marks</label>
          <input type="number" name="marks" id="add_assignment_marks" class="form-input" step="0.01" min="0" value="0">
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-universal-access"></i> Assignment Status</legend>
            <label class="radio-card"><input type="radio" name="status" value="active" checked> <span><strong>Active</strong> - Students can access this assignment</span></label>
            <label class="radio-card"><input type="radio" name="status" value="inactive"> <span><strong>Inactive</strong> - Hide this assignment from students</span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-users"></i> Student Class Mode</legend>
            <label class="radio-card"><input type="radio" name="class_mode" value="online"> <span><strong>Online</strong> - Only online students</span></label>
            <label class="radio-card"><input type="radio" name="class_mode" value="physical"> <span><strong>Physical</strong> - Only physical students</span></label>
            <label class="radio-card"><input type="radio" name="class_mode" value="both" checked> <span><strong>Both</strong> - Online and physical students</span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-link"></i> Assignment Source</legend>
            <label class="radio-card"><input type="radio" name="content_type" value="file" checked data-content-toggle="assignment"> <span><strong>Upload File</strong></span></label>
            <label class="radio-card"><input type="radio" name="content_type" value="url" data-content-toggle="assignment"> <span><strong>Add URL</strong></span></label>
            <label class="radio-card"><input type="radio" name="content_type" value="existing" data-content-toggle="assignment"> <span><strong>Use Existing Assignment</strong> - Assign already uploaded assignment to this intake</span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3 assignment-file-wrap">
          <label><i class="fas fa-file"></i> Upload File</label>
          <input type="file" name="document" class="form-input">
        </div>

        <div class="form-field mb-3 assignment-url-wrap" style="display:none;">
          <label><i class="fas fa-link"></i> Assignment URL</label>
          <input type="url" name="assignment_url" class="form-input" placeholder="https://example.com/assignment">
        </div>

        <div class="form-field mb-3 assignment-existing-wrap" style="display:none;">
          <label><i class="fas fa-database"></i> Select Existing Assignment</label>
          <select name="existing_assignment_id" class="form-input">
            <option value="">- Select existing assignment -</option>
            <?php foreach ($existingAssignments as $a): ?>
              <option value="<?php echo (int)$a['id']; ?>">
                <?php echo h($a['title']); ?> - <?php echo h($a['module_name']); ?> / <?php echo h($a['intake_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($existingAssignments)): ?>
            <div class="field-hint text-danger"><i class="fas fa-info-circle"></i> No existing assignments found for this course.</div>
          <?php else: ?>
            <div class="field-hint"><i class="fas fa-info-circle"></i> This will copy the selected assignment record into the currently selected intake.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" data-close-modal="addAssignmentModal"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" name="add_assignment" class="btn-export"><i class="fas fa-save"></i> Save Assignment</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT ASSIGNMENT MODAL -->
<div class="modal-overlay" id="editAssignmentModal" aria-hidden="true">
  <div class="modal-box">
    <form method="post" action="scripts/update_assignment.php" enctype="multipart/form-data">
      <div class="modal-head">
        <h3><i class="fas fa-pen"></i> Edit Assignment</h3>
        <button type="button" class="modal-close" data-close-modal="editAssignmentModal"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="assignment_id" id="edit_assignment_id">
        <input type="hidden" name="course_unit" id="edit_assignment_unit">
        <input type="hidden" name="course_id" value="<?php echo (int)$cx; ?>">
        <input type="hidden" name="intake" value="<?php echo (int)$intaken; ?>">

        <div class="form-field mb-3">
          <label><i class="fas fa-heading"></i> Assignment Title</label>
          <input type="text" name="title" id="edit_assignment_title" class="form-input" required>
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-align-left"></i> Instructions</label>
          <textarea name="short_message" id="edit_assignment_message" class="form-input" rows="5"></textarea>
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-calendar"></i> Due Date</label>
          <input type="datetime-local" name="date_due" id="edit_assignment_due" class="form-input">
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-universal-access"></i> Assignment Status</legend>
            <label class="radio-card"><input type="radio" name="status" id="edit_assignment_status_active" value="active" required> <span><strong>Active</strong></span></label>
            <label class="radio-card"><input type="radio" name="status" id="edit_assignment_status_inactive" value="inactive" required> <span><strong>Inactive</strong></span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-users"></i> Student Class Mode</legend>
            <label class="radio-card"><input type="radio" name="class_mode" id="edit_assignment_class_online" value="online" required> <span><strong>Online</strong></span></label>
            <label class="radio-card"><input type="radio" name="class_mode" id="edit_assignment_class_physical" value="physical" required> <span><strong>Physical</strong></span></label>
            <label class="radio-card"><input type="radio" name="class_mode" id="edit_assignment_class_both" value="both" required> <span><strong>Both</strong></span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-link"></i> Assignment Source</legend>
            <label class="radio-card"><input type="radio" name="content_type" value="file" id="edit_assignment_content_file" data-content-toggle="assignment"> <span><strong>File</strong></span></label>
            <label class="radio-card"><input type="radio" name="content_type" value="url" id="edit_assignment_content_url" data-content-toggle="assignment"> <span><strong>URL</strong></span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3 assignment-file-wrap">
          <label><i class="fas fa-file"></i> Replace File Optional</label>
          <input type="file" name="document" class="form-input">
          <div class="field-hint"><i class="fas fa-info-circle"></i> Leave empty to keep existing file.</div>
        </div>

        <div class="form-field mb-3 assignment-url-wrap" style="display:none;">
          <label><i class="fas fa-link"></i> Assignment URL</label>
          <input type="url" name="assignment_url" id="edit_assignment_url" class="form-input">
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" data-close-modal="editAssignmentModal"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" name="update_assignment" class="btn-export"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT NOTE MODAL -->
<div class="modal-overlay" id="editNoteModal" aria-hidden="true">
  <div class="modal-box">
    <form method="post" action="scripts/update_notes.php" enctype="multipart/form-data">
      <div class="modal-head">
        <h3><i class="fas fa-pen"></i> Edit Note</h3>
        <button type="button" class="modal-close" data-close-modal="editNoteModal"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="note_id" id="edit_note_id">
        <input type="hidden" name="course_unit" id="edit_note_unit">
        <input type="hidden" name="course_id" value="<?php echo (int)$cx; ?>">
        <input type="hidden" name="intake" value="<?php echo (int)$intaken; ?>">

        <div class="form-field mb-3">
          <label><i class="fas fa-heading"></i> Note Title</label>
          <input type="text" name="title" id="edit_note_title" class="form-input" required>
        </div>

        <div class="form-field mb-3">
          <label><i class="fas fa-align-left"></i> Short Message</label>
          <textarea name="short_message" id="edit_note_message" class="form-input" rows="5"></textarea>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-universal-access"></i> Note Status</legend>
            <label class="radio-card"><input type="radio" name="status" id="edit_note_status_active" value="active" required> <span><strong>Active</strong></span></label>
            <label class="radio-card"><input type="radio" name="status" id="edit_note_status_inactive" value="inactive" required> <span><strong>Inactive</strong></span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-users"></i> Student Class Mode</legend>
            <label class="radio-card"><input type="radio" name="class_mode" id="edit_note_class_online" value="online" required> <span><strong>Online</strong></span></label>
            <label class="radio-card"><input type="radio" name="class_mode" id="edit_note_class_physical" value="physical" required> <span><strong>Physical</strong></span></label>
            <label class="radio-card"><input type="radio" name="class_mode" id="edit_note_class_both" value="both" required> <span><strong>Both</strong></span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3">
          <fieldset class="access-fieldset">
            <legend><i class="fas fa-link"></i> Note Source</legend>
            <label class="radio-card"><input type="radio" name="content_type" value="file" id="edit_note_content_file" data-content-toggle="note"> <span><strong>File</strong></span></label>
            <label class="radio-card"><input type="radio" name="content_type" value="url" id="edit_note_content_url" data-content-toggle="note"> <span><strong>URL</strong></span></label>
          </fieldset>
        </div>

        <div class="form-field mb-3 note-file-wrap">
          <label><i class="fas fa-file"></i> Replace File Optional</label>
          <input type="file" name="document" class="form-input">
          <div class="field-hint"><i class="fas fa-info-circle"></i> Leave empty to keep existing file.</div>
        </div>

        <div class="form-field mb-3 note-url-wrap" style="display:none;">
          <label><i class="fas fa-link"></i> Notes URL</label>
          <input type="url" name="notes_url" id="edit_note_url" class="form-input">
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" data-close-modal="editNoteModal"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" name="update_note" class="btn-export"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE ASSIGNMENT MODAL -->
<div class="modal-overlay" id="deleteAssignmentModal" aria-hidden="true">
  <div class="modal-box modal-sm">
    <form method="post" action="scripts/delete_assignment.php">
      <div class="modal-head">
        <h3><i class="fas fa-trash"></i> Delete Assignment</h3>
        <button type="button" class="modal-close" data-close-modal="deleteAssignmentModal"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="assignment_id" id="delete_assignment_id">
        <input type="hidden" name="document" id="delete_assignment_document">
        <p>Are you sure you want to delete <strong id="delete_assignment_title">this assignment</strong>?</p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" data-close-modal="deleteAssignmentModal">Cancel</button>
        <button type="submit" name="delete_assignment" class="ia-btn ia-del"><i class="fas fa-trash"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE NOTE MODAL -->
<div class="modal-overlay" id="deleteNoteModal" aria-hidden="true">
  <div class="modal-box modal-sm">
    <form method="post" action="scripts/delete_note.php">
      <div class="modal-head">
        <h3><i class="fas fa-trash"></i> Delete Note</h3>
        <button type="button" class="modal-close" data-close-modal="deleteNoteModal"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="note_id" id="delete_note_id">
        <input type="hidden" name="document" id="delete_note_document">
        <p>Are you sure you want to delete <strong id="delete_note_title">this note</strong>?</p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" data-close-modal="deleteNoteModal">Cancel</button>
        <button type="submit" name="delete_note" class="ia-btn ia-del"><i class="fas fa-trash"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- NOTE ANALYTICS MODAL -->
<div class="modal-overlay" id="noteAnalyticsModal" aria-hidden="true">
  <div class="modal-box" style="max-width:860px;">
    <div class="modal-head">
      <h3><i class="fas fa-chart-line"></i> Note Analytics - <span id="noteAnalyticsTitle" style="font-weight:400;font-style:italic;"></span></h3>
      <button type="button" class="modal-close" data-close-modal="noteAnalyticsModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div id="noteAnalyticsLoading" style="text-align:center; padding:40px; display:none;">
        <div class="spin active" style="width:32px;height:32px;border-width:3px;border-color:var(--border);border-top-color:var(--maroon);margin:0 auto 12px;"></div>
        <p style="color:var(--muted);font-size:.84rem;">Loading analytics...</p>
      </div>

      <div id="noteAnalyticsError" class="app-alert app-alert-danger" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i> <span></span>
      </div>

      <div id="noteAnalyticsContent">
        <div class="stats" style="margin-bottom:20px;">
          <div class="stat-card"><div class="stat-icon total"><i class="fas fa-users"></i></div><div><div class="sv" id="stat_total_students">0</div><div class="sl">Total Students</div></div></div>
          <div class="stat-card"><div class="stat-icon prog"><i class="fas fa-percentage"></i></div><div><div class="sv gold" id="stat_avg_progress">0%</div><div class="sl">Avg Progress</div></div></div>
          <div class="stat-card"><div class="stat-icon done"><i class="fas fa-check-circle"></i></div><div><div class="sv green" id="stat_completed">0</div><div class="sl">Completed</div></div></div>
          <div class="stat-card"><div class="stat-icon cancel"><i class="fas fa-hourglass-start"></i></div><div><div class="sv red" id="stat_not_started">0</div><div class="sl">Not Started</div></div></div>
        </div>

        <div class="tbl-card">
          <div class="tbl-toolbar"><span class="tbl-title"><i class="fas fa-users"></i> Student Reading Progress</span></div>
          <div class="tbl-scroll">
            <table class="app-table">
              <thead>
                <tr>
                  <th><i class="fas fa-user"></i> Student Name</th>
                  <th class="th-gold"><i class="fas fa-percentage"></i> Progress</th>
                  <th><i class="fas fa-clock"></i> Last Accessed</th>
                </tr>
              </thead>
              <tbody id="analyticsStudentsBody">
                <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--muted);">No analytics loaded yet.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn-modal-cancel" data-close-modal="noteAnalyticsModal"><i class="fas fa-times"></i> Close</button>
    </div>
  </div>
</div>

<!-- NOTE COMMENTS MODAL -->
<div class="modal-overlay" id="noteCommentsModal" aria-hidden="true">
  <div class="modal-box" style="max-width:740px;">
    <div class="modal-head" style="background:linear-gradient(135deg,var(--gold) 0%,var(--gold-lt) 100%);border-bottom-color:var(--maroon);">
      <h3 style="color:#fff;"><i class="fas fa-comments"></i> Comments - <span id="noteCommentsTitle" style="font-weight:400;font-style:italic;"></span></h3>
      <button type="button" class="modal-close" data-close-modal="noteCommentsModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div id="noteCommentsLoading" style="text-align:center;padding:40px;display:none;">
        <div class="spin active" style="width:32px;height:32px;border-width:3px;border-color:var(--border);border-top-color:var(--gold);margin:0 auto 12px;"></div>
        <p style="color:var(--muted);font-size:.84rem;">Loading comments...</p>
      </div>

      <div id="noteCommentsError" class="app-alert app-alert-danger" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i> <span></span>
      </div>

      <div id="noteCommentsContent">
        <div id="commentsContainer" style="display:flex;flex-direction:column;gap:12px;">
          <div class="empty"><i class="fas fa-comments"></i><p>No comments loaded yet.</p></div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn-modal-cancel" data-close-modal="noteCommentsModal"><i class="fas fa-times"></i> Close</button>
    </div>
  </div>
</div>

<style>
.modal-overlay{
  position:fixed;
  inset:0;
  z-index:99999;
  display:none;
  align-items:flex-start;
  justify-content:center;
  padding:28px 12px;
  background:rgba(15,23,42,.60);
  overflow-y:auto;
}

.modal-overlay.is-open,
.modal-overlay.active{
  display:flex !important;
}

.modal-box{
  width:100%;
  max-width:720px;
  max-height:calc(100vh - 56px);
  display:flex;
  flex-direction:column;
  background:#fff;
  border-radius:16px;
  box-shadow:0 20px 60px rgba(15,23,42,.25);
  overflow:hidden;
}

.modal-box.modal-sm{ max-width:420px; }

.modal-head{
  flex:0 0 auto;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  padding:16px 20px;
  border-bottom:1px solid #e5e7eb;
  background:#fff;
}

.modal-head h3{
  margin:0;
  font-size:17px;
  font-weight:800;
  color:#7f111b;
}

.modal-close{
  border:0;
  background:#f3f4f6;
  color:#111827;
  width:34px;
  height:34px;
  border-radius:50%;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
}

.modal-close:hover{ background:#fee2e2; color:#991b1b; }

.modal-body{
  flex:1 1 auto;
  overflow-y:auto;
  padding:18px 20px;
  min-height:0;
  max-height:calc(100vh - 180px);
}

.modal-foot{
  flex:0 0 auto;
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:10px;
  padding:14px 20px;
  border-top:1px solid #e5e7eb;
  background:#fff;
}

.access-fieldset{
  border:1px solid #d9e2ec;
  border-radius:12px;
  padding:14px;
  background:#f8fafc;
}

.access-fieldset legend{
  width:auto;
  padding:0 8px;
  font-size:14px;
  font-weight:700;
  color:#1f2937;
  margin-bottom:10px;
  border:0;
}

.radio-card{
  display:flex;
  gap:10px;
  align-items:flex-start;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:10px;
  padding:10px 12px;
  margin-bottom:8px;
  cursor:pointer;
}

.radio-card:last-child{ margin-bottom:0; }
.radio-card input[type="radio"]{ margin-top:4px; }
.radio-card span{ line-height:1.4; color:#374151; }
.radio-card:hover{ border-color:#1ab394; background:#f0fdf9; }

.field-hint{
  font-size:.75rem;
  color:#6b7280;
  margin-top:6px;
}

.text-danger{ color:#991b1b !important; }

@media (max-width:640px){
  .modal-overlay{ padding:12px 8px; }
  .modal-box{ max-height:calc(100vh - 24px); border-radius:12px; }
  .modal-body{ max-height:calc(100vh - 155px); padding:14px; }
  .modal-foot{ flex-direction:column; align-items:stretch; }
  .modal-foot button{ width:100%; }
}
</style>