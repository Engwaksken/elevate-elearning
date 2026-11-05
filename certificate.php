<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/scripts/connector.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$systemname = $systemname ?? 'Elevate Her 360';

$user_unique = $_SESSION[$systemname . 'leido'] ?? '';
$user_id     = $_SESSION[$systemname . 'leid'] ?? '';
$user_role   = (string)($_SESSION[$systemname . 'letype'] ?? '');

// Super admins, admins and instructors may preview any student's certificate.
$isStaff = in_array($user_role, ['sabrtw', 'xybrtw', 'xtfbry'], true);

if ($user_unique === '' && $user_id === '') exit('Session expired. Please login again.');
if (!isset($conn) || !($conn instanceof mysqli)) exit('Database connection not found.');

function cp_e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function cp_safe_html(string $html): string {
    return strip_tags($html, '<br><b><strong><i><em><u><span>');
}

function cp_resolve_file(?string $path): string {
    $path = trim((string)($path ?? ''));
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, 'data:')) return $path;

    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = basename($path);

    $candidates = [
        __DIR__ . '/' . $path,
        __DIR__ . '/uploads/' . $base,
        __DIR__ . '/uploads/certificates/' . $base,
        __DIR__ . '/uploads/generated_certificates/' . $base,
        __DIR__ . '/uploads/templates/' . $base,
        __DIR__ . '/uploads/certificate_templates/' . $base,
        __DIR__ . '/certificates/' . $base,
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) return $file;
    }

    return '';
}

function cp_data_uri(string $file): string {
    if ($file === '') return '';
    if (preg_match('/^https?:\/\//i', $file) || str_starts_with($file, 'data:')) return $file;
    if (!is_file($file)) return '';

    $mime = mime_content_type($file) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($file));
}

function cp_px($value, float $default = 0): float {
    if (is_numeric($value)) return (float)$value;
    if (preg_match('/-?\d+(\.\d+)?/', (string)$value, $m)) return (float)$m[0];
    return $default;
}

function cp_style(array $el): array {
    return is_array($el['style'] ?? null) ? $el['style'] : [];
}

function cp_pick_number(array $el, array $keys, float $fallback = 0): float {
    $style = cp_style($el);

    foreach ($keys as $key) {
        if (isset($el[$key]) && trim((string)$el[$key]) !== '') return cp_px($el[$key], $fallback);
        if (isset($style[$key]) && trim((string)$style[$key]) !== '') return cp_px($style[$key], $fallback);
    }

    return $fallback;
}

function cp_pick_style(array $el, array $keys, string $fallback = ''): string {
    $style = cp_style($el);

    foreach ($keys as $key) {
        if (isset($style[$key]) && trim((string)$style[$key]) !== '') return trim((string)$style[$key]);
        if (isset($el[$key]) && trim((string)$el[$key]) !== '') return trim((string)$el[$key]);
    }

    return $fallback;
}

function cp_font_family(string $font): string {
    $font = trim($font);

    if ($font === '') return 'Garet, sans-serif';

    if (str_contains($font, ',') || str_starts_with($font, "'") || str_starts_with($font, '"')) {
        return $font;
    }

    return "'" . str_replace("'", '', $font) . "', Garet, sans-serif";
}

function cp_normalize_field(string $field): string {
    $field = trim($field);
    if ($field === '') return '';
    if ($field[0] !== '{') $field = '{' . trim($field, '{}') . '}';
    return $field;
}

function cp_replace_fields(string $text, array $fields): string {
    return strtr($text, $fields);
}

/**
 * Join name parts (first, other/middle, last) skipping empties and extra spaces.
 */
function cp_full_name(...$parts): string {
    $clean = [];
    foreach ($parts as $p) {
        $p = trim(preg_replace('/\s+/u', ' ', (string)($p ?? '')));
        if ($p !== '') $clean[] = $p;
    }
    return implode(' ', $clean);
}

/**
 * True when the element is just the student name placeholder (not a sentence containing it).
 */
function cp_is_name_element(array $el): bool {
    if (cp_normalize_field((string)($el['field'] ?? '')) === '{student_name}') return true;

    $raw = trim(strip_tags((string)($el['html'] ?? $el['text'] ?? $el['content'] ?? $el['value'] ?? '')));
    return in_array($raw, ['{student_name}', '{{student_name}}'], true);
}

/**
 * Shrink a px font size so a single-line text fits the box width.
 * Uses a conservative average glyph width (uppercase-heavy names in wide fonts).
 */
