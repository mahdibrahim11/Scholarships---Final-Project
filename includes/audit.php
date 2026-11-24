<?php
/**
 * FR-SA-05: record significant actions. The acting user is the signed-in user (or the first Super-admin for command-line tools).
 * Pass $userId explicitly for events with no signed-in user (failed logins), or null for "nobody".
 */
function audit_log(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, array $details = [], int|false|null $userId = false): void
{
    if ($userId === false) {
        $userId = auth_user() ? (int)auth_user()['id'] : (PHP_SAPI === 'cli' ? current_user_id() : null);
    }
    $st = $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$userId, $action, $entityType, $entityId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
}
