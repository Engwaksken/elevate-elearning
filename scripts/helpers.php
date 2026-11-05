<?php

function csrf_verify(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function logLoginAttempt($conn, $email, $status, $ip = null) {
   
    if (!($conn instanceof mysqli)) return;
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $sql = 'INSERT INTO login_logs (email, status, ip, created_at) VALUES (?, ?, ?, NOW())';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sss', $email, $status, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

function rateLimitCheck($ip): bool {
   
    return true;
}
