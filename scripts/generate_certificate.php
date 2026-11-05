<?php
declare(strict_types=1);

require_once __DIR__ . '/connector.php';

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadPaths as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    die('Dompdf vendor autoload file not found. Please install Dompdf using Composer.');
}

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not found.');
}

$conn->set_charset('utf8mb4');

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function clean_text($value): string {
    return trim((string)$value);
}

function safe_filename(string $value): string {
    $value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $value);
    return trim((string)$value, '_') ?: 'certificate';
}

function table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}

function data_url_from_file(string $path): string {
    $path = trim($path);
    if ($path === '') return '';

    if (str_starts_with($path, 'data:image/')) {
        return $path;
    }

    $path = ltrim($path, '/');

    $possiblePaths = [
        __DIR__ . '/../' . $path,
        __DIR__ . '/../../' . $path,
        dirname(__DIR__) . '/' . $path,
    ];

    foreach ($possiblePaths as $fullPath) {
        if (is_file($fullPath)) {
            $mime = mime_content_type($fullPath) ?: 'image/png';

            if ($mime === 'application/pdf') {
                die('PDF background must be converted to an image before generating certificate.');
            }

            return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($fullPath));
        }
    }

    return '';
}

function get_template(mysqli $conn, int $templateId): ?array {
    if (!table_exists($conn, 'certificate_templates')) {
        return null;
    }

    if ($templateId > 0) {
        $sql = "SELECT * FROM certificate_templates WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) return null;

        $stmt->bind_param('i', $templateId);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    $statusWhere = column_exists($conn, 'certificate_templates', 'status')
        ? "WHERE status = 'active'"
        : "";

    $orderBy = column_exists($conn, 'certificate_templates', 'is_default')
        ? "ORDER BY is_default DESC, id DESC"
        : "ORDER BY id DESC";

    $res = $conn->query("
        SELECT *
        FROM certificate_templates
        {$statusWhere}
        {$orderBy}
        LIMIT 1
    ");

    return $res ? ($res->fetch_assoc() ?: null) : null;
}

function generate_certificate_no(mysqli $conn, string $year): string {
    $year = preg_replace('/[^0-9]/', '', $year);
    if ($year === '') $year = date('Y');

    $prefix = "EH360-CERT-{$year}-";
    $next = 1;

    if (
        table_exists($conn, 'certificates') &&
        column_exists($conn, 'certificates', 'certificate_no')
    ) {
        $stmt = $conn->prepare("
            SELECT certificate_no
            FROM certificates
            WHERE certificate_no LIKE CONCAT(?, '%')
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param('s', $prefix);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $row = $res->fetch_assoc()) {
                $last = str_replace($prefix, '', (string)$row['certificate_no']);
                $last = preg_replace('/[^0-9]/', '', $last);
                $next = ((int)$last) + 1;
            }

            $stmt->close();
        }
    }

    return $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

$templateId = (int)($_POST['template_id'] ?? 0);
$template   = get_template($conn, $templateId);

$studentName   = clean_text($_POST['student_name'] ?? '');
$courseName    = clean_text($_POST['course_name'] ?? '');
$intakeName    = clean_text($_POST['intake_name'] ?? '');
$dateFrom      = clean_text($_POST['course_date_from'] ?? '');
$dateTo        = clean_text($_POST['course_date_to'] ?? '');
$year          = clean_text($_POST['year'] ?? date('Y'));
$certificateNo = clean_text($_POST['certificate_no'] ?? '');
$signature     = clean_text($_POST['signature'] ?? '');

if ($studentName === '') {
    die('Student name is required.');
}

if ($courseName === '') {
    die('Course name is required.');
}

if ($certificateNo === '') {
    $certificateNo = generate_certificate_no($conn, $year);
}

$layoutJson = clean_text($_POST['layout_json'] ?? '');
$bgDataUrl  = clean_text($_POST['bg_data_url'] ?? '');

$canvasW = (int)($_POST['canvas_width'] ?? 900);
$canvasH = (int)($_POST['canvas_height'] ?? 637);

if ($canvasW < 100) $canvasW = 900;
if ($canvasH < 100) $canvasH = 637;

$templateName = 'Certificate';

if ($template) {
    $templateName = $template['template_name'] ?? 'Certificate';

    if (!empty($template['layout_json'])) {
        $layoutJson = (string)$template['layout_json'];
    }

    foreach (['background_path', 'background', 'bg_image', 'template_image'] as $bgColumn) {
        if (!empty($template[$bgColumn])) {
            $fileBg = data_url_from_file((string)$template[$bgColumn]);
            if ($fileBg !== '') {
                $bgDataUrl = $fileBg;
                break;
            }
        }
    }
}

$layout = [];

if ($layoutJson !== '') {
    $layout = json_decode($layoutJson, true);

    if (!is_array($layout)) {
        die('Invalid certificate layout JSON.');
    }

    if (isset($layout['canvas']['w'])) {
        $canvasW = (int)$layout['canvas']['w'];
    }

    if (isset($layout['canvas']['h'])) {
        $canvasH = (int)$layout['canvas']['h'];
    }
}

$elements = $layout['elements'] ?? [];

$replacements = [
    '{student_name}'     => $studentName,
    'student_name'       => $studentName,
    '{course_name}'      => $courseName,
    'course_name'        => $courseName,
    '{course_date_from}' => $dateFrom,
    'course_date_from'   => $dateFrom,
    '{course_date_to}'   => $dateTo,
    'course_date_to'     => $dateTo,
    '{year}'             => $year,
    'year'               => $year,
    '{certificate_no}'   => $certificateNo,
    'certificate_no'     => $certificateNo,
    '{certificat_no}'    => $certificateNo,
    'certificat_no'      => $certificateNo,
    '{intake_name}'      => $intakeName,
    'intake_name'        => $intakeName,
    '{signature}'        => $signature,
    'signature'          => $signature,
];

$htmlElements = '';

foreach ($elements as $el) {
    if (!is_array($el)) continue;

    $type = (string)($el['type'] ?? '');

    $x = (float)($el['x'] ?? 0);
    $y = (float)($el['y'] ?? 0);
    $w = (float)($el['w'] ?? 100);
    $h = (float)($el['h'] ?? 40);

    $baseStyle = "
        position:absolute;
        left:{$x}px;
        top:{$y}px;
        width:{$w}px;
        height:{$h}px;
        box-sizing:border-box;
        overflow:visible;
    ";

    if ($type === 'text') {
        $field = clean_text($el['field'] ?? '');
        $text  = clean_text($el['text'] ?? '');

        if ($field !== '' && isset($replacements[$field])) {
            $text = $replacements[$field];
        } else {
            $text = strtr($text, $replacements);
        }

        $style = is_array($el['style'] ?? null) ? $el['style'] : [];

        $fontSize   = clean_text($style['fontSize'] ?? '22px');
        $fontWeight = clean_text($style['fontWeight'] ?? '700');
        $color      = clean_text($style['color'] ?? '#222222');
        $fontFamily = clean_text($style['fontFamily'] ?? 'Arial, sans-serif');
        $textAlign  = clean_text($style['textAlign'] ?? 'left');

        $htmlElements .= '
            <div style="' . $baseStyle . '
                font-size:' . e($fontSize) . ';
                font-weight:' . e($fontWeight) . ';
                color:' . e($color) . ';
                font-family:' . e($fontFamily) . ';
                text-align:' . e($textAlign) . ';
                line-height:1.2;
                white-space:nowrap;
            ">' . e($text) . '</div>
        ';
    }

    if ($type === 'logo') {
        $src = clean_text($el['logoSrc'] ?? '');

        if ($src !== '') {
            $htmlElements .= '
                <div style="' . $baseStyle . '">
                    <img src="' . e($src) . '" style="width:100%;height:100%;object-fit:contain;">
                </div>
            ';
        }
    }
}

$bgHtml = '';

if ($bgDataUrl !== '') {
    $bgHtml = '
        <img src="' . e($bgDataUrl) . '"
             style="position:absolute;left:0;top:0;width:100%;height:100%;">
    ';
}

if ($bgHtml === '' && $htmlElements === '') {
    die('No certificate background or layout found.');
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page {
    size: ' . $canvasW . 'px ' . $canvasH . 'px;
    margin: 0;
}

html, body {
    margin: 0;
    padding: 0;
    background: #ffffff;
}

.certificate-page {
    position: relative;
    width: ' . $canvasW . 'px;
    height: ' . $canvasH . 'px;
    overflow: hidden;
    background: #ffffff;
}
</style>
</head>
<body>
    <div class="certificate-page">
        ' . $bgHtml . '
        ' . $htmlElements . '
    </div>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, $canvasW, $canvasH]);
$dompdf->render();

$fileName = safe_filename($studentName . '_' . $templateName . '_' . $certificateNo) . '.pdf';

$dompdf->stream($fileName, [
    'Attachment' => true
]);

exit;