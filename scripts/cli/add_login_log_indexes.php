<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Add the database indexes the Login Logs and Students pages rely on
|--------------------------------------------------------------------------
| Run once from the project folder (SSH or a cPanel cron job):
|   php scripts/cli/add_login_log_indexes.php           # report only
|   php scripts/cli/add_login_log_indexes.php --apply   # add missing indexes
|
| Safe to run again: existing indexes are detected and skipped. Adding an
| index to a very large table can take a minute; run it at a quiet time.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/connector.php';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "Database connection failed. Check your .env settings.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);

// table => [index name => columns]
$wanted = [
    'login_logs' => [
        'idx_login_logs_time'        => ['login_time'],
        'idx_login_logs_email'       => ['email'],
        'idx_login_logs_status_time' => ['status', 'login_time'],
    ],
    'users' => [
        'idx_users_email'            => ['email'],
        'idx_users_acc_type_created' => ['acc_type', 'date_created'],
    ],
    'course_registration' => [
        'idx_course_reg_user'        => ['user_id'],
    ],
];

$missingTotal = 0;
foreach ($wanted as $table => $indexes) {
    $t = $conn->real_escape_string($table);
    $exists = $conn->query("SHOW TABLES LIKE '{$t}'");
    if (!$exists || $exists->num_rows === 0) {
        echo "- {$table}: table not found, skipped\n";
        continue;
    }

    $rows = (int)($conn->query("SELECT COUNT(*) FROM `{$table}`")->fetch_row()[0] ?? 0);

    // Leading column of every existing index: an index starting with the
    // same column(s) already serves these queries.
    $existing = [];
    $res = $conn->query("SHOW INDEX FROM `{$table}`");
    while ($r = $res->fetch_assoc()) {
        $existing[$r['Key_name']][(int)$r['Seq_in_index']] = strtolower($r['Column_name']);
    }
    foreach ($existing as &$cols) { ksort($cols); $cols = array_values($cols); }
    unset($cols);

    echo "- {$table} (" . number_format($rows) . " rows)\n";
    foreach ($indexes as $name => $cols) {
        $want = array_map('strtolower', $cols);
        $covered = false;
        foreach ($existing as $key => $have) {
            if (array_slice($have, 0, count($want)) === $want) { $covered = $key; break; }
        }
        $colList = implode(', ', $cols);
        if ($covered !== false) {
            echo "    ok       ({$colList}) already indexed by `{$covered}`\n";
            continue;
        }
        $missingTotal++;
        if (!$apply) {
            echo "    MISSING  ({$colList})\n";
            continue;
        }
        // TEXT/BLOB columns can only be indexed by a prefix.
        $parts = [];
        foreach ($cols as $c) {
            $type = $conn->query("
                SELECT DATA_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '" . $conn->real_escape_string($c) . "'
            ")->fetch_row()[0] ?? '';
            $parts[] = preg_match('/text|blob/i', (string)$type) ? "`{$c}`(191)" : "`{$c}`";
        }
        $sql = "ALTER TABLE `{$table}` ADD INDEX `{$name}` (" . implode(', ', $parts) . ')';
        $start = microtime(true);
        if ($conn->query($sql)) {
            printf("    ADDED    (%s) as `%s` in %.1fs\n", $colList, $name, microtime(true) - $start);
        } else {
            echo "    FAILED   ({$colList}): {$conn->error}\n";
        }
    }
}

if (!$apply) {
    echo $missingTotal
        ? "\n{$missingTotal} index(es) missing. Run again with --apply to add them.\n"
        : "\nAll indexes are in place.\n";
}
