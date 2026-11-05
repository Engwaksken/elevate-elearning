<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'scripts/connector.php';


$systemname = 'Elevate Her 360';


if (empty($_SESSION[$systemname . "loggedin"]) || empty($_SESSION[$systemname . "email"])) {
    echo '<div class="alert alert-danger">Please log in. <a href="?view=login">Login</a></div>';
    exit;
}

$current_user_id = $_SESSION[$systemname . "leido"];


$courses_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name
    FROM courses c
    JOIN course_unit cu ON cu.course_id = c.id
    JOIN assignments a ON a.course_unit = cu.id
    WHERE a.uploaded_by = ?
");
$courses_stmt->bind_param("i", $current_user_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (count($courses) ? $courses[0]['id'] : 0);


$units = [];
if ($selected_course_id > 0) {
    $units_stmt = $conn->prepare("
        SELECT cu.id, cu.name
        FROM course_unit cu
        JOIN assignments a ON a.course_unit = cu.id
        WHERE cu.course_id = ? AND a.uploaded_by = ?
    ");
    $units_stmt->bind_param("ii", $selected_course_id, $current_user_id);
    $units_stmt->execute();
    $units_result = $units_stmt->get_result();

    while ($u = $units_result->fetch_assoc()) {
        $units[] = $u;
    }
}

$selected_unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

$intakesStmt = $conn->prepare("
    SELECT intakes.id AS intake_id, intakes.name AS intake_name, branches.name AS branch_name
    FROM intakes
    INNER JOIN branches ON intakes.branch_id = branches.id
    ORDER BY intakes.name
");
$intakesStmt->execute();
$intakesResult = $intakesStmt->get_result();

$intakes = [];
while ($row = $intakesResult->fetch_assoc()) {
    $intakes[] = $row;
}

$selectedIntakeId = isset($_GET['intake_id']) ? (int)$_GET['intake_id'] : (count($intakes) ? $intakes[0]['intake_id'] : 0);
?>

<form id="filterForm" method="GET" class="form-inline mb-3">
    <input type="hidden" name="view" value="my_assignments">

  
    <div class="form-group mr-3">
        <label for="intake_id" class="mr-2">Intake:</label>
        <select name="intake_id" id="intake_id" class="form-control" onchange="this.form.submit();">
            <option value="">Choose Intake</option>
            <?php foreach ($intakes as $intake): ?>
                <option value="<?= (int)$intake['intake_id'] ?>" <?= $intake['intake_id'] == $selectedIntakeId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($intake['intake_name'] . ' - ' . $intake['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

  
    <div class="form-group mr-3">
        <label for="course_id" class="mr-2">Course:</label>
        <select name="course_id" id="course_id" class="form-control">
            <?php foreach ($courses as $course): ?>
                <option value="<?= $course['id']; ?>" <?= ($course['id'] == $selected_course_id ? 'selected' : ''); ?>>
                    <?= htmlspecialchars($course['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!empty($units)): ?>
        <div class="form-group mr-3" style="display:none;">
            <label for="unit_id" class="mr-2">Unit:</label>
            <select name="unit_id" id="unit_id" class="form-control">
                <option value="0">All Units</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id']; ?>" <?= ($unit['id'] == $selected_unit_id ? 'selected' : ''); ?>>
                        <?= htmlspecialchars($unit['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
</form>


<div id="ajaxContent"></div>

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<script>
$(document).ready(function() {

    function loadAssignments() {
        const formData = $('#filterForm').serialize();

        $.ajax({
            url: 'fetch_assignments.php',
            type: 'GET',
            data: formData,
            beforeSend: function() {
                $('#ajaxContent').html('<p>Loading data...</p>');
            },
            success: function(response) {
                $('#ajaxContent').html(response);
                initializeDataTable();
            },
            error: function() {
                $('#ajaxContent').html('<div class="alert alert-danger">Error loading data</div>');
            }
        });
    }

    $('#filterForm select').on('change', loadAssignments);
    loadAssignments(); // initial load

    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('#assignmentsTable')) {
            $('#assignmentsTable').DataTable().destroy();
        }

        $('#assignmentsTable').DataTable({
            paging: true,
            pageLength: 10,
            responsive: true,
            searching: true,
            info: true, // display summary
            order: [[1, 'asc']],
            columnDefs: [
                { targets: 0, orderable: false }, // # column
                { targets: -1, orderable: false } // Actions
            ]
        });
    }

});
</script>
