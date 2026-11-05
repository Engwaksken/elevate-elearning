<?php
declare(strict_types=1);

/**
 * Retry failed identity publishes.
 *
 * Docker:
 *   docker compose exec web php /var/www/elearning/scripts/cli/drain_auth_publish_outbox.php
 *
 * Host (with eLearning DB config):
 *   php elevate-her/scripts/cli/drain_auth_publish_outbox.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once dirname(__DIR__) . '/connector.php';
require_once dirname(__DIR__) . '/auth_publish.php';

/** @var mysqli|null $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection failed\n");
    exit(1);
}

$limit = isset($argv[1]) ? (int)$argv[1] : 50;
$counts = eh_auth_publish_drain_outbox($conn, $limit);

echo sprintf(
    "auth_publish_outbox: attempted=%d succeeded=%d failed=%d\n",
    $counts['attempted'],
    $counts['succeeded'],
    $counts['failed']
);

exit($counts['failed'] > 0 && $counts['succeeded'] === 0 ? 2 : 0);
