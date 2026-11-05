<?php
session_start();

require_once __DIR__ . "/connector.php";
require_once __DIR__ . "/EmailService.php";

if (!isset($systemname) || trim($systemname) === '') {
    $systemname = "Elevate Her 360";
}

function back_error($msg) {
    global $systemname;
    $_SESSION[$systemname . "errormsg"] = $msg;
    header("Location: ../login");
    exit;
}

function user_name($user) {
    return trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?: $user['email'];
}

function password_ok($plain, $stored, $salt = '') {
    if ($stored === '') return false;

    if (preg_match('/^\$2y\$|^\$argon2i\$|^\$argon2id\$/', $stored)) {
        return password_verify($plain, $stored);
    }

    if ($salt !== '') {
        if (hash_equals($stored, md5($salt . $plain))) return true;
        if (hash_equals($stored, sha1($salt . $plain))) return true;
        if (hash_equals($stored, hash('sha256', $salt . $plain))) return true;
        if (hash_equals($stored, md5($plain . $salt))) return true;
        if (hash_equals($stored, sha1($plain . $salt))) return true;
        if (hash_equals($stored, hash('sha256', $plain . $salt))) return true;
    }

    return hash_equals($stored, $plain)
        || hash_equals($stored, md5($plain))
        || hash_equals($stored, sha1($plain))
        || hash_equals($stored, hash('sha256', $plain));
}

function send_code(mysqli $conn, array $user) {
    global $systemname;

    $code = (string)random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + 900);
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
        error_log("MFA update prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssi", $code, $expires, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) return false;

    $name = user_name($user);
    $email = $user['email'];

    $body = "
        <div style='font-family:Arial,sans-serif;background:#f6f7fa;padding:24px;'>
            <div style='max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e1e5ea;'>
                <div style='background:#5a0e0e;color:#fff;padding:20px;border-bottom:4px solid #8b7536;'>
                    <h2 style='margin:0;'>Login Verification Code</h2>
                </div>
                <div style='padding:24px;color:#222;'>
                    <p>Dear " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
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
        return $mailer->sendEmail($email, $name, "{$systemname} Login Verification Code", $body);
    } catch (Throwable $e) {
        error_log("MFA email error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login");
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    back_error("Please enter both email and password.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_error("Please enter a valid email address.");
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
        salt,
        password,
        dp,
        verified,
        unique_id,
        acc_type,
        class_type_id,
        created_by,
        date_created,
        country,
        is_pwd,
        dob,
        student_no,
        gender,
        contact,
        google_id
    FROM users
    WHERE email = ?
    LIMIT 1
");

if (!$stmt) {
    error_log("Login prepare failed: " . $conn->error);
    back_error("Login failed. Please try again.");
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user || !password_ok($password, (string)$user['password'], (string)($user['salt'] ?? ''))) {
    back_error("Invalid email or password.");
}

if (!empty($_SESSION[$systemname . 'loggedin']) && !empty($_SESSION['loggedin'])) {
    header("Location: ../");
    exit;
}

/*
|--------------------------------------------------------------------------
| MFA required on every login
|--------------------------------------------------------------------------
*/
$_SESSION['pending_verify_user_id'] = (int)$user['id'];
$_SESSION['pending_verify_email'] = $user['email'];

$_SESSION['mfa_user'] = [
    'id' => (int)$user['id'],
    'email' => $user['email'],
    'unique_id' => $user['unique_id'] ?? '',
    'acc_type' => $user['acc_type'] ?? '',
    'fname' => $user['fname'] ?? '',
    'lname' => $user['lname'] ?? '',
    'student_no' => $user['student_no'] ?? '',
    'class_type_id' => $user['class_type_id'] ?? ''
];

$_SESSION['verify_csrf_token'] = bin2hex(random_bytes(32));

if (!send_code($conn, $user)) {
    back_error("Verification email could not be sent. Please check email settings.");
}

$_SESSION['verify_flash_type'] = "success";
$_SESSION['verify_flash_msg'] = "A verification code has been sent to your email.";

header("Location: ../verify");
exit;