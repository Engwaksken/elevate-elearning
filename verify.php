<?php
session_start();
require_once __DIR__ . "/scripts/connector.php";

if (!isset($systemname) || trim($systemname) === '') {
    $systemname = "Elevate Her 360 eLearning";
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pendingUserId = (int)($_SESSION['pending_verify_user_id'] ?? 0);
$pendingEmail  = $_SESSION['pending_verify_email'] ?? '';

if ($pendingUserId <= 0 || $pendingEmail === '') {
    $_SESSION[$systemname . "errormsg"] = "Please login first to verify your account.";
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['verify_csrf_token'])) {
    $_SESSION['verify_csrf_token'] = bin2hex(random_bytes(32));
}

$flashType = $_SESSION['verify_flash_type'] ?? '';
$flashMsg  = $_SESSION['verify_flash_msg'] ?? '';

unset($_SESSION['verify_flash_type'], $_SESSION['verify_flash_msg']);

$maskedEmail = preg_replace('/(^.).*(@.*$)/', '$1***$2', $pendingEmail);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($systemname) ?> | Verify Email</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="images/syx_images/logo.png" type="image/x-icon">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(122,25,25,.10), transparent 32%),
                linear-gradient(135deg, var(--bg), #ffffff);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
        }

        .auth-wrap {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }

        .verify-icon {
            width: 76px;
            height: 76px;
            border-radius: 22px;
            margin: 0 auto 14px;
            display: grid;
            place-items: center;
            background: var(--maroon-dim);
            color: var(--maroon);
            font-size: 30px;
            border: 1px solid rgba(122,25,25,.12);
        }

        .code-input {
            text-align: center;
            font-size: 28px !important;
            letter-spacing: 8px;
            font-weight: 700;
            color: var(--maroon) !important;
        }

        .auth-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .auth-label i {
            color: var(--maroon);
        }

        .resend-form {
            margin-top: 12px;
            text-align: center;
        }

        .btn-link-auth {
            background: transparent;
            border: none;
            color: var(--maroon);
            font-weight: 700;
            cursor: pointer;
        }

        .btn-link-auth:hover {
            color: var(--gold);
        }
    </style>
</head>

<body>

<div class="auth-wrap animated fadeInDown">
    <div class="auth-hero">
        <div class="auth-logo">
            <img src="images/syx_images/logo.png" alt="<?= e($systemname) ?> Logo">
        </div>

        <h2>Verify Your Email</h2>
        <p>We sent a 6-digit code to <strong><?= e($maskedEmail) ?></strong></p>
    </div>

    <?php if ($flashMsg): ?>
        <div class="auth-alert <?= $flashType === 'success' ? 'success' : 'error' ?>">
            <i class="fa <?= $flashType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="scripts/verify.php" id="verifyForm">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['verify_csrf_token']) ?>">
        <input type="hidden" name="action" value="verify">

        <div class="form-group">
            <label class="auth-label" for="verification_code">
                <i class="fa fa-key"></i> Verification Code
            </label>

            <input
                type="text"
                class="form-input code-input"
                id="verification_code"
                name="verification_code"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                placeholder="000000"
                autocomplete="one-time-code"
                required
            >

            <div class="field-hint">
                <i class="fa fa-clock-o"></i>
                Code expires after 15 minutes.
            </div>
        </div>

        <div class="auth-actions">
            <button type="submit" class="btn-auth">
                <i class="fa fa-check-circle"></i> Verify & Continue
            </button>
        </div>
    </form>

    <form class="resend-form" method="post" action="scripts/verify.php">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['verify_csrf_token']) ?>">
        <input type="hidden" name="action" value="resend">

        <button type="submit" class="btn-link-auth">
            <i class="fa fa-refresh"></i> Resend verification code
        </button>
    </form>

    <div class="auth-links" style="margin-top:14px;">
        <a href="login.php">Back to Login</a>
    </div>
</div>

<script>
(function () {
    const code = document.getElementById('verification_code');

    code.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
})();
</script>

</body>
</html>