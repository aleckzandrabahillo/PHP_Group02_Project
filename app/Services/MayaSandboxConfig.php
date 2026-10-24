<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

final class MayaSandboxConfig
{
    public static function client(): MayaSandboxClient
    {
        if (env('MAYA_LOCAL_SIMULATION', false) !== false) {
            throw new RuntimeException('Local simulation requires an explicitly injected test transport.');
        }
        if (env('MAYA_ENVIRONMENT', 'sandbox') !== 'sandbox') throw new RuntimeException('Only Maya Sandbox is supported.');
        $local = env('MAYA_LOCAL_HOSTED_SANDBOX', false) !== false;
        if ($local && !self::localHosted()) throw new RuntimeException('Local hosted Sandbox requires the guarded disposable runtime.');
        return new MayaSandboxClient(
            self::key('MAYA_PUBLIC_KEY', 'MAYA_SANDBOX_PUBLIC_KEY'),
            self::key('MAYA_SECRET_KEY', 'MAYA_SANDBOX_SECRET_KEY'),
            (string) env('MAYA_MERCHANT_ORIGIN', ''),
            null,
            $local
        );
    }

    public static function available(): bool
    {
        if (env('MAYA_LOCAL_SIMULATION', false) !== false) return self::localSimulation();
        if (!function_exists('curl_init') || !extension_loaded('openssl')) return false;
        if (env('MAYA_LOCAL_HOSTED_SANDBOX', false) !== false) {
            if (!self::localHosted()) return false;
        } elseif (env('MAYA_SANDBOX_ENABLED', false) !== true || env('MAYA_WEBHOOK_READY', false) !== true
            || env('MAYA_RECONCILIATION_READY', false) !== true) return false;
        try { self::client(); return true; } catch (\Throwable) { return false; }
    }

    /** Explicit loopback experiment: authenticated API reconciliation, no public webhook claim. */
    public static function localHosted(): bool
    {
        $launcher = defined('AVELA_LOCAL_HOSTED_SANDBOX_ACTIVE') && AVELA_LOCAL_HOSTED_SANDBOX_ACTIVE === true;
        $web = PHP_SAPI === 'cli-server' && ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1'
            && (int)($_SERVER['SERVER_PORT'] ?? 0) === 18080 && ($_SERVER['HTTP_HOST'] ?? '') === 'localhost:18080';
        return env('MAYA_LOCAL_HOSTED_SANDBOX', false) === true
            && env('MAYA_LOCAL_SIMULATION', false) === false
            && env('MAYA_SANDBOX_ENABLED', false) === true
            && env('MAYA_ENVIRONMENT', 'sandbox') === 'sandbox' && env('APP_ENV') === 'development'
            && env('APP_URL') === 'http://localhost:18080' && env('MAYA_MERCHANT_ORIGIN') === 'http://localhost:18080'
            && $launcher && ($web || PHP_SAPI === 'cli') && self::disposableDatabase();
    }

    /** Enabled only by an explicit private development launcher; never an API fallback. */
    public static function localSimulation(): bool
    {
        return env('MAYA_LOCAL_SIMULATION', false) === true
            && env('MAYA_LOCAL_HOSTED_SANDBOX', false) === false
            && env('MAYA_ENVIRONMENT', 'sandbox') === 'sandbox' && env('APP_ENV') === 'development'
            && defined('AVELA_LOCAL_SIMULATION_ACTIVE') && AVELA_LOCAL_SIMULATION_ACTIVE === true
            && PHP_SAPI === 'cli-server' && ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1'
            && self::disposableDatabase();
    }

    private static function disposableDatabase(): bool
    {
        return env('DB_HOST') === '127.0.0.1' && (int)env('DB_PORT', 3306) > 1024
            && !in_array((int)env('DB_PORT', 3306), [3306, 13307], true)
            && preg_match('/^avela_[a-z0-9_]*test_[a-z0-9_]+$/D', (string)env('DB_DATABASE')) === 1;
    }

    private static function key(string $preferred, string $legacy): string
    {
        $a = env($preferred, ''); $b = env($legacy, '');
        if (!is_string($a) || !is_string($b) || ($a !== '' && $b !== '' && !hash_equals($a, $b))) {
            throw new RuntimeException('Resolve conflicting private sandbox configuration.');
        }
        return $a !== '' ? $a : $b;
    }
}
