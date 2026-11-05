<?php
declare(strict_types=1);

/*
 * Shared helpers for the upload maintenance CLI scripts.
 * Everything they produce (reports, backups, quarantine, state) lives in
 * <project>/_maintenance/, which is git-ignored and blocked from the web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const EH_PROJECT_ROOT = __DIR__ . '/../..';

/** Folders that hold uploaded or generated user content (relative to project root). */
const EH_UPLOAD_DIRS = [
    'assignments',
    'notes',
    'online_class',
    'course_materials',
    'uploads',
    'images/user_p_pics',
    'announcements',
    'certificates',
];

/** Files that are never treated as uploads. */
const EH_IGNORED_NAMES = ['.htaccess', 'index.html', 'index.php', '.gitkeep', '.DS_Store', 'web.config'];

function eh_root(): string
{
    return (string)realpath(EH_PROJECT_ROOT);
}

function eh_rel(string $abs): string
{
    return ltrim(str_replace('\\', '/', substr($abs, strlen(eh_root()))), '/');
}

/** Parse --flag and --key=value arguments. */
function eh_args(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $a) {
        if (!str_starts_with($a, '--')) continue;
        [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);
        $out[$k] = $v;
    }
    return $out;
}

/** Create the protected maintenance folder (and a subfolder) on demand. */
function eh_maintenance_dir(string $sub = ''): string
{
    $base = eh_root() . '/_maintenance';
    if (!is_dir($base)) {
        mkdir($base, 0750, true);
    }
    // Block web access (Apache 2.2 and 2.4) and directory listing.
    if (!is_file($base . '/.htaccess')) {
        file_put_contents($base . '/.htaccess', implode("\n", [
            '<IfModule mod_authz_core.c>',
            '    Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            '    Order allow,deny',
            '    Deny from all',
            '</IfModule>',
            'Options -Indexes',
            '',
        ]));
    }
    if (!is_file($base . '/index.html')) {
        file_put_contents($base . '/index.html', '');
    }
    $dir = $sub === '' ? $base : $base . '/' . $sub;
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    return $dir;
}

/**
 * Yield every regular file inside the upload folders.
 * @return Generator<string> absolute paths
 */
function eh_upload_files(array $dirs = EH_UPLOAD_DIRS): Generator
{
    foreach ($dirs as $d) {
        $abs = eh_root() . '/' . trim($d, '/');
        if (!is_dir($abs)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            /** @var SplFileInfo $f */
            if (!$f->isFile() || $f->isLink()) continue;
            $name = $f->getFilename();
            if (in_array($name, EH_IGNORED_NAMES, true) || str_starts_with($name, '.ehc_') || str_starts_with($name, '._')) continue;
            yield $f->getPathname();
        }
    }
}

/** Move a file, falling back to copy+delete across devices. */
function eh_move(string $from, string $to): bool
{
    if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0750, true) && !is_dir(dirname($to))) return false;
    if (@rename($from, $to)) return true;
    if (@copy($from, $to)) {
        @touch($to, (int)filemtime($from));
        return @unlink($from);
    }
    return false;
}

function eh_human(int|float $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($u) - 1) { $bytes /= 1024; $i++; }
    return sprintf($i ? '%.1f %s' : '%d %s', $bytes, $u[$i]);
}

function eh_csv_open(string $path, array $header)
{
    $fh = fopen($path, 'w');
    fputcsv($fh, $header);
    return $fh;
}

/**
 * Quarantine files listed as relative paths. Writes a manifest so the batch
 * can be restored with --restore=<batch>.
 * @return array{batch:string,moved:int,bytes:int,failed:int}
 */
function eh_quarantine(array $relPaths, string $reason): array
{
    $batch = date('Ymd_His');
    $dir = eh_maintenance_dir('quarantine/' . $batch);
    $manifest = eh_csv_open($dir . '/manifest.csv', ['original_path', 'size', 'mtime', 'reason']);
    $moved = $bytes = $failed = 0;

    foreach ($relPaths as $rel) {
        $src = eh_root() . '/' . $rel;
        if (!is_file($src)) continue;
        $size = (int)filesize($src);
        $mtime = (int)filemtime($src);
        if (eh_move($src, $dir . '/files/' . $rel)) {
            fputcsv($manifest, [$rel, $size, date('Y-m-d H:i:s', $mtime), $reason]);
            $moved++;
            $bytes += $size;
        } else {
            fwrite(STDERR, "  could not move: {$rel}\n");
            $failed++;
        }
    }
    fclose($manifest);
    return ['batch' => $batch, 'moved' => $moved, 'bytes' => $bytes, 'failed' => $failed];
}

/** Restore a quarantine batch to its original locations. */
function eh_restore(string $batch): int
{
    $batch = preg_replace('/[^0-9_]/', '', $batch);
    $dir = eh_root() . '/_maintenance/quarantine/' . $batch;
    if ($batch === '' || !is_file($dir . '/manifest.csv')) {
        fwrite(STDERR, "No quarantine batch '{$batch}' found in _maintenance/quarantine/.\n");
        return 1;
    }
    $fh = fopen($dir . '/manifest.csv', 'r');
    fgetcsv($fh);
    $ok = $fail = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $rel = (string)$row[0];
        $dest = eh_root() . '/' . $rel;
        if (is_file($dest)) { fwrite(STDERR, "  exists, skipped: {$rel}\n"); $fail++; continue; }
        if (eh_move($dir . '/files/' . $rel, $dest)) $ok++; else { fwrite(STDERR, "  failed: {$rel}\n"); $fail++; }
    }
    fclose($fh);
    echo "Restored {$ok} file(s)" . ($fail ? ", {$fail} problem(s)" : '') . " from batch {$batch}.\n";
    return $fail ? 1 : 0;
}
