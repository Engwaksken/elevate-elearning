<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/connector.php';

$systemname  = $systemname ?? "Elevate Her 360";
$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$role        = $_SESSION[$systemname . "letype"] ?? '';

date_default_timezone_set('Africa/Nairobi');

function back_msg(string $msg, string $type = "errormsg"): void {
    global $systemname;
    $_SESSION[$systemname . $type] = $msg;
    header("Location: ../?view=certificate_templates");
    exit;
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function safe_unlink(?string $relativePath): void {
    $relativePath = trim((string)($relativePath ?? ''));

    if (
        $relativePath === '' ||
        preg_match('/^https?:\/\//i', $relativePath) ||
        str_starts_with($relativePath, 'data:')
    ) {
        return;
    }

    $file = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
    $base = realpath(__DIR__ . '/../uploads/certificate_templates');

    if ($file && $base && str_starts_with($file, $base) && is_file($file)) {
        unlink($file);
    }
}

function upload_cert_file(string $field, string $uploadDir, bool $allowPdf = false): ?string {
    if (
        empty($_FILES[$field]['name']) ||
        (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ((int)$_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload failed for {$field}.");
    }

    $allowedExt = $allowPdf
        ? ['jpg', 'jpeg', 'png', 'webp', 'pdf']
        : ['jpg', 'jpeg', 'png', 'webp'];

    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception(
            $allowPdf
                ? "Invalid file type for {$field}. Only JPG, PNG, WEBP and PDF allowed."
                : "Invalid file type for {$field}. Only JPG, PNG and WEBP allowed."
        );
    }

    $name = $field . '_' . date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $name)) {
        throw new Exception("Failed to upload {$field}.");
    }

    return 'uploads/certificate_templates/' . $name;
}

function save_bg_from_data_url(string $dataUrl, string $uploadDir): ?string {
    $dataUrl = trim($dataUrl);

    if ($dataUrl === '' || !str_starts_with($dataUrl, 'data:')) {
        return null;
    }

    if (!preg_match('/^data:(image\/(png|jpeg|jpg|webp));base64,(.+)$/s', $dataUrl, $m)) {
        return null;
    }

    $ext = strtolower($m[2]) === 'jpeg' ? 'jpg' : strtolower($m[2]);
    $decoded = base64_decode($m[3], true);

    if ($decoded === false) {
        return null;
    }

    $name = 'background_' . date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;

    if (file_put_contents($uploadDir . $name, $decoded) === false) {
        return null;
    }

    return 'uploads/certificate_templates/' . $name;
}

