<?php
session_start();
require 'connector.php';

if (!isset($_POST['id']) || trim($_POST['id']) === '') {
    echo '<div class="alert alert-danger"><strong>Danger!</strong> Hacking Attempt detected.</div>';
    exit;
}

$courseid = (int)$_POST['id'];

// Avoid undefined $systemname
$systemname = $systemname ?? '';
$unik = '';
if ($systemname !== '' && isset($_SESSION[$systemname . "leido"])) {
    $unik = strip_tags(trim($_SESSION[$systemname . "leido"]));
}

$sql = "
    SELECT
        cr.user_id,
        u.unique_id, u.fname, u.lname, u.email, u.dp, u.verified, u.class_type_id,
        c.name AS course_name,
        ct.name AS class_type_name
    FROM course_registration cr
    JOIN users u            ON u.unique_id = cr.user_id
    LEFT JOIN courses c     ON c.id = cr.course_id
    LEFT JOIN class_type ct ON ct.id = u.class_type_id
    WHERE cr.course_id = ?
    ORDER BY u.fname ASC, u.lname ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo '<div class="alert alert-danger"><strong>Error!</strong> Failed to prepare query.</div>';
    exit;
}

$stmt->bind_param("i", $courseid);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows < 1) {
    echo '<div class="alert alert-danger"><strong>No Students to display.</strong></div>';
    $stmt->close();
    exit;
}

while ($d = $res->fetch_assoc()) {
    $dpc = $d["dp"] ?? "default.jpg";

    if ($dpc === "default.jpg" || $dpc === '' || $dpc === null) {
        $dpp = '<img alt="image" class="img-circle profile-top" src="images/default_p_pics/user.png" />';
    } else {
        $dpp = '<img alt="image" class="img-circle profile-top" src="images/user_p_pics/' . htmlspecialchars($dpc) . '" />';
    }

    if (($d["verified"] ?? '') === "yes") {
        $ver = '<a href="deactivate.php?id=' . urlencode($d["unique_id"]) . '"><span class="label label-primary">Active</span></a>';
    } else {
        $ver = '<a href="activate.php?id=' . urlencode($d["unique_id"]) . '"><span class="label label-info">Not Active</span></a>';
    }

    $chatter = '';
    if ($unik !== '' && $d["unique_id"] !== $unik) {
        $chatter = '<a href="?view=chatwith&pr=' . urlencode($d["unique_id"]) . '"><span class="label label-success">Chat</span></a>';
    }

    $course    = $d["course_name"] ?? "N/A";
    $classType = $d["class_type_name"] ?? "N/A";

    echo '<tr>
            <td class="client-avatar">' . $dpp . '</td>
            <td>
                <a data-toggle="tab" class="client-link" onclick="openuser(\'' . htmlspecialchars($d["unique_id"], ENT_QUOTES) . '\')">
                    ' . htmlspecialchars($d["fname"] . ' ' . $d["lname"]) . '
                </a>
            </td>
            <td>
                ' . htmlspecialchars($course) . '<br>
                <small class="text-muted">Class Type: ' . htmlspecialchars($classType) . '</small>
            </td>
            <td class="contact-type"><i class="fa fa-envelope"></i></td>
            <td>' . htmlspecialchars($d["email"]) . '</td>
            <td class="client-status">' . $ver . ' ' . $chatter . '</td>
        </tr>';
}

$stmt->close();
exit;
?>
