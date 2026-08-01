<?php

namespace SJ\Admin;

/**
 * Admin audit trail (SECURITY.md SEC-19). Best-effort: logging never breaks a
 * request, and secrets (passwords, tokens, hashes) are never recorded.
 */
final class Audit
{
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void
    {
        try {
            $st = \db()->prepare(
                'INSERT INTO audit_log (admin_id, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?)'
            );
            $st->execute([
                !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                $action,
                $entity,
                $entityId,
                \mb_substr($detail, 0, 500),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Swallow — auditing must never surface an error or abort the action.
        }
    }
}
