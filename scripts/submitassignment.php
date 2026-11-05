<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connector.php';

date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(bool $success, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        array_merge(
            ['success' => $success, 'message' => $message],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function columns(mysqli $conn, string $table): array
{
    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");

    if (!$result) {
        return [];
    }

    $out = [];

    while ($row = $result->fetch_assoc()) {
        $out[(string)$row['Field']] = $row;
    }

    return $out;
}

function dynamicInsert(mysqli $conn, string $table, array $values): int
{
    if (!$values) {
        throw new RuntimeException('No values were supplied for insertion.');
    }

    $cols = array_keys($values);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));

    $quotedCols = implode(
        ',',
        array_map(
            fn($col) => '`' . str_replace('`', '``', $col) . '`',
            $cols
        )
    );

    $sql = "INSERT INTO `{$table}` ({$quotedCols}) VALUES ({$placeholders})";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $types = '';
    $bindValues = [];

    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }

        $bindValues[] = $value;
    }

    $refs = [$types];

    foreach ($bindValues as $key => &$value) {
        $refs[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }

    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

try {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        respond(false, 'Database connection failed.', [], 500);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Invalid request method.', [], 405);
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
        respond(false, 'Your student session has expired.', [], 401);
    }

    $assignmentId = (int)($_POST['assignment_id'] ?? 0);

    if ($assignmentId <= 0) {
        respond(false, 'Invalid assignment.', [], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment and student access
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            a.*,
            cu.course_id
        FROM assignments a
        INNER JOIN course_unit cu ON cu.id = a.course_unit
        WHERE a.id = ?
          AND COALESCE(a.status, 'active') = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        respond(false, 'Unable to load assignment: ' . $conn->error, [], 500);
    }

    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();

    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        respond(false, 'Assignment not found.', [], 404);
    }

    $courseId = (int)$assignment['course_id'];
    $moduleId = (int)$assignment['course_unit'];
    $intakeId = (int)$assignment['intake'];

    $stmt = $conn->prepare("
        SELECT 1
        FROM course_registration
        WHERE user_id = ?
          AND course_id = ?
          AND intake = ?
        LIMIT 1
    ");

    if (!$stmt) {
        respond(false, 'Unable to validate your course registration.', [], 500);
    }

    $stmt->bind_param('sii', $userId, $courseId, $intakeId);
    $stmt->execute();

    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$allowed) {
        respond(false, 'You are not registered for this assignment.', [], 403);
    }

    /*
    |--------------------------------------------------------------------------
    | Attempts and deadline
    |--------------------------------------------------------------------------
    */

    $maxAttempts = max(1, (int)($assignment['max_attempts'] ?? 1));

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM handed_in
        WHERE assignment_id = ?
          AND user_id = ?
    ");

    if (!$stmt) {
        respond(false, 'Unable to validate submission attempts.', [], 500);
    }

    $stmt->bind_param('is', $assignmentId, $userId);
    $stmt->execute();

    $attempts = (int)(
        $stmt->get_result()->fetch_assoc()['total']
        ?? 0
    );

    $stmt->close();

    if ($attempts >= $maxAttempts) {
        respond(
            false,
            'You have reached the maximum number of submission attempts for this assignment.',
            [],
            422
        );
    }

    $due = !empty($assignment['date_due'])
        ? strtotime((string)$assignment['date_due'])
        : false;

    $allowLate = (int)($assignment['allow_late_submission'] ?? 0) === 1;

    if ($due !== false && $due < time() && !$allowLate) {
        respond(
            false,
            'The submission deadline has passed and late submissions are not allowed.',
            [],
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate submission type
    |--------------------------------------------------------------------------
    */

    $allowedType = (string)($assignment['submission_type'] ?? 'file');

    if (!in_array(
        $allowedType,
        ['file', 'text', 'link', 'file_text', 'any'],
        true
    )) {
        $allowedType = 'file';
    }

    $textResponse = trim((string)($_POST['text_response'] ?? ''));
    $submissionUrl = trim((string)($_POST['submission_url'] ?? ''));

    $uploadedFile = $_FILES['submission_file'] ?? null;

    $hasFile =
        is_array($uploadedFile)
        && isset($uploadedFile['error'])
        && (int)$uploadedFile['error'] === UPLOAD_ERR_OK;

    $hasText = $textResponse !== '';
    $hasLink = $submissionUrl !== '';

    if ($allowedType === 'file' && !$hasFile) {
        respond(false, 'Please upload your completed assignment file.', [], 422);
    }

    if ($allowedType === 'text' && !$hasText) {
        respond(false, 'Please enter your assignment response.', [], 422);
    }

    if ($allowedType === 'link' && !$hasLink) {
        respond(false, 'Please enter your assignment submission link.', [], 422);
    }

    if ($allowedType === 'file_text' && !$hasFile && !$hasText) {
        respond(false, 'Please upload a file or enter a text response.', [], 422);
    }

    if ($allowedType === 'any' && !$hasFile && !$hasText && !$hasLink) {
        respond(false, 'Please provide a file, text response, or link.', [], 422);
    }

    if ($hasLink && !filter_var($submissionUrl, FILTER_VALIDATE_URL)) {
        respond(false, 'Please enter a valid submission link.', [], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload file
    |--------------------------------------------------------------------------
    */

    $storedFilename = '';

    if ($hasFile) {
        $uploadDir = realpath(__DIR__ . '/../course_materials');

        if ($uploadDir === false) {
            $uploadDir = __DIR__ . '/../course_materials';

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    respond(false, 'Unable to create the assignment upload directory.', [], 500);
                }
            }
        }

        $original = basename((string)$uploadedFile['name']);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        $blocked = [
            'php', 'php3', 'php4', 'php5', 'phtml',
            'phar', 'cgi', 'pl', 'py', 'sh', 'exe',
            'bat', 'cmd', 'com', 'msi', 'js'
        ];

        if ($extension !== '' && in_array($extension, $blocked, true)) {
            respond(false, 'This file type is not allowed.', [], 422);
        }

        $maxBytes = 25 * 1024 * 1024;

        if ((int)$uploadedFile['size'] > $maxBytes) {
            respond(false, 'The assignment file must not exceed 25 MB.', [], 422);
        }

        $safeBase = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            pathinfo($original, PATHINFO_FILENAME)
        );

        $safeBase = trim((string)$safeBase, '-_.');

        if ($safeBase === '') {
            $safeBase = 'assignment';
        }

        $storedFilename =
            'submission-' .
            $assignmentId .
            '-' .
            preg_replace('/[^A-Za-z0-9_-]+/', '', $userId) .
            '-' .
            date('YmdHis') .
            '-' .
            bin2hex(random_bytes(3)) .
            ($extension !== '' ? '.' . $extension : '');

        $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $storedFilename;

        if (!move_uploaded_file(
            (string)$uploadedFile['tmp_name'],
            $destination
        )) {
            respond(false, 'Unable to save the uploaded assignment file.', [], 500);
        }
        require_once __DIR__ . '/upload_compressor.php';
        eh_compress_upload($destination);
    }

    /*
    |--------------------------------------------------------------------------
    | Determine actual submitted format
    |--------------------------------------------------------------------------
    */

    if ($hasFile && !$hasText && !$hasLink) {
        $actualType = 'file';
    } elseif (!$hasFile && $hasText && !$hasLink) {
        $actualType = 'text';
    } elseif (!$hasFile && !$hasText && $hasLink) {
        $actualType = 'link';
    } else {
        $actualType = 'mixed';
    }

    /*
    |--------------------------------------------------------------------------
    | Insert using columns available in this installation
    |--------------------------------------------------------------------------
    */

    $cols = columns($conn, 'handed_in');

    if (!$cols) {
        respond(false, 'The assignment submissions table is unavailable.', [], 500);
    }

    $now = date('Y-m-d H:i:s');
    $attemptNumber = $attempts + 1;

    $candidate = [
        'assignment_id'   => $assignmentId,
        'user_id'         => $userId,
        'course_id'       => $courseId,
        'course'          => $courseId,
        'course_unit_id'  => $moduleId,
        'course_unit'     => $moduleId,
        'themodule'       => $moduleId,

        'submission_type' => $actualType,
        'file_uploaded'   => $storedFilename,
        'document'        => $storedFilename,
        'text_response'   => $textResponse,
        'submission_url'  => $submissionUrl,

        'status'          => 'submitted',
        'attempt_number'  => $attemptNumber,

        'date_handed_in'  => $now,
        'submitted_at'    => $now,
        'date_uploaded'   => $now,
        'created_at'      => $now,
        'updated_at'      => $now,
    ];

    $insert = [];

    foreach ($candidate as $column => $value) {
        if (isset($cols[$column])) {
            $insert[$column] = $value;
        }
    }

    /*
     * Legacy tables sometimes require expectedfiletype/file_type.
     * Preserve a numeric value when the column exists.
     */
    if (isset($cols['expectedfiletype'])) {
        $insert['expectedfiletype'] = (int)($_POST['expectedfiletype'] ?? 0);
    }

    if (isset($cols['file_type'])) {
        $insert['file_type'] = (int)($_POST['expectedfiletype'] ?? 0);
    }

    dynamicInsert($conn, 'handed_in', $insert);

    respond(
        true,
        'Assignment submitted successfully.',
        [
            'attempt_number' => $attemptNumber,
            'max_attempts' => $maxAttempts,
        ]
    );

} catch (Throwable $e) {
    error_log('submitassignment.php: ' . $e->getMessage());

    respond(
        false,
        'An unexpected error occurred while submitting the assignment.',
        [],
        500
    );
}
