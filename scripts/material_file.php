<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connector.php';

date_default_timezone_set('Africa/Nairobi');

function failFile(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    failFile('Database connection failed.', 500);
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
    failFile('Your student session has expired.', 401);
}

$materialId = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'inline') === 'download'
    ? 'download'
    : 'inline';

if ($materialId <= 0) {
    failFile('Invalid material.', 422);
}

$stmt = $conn->prepare("
    SELECT
        tm.document,
        tm.original_filename,
        tm.mime_type,
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
    failFile('Unable to load material.', 500);
}

$stmt->bind_param('i', $materialId);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    failFile('Material not found.', 404);
}

$courseId = (int)$material['course_id'];
$intakeId = (int)$material['intake_id'];

$sql = "
    SELECT 1
    FROM course_registration
    WHERE user_id = ?
      AND course_id = ?
";

if ($intakeId > 0) {
    $sql .= " AND intake = ? ";
}

$sql .= " LIMIT 1 ";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    failFile('Unable to validate course access.', 500);
}

if ($intakeId > 0) {
    $stmt->bind_param('sii', $userId, $courseId, $intakeId);
} else {
    $stmt->bind_param('si', $userId, $courseId);
}

$stmt->execute();
$allowed = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$allowed) {
    failFile('You do not have access to this material.', 403);
}

$document = basename((string)($material['document'] ?? ''));

if ($document === '') {
    failFile('This material has no uploaded file.', 404);
}

$base = realpath(__DIR__ . '/../course_materials');
$path = realpath(__DIR__ . '/../course_materials/' . $document);

if (
    $base === false
    || $path === false
    || strncmp($path, $base, strlen($base)) !== 0
    || !is_file($path)
) {
    failFile('Material file not found.', 404);
}

$ext = strtolower(pathinfo($document, PATHINFO_EXTENSION));

$mimeMap = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'txt' => 'text/plain; charset=utf-8',
];

$mime = trim((string)($material['mime_type'] ?? ''));

if ($mime === '') {
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
}

$displayName = trim((string)($material['original_filename'] ?? ''));

if ($displayName === '') {
    $displayName = $document;
}

if ($mode === 'download') {
    $now = date('Y-m-d H:i:s');

    $progressStmt = $conn->prepare("
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
        ) VALUES (?, ?, ?, ?, 0, 0, 0, 'started', NULL, ?, 1)
        ON DUPLICATE KEY UPDATE
            last_viewed_at = VALUES(last_viewed_at),
            downloaded_at = VALUES(downloaded_at),
            total_downloads = total_downloads + 1,
            status = CASE
                WHEN status = 'completed' THEN 'completed'
                ELSE 'started'
            END
    ");

    if ($progressStmt) {
        $progressStmt->bind_param(
            'sisss',
            $userId,
            $materialId,
            $now,
            $now,
            $now
        );

        $progressStmt->execute();
        $progressStmt->close();
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');

$disposition = $mode === 'download'
    ? 'attachment'
    : 'inline';

header(
    'Content-Disposition: '
    . $disposition
    . '; filename="'
    . str_replace('"', '', basename($displayName))
    . '"'
);

readfile($path);
exit;
