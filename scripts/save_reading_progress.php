<?php
declare(strict_types=1);

session_start();
require 'connector.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method', [], 405);
}

if (
    !isset($conn) ||
    !($conn instanceof mysqli) ||
    $conn->connect_errno ||
    (isset($db_connection_error) && $db_connection_error !== null)
) {
    jsonResponse(false, $db_connection_error ?? 'Database connection failed', [], 500);
}


if (
    !isset($_SESSION[$systemname . 'leido']) ||
    !isset($_SESSION[$systemname . 'letype'])
) {
    jsonResponse(false, 'User not authenticated', [], 401);
}

$user_id = (string) $_SESSION[$systemname . 'leido'];
$user_type = (string) $_SESSION[$systemname . 'letype'];

if ($user_id === '' || $user_type === '') {
    jsonResponse(false, 'Invalid user session', [], 401);
}


$note_id_raw    = isset($_POST['note_id']) ? trim((string) $_POST['note_id']) : '';
$percentage_raw = $_POST['percentage'] ?? null;

if ($note_id_raw === '' || $percentage_raw === null || $percentage_raw === '') {
    jsonResponse(false, 'Missing required parameters', [], 422);
}

if (!is_numeric($percentage_raw)) {
    jsonResponse(false, 'Percentage must be numeric', [], 422);
}

$percentage = (float) $percentage_raw;

if ($percentage < 0 || $percentage > 100) {
    jsonResponse(false, 'Invalid percentage value', [], 422);
}


$note_is_int = ctype_digit($note_id_raw);
$note_id_int = $note_is_int ? (int) $note_id_raw : 0;
$note_id_str = $note_id_raw;


if ($note_is_int) {
    $check_sql = "SELECT id, reading_percentage
                  FROM note_reading_progress
                  WHERE user_id = ? AND note_id = ?
                  LIMIT 1";

    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        jsonResponse(false, 'Failed to prepare check query: ' . $conn->error, [], 500);
    }

    $check_stmt->bind_param('si', $user_id, $note_id_int);
} else {
    $check_sql = "SELECT id, reading_percentage
                  FROM note_reading_progress
                  WHERE user_id = ? AND note_id = ?
                  LIMIT 1";

    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        jsonResponse(false, 'Failed to prepare check query: ' . $conn->error, [], 500);
    }

    $check_stmt->bind_param('ss', $user_id, $note_id_str);
}

if (!$check_stmt->execute()) {
    $error = $check_stmt->error;
    $check_stmt->close();
    jsonResponse(false, 'Database error: ' . $error, [], 500);
}

$check_result = $check_stmt->get_result();
$existing     = $check_result ? $check_result->fetch_assoc() : null;
$check_stmt->close();

if ($existing) {
    $old_percentage = (float) ($existing['reading_percentage'] ?? 0);

    if ($percentage > $old_percentage) {
        if ($note_is_int) {
            $update_sql = "UPDATE note_reading_progress
                           SET reading_percentage = ?, last_accessed = NOW()
                           WHERE user_id = ? AND note_id = ?";

            $update_stmt = $conn->prepare($update_sql);
            if (!$update_stmt) {
                jsonResponse(false, 'Failed to prepare update query: ' . $conn->error, [], 500);
            }

            $update_stmt->bind_param('dsi', $percentage, $user_id, $note_id_int);
        } else {
            $update_sql = "UPDATE note_reading_progress
                           SET reading_percentage = ?, last_accessed = NOW()
                           WHERE user_id = ? AND note_id = ?";

            $update_stmt = $conn->prepare($update_sql);
            if (!$update_stmt) {
                jsonResponse(false, 'Failed to prepare update query: ' . $conn->error, [], 500);
            }

            $update_stmt->bind_param('dss', $percentage, $user_id, $note_id_str);
        }

        if (!$update_stmt->execute()) {
            $error = $update_stmt->error;
            $update_stmt->close();
            $conn->close();
            jsonResponse(false, 'Failed to update progress: ' . $error, [], 500);
        }

        $update_stmt->close();
        $conn->close();

        jsonResponse(true, 'Progress updated', [
            'percentage' => $percentage,
            'previous_percentage' => $old_percentage,
        ]);
    }

    $conn->close();
    jsonResponse(true, 'No update needed (percentage not higher)', [
        'percentage' => $old_percentage,
    ]);
}

if ($note_is_int) {
    $insert_sql = "INSERT INTO note_reading_progress
                   (user_id, note_id, reading_percentage, last_accessed)
                   VALUES (?, ?, ?, NOW())";

    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        jsonResponse(false, 'Failed to prepare insert query: ' . $conn->error, [], 500);
    }

    $insert_stmt->bind_param('sid', $user_id, $note_id_int, $percentage);
} else {
    $insert_sql = "INSERT INTO note_reading_progress
                   (user_id, note_id, reading_percentage, last_accessed)
                   VALUES (?, ?, ?, NOW())";

    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        jsonResponse(false, 'Failed to prepare insert query: ' . $conn->error, [], 500);
    }

    $insert_stmt->bind_param('ssd', $user_id, $note_id_str, $percentage);
}

if (!$insert_stmt->execute()) {
    $error = $insert_stmt->error;
    $insert_stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to save progress: ' . $error, [], 500);
}

$insert_stmt->close();
$conn->close();

jsonResponse(true, 'Progress saved', [
    'percentage' => $percentage,
]);
?>