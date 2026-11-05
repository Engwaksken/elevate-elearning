<?php
session_start();
require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';

date_default_timezone_set('Africa/Nairobi');

$systemname  = $systemname  ?? "Elevate Her 360";
$user_unique = $_SESSION[$systemname . "leido"] ?? '';
$role        = $_SESSION[$systemname . "letype"] ?? '';

function back_msg(string $msg, string $type = "errormsg"): never
{
    global $systemname;
    $_SESSION[$systemname . $type] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index.php?view=recommend_certificates'));
    exit;
}

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function cert_no(int $registrationId): string
{
    return 'CERT-' . date('Y') . '-' . str_pad((string)$registrationId, 6, '0', STR_PAD_LEFT);
}

if (!in_array($role, ['sabrtw', 'xybrtw', 'xtfbry'], true)) {
    back_msg("Access denied.");
}

$course_id   = (int)($_POST['course_id']  ?? 0);
$intake_id   = (int)($_POST['intake_id']  ?? 0);
$students    = $_POST['students'] ?? [];
$reason      = trim($_POST['reason'] ?? '');
$generateNow = isset($_POST['recommend_and_generate']);

if ($course_id <= 0 || $intake_id <= 0 || empty($students)) {
    back_msg("Please select at least one student.");
}

$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');


$template_id = (int)($_POST['template_id'] ?? 0);

$template = null;
if ($template_id > 0) {
    $tRow = $conn->query(
        "SELECT * FROM certificate_templates
         WHERE id = {$template_id} AND status = 'active'
         LIMIT 1"
    );
    if ($tRow) {
        $template = $tRow->fetch_assoc();
    }
}
if (!$template) {
    $tRow = $conn->query(
        "SELECT * FROM certificate_templates
         WHERE is_default = 1 AND status = 'active'
         LIMIT 1"
    );
    if ($tRow) {
        $template = $tRow->fetch_assoc();
    }
}


