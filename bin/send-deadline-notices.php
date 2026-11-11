<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/app/notifications.php';

try {
    $pdo = database();
    $pdo->beginTransaction();
    $expired = $pdo->exec(
        "UPDATE c2c_opportunities
         SET status = 'expired'
         WHERE status = 'published' AND application_deadline < CURRENT_DATE"
    );

    $due = $pdo->query(
        "SELECT s.user_id, p.id AS opportunity_id, p.title, p.application_deadline
         FROM c2c_saved_opportunities s
         INNER JOIN c2c_opportunities p ON p.id = s.opportunity_id
         WHERE p.status = 'published'
           AND p.application_deadline = DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)"
    )->fetchAll();
    $notice = $pdo->prepare(
        'INSERT IGNORE INTO c2c_deadline_notices (user_id, opportunity_id, deadline)
         VALUES (:user, :opportunity, :deadline)'
    );
    $sent = 0;
    foreach ($due as $item) {
        $notice->execute([
            'user' => (int) $item['user_id'],
            'opportunity' => (int) $item['opportunity_id'],
            'deadline' => $item['application_deadline'],
        ]);
        if ($notice->rowCount() === 1) {
            notifyUser(
                $pdo,
                (int) $item['user_id'],
                'Saved opportunity deadline in 3 days',
                'The application deadline for ' . $item['title'] . ' is ' . $item['application_deadline'] . '.',
                'opportunity:' . (int) $item['opportunity_id']
            );
            $sent++;
        }
    }

    $pdo->commit();
    fwrite(STDOUT, "Expired {$expired} listing(s); sent {$sent} saved-opportunity deadline notification(s).\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Campus2Career deadline notices failed: ' . $exception->getMessage());
    fwrite(STDERR, "Deadline processing failed. Check the PHP error log.\n");
    exit(1);
}
