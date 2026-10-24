<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/** Optional real Windows Defender custom-file scan. No filename, output or detection text is logged. */
final class UploadScanner
{
    public static function assertClean(string $file): void
    {
        $driver = env('UPLOAD_SCANNER', 'disabled');
        if ($driver === 'disabled') return; // Validation still applies; no antivirus claim in this mode.
        if ($driver !== 'defender' || PHP_OS_FAMILY !== 'Windows' || !function_exists('proc_open')) self::unavailable();
        $binary = realpath((string) env('UPLOAD_DEFENDER_BINARY', ''));
        $path = realpath($file);
        $roots = [(string)getenv('ProgramData').'/Microsoft/Windows Defender/Platform/', (string)getenv('ProgramFiles').'/Windows Defender/'];
        $normalize = static fn(string $p): string => strtolower(str_replace('\\','/',$p));
        $trusted = false;
        foreach ($roots as $root) if ($binary && str_starts_with($normalize($binary),$normalize($root))) $trusted=true;
        if (!$trusted || basename($binary)!=='MpCmdRun.exe' || !$path || is_link($file) || !is_file($path)) self::unavailable();
        // No remediation: zero can only mean clean, rather than successfully remediated.
        // This fixed custom scan ignores exclusions and never scans the whole machine.
        $exit = (new UploadScanProcess())->run([$binary,'-Scan','-ScanType','3','-File',$path,'-DisableRemediation'],15.0);
        if ($exit !== 0) throw new RuntimeException('The image could not pass its security check. Choose another image or try again later.');
    }

    private static function unavailable(): never
    {
        throw new RuntimeException('Image security checks are temporarily unavailable. Please try again later.');
    }
}
