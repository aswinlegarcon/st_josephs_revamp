<?php
// POST {entity, id, field, value} → update one field (registry-validated).
require __DIR__ . '/_bootstrap.php';

$in = api_input();
$entity = (string)($in['entity'] ?? '');
$id     = (int)($in['id'] ?? 0);
$field  = (string)($in['field'] ?? '');

$reg = api_entity($entity);
$def = $reg['fields'][$field] ?? null;
if ($def === null || $id <= 0) {
    api_fail('Unknown field');
}
// N3: create-only fields (slugs = URLs) can never be edited afterwards —
// renaming would break the page's address, links and SEO row.
if (!empty($def['create_only'])) {
    api_fail("Field '$field' is set at creation and cannot be changed");
}
$value = api_validate_field($entity, $field, $def, $in['value'] ?? null);

$st = db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?");
$st->execute([$value, $id]);
if (!$st->rowCount()) {
    // Row may exist with an identical value; confirm it exists at all.
    $chk = db()->prepare("SELECT COUNT(*) FROM {$reg['table']} WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        api_fail('Row not found', 404);
    }
}
sj_audit('field.save', $entity, $id, $field);
// N3/N4: hiding/showing a URL-bearing row changes the public URL set.
if ($field === 'is_active' && in_array($entity, ['academy', 'gallery_album'], true)) {
    \SJ\Content\Sitemap::regenerate();
}
api_out(['value' => $value]);
