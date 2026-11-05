<?php
require 'connector.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $mymodule = isset($_POST['mymodule']) ? intval($_POST['mymodule']) : 0;
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

    if ($id > 0 && $mymodule > 0 && $assignment_id > 0) {
        $stmt = $conn->prepare("UPDATE handed_in SET finalmarks = NULL WHERE id = ?");
        
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: ../?view=subby&hjadvhagsdjhsagsdhg=$mymodule&piouglv=$assignment_id");
        exit;
    } else {
        echo "Invalid input received.";
    }
}
?>
