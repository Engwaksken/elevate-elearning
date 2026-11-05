<?php
declare(strict_types=1);

/**
 * Map eh360_sso cookie to eLearning PHP session (link-only; no JIT users).
 * Included from connector.php after $conn and $systemname are set.
 *
 * Env: EH_SSO_REQUIRE_MFA (default 1 for eLearning soak/cutover),
 *      EH_SSO_FORCE_PORTAL (default 0).
 *
 * New signups are unpublished for local MFA (random `verified` token,
 * email_verified=0). After portal login, send them to verify.php instead of
 * dropping them on the local login form.
 */
require_once __DIR__ . '/sso_bridge.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$systemname = $systemname ?? 'Elevate Her 360';

if (elevate_sso_has_local_session($systemname)) {
    return;
}

$sso = eh_sso_user();
if (!$sso) {
    if (eh_sso_force_portal_enabled()) {
        eh_sso_redirect_force_portal();
    }
    return;
}

// Enable with portal MFA: EH_SSO_REQUIRE_MFA=1 (and EH_PORTAL_MFA_ENABLED=1 on portal).
$requireMfa = eh_sso_require_mfa_enabled();
if ($requireMfa && !eh_sso_has_mfa($sso)) {
    eh_sso_redirect_step_up();
}

$email = strtolower(trim((string)$sso['email']));
if ($email === '') {
    return;
}

