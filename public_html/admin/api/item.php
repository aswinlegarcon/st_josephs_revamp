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
            if (!empty($def['create_only'])) {
                continue; // N3: never offered in edit modals (immutable after create)
            }
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
                'options'   => $def['type'] === 'pagelink' ? sj_page_link_options() : ($def['values'] ?? null),
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

        // K2: registry max_count is authoritative here — the Add buttons hide
        // client-side, but only this check makes the cap real. Table/parent
        // identifiers come from the registry, never from the request.
        if (!empty($reg['max_count'])) {
            $max = (int)$reg['max_count'];
            if (!empty($reg['parent']) && isset($preset[$reg['parent']])) {
                $st = db()->prepare("SELECT COUNT(*) FROM {$reg['table']} WHERE {$reg['parent']} = ?");
                $st->execute([(int)$preset[$reg['parent']]]);
                $cnt = (int)$st->fetchColumn();
            } else {
                $cnt = (int)db()->query("SELECT COUNT(*) FROM {$reg['table']}")->fetchColumn();
            }
            if ($cnt >= $max) {
                api_fail("This section is full — it holds a maximum of {$max} entries. Delete one first.");
            }
        }

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
        try {
            $pdo->prepare($sql)->execute(array_values($cols));
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) { // duplicate UNIQUE (slug)
                api_fail('That URL key is already in use — pick another');
            }
            throw $e;
        }
        $newId = (int)$pdo->lastInsertId();
        sj_audit('item.create', $entity, $newId);
        // N3/N4: a new academy/album is a new public URL — give it a search
        // snippet row (admin-editable afterwards; INSERT IGNORE = never
        // overwrites) and refresh the sitemap.
        if ($entity === 'academy' && isset($cols['slug'])) {
            $pdo->prepare('INSERT IGNORE INTO seo_meta (slug, title, description) VALUES (?,?,?)')->execute([
                $cols['slug'],
                mb_substr(($cols['banner_title'] ?: $cols['card_title']) . " | St.Joseph's MHSS, Ondipudur", 0, 160),
                mb_substr((string)($cols['card_subtitle'] ?? ''), 0, 300),
            ]);
            \SJ\Content\Sitemap::regenerate();
        } elseif ($entity === 'gallery_album' && isset($cols['slug'])) {
            $pdo->prepare('INSERT IGNORE INTO seo_meta (slug, title, description) VALUES (?,?,?)')->execute([
                $cols['slug'],
                mb_substr(($cols['heading'] ?: $cols['title']) . " — Photo Gallery | St.Joseph's MHSS", 0, 160),
                mb_substr('Photos from ' . $cols['title'] . " at St.Joseph's MHSS, Ondipudur.", 0, 300),
            ]);
            \SJ\Content\Sitemap::regenerate();
        }
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
            if (!empty($def['create_only'])) {
                api_fail("Field '$name' is set at creation and cannot be changed"); // N3
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

        // N3/N4: the shipped academies/albums have their own .php files — the
        // row deleting would leave a live URL answering "content not seeded".
        // Their slugs are a code literal (Registry::legacySlugs) and protected.
        $legacy = \SJ\Content\Registry::legacySlugs()[$entity] ?? null;
        $slug   = null;
        if ($legacy !== null) {
            $st = $pdo->prepare("SELECT slug FROM `$table` WHERE id = ?");
            $st->execute([$id]);
            $slug = $st->fetchColumn() ?: null;
            if ($slug !== null && in_array($slug, $legacy, true)) {
                api_fail('This one has a fixed page on the site and cannot be deleted — hide it instead');
            }
        }

        // N4: polymorphic children first (no FK covers image_links / album_years).
        if ($entity === 'gallery_album') {
            $yr = $pdo->prepare("SELECT id FROM album_years WHERE album_id = ?");
            $yr->execute([$id]);
            foreach ($yr->fetchAll(PDO::FETCH_COLUMN) as $yid) {
                $pdo->prepare("DELETE FROM image_links WHERE owner_type = 'album_year' AND owner_id = ?")->execute([(int)$yid]);
            }
            $pdo->prepare("DELETE FROM album_years WHERE album_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM image_links WHERE owner_type = 'album' AND owner_id = ?")->execute([$id]);
        } elseif ($entity === 'academy') {
            $pdo->prepare("DELETE FROM image_links WHERE owner_type = 'academy' AND owner_id = ?")->execute([$id]);
        } elseif ($entity === 'album_year') {
            $pdo->prepare("DELETE FROM image_links WHERE owner_type = 'album_year' AND owner_id = ?")->execute([$id]);
        }

        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        sj_audit('item.delete', $entity, $id, (string)($slug ?? ''));

        // The URL set changed: drop the snippet row, refresh the sitemap.
        if ($slug !== null && in_array($entity, ['academy', 'gallery_album'], true)) {
            $pdo->prepare('DELETE FROM seo_meta WHERE slug = ?')->execute([$slug]);
            \SJ\Content\Sitemap::regenerate();
        }
        api_out();
    }
}
api_fail('Unknown action');
