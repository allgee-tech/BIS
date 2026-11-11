<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/data.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (strtolower((string) appConfig('APP_ENV', 'development')) === 'production' && !$isHttps) {
    http_response_code(400);
    exit('HTTPS must be enabled for the production Campus2Career application.');
}
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:");
header('Cache-Control: no-store, private');

if (isset($_SESSION['user_id'])) {
    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
    if ($lastActivity === 0 || $authenticatedAt === 0 || $now - $lastActivity > 1800 || $now - $authenticatedAt > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Your session expired. Please sign in again.'];
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function basePath(): string
{
    $configured = trim((string) appConfig('APP_BASE_PATH', '/BIS'));
    return $configured === '' || $configured === '/' ? '' : '/' . trim($configured, '/');
}

function pageUrl(string $page, array $params = []): string
{
    $query = $params === [] ? '' : '&' . http_build_query($params);
    return basePath() . '/index.php?page=' . rawurlencode($page) . $query;
}

function redirectTo(string $page, array $params = []): never
{
    header('Location: ' . pageUrl($page, $params), true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function currentUser(?PDO $pdo = null): ?array
{
    static $cached;
    static $loaded = false;
    if ($loaded) {
        return $cached;
    }
    $loaded = true;

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $pdo ??= database();
    $stmt = $pdo->prepare(
        'SELECT id, email, full_name, role, status, email_verified_at
         FROM c2c_users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user === false || $user['status'] !== 'active') {
        $_SESSION = [];
        session_regenerate_id(true);
        $cached = null;
        return null;
    }

    if ($user['role'] === 'student') {
        $profile = $pdo->prepare('SELECT institution_id FROM c2c_student_profiles WHERE user_id = :user');
        $profile->execute(['user' => (int) $user['id']]);
        $user['institution_id'] = $profile->fetchColumn() ?: null;
    }

    $cached = $user;
    return $cached;
}

function requireAuth(?PDO $pdo = null): array
{
    $user = currentUser($pdo);
    if ($user === null) {
        flash('Please sign in to continue.', 'error');
        redirectTo('login');
    }

    return $user;
}

function requireRole(array $roles, ?PDO $pdo = null): array
{
    $user = requireAuth($pdo);
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        throw new RuntimeException('You do not have permission to access this page.');
    }

    return $user;
}

function requestMethod(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}
