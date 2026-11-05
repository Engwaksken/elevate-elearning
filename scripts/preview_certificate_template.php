<?php
declare(strict_types=1);

require_once __DIR__ . '/connector.php';

date_default_timezone_set('Africa/Nairobi');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function safe_html(string $html): string
{
    return strip_tags($html, '<br><b><strong><i><em><u><span>');
}

function css_value($value, string $fallback = ''): string
{
    $value = trim((string)($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

function clean_path(?string $path): string
{
    return trim((string)($path ?? ''));
}

function public_file_url(?string $path, string $cacheKey = ''): string
{
    $path = clean_path($path);

    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, 'data:')) {
        return $path;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        $sep = str_contains($path, '?') ? '&' : '?';
        return $path . $sep . 'v=' . rawurlencode($cacheKey ?: (string)time());
    }

    $path = ltrim(str_replace('\\', '/', $path), '/');

    $candidates = [
        '../' . $path,
        '../uploads/certificate_templates/' . basename($path),
        '../uploads/templates/' . basename($path),
        '../uploads/' . basename($path),
    ];

    foreach ($candidates as $url) {
        $full = realpath(__DIR__ . '/' . $url);

        if ($full && is_file($full)) {
            $v = $cacheKey !== '' ? $cacheKey : (string)filemtime($full);
            return $url . '?v=' . rawurlencode($v);
        }
    }

    return '../' . $path . '?v=' . rawurlencode($cacheKey ?: (string)time());
}

function file_ext(string $url): string
{
    $clean = strtok($url, '?') ?: $url;
    $clean = strtok($clean, '#') ?: $clean;
    return strtolower(pathinfo($clean, PATHINFO_EXTENSION));
}

function replace_fields(string $text, array $fields): string
{
    return strtr($text, $fields);
}

function normalize_field_key(string $field): string
{
    $field = trim($field);

    if ($field === '') {
        return '';
    }

    if ($field[0] !== '{') {
        $field = '{' . trim($field, '{}') . '}';
    }

    return $field;
}

function px_num($value, float $fallback = 0): float
{
    if (is_numeric($value)) {
        return (float)$value;
    }

    if (preg_match('/-?\d+(\.\d+)?/', (string)$value, $m)) {
        return (float)$m[0];
    }

    return $fallback;
}

function get_style_array(array $el): array
{
    $style = $el['style'] ?? [];
    return is_array($style) ? $style : [];
}

function pick_number(array $el, array $keys, float $fallback = 0): float
{
    $style = get_style_array($el);

    foreach ($keys as $key) {
        if (array_key_exists($key, $el) && trim((string)$el[$key]) !== '') {
            return px_num($el[$key], $fallback);
        }

        if (array_key_exists($key, $style) && trim((string)$style[$key]) !== '') {
            return px_num($style[$key], $fallback);
        }
    }

    return $fallback;
}

function pick_style(array $el, array $keys, string $fallback = ''): string
{
    $style = get_style_array($el);

    foreach ($keys as $key) {
        if (array_key_exists($key, $style) && trim((string)$style[$key]) !== '') {
            return trim((string)$style[$key]);
        }

        if (array_key_exists($key, $el) && trim((string)$el[$key]) !== '') {
            return trim((string)$el[$key]);
        }
    }

    return $fallback;
}

function normalize_font_family(string $font): string
{
    $font = trim($font);

    if ($font === '') {
        return "Calibri, Arial, sans-serif";
    }

    if (
        str_contains($font, ',') ||
        str_starts_with($font, "'") ||
        str_starts_with($font, '"')
    ) {
        return $font;
    }

    return "'" . str_replace("'", '', $font) . "', sans-serif";
}

function render_text_value(array $el, array $fields): string
{
    $field = normalize_field_key((string)($el['field'] ?? ''));

    if ($field !== '' && isset($fields[$field])) {
        return e($fields[$field]);
    }

    $html = (string)($el['html'] ?? '');
    $text = (string)($el['text'] ?? $el['content'] ?? $el['value'] ?? '');

    if ($html !== '') {
        return safe_html(replace_fields($html, $fields));
    }

    return nl2br(e(replace_fields($text, $fields)));
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not found.');
}

$conn->set_charset('utf8mb4');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM certificate_templates
        WHERE id = ?
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        die('SQL Error: ' . e($conn->error));
    }

    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM certificate_templates
        WHERE status = 'active'
        ORDER BY is_default DESC, updated_at DESC, id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        die('SQL Error: ' . e($conn->error));
    }
}

$stmt->execute();
$result = $stmt->get_result();
$template = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$template) {
    die('No certificate template found.');
}

$cacheKey = (string)($template['updated_at'] ?? time()) . '-' . (string)($template['id'] ?? time());

$studentName   = $_GET['student_name'] ?? 'Jane Doe';
$courseName    = $_GET['course_name'] ?? 'Digital Skills Training';
$intakeName    = $_GET['intake_name'] ?? 'Cohort / Intake 2026';
$dateFrom      = $_GET['course_date_from'] ?? '01 May 2026';
$dateTo        = $_GET['course_date_to'] ?? date('d M Y');
$year          = $_GET['year'] ?? date('Y');
$certificateNo = $_GET['certificate_no'] ?? ('EH360-CERT-' . $year . '-' . str_pad((string)$template['id'], 5, '0', STR_PAD_LEFT));
$signatureText = $_GET['signature'] ?? 'Authorized Signature';

