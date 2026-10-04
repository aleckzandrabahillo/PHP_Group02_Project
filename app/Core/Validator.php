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

        $min = (int) env('PASSWORD_MIN_LENGTH', 12);
        if (
            strlen($password) < $min ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/\d/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $errors['password'] = "Use at least {$min} characters with uppercase, lowercase, a number, and a special character.";
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

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
        if (trim((string) ($input['captcha'] ?? '')) === '') {
            $errors['captcha'] = 'Enter the CAPTCHA code.';
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
