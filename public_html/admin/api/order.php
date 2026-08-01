<?php
// POST {entity, ids:[…]} → rewrite position 0..n-1 in one transaction.
require __DIR__ . '/_bootstrap.php';

$in     = api_input();
$entity = (string)($in['entity'] ?? '');
$ids    = $in['ids'] ?? null;
$reg    = api_entity($entity);

if (empty($reg['orderable']) || !is_array($ids) || !$ids) {
    api_fail('Entity is not orderable');
}
$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare("UPDATE `{$reg['table']}` SET position = ? WHERE id = ?");
    foreach (array_values($ids) as $pos => $id) {
        $st->execute([$pos, (int)$id]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    api_fail('Reorder failed', 500);
}
api_out();
