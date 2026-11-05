
function toDatetimeLocal(value) {
 
  if (!value) return "";
  if (value.includes("T")) return value.slice(0, 16);
  return value.replace(" ", "T").slice(0, 16);
}

// ---------- Assignments ----------
window.openEditAssignmentModal = function (data) {
  $("#edit_assignment_id").val(data.id || "");
  $("#edit_assignment_module_id").val(data.module_id || "");
  $("#edit_assignment_title").val(data.title || "");
  $("#edit_assignment_message").val(data.message || "");
  $("#edit_assignment_due").val(toDatetimeLocal(data.due || ""));
  $("#editAssignmentAlert").html("");
  $("#editAssignmentModal").modal("show");
};

window.openDeleteAssignmentModal = function (data) {
  $("#delete_assignment_id").val(data.id || "");
  $("#delete_assignment_file").val(data.file || "");
  $("#delete_assignment_title").text(data.title || "");
  $("#deleteAssignmentAlert").html("");
  $("#deleteAssignmentModal").modal("show");
};

// ---------- Notes ----------
window.openEditNoteModal = function (data) {
  $("#edit_note_id").val(data.id || "");
  $("#edit_note_module_id").val(data.module_id || "");
  $("#edit_note_title").val(data.title || "");
  $("#edit_note_message").val(data.message || "");
  $("#editNoteAlert").html("");
  $("#editNoteModal").modal("show");
};

window.openDeleteNoteModal = function (data) {
  $("#delete_note_id").val(data.id || "");
  $("#delete_note_file").val(data.file || "");
  $("#delete_note_title").text(data.title || "");
  $("#deleteNoteAlert").html("");
  $("#deleteNoteModal").modal("show");
};
