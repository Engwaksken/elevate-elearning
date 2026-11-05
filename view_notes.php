
<?php
if (isset($_GET['file'])) {
    $file = $_GET['file'];
   
    $file_path = 'notes/' . $file;
    if (file_exists($file_path)) {
        $allowed_path = realpath('https://elevateher360.org/elearning/notes');
        $requested_path = realpath($file_path);
        
        echo "Requested file: " . htmlspecialchars($file) . "<br>";
        echo "Allowed path: " . $allowed_path . "<br>";
        echo "Requested path: " . $requested_path . "<br>";
        
        if (strpos($requested_path, $allowed_path) === 0) {
          
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
            readfile($requested_path);
            exit;
        } else {
            echo "Invalid file.";
        }
    } else {
        echo "File does not exist.";
    }
} else {
    echo "No file specified.";
}
?>
