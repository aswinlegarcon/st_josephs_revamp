<?php
// POST {action:'get'|'create'|'update'|'delete', entity, id?, data?, preset?}
require __DIR__ . '/_bootstrap.php';

$in     = api_input();
$action = (string)($in['action'] ?? '');
$entity = (string)($in['entity'] ?? '');
$reg    = api_entity($entity);
$table  = $reg['table'];
$pdo    = db();

switch ($action) {
    case 'get': {
        $id = (int)($in['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            api_fail('Row not found', 404);
        }
        $values = [];
        $fields = [];
        $thumbs = [];
        foreach ($reg['fields'] as $name => $def) {
            $values[$name] = $row[$name] ?? null;
            if ($def['type'] === 'image' && !empty($row[$name])) {
                $imgRow = repo_image((int)$row[$name]);
                if ($imgRow) {
                    $thumbs[$name] = img_url($imgRow, $def['preset'] ?? 'card_4x3');
                }
            }
            $fields[] = [
                'name'      => $name,
                'label'     => $def['label'] ?? ucfirst(str_replace('_', ' ', $name)),
                'type'      => $def['type'],
                'options'   => $def['values'] ?? null,
                'preset'    => $def['preset'] ?? null,
                'required'  => !empty($def['required']),
                'multiline' => !empty($def['multiline']),
            ];
        }
        api_out(['id' => (int)$row['id'], 'values' => $values, 'fields' => $fields, 'thumbs' => $thumbs]);
    }

    case 'create': {
        if (empty($reg['creatable'])) {
            api_fail('Entity cannot be created');
        }
        $data   = is_array($in['data'] ?? null) ? $in['data'] : [];
        $preset = is_array($in['preset'] ?? null) ? $in['preset'] : [];

        $cols = [];
        $vals = [];
        foreach ($reg['fields'] as $name => $def) {
            $provided = array_key_exists($name, $data) ? $data[$name] : null;
            if ($provided === null && $def['type'] === 'bool') {
                $provided = 1; // new rows visible by default
            }
            $cols[$name] = api_validate_field($entity, $name, $def, $provided);
        }
        // Parent FK from the creation preset (e.g. page_id / year_id).
        if (!empty($reg['parent'])) {
            $p = (int)($preset[$reg['parent']] ?? 0);
            if ($p <= 0) {
                api_fail('Missing parent reference');
            }
            $cols[$reg['parent']] = $p;
        }
        // Position: append at the end (scoped to parent when present).
        if (!empty($reg['orderable'])) {
            if (!empty($reg['parent'])) {
                $st = $pdo->prepare("SELECT COALESCE(MAX(position)+1,0) FROM `$table` WHERE `{$reg['parent']}` = ?");
                $st->execute([$cols[$reg['parent']]]);
            } else {
                $st = $pdo->query("SELECT COALESCE(MAX(position)+1,0) FROM `$table`");
            }
            $cols['position'] = (int)$st->fetchColumn();
        }

        $names = array_keys($cols);
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $names) . "`) VALUES (" . implode(',', array_fill(0, count($names), '?')) . ")";
        $pdo->prepare($sql)->execute(array_values($cols));
        $newId = (int)$pdo->lastInsertId();
        sj_audit('item.create', $entity, $newId);
        api_out(['id' => $newId]);
    }

    case 'update': {
        $id   = (int)($in['id'] ?? 0);
        $data = is_array($in['data'] ?? null) ? $in['data'] : [];
        if ($id <= 0 || !$data) {
            api_fail('Nothing to update');
        }
        $sets = [];
        $vals = [];
        foreach ($data as $name => $value) {
            $def = $reg['fields'][$name] ?? null;
            if ($def === null) {
                api_fail("Unknown field '$name'");
            }
            $sets[] = "`$name` = ?";
            $vals[] = api_validate_field($entity, $name, $def, $value);
        }
        $vals[] = $id;
        $pdo->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
        sj_audit('item.update', $entity, $id);
        api_out();
    }

    case 'delete': {
        if (empty($reg['deletable'])) {
            api_fail('Entity cannot be deleted');
        }
        $id = (int)($in['id'] ?? 0);
        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        sj_audit('item.delete', $entity, $id);
        api_out();
    }
}
api_fail('Unknown action');
