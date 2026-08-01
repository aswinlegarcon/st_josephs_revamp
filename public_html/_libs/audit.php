<?php
// Shim → SJ\Admin\Audit (src/Admin/Audit.php). SECURITY.md SEC-19.
function sj_audit(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void
{
    \SJ\Admin\Audit::log($action, $entity, $entityId, $detail);
}