function cp_fit_font_size(string $fontSize, string $text, float $boxWidth): string {
    if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*px\s*$/i', $fontSize, $m)) return $fontSize;

    $size  = (float)$m[1];
    $chars = max(1, mb_strlen($text, 'UTF-8'));
    $avgGlyph = 0.68; // em per character

    $needed = $chars * $size * $avgGlyph;
    if ($boxWidth <= 0 || $needed <= $boxWidth) return $fontSize;

    $fitted = max(10.0, floor(($boxWidth / ($chars * $avgGlyph)) * 10) / 10);
    return $fitted . 'px';
}

function cp_render_text(array $el, array $fields): string {
    $field = cp_normalize_field((string)($el['field'] ?? ''));

    if ($field !== '' && isset($fields[$field])) {
        return cp_e($fields[$field]);
    }

    $html = (string)($el['html'] ?? '');
    $text = (string)($el['text'] ?? $el['content'] ?? $el['value'] ?? '');

    if ($html !== '') {
        return cp_safe_html(cp_replace_fields($html, $fields));
    }

    return nl2br(cp_e(cp_replace_fields($text, $fields)));
}

function cp_render_layout(string $layoutJson, array $fields, array $assets, array $logosMap, int &$canvasW, int &$canvasH): string {
    $layout = json_decode($layoutJson, true);

    if (!is_array($layout)) {
        exit('Template layout_json is invalid.');
    }

    $canvasW = (int)($layout['canvas']['w'] ?? $layout['canvas']['width'] ?? $layout['canvas_width'] ?? $layout['width'] ?? 900);
    $canvasH = (int)($layout['canvas']['h'] ?? $layout['canvas']['height'] ?? $layout['canvas_height'] ?? $layout['height'] ?? 637);

    if ($canvasW <= 0) $canvasW = 900;
    if ($canvasH <= 0) $canvasH = 637;

    $elements = $layout['elements'] ?? $layout['items'] ?? $layout['objects'] ?? [];
    if (!is_array($elements)) $elements = [];

    $html = "<div class='cert-canvas' style='width:{$canvasW}px;height:{$canvasH}px;'>";

    if ($assets['background'] !== '') {
        $html .= "<img src='" . cp_e($assets['background']) . "' class='cert-bg'>";
    }

    foreach ($elements as $el) {
        if (!is_array($el)) continue;

        $type = strtolower((string)($el['type'] ?? 'text'));

        $x = cp_pick_number($el, ['x', 'left', 'pos_x'], 0);
        $y = cp_pick_number($el, ['y', 'top', 'pos_y'], 0);
        $w = cp_pick_number($el, ['w', 'width'], 150);
        $h = cp_pick_number($el, ['h', 'height'], 40);

        $rotation = cp_pick_number($el, ['rotation', 'rotate'], 0);
        $zIndex   = (int)cp_pick_number($el, ['zIndex', 'z_index'], 5);
        $opacity  = cp_pick_style($el, ['opacity'], '1');

        $fontSize       = cp_pick_style($el, ['fontSize', 'font_size'], '22px');
        $fontWeight     = cp_pick_style($el, ['fontWeight', 'font_weight'], '700');
        $fontStyle      = cp_pick_style($el, ['fontStyle', 'font_style'], 'normal');
        $textDecoration = cp_pick_style($el, ['textDecoration', 'text_decoration'], 'none');
        $color          = cp_pick_style($el, ['color', 'fontColor', 'font_color'], '#222222');
        $fontFamily     = cp_font_family(cp_pick_style($el, ['fontFamily', 'font_family'], 'Garet, sans-serif'));
        $textAlign      = cp_pick_style($el, ['textAlign', 'text_align', 'align'], 'left');
        $lineHeight     = cp_pick_style($el, ['lineHeight', 'line_height'], '1.2');
        $letterSpacing  = cp_pick_style($el, ['letterSpacing', 'letter_spacing'], 'normal');
        $background     = cp_pick_style($el, ['backgroundColor', 'background'], 'transparent');
        $border         = cp_pick_style($el, ['border'], 'none');
        $borderRadius   = cp_pick_style($el, ['borderRadius', 'border_radius'], '0');
        $padding        = cp_pick_style($el, ['padding'], '0');
        $textTransform  = cp_pick_style($el, ['textTransform', 'text_transform'], 'none');

        $html .= "
        <div class='cert-element'
             style='
                left:{$x}px;
                top:{$y}px;
                width:{$w}px;
                height:{$h}px;
                z-index:{$zIndex};
                opacity:" . cp_e($opacity) . ";
                transform:rotate({$rotation}deg);
             '>";

        if ($type === 'logo' || $type === 'image' || $type === 'signature') {
            $src = (string)($el['logoSrc'] ?? $el['src'] ?? $el['path'] ?? $el['url'] ?? '');

            if ($type === 'signature' && $assets['signature'] !== '') {
                $src = $assets['signature'];
            }

            if ($src === '' && !empty($el['logoId'])) {
                $logoId = (string)$el['logoId'];
                if (!empty($logosMap[$logoId]['src'])) {
                    $src = (string)$logosMap[$logoId]['src'];
                } elseif (!empty($logosMap[$logoId]['path'])) {
                    $src = cp_data_uri(cp_resolve_file((string)$logosMap[$logoId]['path']));
                }
            }

            if ($src && !preg_match('/^data:|^https?:\/\//i', $src)) {
                $src = cp_data_uri(cp_resolve_file($src));
            }

            if ($src !== '') {
                $html .= "<img src='" . cp_e($src) . "' class='cert-logo'>";
            }
        } else {
            $renderText = cp_render_text($el, $fields);
            $whiteSpace = 'pre-wrap';

            // Keep the full student name on one line and scale it down to fit the box,
            // so long (three-part) names are never wrapped out of view or clipped.
            if (cp_is_name_element($el)) {
                $whiteSpace = 'nowrap';
                $fontSize   = cp_fit_font_size($fontSize, (string)($fields['{student_name}'] ?? ''), $w - (2 * cp_px($padding, 0)));
            }

            $html .= "
            <div class='cert-text'
                 style='
                    white-space:{$whiteSpace};
                    font-size:" . cp_e($fontSize) . ";
                    font-weight:" . cp_e($fontWeight) . ";
                    font-style:" . cp_e($fontStyle) . ";
                    text-decoration:" . cp_e($textDecoration) . ";
                    color:" . cp_e($color) . ";
                    font-family:" . cp_e($fontFamily) . ";
                    text-align:" . cp_e($textAlign) . ";
                    line-height:" . cp_e($lineHeight) . ";
                    letter-spacing:" . cp_e($letterSpacing) . ";
                    background:" . cp_e($background) . ";
                    border:" . cp_e($border) . ";
                    border-radius:" . cp_e($borderRadius) . ";
                    padding:" . cp_e($padding) . ";
                    text-transform:" . cp_e($textTransform) . ";
                 '>
                {$renderText}
            </div>";
        }

        $html .= "</div>";
    }

    $html .= "</div>";

    return $html;
}



