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
    $st = db()->prepare('SELECT * FROM profiles WHERE role_key = ?');
    $st->execute([$roleKey]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return repo_attach_images([$row])[0];
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
    $st = db()->prepare('SELECT * FROM mark_entries WHERE year_id = ? ORDER BY FIELD(standard, "12","11","10"), position, id');
    foreach ($years as &$y) {
        $st->execute([$y['id']]);
        $entries = $st->fetchAll();
        $grouped = [];
        foreach ($entries as $en) {
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
