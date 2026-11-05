<?php
// submission_details.php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

$systemname = 'Elevate Her 360';

/* -------------------- AUTH -------------------- */
if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo '<div class="alert alert-danger">Please log in. <a href="?view=login">Login</a></div>';
    exit;
}

$current_user_id = $_SESSION[$systemname . "leido"] ?? '';
if ($current_user_id === '') {
    echo '<div class="alert alert-danger">Session error. Please log in again.</div>';
    exit;
}

/* -------------------- INPUTS -------------------- */
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if ($assignment_id <= 0 || $submission_id <= 0) {
    echo '<div class="alert alert-danger">Invalid request. Missing assignment/submission IDs.</div>';
    exit;
}

/* -------------------- FETCH SUBMISSION (STUDENT-OWNED ONLY) -------------------- */
$sql = "
    SELECT
        hi.id AS submission_id,
        hi.user_id,
        hi.assignment_id,
        hi.file_uploaded,
        hi.date_handed_in,
        hi.marks_deducted,
        hi.finalmarks,
        hi.feedback AS feedback_comment,

        a.title AS assignment_title,
        a.marks AS total_marks,
        a.date_due,
        a.course_unit,

        cu.name AS unit_name,
        c.name AS course_name,

        u.fname, u.lname, u.email,
        COALESCE(ct.name,'N/A') AS class_type_name
    FROM handed_in hi
    JOIN assignments a ON a.id = hi.assignment_id
    JOIN course_unit cu ON cu.id = a.course_unit
    JOIN courses c ON c.id = cu.course_id
    JOIN users u ON u.unique_id = hi.user_id
    LEFT JOIN class_type ct ON ct.id = u.class_type_id
    WHERE hi.id = ?
      AND hi.assignment_id = ?
      AND hi.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo '<div class="alert alert-danger">Query prepare failed: ' . htmlspecialchars($conn->error) . '</div>';
    exit;
}

$stmt->bind_param("iis", $submission_id, $assignment_id, $current_user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo '<div class="alert alert-danger">Submission not found or you are not allowed to view it.</div>';
    exit;
}

