<?php


session_start();
require 'connector.php'; 

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate user session - must be instructor or admin
if (!isset($_SESSION[$systemname . "leido"]) || !isset($_SESSION[$systemname . "letype"])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_type = $_SESSION[$systemname . "letype"];
if ($user_type !== "xtfbry" && $user_type !== "xybrtw") {
    echo json_encode(['success' => false, 'message' => 'Access denied. Instructors only.']);
    exit;
}

$user_id = $_SESSION[$systemname . "leido"];

// Validate input parameters
if (!isset($_POST['note_id']) || !isset($_POST['comment_text'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$note_id = $conn->real_escape_string($_POST['note_id']);
$parent_comment_id = isset($_POST['parent_comment_id']) ? $conn->real_escape_string($_POST['parent_comment_id']) : NULL;
$comment_text = trim($_POST['comment_text']);

// Validate comment text
if (empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
    exit;
}

if (strlen($comment_text) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Reply exceeds maximum length of 1000 characters']);
    exit;
}

$comment_text_escaped = $conn->real_escape_string($comment_text);

// Verify note exists and get course info
$noteCheckQuery = "
    SELECT cun.id, cun.title, cu.course_id 
    FROM course_unit_notes cun
    JOIN course_unit cu ON cun.course_unit = cu.id
    WHERE cun.id='$note_id'
";
$noteCheckResult = $conn->query($noteCheckQuery);

if (!$noteCheckResult || $noteCheckResult->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid note ID']);
    exit;
}

$noteData = $noteCheckResult->fetch_assoc();
$note_title = $noteData['title'];
$course_id = $noteData['course_id'];

// Get student info if replying to a comment
$student_id = null;
if ($parent_comment_id !== NULL) {
    $commentCheckQuery = "SELECT user_id FROM note_comments WHERE id='$parent_comment_id'";
    $commentCheckResult = $conn->query($commentCheckQuery);
    
    if (!$commentCheckResult || $commentCheckResult->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parent comment ID']);
        exit;
    }
    
    $commentData = $commentCheckResult->fetch_assoc();
    $student_id = $commentData['user_id'];
}

/* ============================================================================
   INSERT REPLY INTO DATABASE
   ============================================================================ */
$insertQuery = "
    INSERT INTO note_comments 
    (note_id, user_id, comment_text, created_at, parent_comment_id)
    VALUES 
    ('$note_id', '$user_id', '$comment_text_escaped', NOW(), " . 
    ($parent_comment_id !== NULL ? "'$parent_comment_id'" : "NULL") . ")
";

if (!$conn->query($insertQuery)) {
    echo json_encode(['success' => false, 'message' => 'Failed to post reply: ' . $conn->error]);
    exit;
}

$comment_id = $conn->insert_id;

// Get instructor's name
$userQuery = "SELECT fname, lname FROM users WHERE unique_id='$user_id'";
$userResult = $conn->query($userQuery);
$user = $userResult->fetch_assoc();
$author_name = htmlspecialchars($user['fname'] . ' ' . $user['lname']);

/* ============================================================================
   CREATE NOTIFICATION FOR STUDENT
   ============================================================================ */
if ($student_id !== null) {
    // Build action URL
    $action_url = "?view=course_notes&note_id=" . $note_id . "#comment-" . $comment_id;
    
    // Create notification
    $notificationQuery = "
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            priority,
            related_type,
            related_id,
            course_id,
            sender_id,
            action_url
        ) VALUES (
            '$student_id',
            'Instructor Replied to Your Comment',
            " . $conn->real_escape_string("$author_name replied to your comment on \"$note_title\"") . ",
            'comment_reply',
            'normal',
            'comment',
            $comment_id,
            $course_id,
            '$user_id',
            '$action_url'
        )
    ";
    
    if (!$conn->query($notificationQuery)) {
        // Log error but don't fail the whole request
        error_log("Failed to create notification: " . $conn->error);
    }
    
    /* ========================================================================
       OPTIONAL: SEND EMAIL NOTIFICATION
       Check if student has email notifications enabled
       ======================================================================== */
    $settingsQuery = "
        SELECT email_comment_reply 
        FROM notification_settings 
        WHERE user_id = '$student_id'
    ";
    $settingsResult = $conn->query($settingsQuery);
    
    if ($settingsResult && $settingsResult->num_rows > 0) {
        $settings = $settingsResult->fetch_assoc();
        
        if ($settings['email_comment_reply'] == 1) {
            // Get student email
            $studentQuery = "SELECT email, fname, lname FROM users WHERE unique_id='$student_id'";
            $studentResult = $conn->query($studentQuery);
            
            if ($studentResult && $studentResult->num_rows > 0) {
                $student = $studentResult->fetch_assoc();
                $student_email = $student['email'];
                $student_name = $student['fname'] . ' ' . $student['lname'];
                
                 $emailSubject = "Instructor Replied to Your Comment";
                $emailMessage = "
                    Hi $student_name,
                    
                    $author_name has replied to your comment on '$note_title'.
                    
                    Reply: $comment_text
                    
                    View the full discussion: " . $_SERVER['HTTP_HOST'] . "/" . $action_url . "
                    
                    Best regards,
                    E-Learning Platform
                ";
                
                // Add to notification queue (if you have email sending system)
                // Or send immediately:
                // mail($student_email, $emailSubject, $emailMessage);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Reply posted successfully' . ($student_id ? ' and notification sent' : ''),
    'comment' => [
        'id' => $comment_id,
        'comment_text' => $comment_text,
        'author_name' => $author_name,
        'time_ago' => 'just now',
        'is_instructor' => true
    ]
]);

$conn->close();
?>