function build_certificate_html(
    ?array $template,
    string $studentName,
    string $courseName,
    string $intakeName,
    string $dateFrom,
    string $dateTo,
    string $year,
    string $certificateNo,
    float  $avgScore
): string {
    $map = [
        '{student_name}'     => h($studentName),
        '{course_name}'      => h($courseName),
        '{intake_name}'      => h($intakeName),
        '{course_date_from}' => h($dateFrom),
        '{course_date_to}'   => h($dateTo),
        '{year}'             => h($year),
        '{certificate_no}'   => h($certificateNo),
    ];

    if (!$template || empty($template['layout_json'])) {
        return plain_certificate_html($map, $avgScore, $intakeName);
    }

    $layout   = json_decode($template['layout_json'], true) ?? [];
    $elements = $layout['elements'] ?? [];
    $cw       = (int)($layout['canvas']['w'] ?? 900);
    $ch       = (int)($layout['canvas']['h'] ?? 637);

    $bg_style = '';
    if (!empty($template['background_path'])) {
        $bg_file = __DIR__ . '/../' . $template['background_path'];
        if (file_exists($bg_file)) {
            $mime     = mime_content_type($bg_file);
            $b64      = base64_encode(file_get_contents($bg_file));
            $bg_data  = "data:{$mime};base64,{$b64}";
            $bg_style = "background-image:url('{$bg_data}');background-size:cover;background-position:center;";
        }
    }

    $elem_html = '';
    foreach ($elements as $el) {
        $x    = round((float)($el['x'] ?? 0));
        $y    = round((float)($el['y'] ?? 0));
        $w    = round((float)($el['w'] ?? 150));
        $h_px = round((float)($el['h'] ?? 40));

        $base = "position:absolute;left:{$x}px;top:{$y}px;"
              . "width:{$w}px;height:{$h_px}px;"
              . "overflow:visible;box-sizing:border-box;";

        if ($el['type'] === 'text') {
            $raw = strtr((string)($el['text'] ?? ''), $map);
            $s   = $el['style'] ?? [];
            $fs  = h($s['fontSize']   ?? '20px');
            $fw  = h($s['fontWeight'] ?? '700');
            $col = h($s['color']      ?? '#222222');
            $ff  = h($s['fontFamily'] ?? 'serif');
            $ta  = h($s['textAlign']  ?? 'left');
            $css = $base
                 . "font-size:{$fs};font-weight:{$fw};color:{$col};"
                 . "font-family:{$ff};text-align:{$ta};"
                 . "white-space:nowrap;line-height:1.2;";
            $elem_html .= "<div style=\"{$css}\">{$raw}</div>\n";

        } elseif ($el['type'] === 'logo') {
            $logos_meta = json_decode($template['logos_json'] ?? '[]', true) ?? [];
            $logo_id    = $el['logoId'] ?? null;
            $logo_src   = '';

            foreach ($logos_meta as $lm) {
                if (($lm['id'] ?? '') === $logo_id && !empty($lm['file_path'])) {
                    $lf = __DIR__ . '/../' . $lm['file_path'];
                    if (file_exists($lf)) {
                        $lmime    = mime_content_type($lf);
                        $logo_src = 'data:' . $lmime . ';base64,' . base64_encode(file_get_contents($lf));
                    }
                    break;
                }
            }

            if (!$logo_src && !empty($template['logo_path'])) {
                $lf = __DIR__ . '/../' . $template['logo_path'];
                if (file_exists($lf)) {
                    $lmime    = mime_content_type($lf);
                    $logo_src = 'data:' . $lmime . ';base64,' . base64_encode(file_get_contents($lf));
                }
            }

            if ($logo_src) {
                $css = $base . "display:block;";
                $elem_html .= "<img src=\"{$logo_src}\" style=\"{$css}object-fit:contain;\" alt=\"logo\">\n";
            }
        }
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate - {$map['{certificate_no}']}</title>
<style>
  @page { margin:0; size:{$cw}px {$ch}px; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { width:{$cw}px; height:{$ch}px; overflow:hidden; }
  .cert-wrap {
    position:relative;
    width:{$cw}px; height:{$ch}px;
    background:#fff;
    {$bg_style}
  }
</style>
</head>
<body>
<div class="cert-wrap">
{$elem_html}
</div>
</body>
</html>
HTML;
}

function plain_certificate_html(array $map, float $avgScore, string $intakeName): string
{
    $certNo      = $map['{certificate_no}'];
    $studentName = $map['{student_name}'];
    $courseName  = $map['{course_name}'];
    $intake      = h($intakeName);
    $issued      = h(date('d M Y'));
    $score       = h(number_format($avgScore, 2));

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{$certNo}</title>
<style>
body{font-family:Georgia,serif;background:#f7f2ea;padding:50px;color:#2b2b2b;}
.certificate{border:12px solid #7a1919;background:#fff;padding:60px;text-align:center;min-height:620px;}
.small{color:#777;font-size:14px;letter-spacing:2px;text-transform:uppercase;}
h1{color:#7a1919;font-size:46px;margin:20px 0;}
h2{font-size:34px;margin:25px 0;}
.course{font-size:26px;color:#8b7536;font-weight:bold;}
.footer{margin-top:70px;display:flex;justify-content:space-between;font-size:14px;}
</style>
</head>
<body>
<div class="certificate">
  <div class="small">Certificate No: {$certNo}</div>
  <h1>Certificate of Completion</h1>
  <p>This is to certify that</p>
  <h2>{$studentName}</h2>
  <p>has successfully completed</p>
  <div class="course">{$courseName}</div>
  <p>Average Score: <strong>{$score}%</strong></p>
  <p>Intake: <strong>{$intake}</strong></p>
  <p>Issued on <strong>{$issued}</strong></p>
  <div class="footer">
    <div>Issued by Elevate Her 360 eLearning</div>
    <div>Authorized Signature</div>
  </div>
</div>
</body>
</html>
HTML;
}


$emailService = null;
try {
    $emailService = new EmailService();
} catch (Throwable $e) {
    error_log("EmailService init failed: " . $e->getMessage());
}


$recommendedCount = 0;
$generatedCount   = 0;
$emailedCount     = 0;

$conn->begin_transaction();

try {
    foreach ($students as $student_unique) {
        $student_unique = trim((string)$student_unique);
        if ($student_unique === '') {
            continue;
        }

        // -- Fetch student + enrollment data ----------------------
        $stmt = $conn->prepare("
            SELECT
                cr.id              AS registration_id,
                cr.user_id,
                cr.course_id,
                cr.intake,
                u.fname,
                u.lname,
                u.other_name,
                u.email,
                c.name             AS course_name,
                i.name             AS intake_name,
                i.date_launching AS start_date,
                i.date_ending AS end_date,
                ROUND(AVG(COALESCE(hi.finalmarks, 0)), 2) AS avg_score
            FROM course_registration cr
            INNER JOIN users u   ON cr.user_id   = u.unique_id
            INNER JOIN courses c ON cr.course_id = c.id
            INNER JOIN intakes i ON cr.intake    = i.id
            LEFT JOIN course_unit cu
                ON cu.course_id = cr.course_id
            LEFT JOIN assignments a
                ON a.course_unit = cu.id
               AND a.intake      = cr.intake
               AND a.dummy      != 'yes'
            LEFT JOIN handed_in hi
                ON hi.assignment_id = a.id
               AND hi.user_id       = cr.user_id
            WHERE cr.user_id   = ?
              AND cr.course_id = ?
              AND cr.intake    = ?
            GROUP BY cr.id
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("sii", $student_unique, $course_id, $intake_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            continue;
        }

        $avgScore = (float)($student['avg_score'] ?? 0);

        $rec = $conn->prepare("
            INSERT INTO certificate_recommendations (
                user_id, course_id, intake_id,
                recommended_by, reason, avg_score,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'recommended', ?)
            ON DUPLICATE KEY UPDATE
                recommended_by = VALUES(recommended_by),
                reason         = VALUES(reason),
                avg_score      = VALUES(avg_score),
                status         = 'recommended'
        ");

        if (!$rec) {
            throw new Exception($conn->error);
        }

        $rec->bind_param(
            "siissds",
            $student_unique, $course_id, $intake_id,
            $user_unique, $reason, $avgScore, $now
        );
        $rec->execute();
        $rec->close();
        $recommendedCount++;

        if (!$generateNow) {
            continue;
        }

      
        $registrationId = (int)$student['registration_id'];
        $certificateNo  = cert_no($registrationId);

        // First + other (middle) + last name, skipping empty parts.
        $studentName = implode(' ', array_filter(array_map(
            static fn($p) => trim(preg_replace('/\s+/u', ' ', (string)($p ?? ''))),
            [$student['fname'] ?? '', $student['other_name'] ?? '', $student['lname'] ?? '']
        ), static fn($p) => $p !== ''));
        if ($studentName === '') {
            $studentName = $student['email'] ?? 'Student';
        }

        $courseName  = $student['course_name'] ?? 'Course';
        $intakeName  = $student['intake_name'] ?? '';
        $completedOn = $today;
        $year        = date('Y');

        $dateFrom = !empty($student['start_date'])
            ? date('d M', strtotime($student['start_date'])) : '';
        $dateTo   = !empty($student['end_date'])
            ? date('d M', strtotime($student['end_date']))   : '';

        $certDir = __DIR__ . '/../certificates/';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0775, true);
        }

        $fileName   = strtolower($certificateNo) . '.html';
        $filePath   = $certDir . $fileName;
        $publicPath = 'certificates/' . $fileName;

        $certificateHtml = build_certificate_html(
            $template,
            $studentName,
            $courseName,
            $intakeName,
            $dateFrom,
            $dateTo,
            $year,
            $certificateNo,
            $avgScore
        );

        file_put_contents($filePath, $certificateHtml);

       
        $cert = $conn->prepare("
            INSERT INTO certificates (
                certificate_no, course_registration_id,
                user_id, course_id, intake_id,
                student_name, course_name,
                completed_on, certificate_file,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id               = LAST_INSERT_ID(id),
                student_name     = VALUES(student_name),
                course_name      = VALUES(course_name),
                certificate_file = VALUES(certificate_file),
                status           = 'active'
        ");

        if (!$cert) {
            throw new Exception($conn->error);
        }

        $cert->bind_param(
            "sisiisssss",
            $certificateNo, $registrationId,
            $student_unique, $course_id, $intake_id,
            $studentName, $courseName,
            $completedOn, $publicPath, $now
        );
        $cert->execute();
        $certificateId = (int)$conn->insert_id;
        $cert->close();

        
        $conn->query(
            "UPDATE course_registration
             SET status = 'done'
             WHERE id = {$registrationId}
             LIMIT 1"
        );

      
        $up = $conn->prepare("
            UPDATE certificate_recommendations
            SET status         = 'certificate_generated',
                certificate_id = ?
            WHERE user_id   = ?
              AND course_id  = ?
              AND intake_id  = ?
            LIMIT 1
        ");

        if (!$up) {
            throw new Exception($conn->error);
        }

        $up->bind_param("isii", $certificateId, $student_unique, $course_id, $intake_id);
        $up->execute();
        $up->close();

        $generatedCount++;

        // -- Send certificate-ready email -------------------------
        $studentEmail = trim($student['email'] ?? '');

        if ($studentEmail !== '' && $emailService !== null) {
           
            $credentialUrl = rtrim((new EmailService())->getDomainName(), '/')
                           . '/elearning/index.php?view=my_certificate&cert='
                           . urlencode($certificateNo);

            $sent = $emailService->sendCertificateEmail(
                $studentEmail,
                $studentName,
                $courseName,
                $credentialUrl
            );

            if ($sent) {
                $emailedCount++;
            } else {
            
                error_log(
                    "Certificate email failed for {$studentEmail} "
                    . "(cert: {$certificateNo})"
                );
            }
        }
    }

    $conn->commit();

  
    $msg = "Recommended {$recommendedCount} student(s).";

    if ($generateNow) {
        $tplNote = $template
            ? " using template <strong>" . h($template['template_name']) . "</strong>."
            : " using the built-in plain template (no designer template found).";

        $msg .= " Generated {$generatedCount} certificate(s){$tplNote}";

        if ($emailService !== null) {
            $msg .= " Notification email sent to {$emailedCount} student(s).";
        }
    }

    back_msg($msg, "successmsg");

} catch (Throwable $e) {
    $conn->rollback();
    back_msg("Failed: " . $e->getMessage());
}