function normalize_json(string $json, string $label): string {
    $json = trim($json);

    if ($json === '') {
        return '';
    }

    $parsed = json_decode($json, true);

    if (!is_array($parsed)) {
        throw new Exception("{$label} is malformed. Please re-open the designer and save again.");
    }

    if ($label === 'Layout data') {
        if (!isset($parsed['canvas']) || !is_array($parsed['canvas'])) {
            $parsed['canvas'] = [
                'w' => (int)($parsed['canvas_width'] ?? 900),
                'h' => (int)($parsed['canvas_height'] ?? 637),
            ];
        }

        if (!isset($parsed['elements']) || !is_array($parsed['elements'])) {
            $parsed['elements'] = [];
        }

        foreach ($parsed['elements'] as &$el) {
            if (!is_array($el)) continue;

            if (!isset($el['style']) || !is_array($el['style'])) {
                $el['style'] = [];
            }

            $defaults = [
                'fontSize'       => '22px',
                'fontWeight'     => '700',
                'fontStyle'      => 'normal',
                'textDecoration' => 'none',
                'color'          => '#222222',
                'fontFamily'     => "'Segoe UI',sans-serif",
                'textAlign'      => 'left',
                'lineHeight'     => '1.4',
                'letterSpacing'  => 'normal',
                'background'     => 'transparent',
                'border'         => 'none',
                'borderRadius'   => '0',
                'padding'        => '0',
                'textTransform'  => 'none',
            ];

            foreach ($defaults as $key => $value) {
                if (!array_key_exists($key, $el['style']) || trim((string)$el['style'][$key]) === '') {
                    $el['style'][$key] = $value;
                }
            }

            $el['x'] = (float)($el['x'] ?? 0);
            $el['y'] = (float)($el['y'] ?? 0);
            $el['w'] = (float)($el['w'] ?? 150);
            $el['h'] = (float)($el['h'] ?? 40);
        }
        unset($el);
    }

    return json_encode($parsed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    back_msg("Database connection not found.");
}

$conn->set_charset('utf8mb4');

if (!in_array($role, ['sabrtw', 'xybrtw'], true)) {
    back_msg("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_msg("Invalid request.");
}

$uploadDir = __DIR__ . '/../uploads/certificate_templates/';
ensure_dir($uploadDir);

/* DELETE */
if (isset($_POST['delete_template'])) {
    $id = (int)($_POST['delete_id'] ?? 0);

    if ($id < 1) {
        back_msg("Invalid template ID.");
    }

    $stmt = $conn->prepare("SELECT logo_path, background_path, signature_path FROM certificate_templates WHERE id = ? LIMIT 1");

    if (!$stmt) {
        back_msg("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        back_msg("Template not found.");
    }

    safe_unlink($row['logo_path'] ?? null);
    safe_unlink($row['background_path'] ?? null);
    safe_unlink($row['signature_path'] ?? null);

    $del = $conn->prepare("DELETE FROM certificate_templates WHERE id = ?");

    if (!$del) {
        back_msg("Prepare failed: " . $conn->error);
    }

    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    back_msg("Template deleted successfully.", "successmsg");
}

if (!isset($_POST['save_template'])) {
    back_msg("Invalid request.");
}

try {
    $template_id   = (int)($_POST['template_id'] ?? 0);
    $template_name = trim((string)($_POST['template_name'] ?? ''));
    $title         = trim((string)($_POST['title'] ?? 'Certificate of Completion'));
    $body_text     = trim((string)($_POST['body_text'] ?? ''));
    $footer_text   = trim((string)($_POST['footer_text'] ?? ''));
    $status        = trim((string)($_POST['status'] ?? 'active'));
    $is_default    = isset($_POST['is_default']) ? 1 : 0;
    $now           = date('Y-m-d H:i:s');

    $layout_json = normalize_json((string)($_POST['layout_json'] ?? ''), 'Layout data');
    $logos_json  = normalize_json((string)($_POST['logos_json'] ?? ''), 'Logos data');
    $bg_data_url = trim((string)($_POST['bg_data_url'] ?? ''));

    $existing_background_path = trim((string)($_POST['existing_background_path'] ?? ''));
    $existing_logo_path       = trim((string)($_POST['existing_logo_path'] ?? ''));
    $existing_signature_path  = trim((string)($_POST['existing_signature_path'] ?? ''));

    if ($template_name === '') {
        back_msg("Template name is required.");
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    $existing = null;

    if ($template_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM certificate_templates WHERE id = ? LIMIT 1");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Template not found for update.");
        }
    }

    $logo_path       = upload_cert_file('logo', $uploadDir, false);
    $signature_path  = upload_cert_file('signature', $uploadDir, false);
    $background_path = upload_cert_file('designed_certificate', $uploadDir, true);

    if ($background_path === null) {
        $background_path = upload_cert_file('background', $uploadDir, true);
    }

    if ($background_path === null && $bg_data_url !== '') {
        $background_path = save_bg_from_data_url($bg_data_url, $uploadDir);
    }

    if ($existing) {
        if ($logo_path === null) {
            $logo_path = (string)($existing['logo_path'] ?? $existing_logo_path);
        }

        if ($signature_path === null) {
            $signature_path = (string)($existing['signature_path'] ?? $existing_signature_path);
        }

        if ($background_path === null) {
            $background_path = (string)($existing['background_path'] ?? $existing_background_path);
        }
    } else {
        if ($logo_path === null) {
            $logo_path = $existing_logo_path;
        }

        if ($signature_path === null) {
            $signature_path = $existing_signature_path;
        }

        if ($background_path === null) {
            $background_path = $existing_background_path;
        }
    }

    if ($background_path === '' && $layout_json === '') {
        back_msg("Please upload a designed certificate or add a background/layout in the editor.");
    }

    if ($is_default === 1) {
        $conn->query("UPDATE certificate_templates SET is_default = 0");
        $status = 'active';
    }

    if ($existing) {
        if (!empty($existing['logo_path']) && $logo_path !== $existing['logo_path']) {
            safe_unlink($existing['logo_path']);
        }

        if (!empty($existing['signature_path']) && $signature_path !== $existing['signature_path']) {
            safe_unlink($existing['signature_path']);
        }

        if (!empty($existing['background_path']) && $background_path !== $existing['background_path']) {
            safe_unlink($existing['background_path']);
        }

        $stmt = $conn->prepare("
            UPDATE certificate_templates SET
                template_name = ?,
                logo_path = ?,
                background_path = ?,
                signature_path = ?,
                title = ?,
                body_text = ?,
                footer_text = ?,
                layout_json = ?,
                logos_json = ?,
                is_default = ?,
                status = ?,
                updated_at = ?
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssssssissi",
            $template_name,
            $logo_path,
            $background_path,
            $signature_path,
            $title,
            $body_text,
            $footer_text,
            $layout_json,
            $logos_json,
            $is_default,
            $status,
            $now,
            $template_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }

        $stmt->close();

        back_msg("Certificate template \"{$template_name}\" updated successfully.", "successmsg");
    }

    $stmt = $conn->prepare("
        INSERT INTO certificate_templates (
            template_name,
            logo_path,
            background_path,
            signature_path,
            title,
            body_text,
            footer_text,
            layout_json,
            logos_json,
            is_default,
            status,
            created_by,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssssissss",
        $template_name,
        $logo_path,
        $background_path,
        $signature_path,
        $title,
        $body_text,
        $footer_text,
        $layout_json,
        $logos_json,
        $is_default,
        $status,
        $user_unique,
        $now,
        $now
    );

    if (!$stmt->execute()) {
        throw new Exception("Insert failed: " . $stmt->error);
    }

    $stmt->close();

    back_msg("Certificate template \"{$template_name}\" saved successfully.", "successmsg");

} catch (Throwable $e) {
    back_msg($e->getMessage());
}