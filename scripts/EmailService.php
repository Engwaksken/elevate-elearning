<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/connector.php';

class EmailService
{
    private PHPMailer $mail;
    /** Where the SMTP settings came from + host/port/user (never the password), for error logs. */
    private string $mailSummary = '';
    private string $systemname  = "Elevate Her 360 eLearning";
    private string $systememail = "no-reply@elevateher360.org";
    private string $domainname  = "https://www.elevateher360.org";

    public function __construct()
    {
        $this->systemname  = (string)eh_env('EH_MAIL_FROM_NAME', $this->systemname);
        $this->systememail = (string)eh_env('EH_MAIL_FROM_ADDRESS', $this->systememail);
        $this->domainname  = rtrim((string)eh_env('EH_APP_URL', $this->domainname), '/');

        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }

    /**
     * SMTP settings from .env (EH_MAIL_*). Returns null when EH_MAIL_HOST is not set,
     * so installs without a .env keep using the email_settings table.
     */
    private static function settingsFromEnv(): ?array
    {
        $host = trim((string)eh_env('EH_MAIL_HOST', ''));
        if ($host === '') {
            return null;
        }

        return [
            'smtp_host'     => $host,
            'smtp_port'     => (int)eh_env('EH_MAIL_PORT', '465'),
            'smtp_user'     => (string)eh_env('EH_MAIL_USERNAME', ''),
            'smtp_password' => (string)eh_env('EH_MAIL_PASSWORD', ''),
            'smtp_secure'   => (string)eh_env('EH_MAIL_ENCRYPTION', 'ssl'),
            'from_email'    => (string)eh_env('EH_MAIL_FROM_ADDRESS', ''),
            'from_name'     => (string)eh_env('EH_MAIL_FROM_NAME', ''),
            '_source'       => '.env (' . (eh_env_file() ?: 'host environment variables') . ')',
        ];
    }

    /** SMTP settings from the newest row of the email_settings table. */
    private function settingsFromDatabase(): array
    {
        global $conn;

        if (!$conn instanceof mysqli) {
            throw new Exception("Database connection not available.");
        }

        $stmt = $conn->prepare("
            SELECT
                smtp_host,
                smtp_port,
                smtp_user,
                smtp_password,
                smtp_secure,
                from_email,
                from_name
            FROM email_settings
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Email settings query failed: " . $conn->error);
        }

        $stmt->execute();
        $result   = $stmt->get_result();
        $settings = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$settings) {
            throw new Exception("Email settings are not configured.");
        }

        $settings['_source'] = 'database table email_settings (EH_MAIL_HOST is not set in .env)';

