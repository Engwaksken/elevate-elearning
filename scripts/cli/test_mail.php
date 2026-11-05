<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mail diagnostics
|--------------------------------------------------------------------------
| Run from the project folder over SSH:
|   php scripts/cli/test_mail.php                      # log in to SMTP, send nothing
|   php scripts/cli/test_mail.php --send=you@mail.com  # also send a test email
|
| Shows which .env file and mail settings are in use and the SMTP login
| conversation. The password and login credentials are never printed.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/EmailService.php';

$send = null;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--send=')) $send = substr($a, 7);
}

echo "== Settings\n";
echo '.env file in use : ' . (eh_env_file() ?: 'NONE FOUND (looked in the project folder and up to 4 parent folders)') . "\n";

$envHost = eh_env('EH_MAIL_HOST');
if ($envHost !== null) {
    $pass = (string)eh_env('EH_MAIL_PASSWORD', '');
    $user = (string)eh_env('EH_MAIL_USERNAME', '');
    echo "EH_MAIL_USERNAME : " . ($user === '' ? '(empty!)' : $user) . "\n";
    if ($user !== '' && !str_contains($user, '@')) {
        echo "  ! The username has no '@'. Most hosts (cPanel) require the FULL email address.\n";
    }

    // Password checks that reveal nothing about the password itself.
    $warn = [];
    if ($pass === '')                                    $warn[] = 'it is EMPTY';
    if ($pass !== trim($pass))                           $warn[] = 'it starts or ends with a space';
    if (preg_match('/^["\']|["\']$/', $pass))            $warn[] = 'it still contains quote marks at the start or end';
    if (str_contains($pass, "\n"))                       $warn[] = 'it contains a line break (a \\n inside double quotes?)';
    echo 'EH_MAIL_PASSWORD : ' . strlen($pass) . " characters as read from .env\n";

    // How the line is written in the file (value hidden). Skipped when the host
    // sets EH_MAIL_PASSWORD as a real environment variable, which overrides .env.
    $file = eh_env_file();
    $fromHost = getenv('EH_MAIL_PASSWORD') !== false && getenv('EH_MAIL_PASSWORD') !== '' && $file !== ''
        && !preg_match('/^[ \t]*(?:export[ \t]+)?EH_MAIL_PASSWORD[ \t]*=[ \t]*\S/m', (string)file_get_contents($file));
    if ($fromHost) echo "  (set by the host's environment, which overrides .env)\n";
    if (!$fromHost && $file !== '' && preg_match('/^[ \t]*(?:export[ \t]+)?EH_MAIL_PASSWORD[ \t]*=[ \t]*(.*)$/m', (string)file_get_contents($file), $m)) {
        $rawVal = rtrim($m[1]);
        $style = $rawVal === '' ? 'empty'
            : ($rawVal[0] === '"' ? 'double quotes' : ($rawVal[0] === "'" ? 'single quotes' : 'no quotes'));
        echo "  written in .env with: {$style}\n";
        if ($style === 'no quotes' && preg_match('/\s#/', $rawVal)) $warn[] = 'it has " #" without quotes, so everything from # on was treated as a comment';
        if ($style === 'double quotes' && str_contains($rawVal, '\\'))  $warn[] = 'it is in double quotes and contains \\ (escape sequences are converted); use single quotes';
        if ($style === 'double quotes' && str_contains(substr($rawVal, 1, -1), '"')) $warn[] = 'it contains a " inside double quotes; use single quotes';
        if ($style === 'single quotes' && str_contains(substr($rawVal, 1, -1), "'")) $warn[] = "it contains a ' inside single quotes; use double quotes";
        if ($style === 'no quotes' && preg_match('/[\s"\'$`\\\\]/', $rawVal)) $warn[] = 'it has spaces or special characters without quotes; wrap it in single quotes';
    }
    foreach ($warn as $w) echo "  ! Password problem: {$w}.\n";
    if (!$warn) echo "  password format looks fine\n";
} else {
    echo "EH_MAIL_HOST is not set, so the email_settings DATABASE table is used, not .env.\n";
}

echo "\n== SMTP login test (no email sent)\n";
try {
    $mailer = new EmailService();
} catch (Throwable $e) {
    echo "Could not set up mail: " . $e->getMessage() . "\n";
    exit(1);
}
echo $mailer->getMailSummary() . "\n\n";

$r = $mailer->testConnection();
foreach ($r['transcript'] as $line) echo '  ' . $line . "\n";
echo "\n" . ($r['ok'] ? "RESULT: login OK\n" : "RESULT: FAILED - {$r['error']}\n");

if (!$r['ok']) {
    $t = implode("\n", $r['transcript']);
    echo "\nWhat this usually means:\n";
    if (preg_match('/\b535\b|authentication failed|Incorrect authentication|Username and Password not accepted/i', $t)) {
        echo "  The server reached the login step and REJECTED the username/password.\n";
        echo "  - Use the full email address as EH_MAIL_USERNAME.\n";
        echo "  - Re-type the password; reset it in cPanel > Email Accounts if unsure.\n";
        echo "  - Gmail/Office 365 need an app password, not the normal password.\n";
    } elseif (preg_match('/connect|timed out|Connection refused|getaddrinfo/i', $t . $r['error'])) {
        echo "  Could not connect. Check EH_MAIL_HOST and the port/encryption pair:\n";
        echo "  465 + ssl  or  587 + tls. Many shared hosts only allow their own mail server (e.g. mail.<yourdomain>).\n";
    } else {
        echo "  See the transcript above for the server's exact reply.\n";
    }
    exit(1);
}

if ($send !== null) {
    $ok = $mailer->sendEmail($send, '', 'Test email from ' . $mailer->getSystemName(),
        '<p>This is a test email from the eLearning mail diagnostics.</p>');
    echo $ok ? "Test email sent to {$send}.\n" : "Sending to {$send} failed; see the PHP error log.\n";
}
