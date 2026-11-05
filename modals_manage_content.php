
<!-- ===================== EDIT ASSIGNMENT MODAL ===================== -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:700px;">
    <div class="modal-content">
      <form id="editAssignmentForm" method="POST" action="scripts/assignment_update.php" enctype="multipart/form-data">
        <div class="modal-header bg-info text-white">
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-pencil"></i> Edit Assignment</h4>
        </div>

        <div class="modal-body">
          <input type="hidden" name="assignment_id" id="edit_assignment_id">
          <input type="hidden" name="module_id" id="edit_assignment_module_id">
          <input type="hidden" name="intake_id" value="<?php echo (int)$intaken; ?>">

          <div class="form-group">
            <label>Title</label>
            <input type="text" class="form-control" name="title" id="edit_assignment_title" required>
          </div>

          <div class="form-group">
            <label>Short Message</label>
            <textarea class="form-control" rows="4" name="short_message" id="edit_assignment_message" required></textarea>
          </div>

          <div class="form-group">
            <label>Due Date</label>
            <input type="datetime-local" class="form-control" name="date_due" id="edit_assignment_due">
          </div>

          <div class="form-group">
            <label>Replace Document (optional)</label>
            <input type="file" class="form-control" name="document">
            <small class="text-muted">Leave empty to keep the current file.</small>
          </div>

          <div id="editAssignmentAlert"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
          <button type="submit" class="btn btn-info"><i class="fa fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== DELETE ASSIGNMENT MODAL ===================== -->
<div class="modal fade" id="deleteAssignmentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content">
      <form id="deleteAssignmentForm" method="POST" action="scripts/assignment_delete.php">
        <div class="modal-header bg-danger text-white">
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-trash"></i> Delete Assignment</h4>
        </div>

        <div class="modal-body">
          <input type="hidden" name="assignment_id" id="delete_assignment_id">
          <input type="hidden" name="document" id="delete_assignment_file">

          <p style="margin:0;">
            Are you sure you want to delete:
            <strong id="delete_assignment_title"></strong> ?
          </p>

          <div id="deleteAssignmentAlert" style="margin-top:12px;"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== EDIT NOTE MODAL ===================== -->
<div class="modal fade" id="editNoteModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:700px;">
    <div class="modal-content">
      <form id="editNoteForm" method="POST" action="scripts/note_update.php" enctype="multipart/form-data">
        <div class="modal-header bg-warning text-white">
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-pencil"></i> Edit Note</h4>
        </div>

        <div class="modal-body">
          <input type="hidden" name="note_id" id="edit_note_id">
          <input type="hidden" name="module_id" id="edit_note_module_id">
          <input type="hidden" name="intake_id" value="<?php echo (int)$intaken; ?>">

          <div class="form-group">
            <label>Title</label>
            <input type="text" class="form-control" name="title" id="edit_note_title" required>
          </div>

          <div class="form-group">
            <label>Short Message</label>
            <textarea class="form-control" rows="4" name="short_message" id="edit_note_message" required></textarea>
          </div>

          <div class="form-group">
            <label>Replace Document (optional)</label>
            <input type="file" class="form-control" name="document">
            <small class="text-muted">Leave empty to keep the current file.</small>
          </div>

          <div id="editNoteAlert"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== DELETE NOTE MODAL ===================== -->
<div class="modal fade" id="deleteNoteModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content">
      <form id="deleteNoteForm" method="POST" action="scripts/note_delete.php">
        <div class="modal-header bg-danger text-white">
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-trash"></i> Delete Note</h4>
        </div>

        <div class="modal-body">
          <input type="hidden" name="note_id" id="delete_note_id">
          <input type="hidden" name="document" id="delete_note_file">

          <p style="margin:0;">
            Are you sure you want to delete:
            <strong id="delete_note_title"></strong> ?
          </p>

          <div id="deleteNoteAlert" style="margin-top:12px;"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>
