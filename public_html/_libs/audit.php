<?php
// Admin audit trail (SECURITY.md SEC-19). Procedural for now; ported to
// SJ\Admin\Audit::log() in P2. Best-effort: never let logging break a request,
// and never record secrets (no passwords, tokens, or hashes).

function sj_audit(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO audit_log (admin_id, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            $action,
            $entity,
            $entityId,
            mb_substr($detail, 0, 500),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (\Throwable $e) {
        // Swallow — auditing must never surface an error to the user or abort the action.
    }
}