function cp_log_activity(mysqli $conn, int $certificateId, string $userId, int $courseId, int $intakeId, string $action): void {
    if (!in_array($action, ['view', 'download'], true)) return;

  

    $stmt = $conn->prepare("
        INSERT INTO certificate_activity (certificate_id, user_id, course_id, intake_id, action)
        VALUES (?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param('isiis', $certificateId, $userId, $courseId, $intakeId, $action);
        $stmt->execute();
        $stmt->close();
    }
}

$id = (int)($_GET['id'] ?? 0);
$action = strtolower(trim((string)($_GET['action'] ?? 'view')));
$action = in_array($action, ['view', 'download'], true) ? $action : 'view';

if ($id < 1) exit('Invalid certificate.');

$sql = "
    SELECT
        c.*,
        i.name AS intake_name,
        i.date_launching AS course_date_from,
        i.date_ending AS course_date_to,
        t.id AS template_id,
        t.template_name,
        t.logo_path,
        t.background_path,
        t.signature_path,
        t.title,
        t.body_text,
        t.footer_text,
        t.layout_json,
        t.logos_json,
        u.fname       AS u_fname,
        u.other_name  AS u_other_name,
        u.lname       AS u_lname
    FROM certificates c
    INNER JOIN certificate_templates t
        ON t.is_default = 1
        AND t.status = 'active'
    LEFT JOIN intakes i
        ON i.id = c.intake_id
    LEFT JOIN users u
        ON u.unique_id = c.user_id
    WHERE c.id = ?
      AND (c.user_id = ? OR ? = 1)
      AND c.status IN ('active','issued','generated','approved')
    ORDER BY t.id DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) exit('SQL Error: ' . cp_e($conn->error));

$staffFlag = $isStaff ? 1 : 0;
$stmt->bind_param('isi', $id, $user_unique, $staffFlag);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cert) exit('Certificate not found, access denied, or no active default template found.');


// Only log the student's own views/downloads; staff previews would skew the activity stats.
if (!$isStaff || (string)$cert['user_id'] === (string)$user_unique) {
    cp_log_activity(
        $conn,
        (int)$cert['id'],
        $user_unique,
        (int)($cert['course_id'] ?? 0),
        (int)($cert['intake_id'] ?? 0),
        $action
    );
}

