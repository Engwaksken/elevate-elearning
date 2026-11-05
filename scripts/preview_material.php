<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| scripts/preview_material.php
|--------------------------------------------------------------------------
| Inline material preview endpoint.
|
| - PDF: streamed inline in browser.
| - Images/audio/video: streamed inline.
| - Text/Reading: rendered to PDF using Dompdf.
| - DOCX/PPTX/XLSX: extracts readable text and renders it to PDF via Dompdf.
| - TXT/CSV/JSON/XML/SQL/LOG/RTF/ODT/ODS/ODP: text preview rendered with Dompdf.
| - ZIP/RAR/7Z/TAR/GZ: download only, not previewed.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connector.php';

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];

$autoload = '';

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === '') {
    failPreview(
        'Dompdf vendor/autoload.php was not found. Checked: '
        . implode(' and ', $autoloadCandidates),
        500
    );
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

function failPreview(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Preview unavailable</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#f5f6f8;margin:0;padding:30px;color:#333}
        .box{max-width:700px;margin:auto;background:#fff;border:1px solid #ddd;border-left:4px solid #9b1c1c;
             border-radius:10px;padding:20px;box-shadow:0 8px 30px rgba(0,0,0,.08)}
        h2{margin:0 0 10px;color:#9b1c1c;font-size:20px}
        p{font-size:15px;line-height:1.6}
    </style></head><body><div class="box">';
    echo '<h2>Preview unavailable</h2><p>' .
        htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '</p></div></body></html>';
    exit;
}

function mimeForExtension(string $ext): string
{
    return match ($ext) {
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'm4a' => 'audio/mp4',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        default => 'application/octet-stream',
    };
}

function streamInline(string $path, string $filename, string $mime): never
{
    if (!is_file($path) || !is_readable($path)) {
        failPreview('The requested file was not found on the server.', 404);
    }

    header('Content-Type: ' . $mime);
    header(
        'Content-Disposition: inline; filename="' .
        str_replace('"', '', basename($filename)) .
        '"'
    );
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
    header('Accept-Ranges: bytes');

    readfile($path);
    exit;
}

function xmlText(string $xml): string
{
    if ($xml === '') {
        return '';
    }

    $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/a:p>/', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/row>/', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/c>/', "\t", $xml) ?? $xml;

    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

    $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text);
}

function extractDocx(string $path): string
{
    if (!class_exists('ZipArchive')) {
        return 'DOCX preview requires the PHP Zip extension.';
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        return 'Unable to open this DOCX file.';
    }

    $xml = (string)$zip->getFromName('word/document.xml');
    $zip->close();

    return xmlText($xml);
}

function extractPptx(string $path): string
{
    if (!class_exists('ZipArchive')) {
        return 'PowerPoint preview requires the PHP Zip extension.';
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        return 'Unable to open this PowerPoint file.';
    }

    $slides = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (
            preg_match(
                '#^ppt/slides/slide(\d+)\.xml$#',
                (string)$name,
                $match
            )
        ) {
            $slides[(int)$match[1]] =
                xmlText((string)$zip->getFromName((string)$name));
        }
    }

    $zip->close();
    ksort($slides);

    if (!$slides) {
        return 'No readable slide text was found in this PowerPoint file.';
    }

    $parts = [];

    foreach ($slides as $number => $slideText) {
        $parts[] = "Slide {$number}\n" . $slideText;
    }

    return implode("\n\n", $parts);
}

function extractXlsx(string $path): string
{
    if (!class_exists('ZipArchive')) {
        return 'Excel preview requires the PHP Zip extension.';
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        return 'Unable to open this Excel file.';
    }

    $sharedStrings = [];

    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedXml !== false) {
        $sx = @simplexml_load_string((string)$sharedXml);

        if ($sx) {
            foreach ($sx->si as $si) {
                $pieces = [];

                if (isset($si->t)) {
                    $pieces[] = (string)$si->t;
                }

                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $pieces[] = (string)$run->t;
                    }
                }

                $sharedStrings[] = implode('', $pieces);
            }
        }
    }

    $sheets = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);

        if (!preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $match)) {
            continue;
        }

        $sheetXml = (string)$zip->getFromName($name);
        $sx = @simplexml_load_string($sheetXml);

        if (!$sx) {
            continue;
        }

        $rows = [];

        foreach ($sx->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $type = (string)$cell['t'];
                $value = isset($cell->v) ? (string)$cell->v : '';

                if (
                    $type === 's'
                    && $value !== ''
                    && isset($sharedStrings[(int)$value])
                ) {
                    $value = $sharedStrings[(int)$value];
                }

                $cells[] = $value;
            }

            $rows[] = implode(' | ', $cells);
        }

        $sheets[(int)$match[1]] =
            "Sheet {$match[1]}\n" . implode("\n", $rows);
    }

    $zip->close();
    ksort($sheets);

    return $sheets
        ? implode("\n\n", $sheets)
        : 'No readable worksheet data was found in this Excel file.';
}

