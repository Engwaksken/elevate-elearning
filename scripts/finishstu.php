<?php
declare(strict_types=1);

require_once __DIR__ . '/connector.php';
// Bundled vendor/ in the project first; the parent folder is a legacy fallback.
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}
if (!class_exists(\Dompdf\Dompdf::class)) {
    die('Dompdf not found. Make sure the vendor/ folder was uploaded with the project.');
}

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('Africa/Nairobi');

$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not found.');
}

$conn->set_charset('utf8mb4');

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function cert_no(int $registrationId): string
{
    return 'CERT-' . date('Y') . '-' . str_pad((string)$registrationId, 6, '0', STR_PAD_LEFT);
}

function safe_filename(string $name): string
{
    $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $name);
    return trim($name, '_') ?: 'certificate';
}

function data_url_from_file(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');

    $paths = [
        __DIR__ . '/../' . $relativePath,
        __DIR__ . '/../../' . $relativePath,
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {

            $mime = mime_content_type($path) ?: 'image/png';

            if ($mime === 'application/pdf') {
                return '';
            }

            $data = base64_encode((string)file_get_contents($path));

            return "data:$mime;base64,$data";
        }
    }

    return '';
}

function build_certificate_html(
    array $elements,
    array $replacements,
    string $bgDataUrl,
    int $canvasW = 900,
    int $canvasH = 637
): string {

    $htmlElements = '';

    foreach ($elements as $el) {

        if (!is_array($el)) {
            continue;
        }

        $type = $el['type'] ?? '';

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
            overflow:visible;
            box-sizing:border-box;
        ";

        if ($type === 'text') {

            $text = (string)($el['text'] ?? '');
            $text = strtr($text, $replacements);

            $style = is_array($el['style'] ?? null)
                ? $el['style']
                : [];

            $fontSize   = $style['fontSize'] ?? '22px';
            $fontWeight = $style['fontWeight'] ?? '700';
            $color      = $style['color'] ?? '#222222';
            $fontFamily = $style['fontFamily'] ?? 'Arial, sans-serif';
            $textAlign  = $style['textAlign'] ?? 'left';

            $htmlElements .= '
                <div style="' . $baseStyle . '
                    font-size:' . h((string)$fontSize) . ';
                    font-weight:' . h((string)$fontWeight) . ';
                    color:' . h((string)$color) . ';
                    font-family:' . h((string)$fontFamily) . ';
                    text-align:' . h((string)$textAlign) . ';
                    line-height:1.2;
                    white-space:nowrap;
                ">
                    ' . h($text) . '
                </div>
            ';
        }

        if ($type === 'logo') {

            $src = $el['logoSrc'] ?? '';

            if ($src !== '') {

                $htmlElements .= '
                    <div style="' . $baseStyle . '">
                        <img src="' . h($src) . '" 
                             style="width:100%;height:100%;object-fit:contain;">
                    </div>
                ';
            }
        }
    }

    $bgHtml = '';

    if ($bgDataUrl !== '') {
        $bgHtml = '
            <img src="' . h($bgDataUrl) . '" 
                 style="
                    position:absolute;
                    left:0;
                    top:0;
                    width:100%;
                    height:100%;
                    object-fit:cover;
                 ">
        ';
    }

    return '
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
                margin:0;
                padding:0;
                background:#ffffff;
            }

            body {
                font-family: Arial, sans-serif;
            }

            .certificate-page {
                position: relative;
                width:' . $canvasW . 'px;
                height:' . $canvasH . 'px;
                overflow:hidden;
                background:#ffffff;
            }

        </style>
    </head>
    <body>

        <div class="certificate-page">

            ' . $bgHtml . '

            ' . $htmlElements . '

        </div>

    </body>
    </html>';
}

