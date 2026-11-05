<?php
declare(strict_types=1);

/**
 * Publish eLearning identity to elevateher-auth (W2-5b).
 * Soft-fail: local signup/reset always succeeds; failures go to auth_publish_outbox.
 */

require_once __DIR__ . '/sso_bridge.php';

function eh_auth_publish_config(string $key, string $default): string
{
    return eh_sso_config($key, $default);
}

function eh_auth_publish_url(): string
{
    return eh_auth_publish_config(
        'AUTH_PUBLISH_URL',
        'http://127.0.0.1:8080/auth/api/users.php'
        //'https://www.elevateher360.org/auth/api/users.php'
        
    );
}

function eh_auth_publish_token(): string
{
    return eh_auth_publish_config(
        'AUTH_PUBLISH_TOKEN',
        'local-dev-publish-token-change-me'
    );
}

/**
 * @param array{email:string,fname?:string,lname?:string,password_hash:string,source?:string} $payload
 * @return bool true if published to auth API; false if queued/failed
 */
function eh_auth_publish_user(array $payload, ?mysqli $conn = null): bool
{
    $email = strtolower(trim((string)($payload['email'] ?? '')));
    $fname = trim((string)($payload['fname'] ?? ''));
    $lname = trim((string)($payload['lname'] ?? ''));
    $passwordHash = (string)($payload['password_hash'] ?? '');
    $source = trim((string)($payload['source'] ?? 'elearning'));

    if ($email === '' || $passwordHash === '') {
        error_log('[elearning-auth-publish] skip: missing email or password_hash');
        return false;
    }

    $body = [
        'email' => $email,
        'fname' => $fname,
        'lname' => $lname,
        'password_hash' => $passwordHash,
        'source' => $source,
    ];

    $ok = eh_auth_publish_http_post($body);
    if ($ok) {
        error_log(sprintf(
            '[elearning-auth-publish] ok email=%s source=%s',
            $email,
            $source
        ));
        return true;
    }

    error_log(sprintf(
        '[elearning-auth-publish] failed email=%s source=%s — enqueueing outbox',
        $email,
        $source
    ));

    if ($conn instanceof mysqli) {
        eh_auth_publish_enqueue($conn, $body, 'http_failed');
    }

    return false;
}

/**
 * @param array{email:string,fname:string,lname:string,password_hash:string,source:string} $body
 */
function eh_auth_publish_http_post(array $body): bool
{
    $url = eh_auth_publish_url();
    $token = eh_auth_publish_token();
    $json = json_encode($body, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return eh_auth_publish_http_post_stream($url, $token, $json);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return eh_auth_publish_http_post_stream($url, $token, $json);
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        error_log('[elearning-auth-publish] curl_errno=' . $errno);
        return false;
    }

    if ($status < 200 || $status >= 300) {
        error_log('[elearning-auth-publish] http_status=' . $status);
        return false;
    }

    $decoded = json_decode((string)$response, true);
    return is_array($decoded) && !empty($decoded['ok']);
}

function eh_auth_publish_http_post_stream(string $url, string $token, string $json): bool
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ]),
            'content' => $json,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        error_log('[elearning-auth-publish] stream request failed');
        return false;
    }

    $status = 0;
    if (isset($http_response_header[0])
        && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)
    ) {
        $status = (int)$m[1];
    }
    if ($status < 200 || $status >= 300) {
        error_log('[elearning-auth-publish] http_status=' . $status);
        return false;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) && !empty($decoded['ok']);
}

function eh_auth_publish_ensure_outbox(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS auth_publish_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            fname VARCHAR(120) NOT NULL DEFAULT '',
            lname VARCHAR(120) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            source VARCHAR(64) NOT NULL DEFAULT 'elearning',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_outbox_next (next_attempt_at),
            KEY idx_outbox_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * @param array{email:string,fname:string,lname:string,password_hash:string,source:string} $body
 */
function eh_auth_publish_enqueue(mysqli $conn, array $body, string $lastError): void
{
    try {
        eh_auth_publish_ensure_outbox($conn);
        $email = $body['email'];
        $fname = $body['fname'];
        $lname = $body['lname'];
        $passwordHash = $body['password_hash'];
        $source = $body['source'];
        $err = substr($lastError, 0, 255);

        $stmt = $conn->prepare(
            'INSERT INTO auth_publish_outbox
                (email, fname, lname, password_hash, source, attempts, last_error, next_attempt_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, NOW())'
        );
        $stmt->bind_param('ssssss', $email, $fname, $lname, $passwordHash, $source, $err);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[elearning-auth-publish] outbox enqueue failed: ' . $e->getMessage());
    }
}

/**
 * Drain due outbox rows. Returns counts for CLI/logging.
 *
 * @return array{attempted:int,succeeded:int,failed:int}
 */
function eh_auth_publish_drain_outbox(mysqli $conn, int $limit = 50): array
{
    eh_auth_publish_ensure_outbox($conn);
    $attempted = 0;
    $succeeded = 0;
    $failed = 0;

    $limit = max(1, min(200, $limit));
    $sql = "SELECT id, email, fname, lname, password_hash, source, attempts
            FROM auth_publish_outbox
            WHERE next_attempt_at <= NOW()
            ORDER BY id ASC
            LIMIT {$limit}";
    $result = $conn->query($sql);
    if (!$result) {
        return compact('attempted', 'succeeded', 'failed');
    }

    while ($row = $result->fetch_assoc()) {
        $attempted++;
        $id = (int)$row['id'];
        $body = [
            'email' => $row['email'],
            'fname' => $row['fname'],
            'lname' => $row['lname'],
            'password_hash' => $row['password_hash'],
            'source' => $row['source'],
        ];

        if (eh_auth_publish_http_post($body)) {
            $del = $conn->prepare('DELETE FROM auth_publish_outbox WHERE id = ?');
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            $succeeded++;
            error_log(sprintf(
                '[elearning-auth-publish] outbox drained id=%d email=%s',
                $id,
                $row['email']
            ));
            continue;
        }

        $attempts = (int)$row['attempts'] + 1;
        $delayMinutes = min(60, (int)pow(2, min(5, $attempts)));
        $err = 'retry_failed';
        $upd = $conn->prepare(
            'UPDATE auth_publish_outbox
             SET attempts = ?, last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE id = ?'
        );
        $upd->bind_param('isii', $attempts, $err, $delayMinutes, $id);
        $upd->execute();
        $upd->close();
        $failed++;
    }

    $result->free();
    return compact('attempted', 'succeeded', 'failed');
}