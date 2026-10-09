<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Models\User;
use App\Core\Validator;

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

$errors = Validator::staff([
    'role' => $role,
    'email' => $email,
    'username' => $username,
    'full_name' => $fullName,
    'password' => $password,
    'password_confirmation' => $password,
]);

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
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