function renderPdf(
    string $title,
    string $content,
    string $subtitle = ''
): never {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);

    $safeTitle = htmlspecialchars(
        $title,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeSubtitle = htmlspecialchars(
        $subtitle,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeContent = nl2br(
        htmlspecialchars(
            $content,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
    );

    $html = '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page{margin:32px 34px}
    body{
        font-family:"DejaVu Sans",sans-serif;
        color:#222;
        font-size:12px;
        line-height:1.55
    }
    h1{
        color:#861b1b;
        font-size:21px;
        margin:0 0 5px;
        padding-bottom:10px;
        border-bottom:2px solid #b89a3d
    }
    .subtitle{
        color:#666;
        font-size:10px;
        margin:0 0 18px
    }
    .content{
        font-size:12px;
        line-height:1.65
    }
</style>
</head>
<body>
    <h1>' . $safeTitle . '</h1>
    <div class="subtitle">' . $safeSubtitle . '</div>
    <div class="content">' . $safeContent . '</div>
</body>
</html>';

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $safeFilename = preg_replace(
        '/[^A-Za-z0-9._-]+/',
        '-',
        $title
    ) ?: 'material-preview';

    $dompdf->stream(
        $safeFilename . '.pdf',
        ['Attachment' => false]
    );

    exit;
}

if (
    !isset($conn)
    || !($conn instanceof mysqli)
    || $conn->connect_errno
) {
    failPreview('Database connection failed.', 500);
}

$materialId = (int)($_GET['id'] ?? 0);

if ($materialId <= 0) {
    failPreview('Invalid material selected.');
}

$stmt = $conn->prepare("
    SELECT
        tm.*,
        ct.title AS topic_title
    FROM topic_materials tm
    INNER JOIN course_topics ct ON ct.id = tm.topic_id
    WHERE tm.id = ?
      AND COALESCE(tm.status, 'active') = 'active'
    LIMIT 1
");

if (!$stmt) {
    failPreview(
        'Unable to prepare material preview query: ' . $conn->error,
        500
    );
}

$stmt->bind_param('i', $materialId);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    failPreview('Material not found.', 404);
}

$title = trim((string)($material['title'] ?? 'Learning Material'));
$type  = strtolower((string)($material['material_type'] ?? 'document'));

if ($type === 'link') {
    $url = trim((string)($material['external_url'] ?? ''));

    if ($url === '') {
        failPreview('This external link is empty.');
    }

    header('Location: ' . $url);
    exit;
}

if ($type === 'text') {
    $content = trim((string)($material['reading_content'] ?? ''));

    if ($content === '') {
        $content = trim((string)($material['description'] ?? ''));
    }

    renderPdf(
        $title,
        $content !== '' ? $content : 'No reading content is available.',
        'Topic: ' . (string)($material['topic_title'] ?? '')
    );
}

$document = basename((string)($material['document'] ?? ''));

if ($document === '') {
    failPreview('No file has been attached to this material.');
}

$path = realpath(__DIR__ . '/../course_materials/' . $document);
$base = realpath(__DIR__ . '/../course_materials');

if (
    $path === false
    || $base === false
    || strncmp($path, $base, strlen($base)) !== 0
) {
    failPreview('The material file could not be located.', 404);
}

$ext = strtolower(pathinfo($document, PATHINFO_EXTENSION));

$downloadOnly = [
    'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2'
];

if (in_array($ext, $downloadOnly, true)) {
    failPreview(
        'Archive files cannot be previewed. Please use the Download button.'
    );
}

/*
|--------------------------------------------------------------------------
| Native browser preview types
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $ext,
        [
            'pdf',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg',
            'mp3', 'wav', 'm4a',
            'mp4', 'webm', 'avi'
        ],
        true
    )
) {
    streamInline(
        $path,
        $document,
        mimeForExtension($ext)
    );
}

/*
|--------------------------------------------------------------------------
| Office formats -> extract readable content -> Dompdf
|--------------------------------------------------------------------------
*/

$content = '';

if ($ext === 'docx') {
    $content = extractDocx($path);
} elseif (in_array($ext, ['pptx'], true)) {
    $content = extractPptx($path);
} elseif ($ext === 'xlsx') {
    $content = extractXlsx($path);
} elseif (
    in_array(
        $ext,
        [
            'txt', 'csv', 'json', 'xml', 'sql', 'log',
            'html', 'htm', 'rtf'
        ],
        true
    )
) {
    $content = (string)@file_get_contents($path);
} elseif (
    in_array(
        $ext,
        ['doc', 'ppt', 'xls', 'odt', 'ods', 'odp'],
        true
    )
) {
    failPreview(
        'This older document format cannot be converted reliably by Dompdf. '
        . 'Please save it as DOCX, PPTX, XLSX or PDF, then upload it again.'
    );
} else {
    failPreview(
        'This file format is not supported for inline preview.'
    );
}

if (trim($content) === '') {
    $content = 'No readable text could be extracted from this file.';
}

renderPdf(
    $title,
    $content,
    strtoupper($ext) . ' preview generated with Dompdf'
);
