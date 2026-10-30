<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

final class SecurityPolicy
{
    public const RULES = [
        // Keep the default at three; the existing unsigned-byte counters support 1–255.
        'max_login_attempts' => [1, 255, 3, 'MAX_LOGIN_ATTEMPTS'],
        'lockout_minutes' => [15, 120, 15, 'LOCKOUT_MINUTES'],
        'session_timeout_minutes' => [1, 30, 30, 'SESSION_TIMEOUT_MINUTES'],
        'password_min_length' => [12, 64, 12, 'PASSWORD_MIN_LENGTH'],
        'password_min_uppercase' => [1, 4, 1, 'PASSWORD_MIN_UPPERCASE'],
        'password_min_lowercase' => [1, 4, 1, 'PASSWORD_MIN_LOWERCASE'],
        'password_min_numbers' => [1, 4, 1, 'PASSWORD_MIN_NUMBERS'],
        'password_min_special' => [1, 4, 1, 'PASSWORD_MIN_SPECIAL'],
    ];

    public static function values(): array
    {
        $stored = Database::connection()->query('SELECT setting_key, setting_value FROM security_settings')
            ->fetchAll(\PDO::FETCH_KEY_PAIR);
        $values = [];
        foreach (self::RULES as $key => [$min, $max, $default, $env]) {
            $value = filter_var($stored[$key] ?? env($env, $default), FILTER_VALIDATE_INT);
            $values[$key] = $value === false ? $default : max($min, min($max, $value));
        }
        return $values;
    }

    public static function validate(array $input): array
    {
        if (array_diff(array_keys($input), array_keys(self::RULES))) {
            throw new InvalidArgumentException('Unknown security setting.');
        }
        $values = [];
        $current = self::values();
        foreach (self::RULES as $key => [$min, $max]) {
            // Older policy forms preserve newly added complexity settings rather than resetting them.
            $raw = $input[$key] ?? (in_array($key,['password_min_uppercase','password_min_lowercase','password_min_numbers','password_min_special'],true) ? $current[$key] : null);
            $value = is_string($raw) || is_int($raw) ? filter_var($raw, FILTER_VALIDATE_INT) : false;
            if ($value === false || $value < $min || $value > $max) {
                throw new InvalidArgumentException("Use a whole number from {$min} to {$max} for " . str_replace('_', ' ', $key) . '.');
            }
            $values[$key] = $value;
        }
        return $values;
    }
}
