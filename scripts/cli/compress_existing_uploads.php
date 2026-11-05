<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Compress files that were uploaded before upload compression existed
|--------------------------------------------------------------------------
| Usage (from the project root):
|   php scripts/cli/compress_existing_uploads.php            # dry run: report only
|   php scripts/cli/compress_existing_uploads.php --apply    # compress in place
|
| Options:
|   --apply            Replace files with their compressed versions.
|   --no-backup        With --apply, do not keep originals (default: originals
|                      are copied to _maintenance/originals/<batch>/ first;
|                      delete that folder once you are happy with the result).
|   --dirs=a,b         Only these upload folders (default: all upload folders).
|   --no-pdf           Images only.
|   --max-pdf-mb=N     Skip PDFs larger than N MB (default 500).
|   --pdf-timeout=N    Seconds per PDF for Ghostscript (default 600).
|   --limit=N          Stop after N files (useful for a first trial).
|   --reprocess        Ignore the record of files already processed.
|
| Images (JPEG/PNG/WebP) are downscaled to 2400px and re-encoded; PDFs are
| rewritten with Ghostscript when installed. File names never change, so
| database references stay valid. Already-compressed formats (zip, rar,
| docx, ...) are left alone because they cannot be made meaningfully smaller.
*/

require_once __DIR__ . '/maintenance_common.php';
require_once dirname(__DIR__) . '/upload_compressor.php';

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$args      = eh_args($argv);
$apply     = isset($args['apply']);
$backup    = $apply && !isset($args['no-backup']);
$noPdf     = isset($args['no-pdf']);
$limit     = isset($args['limit']) ? max(1, (int)$args['limit']) : 0;
$maxPdfMb  = isset($args['max-pdf-mb']) ? (int)$args['max-pdf-mb'] : 500;
$pdfTime   = isset($args['pdf-timeout']) ? (int)$args['pdf-timeout'] : 600;
$dirs      = isset($args['dirs']) ? array_filter(array_map('trim', explode(',', (string)$args['dirs']))) : EH_UPLOAD_DIRS;
$reprocess = isset($args['reprocess']);

$batch      = date('Ymd_His');
$stateFile  = eh_maintenance_dir() . '/compress_state.json';
$state      = (!$reprocess && is_file($stateFile)) ? (json_decode((string)file_get_contents($stateFile), true) ?: []) : [];
$reportPath = eh_maintenance_dir('reports') . "/compress_{$batch}" . ($apply ? '' : '_dryrun') . '.csv';
$report     = eh_csv_open($reportPath, ['path', 'type', 'status', 'before_bytes', 'after_bytes', 'note']);
// Created lazily (on first backup copy) so runs that change nothing leave no empty folder.
$backupDir  = $backup ? eh_maintenance_dir('originals') . '/' . $batch : '';

$gs = eh_ghostscript_binary();
echo ($apply ? 'APPLY' : 'DRY RUN') . ' - compressing existing uploads in: ' . implode(', ', $dirs) . "\n";
echo 'Ghostscript: ' . ($gs !== '' ? $gs : 'not installed (PDFs will be skipped)') . "\n";
if ($backup) echo "Originals will be kept in _maintenance/originals/{$batch}/\n";
echo "\n";

$seen = $done = $changed = 0;
$before = $after = 0;
$skipReasons = [];

foreach (eh_upload_files($dirs) as $abs) {
    $rel  = eh_rel($abs);
    $size = (int)filesize($abs);
    $key  = $size . ':' . filemtime($abs);

    if (($state[$rel] ?? null) === $key) continue; // processed in an earlier run, unchanged since

    $head = (string)@file_get_contents($abs, false, null, 0, 8);
    $isPdf = str_starts_with($head, '%PDF');
    $isImg = !$isPdf && in_array(@getimagesize($abs)[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
    if (!$isPdf && !$isImg) continue;
    if ($isPdf && $noPdf) continue;

    $seen++;
    if ($limit && $seen > $limit) break;

    $opts = ['pdf_timeout' => $pdfTime, 'pdf_max_mb' => $maxPdfMb, 'dry_run' => !$apply];

    // Keep the original before touching it.
    $backupPath = '';
    if ($backup) {
        $free = @disk_free_space(dirname($backupDir));
        if ($free !== false && $free < $size * 3) {
            fwrite(STDERR, "Stopping: not enough free disk space to keep a backup of {$rel}.\n");
            break;
        }
        $backupPath = $backupDir . '/' . $rel;
        @mkdir(dirname($backupPath), 0750, true);
        if (!@copy($abs, $backupPath)) {
            fwrite(STDERR, "  backup failed, skipping: {$rel}\n");
            continue;
        }
        @touch($backupPath, (int)filemtime($abs));
    }

    $r = eh_compress_upload($abs, $opts);
    $done++;

    if ($backupPath !== '' && $r['status'] !== 'compressed') {
        @unlink($backupPath); // nothing changed, no backup needed
    }

    $before += $r['before'];
    $after  += in_array($r['status'], ['compressed', 'would_compress'], true) ? $r['after'] : $r['before'];

    if (in_array($r['status'], ['compressed', 'would_compress'], true)) {
        $changed++;
        printf("  %-15s %10s -> %10s  %s\n", $r['status'], eh_human($r['before']), eh_human($r['after']), $rel);
    } else {
        $skipReasons[$r['reason']] = ($skipReasons[$r['reason']] ?? 0) + 1;
    }
    fputcsv($report, [$rel, $r['type'], $r['status'], $r['before'], $r['after'], $r['reason']]);

    // Remember finished files (only in apply mode, and not transient failures).
    $transient = in_array($r['reason'], ['Ghostscript not installed', 'Ghostscript timed out', 'proc_open disabled', 'compression disabled'], true)
        || str_starts_with($r['reason'], 'PDF larger than');
    if ($apply && $r['status'] !== 'error' && !$transient) {
        clearstatcache(true, $abs);
        $state[$rel] = filesize($abs) . ':' . filemtime($abs);
        if ($done % 25 === 0) file_put_contents($stateFile, json_encode($state));
    }
}

fclose($report);
if ($apply) file_put_contents($stateFile, json_encode($state));

$saved = $before - $after;
echo "\nChecked {$done} image/PDF file(s); " . ($apply ? 'compressed' : 'would compress') . " {$changed}.\n";
printf("Size of checked files: %s -> %s (%s %s)\n", eh_human($before), eh_human($after), $apply ? 'saved' : 'would save', eh_human($saved));
if ($skipReasons) {
    echo "Left unchanged:\n";
    arsort($skipReasons);
    foreach ($skipReasons as $why => $n) echo "  {$n} x {$why}\n";
}
echo "Report: _maintenance/reports/" . basename($reportPath) . "\n";
if ($backup && $changed) {
    echo "Originals kept in _maintenance/originals/{$batch}/ - delete that folder after checking the site.\n";
}
if (!$apply) echo "\nNothing was changed. Re-run with --apply to compress.\n";
