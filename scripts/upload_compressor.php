<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Upload compression + upload safety helpers
|--------------------------------------------------------------------------
| eh_compress_upload($path)
|   Shrinks a stored upload IN PLACE, keeping its name and extension so
|   existing database references stay valid:
|     - JPEG / PNG / WebP: downscaled to EH_IMG_MAX_EDGE and re-encoded (GD).
|     - PDF: rewritten with Ghostscript when it is installed (skipped if not).
|   The original is kept unless the result is at least EH_MIN_SAVING smaller.
|   Never throws and never fails the upload: compression is best-effort.
|
| eh_upload_extension_allowed($name, $allowed) / eh_random_upload_name($ext)
|   Shared helpers for handlers that previously accepted any file type.
|
| Tunables (environment variables override the defaults):
|   EH_UPLOAD_COMPRESSION=0   disable compression entirely
|   EH_GHOSTSCRIPT=/path/gs   Ghostscript binary (auto-detected otherwise)
|   EH_PDF_QUALITY=ebook      gs PDFSETTINGS: screen|ebook|printer|prepress
*/

const EH_IMG_MAX_EDGE       = 2400;      // px, longest side
const EH_IMG_MAX_PIXELS     = 60000000;  // skip images too large to decode safely
const EH_JPEG_QUALITY       = 80;
const EH_WEBP_QUALITY       = 80;
const EH_MIN_SAVING         = 0.05;      // keep compressed copy only if >= 5% smaller
const EH_MIN_SAVING_BYTES   = 2048;
const EH_PDF_UPLOAD_MAX_MB  = 50;        // larger PDFs are left for the batch script
const EH_PDF_UPLOAD_TIMEOUT = 60;        // seconds, during a web upload

/** Extensions that must never be stored from an upload (executable on the web server). */
const EH_DANGEROUS_EXTENSIONS = [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
    'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess', 'htpasswd', 'ini', 'exe', 'svg',
];

function eh_compression_enabled(): bool
{
    $flag = getenv('EH_UPLOAD_COMPRESSION');
    return $flag === false || !in_array(strtolower(trim($flag)), ['0', 'false', 'off', 'no'], true);
}

/**
 * Compress a stored upload in place.
 *
 * @param array{pdf_timeout?:int,pdf_max_mb?:int,dry_run?:bool} $opts
 * @return array{status:string,type:string,before:int,after:int,reason:string}
 */
function eh_compress_upload(string $path, array $opts = []): array
{
    $result = ['status' => 'skipped', 'type' => '', 'before' => 0, 'after' => 0, 'reason' => ''];

    try {
        if (!eh_compression_enabled()) return ['reason' => 'compression disabled'] + $result;
        if (!is_file($path) || !is_writable($path)) return ['reason' => 'not a writable file'] + $result;

        clearstatcache(true, $path);
        $before = (int)filesize($path);
        $result['before'] = $result['after'] = $before;
        if ($before < 20 * 1024) return ['reason' => 'already small'] + $result;

        $head = (string)file_get_contents($path, false, null, 0, 8);

        if (str_starts_with($head, '%PDF')) {
            $result['type'] = 'pdf';
            $tmp = eh_compress_pdf_to_temp(
                $path,
                (int)($opts['pdf_timeout'] ?? EH_PDF_UPLOAD_TIMEOUT),
                (int)($opts['pdf_max_mb'] ?? EH_PDF_UPLOAD_MAX_MB),
                $reason
            );
        } else {
            $info = @getimagesize($path);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                return ['reason' => 'type not compressible'] + $result;
            }
            $result['type'] = image_type_to_extension($info[2], false);
            $tmp = eh_compress_image_to_temp($path, $info, $reason);
        }

        if ($tmp === null) return ['reason' => $reason] + $result;

        clearstatcache(true, $tmp);
        $after = (int)filesize($tmp);
        $saving = $before - $after;

        if ($after <= 0 || $saving < EH_MIN_SAVING_BYTES || $saving < $before * EH_MIN_SAVING) {
            @unlink($tmp);
            return ['reason' => 'no worthwhile saving'] + $result;
        }

        if (!empty($opts['dry_run'])) {
            @unlink($tmp);
            return ['status' => 'would_compress', 'after' => $after, 'reason' => 'dry run'] + $result;
        }

        $mtime = (int)filemtime($path);
        $perms = fileperms($path) & 0777;

        if (!@rename($tmp, $path)) {
            // Windows / cross-device fallback.
            if (!@copy($tmp, $path)) {
                @unlink($tmp);
                return ['status' => 'error', 'reason' => 'could not replace original'] + $result;
            }
            @unlink($tmp);
        }
        @chmod($path, $perms ?: 0644);
        @touch($path, $mtime); // keep the original upload time

        return ['status' => 'compressed', 'after' => $after, 'reason' => ''] + $result;
    } catch (Throwable $e) {
        error_log('[upload-compressor] ' . $path . ': ' . $e->getMessage());
        return ['status' => 'error', 'reason' => $e->getMessage()] + $result;
    }
}

