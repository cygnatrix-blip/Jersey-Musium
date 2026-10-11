<?php

declare(strict_types=1);

namespace App\Services;

/** Append-only log of admin actions (who changed what, when, from where). */
final class AuditService
{
    public static function log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $meta = []): void
    {
        db()->insert('audit_logs', [
            'admin_id'    => admin_auth()->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId !== null ? (int) $entityId : null,
            'meta'        => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'  => request()->ip(),
        ]);
    }
}
