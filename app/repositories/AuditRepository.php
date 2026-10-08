<?php
declare(strict_types=1);

final class AuditRepository
{
    public static function log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?array $old = null, ?array $new = null): void
    {
        Database::query(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)',
            [
                $userId, $action, $entityType, $entityId,
                $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
                $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
                Request::ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );
    }
}
