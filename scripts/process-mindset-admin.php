<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ini_set('display_errors', '0');
error_reporting(E_ALL);

date_default_timezone_set('Africa/Nairobi');

/* =========================================================
   JSON RESPONSE HELPERS
========================================================= */

function json_response($payload, $code = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = array())
{
    json_response(array_merge(array('success' => true), $data), 200);
}

function json_error($message, $code = 400, $extra = array())
{
    json_response(array_merge(array(
        'success' => false,
        'error'   => $message
    ), $extra), $code);
}

register_shutdown_function(function () {
    $err = error_get_last();

    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        echo json_encode(array(
            'success' => false,
            'error'   => 'Fatal PHP error: ' . $err['message'],
            'file'    => basename($err['file']),
            'line'    => $err['line']
        ));
        exit;
    }
});

set_exception_handler(function ($e) {
    json_error('Server exception: ' . $e->getMessage(), 500);
});

/* =========================================================
   LOAD CONNECTOR
========================================================= */

$connectorFile = __DIR__ . '/connector.php';

if (!file_exists($connectorFile)) {
    json_error('connector.php not found at: ' . $connectorFile, 500);
}

require_once $connectorFile;

if (!isset($conn) || !($conn instanceof mysqli)) {
    json_error('Database connection variable $conn is missing or invalid.', 500);
}

$conn->set_charset('utf8mb4');

/* =========================================================
   AUTH
========================================================= */

$systemname  = 'Elevate Her 360';
$user_unique = isset($_SESSION[$systemname . 'leido']) ? $_SESSION[$systemname . 'leido'] : '';
$role        = isset($_SESSION[$systemname . 'letype']) ? $_SESSION[$systemname . 'letype'] : '';

$adminRoles = array('sabrtw', 'xybrtw');

if ($user_unique === '' || !in_array($role, $adminRoles, true)) {
    json_error('Forbidden or session expired. Please login as admin.', 403, array(
        'debug_role' => $role
    ));
}

/* =========================================================
   HELPERS
========================================================= */

