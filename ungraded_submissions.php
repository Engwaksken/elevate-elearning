<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';

// Fetch all courses
$courses_stmt = $conn->prepare("SELECT id, name FROM courses");
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}


$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['id'] ?? 0);
$selected_unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

$units = [];
if ($selected_course_id > 0) {
    $units_stmt = $conn->prepare("SELECT id, name FROM course_unit WHERE course_id = ?");
    $units_stmt->bind_param("i", $selected_course_id);
    $units_stmt->execute();
    $units_result = $units_stmt->get_result();

    while ($unit = $units_result->fetch_assoc()) {
        $units[] = $unit;
    }
}
?>

<!-- Filter Form -->
<form method="GET" class="form-inline mb-3">
    <input type="hidden" name="view" value="graded_submissions">

    <div class="form-group mr-3">
        <label for="course_id" class="mr-2">Select a Course:</label>
        <select name="course_id" id="course_id" class="form-control" onchange="this.form.unit_id.value=''; this.form.submit();">
            <?php foreach ($courses as $course): ?>
                <option value="<?= $course['id']; ?>" <?= $course['id'] == $selected_course_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!empty($units)): ?>
        <div class="form-group mr-3" style="display: none;">
            <label for="unit_id" class="mr-2">Unit:</label>
            <select name="unit_id" id="unit_id" class="form-control" onchange="this.form.submit();">
                <option value="0">All Units</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id']; ?>" <?= $unit['id'] == $selected_unit_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unit['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
</form>

<?php
$sql = "
    SELECT 
        hi.id AS submission_id,
        hi.assignment_id,
        hi.file_uploaded,
        hi.date_handed_in,
        hi.marks_deducted,
        hi.finalmarks,
        hi.feedback AS feedback_comment,
        a.title AS assignment_title,
        a.course_unit,
        a.date_due,
        cu.name AS unit_name,
        CONCAT(u.fname, ' ', u.lname) AS student_name,
        u.unique_id AS student_id
    FROM handed_in hi
    JOIN assignments a ON hi.assignment_id = a.id
    JOIN course_unit cu ON a.course_unit = cu.id
    JOIN users u ON hi.user_id = u.unique_id
    WHERE cu.course_id = ? AND finalmarks <= 0 OR hi.finalmarks IS NULL
";

$params = [$selected_course_id];
$types = "i";

if ($selected_unit_id > 0) {
    $sql .= " AND cu.id = ?";
    $params[] = $selected_unit_id;
    $types .= "i";
}

$sql .= " ORDER BY a.date_due DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<?php if ($result->num_rows > 0): ?>

<!-- Scoped DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<!-- Custom Controls -->
<div class="row mb-3">
    <div class="col-md-3">
        <label for="entriesSelect">Show entries:</label>
        <select id="entriesSelect" class="form-control form-control-sm">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="-1">All</option>
        </select>
    </div>
    <div class="col-md-3">
        <label>Export:</label>
        <div id="exportButtons"></div>
    </div>
    <div class="col-md-6">
        <label for="searchInput">Search:</label>
        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search assignments...">
    </div>
</div>

<!-- Table -->
<div class="table-responsive">
    <table id="assignmentsTable" class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Unit</th>
                <th>Assignment</th>
                <th>Due Date</th>
                <th>Submitted</th>
                <th>Final Marks</th>
                <th>Deducted</th>
                <th>Feedback</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($row['student_name']); ?></td>
                <td><?= htmlspecialchars($row['unit_name']); ?></td>
                <td><?= htmlspecialchars($row['assignment_title']); ?></td>
               <td><?= date('M d, Y h:i A', strtotime($row['date_due'])); ?></td>
<td><?= date('M d, Y h:i A', strtotime($row['date_handed_in'])); ?></td>

                <td>
                    <?php if ($row['finalmarks'] !== null): ?>
                        <span class="badge bg-success"><?= htmlspecialchars($row['finalmarks']); ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning">Pending</span>
                    <?php endif; ?>
                </td>
                <td><?= $row['marks_deducted'] !== null ? htmlspecialchars($row['marks_deducted']) : '-'; ?></td>
                <td>
                    <?php if ($row['feedback_comment']): ?>
                        <span title="<?= htmlspecialchars($row['feedback_comment']); ?>">
                            <?= substr(htmlspecialchars($row['feedback_comment']), 0, 50) . '...'; ?>
                        </span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <a href="index.php?view=edit_submission&course_id=<?= $row['course_unit']; ?>&id=<?= $row['submission_id']; ?>" class="btn btn-sm btn-info" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="index.php?view=delete_submission&course_id=<?= $row['course_unit']; ?>&id=<?= $row['submission_id']; ?>" 
                       class="btn btn-sm btn-danger ms-1" 
                       onclick="return confirm('Are you sure you want to delete this submission?');" 
                       title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- DataTables JS (scoped to this page) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- Inline JS for table setup -->
<script>
$(document).ready(function () {
    const table = $('#assignmentsTable').DataTable({
        dom: 'Brtip', // Remove default length/search UI
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Assignments Export',
                className: 'btn btn-success btn-sm',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdfHtml5',
                title: 'Assignments Export',
                orientation: 'landscape',
                pageSize: 'A4',
                className: 'btn btn-danger btn-sm',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
            {
                extend: 'csvHtml5',
                title: 'Assignments Export',
                className: 'btn btn-primary btn-sm',
                text: '<i class="fas fa-file-csv"></i> CSV'
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 20, 50, 100, -1], [10, 20, 50, 100, "All"]],
        ordering: true,
        info: true,
        paging: true,
        responsive: true,
        language: {
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            emptyTable: "No data available in table",
            zeroRecords: "No matching records found",
            paginate: {
                next: "Next",
                previous: "Previous"
            }
        },
        initComplete: function () {
            table.buttons().container().appendTo('#exportButtons');
        }
    });

    // Hook up search
    $('#searchInput').on('keyup', function () {
        table.search(this.value).draw();
    });

    // Hook up entries per page
    $('#entriesSelect').on('change', function () {
        const val = parseInt($(this).val());
        table.page.len(val).draw();
    });
});
</script>

<?php else: ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> No handed-in assignments found for the selected course.
</div>
<?php endif; ?>
