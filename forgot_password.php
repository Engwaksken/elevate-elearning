<?php
session_start();
require_once __DIR__ . '/scripts/connector.php';

$systemname = $systemname ?? "Elevate Her 360 eLearning";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$alertClass = '';
$message = '';

if (isset($_SESSION[$systemname . "errormsg"])) {
    $message = $_SESSION[$systemname . "errormsg"];
    $alertClass = "error";
    unset($_SESSION[$systemname . "errormsg"]);
} elseif (isset($_SESSION[$systemname . "successmsg"])) {
    $message = $_SESSION[$systemname . "successmsg"];
    $alertClass = "success";
    unset($_SESSION[$systemname . "successmsg"]);
}

if (
    !empty($_SESSION[$systemname . "loggedin"]) &&
    !empty($_SESSION[$systemname . "email"])
) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($systemname); ?> | Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href="images/syx_images/logo.png" type="image/x-icon">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(122,25,25,.10), transparent 30%),
                linear-gradient(135deg, var(--bg), #ffffff);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            font-family: var(--font-sans);
        }

        .forgot-wrap {
            width: 100%;
            max-width: 430px;
        }

        .forgot-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .forgot-head {
            background: var(--maroon-dk);
            border-bottom: 4px solid var(--gold);
            padding: 24px 22px;
            text-align: center;
            color: #fff;
        }

        .forgot-logo {
            width: 76px;
            height: 76px;
            border-radius: 18px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.16);
            display: grid;
            place-items: center;
            margin: 0 auto 12px;
        }

        .forgot-logo img {
            max-width: 62px;
            max-height: 62px;
            object-fit: contain;
        }

        .forgot-head h2 {
            margin: 0;
            color: #fff;
            font-family: var(--font-serif);
            font-size: 1.45rem;
        }

        .forgot-head p {
            margin: 7px 0 0;
            color: rgba(255,255,255,.70);
            font-size: .9rem;
            line-height: 1.5;
        }

        .forgot-body {
            padding: 24px 22px 22px;
        }

        .form-label-auth {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .form-label-auth i {
            color: var(--maroon);
        }

        .auth-links {
            margin-top: 16px;
            line-height: 1.9;
        }

        .field-hint {
            margin-top: 8px;
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: flex-start;
            gap: 6px;
            line-height: 1.5;
        }

        .field-hint i {
            color: var(--gold);
            margin-top: 2px;
        }

        .form-val-msg {
            min-height: 18px;
        }
    </style>
</head>

<body>

<div class="forgot-wrap animated fadeInDown">
    <div class="forgot-card">
        <div class="forgot-head">
            <div class="forgot-logo">
                <img src="images/syx_images/logo.png" alt="<?= e($systemname); ?>">
            </div>

            <h2>Forgot Password</h2>
            <p>Enter your account email address and we shall send password reset instructions.</p>
        </div>

        <div class="forgot-body">
            <?php if (!empty($message)): ?>
                <div class="auth-alert <?= $alertClass === 'success' ? 'success' : 'error'; ?>" id="feedbackMessage">
                    <i class="fa <?= $alertClass === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?= e($message); ?>
                </div>

                <?php if ($alertClass === 'success'): ?>
                    <script>
                        setTimeout(function () {
                            window.location.href = 'login.php';
                        }, 5000);
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <form class="auth-form" method="post" action="scripts/forgot_password.php" id="forgotPasswordForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['forgot_password_csrf']); ?>">

                <div class="form-group">
                    <label class="form-label-auth" for="email">
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

                    <span id="report" class="form-val-msg"></span>

                    <div class="field-hint">
                        <i class="fa fa-shield"></i>
                        For security, we only send reset instructions to registered account emails.
                    </div>
                </div>

                <div class="auth-actions">
                    <button type="submit" class="btn-auth" name="forgot">
                        <i class="fa fa-paper-plane"></i> Send Reset Instructions
                    </button>
                </div>

                <div class="auth-links">
                    <a href="login.php">Return to Login</a>
                    <span>|</span>
                    <a href="../">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/jquery-2.1.1.js"></script>
<script src="js/bootstrap.min.js"></script>

<script>
(function () {
    const form = document.getElementById('forgotPasswordForm');
    const email = document.getElementById('email');
    const report = document.getElementById('report');

    function validateEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }

    function setError(message) {
        report.className = 'form-val-msg form-val-error';
        report.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + message;
    }

    function clearError() {
        report.className = 'form-val-msg';
        report.innerHTML = '';
    }

    form.addEventListener('submit', function (e) {
        clearError();

        const value = email.value.trim();

        if (!value) {
            e.preventDefault();
            setError('Email address is required.');
            email.focus();
            return false;
        }

        if (!validateEmail(value)) {
            e.preventDefault();
            setError('Please enter a valid email address.');
            email.focus();
            return false;
        }

        return true;
    });

    $(".auth-alert").delay(5000).fadeTo(800, 0, function () {
        $(this).slideUp(300, function () {
            $(this).remove();
        });
    });
})();
</script>

</body>
</html>