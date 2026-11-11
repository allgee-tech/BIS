<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/app/security.php';

$email = trim((string) appConfig('C2C_ADMIN_EMAIL', ''));
$name = trim((string) appConfig('C2C_ADMIN_NAME', ''));
$password = (string) appConfig('C2C_ADMIN_PASSWORD', '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Set C2C_ADMIN_EMAIL, C2C_ADMIN_NAME and a 12–72 byte C2C_ADMIN_PASSWORD in the private .env file, then run this script once.\n");
    exit(2);
}

try {
    $pdo = database();
    $hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM c2c_users WHERE role = 'admin'")->fetchColumn();
    if ($hasAdmin > 0) {
        fwrite(STDERR, "A Campus2Career administrator already exists; no account was changed.\n");
        exit(3);
    }

    $lock = (int) $pdo->query("SELECT GET_LOCK('c2c_first_admin', 5)")->fetchColumn() === 1;
    if (!$lock) {
        throw new RuntimeException('Could not acquire the initial administrator setup lock.');
    }

    try {
        $hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM c2c_users WHERE role = 'admin'")->fetchColumn();
        if ($hasAdmin > 0) {
            fwrite(STDERR, "A Campus2Career administrator already exists; no account was changed.\n");
            exit(3);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO c2c_users (email, full_name, password_hash, role)
             VALUES (:email, :full_name, :password_hash, 'admin')"
        );
        $stmt->execute([
            'email' => $email,
            'full_name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        auditEvent($pdo, $userId, 'admin_created', 'user', $userId);
        fwrite(STDOUT, "Initial Campus2Career administrator created.\n");
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('c2c_first_admin')");
    }
} catch (Throwable $exception) {
    error_log('Campus2Career admin initialization failed: ' . $exception->getMessage());
    fwrite(STDERR, "The administrator could not be created. Check the PHP error log.\n");
    exit(1);
}
