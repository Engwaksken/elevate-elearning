<?php
require 'scripts/connector.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$systemname = $systemname ?? "Elevate Her 360";
$unik = $_SESSION[$systemname . "leido"] ?? 0;

if (!isset($_POST['course_id']) || $_POST['course_id'] == "") {
    echo "<tr><td colspan='6'><div class='alert alert-danger'>No Course Selected</div></td></tr>";
    exit;
}

$courseId = intval($_POST['course_id']);

$q = $conn->prepare("
    SELECT u.*
    FROM users u
    JOIN course_registration r ON u.unique_id = r.user_id
    WHERE r.course_id = ?
    AND u.acc_type = 'afgezt'
    ORDER BY u.fname ASC
");

$q->bind_param("i", $courseId);
$q->execute();
$res = $q->get_result();

if ($res->num_rows < 1) {
    echo "<tr><td colspan='6'>
            <div class='alert alert-danger'><strong>No Students Found for this Course</strong></div>
          </td></tr>";
    exit;
}

while ($d = $res->fetch_assoc()):
    $stdId = $d["unique_id"];
    $dp = $d["dp"] ?? "default.jpg";

    // Profile image
    $dpImage = ($dp === "default.jpg")
        ? '<img class="img-circle profile-top" src="images/default_p_pics/user.png">'
        : '<img class="img-circle profile-top" src="images/user_p_pics/'.$dp.'">';

    // Status
    $status = ($d["verified"] === "yes")
        ? '<a href="deactivate.php?id='.$stdId.'&xts=view_students"><span class="label label-primary">Active</span></a>'
        : '<a href="activate.php?id='.$stdId.'&xts=view_students"><span class="label label-info">Not Active</span></a>';

    // Chat
    $chat = ($stdId != $unik)
        ? '<a href="?view=chatwith&pr='.$stdId.'"><span class="label label-success">Chat</span></a>'
        : '';

    // Course & intake
    $course = "N/A";
    $cc = $conn->query("
        SELECT c.name AS course_name, i.name AS intake_name, i.date_launching
        FROM course_registration cr
        LEFT JOIN courses c ON cr.course_id = c.id
        LEFT JOIN intakes i ON cr.intake = i.id
        WHERE cr.user_id='$stdId'
        ORDER BY cr.id DESC LIMIT 1
    ");

    if ($cc && $cc->num_rows > 0) {
        $info = $cc->fetch_assoc();
        $course = $info["course_name"] . " (" . $info["intake_name"] . " " . date("Y", strtotime($info["date_launching"])) . ")";
    }

    $edit = '<a href="?view=edit_student&id='.$stdId.'"><span class="label label-warning">Edit</span></a>';
    $assign = '<a href="?view=change_course&id='.$stdId.'"><span class="label label-info">Assign</span></a>';
?>

<tr>
  <td class="client-avatar"><?= $dpImage ?></td>
  <td><a class="client-link" onclick="openuser('<?= $stdId ?>')"><?= $d['fname'].' '.$d['lname'] ?></a></td>
  <td><?= $course ?></td>
  <td><i class="fa fa-envelope"></i></td>
  <td><?= $d['email'] ?></td>
  <td class="client-status"><?= $status ?> <?= $assign ?> <?= $edit ?> <?= $chat ?></td>
</tr>

<?php endwhile; ?>
