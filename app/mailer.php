<?php
declare(strict_types=1);

function sendAppEmail(string $recipient, string $subject, string $message): bool
{
    $from = appConfig('MAIL_FROM', '');
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || appConfig('MAIL_ENABLED', '0') !== '1') {
        return false;
    }

    $headers = [
        'From' => $from,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'Campus2Career',
    ];
    return mail($recipient, $subject, $message, $headers);
}

function sendVerificationEmail(PDO $pdo, int $userId, string $email, string $fullName): bool
{
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO c2c_email_verifications (user_id, token_hash, expires_at)
         VALUES (:user_id, :token_hash, :expires_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expires,
    ]);

    $url = rtrim((string) appConfig('APP_URL', 'http://localhost/BIS'), '/')
        . '/index.php?page=verify-email&token=' . rawurlencode($token);
    return sendAppEmail(
        $email,
        'Verify your Campus2Career email',
        "Hello {$fullName},\n\nVerify your email address within 24 hours:\n{$url}\n\nIf you did not create an account, you can ignore this message."
    );
}

function sendPasswordResetEmail(PDO $pdo, int $userId, string $email): bool
{
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO c2c_password_resets (user_id, token_hash, expires_at)
         VALUES (:user_id, :token_hash, :expires_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expires,
    ]);

    $url = rtrim((string) appConfig('APP_URL', 'http://localhost/BIS'), '/')
        . '/index.php?page=reset-password&token=' . rawurlencode($token);
    return sendAppEmail(
        $email,
        'Reset your Campus2Career password',
        "A password reset was requested for your account. This link expires in one hour:\n{$url}\n\nIf you did not request a reset, you can ignore this message."
    );
}
