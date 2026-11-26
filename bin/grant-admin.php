<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/app/security.php';

$email = strtolower(trim((string) appConfig('C2C_GRANT_ADMIN_EMAIL', '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set C2C_GRANT_ADMIN_EMAIL to the existing account email in .env, then run this script.\n");
    exit(2);
}

try {
    $pdo = database();
    $lookup = $pdo->prepare('SELECT id, role, status FROM c2c_users WHERE email = :email LIMIT 1');
    $lookup->execute(['email' => $email]);
    $account = $lookup->fetch();
    if ($account === false) {
        fwrite(STDERR, "No existing Campus2Career account was found for {$email}. The account must register first.\n");
        exit(3);
    }
    if ($account['status'] !== 'active') {
        fwrite(STDERR, "The account is suspended; reactivate it before granting administrator access.\n");
        exit(4);
    }
    if ($account['role'] === 'admin') {
        fwrite(STDOUT, "The account already has administrator access.\n");
        exit(0);
    }

    $update = $pdo->prepare("UPDATE c2c_users SET role = 'admin' WHERE id = :id AND email = :email AND status = 'active'");
    $update->execute(['id' => (int) $account['id'], 'email' => $email]);
    auditEvent($pdo, (int) $account['id'], 'admin_access_granted', 'user', (int) $account['id'], ['email' => $email]);
    fwrite(STDOUT, "Administrator access granted to {$email}.\n");
} catch (Throwable $exception) {
    error_log('Campus2Career admin access grant failed: ' . $exception->getMessage());
    fwrite(STDERR, "Administrator access could not be granted. Check the PHP error log.\n");
    exit(1);
}
