<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connector.php';

date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonResponse(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message,
        ], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        jsonResponse(false, 'Database connection failed.', [], 500);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method.', [], 405);
    }

    $systemname = $systemname ?? '';

    $userId = trim((string)(
        $_SESSION[$systemname . 'leido']
        ?? $_SESSION['unique_id']
        ?? ''
    ));

    $role = trim((string)(
        $_SESSION[$systemname . 'letype']
        ?? $_SESSION['role']
        ?? ''
    ));

    if ($userId === '' || $role !== 'afgezt') {
        jsonResponse(false, 'Your student session has expired.', [], 401);
    }

    $materialId = (int)($_POST['material_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? 'progress'));

    $allowedActions = ['open', 'progress', 'complete', 'download', 'heartbeat'];

    if ($materialId <= 0 || !in_array($action, $allowedActions, true)) {
        jsonResponse(false, 'Invalid material progress request.', [], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm material belongs to a course/intake registered to this student
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            tm.id,
            ct.course_id,
            COALESCE(ct.intake_id, 0) AS intake_id
        FROM topic_materials tm
        INNER JOIN course_topics ct ON ct.id = tm.topic_id
        WHERE tm.id = ?
          AND COALESCE(tm.status, 'active') = 'active'
          AND COALESCE(ct.status, 'active') = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(false, 'Unable to validate material: ' . $conn->error, [], 500);
    }

    $stmt->bind_param('i', $materialId);
    $stmt->execute();

    $material = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$material) {
        jsonResponse(false, 'Learning material was not found.', [], 404);
    }

    $courseId = (int)$material['course_id'];
    $materialIntakeId = (int)$material['intake_id'];

    $accessSql = "
        SELECT 1
        FROM course_registration
        WHERE user_id = ?
          AND course_id = ?
    ";

    if ($materialIntakeId > 0) {
        $accessSql .= " AND intake = ? ";
    }

    $accessSql .= " LIMIT 1 ";

    $stmt = $conn->prepare($accessSql);

    if (!$stmt) {
        jsonResponse(false, 'Unable to validate course registration.', [], 500);
    }

    if ($materialIntakeId > 0) {
        $stmt->bind_param('sii', $userId, $courseId, $materialIntakeId);
    } else {
        $stmt->bind_param('si', $userId, $courseId);
    }

    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$allowed) {
        jsonResponse(false, 'You do not have access to this learning material.', [], 403);
    }

    $now = date('Y-m-d H:i:s');

    $progress = isset($_POST['progress'])
        ? (float)$_POST['progress']
        : 0.0;

    $progress = max(0.0, min(100.0, $progress));

    $seconds = isset($_POST['seconds'])
        ? max(0, (int)$_POST['seconds'])
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Create progress row if this is the student's first interaction
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO topic_material_progress (
            user_id,
            material_id,
            first_opened_at,
            last_viewed_at,
            progress_percentage,
            total_views,
            seconds_spent,
            status,
            completed_at,
            downloaded_at,
            total_downloads
        )
        VALUES (?, ?, ?, ?, 0, 0, 0, 'not_started', NULL, NULL, 0)
        ON DUPLICATE KEY UPDATE
            last_viewed_at = VALUES(last_viewed_at)
    ");

    if (!$stmt) {
        jsonResponse(false, 'Unable to initialise material progress: ' . $conn->error, [], 500);
    }

    $stmt->bind_param('siss', $userId, $materialId, $now, $now);
    $stmt->execute();
    $stmt->close();

    if ($action === 'open') {
        $stmt = $conn->prepare("
            UPDATE topic_material_progress
            SET
                first_opened_at = COALESCE(first_opened_at, ?),
                last_viewed_at = ?,
                total_views = total_views + 1,
                status = CASE
                    WHEN status = 'completed' THEN 'completed'
                    ELSE 'started'
                END,
                progress_percentage = CASE
                    WHEN progress_percentage < 1 THEN 1
                    ELSE progress_percentage
                END
            WHERE user_id = ?
              AND material_id = ?
        ");

        if (!$stmt) {
            jsonResponse(false, 'Unable to record material opening.', [], 500);
        }

        $stmt->bind_param('sssi', $now, $now, $userId, $materialId);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'progress') {
        $stmt = $conn->prepare("
            UPDATE topic_material_progress
            SET
                last_viewed_at = ?,
                progress_percentage = GREATEST(progress_percentage, ?),
                status = CASE
                    WHEN status = 'completed' THEN 'completed'
                    WHEN ? >= 100 THEN 'completed'
                    ELSE 'started'
                END,
                completed_at = CASE
                    WHEN completed_at IS NOT NULL THEN completed_at
                    WHEN ? >= 100 THEN ?
                    ELSE NULL
                END
            WHERE user_id = ?
              AND material_id = ?
        ");

        if (!$stmt) {
            jsonResponse(false, 'Unable to update reading progress.', [], 500);
        }

        $stmt->bind_param(
            'sdddssi',
            $now,
            $progress,
            $progress,
            $progress,
            $now,
            $userId,
            $materialId
        );
    }

    if ($action === 'heartbeat') {
        $stmt = $conn->prepare("
            UPDATE topic_material_progress
            SET
                last_viewed_at = ?,
                seconds_spent = seconds_spent + ?,
                status = CASE
                    WHEN status = 'completed' THEN 'completed'
                    ELSE 'started'
                END
            WHERE user_id = ?
              AND material_id = ?
        ");

        if (!$stmt) {
            jsonResponse(false, 'Unable to update viewing time.', [], 500);
        }

        $stmt->bind_param('sisi', $now, $seconds, $userId, $materialId);
    }

    if ($action === 'complete') {
        $stmt = $conn->prepare("
            UPDATE topic_material_progress
            SET
                last_viewed_at = ?,
                progress_percentage = 100,
                status = 'completed',
                completed_at = COALESCE(completed_at, ?)
            WHERE user_id = ?
              AND material_id = ?
        ");

        if (!$stmt) {
            jsonResponse(false, 'Unable to complete material.', [], 500);
        }

        $stmt->bind_param('sssi', $now, $now, $userId, $materialId);
    }

    if ($action === 'download') {
        $stmt = $conn->prepare("
            UPDATE topic_material_progress
            SET
                last_viewed_at = ?,
                downloaded_at = ?,
                total_downloads = total_downloads + 1,
                status = CASE
                    WHEN status = 'completed' THEN 'completed'
                    WHEN status = 'not_started' THEN 'started'
                    ELSE status
                END
            WHERE user_id = ?
              AND material_id = ?
        ");

        if (!$stmt) {
            jsonResponse(false, 'Unable to record material download.', [], 500);
        }

        $stmt->bind_param('sssi', $now, $now, $userId, $materialId);
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            jsonResponse(false, 'Unable to save material progress: ' . $error, [], 500);
        }

        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT
            progress_percentage,
            total_views,
            seconds_spent,
            status,
            completed_at,
            downloaded_at,
            total_downloads
        FROM topic_material_progress
        WHERE user_id = ?
          AND material_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(true, 'Progress saved.');
    }

    $stmt->bind_param('si', $userId, $materialId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    jsonResponse(
        true,
        $action === 'complete'
            ? 'Material marked as completed.'
            : 'Material progress updated.',
        [
            'progress' => (float)($row['progress_percentage'] ?? 0),
            'status' => (string)($row['status'] ?? 'started'),
            'views' => (int)($row['total_views'] ?? 0),
            'seconds_spent' => (int)($row['seconds_spent'] ?? 0),
            'completed_at' => $row['completed_at'] ?? null,
            'downloaded_at' => $row['downloaded_at'] ?? null,
            'downloads' => (int)($row['total_downloads'] ?? 0),
        ]
    );

} catch (Throwable $e) {
    error_log('update_material_progress.php: ' . $e->getMessage());

    jsonResponse(
        false,
        'An unexpected error occurred while saving material progress.',
        [],
        500
    );
}
