<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Models\User;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc < 6) {
    fwrite(
        STDERR,
        "Usage: php scripts/create_staff.php <admin|catalog_manager> <email> <username> <password> <full name>\n"
    );
    exit(1);
}

[, $role, $email, $username, $password] = $argv;
$fullName = implode(' ', array_slice($argv, 5));

if (!in_array($role, ['admin', 'catalog_manager'], true)) {
    fwrite(STDERR, "Invalid role.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9._-]{4,40}$/', $username)) {
    fwrite(STDERR, "Invalid username.\n");
    exit(1);
}

$minimumLength = (int) env('PASSWORD_MIN_LENGTH', 12);
$passwordIsValid = strlen($password) >= $minimumLength
    && preg_match('/[A-Z]/', $password)
    && preg_match('/[a-z]/', $password)
    && preg_match('/\d/', $password)
    && preg_match('/[^A-Za-z0-9]/', $password);

if (!$passwordIsValid) {
    fwrite(STDERR, "Password does not meet the Avela password policy.\n");
    exit(1);
}

try {
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

    echo "Created {$role} account #{$id} for {$email}. MFA is enabled.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
