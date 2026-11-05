<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Find (and optionally quarantine) uploaded files nothing refers to
|--------------------------------------------------------------------------
| Run ON THE SERVER, from the project root, so it can read the live database:
|   php scripts/cli/find_orphan_uploads.php                  # report only
|   php scripts/cli/find_orphan_uploads.php --quarantine     # move orphans aside
|   php scripts/cli/find_orphan_uploads.php --restore=20261105_140000
|
| A file counts as "in use" when its file name appears in ANY text column of
| ANY table in the database (file columns, but also HTML/notes/messages that
| link to it), or anywhere in the application source code. Everything else
| is an orphan, e.g. left behind when an assignment or note was deleted or
| replaced.
|
| Safety:
|   - Files newer than --min-age-days (default 14) are never touched.
|   - Quarantine MOVES files to _maintenance/quarantine/<batch>/ (blocked from
|     the web) with a manifest; --restore=<batch> puts them back. Delete the
|     batch folder yourself once the site has been checked.
|   - Refuses to quarantine if the database looks unreadable or more than
|     --max-percent (default 40) of files would be removed, unless --force.
*/

require_once __DIR__ . '/maintenance_common.php';

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$args = eh_args($argv);

if (isset($args['restore'])) {
    exit(eh_restore((string)$args['restore']));
}

$quarantine = isset($args['quarantine']);
$force      = isset($args['force']);
$minAgeDays = isset($args['min-age-days']) ? max(0, (int)$args['min-age-days']) : 14;
$maxPercent = isset($args['max-percent']) ? max(1, (int)$args['max-percent']) : 40;
$dirs       = isset($args['dirs']) ? array_filter(array_map('trim', explode(',', (string)$args['dirs']))) : EH_UPLOAD_DIRS;

require_once dirname(__DIR__) . '/connector.php';
/** @var mysqli|null $conn */
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "Database connection failed. Check scripts/config.local.php.\n");
    exit(1);
}

/* ------------------------------------------------------------------ */
/* 1. Inventory of uploaded files                                      */
/* ------------------------------------------------------------------ */
$files = [];          // rel path => [size, mtime]
$byName = [];         // lowercase basename => [rel paths]
foreach (eh_upload_files($dirs) as $abs) {
    $rel = eh_rel($abs);
    $files[$rel] = [(int)filesize($abs), (int)filemtime($abs)];
    $byName[strtolower(basename($rel))][] = $rel;
}
$total = count($files);
echo "Upload files found: {$total} in " . implode(', ', $dirs) . "\n";
if ($total === 0) exit(0);

/* Pull every file-name-looking token out of a text value. */
$referenced = [];  // lowercase basename => true
$collect = static function (string $text) use (&$referenced, $byName): void {
    if ($text === '') return;
    $text = rawurldecode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match_all('~[^\s/\\\\"\'<>=?&#,;:()|*]+\.[A-Za-z0-9]{1,6}~u', $text, $m)) {
        foreach ($m[0] as $tok) {
            $k = strtolower($tok);
            if (isset($byName[$k])) $referenced[$k] = true;
        }
    }
};

/* Names the token regex cannot see (no extension, spaces, etc.): plain substring search. */
$oddNames = array_values(array_filter(array_keys($byName), static fn($n) =>
    !preg_match('~^[^\s/\\\\"\'<>=?&#,;:()|*]+\.[a-z0-9]{1,6}$~u', $n)));
$oddHits = [];

/* ------------------------------------------------------------------ */
/* 2. References in the database (every text column of every table)  */
/* ------------------------------------------------------------------ */
$db = (string)$conn->query('SELECT DATABASE()')->fetch_row()[0];
$colRes = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "'
      AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')
");
if (!$colRes) {
    fwrite(STDERR, "Could not read the database structure: {$conn->error}\n");
    exit(1);
}

$columns = 0;
$rowsScanned = 0;
while ($colRow = $colRes->fetch_row()) {
    [$table, $col] = $colRow;
    $columns++;
    $t = '`' . str_replace('`', '``', $table) . '`';
    $c = '`' . str_replace('`', '``', $col) . '`';
    // Unbuffered so large tables stream instead of loading into memory.
    $res = $conn->query("SELECT {$c} FROM {$t} WHERE {$c} IS NOT NULL AND {$c} <> '' AND {$c} LIKE '%.%'", MYSQLI_USE_RESULT);
    if (!$res) { fwrite(STDERR, "  skipped {$table}.{$col}: {$conn->error}\n"); continue; }
    while ($row = $res->fetch_row()) {
        $rowsScanned++;
        $v = (string)$row[0];
        $collect($v);
        if ($oddNames) {
            $lv = strtolower(rawurldecode($v));
            foreach ($oddNames as $n) if (!isset($oddHits[$n]) && str_contains($lv, $n)) $oddHits[$n] = true;
        }
    }
    $res->free();
}
echo "Database: scanned {$columns} text columns, {$rowsScanned} values.\n";