$fields = [
    '{student_name}'       => $studentName,
    '{course_name}'        => $courseName,
    '{intake_name}'        => $intakeName,
    '{course_date_from}'   => $dateFrom,
    '{course_date_to}'     => $dateTo,
    '{year}'               => $year,
    '{certificate_no}'     => $certificateNo,
    '{certificat_no}'      => $certificateNo,
    '{signature}'          => $signatureText,
    '{completed_on}'       => $dateTo,

    '{{student_name}}'     => $studentName,
    '{{course_name}}'      => $courseName,
    '{{intake_name}}'      => $intakeName,
    '{{course_date_from}}' => $dateFrom,
    '{{course_date_to}}'   => $dateTo,
    '{{year}}'             => $year,
    '{{certificate_no}}'   => $certificateNo,
    '{{signature}}'        => $signatureText,
    '{{completed_on}}'     => $dateTo,
];

$layout = [];
$layoutJson = trim((string)($template['layout_json'] ?? ''));

if ($layoutJson !== '') {
    $decoded = json_decode($layoutJson, true);

    if (is_array($decoded)) {
        $layout = $decoded;
    }
}

$canvasW = (int)(
    $layout['canvas']['w']
    ?? $layout['canvas']['width']
    ?? $layout['canvas_width']
    ?? $layout['width']
    ?? 900
);

$canvasH = (int)(
    $layout['canvas']['h']
    ?? $layout['canvas']['height']
    ?? $layout['canvas_height']
    ?? $layout['height']
    ?? 637
);

if ($canvasW <= 0) {
    $canvasW = 900;
}

if ($canvasH <= 0) {
    $canvasH = 637;
}

$elements = $layout['elements'] ?? $layout['items'] ?? $layout['objects'] ?? [];
if (!is_array($elements)) {
    $elements = [];
}

$backgroundUrl = public_file_url($template['background_path'] ?? '', $cacheKey);
$isPdfBg = file_ext($backgroundUrl) === 'pdf';

$logosMap = [];
$logosJson = trim((string)($template['logos_json'] ?? ''));

if ($logosJson !== '') {
    $logos = json_decode($logosJson, true);

    if (is_array($logos)) {
        foreach ($logos as $logo) {
            if (!empty($logo['id'])) {
                if (!empty($logo['path'])) {
                    $logo['path'] = public_file_url((string)$logo['path'], $cacheKey);
                }

                if (!empty($logo['src']) && !str_starts_with((string)$logo['src'], 'data:')) {
                    $logo['src'] = public_file_url((string)$logo['src'], $cacheKey);
                }

                $logosMap[(string)$logo['id']] = $logo;
            }
        }
    }
}

$hasTemplateDesign = ($backgroundUrl !== '' || !empty($elements));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= e($template['template_name'] ?? 'Certificate Preview'); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Garet:wght@300;400;500;700;900&display=swap&v=<?= e($cacheKey); ?>" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 28px;
    background: #f4f1ea;
    color: #222;
    font-family: Calibri, Arial, sans-serif;
}

