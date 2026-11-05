<?php
require 'scripts/connector.php';
require 'scripts/EmailService.php';

$emailService = new EmailService();

$emails = $conn->query("SELECT * FROM announcement_email_queue ORDER BY created_at ASC");

$idsToDelete = [];

while ($row = $emails->fetch_assoc()) {
    $stmt = $conn->prepare("SELECT post_name, description, post_link FROM annouments WHERE id = ?");
    $stmt->bind_param("i", $row['announcement_id']);
    $stmt->execute();
    $announcement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$announcement) continue; 

    $subject = "New Announcement: " . htmlspecialchars($announcement['post_name'], ENT_QUOTES);
    $message = "
        Hello " . htmlspecialchars($row['fname'], ENT_QUOTES) . ",<br><br>
        A new announcement has been posted in your course.<br><br>
        <strong>Title:</strong> " . htmlspecialchars($announcement['post_name'], ENT_QUOTES) . "<br>
        <strong>Description:</strong> " . nl2br(htmlspecialchars($announcement['description'], ENT_QUOTES)) . "<br><br>
        <a href='" . htmlspecialchars($announcement['post_link'], ENT_QUOTES) . "' target='_blank'>Click here to view</a><br><br>
        Regards,<br>
        Elevate Her 360 eLearning
    ";

    // Send email
    if ($emailService->sendEmail($row['email'], $row['fname'], $subject, $message)) {
      
        $idsToDelete[] = $row['id'];
    }
}

// Delete all successfully sent emails at once
if (!empty($idsToDelete)) {
    $ids = implode(',', array_map('intval', $idsToDelete));
    $conn->query("DELETE FROM announcement_email_queue WHERE id IN ($ids)");
}
?>