/** Temp file in the same directory so the final rename is atomic. */
function eh_temp_beside(string $path): string
{
    return dirname($path) . DIRECTORY_SEPARATOR . '.ehc_' . bin2hex(random_bytes(6)) . '.tmp';
}

/**
 * @param array<int|string,mixed> $info getimagesize() result
 */
function eh_compress_image_to_temp(string $path, array $info, ?string &$reason): ?string
{
    $reason = '';
    [$w, $h, $type] = [(int)$info[0], (int)$info[1], (int)$info[2]];

    if (!extension_loaded('gd')) { $reason = 'GD not available'; return null; }
    if ($w < 1 || $h < 1 || $w * $h > EH_IMG_MAX_PIXELS) { $reason = 'image too large to process'; return null; }

    // Decoding needs ~5 bytes per pixel plus a resized copy.
    $need = (int)($w * $h * 5 * 2 + 32 * 1024 * 1024);
    $limit = eh_bytes((string)ini_get('memory_limit'));
    if ($limit > 0 && $limit < $need) @ini_set('memory_limit', (string)max($need, 256 * 1024 * 1024));

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default        => false,
    };
    if (!$src) { $reason = 'could not decode image'; return null; }

    if ($type === IMAGETYPE_JPEG) {
        $src = eh_apply_exif_orientation($src, $path);
        $w = imagesx($src);
        $h = imagesy($src);
    }

    $scale = min(1.0, EH_IMG_MAX_EDGE / max($w, $h));
    $img = $src;
    if ($scale < 1.0) {
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $img = imagecreatetruecolor($nw, $nh);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        }
        imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
    } elseif ($type !== IMAGETYPE_JPEG) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    $tmp = eh_temp_beside($path);
    $ok = match ($type) {
        IMAGETYPE_JPEG => (function () use ($img, $tmp) { imageinterlace($img, true); return imagejpeg($img, $tmp, EH_JPEG_QUALITY); })(),
        IMAGETYPE_PNG  => imagepng($img, $tmp, 9),
        IMAGETYPE_WEBP => imagewebp($img, $tmp, EH_WEBP_QUALITY),
        default        => false,
    };
    imagedestroy($img);

    if (!$ok || !is_file($tmp)) { @unlink($tmp); $reason = 'could not encode image'; return null; }
    return $tmp;
}

