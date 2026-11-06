<?php
// Security: Validate course parameter
if (!isset($_GET['course']) || empty($_GET['course'])) {
    echo '<div class="alert alert-danger">
            No course has been specified <a class="alert-link" href="?view=home">Return Home</a>.
          </div>';
    exit;
}

$course_id = (int) $_GET['course'];
$valid     = false;
$user_id   = $_SESSION[$systemname . "leido"]  ?? null;
$user_type = $_SESSION[$systemname . "letype"] ?? null;

// Validate user access
if (in_array($user_type, ["sabrtw", "xybrtw"], true)) {
    $valid = true; // Admin
} elseif ($user_type === "xtfbry" && !empty($user_id)) {
    $stmt = $conn->prepare("SELECT 1 FROM course_registration_lecturers WHERE user_id = ? AND course_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $valid = ($result && $result->num_rows > 0);
        $stmt->close();
    }
}

if (!$valid) {
    echo '<div class="alert alert-danger">
            Invalid course selection <a class="alert-link" href="?view=home">Return Home</a>.
          </div>';
    exit;
}

// Fetch course details
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo '<div class="alert alert-danger">Failed to prepare course query.</div>';
    exit;
}

$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows < 1) {
    echo '<div class="alert alert-danger">
            Course not available <a class="alert-link" href="?view=home">Return Home</a>.
          </div>';
    $stmt->close();
    exit;
}

$course_data = $result->fetch_assoc();
$stmt->close();

if (!$course_data) {
    echo '<div class="alert alert-danger">Course data could not be loaded.</div>';
    exit;
}

