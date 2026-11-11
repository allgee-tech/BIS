<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config.php';

try {
    $pdo = database();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS c2c_schema_migrations (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $version = basename($file);
        $check = $pdo->prepare('SELECT 1 FROM c2c_schema_migrations WHERE version = :version');
        $check->execute(['version' => $version]);
        if ($check->fetchColumn() !== false) {
            fwrite(STDOUT, "Already applied: {$version}\n");
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Cannot read migration: {$version}");
        }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
        $record = $pdo->prepare('INSERT INTO c2c_schema_migrations (version) VALUES (:version)');
        $record->execute(['version' => $version]);
        fwrite(STDOUT, "Applied: {$version}\n");
    }

    fwrite(STDOUT, "Campus2Career schema is up to date. No existing BIS tables were altered or removed.\n");
} catch (Throwable $exception) {
    error_log('Campus2Career migration failed: ' . $exception->getMessage());
    fwrite(STDERR, "Migration failed. Check the PHP error log; existing BIS tables were not intentionally changed.\n");
    exit(1);
}