/* ------------------------------------------------------------------ */
/* 3. References in the application source code                        */
/* ------------------------------------------------------------------ */
$skipDirs = array_merge(EH_UPLOAD_DIRS, ['vendor', '_maintenance', '.git', 'node_modules', 'Angular']);
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator(eh_root(), FilesystemIterator::SKIP_DOTS),
    static function (SplFileInfo $f) use ($skipDirs): bool {
        if ($f->isDir()) return !in_array(eh_rel($f->getPathname()), $skipDirs, true);
        return (bool)preg_match('/\.(php|js|html?|css|json)$/i', $f->getFilename());
    }
));
$srcFiles = 0;
foreach ($it as $f) {
    $srcFiles++;
    $text = (string)@file_get_contents($f->getPathname());
    $collect($text);
    if ($oddNames) {
        $lt = strtolower($text);
        foreach ($oddNames as $n) if (!isset($oddHits[$n]) && str_contains($lt, $n)) $oddHits[$n] = true;
    }
}
echo "Source code: scanned {$srcFiles} files.\n";
foreach ($oddHits as $n => $_) $referenced[$n] = true;

/* ------------------------------------------------------------------ */
/* 4. Classify                                                         */
/* ------------------------------------------------------------------ */
$cutoff = time() - $minAgeDays * 86400;
$orphans = [];
$tooNew = 0;
$orphanBytes = 0;
foreach ($files as $rel => [$size, $mtime]) {
    if (isset($referenced[strtolower(basename($rel))])) continue;
    if ($mtime > $cutoff) { $tooNew++; continue; }
    $orphans[$rel] = [$size, $mtime];
    $orphanBytes += $size;
}

$inUse = $total - count($orphans) - $tooNew;
$pct = $total ? round(count($orphans) * 100 / $total, 1) : 0;

$batch = date('Ymd_His');
$reportPath = eh_maintenance_dir('reports') . "/orphans_{$batch}.csv";
$fh = eh_csv_open($reportPath, ['path', 'size_bytes', 'modified']);
$perDir = [];
foreach ($orphans as $rel => [$size, $mtime]) {
    fputcsv($fh, [$rel, $size, date('Y-m-d H:i', $mtime)]);
    $top = explode('/', $rel)[0] . (str_starts_with($rel, 'images/') ? '/' . explode('/', $rel)[1] : '');
    $perDir[$top][0] = ($perDir[$top][0] ?? 0) + 1;
    $perDir[$top][1] = ($perDir[$top][1] ?? 0) + $size;
}
fclose($fh);

echo "\nIn use: {$inUse}   Newer than {$minAgeDays} days (kept): {$tooNew}\n";
echo 'Orphaned: ' . count($orphans) . " file(s), " . eh_human($orphanBytes) . " ({$pct}% of files)\n";
ksort($perDir);
foreach ($perDir as $d => [$n, $b]) printf("  %-22s %5d files  %10s\n", $d, $n, eh_human($b));
echo "Report: _maintenance/reports/" . basename($reportPath) . "\n";

if (!$quarantine) {
    echo "\nNothing was moved. Review the report, then re-run with --quarantine.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* 5. Quarantine (with sanity checks)                                  */
/* ------------------------------------------------------------------ */
if (!$force) {
    if (count($referenced) === 0 || $rowsScanned === 0) {
        fwrite(STDERR, "\nRefusing to quarantine: no references were found at all, which suggests the database was not read correctly.\n");
        exit(1);
    }
    if ($pct > $maxPercent) {
        fwrite(STDERR, "\nRefusing to quarantine: {$pct}% of files look orphaned (limit {$maxPercent}%). Check the report; use --force if it is correct.\n");
        exit(1);
    }
}

if (!$orphans) { echo "Nothing to quarantine.\n"; exit(0); }

$r = eh_quarantine(array_keys($orphans), 'not referenced in database or source code');
echo "\nQuarantined {$r['moved']} file(s), " . eh_human($r['bytes']) . ", into _maintenance/quarantine/{$r['batch']}/"
    . ($r['failed'] ? " ({$r['failed']} could not be moved)" : '') . "\n";
echo "Undo with:  php scripts/cli/find_orphan_uploads.php --restore={$r['batch']}\n";
echo "Once the site is confirmed fine, delete that folder to free the space.\n";
