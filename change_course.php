<?php
$uiid = $_GET["id"] ?? '';
$now = date("Y-m-d");
$thisyear = date("Y", strtotime($now));

function cc_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cc_alert($message, $type = 'danger')
{
    $bg = $type === 'success' ? '#41b54a' : '#f44336';

    echo '
    <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
        <tbody>
            <tr>
                <td style="background:' . $bg . '; padding:20px; color:#fff; text-align:center;">
                    ' . cc_e($message) . '
                </td>
            </tr>
        </tbody>
    </table>';
}

if ($uiid === '') {
    cc_alert('HACKING ATTEMPT DETECTED');
    return;
}

$uiid_safe = $conn->real_escape_string($uiid);

/*
|--------------------------------------------------------------------------
| Fetch user safely
|--------------------------------------------------------------------------
*/
$stmtUser = $conn->prepare("SELECT * FROM users WHERE unique_id = ? LIMIT 1");

if (!$stmtUser) {
    cc_alert("Database error: " . $conn->error);
    return;
}

$stmtUser->bind_param("s", $uiid);
$stmtUser->execute();
$userResult = $stmtUser->get_result();

if (!$userResult || $userResult->num_rows < 1) {
    cc_alert('Unknown User');
    $stmtUser->close();
    return;
}

$data = $userResult->fetch_assoc();
$stmtUser->close();

$typox = $data["acc_type"] ?? '';

$route = "";
$registrationTable = "";
$isLecturer = false;
$isStudent = false;

if ($typox === "xtfbry") {
    $route = "scripts/changecourse_lec.php";
    $registrationTable = "course_registration_lecturers";
    $isLecturer = true;
} elseif ($typox === "afgezt") {
    $route = "scripts/changecourse.php";
    $registrationTable = "course_registration";
    $isStudent = true;
} else {
    cc_alert('Course change is only available for Students and Instructors.');
    return;
}

/*
|--------------------------------------------------------------------------
| Fetch registrations
|--------------------------------------------------------------------------
*/
$registrations = [];

