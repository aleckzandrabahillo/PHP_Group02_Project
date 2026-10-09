<?php

namespace App\Services;

use App\Core\Database;

final class SecuritySettings
{
    // key => [env fallback, default, min, max]
    private const RULES = [
        'password_min_length'     => ['PASSWORD_MIN_LENGTH', 12, 8, 64],
        'max_login_attempts'      => ['MAX_LOGIN_ATTEMPTS', 5, 3, 10],
        'lockout_minutes'         => ['LOCKOUT_MINUTES', 15, 1, 1440],
        'session_timeout_minutes' => ['SESSION_TIMEOUT_MINUTES', 30, 5, 480],
        'staff_mfa_required'      => [null, 1, 0, 1],
        'captcha_enabled'         => [null, 1, 0, 1],
    ];
    private static ?array $cache = null;

    public static function get(string $key): int
    {
        if (self::$cache === null) {
            try {
                self::$cache = Database::connection()
                    ->query('SELECT setting_key, setting_value FROM security_settings')
                    ->fetchAll(\PDO::FETCH_KEY_PAIR);
            } catch (\Throwable) { self::$cache = []; }
        }
        [$envKey, $default] = self::RULES[$key];
        return (int) (self::$cache[$key] ?? ($envKey ? env($envKey, $default) : $default));
    }

    public function update(array $input, int $actorId, AuditService $audit): array
    {
        $changes = [];
        foreach (self::RULES as $key => [, , $min, $max]) {
            if (!isset($input[$key])) continue;
            $val = (int) $input[$key];
            if ($val < $min || $val > $max) continue;           // reject out-of-range silently or collect errors
            if ($val === self::get($key)) continue;
            Database::connection()->prepare(
                'INSERT INTO security_settings (setting_key, setting_value, updated_by) VALUES (:k,:v,:u)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)'
            )->execute(['k'=>$key, 'v'=>$val, 'u'=>$actorId]);
            $changes[] = "$key: " . self::get($key) . " → $val";
        }
        self::$cache = null;
        if ($changes) $audit->record($actorId, 'security.update', 'security_settings', null, implode('; ', $changes));
        return $changes;
    }
}