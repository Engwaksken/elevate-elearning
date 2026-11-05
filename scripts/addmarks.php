<?php 
session_start();
require 'connector.php';


if (!isset($_POST['theaid'], $_POST['theass'], $_POST['themod'], $_POST['marks'])) {
    header("Location: ../?view=home");
    exit;
}


$theass = trim($_POST['theass']);
$themod = strip_tags(trim($_POST['themod']));
$theaid = strip_tags(trim($_POST['theaid']));
$marks  = strip_tags(trim($_POST['marks']));
$shortnotes = isset($_POST['shortnotes']) ? trim($_POST['shortnotes']) : '';

$unik = isset($_SESSION[$systemname . "leido"]) ? strip_tags(trim($_SESSION[$systemname . "leido"])) : '';


$nowdate = date('Y-m-d H:i:s');


$stmt = $conn->prepare("UPDATE `handed_in` 
                        SET `feedback` = ?, 
                            `feedback_date` = ?, 
                            `feedback_from` = ?, 
                            `finalmarks` = ? 
                        WHERE `id` = ?");
if ($stmt) {
    $stmt->bind_param("ssssi", $shortnotes, $nowdate, $unik, $marks, $theaid);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION[$systemname . "successmsg"] = "Marks added";
    } else {
        $_SESSION[$systemname . "successmsg"] = "No changes made or invalid ID";
    }

    $stmt->close();
} else {

    error_log("Database error: " . $conn->error);
    $_SESSION[$systemname . "successmsg"] = "Process failed (DB error)";
}

header("Location: ../?view=subby&hjadvhagsdjhsagsdhg=" . urlencode($themod) . "&piouglv=" . urlencode($theass));
exit;
?>
