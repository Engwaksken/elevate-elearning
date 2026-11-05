<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connector.php';

$systemname = $systemname ?? '';

date_default_timezone_set('Africa/Nairobi');

function cc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cc_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cc_require_db(): mysqli
{
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        throw new RuntimeException('Database connection failed.');
    }
    return $conn;
}

function cc_current_user(): array
{
    global $systemname;
    $uid  = trim((string)($_SESSION[$systemname . 'leido'] ?? $_SESSION['unique_id'] ?? ''));
    $role = trim((string)($_SESSION[$systemname . 'letype'] ?? $_SESSION['role'] ?? ''));
    if ($uid === '' || $role === '') {
        cc_json(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }
    return [$uid, $role];
}

function cc_require_instructor_role(string $role): void
{
    if (!in_array($role, ['xybrtw', 'xtfbry'], true)) {
        cc_json(['success' => false, 'message' => 'You are not authorised to manage course content.'], 403);
    }
}

function cc_valid_class_mode(string $value): string
{
    return in_array($value, ['online', 'physical', 'both'], true) ? $value : 'both';
}

function cc_valid_status(string $value): string
{
    return in_array($value, ['active', 'inactive'], true) ? $value : 'active';
}

function cc_valid_material_type(string $value): string
{
    $allowed = ['video', 'document', 'powerpoint', 'link', 'text', 'audio', 'image'];
    return in_array($value, $allowed, true) ? $value : 'document';
}

function cc_clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('/^https?:\/\//i', $url)) $url = 'https://' . $url;
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function cc_safe_filename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'file';
    $name = trim($name, '-.');
    return $name !== '' ? $name : 'file';
}

function cc_upload_file(array $file, string $prefix = 'mat_'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    $original = (string)($file['name'] ?? '');
    $tmp      = (string)($file['tmp_name'] ?? '');
    if ($original === '' || $tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = [
        'pdf','doc','docx','ppt','pptx','xls','xlsx','csv','txt','rtf','odt','ods','odp',
        'jpg','jpeg','png','gif','webp','mp4','webm','avi','mp3','wav','m4a','zip','rar','7z'
    ];
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported file format.');
    }

    $dir = dirname(__DIR__) . '/course_materials/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create material upload directory.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Material upload directory is not writable.');
    }

    $stored = $prefix . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . $stored)) {
        throw new RuntimeException('Unable to save uploaded file.');
    }
    require_once __DIR__ . '/upload_compressor.php';
    eh_compress_upload($dir . $stored);
    clearstatcache(true, $dir . $stored);

    $mime = '';
    if (function_exists('mime_content_type')) {
        $mime = (string)(mime_content_type($dir . $stored) ?: '');
    }

    return [
        'stored' => $stored,
        'original' => $original,
        'mime' => $mime,
        'size' => (int)(@filesize($dir . $stored) ?: ($file['size'] ?? 0)), // size after compression
        'path' => $dir . $stored,
    ];
}

function cc_check_instructor_course_access(mysqli $conn, string $uid, string $role, int $courseId): bool
{
    if ($role === 'xybrtw') return true;
    $stmt = $conn->prepare('SELECT 1 FROM course_registration_lecturers WHERE user_id = ? AND course_id = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('si', $uid, $courseId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

function cc_module_belongs_to_course(mysqli $conn, int $moduleId, int $courseId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM course_unit WHERE id = ? AND course_id = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ii', $moduleId, $courseId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}
