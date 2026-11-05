<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . "/connector.php";
require_once __DIR__ . "/EmailService.php";
require_once __DIR__ . "/sso_bridge.php";

if (!isset($systemname) || trim((string)$systemname) === '') {
    $systemname = "Elevate Her 360";
}

function flash_and_redirect(string $type, string $message): void
{
    $_SESSION['verify_flash_type'] = $type;
    $_SESSION['verify_flash_msg'] = $message;
    header("Location: ../verify");
    exit;
}

function redirect_login(string $message): void
{
    global $systemname;
    $_SESSION[$systemname . "errormsg"] = $message;
    header("Location: ../login");
    exit;
}

function e_mail($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_display_name(array $user): string
{
    $name = trim((string)($user['fname'] ?? '') . ' ' . (string)($user['lname'] ?? ''));
    return $name !== '' ? $name : (string)($user['email'] ?? 'User');
}

function acc_type_to_legacy($accType): string
{
    $accType = strtolower(trim((string)$accType));

    if (in_array($accType, ["sabrtw", "xybrtw", "afgezt", "xtfbry", "zyffwq"], true)) {
        return $accType;
    }

    return "afgezt";
}

function login_user_and_redirect(array $user, string $systemname): void
{
    session_regenerate_id(true);

    $name = get_display_name($user);
    $legacyType = acc_type_to_legacy($user['acc_type'] ?? '');

    $_SESSION[$systemname . "loggedin"] = true;
    $_SESSION[$systemname . "email"] = $user['email'];
    $_SESSION[$systemname . "firstname"] = $user['fname'] ?? '';
    $_SESSION[$systemname . "lastname"] = $user['lname'] ?? '';
    $_SESSION[$systemname . "leido"] = $user['unique_id'] ?? '';
    $_SESSION[$systemname . "letype"] = $legacyType;

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
    $_SESSION['google_id'] = $user['google_id'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Shared SSO cookie
    |--------------------------------------------------------------------------
    | This allows Library, Jobs Portal and Mentorship to detect the verified
    | Elearning login and log the same user in without asking for password again.
    */
    if (function_exists('eh_sso_create_cookie')) {
        eh_sso_create_cookie($user);
    }

    unset(
        $_SESSION['pending_verify_user_id'],
        $_SESSION['pending_verify_email'],
        $_SESSION['verify_csrf_token'],
        $_SESSION['verify_flash_type'],
        $_SESSION['verify_flash_msg'],
        $_SESSION['mfa_user']
    );

    header("Location: ../");
    exit;
}

function send_verification_email(mysqli $conn, array $user): bool
{
    global $systemname;

    $code = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));
    $userId = (int)$user['id'];

    $stmt = $conn->prepare("
        UPDATE users
        SET verification_code = ?,
            verification_expires_at = ?,
            last_verification_sent_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("MFA resend update prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssi", $code, $expiresAt, $userId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) return false;

    $name = get_display_name($user);
    $email = (string)$user['email'];
    $subject = "Your {$systemname} Login Verification Code";

    $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;color:#222;'>
            <div style='max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e1e5ea;'>
                <div style='background:#5a0e0e;color:#ffffff;padding:20px;border-bottom:4px solid #8b7536;'>
                    <h2 style='margin:0;font-size:22px;'>Login Verification</h2>
                </div>
                <div style='padding:24px;'>
                    <p>Dear " . e_mail($name) . ",</p>
                    <p>Use this code to complete your login:</p>
                    <div style='text-align:center;margin:28px 0;'>
                        <div style='display:inline-block;font-size:32px;letter-spacing:8px;font-weight:bold;color:#7a1919;background:#f6f7fa;border:1px solid #e1e5ea;border-radius:12px;padding:16px 24px;'>
                            {$code}
                        </div>
                    </div>
                    <p>This code expires in <strong>15 minutes</strong>.</p>
                </div>
            </div>
        </div>
    ";

    try {
        $mailer = new EmailService();
        return $mailer->sendEmail($email, $name, $subject, $body);
    } catch (Throwable $e) {
        error_log("MFA resend email failed: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../verify");
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['verify_csrf_token']) ||
    !hash_equals((string)$_SESSION['verify_csrf_token'], (string)$_POST['csrf_token'])
) {
    flash_and_redirect('error', 'Invalid session token. Please refresh and try again.');
}

$pendingUserId = (int)($_SESSION['pending_verify_user_id'] ?? 0);
$pendingEmail = (string)($_SESSION['pending_verify_email'] ?? '');

if ($pendingUserId <= 0 || $pendingEmail === '') {
    redirect_login("Please login first.");
}

$stmt = $conn->prepare("
    SELECT 
        id,
        fname,
        lname,
        other_name,
        email,
        email_verified,
        verification_code,
        verification_expires_at,
        last_verification_sent_at,
        verified,
        unique_id,
        acc_type,
        class_type_id,
        student_no,
        google_id
    FROM users
    WHERE id = ? AND email = ?
    LIMIT 1
");

if (!$stmt) {
    error_log("MFA verify user prepare failed: " . $conn->error);
    flash_and_redirect('error', 'Verification failed. Please try again.');
}

$stmt->bind_param("is", $pendingUserId, $pendingEmail);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    unset($_SESSION['pending_verify_user_id'], $_SESSION['pending_verify_email']);
    redirect_login("Verification session expired. Please login again.");
}

$action = $_POST['action'] ?? 'verify';

if ($action === 'resend') {
    $lastSent = !empty($user['last_verification_sent_at'])
        ? strtotime((string)$user['last_verification_sent_at'])
        : 0;

    if ($lastSent && (time() - $lastSent) < 60) {
        flash_and_redirect('error', 'Please wait at least 1 minute before requesting another code.');
    }

    if (send_verification_email($conn, $user)) {
        flash_and_redirect('success', 'A new verification code has been sent to your email.');
    }

    flash_and_redirect('error', 'Failed to send verification code. Please contact support.');
}

$code = preg_replace('/\D/', '', (string)($_POST['verification_code'] ?? ''));

if (strlen($code) !== 6) {
    flash_and_redirect('error', 'Please enter the 6-digit verification code.');
}

if (empty($user['verification_code']) || !hash_equals((string)$user['verification_code'], $code)) {
    flash_and_redirect('error', 'Invalid verification code.');
}

if (empty($user['verification_expires_at']) || strtotime((string)$user['verification_expires_at']) < time()) {
    flash_and_redirect('error', 'Verification code has expired. Please request a new one.');
}

$stmt = $conn->prepare("
    UPDATE users
    SET email_verified = 1,
        verified = 1,
        verification_code = NULL,
        verification_expires_at = NULL
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    error_log("MFA verify update prepare failed: " . $conn->error);
    flash_and_redirect('error', 'Verification failed. Please try again.');
}

$stmt->bind_param("i", $pendingUserId);
$verified = $stmt->execute();
$stmt->close();

if (!$verified) {
    flash_and_redirect('error', 'Verification failed. Please try again.');
}

$user['email_verified'] = 1;
$user['verified'] = 1;

login_user_and_redirect($user, $systemname);