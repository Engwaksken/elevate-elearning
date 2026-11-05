<?php
declare(strict_types=1);


$__ehConfigFile = __DIR__ . '/config.local.php';
$__ehConfig     = is_file($__ehConfigFile) ? (array)require $__ehConfigFile : [];

// Credentials come from scripts/config.local.php (not committed) or environment variables.
$servername = (string)(($__ehConfig['db_host'] ?? getenv('EH_DB_HOST')) ?: 'localhost');
$username   = (string)(($__ehConfig['db_user'] ?? getenv('EH_DB_USER')) ?: '');
$password   = (string)(($__ehConfig['db_pass'] ?? getenv('EH_DB_PASS')) ?: '');
$dbname     = (string)(($__ehConfig['db_name'] ?? getenv('EH_DB_NAME')) ?: '');
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

$systemname       = 'Elevate Her 360';
$systememail      = 'no-reply@elevateher360.org';
$accountsemail    = 'info@elevateher360.org';
$shortdescription = 'Study online from anywhere around the world';
$year             = date('Y');
$copyright        = 'Elevate Her 360 &copy; ' . $year;
$domainname       = 'https://www.elevateher360.org';

require_once __DIR__ . '/sso_autologin.php';

?>