function clean_string($value, $max = 2000)
{
    $value = trim((string)$value);
    $value = strip_tags($value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

function clean_int($value, $default = 0)
{
    return is_numeric($value) ? (int)$value : $default;
}

function table_exists($conn, $table)
{
    $safe = $conn->real_escape_string($table);
    $res  = $conn->query("SHOW TABLES LIKE '{$safe}'");

    return ($res && $res->num_rows > 0);
}

function require_table($conn, $table)
{
    if (!table_exists($conn, $table)) {
        json_error("Required table '{$table}' does not exist.", 500);
    }
}

function get_next_step_order($conn, $moduleId)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(step_order), 0) + 10 AS next_order
        FROM mindset_questions
        WHERE module_id = ?
          AND is_active = 1
    ");

    if (!$stmt) {
        json_error('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $moduleId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['next_order']) ? (int)$row['next_order'] : 10;
}

/* =========================================================
   VALIDATION
========================================================= */

$allowedTypes = array(
    'intro',
    'textarea',
    'story',
    'rewrite',
    'sentence',
    'quiz',
    'data',
    'goalmap',
    'scenarios',
    'list',
    'task',
    'transcription',
    'earning',
    'earner',
    'review',
    'complete'
);

require_table($conn, 'mindset_questions');

$action = clean_string(isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : ''), 60);

/* =========================================================
   ROUTER
========================================================= */

switch ($action) {
    case 'list_questions':
        list_questions($conn);
        break;

    case 'add_question':
        save_question($conn, $allowedTypes, false);
        break;

    case 'edit_question':
        save_question($conn, $allowedTypes, true);
        break;

    case 'delete_question':
        delete_question($conn);
        break;

    case 'reorder':
        reorder_questions($conn);
        break;

    default:
        json_error('Unknown action: ' . $action, 400);
}

/* =========================================================
   ACTIONS
========================================================= */

function list_questions($conn)
{
    $moduleId = clean_int(isset($_GET['module_id']) ? $_GET['module_id'] : (isset($_POST['module_id']) ? $_POST['module_id'] : 0));

    if ($moduleId < 1 || $moduleId > 6) {
        json_error('Invalid module_id.');
    }

    $stmt = $conn->prepare("
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
            points,
            is_active,
            created_at,
            updated_at
        FROM mindset_questions
        WHERE module_id = ?
          AND is_active = 1
        ORDER BY step_order ASC, id ASC
    ");

    if (!$stmt) {
        json_error('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $moduleId);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        json_error('Query failed: ' . $err, 500);
    }

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    json_success(array(
        'questions' => $rows
    ));
}

function save_question($conn, $allowedTypes, $isEdit)
{
    $id          = clean_int(isset($_POST['id']) ? $_POST['id'] : 0);
    $moduleId    = clean_int(isset($_POST['module_id']) ? $_POST['module_id'] : 0);
    $stepOrder   = clean_int(isset($_POST['step_order']) ? $_POST['step_order'] : 0);
    $stepType    = clean_string(isset($_POST['step_type']) ? $_POST['step_type'] : 'textarea', 30);
    $title       = clean_string(isset($_POST['title']) ? $_POST['title'] : '', 255);
    $prompt      = clean_string(isset($_POST['prompt']) ? $_POST['prompt'] : '', 3000);
    $hint        = clean_string(isset($_POST['hint']) ? $_POST['hint'] : '', 500);
    $quoteText   = clean_string(isset($_POST['quote_text']) ? $_POST['quote_text'] : '', 1500);
    $bodyText    = clean_string(isset($_POST['body_text']) ? $_POST['body_text'] : '', 5000);
    $starterText = clean_string(isset($_POST['starter_text']) ? $_POST['starter_text'] : '', 255);
    $isRequired  = clean_int(isset($_POST['is_required']) ? $_POST['is_required'] : 1);
    $minLength   = clean_int(isset($_POST['min_length']) ? $_POST['min_length'] : 0);
    $minItems    = clean_int(isset($_POST['min_items']) ? $_POST['min_items'] : 0);
    $points      = clean_int(isset($_POST['points']) ? $_POST['points'] : 0);

    if ($isEdit && $id < 1) {
        json_error('Invalid question id.');
    }

    if ($moduleId < 1 || $moduleId > 6) {
        json_error('Invalid module_id.');
    }

    if (!in_array($stepType, $allowedTypes, true)) {
        json_error('Invalid step_type.');
    }

    if ($title === '') {
        json_error('Title is required.');
    }

    $isRequired = ($isRequired === 1) ? 1 : 0;

    if ($stepOrder <= 0) {
        $stepOrder = get_next_step_order($conn, $moduleId);
    }

    if ($isEdit) {
        $stmt = $conn->prepare("
            UPDATE mindset_questions
            SET
                module_id = ?,
                step_order = ?,
                step_type = ?,
                title = ?,
                prompt = ?,
                hint = ?,
                quote_text = ?,
                body_text = ?,
                starter_text = ?,
                is_required = ?,
                min_length = ?,
                min_items = ?,
                points = ?
            WHERE id = ?
              AND is_active = 1
        ");

        if (!$stmt) {
            json_error('Prepare failed: ' . $conn->error, 500);
        }

        $stmt->bind_param(
            'iisssssssiiiii',
            $moduleId,
            $stepOrder,
            $stepType,
            $title,
            $prompt,
            $hint,
            $quoteText,
            $bodyText,
            $starterText,
            $isRequired,
            $minLength,
            $minItems,
            $points,
            $id
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            json_error('Update failed: ' . $err, 500);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        json_success(array(
            'affected' => $affected,
            'message'  => 'Question updated successfully.'
        ));
    }

    $stmt = $conn->prepare("
        INSERT INTO mindset_questions (
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
            points,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    if (!$stmt) {
        json_error('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param(
        'iisssssssiiii',
        $moduleId,
        $stepOrder,
        $stepType,
        $title,
        $prompt,
        $hint,
        $quoteText,
        $bodyText,
        $starterText,
        $isRequired,
        $minLength,
        $minItems,
        $points
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        json_error('Insert failed: ' . $err, 500);
    }

    $newId = (int)$conn->insert_id;
    $stmt->close();

    json_success(array(
        'id'      => $newId,
        'message' => 'Question added successfully.'
    ));
}

function delete_question($conn)
{
    $id = clean_int(isset($_POST['id']) ? $_POST['id'] : 0);

    if ($id < 1) {
        json_error('Invalid question id.');
    }

    $stmt = $conn->prepare("
        UPDATE mindset_questions
        SET is_active = 0
        WHERE id = ?
    ");

    if (!$stmt) {
        json_error('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        json_error('Delete failed: ' . $err, 500);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        json_error('Question not found.');
    }

    json_success(array(
        'message' => 'Question deleted.'
    ));
}

function reorder_questions($conn)
{
    $raw = isset($_POST['orders']) ? $_POST['orders'] : '[]';

    $orders = json_decode((string)$raw, true);

    if (!is_array($orders)) {
        json_error('Invalid orders payload.');
    }

    $stmt = $conn->prepare("
        UPDATE mindset_questions
        SET step_order = ?
        WHERE id = ?
          AND is_active = 1
    ");

    if (!$stmt) {
        json_error('Prepare failed: ' . $conn->error, 500);
    }

    $updated = 0;

    foreach ($orders as $item) {
        $id  = clean_int(isset($item['id']) ? $item['id'] : 0);
        $ord = clean_int(isset($item['step_order']) ? $item['step_order'] : 0);

        if ($id < 1 || $ord < 1) {
            continue;
        }

        $stmt->bind_param('ii', $ord, $id);

        if ($stmt->execute()) {
            $updated += max(0, $stmt->affected_rows);
        }
    }

    $stmt->close();

    json_success(array(
        'updated' => $updated,
        'message' => 'Order updated.'
    ));
}