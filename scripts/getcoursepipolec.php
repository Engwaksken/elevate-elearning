<?php
session_start();
require 'connector.php';

if (!isset($_POST['id']) || trim($_POST['id']) === '') {
    echo '<div class="alert alert-danger"><strong>Danger!</strong> Hacking Attempt detected.</div>';
    exit;
}

$courseid = (int)$_POST['id'];

$systemname = $systemname ?? '';
$unik = '';
if ($systemname !== '' && isset($_SESSION[$systemname . "leido"])) {
    $unik = strip_tags(trim($_SESSION[$systemname . "leido"]));
}


$sql = "
    SELECT
        crl.user_id,
        crl.intake,
        u.unique_id, u.fname, u.lname, u.email, u.dp, u.verified, u.class_type_id,
        ct.name AS class_type_name,
        c.name  AS course_name,
        cu.name AS course_module
    FROM course_registration_lecturers crl
    JOIN users u           ON u.unique_id = crl.user_id
    LEFT JOIN class_type ct ON ct.id = u.class_type_id
    LEFT JOIN courses c     ON c.id = crl.course_id
    LEFT JOIN course_unit cu ON cu.id = crl.intake
    WHERE crl.course_id = ?
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
    echo '<div class="alert alert-danger"><strong>No Lecturers to display.</strong></div>';
    $stmt->close();
    exit;
}

while ($d = $res->fetch_assoc()) {
    $std_id = $d["unique_id"];
    $dpc    = $d["dp"] ?? 'default.jpg';

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

    $course       = $d['course_name'] ?? 'N/A';
    $coursemodule = $d['course_module'] ?? '';
    $classType    = $d['class_type_name'] ?? 'N/A';

    // show class type alongside course/module (edit how you prefer)
    $courseCell = htmlspecialchars($course);
    if ($coursemodule !== '') $courseCell .= ' (' . htmlspecialchars($coursemodule) . ')';
    $courseCell .= '<br><small class="text-muted">Class Type: ' . htmlspecialchars($classType) . '</small>';

    echo '<tr>
            <td class="client-avatar">' . $dpp . '</td>
            <td>
                <a data-toggle="tab" class="client-link" onclick="openuser(\'' . htmlspecialchars($d["unique_id"], ENT_QUOTES) . '\')">
                    ' . htmlspecialchars($d["fname"] . ' ' . $d["lname"]) . '
                </a>
            </td>
            <td>' . $courseCell . '</td>
            <td class="contact-type"><i class="fa fa-envelope"></i></td>
            <td>' . htmlspecialchars($d["email"]) . '</td>
            <td class="client-status">' . $ver . ' ' . $chatter . '</td>
        </tr>';
}

$stmt->close();
exit;
?>