$stmt = $conn->prepare('
    SELECT id, fname, lname, email, verified, email_verified, unique_id, acc_type,
           student_no, class_type_id, status, last_verification_sent_at
    FROM users
    WHERE email = ?
    LIMIT 1
');
if (!$stmt) {
    return;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !elevate_sso_user_may_login($user)) {
    return;
}

$pendingId = (int)($_SESSION['pending_verify_user_id'] ?? 0);
$ssoUserId = (int)$user['id'];
if ($pendingId > 0 && $pendingId !== $ssoUserId) {
    return;
}

if (elevate_sso_user_needs_local_mfa($user)) {
    elevate_sso_continue_local_mfa($conn, $user, $systemname);
    return;
}

elevate_sso_apply_session($user, $systemname);

function elevate_sso_has_local_session(string $systemname): bool
{
    return !empty($_SESSION[$systemname . 'loggedin'])
        && !empty($_SESSION[$systemname . 'email']);
}

function elevate_sso_user_may_login(array $user): bool
{
    $status = strtolower(trim((string)($user['status'] ?? 'active')));
    return $status === '' || $status === 'active';
}

function elevate_sso_user_needs_local_mfa(array $user): bool
{
    return ((int)($user['email_verified'] ?? 0) !== 1)
        || ((int)($user['verified'] ?? 0) !== 1);
}

function elevate_sso_is_verify_request(): bool
{
    $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
    return $script === 'verify.php';
}

function elevate_sso_verify_url(): string
{
    $origin = eh_sso_request_origin();
    return ($origin !== '' ? $origin : '') . '/elearning/verify.php';
}

function elevate_sso_continue_local_mfa(mysqli $conn, array $user, string $systemname): void
{
    $userId = (int)$user['id'];
    $pendingId = (int)($_SESSION['pending_verify_user_id'] ?? 0);

    // Do not replace an in-progress password-login MFA challenge with the SSO
    // cookie identity (connector.php also runs on verify.php).
    if ($pendingId > 0 && $pendingId !== $userId) {
        return;
    }

    if ($pendingId !== $userId) {
        $_SESSION['pending_verify_user_id'] = $userId;
        $_SESSION['pending_verify_email'] = $user['email'];
        $_SESSION['mfa_user'] = [
            'id' => $userId,
            'email' => $user['email'],
            'unique_id' => $user['unique_id'] ?? '',
            'acc_type' => $user['acc_type'] ?? '',
            'fname' => $user['fname'] ?? '',
            'lname' => $user['lname'] ?? '',
            'student_no' => $user['student_no'] ?? '',
            'class_type_id' => $user['class_type_id'] ?? '',
        ];
        if (empty($_SESSION['verify_csrf_token'])) {
            $_SESSION['verify_csrf_token'] = bin2hex(random_bytes(32));
        }

        $lastSent = !empty($user['last_verification_sent_at'])
            ? strtotime((string)$user['last_verification_sent_at'])
            : 0;
        if (!$lastSent || (time() - $lastSent) >= 60) {
            if (elevate_sso_send_local_mfa_code($conn, $user, $systemname)) {
                $_SESSION['verify_flash_type'] = 'success';
                $_SESSION['verify_flash_msg'] = 'A verification code has been sent to your email.';
            } else {
                $_SESSION['verify_flash_type'] = 'error';
                $_SESSION['verify_flash_msg'] = 'Verification email could not be sent. Please use Resend.';
            }
        }
    }

    if (elevate_sso_is_verify_request()) {
        return;
    }

    header('Location: ' . elevate_sso_verify_url());
    exit;
}

function elevate_sso_send_local_mfa_code(mysqli $conn, array $user, string $systemname): bool
{
    require_once __DIR__ . '/EmailService.php';

    $code = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + 900);
    $userId = (int)$user['id'];

    $stmt = $conn->prepare(
        'UPDATE users
         SET verification_code = ?,
             verification_expires_at = ?,
             last_verification_sent_at = NOW()
         WHERE id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        error_log('SSO MFA update prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('ssi', $code, $expiresAt, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return false;
    }

    $name = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
    if ($name === '') {
        $name = (string)($user['email'] ?? 'User');
    }
    $email = (string)$user['email'];
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $subject = "{$systemname} Login Verification Code";
    $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;'>
            <div style='max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e1e5ea;'>
                <div style='background:#5a0e0e;color:#fff;padding:20px;border-bottom:4px solid #8b7536;'>
                    <h2 style='margin:0;'>Login Verification Code</h2>
                </div>
                <div style='padding:24px;color:#222;'>
                    <p>Dear {$safeName},</p>
                    <p>Use this code to complete your login:</p>
                    <div style='text-align:center;margin:25px 0;'>
                        <strong style='font-size:32px;letter-spacing:8px;color:#7a1919;background:#f6f7fa;padding:15px 25px;border-radius:12px;display:inline-block;'>{$code}</strong>
                    </div>
                    <p>This code expires in 15 minutes.</p>
                </div>
            </div>
        </div>
    ";

    try {
        $mailer = new EmailService();
        return $mailer->sendEmail($email, $name, $subject, $body);
    } catch (Throwable $e) {
        error_log('SSO MFA email error: ' . $e->getMessage());
        return false;
    }
}

function elevate_sso_acc_type_to_legacy(string $accType): string
{
    $accType = strtolower(trim($accType));
    if (in_array($accType, ['sabrtw', 'xybrtw', 'afgezt', 'xtfbry', 'zyffwq'], true)) {
        return $accType;
    }
    return 'afgezt';
}

function elevate_sso_apply_session(array $user, string $systemname): void
{
    session_regenerate_id(true);

    $name = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
    if ($name === '') {
        $name = (string)($user['email'] ?? 'User');
    }

    $legacyType = elevate_sso_acc_type_to_legacy((string)($user['acc_type'] ?? ''));

    $_SESSION[$systemname . 'loggedin'] = true;
    $_SESSION[$systemname . 'email'] = $user['email'];
    $_SESSION[$systemname . 'firstname'] = $user['fname'] ?? '';
    $_SESSION[$systemname . 'lastname'] = $user['lname'] ?? '';
    $_SESSION[$systemname . 'leido'] = $user['unique_id'] ?? '';
    $_SESSION[$systemname . 'letype'] = $legacyType;

    $_SESSION['loggedin'] = true;
    $_SESSION['is_logged_in'] = true;
    $_SESSION['id'] = (int)$user['id'];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['unique_id'] = $user['unique_id'] ?? '';
    $_SESSION['acc_type'] = $legacyType;
    $_SESSION['role'] = $legacyType;
    $_SESSION['name'] = $name;
    $_SESSION['fname'] = $user['fname'] ?? '';
    $_SESSION['lname'] = $user['lname'] ?? '';
    $_SESSION['student_no'] = $user['student_no'] ?? '';
    $_SESSION['class_type_id'] = $user['class_type_id'] ?? '';
}