<?php
session_start();
require_once __DIR__ . "/scripts/connector.php";

if (!isset($systemname) || trim($systemname) === '') {
    $systemname = "Elevate Her 360 eLearning";
}

if (!isset($shortdescription) || trim($shortdescription) === '') {
    $shortdescription = "Access your learning dashboard and course resources.";
}

if (!empty($_SESSION[$systemname . "loggedin"]) && !empty($_SESSION[$systemname . "email"])) {
    header("Location: index.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** EH_ELEARNING_LOCAL_LOGIN=0 ? redirect-only to portal (cutover). Default: local login allowed (soak). */
function elevate_local_login_enabled(): bool
{
    $raw = getenv('EH_ELEARNING_LOCAL_LOGIN');
    if ($raw === false || $raw === '') {
        return true;
    }
    return !in_array(strtolower(trim($raw)), ['0', 'false', 'no', 'off'], true);
}

$error = $_SESSION[$systemname . "errormsg"] ?? '';
$success = $_SESSION[$systemname . "successmsg"] ?? '';

unset($_SESSION[$systemname . "errormsg"], $_SESSION[$systemname . "successmsg"]);

$siteBase = rtrim($domainname ?? 'https://www.elevateher360.org', '/');
// Keep the return host tied to the request, so a local/staging portal login does not send users to prod.
$returnBase = function_exists('eh_sso_request_origin') ? eh_sso_request_origin() : '';
$ssoReturnUrl = ($returnBase !== '' ? $returnBase : $siteBase) . '/elearning/';
$centralAuthLoginUrl = function_exists('eh_auth_portal_login_url')
    ? eh_auth_portal_login_url($ssoReturnUrl, false)
    : ($siteBase . '/auth/login.php?return_to=' . rawurlencode($ssoReturnUrl));

if (!elevate_local_login_enabled()) {
    header('Location: ' . $centralAuthLoginUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($systemname) ?> | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="images/syx_images/logo.png" type="image/x-icon">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">

    <script src="https://cdn.userway.org/widget.js" data-account="0qjTIJ2gVr"></script>

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

        .auth-logo {
            width: 86px;
            height: 86px;
            margin: 0 auto 14px;
            border-radius: 20px;
            background: var(--surface2);
            border: 1px solid var(--border);
            display: grid;
            place-items: center;
            box-shadow: var(--shadow-xs);
        }

        .auth-logo img {
            max-width: 70px;
            max-height: 70px;
            object-fit: contain;
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

        .auth-footer-note {
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
        }

        .auth-alert {
            margin-bottom: 12px;
        }
    </style>
</head>

<body>

<div class="auth-wrap animated fadeInDown">
    <div class="auth-hero">
        <div class="auth-logo">
            <img src="images/syx_images/logo.png" alt="<?= e($systemname) ?> Logo">
        </div>

        <h2>Welcome Back</h2>
        <p><?= e($shortdescription) ?></p>
    </div>

    <?php if ($error): ?>
        <div class="auth-alert error">
            <i class="fa fa-exclamation-circle"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="auth-alert success">
            <i class="fa fa-check-circle"></i>
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="scripts/login.php" id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ?>">

        <div class="form-group">
            <label class="auth-label" for="email">
                <i class="fa fa-envelope"></i> Email Address
            </label>
            <input
                type="email"
                class="form-input"
                id="email"
                name="email"
                placeholder="Enter your email address"
                autocomplete="email"
                required
            >
            <div class="form-val-msg" id="emailError"></div>
        </div>

        <div class="form-group">
            <label class="auth-label" for="password">
                <i class="fa fa-lock"></i> Password
            </label>

            <div class="input-with-icon">
                <input
                    type="password"
                    class="form-input"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
                <i class="fa fa-eye-slash toggle-icon" id="togglePassword"></i>
            </div>
            <div class="form-val-msg" id="passwordError"></div>
        </div>

       <!-- <div class="auth-actions">
            <a href="<?= e($centralAuthLoginUrl) ?>" class="btn-auth" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;">
                <i class="fa fa-sign-in"></i> Sign in with Elevate Her (SSO)
            </a>
        </div> 

        <div class="auth-links" style="margin: 12px 0 4px; text-align:center;">
            <span>Or continue with local MFA login</span>
        </div> -->

        <div class="auth-actions">
            <button type="submit" name="submit1" class="btn-auth">
                <i class="fa fa-sign-in"></i> Login Securely
            </button>
        </div>

        <div class="auth-links">
            <a href="create_student.php">Student Sign Up</a>
            <span>|</span>
            <a href="forgot_password.php">Forgot Password?</a>
            <span>|</span>
            <a href="../">Back</a>
        </div>
    </form>

  
</div>

<script src="js/jquery-2.1.1.js"></script>
<script src="js/bootstrap.min.js"></script>

<script>
(function () {
    const form = document.getElementById('loginForm');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    const togglePassword = document.getElementById('togglePassword');

    function setError(el, message) {
        el.className = 'form-val-msg form-val-error';
        el.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + message;
    }

    function clearError(el) {
        el.className = 'form-val-msg';
        el.innerHTML = '';
    }

    function validEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }

    form.addEventListener('submit', function (e) {
        let ok = true;
        clearError(emailError);
        clearError(passwordError);

        if (!email.value.trim()) {
            setError(emailError, 'Email address is required.');
            ok = false;
        } else if (!validEmail(email.value.trim())) {
            setError(emailError, 'Enter a valid email address.');
            ok = false;
        }

        if (!password.value.trim()) {
            setError(passwordError, 'Password is required.');
            ok = false;
        } else if (password.value.length < 4) {
            setError(passwordError, 'Password must be at least 4 characters.');
            ok = false;
        }

        if (!ok) e.preventDefault();
    });

    togglePassword.addEventListener('click', function () {
        const isHidden = password.type === 'password';
        password.type = isHidden ? 'text' : 'password';
        this.classList.toggle('fa-eye', isHidden);
        this.classList.toggle('fa-eye-slash', !isHidden);
    });
})();
</script>

</body>
</html>