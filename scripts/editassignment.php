<?php
include 'connector.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $shortnotes = $_POST['shortnotes'];
    $course = $_POST['course'];
    $intake = $_POST['intake'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $expectedFileType = $_POST['expectedfiletype'];
    $fileType = $_POST['filetype'];

    // Handle file upload
    if ($_FILES['notesfile']['error'] == 0) {
        $fileName = $_FILES['notesfile']['name'];
        $fileTmpName = $_FILES['notesfile']['tmp_name'];
        $filePath = "uploads/" . basename($fileName);

        if (move_uploaded_file($fileTmpName, $filePath)) {
            // Successfully uploaded file, now update database
            $updateQuery = "UPDATE assignments SET title = '$title', shortnotes = '$shortnotes', date_due = '$date', time_due = '$time', 
                            expected_filetype = '$expectedFileType', filetype = '$fileType', file_path = '$filePath' 
                            WHERE id = '$assignment_id'";
            if ($conn->query($updateQuery) === TRUE) {
                echo "Assignment updated successfully!";
            } else {
                echo "Error: " . $conn->error;
            }
        } else {
            echo "Error uploading file.";
        }
    }
}
?>