/** Rotate JPEGs according to their EXIF orientation (pixels are re-saved without EXIF). */
function eh_apply_exif_orientation(GdImage $img, string $path): GdImage
{
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($path);
    $angle = match ((int)($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    if ($angle === 0) return $img;
    $rotated = imagerotate($img, $angle, 0);
    if ($rotated) { imagedestroy($img); return $rotated; }
    return $img;
}

function eh_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return -1;
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
}

/** Locate Ghostscript (cached). Returns '' when not installed. */
function eh_ghostscript_binary(): string
{
    static $bin = null;
    if ($bin !== null) return $bin;

    // Shared hosting commonly disables process functions; on PHP 8 calling a
    // disabled function throws, so check first. Without proc_open, Ghostscript
    // cannot be run at all.
    if (!eh_function_usable('proc_open')) return $bin = '';

    // @ on is_file(): open_basedir restrictions emit warnings for system paths.
    $env = getenv('EH_GHOSTSCRIPT');
    if ($env !== false && $env !== '' && @is_file($env)) return $bin = $env;

    if (eh_function_usable('shell_exec')) {
        $candidates = DIRECTORY_SEPARATOR === '\\' ? ['gswin64c', 'gswin32c'] : ['gs'];
        foreach ($candidates as $c) {
            $probe = @shell_exec((DIRECTORY_SEPARATOR === '\\' ? 'where ' : 'command -v ') . escapeshellarg($c) . ' 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'));
            $line = trim(strtok((string)$probe, "\r\n") ?: '');
            if ($line !== '' && @is_file($line)) return $bin = $line;
        }
    }
    foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/bin/gs'] as $p) {
        if (@is_file($p)) return $bin = $p;
    }
    return $bin = '';
}

/** True when a function exists and is not listed in disable_functions. */
function eh_function_usable(string $fn): bool
{
    if (!function_exists($fn)) return false;
    $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    return !in_array(strtolower($fn), $disabled, true);
}

function eh_compress_pdf_to_temp(string $path, int $timeout, int $maxMb, ?string &$reason): ?string
{
    $reason = '';
    $gs = eh_ghostscript_binary();
    if ($gs === '') { $reason = 'Ghostscript not installed'; return null; }
    if (!eh_function_usable('proc_open') || !eh_function_usable('proc_get_status')) { $reason = 'proc_open disabled'; return null; }
    if ($maxMb > 0 && filesize($path) > $maxMb * 1024 * 1024) { $reason = "PDF larger than {$maxMb} MB"; return null; }

    $quality = strtolower((string)(getenv('EH_PDF_QUALITY') ?: 'ebook'));
    if (!in_array($quality, ['screen', 'ebook', 'printer', 'prepress'], true)) $quality = 'ebook';

    $tmp = eh_temp_beside($path);
    $cmd = [
        $gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5', "-dPDFSETTINGS=/{$quality}",
        '-dDetectDuplicateImages=true', '-dNOPAUSE', '-dQUIET', '-dBATCH', '-dSAFER',
        '-sOutputFile=' . $tmp, $path,
    ];

    $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) { $reason = 'could not start Ghostscript'; return null; }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $deadline = time() + max(5, $timeout);
    $exit = -1;
    while (true) {
        fread($pipes[1], 8192);
        fread($pipes[2], 8192);
        $st = proc_get_status($proc);
        if (!$st['running']) { $exit = (int)$st['exitcode']; break; }
        if (time() > $deadline) {
            proc_terminate($proc, 9);
            $reason = 'Ghostscript timed out';
            break;
        }
        usleep(200000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closeCode = proc_close($proc);
    if ($exit === -1 && $reason === '') $exit = $closeCode;

    $valid = is_file($tmp) && filesize($tmp) > 0
        && (string)file_get_contents($tmp, false, null, 0, 5) === '%PDF-';

    if ($reason !== '' || $exit !== 0 || !$valid) {
        @unlink($tmp);
        if ($reason === '') $reason = 'Ghostscript failed (exit ' . $exit . '), file may be encrypted or damaged';
        return null;
    }
    return $tmp;
}

/* ------------------------------------------------------------------ */
/* Upload safety helpers                                               */
/* ------------------------------------------------------------------ */

/** True when the file's extension is in $allowed and is not an executable type. */
function eh_upload_extension_allowed(string $originalName, array $allowed): bool
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '' || in_array($ext, EH_DANGEROUS_EXTENSIONS, true)) return false;
    // Block double extensions such as "shell.php.jpg" that some servers execute.
    foreach (explode('.', strtolower(basename($originalName))) as $part) {
        if (in_array($part, EH_DANGEROUS_EXTENSIONS, true)) return false;
    }
    return in_array($ext, array_map('strtolower', $allowed), true);
}

/** Random, safe stored filename that keeps only a validated extension. */
function eh_random_upload_name(string $originalName, string $prefix = 'up_'): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
    return $prefix . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
}
