<?php
declare(strict_types=1);

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrfToken(), $submitted)) {
        http_response_code(400);
        throw new InvalidArgumentException('This form expired. Reload the page and try again.');
    }
}

function requiredText(string $key, string $label, int $maxLength = 180): string
{
    $submitted = $_POST[$key] ?? '';
    if (!is_string($submitted)) {
        throw new InvalidArgumentException($label . ' must be plain text.');
    }

    $value = trim($submitted);
    $length = preg_match_all('/./us', $value);
    if ($value === '' || $length === false || $length > $maxLength) {
        throw new InvalidArgumentException($label . ' is required and must be no more than ' . $maxLength . ' characters.');
    }

    return $value;
}

function optionalText(string $key, string $label, int $maxLength = 180): ?string
{
    $submitted = $_POST[$key] ?? '';
    if (!is_string($submitted)) {
        throw new InvalidArgumentException($label . ' must be plain text.');
    }

    $value = trim($submitted);
    $length = preg_match_all('/./us', $value);
    if ($length === false || $length > $maxLength) {
        throw new InvalidArgumentException($label . ' must be no more than ' . $maxLength . ' characters.');
    }

    return $value === '' ? null : $value;
}

function validEmail(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validDateInput(string $key, string $label, bool $required = false): ?string
{
    $value = optionalText($key, $label, 10);
    if ($value === null && !$required) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Enter a valid ' . strtolower($label) . '.');
    }

    return $value;
}

function formToken(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function auditEvent(PDO $pdo, ?int $actorId, string $action, string $entity, ?int $entityId, array $after = []): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO c2c_audit_logs (actor_user_id, action_key, entity_type, entity_id, after_data)
         VALUES (:actor, :action, :entity, :entity_id, :after_data)'
    );
    $stmt->execute([
        'actor' => $actorId,
        'action' => $action,
        'entity' => $entity,
        'entity_id' => $entityId,
        'after_data' => $after === [] ? null : json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
}

function recordAuthAttempt(PDO $pdo, string $ipHash, bool $success): void
{
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO c2c_auth_attempts (ip_hash, attempts, window_started)
             VALUES (:ip_hash, 0, CURRENT_TIMESTAMP)'
        );
        $insert->execute(['ip_hash' => $ipHash]);
        $lookup = $pdo->prepare(
            'SELECT attempts,
                    window_started < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE) AS expired
             FROM c2c_auth_attempts WHERE ip_hash = :ip_hash FOR UPDATE'
        );
        $lookup->execute(['ip_hash' => $ipHash]);
        $state = $lookup->fetch();
        if ($state === false) {
            throw new RuntimeException('Authentication attempt state could not be loaded.');
        }

        $expired = (bool) $state['expired'];
        $attempts = $success ? 0 : ($expired ? 1 : (int) $state['attempts'] + 1);
        $update = $pdo->prepare(
            'UPDATE c2c_auth_attempts
             SET attempts = :attempts,
                 window_started = IF(:reset_window = 1, CURRENT_TIMESTAMP, window_started),
                 blocked_until = IF(:should_block = 1, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), NULL)
             WHERE ip_hash = :ip_hash'
        );
        $update->execute([
            'attempts' => $attempts,
            'reset_window' => $expired || $success ? 1 : 0,
            'should_block' => !$success && $attempts >= 10 ? 1 : 0,
            'ip_hash' => $ipHash,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function isAuthRateLimited(PDO $pdo, string $ipHash): bool
{
    $stmt = $pdo->prepare(
        'SELECT blocked_until IS NOT NULL AND blocked_until > CURRENT_TIMESTAMP
         FROM c2c_auth_attempts WHERE ip_hash = :ip_hash'
    );
    $stmt->execute(['ip_hash' => $ipHash]);
    return (bool) $stmt->fetchColumn();
}

function storeUploadedDocument(array $file, string $kind, int $ownerId): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new InvalidArgumentException('The uploaded document is invalid.');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $message = match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The document exceeds the upload size limit.',
            UPLOAD_ERR_NO_FILE => 'Choose a document to upload.',
            default => 'The document could not be uploaded.',
        };
        throw new InvalidArgumentException($message);
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Documents must be 5 MB or smaller.');
    }

    $originalName = basename((string) $file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (!isset($allowed[$extension]) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new InvalidArgumentException('Upload a PDF, DOC or DOCX document.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    if ($mime !== $allowed[$extension]) {
        throw new InvalidArgumentException('The document content does not match its file type.');
    }

    $directory = privateStoragePath();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private document storage is unavailable.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Private document storage is not writable.');
    }

    $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
    $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        throw new RuntimeException('The document could not be saved.');
    }

    return [
        'owner_id' => $ownerId,
        'kind' => $kind,
        'original_name' => mb_substr($originalName, 0, 255),
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'file_size' => (int) $file['size'],
        'path' => $destination,
    ];
}