$stmtRegs = $conn->prepare("
    SELECT 
        cr.*,
        c.name AS course_name,
        i.name AS intake_name,
        i.date_launching
    FROM {$registrationTable} cr
    LEFT JOIN courses c ON cr.course_id = c.id
    LEFT JOIN intakes i ON cr.intake = i.id
    WHERE cr.user_id = ?
    ORDER BY cr.id DESC
");

if (!$stmtRegs) {
    cc_alert("Registration query error: " . $conn->error);
    return;
}

$stmtRegs->bind_param("s", $uiid);
$stmtRegs->execute();
$regsResult = $stmtRegs->get_result();

if ($regsResult) {
    while ($row = $regsResult->fetch_assoc()) {
        $registrations[] = $row;
    }
}

$stmtRegs->close();

$currentCourseName = "N/A";
$selected_course = "none";

if (!empty($registrations)) {
    $currentCourseName = $registrations[0]['course_name'] ?: "N/A";
    $selected_course = $registrations[0]['course_id'] ?? "none";
}

/*
|--------------------------------------------------------------------------
| Display profile data
|--------------------------------------------------------------------------
*/
$dpc = $data["dp"] ?? "default.jpg";
$dpp = ($dpc === "default.jpg" || trim($dpc) === '')
    ? 'images/default_p_pics/user.png'
    : 'images/user_p_pics/' . $dpc;

$d_email = $data["email"] ?? '';
$d_contact = !empty($data["contact"]) ? $data["contact"] : "N/A";
?>

<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2>Change Course</h2>
        <ol class="breadcrumb">
            <li><a href="?">Home</a></li>
            <li><a href="?view=all_courses">Assign / Change Course</a></li>
            <li class="active"><strong>Assign / Change Course</strong></li>
        </ol>
    </div>
</div>

<div class="wrapper wrapper-content">
    <div class="row">
        <div class="middle-box animated fadeInDown" style="margin-top:0;">

            <?php
            if (isset($_SESSION[$systemname . "errormsg"])) {
                cc_alert($_SESSION[$systemname . "errormsg"]);
                unset($_SESSION[$systemname . "errormsg"]);
            } elseif (isset($_SESSION[$systemname . "successmsg"])) {
                cc_alert($_SESSION[$systemname . "successmsg"], 'success');
                unset($_SESSION[$systemname . "successmsg"]);
            }
            ?>

            <div class="contact-box">
                <div class="col-sm-4 text-center">
                    <img alt="image" class="img-circle m-t-xs img-responsive" src="<?= cc_e($dpp); ?>">
                    <div class="m-t-xs font-bold"><?= cc_e($currentCourseName); ?></div>
                </div>

                <div class="col-sm-8">
                    <h3>
                        <strong><?= cc_e(($data["fname"] ?? '') . ' ' . ($data["lname"] ?? '')); ?></strong>
                    </h3>

                    <p>
                        <i class="fa fa-envelope-o"></i>
                        <?= cc_e($d_email); ?>
                    </p>

                    <address>
                        <strong>Other Details</strong><br>
                        <?= cc_e($data["gender"] ?? ''); ?><br>
                        <?= cc_e($data["country"] ?? ''); ?><br>
                        <abbr title="Phone" class="fa fa-phone"></abbr>
                        <?= cc_e($d_contact); ?>
                    </address>
                </div>

                <div class="clearfix"></div>
            </div>

            <div class="ibox-content">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course</th>
                            <th>Intake</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($registrations)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No course registrations found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $num = 1; ?>
                        <?php foreach ($registrations as $a): ?>
                            <?php
                            $regId = (int)($a["id"] ?? 0);
                            $regUser = $a["user_id"] ?? '';
                            $status = $a["status"] ?? '';

                            $course_name = $a["course_name"] ?: "Course not found";

                            $intake_name = $a["intake_name"] ?: "N/A";
                            $launch_date = !empty($a["date_launching"])
                                ? date("d M Y", strtotime($a["date_launching"]))
                                : "";

                            if ($status === "done") {
                                $action = '<span class="label label-primary">Graduated</span>';
                            } else {
                                if ($isLecturer) {
                                    $action = '
                                        <a href="scripts/removestu_lec.php?use=' . $regId . '&kasjdhajskdhajsdhhouy=' . cc_e($regUser) . '" class="btn btn-danger btn-xs">
                                            Remove
                                        </a>';
                                } else {
                                    $action = '
                                        <a href="scripts/removestu.php?use=' . $regId . '&kasjdhajskdhajsdhhouy=' . cc_e($regUser) . '" class="btn btn-danger btn-xs">
                                            Remove
                                        </a>
                                        <a href="scripts/finishstu.php?use=' . $regId . '&kasjdhajskdhajsdhhouy=' . cc_e($regUser) . '" class="btn btn-info btn-xs">
                                            Graduate
                                        </a>';
                                }
                            }
                            ?>

                            <tr>
                                <td><?= $num++; ?></td>
                                <td><?= cc_e($course_name); ?></td>
                                <td><?= cc_e(trim($intake_name . ' ' . $launch_date)); ?></td>
                                <td><?= $action; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <form class="m-t" method="post" action="<?= cc_e($route); ?>">
                <div class="form-group">
                    <label>Course Category *</label>

                    <select class="form-control" name="course" id="coursexx" onchange="getval(this);" required>
                        <option value="">...None...</option>

                        <?php
                        $coursesStmt = $conn->prepare("SELECT id, name, category FROM courses ORDER BY name ASC");

                        if ($coursesStmt) {
                            $coursesStmt->execute();
                            $coursesResult = $coursesStmt->get_result();

                            while ($dl = $coursesResult->fetch_assoc()) {
                                $c_id = (int)$dl["id"];
                                $c_name = $dl["name"] ?? '';
                                $c_category = $dl["category"] ?? '';
                                $selected = ((string)$c_id === (string)$selected_course) ? 'selected="selected"' : '';

                                echo "<option id='" . cc_e($c_category) . "' value='" . $c_id . "' {$selected}>" . cc_e($c_name) . "</option>";
                            }

                            $coursesStmt->close();
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" id="diplomaxx"></div>

                <input type="hidden" name="pjk" value="<?= cc_e($uiid); ?>">
                <input type="hidden" name="pc" value="<?= cc_e($selected_course); ?>">

                <button type="submit" name="changecourse" class="btn btn-primary block full-width m-b">
                    Make Change
                </button>
            </form>

        </div>
    </div>
</div>

<script>
function getval(sel) {
    var diploma = document.getElementById('diplomaxx');
    var selectedValue = sel.value;

    if (!selectedValue) {
        diploma.innerHTML = '';
        return;
    }

    $.ajax({
        type: "POST",
        url: 'scripts/getselected.php',
        data: { selected: selectedValue },
        success: function (data) {
            diploma.innerHTML = data;
        },
        error: function () {
            diploma.innerHTML = '<div class="alert alert-danger">Failed to load intake/course details.</div>';
        }
    });
}

window.onload = function () {
    var courseSelect = document.getElementById("coursexx");

    if (courseSelect && courseSelect.value !== "") {
        getval(courseSelect);
    }
};
</script>