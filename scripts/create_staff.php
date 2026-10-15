<?php

declare(strict_types=1);

use App\Models\User;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 6 || ($argv[5] ?? '') !== '--password-stdin') {
    fwrite(
        STDERR,
        "Usage: php scripts/create_staff.php <admin|catalog_manager> <email> <username> <full-name> --password-stdin\n"
        . "Use scripts/create_staff.ps1 for hidden password entry on Windows. Never put a password in command arguments.\n"
    );
    exit(1);
}

[, $role, $email, $username, $fullName] = $argv;
$email = strtolower(trim($email));
$username = trim($username);
$fullName = trim($fullName);

if (!in_array($role, ['admin', 'catalog_manager'], true)) {
    fwrite(STDERR, "Invalid role.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9._-]{4,40}$/', $username)) {
    fwrite(STDERR, "Invalid username.\n");
    exit(1);
}

if (!preg_match('/^.{2,120}$/usD', $fullName) || preg_match('/[\x00-\x1F\x7F]/', $fullName)) {
    fwrite(STDERR, "Enter a full name of 2–120 characters without control characters.\n");
    exit(1);
}

if (stream_isatty(STDIN)) {
    fwrite(STDERR, "Password input must be redirected by a secure prompt, not typed into this terminal.\n");
    exit(1);
}

try {
    require dirname(__DIR__) . '/bootstrap.php';
    // Bound input before hashing; one newline terminates the private input protocol.
    $input = stream_get_contents(STDIN, 75);
    $password = preg_replace('/\r?\n\z/', '', (string) $input);
    unset($input);
    if (str_contains($password, "\r") || str_contains($password, "\n") || \App\Core\Validator::passwordError($password) !== null) {
        fwrite(STDERR, "Password does not meet the Avela password policy.\n");
        exit(1);
    }
    $users = new User();

    if ($users->emailExists($email) || $users->usernameExists($username)) {
        fwrite(STDERR, "Email or username already exists.\n");
        exit(1);
    }

    $id = $users->createStaff(
        $role,
        $email,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $fullName
    );

    unset($password);
    echo "Created {$role} account #{$id}. Active; staff email OTP is required.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Staff creation failed. Check the private database configuration and account uniqueness.\n");
    exit(1);
}
