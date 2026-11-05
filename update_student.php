<?php

include('db_connection.php');


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   
    $student_id = $_POST['unique_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $status = $_POST['status'];


    if (empty($name) || empty($email)) {
        echo "Name and Email are required fields!";
        exit;
    }

   
    $update_query = "UPDATE users SET name = '$name', email = '$email', verified = '$status' WHERE unique_id = '$student_id'";

    if ($conn->query($update_query) === TRUE) {
        echo "<div class='alert alert-success'>Student details updated successfully!</div>";
        echo "<a href='view_students.php'>Back to Student List</a>";  
    } else {
        echo "<div class='alert alert-danger'>Error updating student details: " . $conn->error . "</div>";
    }
} else {
    echo "Invalid request!";
}
?>
