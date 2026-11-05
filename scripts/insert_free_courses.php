<?php
require 'connector.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_title = $_POST['course_name'];
    $course_image = $_FILES['course_image']['name'];
    $course_description = $_POST['course_description'];
    $course_link = $_POST['course_link'];
    $created_at = date('Y-m-d H:i:s'); // Current datetime

    // Ensure the directory exists
    $target_dir = "../online_class/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . basename($course_image);

    // Validate and upload course image
    if (move_uploaded_file($_FILES["course_image"]["tmp_name"], $target_file)) {
        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO course_free (course_title, course_image, course_description, course_link, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $course_title, $target_file, $course_description, $course_link, $created_at);

        // Execute the statement
        if ($stmt->execute()) {
            echo "New course inserted successfully";
        } else {
            echo "Error: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "Error uploading file.";
    }
}

// Close the connection
$conn->close();
?>
