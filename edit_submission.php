<?php 
session_start();
require 'scripts/connector.php';
require_once __DIR__ . '/scripts/course_content_helpers.php';

// Staff may access all courses; instructors must own the submission's course.
[$current_user_id, $current_role] = cc_current_user();
cc_require_instructor_role($current_role);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid submission ID.");
}

$submission_id = (int)$_GET['id'];

// Fetch submission details first to get course_id
$stmt = $conn->prepare("
    SELECT hi.*, CONCAT(u.fname, ' ', u.lname) AS student_name, a.title AS assignment_title, a.course_unit, cu.course_id AS access_course_id
    FROM handed_in hi
    JOIN users u ON hi.user_id = u.unique_id
    JOIN assignments a ON hi.assignment_id = a.id
    JOIN course_unit cu ON cu.id = a.course_unit
    WHERE hi.id = ?
");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

if (!$submission) {
    die("Submission not found.");
}

$course_id = $submission['course_unit'];
if (!cc_check_instructor_course_access($conn, $current_user_id, $current_role, (int)$submission['access_course_id'])) {
    http_response_code(403);
    die("Access denied.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finalmarks = $_POST['finalmarks'];
    $marks_deducted = $_POST['marks_deducted'];
    $feedback = trim($_POST['feedback']);

    $stmt = $conn->prepare("UPDATE handed_in SET finalmarks = ?, marks_deducted = ?, feedback = ? WHERE id = ?");
    $stmt->bind_param("ddsi", $finalmarks, $marks_deducted, $feedback, $submission_id);

    if ($stmt->execute()) {
        // Redirect using the dynamic course_id
        header("Location: ../elearning/?view=all_assignments&course_id=$course_id&msg=updated");
        exit;
    } else {
        $error = "Failed to update submission.";
    }
}
?>



<div class="wrapper wrapper-content">
    <div class="row">
        <div class="middle-box animated fadeInDown" style="margin: 0 auto; max-width: 600px;">

<h3>Edit Submission</h3>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="form-group mb-3">
        <label>Student:</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($submission['student_name']); ?>" readonly>
    </div>

    <div class="form-group mb-3">
        <label>Assignment:</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($submission['assignment_title']); ?>" readonly>
    </div>

    <div class="form-group mb-3">
        <label>Final Marks:</label>
        <input type="number" name="finalmarks" step="0.01" class="form-control" value="<?= htmlspecialchars($submission['finalmarks']); ?>" required>
    </div>

    <div class="form-group mb-3">
        <label>Marks Deducted:</label>
        <input type="number" name="marks_deducted" step="0.01" class="form-control" value="<?= htmlspecialchars($submission['marks_deducted']); ?>" required>
    </div>

    <div class="form-group mb-3">
        <label>Feedback:</label>
        <textarea name="feedback" class="form-control" rows="4"><?= htmlspecialchars($submission['feedback']); ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Update Submission</button>
    <a href="./?view=all_assignments" class="btn btn-secondary">Cancel</a>
</form>


