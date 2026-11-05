<?php
require 'scripts/connector.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['marks'])) {
    $id = intval($_POST['id']); 
    $marks = floatval($_POST['marks']); 

    try {
        
        $stmt = $pdo->prepare("UPDATE handed_in SET finalmarks = :marks WHERE id = :id");
        $stmt->execute([
            'marks' => $marks,
            'id' => $id
        ]);

      
        header("Location: ../?view=subby&hjadvhagsdjhsagsdhg&status=edited");
        exit;
    } catch (PDOException $e) {
        
        die("Database error: " . $e->getMessage());
    }
} else {
    die("Invalid request. Make sure all required fields are provided.");
}