/*
|--------------------------------------------------------------------------
| Get active/default template
|--------------------------------------------------------------------------
*/
$template = $conn->query("
    SELECT *
    FROM certificate_templates
    WHERE status = 'active'
    ORDER BY is_default DESC, id DESC
    LIMIT 1
")->fetch_assoc();

if (!$template) {
    die('No active certificate template found.');
}

$layout = [];

if (!empty($template['layout_json'])) {

    $layout = json_decode($template['layout_json'], true);

    if (!is_array($layout)) {
        $layout = [];
    }
}

$elements = $layout['elements'] ?? [];

$bgDataUrl = '';

if (!empty($template['background_path'])) {
    $bgDataUrl = data_url_from_file($template['background_path']);
}

/*
|--------------------------------------------------------------------------
| Students due for completion
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        cr.id AS registration_id,
        cr.user_id AS user_unique_id,
        cr.course_id,
        cr.intake,
        cr.status,

        u.fname,
        u.other_name,
        u.lname,
        u.email,

        c.name AS course_name,

        i.name AS intake_name,
        i.date_ending

    FROM course_registration cr

    INNER JOIN users u 
        ON cr.user_id = u.unique_id

    INNER JOIN courses c 
        ON cr.course_id = c.id

    INNER JOIN intakes i 
        ON cr.intake = i.id

    WHERE i.date_ending IS NOT NULL
      AND i.date_ending <= ?
      AND (cr.status IS NULL OR cr.status != 'done')
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();

$result = $stmt->get_result();

$doneCount = 0;
$certCount = 0;

$certDir = __DIR__ . '/../certificates/';

if (!is_dir($certDir)) {
    mkdir($certDir, 0775, true);
}

$conn->begin_transaction();

try {

    while ($row = $result->fetch_assoc()) {

        $registrationId = (int)$row['registration_id'];

        $userUniqueId = $row['user_unique_id'];

        $courseId = (int)$row['course_id'];
        $intakeId = (int)$row['intake'];

        // First + other (middle) + last name, skipping empty parts.
        $studentName = implode(' ', array_filter(array_map(
            static fn($p) => trim(preg_replace('/\s+/u', ' ', (string)($p ?? ''))),
            [$row['fname'] ?? '', $row['other_name'] ?? '', $row['lname'] ?? '']
        ), static fn($p) => $p !== ''));

        $courseName = $row['course_name'] ?? 'Course';

        $completedOn = $row['date_ending'] ?: $today;

        $certificateNo = cert_no($registrationId);

        /*
        |--------------------------------------------------------------------------
        | Mark registration done
        |--------------------------------------------------------------------------
        */
        $up = $conn->prepare("
            UPDATE course_registration
            SET status = 'done'
            WHERE id = ?
            LIMIT 1
        ");

        $up->bind_param("i", $registrationId);
        $up->execute();
        $up->close();

        $doneCount++;

        /*
        |--------------------------------------------------------------------------
        | Prepare replacements
        |--------------------------------------------------------------------------
        */
        $replacements = [
            '{student_name}'     => $studentName,
            '{course_name}'      => $courseName,
            '{course_date_from}' => '',
            '{course_date_to}'   => date('d M Y', strtotime($completedOn)),
            '{year}'             => date('Y', strtotime($completedOn)),
            '{certificate_no}'   => $certificateNo,
            '{intake_name}'      => $row['intake_name'] ?? '',
        ];

        /*
        |--------------------------------------------------------------------------
        | Generate PDF HTML
        |--------------------------------------------------------------------------
        */
        $html = build_certificate_html(
            $elements,
            $replacements,
            $bgDataUrl,
            900,
            637
        );

        /*
        |--------------------------------------------------------------------------
        | Generate PDF
        |--------------------------------------------------------------------------
        */
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        $dompdf->setPaper([0, 0, 900, 637]);

        $dompdf->render();

        $fileName = safe_filename($certificateNo) . '.pdf';

        $filePath = $certDir . $fileName;

        file_put_contents($filePath, $dompdf->output());

        $publicPath = 'certificates/' . $fileName;

        /*
        |--------------------------------------------------------------------------
        | Save certificate record
        |--------------------------------------------------------------------------
        */
        $cert = $conn->prepare("
            INSERT IGNORE INTO certificates (
                certificate_no,
                course_registration_id,
                user_id,
                course_id,
                intake_id,
                student_name,
                course_name,
                completed_on,
                certificate_file,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $cert->bind_param(
            "sisiisssss",
            $certificateNo,
            $registrationId,
            $userUniqueId,
            $courseId,
            $intakeId,
            $studentName,
            $courseName,
            $completedOn,
            $publicPath,
            $now
        );

        $cert->execute();

        if ($cert->affected_rows > 0) {
            $certCount++;
        }

        $cert->close();
    }

    $conn->commit();

    echo "Success: {$doneCount} registration(s) completed and {$certCount} PDF certificate(s) generated.";

} catch (Throwable $e) {

    $conn->rollback();

    echo "Error: " . $e->getMessage();
}

$stmt->close();
$conn->close();