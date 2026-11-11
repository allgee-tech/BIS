<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . trim($value, "\"'"));
        }
    }
}

function appConfig(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = appConfig('DB_HOST', '127.0.0.1');
    $port = appConfig('DB_PORT', '3306');
    $name = appConfig('DB_NAME', 'BIS');
    $user = appConfig('DB_USER', 'root');
    $password = getenv('DB_PASSWORD');

    if ($password === false) {
        throw new RuntimeException('Set DB_PASSWORD in the private .env file for this MySQL installation.');
    }
    if ($name !== 'BIS') {
        throw new RuntimeException('This local application is configured to use the existing BIS database.');
    }
    if (strtolower((string) appConfig('APP_ENV', 'development')) === 'production' && ($user === 'root' || $password === '')) {
        throw new RuntimeException('Production requires a dedicated MySQL account with a non-empty password.');
    }

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );

    return $connection;
}

function privateStoragePath(): string
{
    $configured = appConfig('C2C_PRIVATE_STORAGE_PATH');
    if ($configured !== null && trim($configured) !== '') {
        return rtrim($configured, DIRECTORY_SEPARATOR);
    }

    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'BIS-private-uploads';
}
