<?php
session_start();
require_once 'connector.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $intake_id = intval($_POST['intake_id']);
    $name = trim($_POST['name']);
    $date_launching = $_POST['date_launching'];
    $date_ending = $_POST['date_ending'];
    $branch_id = intval($_POST['branch_id']);

    if ($intake_id <= 0 || empty($name) || empty($date_launching) || empty($date_ending) || $branch_id <= 0) {
        $_SESSION["errormsg"] = "All fields are required.";
        header("Location: ../?view=intakes");
        exit;
    }

    $stmt = $conn->prepare("UPDATE intakes SET name = ?, date_launching = ?, date_ending = ?, branch_id = ? WHERE id = ?");
    $stmt->bind_param("sssii", $name, $date_launching, $date_ending, $branch_id, $intake_id);

    if ($stmt->execute()) {
        $_SESSION["successmsg"] = "Intake updated successfully.";
    } else {
        $_SESSION["errormsg"] = "Failed to update intake.";
    }

    $stmt->close();
    header("Location: ../?view=intakes");
    exit;
} else {
    header("Location: ../?view=intakes");
    exit;
}
