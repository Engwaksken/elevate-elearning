<?php
declare(strict_types=1);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* =========================================================
   TIMEZONE
========================================================= */

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Africa/Nairobi');
} else {
    date_default_timezone_set('Africa/Nairobi');
}

$timezone = new DateTimeZone('Africa/Nairobi');
$now = new DateTime('now', $timezone);

$GLOBALS['ms_sent'] = false;

/* =========================================================
   UTF-8 SAFE HELPERS
========================================================= */

function safe_string($value): string
{
    if ($value === null) {
        return '';
    }

    $value = (string)$value;

    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding(
            $value,
            'UTF-8',
            'UTF-8, ISO-8859-1, Windows-1252'
        );
    }

    if (function_exists('iconv')) {
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($cleaned !== false) {
            $value = $cleaned;
        }
    }

    return $value;
}

function utf8ize($mixed)
{
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
        return $mixed;
    }

    if (is_object($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed->$key = utf8ize($value);
        }
        return $mixed;
    }

    if (is_string($mixed)) {
        return safe_string($mixed);
    }

    return $mixed;
}

function send_json(array $data, int $code = 200): void
{
    $GLOBALS['ms_sent'] = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }

    $data = utf8ize($data);

    $out = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($out === false) {
        $out = json_encode([
            'success' => false,
            'error'   => 'JSON encode failed: ' . json_last_error_msg()
        ]);
    }

    echo $out;
    exit;
}

function apiOk(array $data = []): void
{
    send_json(array_merge(['success' => true], $data));
}

function apiError(string $msg, int $code = 400, array $extra = []): void
{
    send_json(array_merge(['success' => false, 'error' => $msg], $extra), $code);
}

/* =========================================================
   SHUTDOWN / EXCEPTION HANDLING
========================================================= */

register_shutdown_function(function () {
    if (!empty($GLOBALS['ms_sent'])) {
        return;
    }

    $err = error_get_last();

    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        send_json([
            'success' => false,
            'error'   => 'PHP fatal: ' . $err['message'],
            'file'    => basename($err['file']),
            'line'    => $err['line'],
        ], 500);
    }

    send_json([
        'success' => false,
        'error'   => 'Script ended without output. Check PHP error log near process-mindset.php.',
    ], 500);
});

set_exception_handler(function (Throwable $e) {
    apiError('Unhandled exception: ' . $e->getMessage(), 500);
});

/* =========================================================
   BASIC HELPERS
========================================================= */

function clean($val, int $max = 5000): string
{
    $text = trim(strip_tags(safe_string($val)));

    return function_exists('mb_substr')
        ? mb_substr($text, 0, $max, 'UTF-8')
        : substr($text, 0, $max);
}

function cleanInt($val, int $default = 0): int
{
    if ($val === null || $val === '') {
        return $default;
    }

    return is_numeric($val) ? (int)$val : $default;
}

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");

    return $res && $res->num_rows > 0;
}

function fetch_all_assoc(mysqli_stmt $stmt): array
{
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res) {
            return $res->fetch_all(MYSQLI_ASSOC);
        }
    }

    $stmt->store_result();
    $meta = $stmt->result_metadata();

    if (!$meta) {
        return [];
    }

    $row = [];
    $bind = [];

    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }

    call_user_func_array([$stmt, 'bind_result'], $bind);

    $rows = [];

    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $k => $v) {
            $copy[$k] = $v;
        }
        $rows[] = $copy;
    }

    return $rows;
}

function encode_json_or_fail(array $data): string
{
    $json = json_encode(
        utf8ize($data),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        apiError('Could not encode responses: ' . json_last_error_msg(), 500);
    }

    return $json;
}

function decode_json_array($json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode(safe_string($json), true);

    return is_array($decoded) ? $decoded : [];
}

function sanitize_response_array(array $data): array
{
    array_walk_recursive($data, function (&$v) {
        if (is_string($v)) {
            $v = clean($v);
        }
    });

    return $data;
}