/* -------------------- DERIVED -------------------- */
$student_name = htmlspecialchars(trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')));
$course_name  = htmlspecialchars($row['course_name'] ?? '');
$unit_name    = htmlspecialchars($row['unit_name'] ?? '');
$assignment_title = htmlspecialchars($row['assignment_title'] ?? '');
$class_type_name  = htmlspecialchars($row['class_type_name'] ?? 'N/A');

$date_due = $row['date_due'] ? date('M d, Y g:i A', strtotime($row['date_due'])) : '-';
$date_handed_in = $row['date_handed_in'] ? date('M d, Y g:i A', strtotime($row['date_handed_in'])) : '-';

$total_marks = $row['total_marks'] ?? '';
$finalmarks = $row['finalmarks'];
$marks_deducted = $row['marks_deducted'];

$graded = ($finalmarks !== null && $finalmarks !== '');
$submitted = !empty($row['submission_id']);

if ($graded) {
    $status = "Graded";
    $status_class = "badge bg-success";
} elseif ($submitted) {
    $status = "Submitted";
    $status_class = "badge bg-warning text-dark";
} else {
    $status = "Pending";
    $status_class = "badge bg-danger";
}

$file_uploaded = $row['file_uploaded'] ?? '';
$file_base = $file_uploaded ? basename($file_uploaded) : '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Submission Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- If your project already loads Bootstrap elsewhere, you can remove these -->
    <link rel="stylesheet" href="css/bootstrap.min.css">

    <style>
        .info-card { border:1px solid #e9ecef; border-radius:12px; }
        .info-card .card-header { background:#fc7f10; color:#fff; border-radius:12px 12px 0 0; }
        .kv { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px dashed #e9ecef; }
        .kv:last-child { border-bottom:none; }
        .kv .k { color:#6c757d; }
        .kv .v { font-weight:600; text-align:right; }
        .feedback-box { white-space:pre-wrap; }
        .pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#f1f3f5; font-size:12px; }
    </style>
</head>

<body class="bg-light">
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0">Submission Details</h4>
            <div class="text-muted small">Assignment ID: <?= (int)$assignment_id; ?> - Submission ID: <?= (int)$submission_id; ?></div>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">
                ? Back
            </a>
            <?php if ($file_uploaded): ?>
                <a class="btn btn-success btn-sm" href="download_file?submission_id=<?= (int)$submission_id; ?>">
                    <i class="fas fa-download"></i> Download File
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- LEFT: Summary -->
        <div class="col-lg-5">
            <div class="card info-card shadow-sm">
                <div class="card-header">
                    <strong><?= $assignment_title; ?></strong>
                    <div class="small opacity-75"><?= $course_name; ?> - <?= $unit_name; ?></div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="<?= $status_class; ?>"><?= $status; ?></span>
                        <span class="pill">Class Type: <?= $class_type_name; ?></span>
                    </div>

                    <div class="kv">
                        <div class="k">Student</div>
                        <div class="v"><?= $student_name; ?></div>
                    </div>
                    <div class="kv">
                        <div class="k">Email</div>
                        <div class="v"><?= htmlspecialchars($row['email'] ?? ''); ?></div>
                    </div>
                    <div class="kv">
                        <div class="k">Due Date</div>
                        <div class="v"><?= htmlspecialchars($date_due); ?></div>
                    </div>
                    <div class="kv">
                        <div class="k">Submitted</div>
                        <div class="v"><?= htmlspecialchars($date_handed_in); ?></div>
                    </div>
                    <div class="kv">
                        <div class="k">Assignment Marks</div>
                        <div class="v"><?= htmlspecialchars((string)$total_marks); ?></div>
                    </div>
                    <div class="kv">
                        <div class="k">Final Marks</div>
                        <div class="v">
                            <?= ($graded ? '<span class="text-primary">'.htmlspecialchars((string)$finalmarks).'</span>' : '<span class="text-muted">-</span>'); ?>
                        </div>
                    </div>
                    <div class="kv">
                        <div class="k">Marks Deducted</div>
                        <div class="v">
                            <?php
                            $md = (is_null($marks_deducted) || $marks_deducted === '') ? 0 : (float)$marks_deducted;
                            if ($md > 0) {
                                echo '<span class="text-danger">-' . htmlspecialchars((string)$marks_deducted) . '</span>';
                            } else {
                                echo '<span class="text-muted">0</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="text-muted small mb-1">Uploaded File</div>
                        <?php if ($file_uploaded): ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file me-1"></i>
                                    <strong><?= htmlspecialchars($file_base); ?></strong>
                                    <div class="text-muted small" title="<?= htmlspecialchars($file_uploaded); ?>">
                                        <?= htmlspecialchars($file_uploaded); ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No file uploaded</div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- RIGHT: Feedback -->
        <div class="col-lg-7">
            <div class="card info-card shadow-sm">
                <div class="card-header">
                    <strong>Feedback</strong>
                </div>
                <div class="card-body">
                    <?php if ($graded && !empty(trim((string)$row['feedback_comment']))): ?>
                        <div class="feedback-box border rounded p-3 bg-white">
                            <?= nl2br(htmlspecialchars((string)$row['feedback_comment'])); ?>
                        </div>
                    <?php elseif ($graded): ?>
                        <div class="alert alert-info mb-0">No feedback provided.</div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            This submission is not graded yet. Feedback will appear here once grading is done.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Optional: quick actions -->
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="./?view=my_assignment&course_id=<?= (int)$selected_course_id ?? 0; ?>">
                    Back to My Assignments
                </a>
            </div>
        </div>
    </div>

</div>

<!-- If your project already loads bootstrap/js/fontawesome elsewhere, you can remove these -->
<script src="js/bootstrap.min.js"></script>
</body>
</html>
