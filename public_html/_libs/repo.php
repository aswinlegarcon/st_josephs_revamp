<?php
// Flat read-path query helpers (DYNAMIC_MIGRATION_PLAN.md §4.2). Home-page scope.
// Every function returns plain arrays; rows with an image FK get an 'image' sub-array.

function repo_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function repo_image(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM images WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Attach $row['image'] to each row from its $fk column (single query). */
function repo_attach_images(array $rows, string $fk = 'image_id'): array
{
    $ids = [];
    foreach ($rows as $r) {
        if (!empty($r[$fk])) {
            $ids[(int)$r[$fk]] = true;
        }
    }
    $map = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT * FROM images WHERE id IN ($in)");
        $st->execute(array_keys($ids));
        foreach ($st->fetchAll() as $img) {
            $map[(int)$img['id']] = $img;
        }
    }
    foreach ($rows as &$r) {
        $r['image'] = !empty($r[$fk]) ? ($map[(int)$r[$fk]] ?? null) : null;
    }
    return $rows;
}

function repo_page(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM pages WHERE slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function repo_hero_slides(int $pageId, bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM hero_slides WHERE page_id = ?' . ($includeInactive ? '' : ' AND is_active = 1') . ' ORDER BY position, id';
    $st = db()->prepare($sql);
    $st->execute([$pageId]);
    return repo_attach_images($st->fetchAll());
}

function repo_profile(string $roleKey): ?array
{
    // Profile + its image in ONE query (query budget). The img_* aliases are
    // folded back into the same 'image' sub-array repo_attach_images builds.
    $st = db()->prepare(
        'SELECT p.*,
                i.id AS img_id, i.legacy_path AS img_legacy_path,
                i.original_name AS img_original_name, i.alt_text AS img_alt_text,
                i.mime AS img_mime, i.width AS img_width, i.height AS img_height,
                i.preset_key AS img_preset_key, i.crop_rect AS img_crop_rect,
                i.version AS img_version, i.created_at AS img_created_at,
                i.updated_at AS img_updated_at
           FROM profiles p LEFT JOIN images i ON i.id = p.image_id
          WHERE p.role_key = ?'
    );
    $st->execute([$roleKey]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $image = null;
    if ($row['img_id'] !== null) {
        $image = [];
        foreach ($row as $k => $v) {
            if (strncmp($k, 'img_', 4) === 0) {
                $image[substr($k, 4)] = $v;
            }
        }
    }
    foreach (array_keys($row) as $k) {
        if (strncmp($k, 'img_', 4) === 0) {
            unset($row[$k]);
        }
    }
    $row['image'] = $image;
    return $row;
}

function repo_unique_features(bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM unique_features' . ($includeInactive ? '' : ' WHERE is_active = 1') . ' ORDER BY position, id';
    return repo_attach_images(db()->query($sql)->fetchAll());
}

function repo_ticker(bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM ticker_items' . ($includeInactive ? '' : ' WHERE is_active = 1') . ' ORDER BY position, id';
    return db()->query($sql)->fetchAll();
}

function repo_update_slides(bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM update_slides' . ($includeInactive ? '' : ' WHERE is_active = 1') . ' ORDER BY position, id';
    return repo_attach_images(db()->query($sql)->fetchAll());
}

/** Latest N active years (oldest→newest for display), each with 'entries' grouped ready. */
function repo_marks_board(?int $limit = null, bool $includeInactive = false): array
{
    $limit = $limit ?? (int)repo_setting('marks_years_shown', '3');
    $sql = 'SELECT * FROM mark_years' . ($includeInactive ? '' : ' WHERE is_active = 1')
         . ' ORDER BY year DESC LIMIT ' . max(1, $limit);
    $years = db()->query($sql)->fetchAll();
    $years = array_reverse($years); // display oldest → newest, as today

    // One batched query for every year's entries (query budget: no N+1 loops).
    $byYear = [];
    if ($years) {
        $in = implode(',', array_fill(0, count($years), '?'));
        $st = db()->prepare("SELECT * FROM mark_entries WHERE year_id IN ($in) ORDER BY FIELD(standard, \"12\",\"11\",\"10\"), position, id");
        $st->execute(array_map(static fn ($y) => $y['id'], $years));
        foreach ($st->fetchAll() as $en) {
            $byYear[(int)$en['year_id']][] = $en;
        }
    }
    foreach ($years as &$y) {
        $grouped = [];
        foreach ($byYear[(int)$y['id']] ?? [] as $en) {
            $grouped[$en['standard']][] = $en;
        }
        // fixed display order 12 → 11 → 10, skipping empty standards
        $y['standards'] = [];
        foreach (['12', '11', '10'] as $std) {
            if (!empty($grouped[$std])) {
                $y['standards'][$std] = $grouped[$std];
            }
        }
    }
    return $years;
}
