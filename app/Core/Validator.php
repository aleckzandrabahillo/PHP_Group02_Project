<?php

namespace App\Core;

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

        if ($error = self::passwordError($password)) {
            $errors['password'] = $error;
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        return $errors;
    }

    public static function passwordError(string $password): ?string
    {
        $policy = \App\Services\SecurityPolicy::values();
        $min = $policy['password_min_length'];
        $length = preg_match_all('/./us', $password);
        if ($length === false || $length < $min || strlen($password) > 72
            || preg_match_all('/[A-Z]/', $password) < $policy['password_min_uppercase']
            || preg_match_all('/[a-z]/', $password) < $policy['password_min_lowercase']
            || preg_match_all('/[0-9]/', $password) < $policy['password_min_numbers']
            || preg_match_all('/[^A-Za-z0-9\x09-\x0d\x20]/u', $password) < $policy['password_min_special']
            || str_contains($password, "\0")) {
            return "Use at least {$min} characters, {$policy['password_min_uppercase']} uppercase, {$policy['password_min_lowercase']} lowercase, {$policy['password_min_numbers']} number(s) and {$policy['password_min_special']} special character(s), within 72 bytes.";
        }
        return null;
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