.toolbar {
    width: <?= $canvasW ?>px;
    margin: 0 auto 18px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.toolbar a,
.toolbar button {
    background: #5C2D0A;
    color: #fff;
    border: 0;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.preview-updated {
    width: <?= $canvasW ?>px;
    margin: 0 auto 10px;
    font-size: 11px;
    color: #8a6a55;
    text-align: right;
}

.certificate-outer {
    width: <?= $canvasW ?>px;
    height: <?= $canvasH ?>px;
    margin: 0 auto;
    position: relative;
}

.certificate-wrap {
    width: <?= $canvasW ?>px;
    height: <?= $canvasH ?>px;
    background: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 45px rgba(0,0,0,.18);
    border-radius: 6px;
}

.certificate-bg {
    position: absolute;
    left: 0;
    top: 0;
    width: <?= $canvasW ?>px;
    height: <?= $canvasH ?>px;
    object-fit: cover;
    border: 0;
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

.empty-template {
    width: <?= $canvasW ?>px;
    height: <?= $canvasH ?>px;
    margin: 0 auto;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px;
    box-shadow: 0 12px 45px rgba(0,0,0,.18);
    border-radius: 6px;
    color: #991b1b;
}

@media print {
    body {
        background: #fff;
        padding: 0;
    }

    .toolbar,
    .preview-updated {
        display: none;
    }

    .certificate-outer,
    .certificate-wrap,
    .empty-template {
        margin: 0;
        box-shadow: none;
        border-radius: 0;
    }

    @page {
        size: <?= $canvasW ?>px <?= $canvasH ?>px;
        margin: 0;
    }
}
</style>
</head>

<body>

<div class="toolbar">
    <a href="../?view=certificate_templates">Back to Templates</a>
    <button onclick="location.reload(true)">Refresh</button>
    <button onclick="window.print()">Print / Save PDF</button>
</div>

<div class="preview-updated">
    Template ID: <?= (int)$template['id']; ?> |
    Updated: <?= e($template['updated_at'] ?? 'N/A'); ?>
</div>

<?php if (!$hasTemplateDesign): ?>

<div class="empty-template">
    <div>
        <h2>No saved design found</h2>
        <p>Please edit this template and save the certificate background and layout.</p>
    </div>
</div>

<?php else: ?>

<div class="certificate-outer">
<div class="certificate-wrap">

    <?php if ($backgroundUrl): ?>
        <?php if ($isPdfBg): ?>
            <iframe
                src="<?= e($backgroundUrl); ?>#toolbar=0&navpanes=0&scrollbar=0"
                class="certificate-bg">
            </iframe>
        <?php else: ?>
            <img src="<?= e($backgroundUrl); ?>" class="certificate-bg" alt="">
        <?php endif; ?>
    <?php endif; ?>

    <?php foreach ($elements as $el): ?>
        <?php
        if (!is_array($el)) continue;

        $type = strtolower((string)($el['type'] ?? 'text'));

        $x = pick_number($el, ['x', 'left', 'pos_x'], 0);
        $y = pick_number($el, ['y', 'top', 'pos_y'], 0);
        $w = pick_number($el, ['w', 'width'], 150);
        $h = pick_number($el, ['h', 'height'], 40);

        $rotation = pick_number($el, ['rotation', 'rotate'], 0);
        $zIndex   = (int)pick_number($el, ['zIndex', 'z_index'], 5);
        $opacity  = pick_style($el, ['opacity'], '1');

        $fontSize       = pick_style($el, ['fontSize', 'font_size'], '22px');
        $fontWeight     = pick_style($el, ['fontWeight', 'font_weight'], '700');
        $fontStyle      = pick_style($el, ['fontStyle', 'font_style'], 'normal');
        $textDecoration = pick_style($el, ['textDecoration', 'text_decoration'], 'none');
        $color          = pick_style($el, ['color', 'fontColor', 'font_color'], '#222222');
        $fontFamily     = normalize_font_family(pick_style($el, ['fontFamily', 'font_family'], "Calibri, Arial, sans-serif"));
        $textAlign      = pick_style($el, ['textAlign', 'text_align', 'align'], 'left');
        $lineHeight     = pick_style($el, ['lineHeight', 'line_height'], '1.2');
        $letterSpacing  = pick_style($el, ['letterSpacing', 'letter_spacing'], 'normal');
        $background     = pick_style($el, ['backgroundColor', 'background'], 'transparent');
        $border         = pick_style($el, ['border'], 'none');
        $borderRadius   = pick_style($el, ['borderRadius', 'border_radius'], '0');
        $padding        = pick_style($el, ['padding'], '0');
        $textTransform  = pick_style($el, ['textTransform', 'text_transform'], 'none');

        $renderText = render_text_value($el, $fields);

        $logoSrc = (string)($el['logoSrc'] ?? $el['src'] ?? '');

        if ($logoSrc === '' && !empty($el['logoId'])) {
            $logoId = (string)$el['logoId'];

            if (!empty($logosMap[$logoId]['src'])) {
                $logoSrc = (string)$logosMap[$logoId]['src'];
            } elseif (!empty($logosMap[$logoId]['path'])) {
                $logoSrc = (string)$logosMap[$logoId]['path'];
            }
        }

        $logoSrc = public_file_url($logoSrc, $cacheKey);
        ?>

        <div class="cert-element"
             style="
                left: <?= $x ?>px;
                top: <?= $y ?>px;
                width: <?= $w ?>px;
                height: <?= $h ?>px;
                z-index: <?= $zIndex ?>;
                opacity: <?= e($opacity); ?>;
                transform: rotate(<?= $rotation ?>deg);
             ">

            <?php if ($type === 'logo' || $type === 'image' || $type === 'signature'): ?>

                <?php if ($logoSrc): ?>
                    <img src="<?= e($logoSrc); ?>" class="cert-logo" alt="">
                <?php endif; ?>

            <?php else: ?>

                <div class="cert-text"
                     style="
                        font-size: <?= e($fontSize); ?>;
                        font-weight: <?= e($fontWeight); ?>;
                        font-style: <?= e($fontStyle); ?>;
                        text-decoration: <?= e($textDecoration); ?>;
                        color: <?= e($color); ?>;
                        font-family: <?= e($fontFamily); ?>;
                        text-align: <?= e($textAlign); ?>;
                        line-height: <?= e($lineHeight); ?>;
                        letter-spacing: <?= e($letterSpacing); ?>;
                        background: <?= e($background); ?>;
                        border: <?= e($border); ?>;
                        border-radius: <?= e($borderRadius); ?>;
                        padding: <?= e($padding); ?>;
                        text-transform: <?= e($textTransform); ?>;
                     ">
                    <?= $renderText; ?>
                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</div>
</div>

<?php endif; ?>

</body>
</html>