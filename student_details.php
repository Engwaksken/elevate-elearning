<?php
require 'scripts/connector.php';

if (isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $query = $conn->query("SELECT * FROM users WHERE unique_id = '$id'") or die(mysqli_error($conn));

    if ($query->num_rows > 0) {
        $student = $query->fetch_assoc();
        echo "<h4>{$student['fname']} {$student['lname']}</h4>";
        echo "<p><strong>Email:</strong> {$student['email']}</p>";
        echo "<p><strong>Status:</strong> " . ($student['verified'] === 'yes' ? 'Active' : 'Not Active') . "</p>";
        // Add more info as needed
    } else {
        echo "<p>No student found.</p>";
    }
} else {
    echo "<p>Invalid request.</p>";
}
?>