$certificateNo = trim((string)($cert['certificate_no'] ?? ''));

// Build the name from the live user record (first + other + last) so every name shows,
// falling back to the name stored on the certificate when the user record is missing.
$studentName = cp_full_name($cert['u_fname'] ?? '', $cert['u_other_name'] ?? '', $cert['u_lname'] ?? '');
if ($studentName === '') {
    $studentName = cp_full_name($cert['student_name'] ?? '');
}
$studentName = mb_strtoupper($studentName, 'UTF-8');
$courseName    = trim((string)($cert['course_name'] ?? ''));

$completedOn = !empty($cert['completed_on'])
    ? date('d M Y', strtotime((string)$cert['completed_on']))
    : '';

$dateFrom = !empty($cert['course_date_from'])
    ? date('d M Y', strtotime((string)$cert['course_date_from']))
    : '';

$dateTo = !empty($cert['course_date_to'])
    ? date('d M Y', strtotime((string)$cert['course_date_to']))
    : $completedOn;

$issuedOn = !empty($cert['created_at'])
    ? date('d M Y', strtotime((string)$cert['created_at']))
    : date('d M Y');

$assets = [
    'background' => cp_data_uri(cp_resolve_file($cert['background_path'] ?? '')),
    'signature'  => cp_data_uri(cp_resolve_file($cert['signature_path'] ?? '')),
];

$fields = [
    '{student_name}'       => $studentName,
    '{course_name}'        => $courseName,
    '{intake_name}'        => $cert['intake_name'] ?? '',
    '{course_date_from}'   => $dateFrom,
    '{course_date_to}'     => $dateTo,
    '{year}'               => date('Y'),
    '{certificate_no}'     => $certificateNo,
    '{certificat_no}'      => $certificateNo,
    '{signature}'          => 'Authorized Signature',
    '{completed_on}'       => $completedOn,
    '{issued_on}'          => $issuedOn,

    '{{student_name}}'     => $studentName,
    '{{course_name}}'      => $courseName,
    '{{intake_name}}'      => $cert['intake_name'] ?? '',
    '{{course_date_from}}' => $dateFrom,
    '{{course_date_to}}'   => $dateTo,
    '{{year}}'             => date('Y'),
    '{{certificate_no}}'   => $certificateNo,
    '{{signature}}'        => 'Authorized Signature',
    '{{completed_on}}'     => $completedOn,
    '{{issued_on}}'        => $issuedOn,
];

$logosMap = [];
$logosJson = trim((string)($cert['logos_json'] ?? ''));

if ($logosJson !== '') {
    $logos = json_decode($logosJson, true);
    if (is_array($logos)) {
        foreach ($logos as $logo) {
            if (!empty($logo['id'])) {
                $logosMap[(string)$logo['id']] = $logo;
            }
        }
    }
}

$layoutJson = trim((string)($cert['layout_json'] ?? ''));

if ($layoutJson === '') {
    exit('Active default certificate template has no layout_json.');
}

$canvasW = 900;
$canvasH = 637;

$certificateBody = cp_render_layout($layoutJson, $fields, $assets, $logosMap, $canvasW, $canvasH);

$html = "
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<style>
@page {
    size: {$canvasW}px {$canvasH}px;
    margin: 0;
}

html,
body {
    margin: 0;
    padding: 0;
    width: {$canvasW}px;
    height: {$canvasH}px;
    overflow: hidden;
    font-family: Garet, sans-serif;
}

.cert-canvas {
    position: relative;
    width: {$canvasW}px;
    height: {$canvasH}px;
    margin: 0;
    padding: 0;
    overflow: hidden;
    page-break-before: avoid;
    page-break-after: avoid;
    page-break-inside: avoid;
}

.cert-bg {
    position: absolute;
    left: 0;
    top: 0;
    width: {$canvasW}px;
    height: {$canvasH}px;
    object-fit: cover;
    z-index: 1;
}

.cert-element {
    position: absolute;
    box-sizing: border-box;
    overflow: visible;
}

.cert-text {
    width: 100%;
    height: 100%;
    display: block;
    overflow: visible;
    white-space: pre-wrap;
    word-break: normal;
    overflow-wrap: normal;
    box-sizing: border-box;
}

.cert-logo {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
}
</style>
</head>
<body>{$certificateBody}</body>
</html>
";

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Garet');

$root = realpath(__DIR__);
if ($root) $options->set('chroot', $root);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, $canvasW, $canvasH]);
$dompdf->render();

$safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $certificateNo);
$filename = ($safeName ?: 'certificate') . '.pdf';

$dompdf->stream($filename, [
    'Attachment' => $action === 'download'
]);

exit;