// Fetch category name
$category_name = 'N/A';
if (!empty($course_data['category'])) {
    $category_id = (int) $course_data['category'];
    $stmt = $conn->prepare("SELECT name FROM course_category WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $cat_result = $stmt->get_result();
        if ($cat_result && $cat_result->num_rows > 0) {
            $cat_data = $cat_result->fetch_assoc();
            $category_name = htmlspecialchars($cat_data['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        }
        $stmt->close();
    }
}

// Fetch course units once
$units = [];
$stmt = $conn->prepare("SELECT * FROM course_unit WHERE course_id = ? ORDER BY order_number ASC, id ASC");
if ($stmt) {
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $units_result = $stmt->get_result();

    while ($row = $units_result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();
}
$modules_count = count($units);

// Helper to safely get uploader name
function getUploaderName(mysqli $conn, $uploaded_by): string
{
    if (empty($uploaded_by)) {
        return 'Unknown User';
    }

    $stmt = $conn->prepare("SELECT fname, lname FROM users WHERE unique_id = ? LIMIT 1");
    if (!$stmt) {
        return 'Unknown User';
    }

    $stmt->bind_param("s", $uploaded_by);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        return 'Unknown User';
    }

    $fullname = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
    return htmlspecialchars($fullname !== '' ? $fullname : 'Unknown User', ENT_QUOTES, 'UTF-8');
}
?>

<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2><?= htmlspecialchars($course_data['name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></h2>
        <ol class="breadcrumb">
            <li><a href="?">Home</a></li>
            <li><a href="?view=courses">Courses</a></li>
            <li class="active"><strong>Course</strong></li>
        </ol>
    </div>
    <div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content animated fadeInRight">
    <div class="row">
        <div class="col-sm-8">
            <div class="ibox">
                <div class="ibox-content">
                    <div class="clients-list">
                        <div class="m-b-md">
                            <strong>Category:</strong> <?= $category_name; ?><br>
                            <strong>Total Units:</strong> <?= (int) $modules_count; ?>
                        </div>

                        <?php if ($modules_count > 0): ?>
                            <ul class="nav nav-tabs">
                                <?php foreach ($units as $index => $unit): ?>
                                    <li class="<?= $index === 0 ? 'active' : ''; ?>">
                                        <a data-toggle="tab" href="#tab-<?= $index + 1; ?>">
                                            Course Unit <?= $index + 1; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="tab-content">
                                <?php foreach ($units as $index => $unit): ?>
                                    <?php
                                    $tab_num   = $index + 1;
                                    $unit_id   = (int) ($unit['id'] ?? 0);
                                    $unit_name = htmlspecialchars($unit['name'] ?? 'Unnamed Unit', ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div id="tab-<?= $tab_num; ?>" class="tab-pane <?= $index === 0 ? 'active' : ''; ?>">
                                        <div class="full-height-scroll">
                                            <div class="table-responsive">
                                                <div class="ibox float-e-margins">
                                                    <div class="ibox-title">
                                                        <h5><?= $unit_name; ?></h5>
                                                        <div class="ibox-tools">
                                                            <a class="collapse-link">
                                                                <i class="fa fa-chevron-up"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="ibox-content">
                                                        <?php
                                                        $file_icons = [
                                                            1 => 'fa-file-audio-o',
                                                            2 => 'fa-file-video-o',
                                                            3 => 'fa-file-text-o',
                                                            4 => 'fa-file-image-o'
                                                        ];

                                                        // Notes
                                                        $stmt_notes = $conn->prepare("SELECT * FROM course_unit_notes WHERE course_unit = ? ORDER BY id DESC");
                                                        if ($stmt_notes) {
                                                            $stmt_notes->bind_param("i", $unit_id);
                                                            $stmt_notes->execute();
                                                            $notes_result = $stmt_notes->get_result();

                                                            while ($note = $notes_result->fetch_assoc()) {
                                                                $icon = $file_icons[(int)($note['file_type'] ?? 0)] ?? 'fa-file-o';
                                                                $date_formatted = !empty($note['date_uploaded'])
                                                                    ? date("l d-m-Y h:i a", strtotime($note['date_uploaded']))
                                                                    : 'Unknown date';

                                                                $uploader_name = getUploaderName($conn, $note['uploaded_by'] ?? '');

                                                                $note_id  = (int) ($note['id'] ?? 0);
                                                                $document = htmlspecialchars($note['document'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                $title    = htmlspecialchars($note['title'] ?? 'Untitled Note', ENT_QUOTES, 'UTF-8');
                                                                $message  = htmlspecialchars($note['short_message'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                                <div class="m-b-md">
                                                                    <div class="panel panel-default">
                                                                        <div class="panel-heading" style="font-weight: bold;">
                                                                            <span class="fa <?= $icon; ?>"></span> <?= $title; ?>
                                                                        </div>
                                                                        <div class="panel-body">
                                                                            <p><?= nl2br($message); ?></p>
                                                                            <span style="font-size:9px; font-style:italic; color:#940101;">
                                                                                <?= htmlspecialchars($date_formatted, ENT_QUOTES, 'UTF-8'); ?>
                                                                            </span>
                                                                        </div>
                                                                        <div class="panel-footer">
                                                                            <span class="badge">added by <?= $uploader_name; ?></span>
                                                                            <span style="float:right;">
                                                                                <a href="scripts/deletenotes.php?find=<?= $note_id; ?>&zambezia=<?= urlencode($document); ?>&bengani=<?= $course_id; ?>"
                                                                                   class="btn btn-danger btn-xs"
                                                                                   onclick="return confirm('Are you sure you want to delete this note?')">
                                                                                    Delete
                                                                                </a>
                                                                                <?php if ($document !== ''): ?>
                                                                                    <a href="notes/<?= rawurlencode($note['document']); ?>" class="btn btn-success btn-xs" target="_blank">
                                                                                        Read Notes
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                            <div style="clear:both;"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php
                                                            }
                                                            $stmt_notes->close();
                                                        }

                                                        // Assignments
                                                        $stmt_assign = $conn->prepare("SELECT * FROM assignments WHERE course_unit = ? ORDER BY id DESC");
                                                        if ($stmt_assign) {
                                                            $stmt_assign->bind_param("i", $unit_id);
                                                            $stmt_assign->execute();
                                                            $assign_result = $stmt_assign->get_result();

                                                            while ($assignment = $assign_result->fetch_assoc()) {
                                                                $icon = $file_icons[(int)($assignment['file_type'] ?? 0)] ?? 'fa-file-o';
                                                                $date_uploaded = !empty($assignment['date_uploaded'])
                                                                    ? date("l d-m-Y h:i a", strtotime($assignment['date_uploaded']))
                                                                    : 'Unknown upload date';
                                                                $date_due = !empty($assignment['date_due'])
                                                                    ? date("l d-m-Y h:i a", strtotime($assignment['date_due']))
                                                                    : 'No due date';

                                                                $uploader_name = getUploaderName($conn, $assignment['uploaded_by'] ?? '');

                                                                $assign_id = (int) ($assignment['id'] ?? 0);
                                                                $document  = htmlspecialchars($assignment['document'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                $title     = htmlspecialchars($assignment['title'] ?? 'Untitled Assignment', ENT_QUOTES, 'UTF-8');
                                                                $message   = htmlspecialchars($assignment['short_message'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                                <div class="m-b-md">
                                                                    <div class="panel panel-info">
                                                                        <div class="panel-heading" style="font-weight: bold;">
                                                                            <span class="fa <?= $icon; ?>"></span> ASSIGNMENT: <?= $title; ?>
                                                                        </div>
                                                                        <div class="panel-body">
                                                                            <p><?= nl2br($message); ?></p>
                                                                            <span style="font-size:12px; color:#23c6c8; font-weight:bold;">
                                                                                HAND IN BY: <?= htmlspecialchars($date_due, ENT_QUOTES, 'UTF-8'); ?>
                                                                            </span>
                                                                            <br>
                                                                            <span style="font-size:9px; font-style:italic; color:#940101;">
                                                                                <?= htmlspecialchars($date_uploaded, ENT_QUOTES, 'UTF-8'); ?>
                                                                            </span>
                                                                        </div>
                                                                        <div class="panel-footer">
                                                                            <span class="badge">added by <?= $uploader_name; ?></span>
                                                                            <span style="float:right;">
                                                                                <a href="scripts/deleteassignments.php?find=<?= $assign_id; ?>&zambezia=<?= urlencode($document); ?>&bengani=<?= $course_id; ?>"
                                                                                   class="btn btn-danger btn-xs"
                                                                                   onclick="return confirm('Are you sure you want to delete this assignment?')">
                                                                                    Delete
                                                                                </a>
                                                                                <?php if ($document !== ''): ?>
                                                                                    <a href="assignments/agjashdbsjdhkhsdvfh/<?= rawurlencode($assignment['document']); ?>" class="btn btn-success btn-xs" target="_blank">
                                                                                        View Assignment
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                            <div style="clear:both;"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php
                                                            }
                                                            $stmt_assign->close();
                                                        }
                                                        ?>

                                                        <button
                                                            onclick="setnotesmodule('<?= $unit_id; ?>','Mod <?= $tab_num; ?>','<?= htmlspecialchars(addslashes($unit['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')"
                                                            type="button"
                                                            class="btn btn-w-m btn-info">
                                                            Add Notes
                                                        </button>

                                                        <button
                                                            onclick="setassignmentmodule('<?= $unit_id; ?>','Mod <?= $tab_num; ?>','<?= htmlspecialchars(addslashes($unit['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')"
                                                            type="button"
                                                            class="btn btn-w-m btn-danger">
                                                            Add Assignment
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No course units found for this course.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="ibox">
                <div class="ibox-content">
                    <p class="m-b-lg">You can drag and drop to rearrange the Modules.</p>

                    <div class="dd" id="nestable">
                        <ol class="dd-list">
                            <?php foreach ($units as $index => $unit): ?>
                                <?php
                                $unit_id   = (int) ($unit['id'] ?? 0);
                                $unit_name = htmlspecialchars($unit['name'] ?? 'Unnamed Unit', ENT_QUOTES, 'UTF-8');
                                $duration  = (int) ($unit['duration'] ?? 0);
                                ?>
                                <li class="dd-item" data-id="<?= $unit_id; ?>">
                                    <div class="dd-handle"><?= $index + 1; ?> - <?= $unit_name; ?> (<?= $duration; ?> days)</div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                    <input id="nestable-outputxx" class="form-control" type="hidden" />
                    <div id="report"></div>
                    <button type="button" onclick="editlist()" class="btn btn-primary">Submit Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding notes -->
<div class="modal inmodal" id="myModal6" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content animated flipInY">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">×</span>
                    <span class="sr-only">Close</span>
                </button>
                <h4 class="modal-title"><?= htmlspecialchars($course_data['name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></h4>
                <small class="font-bold">Add Notes</small>
                <h4 id="hghg" style="color:#c21033;"></h4>
            </div>
            <div class="modal-body" style="background-color:#09b8ba;">
                <form method="POST" role="form" enctype="multipart/form-data" action="scripts/addnotes.php">
                    <div class="form-group">
                        <label class="control-label">Add Title</label>
                        <input type="text" placeholder="Title" name="title" class="form-control" required>
                    </div>

                    <input type="hidden" value="" name="themodule" id="themodule"/>

                    <div class="form-group">
                        <label class="control-label">Short Notes</label>
                        <textarea name="shortnotes" class="form-control" required></textarea>
                        <input type="hidden" name="course" value="<?= $course_id; ?>" class="form-control"/>
                    </div>

                    <div class="form-group">
                        <label class="control-label">File type</label>
                        <select class="form-control" name="filetype" required>
                            <?php
                            $stmt = $conn->prepare("SELECT * FROM filetypes ORDER BY name ASC");
                            if ($stmt) {
                                $stmt->execute();
                                $filetypes = $stmt->get_result();

                                while ($filetype = $filetypes->fetch_assoc()) {
                                    $selected = ((int)($filetype['id'] ?? 0) === 3) ? 'selected' : '';
                                    $ft_id    = (int) ($filetype['id'] ?? 0);
                                    $ft_name  = htmlspecialchars($filetype['name'] ?? '', ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $ft_id . '" ' . $selected . '>' . $ft_name . '</option>';
                                }
                                $stmt->close();
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <input type="file" name="notesfile" required class="form-control-file"/>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Notes</button>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding assignments -->
<div class="modal inmodal" id="myModal7" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content animated flipInY">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">×</span>
                    <span class="sr-only">Close</span>
                </button>
                <h4 class="modal-title"><?= htmlspecialchars($course_data['name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></h4>
                <small class="font-bold">Add Assignment</small>
                <h4 id="hxhx" style="color:#c21033;"></h4>
            </div>
            <div class="modal-body" style="background-color:#ed5565;">
                <form method="POST" role="form" enctype="multipart/form-data" action="scripts/addassignment.php">
                    <div class="form-group">
                        <label class="control-label">Assignment Title</label>
                        <input type="text" placeholder="Title" name="title" class="form-control" required>
                    </div>

                    <input type="hidden" value="" name="themodule" id="theass"/>

                    <div class="form-group">
                        <label class="control-label">Short Notes</label>
                        <textarea name="shortnotes" class="form-control" required></textarea>
                        <input type="hidden" name="course" value="<?= $course_id; ?>" class="form-control"/>
                    </div>

                    <div class="form-inline">
                        <div class="form-group">
                            <label>Date Due</label>
                            <input type="date" min="<?= date('Y-m-d'); ?>" name="date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label">Expected File type</label>
                        <select class="form-control" name="expectedfiletype" required>
                            <?php
                            $stmt = $conn->prepare("SELECT * FROM filetypes ORDER BY name ASC");
                            if ($stmt) {
                                $stmt->execute();
                                $filetypes = $stmt->get_result();

                                while ($filetype = $filetypes->fetch_assoc()) {
                                    $selected = ((int)($filetype['id'] ?? 0) === 3) ? 'selected' : '';
                                    $ft_id    = (int) ($filetype['id'] ?? 0);
                                    $ft_name  = htmlspecialchars($filetype['name'] ?? '', ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $ft_id . '" ' . $selected . '>' . $ft_name . '</option>';
                                }
                                $stmt->close();
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="control-label">File type</label>
                        <select class="form-control" name="filetype" required>
                            <?php
                            $stmt = $conn->prepare("SELECT * FROM filetypes ORDER BY name ASC");
                            if ($stmt) {
                                $stmt->execute();
                                $filetypes = $stmt->get_result();

                                while ($filetype = $filetypes->fetch_assoc()) {
                                    $selected = ((int)($filetype['id'] ?? 0) === 3) ? 'selected' : '';
                                    $ft_id    = (int) ($filetype['id'] ?? 0);
                                    $ft_name  = htmlspecialchars($filetype['name'] ?? '', ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $ft_id . '" ' . $selected . '>' . $ft_name . '</option>';
                                }
                                $stmt->close();
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <input type="file" name="notesfile" required class="form-control-file"/>
                    </div>

                    <button type="submit" class="btn btn-primary">Upload Assignment</button>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function isNumberKey(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode;
    return !(charCode < 48 || charCode > 57);
}

function editlist() {
    var data = document.getElementById("nestable-outputxx").value;

    if (!data) {
        alert("No changes detected");
        return;
    }

    data = data.replace(/{"id":/g, '').replace(/}/g, '').replace(/\[/g, '').replace(/\]/g, '');

    try {
        var array = JSON.parse("[" + data + "]");

        array.forEach(function(id, index) {
            var position = index + 1;

            $.ajax({
                type: "POST",
                data: { position: position, theid: id },
                url: 'scripts/updateposition.php',
                success: function(response) {
                    console.log("Updated position for ID " + id);
                },
                error: function(xhr, status, error) {
                    console.error("Error updating position:", error);
                }
            });
        });

        document.getElementById("report").innerHTML =
            '<div class="alert alert-success alert-dismissable">' +
            '<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>' +
            'Process Successful</div>';
    } catch (e) {
        alert("Error processing changes: " + e.message);
    }
}

function setnotesmodule(unitId, mod, title) {
    $("#myModal6").modal('show');
    document.getElementById("themodule").value = unitId;
    document.getElementById("hghg").textContent = mod + " (" + title + ")";
}

function setassignmentmodule(unitId, mod, title) {
    $("#myModal7").modal('show');
    document.getElementById("theass").value = unitId;
    document.getElementById("hxhx").textContent = mod + " (" + title + ")";
}
</script>
