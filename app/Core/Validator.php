<?php

namespace App\Core;

use App\Services\SecuritySettings;

final class Validator
{
    public static function registration(array $input): array
    {
        $errors = [];
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $contact = trim((string) ($input['contact_no'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirmation'] ?? '');

        if (strlen($fullName) < 2 || strlen($fullName) > 120) {
            $errors['full_name'] = 'Enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!preg_match('/^[A-Za-z0-9._-]{4,40}$/', $username)) {
            $errors['username'] = 'Use 4–40 letters, numbers, dots, underscores, or hyphens.';
        }
        if ($contact === '') {
            $errors['contact_no'] = 'Enter your contact number.';
        } elseif (self::normalizePhilippineMobile($contact) === null) {
            $errors['contact_no'] = 'Enter a valid Philippine mobile number, such as 09XXXXXXXXX.';
        }
        if ($msg = self::password($password)) $errors['password'] = $msg;

        return $errors;
    }

public static function password(string $password): ?string
{
    $min = SecuritySettings::get('password_min_length');
    if (
        strlen($password) < $min ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/\d/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        return "Use at least {$min} characters with uppercase, lowercase, a number, and a special character.";
    }
    return null;
}

    public static function staff(array $input): array
    {
        $errors = [];
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email    = trim((string) ($input['email'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (!in_array($input['role'] ?? '', ['admin', 'catalog_manager'], true)) $errors['role'] = 'Choose a valid role.';
        if (strlen($fullName) < 2 || strlen($fullName) > 120) $errors['full_name'] = 'Enter the staff member\'s full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors['email'] = 'Enter a valid email address.';
        if (!preg_match('/^[A-Za-z0-9._-]{4,40}$/', $username)) $errors['username'] = 'Use 4–40 letters, numbers, dots, underscores, or hyphens.';
        if ($msg = self::password($password)) $errors['password'] = $msg;
        if ($password !== (string) ($input['password_confirmation'] ?? '')) $errors['password_confirmation'] = 'Passwords do not match.';
        return $errors;
    }

    public static function login(array $input): array
    {
        $errors = [];
        if (trim((string) ($input['identifier'] ?? '')) === '') {
            $errors['identifier'] = 'Enter your email or username.';
        }
        if ((string) ($input['password'] ?? '') === '') {
            $errors['password'] = 'Enter your password.';
        }
        return $errors;
       
    }

    /**
     * Normalize common Philippine mobile formats to +63XXXXXXXXXX.
     * Returns null when the input is not a valid 09XXXXXXXXX / +639XXXXXXXXX number.
     */
    public static function normalizePhilippineMobile(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if ($digits === null || $digits === '') {
            return null;
        }

        if (preg_match('/^09\d{9}$/', $digits)) {
            return '+63' . substr($digits, 1);
        }

        if (preg_match('/^639\d{9}$/', $digits)) {
            return '+' . $digits;
        }

        return null;
    }
}