function get_existing_progress(mysqli $conn, string $userUnique, int $moduleId): array
{
    $stmt = $conn->prepare("
        SELECT module_id, step_index, responses, points, status, completed_at
        FROM mindset_progress
        WHERE user_unique = ?
          AND module_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('si', $userUnique, $moduleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: [];
}

function get_module_points(mysqli $conn, int $moduleId, int $fallback = 0): int
{
    if (!table_exists($conn, 'mindset_questions')) {
        return $fallback;
    }

    $stmt = $conn->prepare("
        SELECT points
        FROM mindset_questions
        WHERE module_id = ?
          AND step_type = 'complete'
          AND is_active = 1
          AND points > 0
        ORDER BY step_order DESC, id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['points']) ? (int)$row['points'] : $fallback;
}

/* =========================================================
   LOAD CONNECTOR SAFELY
========================================================= */

$connector = __DIR__ . '/connector.php';

if (!file_exists($connector)) {
    apiError('connector.php not found in the scripts folder.', 500);
}

ob_start();

try {
    require_once $connector;
} catch (Throwable $e) {
    $connOut = ob_get_clean();

    apiError(
        'connector.php threw: ' . $e->getMessage(),
        500,
        ['connector_output' => substr((string)$connOut, 0, 2000)]
    );
}

$connectorOutput = ob_get_clean();

if (!isset($conn) || !($conn instanceof mysqli)) {
    apiError(
        'connector.php did not produce a valid $conn mysqli connection.',
        500,
        ['connector_output' => substr((string)$connectorOutput, 0, 2000)]
    );
}

if ($conn->connect_errno) {
    apiError('DB connect failed: ' . $conn->connect_error, 500);
}

$conn->set_charset('utf8mb4');
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

if (!isset($systemname) || trim((string)$systemname) === '') {
    $systemname = 'Elevate Her 360';
}

/* =========================================================
   SESSION
========================================================= */

$user_unique = $_SESSION[$systemname . 'leido']
    ?? $_SESSION['unique_id']
    ?? $_SESSION['user_unique']
    ?? '';

$action = clean($_POST['action'] ?? $_GET['action'] ?? '', 50);

if ($action === '') {
    apiError('Missing action parameter.');
}

$publicActions = ['load_questions', 'load_community', 'ping', 'debug_session'];

if (!in_array($action, $publicActions, true) && $user_unique === '') {
    apiError('Not authenticated. Please login again.', 401);
}

/* =========================================================
   QUICK ACTIONS
========================================================= */

if ($action === 'ping') {
    apiOk([
        'message'          => 'process-mindset.php is working',
        'time'             => date('Y-m-d H:i:s'),
        'db'               => 'connected',
        'connector_output' => $connectorOutput ?: '(none)',
    ]);
}

if ($action === 'debug_session') {
    apiOk([
        'systemname'   => $systemname,
        'session_keys' => array_keys($_SESSION),
        'user_unique'  => $user_unique ?: '(empty)',
    ]);
}

/* =========================================================
   ROUTER
========================================================= */

switch ($action) {
    case 'load_questions':
        actionLoadQuestions();
        break;

    case 'load_progress':
        actionLoadProgress();
        break;

    case 'save_step':
        actionSaveStep();
        break;

    case 'complete_module':
        actionCompleteModule();
        break;

    case 'share_post':
        actionSharePost();
        break;

    case 'load_community':
        actionLoadCommunity();
        break;

    default:
        apiError('Unknown action: ' . $action);
}

/* =========================================================
   ACTION HANDLERS
========================================================= */

function actionLoadQuestions(): void
{
    global $conn;

    if (!table_exists($conn, 'mindset_questions')) {
        apiOk([
            'modules' => [],
            'warning' => 'Table mindset_questions does not exist yet.',
        ]);
    }

    $moduleId = cleanInt($_POST['module_id'] ?? $_GET['module_id'] ?? 0);

    $sql = "
        SELECT
            id,
            module_id,
            step_order,
            step_type,
            title,
            prompt,
            hint,
            quote_text,
            body_text,
            starter_text,
            is_required,
            min_length,
            min_items,
            points
        FROM mindset_questions
        WHERE is_active = 1
    ";

    if ($moduleId > 0) {
        $sql .= ' AND module_id = ' . (int)$moduleId;
    }

    $sql .= ' ORDER BY module_id ASC, step_order ASC, id ASC';

    $result = $conn->query($sql);

    if (!$result) {
        apiError('DB error loading questions: ' . $conn->error, 500);
    }

    $byModule = [];
    $modulePoints = [];

    while ($row = $result->fetch_assoc()) {
        $mid = (int)$row['module_id'];

        if (!isset($byModule[$mid])) {
            $byModule[$mid] = [];
        }

        if ((string)$row['step_type'] === 'complete' && (int)$row['points'] > 0) {
            $modulePoints[$mid] = (int)$row['points'];
        }

        $stepType = safe_string($row['step_type']);

        $byModule[$mid][] = [
            'db_id'    => (int)$row['id'],
            'type'     => $stepType,
            'title'    => safe_string($row['title']),
            'key'      => 'm' . $mid . '_' . $stepType . '_' . (int)$row['id'],
            'prompt'   => !empty($row['prompt']) ? safe_string($row['prompt']) : null,
            'hint'     => !empty($row['hint']) ? safe_string($row['hint']) : null,
            'quote'    => !empty($row['quote_text']) ? safe_string($row['quote_text']) : null,
            'body'     => !empty($row['body_text']) ? safe_string($row['body_text']) : null,
            'starter'  => !empty($row['starter_text']) ? safe_string($row['starter_text']) : null,
            'required' => ((int)$row['is_required'] === 1),
            'minLen'   => (int)$row['min_length'],
            'minItems' => (int)$row['min_items'],
            'points'   => (int)$row['points'],
            'message'  => $stepType === 'complete'
                ? safe_string($row['body_text'] ?? '')
                : null,
        ];
    }

    $meta = [
        1 => [
            'title'    => 'Who am I beyond my circumstances?',
            'theme'    => 'Self-awareness & identity',
            'duration' => '35 min',
            'points'   => 50
        ],
        2 => [
            'title'    => 'What do I actually believe about money and work?',
            'theme'    => 'Money mindset & economic self-image',
            'duration' => '40 min',
            'points'   => 60
        ],
        3 => [
            'title'    => 'Where am I going and why does it matter?',
            'theme'    => 'Goal-setting & future orientation',
            'duration' => '35 min',
            'points'   => 55
        ],
        4 => [
            'title'    => 'What do I do when things get hard?',
            'theme'    => 'Resilience & navigating setbacks',
            'duration' => '40 min',
            'points'   => 65
        ],
        5 => [
            'title'    => 'My first earning experience',
            'theme'    => 'Action & income activation',
            'duration' => '45 min',
            'points'   => 100
        ],
        6 => [
            'title'    => 'I am an earner - what is next?',
            'theme'    => 'Identity consolidation & sustained momentum',
            'duration' => '35 min',
            'points'   => 75
        ],
    ];

    $ids = $moduleId > 0 ? [$moduleId] : range(1, 6);
    $modules = [];

    foreach ($ids as $mid) {
        $m = $meta[$mid] ?? [
            'title'    => 'Module ' . $mid,
            'theme'    => '',
            'duration' => '',
            'points'   => 0
        ];

        if (isset($modulePoints[$mid])) {
            $m['points'] = $modulePoints[$mid];
        }

        $modules[] = [
            'id'       => $mid,
            'title'    => safe_string($m['title']),
            'theme'    => safe_string($m['theme']),
            'duration' => safe_string($m['duration']),
            'points'   => (int)$m['points'],
            'steps'    => $byModule[$mid] ?? [],
        ];
    }

    apiOk(['modules' => $modules]);
}

function actionLoadProgress(): void
{
    global $conn, $user_unique;

    if (!table_exists($conn, 'mindset_progress')) {
        apiOk(['progress' => []]);
    }

    $stmt = $conn->prepare("
        SELECT module_id, step_index, responses, points, status
        FROM mindset_progress
        WHERE user_unique = ?
        ORDER BY module_id ASC
    ");

    if (!$stmt) {
        apiError('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('s', $user_unique);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        apiError('Execute failed: ' . $err, 500);
    }

    $rows = fetch_all_assoc($stmt);
    $stmt->close();

    $progress = [];

    foreach ($rows as $row) {
        $progress[(int)$row['module_id']] = [
            'step_index' => (int)$row['step_index'],
            'responses'  => decode_json_array($row['responses'] ?? ''),
            'points'     => (int)$row['points'],
            'status'     => safe_string($row['status']),
        ];
    }

    apiOk(['progress' => $progress]);
}

function actionSaveStep(): void
{
    global $conn, $user_unique;

    if (!table_exists($conn, 'mindset_progress')) {
        apiError('Table mindset_progress does not exist.', 500);
    }

    $moduleId  = cleanInt($_POST['module_id'] ?? 0);
    $stepIndex = cleanInt($_POST['step_index'] ?? 0);
    $rawResp   = $_POST['responses'] ?? '{}';

    if ($moduleId < 1 || $moduleId > 6) {
        apiError('Invalid module_id.');
    }

    if ($stepIndex < 0) {
        apiError('Invalid step_index.');
    }

    $incoming = json_decode((string)$rawResp, true);

    if (!is_array($incoming)) {
        apiError('Invalid responses payload.');
    }

    $incoming = sanitize_response_array($incoming);

    $existing = get_existing_progress($conn, $user_unique, $moduleId);
    $oldResponses = decode_json_array($existing['responses'] ?? '');
    $merged = array_replace_recursive($oldResponses, $incoming);

    $cleanJson = encode_json_or_fail($merged);

    $existingStatus = safe_string($existing['status'] ?? '');
    $existingPoints = (int)($existing['points'] ?? 0);
    $existingStep   = (int)($existing['step_index'] ?? 0);

    if ($existingStatus === 'completed') {
        $saveStatus = 'completed';
        $savePoints = $existingPoints > 0 ? $existingPoints : get_module_points($conn, $moduleId, 0);
        $saveStep   = max(999, $existingStep, $stepIndex);
        $completedAtSql = 'completed_at';
    } else {
        $saveStatus = 'in_progress';
        $savePoints = $existingPoints;
        $saveStep   = max($existingStep, $stepIndex);
        $completedAtSql = 'NULL';
    }

   $stmt = $conn->prepare("
    INSERT INTO mindset_progress
        (
            user_unique,
            module_id,
            step_index,
            responses,
            points,
            status,
            completed_at
        )
    VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            {$completedAtSql}
        )

    ON DUPLICATE KEY UPDATE

        step_index = GREATEST(step_index, VALUES(step_index)),

        responses = VALUES(responses),

        points = CASE
            WHEN VALUES(points) > points THEN VALUES(points)
            ELSE points
        END,

        status = CASE
            WHEN status = 'completed' THEN 'completed'
            ELSE VALUES(status)
        END,

        completed_at = CASE
            WHEN VALUES(status) = 'completed'
            THEN COALESCE(
                completed_at,
                CONVERT_TZ(NOW(), @@session.time_zone, '+03:00')
            )
            ELSE completed_at
        END,

        updated_at = CONVERT_TZ(
            NOW(),
            @@session.time_zone,
            '+03:00'
        )
");

    if (!$stmt) {
        apiError('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('siisis', $user_unique, $moduleId, $saveStep, $cleanJson, $savePoints, $saveStatus);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        apiError('Execute failed: ' . $err, 500);
    }

    $stmt->close();

    apiOk([
        'saved'      => true,
        'module_id'  => $moduleId,
        'step_index' => $saveStep,
        'status'     => $saveStatus,
        'points'     => $savePoints
    ]);
}

function actionCompleteModule(): void
{
    global $conn, $user_unique;

    if (!table_exists($conn, 'mindset_progress')) {
        apiError('Table mindset_progress does not exist.', 500);
    }

    $moduleId = cleanInt($_POST['module_id'] ?? 0);
    $points   = cleanInt($_POST['points'] ?? 0);
    $rawResp  = $_POST['responses'] ?? '{}';

    if ($moduleId < 1 || $moduleId > 6) {
        apiError('Invalid module_id.');
    }

    if ($points < 0 || $points > 500) {
        apiError('Invalid points value.');
    }

    if ($points <= 0) {
        $points = get_module_points($conn, $moduleId, 0);
    }

    $incoming = json_decode((string)$rawResp, true);

    if (!is_array($incoming)) {
        apiError('Invalid responses payload.');
    }

    $incoming = sanitize_response_array($incoming);

    $existing = get_existing_progress($conn, $user_unique, $moduleId);
    $oldResponses = decode_json_array($existing['responses'] ?? '');
    $merged = array_replace_recursive($oldResponses, $incoming);

    $cleanJson = encode_json_or_fail($merged);

    $stepMax = 999;
    $status = 'completed';
$stmt = $conn->prepare("
    INSERT INTO mindset_progress
        (
            user_unique,
            module_id,
            step_index,
            responses,
            points,
            status,
            completed_at
        )
    VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            CONVERT_TZ(NOW(), @@session.time_zone, '+03:00')
        )

    ON DUPLICATE KEY UPDATE

        responses = VALUES(responses),

        points = CASE
            WHEN VALUES(points) > points
            THEN VALUES(points)
            ELSE points
        END,

        status = CASE
            WHEN status = 'completed'
            THEN 'completed'
            ELSE 'completed'
        END,

        step_index = GREATEST(
            step_index,
            VALUES(step_index)
        ),

        completed_at = COALESCE(
            completed_at,
            CONVERT_TZ(
                NOW(),
                @@session.time_zone,
                '+03:00'
            )
        ),

        updated_at = CONVERT_TZ(
            NOW(),
            @@session.time_zone,
            '+03:00'
        )
");

    if (!$stmt) {
        apiError('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('siiiss', $user_unique, $moduleId, $stepMax, $cleanJson, $points, $status);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        apiError('Execute failed: ' . $err, 500);
    }

    $stmt->close();

    apiOk([
        'completed' => true,
        'module_id' => $moduleId,
        'points'    => $points,
        'step_index'=> $stepMax,
        'status'    => $status
    ]);
}

function actionSharePost(): void
{
    global $conn, $user_unique;

    if (!table_exists($conn, 'mindset_community_posts')) {
        apiError('Table mindset_community_posts does not exist.', 500);
    }

    $content  = clean($_POST['content'] ?? '', 1000);
    $moduleId = cleanInt($_POST['module_id'] ?? 0);
    $postType = clean($_POST['post_type'] ?? 'reflection', 50);

    if ($content === '') {
        apiError('Content cannot be empty.');
    }

    if (!in_array($postType, ['reflection', 'identity', 'earner'], true)) {
        $postType = 'reflection';
    }

    $moduleValue = $moduleId > 0 ? $moduleId : null;

    $stmt = $conn->prepare("
        INSERT INTO mindset_community_posts
            (user_unique, module_id, post_type, content)
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {
        apiError('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('siss', $user_unique, $moduleValue, $postType, $content);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        apiError('Execute failed: ' . $err, 500);
    }

    $stmt->close();

    apiOk(['posted' => true]);
}

function actionLoadCommunity(): void
{
    global $conn;

    if (!table_exists($conn, 'mindset_community_posts')) {
        apiOk(['posts' => []]);
    }

    $limit = max(1, min(cleanInt($_POST['limit'] ?? $_GET['limit'] ?? 8), 50));

    $stmt = $conn->prepare("
        SELECT id, user_unique, module_id, post_type, content, created_at
        FROM mindset_community_posts
        ORDER BY created_at DESC
        LIMIT ?
    ");

    if (!$stmt) {
        apiError('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $limit);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        apiError('Execute failed: ' . $err, 500);
    }

    $rows = fetch_all_assoc($stmt);
    $stmt->close();

    $posts = [];

    foreach ($rows as $p) {
        $uid = safe_string($p['user_unique']);
        $visible = function_exists('mb_substr') ? mb_substr($uid, 0, 4, 'UTF-8') : substr($uid, 0, 4);
        $length = function_exists('mb_strlen') ? mb_strlen($uid, 'UTF-8') : strlen($uid);
        $rest = max(0, $length - 4);

        $posts[] = [
            'id'           => (int)$p['id'],
            'module_id'    => $p['module_id'] !== null ? (int)$p['module_id'] : null,
            'post_type'    => safe_string($p['post_type']),
            'content'      => safe_string($p['content']),
            'created_at'   => safe_string($p['created_at']),
            'display_name' => $visible . str_repeat('*', $rest),
        ];
    }

    apiOk(['posts' => $posts]);
}