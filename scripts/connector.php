<?php
declare(strict_types=1);


require_once __DIR__ . '/env.php';

// Settings come from .env (see .env.example) or real environment variables.
// scripts/config.local.php is still honoured as a fallback for older installs.
$__ehConfigFile = __DIR__ . '/config.local.php';
$__ehConfig     = is_file($__ehConfigFile) ? (array)require $__ehConfigFile : [];

$servername = (string)(eh_env('EH_DB_HOST') ?? $__ehConfig['db_host'] ?? 'localhost');
$username   = (string)(eh_env('EH_DB_USER') ?? $__ehConfig['db_user'] ?? '');
$password   = (string)(eh_env('EH_DB_PASS') ?? $__ehConfig['db_pass'] ?? '');
$dbname     = (string)(eh_env('EH_DB_NAME') ?? $__ehConfig['db_name'] ?? '');
unset($__ehConfigFile, $__ehConfig);

mysqli_report(MYSQLI_REPORT_OFF);

$conn = null;
$db_connection_error = null;

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_errno) {
        $db_connection_error = 'Database connection failed: ' . $conn->connect_error;
    } else {
        if (!$conn->set_charset('utf8mb4')) {
            $db_connection_error = 'Failed to set database charset: ' . $conn->error;
        }
    }
} catch (Throwable $e) {
    $db_connection_error = 'Database connection error.';
}


date_default_timezone_set('Africa/Nairobi');

$nowdate = date('Y-m-d H:i:s');
$now     = date('Y-m-d');

// NOTE: $systemname is also the prefix of every session key. Do not make it
// configurable or change it: that would log everyone out and break role checks.
$systemname       = 'Elevate Her 360';
$systememail      = (string)eh_env('EH_MAIL_FROM_ADDRESS', 'no-reply@elevateher360.org');
$accountsemail    = (string)eh_env('EH_ACCOUNTS_EMAIL', 'info@elevateher360.org');
$shortdescription = 'Study online from anywhere around the world';
$year             = date('Y');
$copyright        = 'Elevate Her 360 &copy; ' . $year;
$domainname       = rtrim((string)eh_env('EH_APP_URL', 'https://www.elevateher360.org'), '/');

require_once __DIR__ . '/sso_autologin.php';

?>