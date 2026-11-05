<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bulk-activate inactive students
|--------------------------------------------------------------------------
| POST (application/json): { "ids": ["<unique_id>", ...] }
| Only Super Admins (sabrtw) and Admins (xybrtw) may call this.
*/

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/connector.php';

header('Content-Type: application/json; charset=utf-8');

function ba_respond(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$systemname = $systemname ?? 'Elevate Her 360';
$role       = (string)($_SESSION[$systemname . 'letype'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ba_respond(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

if (!in_array($role, ['sabrtw', 'xybrtw'], true)) {
    ba_respond(403, ['status' => 'error', 'message' => 'Only administrators can activate students.']);
}

// Require a JSON body: browsers cannot send this cross-site without a CORS preflight.
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_starts_with($contentType, 'application/json')) {
    ba_respond(415, ['status' => 'error', 'message' => 'Expected a JSON request.']);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    ba_respond(500, ['status' => 'error', 'message' => 'Database connection not found.']);
}

$body = json_decode((string)file_get_contents('php://input'), true);
$ids  = is_array($body['ids'] ?? null) ? $body['ids'] : [];

$ids = array_values(array_unique(array_filter(
    array_map(static fn($v) => trim((string)$v), $ids),
    static fn($v) => $v !== ''
)));

if (!$ids) {
    ba_respond(400, ['status' => 'error', 'message' => 'Select at least one student.']);
}

if (count($ids) > 1000) {
    ba_respond(400, ['status' => 'error', 'message' => 'You can activate at most 1000 students at a time.']);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "
    UPDATE users
    SET verified = 'yes'
    WHERE acc_type = 'afgezt'
      AND (verified IS NULL OR verified NOT IN ('yes', 'Active'))
      AND unique_id IN ({$placeholders})
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    ba_respond(500, ['status' => 'error', 'message' => 'Could not prepare the update.']);
}

$stmt->bind_param(str_repeat('s', count($ids)), ...$ids);

if (!$stmt->execute()) {
    $stmt->close();
    ba_respond(500, ['status' => 'error', 'message' => 'Activation failed. Please try again.']);
}

$activated = $stmt->affected_rows;
$stmt->close();

ba_respond(200, [
    'status'    => 'success',
    'activated' => $activated,
    'skipped'   => count($ids) - $activated,
    'message'   => "{$activated} student(s) activated.",
]);
