<?php

function csrf_verify(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function logLoginAttempt($conn, $email, $status, $ip = null) {
    if (!($conn instanceof mysqli)) return;
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    $columns = [];
    $columnResult = $conn->query('SHOW COLUMNS FROM login_logs');
    if (!$columnResult) {
        error_log('Unable to inspect login_logs schema: ' . $conn->error);
        return;
    }
    while ($column = $columnResult->fetch_assoc()) $columns[] = $column['Field'];
    $timeColumn = in_array('login_time', $columns, true) ? 'login_time' : 'created_at';
    $ipColumn = in_array('ip_address', $columns, true) ? 'ip_address' : 'ip';
    if (!in_array($timeColumn, $columns, true) || !in_array($ipColumn, $columns, true)) {
        error_log('login_logs is missing a supported timestamp or IP column.');
        return;
    }

    $sql = "INSERT INTO login_logs (email, status, `{$ipColumn}`, `{$timeColumn}`) VALUES (?, ?, ?, NOW())";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sss', $email, $status, $ip);
        if (!$stmt->execute()) error_log('Unable to record login attempt: ' . $stmt->error);
        $stmt->close();
    } else {
        error_log('Unable to prepare login attempt record: ' . $conn->error);
    }
}

function rateLimitCheck($ip): bool {
   
    return true;
}