        return $settings;
    }

    private function configureSMTP(): void
    {
        // Global mail configuration from .env wins; otherwise the email_settings table.
        $settings = self::settingsFromEnv() ?? $this->settingsFromDatabase();

        $host      = trim($settings['smtp_host']     ?? '');
        $port      = (int)($settings['smtp_port']    ?? 0);
        $user      = trim($settings['smtp_user']     ?? '');
        $pass      = (string)($settings['smtp_password'] ?? '');
        $secure    = strtolower(trim($settings['smtp_secure'] ?? ''));
        $fromEmail = trim($settings['from_email']    ?? '') ?: $user;
        $fromName  = trim($settings['from_name']     ?? '') ?: $this->systemname;

        $this->mailSummary = sprintf(
            'settings from %s; host=%s port=%d encryption=%s username=%s',
            $settings['_source'] ?? 'unknown',
            $host,
            $port,
            $secure !== '' ? $secure : 'auto',
            $user
        );

        if ($host === '' || $port <= 0 || $user === '' || $pass === '') {
            throw new Exception("Incomplete SMTP settings. Check host, port, user, and password.");
        }

        $this->mail->isSMTP();
        $this->mail->Host     = $host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $user;
        $this->mail->Password = $pass;

        if ($port === 465) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'ssl' || $secure === 'smtps') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls' || $secure === 'starttls') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'none' || $secure === 'off') {
            $this->mail->SMTPSecure  = '';
            $this->mail->SMTPAutoTLS = false;
        } else {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $this->mail->Port     = $port;
        $this->mail->CharSet  = 'UTF-8';
        $this->mail->Encoding = 'base64';
        $this->mail->Timeout  = 30;

        $this->mail->setFrom($fromEmail, $fromName);

        if ($fromEmail !== $user) {
            $this->mail->Sender = $user;
        }

        $this->mail->addReplyTo($fromEmail, $fromName);

        $this->mail->SMTPDebug  = 0;
        $this->mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };
    }

    private function resetMailer(): void
    {
        $fromEmail = $this->mail->From     ?: $this->systememail;
        $fromName  = $this->mail->FromName ?: $this->systemname;

        $this->mail->clearAddresses();
        $this->mail->clearCCs();
        $this->mail->clearBCCs();
        $this->mail->clearReplyTos();
        $this->mail->clearAttachments();
        $this->mail->clearCustomHeaders();

        $this->mail->setFrom($fromEmail, $fromName);
        $this->mail->addReplyTo($fromEmail, $fromName);
    }

    public function sendEmail(string $toEmail, string $toName, string $subject, string $body): bool
    {
        try {
            $this->resetMailer();

            $this->mail->addAddress($toEmail, $toName ?: $toEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags(
                str_replace(['<br>', '<br/>', '<br />'], "\n", $body)
            );

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo . ' [' . $this->mailSummary . ']');
            return false;
        } catch (Throwable $e) {
            error_log("Email service error: " . $e->getMessage());
            return false;
        }
    }

    public function sendCustomEmail(
        string  $to,
        string  $name,
        string  $subject,
        string  $body,
        ?string $replyTo = null
    ): bool {
        try {
            $this->resetMailer();

            if ($replyTo) {
                $this->mail->clearReplyTos();
                $this->mail->addReplyTo($replyTo, 'Support Team');
            }

            $this->mail->addAddress($to, $name ?: $to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags(
                str_replace(['<br>', '<br/>', '<br />'], "\n", $body)
            );

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Custom email failed: " . $this->mail->ErrorInfo);
            return false;
        } catch (Throwable $e) {
            error_log("Custom email service error: " . $e->getMessage());
            return false;
        }
    }

    public function sendVerificationCode(string $toEmail, string $toName, string $code): bool
    {
        $safeName = htmlspecialchars($toName ?: $toEmail, ENT_QUOTES, 'UTF-8');

        $body = "
            <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
                <div style='max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;
                            overflow:hidden;border:1px solid #e1e5ea;'>
                    <div style='background:#5a0e0e;color:#ffffff;padding:20px;
                                border-bottom:4px solid #8b7536;'>
                        <h2 style='margin:0;'>Email Verification</h2>
                    </div>
                    <div style='padding:24px;'>
                        <p>Dear {$safeName},</p>
                        <p>Please use the verification code below to activate your account.</p>
                        <div style='text-align:center;margin:28px 0;'>
                            <strong style='font-size:32px;letter-spacing:8px;color:#7a1919;
                                           background:#f6f7fa;padding:15px 25px;border-radius:12px;
                                           display:inline-block;'>{$code}</strong>
                        </div>
                        <p>This code expires in <strong>15 minutes</strong>.</p>
                    </div>
                </div>
            </div>
        ";

        return $this->sendEmail(
            $toEmail,
            $toName,
            "{$this->systemname} Verification Code",
            $body
        );
    }

    public function sendAccountCreationEmail(
        array   $userData,
        string  $password,
        string  $verifyToken,
        ?array  $courseData = null
    ): bool {
        $email = trim($userData['email'] ?? '');
        $name  = trim(($userData['fname'] ?? '') . ' ' . ($userData['lname'] ?? ''));
        $role  = ucfirst($userData['role'] ?? 'User');

        if ($email === '') {
            return false;
        }
        if ($name === '') {
            $name = $email;
        }

        $safeName     = htmlspecialchars($name,     ENT_QUOTES, 'UTF-8');
        $safeEmail    = htmlspecialchars($email,    ENT_QUOTES, 'UTF-8');
        $safeRole     = htmlspecialchars($role,     ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');

        $verificationUrl = $this->domainname
            . "/elearning/verifyme?gen=" . urlencode($verifyToken)
            . "&email=" . urlencode($email);

        $studentInfo = '';
        if (!empty($userData['student_no'])) {
            $studentNo   = htmlspecialchars($userData['student_no'], ENT_QUOTES, 'UTF-8');
            $studentInfo = "
                <p><strong>Student Number:</strong>
                   <code style='background:#fff;padding:4px 8px;border-radius:5px;'>{$studentNo}</code>
                </p>
            ";
        }

        $courseInfo = '';
        if (!empty($courseData['course_name'])) {
            $courseName = htmlspecialchars($courseData['course_name'],        ENT_QUOTES, 'UTF-8');
            $intakeName = htmlspecialchars($courseData['intake_name'] ?? '-', ENT_QUOTES, 'UTF-8');
            $courseInfo = "
                <div style='background:#eef8ef;padding:16px;border-radius:10px;margin:18px 0;
                            border-left:4px solid #41b54a;'>
                    <h3 style='margin-top:0;color:#222;'>Course Enrollment</h3>
                    <p><strong>Course:</strong> {$courseName}</p>
                    <p><strong>Intake:</strong> {$intakeName}</p>
                </div>
            ";
        }

        $body = "
            <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
                <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:14px;
                            overflow:hidden;border:1px solid #e1e5ea;'>
                    <div style='background:#5a0e0e;color:#fff;padding:22px;
                                border-bottom:4px solid #8b7536;'>
                        <h2 style='margin:0;'>Welcome to {$this->systemname}</h2>
                        <p style='margin:6px 0 0;color:rgba(255,255,255,.75);'>
                            Your account has been created successfully.
                        </p>
                    </div>
                    <div style='padding:24px;'>
                        <p>Dear {$safeName},</p>
                        <p>Your <strong>{$safeRole}</strong> account has been created successfully.</p>
                        <div style='background:#f6f7fa;padding:16px;border-radius:10px;margin:18px 0;
                                    border-left:4px solid #5a0e0e;'>
                            <h3 style='margin-top:0;color:#222;'>Login Credentials</h3>
                            <p><strong>Email:</strong> {$safeEmail}</p>
                            <p><strong>Password:</strong>
                               <code style='background:#fff;padding:4px 8px;border-radius:5px;'>{$safePassword}</code>
                            </p>
                            {$studentInfo}
                        </div>
                        {$courseInfo}
                        <div style='text-align:center;margin:28px 0;'>
                            <a href='{$verificationUrl}'
                               style='display:inline-block;background:#5a0e0e;color:#fff;
                                      text-decoration:none;padding:14px 24px;border-radius:8px;
                                      font-weight:bold;'>
                                Verify Email Address
                            </a>
                        </div>
                        <p style='color:#856404;background:#fff3cd;padding:12px;border-radius:8px;'>
                            Please change your password after your first login.
                        </p>
                        <p style='font-size:13px;color:#555;margin-top:24px;'>
                            Regards,<br>
                            <strong>{$this->systemname} Team</strong>
                        </p>
                    </div>
                </div>
            </div>
        ";

        return $this->sendEmail(
            $email,
            $name,
            "{$this->systemname} - Account Created Successfully",
            $body
        );
    }


    public function sendCertificateEmail(
        string $toEmail,
        string $toName,
        string $courseName,
        string $credentialUrl
    ): bool {
        $safeName       = htmlspecialchars($toName,        ENT_QUOTES, 'UTF-8');
        $safeCourseName = htmlspecialchars($courseName,    ENT_QUOTES, 'UTF-8');
        $safeUrl        = htmlspecialchars($credentialUrl, ENT_QUOTES, 'UTF-8');

        $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
            <div style='max-width:620px;margin:0 auto;background:#ffffff;border-radius:14px;
                        overflow:hidden;border:1px solid #e1e5ea;'>

                <!-- Header -->
                <div style='background:#5a0e0e;color:#ffffff;padding:28px 24px;
                            border-bottom:4px solid #8b7536;text-align:center;'>
                    <h2 style='margin:0;font-size:24px;letter-spacing:0.5px;'>
                        Your Credential Is Ready!
                    </h2>
                </div>

                <!-- Body -->
                <div style='padding:32px 28px;'>

                    <p style='font-size:16px;margin-top:0;'>
                        Congratulations <strong>{$safeName}</strong>,
                    </p>

                    <p style='font-size:15px;line-height:1.7;'>
                        Your credential for successfully completing the
                        <strong>{$safeCourseName}</strong> course under
                        <strong>Women in Technology Uganda's Digital Skilling Program</strong>
                        is now ready.
                    </p>

                    <!-- Credential perks box -->
                    <div style='background:#fdf8f0;border-left:4px solid #8b7536;border-radius:8px;
                                padding:20px 24px;margin:24px 0;'>
                        <p style='margin:0 0 12px;font-weight:bold;color:#5a0e0e;font-size:15px;'>
                            Your credential is available online, allowing you to:
                        </p>
                        <table cellpadding='0' cellspacing='0' border='0' style='width:100%;'>
                            <tr>
                                <td style='padding:6px 0;font-size:14px;'>
                                    &#x1F4BC;&nbsp; Add it to your <strong>LinkedIn profile</strong>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0;font-size:14px;'>
                                    &#x1F4F1;&nbsp; <strong>Share it on social media</strong> to showcase your achievement
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0;font-size:14px;'>
                                    &#x1F4C4;&nbsp; <strong>Download and print</strong> it as a PDF copy
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- CTA button -->
                    <div style='text-align:center;margin:32px 0;'>
                        <a href='{$safeUrl}'
                           style='display:inline-block;background:#5a0e0e;color:#ffffff;
                                  text-decoration:none;padding:15px 36px;border-radius:8px;
                                  font-size:16px;font-weight:bold;letter-spacing:0.5px;'>
                            View Credential
                        </a>
                    </div>

                    <!-- Support note -->
                    <p style='font-size:14px;line-height:1.7;color:#444;'>
                        If you experience any challenges accessing your credential or have any questions,
                        please contact us at
                        <a href='mailto:info@witu.org' style='color:#5a0e0e;font-weight:bold;'>
                            info@witu.org
                        </a>.
                    </p>

                    <!-- Sign-off -->
                    <p style='font-size:14px;color:#555;margin-top:32px;border-top:1px solid #e1e5ea;
                              padding-top:20px;'>
                        Kind regards,<br>
                        <strong>Women in Technology Uganda (WITU)</strong>
                    </p>
                </div>

            </div>
        </div>
        ";

        return $this->sendEmail(
            $toEmail,
            $toName,
            "Your {$safeCourseName} Credential Is Ready",
            $body
        );
    }


/* =========================================================
   eLearning Course Notifications
========================================================= */

private function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

private function courseUrl(int $courseId): string
{
    return $this->domainname . "/elearning/course.php?id=" . urlencode((string)$courseId);
}

private function getCourseStudents(int $courseId, int $intakeId): array
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.unique_id,
            u.fname,
            u.lname,
            u.email,
            c.course_name,
            i.intake_name
        FROM student_courses sc
        INNER JOIN users u ON u.unique_id = sc.student_unique OR u.id = sc.student_id
        LEFT JOIN courses c ON c.id = sc.course_id
        LEFT JOIN intakes i ON i.id = sc.intake_id
        WHERE sc.course_id = ?
          AND sc.intake_id = ?
          AND u.email IS NOT NULL
          AND u.email <> ''
    ");

    if (!$stmt) {
        error_log("getCourseStudents prepare failed: " . $conn->error);
        return [];
    }

    $stmt->bind_param("ii", $courseId, $intakeId);
    $stmt->execute();

    $res = $stmt->get_result();
    $students = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    $stmt->close();
    return $students;
}

private function getCourseInstructor(int $courseId): ?array
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.unique_id,
            u.fname,
            u.lname,
            u.email,
            c.course_name
        FROM courses c
        LEFT JOIN users u 
            ON u.unique_id = c.instructor_unique 
            OR u.id = c.instructor_id
        WHERE c.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("getCourseInstructor prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param("i", $courseId);
    $stmt->execute();

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    $stmt->close();

    return ($row && !empty($row['email'])) ? $row : null;
}

private function sendToCourseStudents(
    int $courseId,
    int $intakeId,
    string $subject,
    string $title,
    string $message,
    ?string $buttonText = null,
    ?string $buttonUrl = null
): array {
    $students = $this->getCourseStudents($courseId, $intakeId);

    $sent = 0;
    $failed = 0;

    foreach ($students as $student) {
        $name = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
        $safeName = $this->e($name ?: $student['email']);
        $safeTitle = $this->e($title);
        $safeMessage = nl2br($this->e($message));

        $button = '';
        if ($buttonText && $buttonUrl) {
            $button = "
                <div style='text-align:center;margin:28px 0;'>
                    <a href='{$this->e($buttonUrl)}'
                       style='display:inline-block;background:#5a0e0e;color:#fff;
                              text-decoration:none;padding:13px 22px;border-radius:8px;
                              font-weight:bold;'>
                        {$this->e($buttonText)}
                    </a>
                </div>
            ";
        }

        $body = "
            <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
                <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:14px;
                            overflow:hidden;border:1px solid #e1e5ea;'>
                    <div style='background:#5a0e0e;color:#fff;padding:22px;
                                border-bottom:4px solid #8b7536;'>
                        <h2 style='margin:0;'>{$safeTitle}</h2>
                    </div>
                    <div style='padding:24px;'>
                        <p>Dear {$safeName},</p>
                        <p style='line-height:1.7;'>{$safeMessage}</p>
                        {$button}
                        <p style='font-size:13px;color:#555;margin-top:24px;'>
                            Regards,<br>
                            <strong>{$this->systemname} Team</strong>
                        </p>
                    </div>
                </div>
            </div>
        ";

        if ($this->sendEmail($student['email'], $name, $subject, $body)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return [
        'total' => count($students),
        'sent' => $sent,
        'failed' => $failed
    ];
}

public function notifyNewNote(
    int $courseId,
    int $intakeId,
    string $noteTitle
): array {
    return $this->sendToCourseStudents(
        $courseId,
        $intakeId,
        "New Note Uploaded - {$noteTitle}",
        "New Course Note Uploaded",
        "A new note titled '{$noteTitle}' has been uploaded for your course. Please log in to view it.",
        "View Note",
        $this->courseUrl($courseId)
    );
}

public function notifyUpdatedNote(
    int $courseId,
    int $intakeId,
    string $noteTitle
): array {
    return $this->sendToCourseStudents(
        $courseId,
        $intakeId,
        "Course Note Updated - {$noteTitle}",
        "Course Note Updated",
        "The note titled '{$noteTitle}' has been updated. Please log in to review the latest version.",
        "View Updated Note",
        $this->courseUrl($courseId)
    );
}

public function notifyNewAssignment(
    int $courseId,
    int $intakeId,
    string $assignmentTitle,
    ?string $deadline = null
): array {
    $message = "A new assignment titled '{$assignmentTitle}' has been posted for your course.";

    if ($deadline) {
        $message .= "\n\nDeadline: {$deadline}";
    }

    return $this->sendToCourseStudents(
        $courseId,
        $intakeId,
        "New Assignment - {$assignmentTitle}",
        "New Assignment Posted",
        $message,
        "View Assignment",
        $this->courseUrl($courseId)
    );
}

public function notifyGradedAssignment(
    string $studentEmail,
    string $studentName,
    string $assignmentTitle,
    string $courseName,
    string $score,
    ?string $feedback = null,
    ?int $courseId = null
): bool {
    $safeName = $this->e($studentName ?: $studentEmail);
    $safeAssignment = $this->e($assignmentTitle);
    $safeCourse = $this->e($courseName);
    $safeScore = $this->e($score);
    $safeFeedback = $feedback ? nl2br($this->e($feedback)) : "No feedback provided.";

    $button = '';
    if ($courseId) {
        $url = $this->courseUrl($courseId);
        $button = "
            <div style='text-align:center;margin:28px 0;'>
                <a href='{$this->e($url)}'
                   style='display:inline-block;background:#5a0e0e;color:#fff;
                          text-decoration:none;padding:13px 22px;border-radius:8px;
                          font-weight:bold;'>
                    View Grade
                </a>
            </div>
        ";
    }

    $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
            <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:14px;
                        overflow:hidden;border:1px solid #e1e5ea;'>
                <div style='background:#5a0e0e;color:#fff;padding:22px;
                            border-bottom:4px solid #8b7536;'>
                    <h2 style='margin:0;'>Assignment Graded</h2>
                </div>
                <div style='padding:24px;'>
                    <p>Dear {$safeName},</p>
                    <p>Your assignment <strong>{$safeAssignment}</strong> for 
                       <strong>{$safeCourse}</strong> has been graded.</p>

                    <div style='background:#f6f7fa;padding:16px;border-radius:10px;
                                border-left:4px solid #5a0e0e;margin:18px 0;'>
                        <p><strong>Score:</strong> {$safeScore}</p>
                        <p><strong>Feedback:</strong><br>{$safeFeedback}</p>
                    </div>

                    {$button}

                    <p style='font-size:13px;color:#555;margin-top:24px;'>
                        Regards,<br>
                        <strong>{$this->systemname} Team</strong>
                    </p>
                </div>
            </div>
        </div>
    ";

    return $this->sendEmail(
        $studentEmail,
        $studentName,
        "Assignment Graded - {$assignmentTitle}",
        $body
    );
}

public function notifyCourseAssigned(
    string $studentEmail,
    string $studentName,
    string $courseName,
    string $intakeName,
    int $courseId
): bool {
    $safeName = $this->e($studentName ?: $studentEmail);
    $safeCourse = $this->e($courseName);
    $safeIntake = $this->e($intakeName);
    $url = $this->courseUrl($courseId);

    $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
            <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:14px;
                        overflow:hidden;border:1px solid #e1e5ea;'>
                <div style='background:#5a0e0e;color:#fff;padding:22px;
                            border-bottom:4px solid #8b7536;'>
                    <h2 style='margin:0;'>Course Assigned</h2>
                </div>
                <div style='padding:24px;'>
                    <p>Dear {$safeName},</p>
                    <p>You have been assigned to the course below:</p>

                    <div style='background:#eef8ef;padding:16px;border-radius:10px;
                                border-left:4px solid #41b54a;margin:18px 0;'>
                        <p><strong>Course:</strong> {$safeCourse}</p>
                        <p><strong>Intake:</strong> {$safeIntake}</p>
                    </div>

                    <div style='text-align:center;margin:28px 0;'>
                        <a href='{$this->e($url)}'
                           style='display:inline-block;background:#5a0e0e;color:#fff;
                                  text-decoration:none;padding:13px 22px;border-radius:8px;
                                  font-weight:bold;'>
                            Open Course
                        </a>
                    </div>

                    <p style='font-size:13px;color:#555;margin-top:24px;'>
                        Regards,<br>
                        <strong>{$this->systemname} Team</strong>
                    </p>
                </div>
            </div>
        </div>
    ";

    return $this->sendEmail(
        $studentEmail,
        $studentName,
        "You Have Been Assigned to {$courseName}",
        $body
    );
}

public function notifyInstructorNoteComment(
    int $courseId,
    string $studentName,
    string $noteTitle,
    string $comment
): bool {
    $instructor = $this->getCourseInstructor($courseId);

    if (!$instructor || empty($instructor['email'])) {
        return false;
    }

    $instructorName = trim(($instructor['fname'] ?? '') . ' ' . ($instructor['lname'] ?? ''));
    $safeInstructor = $this->e($instructorName ?: $instructor['email']);
    $safeStudent = $this->e($studentName);
    $safeNote = $this->e($noteTitle);
    $safeComment = nl2br($this->e($comment));
    $url = $this->courseUrl($courseId);

    $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
            <div style='max-width:620px;margin:0 auto;background:#fff;border-radius:14px;
                        overflow:hidden;border:1px solid #e1e5ea;'>
                <div style='background:#5a0e0e;color:#fff;padding:22px;
                            border-bottom:4px solid #8b7536;'>
                    <h2 style='margin:0;'>New Student Comment</h2>
                </div>
                <div style='padding:24px;'>
                    <p>Dear {$safeInstructor},</p>
                    <p><strong>{$safeStudent}</strong> commented on the note 
                       <strong>{$safeNote}</strong>.</p>

                    <div style='background:#f6f7fa;padding:16px;border-radius:10px;
                                border-left:4px solid #5a0e0e;margin:18px 0;'>
                        {$safeComment}
                    </div>

                    <div style='text-align:center;margin:28px 0;'>
                        <a href='{$this->e($url)}'
                           style='display:inline-block;background:#5a0e0e;color:#fff;
                                  text-decoration:none;padding:13px 22px;border-radius:8px;
                                  font-weight:bold;'>
                            View Comment
                        </a>
                    </div>

                    <p style='font-size:13px;color:#555;margin-top:24px;'>
                        Regards,<br>
                        <strong>{$this->systemname} Team</strong>
                    </p>
                </div>
            </div>
        </div>
    ";

    return $this->sendEmail(
        $instructor['email'],
        $instructorName,
        "New Comment on {$noteTitle}",
        $body
    );
}

    public function getSystemName(): string  { return $this->systemname;  }
    public function getSystemEmail(): string { return $this->systememail; }
    public function getDomainName(): string  { return $this->domainname;  }
    public function getMailSummary(): string { return $this->mailSummary; }

    /**
     * Connect and log in to the SMTP server WITHOUT sending a message.
     * Returns ['ok' => bool, 'error' => string, 'transcript' => string[]].
     * Credentials are removed from the transcript.
     */
    public function testConnection(): array
    {
        $lines = [];
        $hideNext = false;
        $this->mail->SMTPDebug   = 3;
        $this->mail->Debugoutput = static function ($str, $level) use (&$lines, &$hideNext) {
            foreach (preg_split('/\R/', rtrim((string)$str)) as $line) {
                if (preg_match('/^CLIENT -> SERVER:\s*(.*)$/', $line, $m)) {
                    $cmd = $m[1];
                    if ($hideNext) {
                        $line = 'CLIENT -> SERVER: [credentials hidden]';
                    } elseif (preg_match('/^AUTH\s+(PLAIN|LOGIN|CRAM-MD5|XOAUTH2)\s+\S+/i', $cmd, $a)) {
                        $line = 'CLIENT -> SERVER: AUTH ' . strtoupper($a[1]) . ' [credentials hidden]';
                    }
                    $hideNext = false;
                } elseif (preg_match('/^SERVER -> CLIENT:\s*334/', $line)) {
                    $hideNext = true; // next client line is a base64 username/password
                }
                $lines[] = $line;
            }
        };

        try {
            $ok = $this->mail->smtpConnect();
            $error = $ok ? '' : (string)$this->mail->ErrorInfo;
        } catch (Throwable $e) {
            $ok = false;
            $error = $e->getMessage() ?: (string)$this->mail->ErrorInfo;
        } finally {
            $this->mail->smtpClose();
            $this->mail->SMTPDebug = 0;
        }

        return ['ok' => $ok, 'error' => $error, 'transcript' => $lines];
    }
}