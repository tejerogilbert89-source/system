<?php
/**
 * Writes one row per important action to the audit_log table:
 * who did what, to which record, and when.
 *
 * Every feature that changes data should call Audit::log(...) right after
 * the change. See features/auth/pages/login.php for an example.
 */
class Audit
{
    public static function log(
        ?int $userId,
        string $action,          // e.g. 'login', 'checkout', 'fine_waived'
        string $entityType,      // e.g. 'users', 'loans', 'items'
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValues !== null ? json_encode($oldValues) : null,
            $newValues !== null ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
