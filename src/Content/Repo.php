<?php

namespace SJ\Content;

/**
 * Flat read-path query helpers (DYNAMIC_MIGRATION_PLAN.md §4.2). Every method
 * returns plain arrays; rows with an image FK get an 'image' sub-array. Query
 * budget: image joins are single-query (IMG_SELECT + foldImage) and list
 * children are batched IN() lookups — no N+1 loops. Ported verbatim from
 * _libs/repo.php in R2; the repo_* global names (src/helpers.php) delegate here.
 */
final class Repo
{
    /** SELECT fragment aliasing every images column as img_* (for one-query joins). */
    public const IMG_SELECT = 'i.id AS img_id, i.legacy_path AS img_legacy_path,
    i.original_name AS img_original_name, i.alt_text AS img_alt_text,
    i.mime AS img_mime, i.width AS img_width, i.height AS img_height,
    i.preset_key AS img_preset_key, i.crop_rect AS img_crop_rect,
    i.version AS img_version, i.created_at AS img_created_at,
    i.updated_at AS img_updated_at';

    public static function setting(string $key, ?string $default = null): ?string
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

    /** SEO row (title/description) for one URL slug — F3. */
    public static function seo(string $slug): ?array
    {
        $st = db()->prepare('SELECT * FROM seo_meta WHERE slug = ?');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function image(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM images WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Attach $row['image'] to each row from its $fk column (single query). */
    public static function attachImages(array $rows, string $fk = 'image_id'): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if (!empty($r[$fk])) {
                $ids[(int)$r[$fk]] = true;
            }
        }
        $map = [];
        if ($ids) {
            $in = \implode(',', \array_fill(0, \count($ids), '?'));
            $st = db()->prepare("SELECT * FROM images WHERE id IN ($in)");
            $st->execute(\array_keys($ids));
            foreach ($st->fetchAll() as $img) {
                $map[(int)$img['id']] = $img;
            }
        }
        foreach ($rows as &$r) {
            $r['image'] = !empty($r[$fk]) ? ($map[(int)$r[$fk]] ?? null) : null;
        }
        return $rows;
    }

    /** Fold the img_* aliases of a joined row back into the 'image' sub-array. */
    public static function foldImage(array $row): array
    {
        $image = null;
        if (($row['img_id'] ?? null) !== null) {
            $image = [];
            foreach ($row as $k => $v) {
                if (\strncmp($k, 'img_', 4) === 0) {
                    $image[\substr($k, 4)] = $v;
                }
            }
        }
        foreach (\array_keys($row) as $k) {
            if (\strncmp($k, 'img_', 4) === 0) {
                unset($row[$k]);
            }
        }
        $row['image'] = $image;
        return $row;
    }

    public static function page(string $slug): ?array
    {
        $st = db()->prepare('SELECT * FROM pages WHERE slug = ?');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function heroSlides(int $pageId, bool $includeInactive = false): array
    {
        // Slides + their images in ONE query (query budget).
        $sql = 'SELECT h.*, ' . self::IMG_SELECT . ' FROM hero_slides h LEFT JOIN images i ON i.id = h.image_id
                WHERE h.page_id = ?' . ($includeInactive ? '' : ' AND h.is_active = 1') . ' ORDER BY h.position, h.id';
        $st = db()->prepare($sql);
        $st->execute([$pageId]);
        return \array_map([self::class, 'foldImage'], $st->fetchAll());
    }

    public static function testimonials(bool $includeInactive = false): array
    {
        // N2: bg image joined in the same single query (budget-neutral).
        $sql = 'SELECT t.*, ' . self::IMG_SELECT . ' FROM testimonials t LEFT JOIN images i ON i.id = t.bg_image_id'
             . ($includeInactive ? '' : ' WHERE t.is_active = 1') . ' ORDER BY t.position, t.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    /** One school section by slug, with its grade-card image (C4). */
    public static function section(string $slug): ?array
    {
        $st = db()->prepare(
            'SELECT s.*, ' . self::IMG_SELECT . ' FROM school_sections s LEFT JOIN images i ON i.id = s.card_image_id WHERE s.slug = ?'
        );
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ? self::foldImage($row) : null;
    }

    /** All school sections in display order (academics grade cards — C8). */
    public static function sections(): array
    {
        return \array_map([self::class, 'foldImage'], db()->query(
            'SELECT s.*, ' . self::IMG_SELECT . ' FROM school_sections s LEFT JOIN images i ON i.id = s.card_image_id ORDER BY s.position, s.id'
        )->fetchAll());
    }

    /** Timeline entries of one section, ordered (C4). */
    public static function timeline(int $sectionId): array
    {
        $st = db()->prepare('SELECT * FROM timeline_entries WHERE section_id = ? ORDER BY position, id');
        $st->execute([$sectionId]);
        return $st->fetchAll();
    }

    /** Staffs-page photo+text blocks, with images, ordered — one query (K7). */
    public static function staffBlocks(bool $includeInactive = false): array
    {
        $sql = 'SELECT b.*, ' . self::IMG_SELECT . ' FROM staff_blocks b LEFT JOIN images i ON i.id = b.image_id'
             . ($includeInactive ? '' : ' WHERE b.is_active = 1') . ' ORDER BY b.position, b.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    /** School-timings rows of the About rules block, ordered (K4). */
    public static function rulesTimings(): array
    {
        return db()->query('SELECT * FROM rules_timings ORDER BY position, id')->fetchAll();
    }

    /** Event blocks of one section, with images, ordered — one query (C4). */
    public static function sectionEvents(int $sectionId): array
    {
        $st = db()->prepare(
            'SELECT ev.*, ' . self::IMG_SELECT . ' FROM section_events ev LEFT JOIN images i ON i.id = ev.image_id
              WHERE ev.section_id = ? ORDER BY ev.position, ev.id'
        );
        $st->execute([$sectionId]);
        return \array_map([self::class, 'foldImage'], $st->fetchAll());
    }

    /** One academy by slug, with its bg image (C5). */
    public static function academy(string $slug): ?array
    {
        $st = db()->prepare(
            'SELECT a.*, ' . self::IMG_SELECT . ' FROM academies a LEFT JOIN images i ON i.id = a.bg_image_id WHERE a.slug = ?'
        );
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ? self::foldImage($row) : null;
    }

    /** All academies in grid order, with their card images (co-curriculum — C5). */
    public static function academies(bool $includeInactive = false): array
    {
        $sql = 'SELECT a.*, ' . self::IMG_SELECT . ' FROM academies a LEFT JOIN images i ON i.id = a.card_image_id'
             . ($includeInactive ? '' : ' WHERE a.is_active = 1') . ' ORDER BY a.position, a.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    /** All sports with images, ordered — one query (C6). */
    public static function sports(bool $includeInactive = false): array
    {
        $sql = 'SELECT sp.*, ' . self::IMG_SELECT . ' FROM sports sp LEFT JOIN images i ON i.id = sp.image_id'
             . ($includeInactive ? '' : ' WHERE sp.is_active = 1') . ' ORDER BY sp.position, sp.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    /** All facilities with bg images + their carousels batched — two queries (C7). */
    public static function facilities(bool $includeInactive = false): array
    {
        $sql = 'SELECT f.*, ' . self::IMG_SELECT . ' FROM facilities f LEFT JOIN images i ON i.id = f.bg_image_id'
             . ($includeInactive ? '' : ' WHERE f.is_active = 1') . ' ORDER BY f.position, f.id';
        $rows = \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
        if (!$rows) {
            return [];
        }
        // one batched query for every facility carousel (no N+1)
        $ids = \array_column($rows, 'id');
        $in  = \implode(',', \array_fill(0, \count($ids), '?'));
        $st  = db()->prepare(
            'SELECT l.owner_id, l.id AS link_id, ' . self::IMG_SELECT . '
               FROM image_links l JOIN images i ON i.id = l.image_id
              WHERE l.owner_type = \'facility\' AND l.role = \'carousel\' AND l.owner_id IN (' . $in . ')
              ORDER BY l.position, l.id'
        );
        $st->execute($ids);
        $byOwner = [];
        foreach ($st->fetchAll() as $r) {
            $oid = (int)$r['owner_id'];
            $byOwner[$oid][] = self::foldImage($r)['image'];
        }
        foreach ($rows as &$f) {
            $f['carousel'] = $byOwner[(int)$f['id']] ?? [];
        }
        return $rows;
    }

    /** Achievements of one type with images, ordered — one query (C8). */
    public static function achievements(string $type, bool $includeInactive = false): array
    {
        $sql = 'SELECT a.*, ' . self::IMG_SELECT . ' FROM achievements a LEFT JOIN images i ON i.id = a.image_id
                WHERE a.type = ?' . ($includeInactive ? '' : ' AND a.is_active = 1') . ' ORDER BY a.position, a.id';
        $st = db()->prepare($sql);
        $st->execute([$type]);
        return \array_map([self::class, 'foldImage'], $st->fetchAll());
    }

    /** All gallery albums for the hub, with card images (C9). */
    public static function albums(bool $includeInactive = false): array
    {
        $sql = 'SELECT g.*, ' . self::IMG_SELECT . ' FROM gallery_albums g LEFT JOIN images i ON i.id = g.card_image_id'
             . ($includeInactive ? '' : ' WHERE g.is_active = 1') . ' ORDER BY g.position, g.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    /** One album by slug with its years and every year's photos — three queries (C9). */
    public static function album(string $slug, bool $includeInactive = false): ?array
    {
        $st = db()->prepare('SELECT * FROM gallery_albums WHERE slug = ?');
        $st->execute([$slug]);
        $album = $st->fetch();
        if (!$album) {
            return null;
        }
        $sql = 'SELECT * FROM album_years WHERE album_id = ?' . ($includeInactive ? '' : ' AND is_active = 1') . ' ORDER BY position, id';
        $st = db()->prepare($sql);
        $st->execute([$album['id']]);
        $years = $st->fetchAll();
        if ($years) {
            $ids = \array_column($years, 'id');
            $in  = \implode(',', \array_fill(0, \count($ids), '?'));
            $st = db()->prepare(
                'SELECT l.owner_id, ' . self::IMG_SELECT . ' FROM image_links l JOIN images i ON i.id = l.image_id
                  WHERE l.owner_type = \'album_year\' AND l.role = \'photos\' AND l.owner_id IN (' . $in . ')
                  ORDER BY l.position, l.id'
            );
            $st->execute($ids);
            $byYear = [];
            foreach ($st->fetchAll() as $r) {
                $byYear[(int)$r['owner_id']][] = self::foldImage($r)['image'];
            }
            foreach ($years as &$y) {
                $y['photos'] = $byYear[(int)$y['id']] ?? [];
            }
        }
        $album['years'] = $years;
        return $album;
    }

    /** Images linked to one owner collection (image_links), ordered — one query (M1). */
    public static function linkedImages(string $ownerType, int $ownerId, string $role = 'carousel'): array
    {
        $st = db()->prepare(
            'SELECT l.id AS link_id, l.position, ' . self::IMG_SELECT . '
               FROM image_links l JOIN images i ON i.id = l.image_id
              WHERE l.owner_type = ? AND l.owner_id = ? AND l.role = ?
              ORDER BY l.position, l.id'
        );
        $st->execute([$ownerType, $ownerId, $role]);
        return \array_map(static fn (array $r) => self::foldImage($r)['image'] + ['link_id' => (int)$r['link_id']], $st->fetchAll());
    }

    public static function profile(string $roleKey): ?array
    {
        // Profile + its image in ONE query (query budget).
        $st = db()->prepare(
            'SELECT p.*, ' . self::IMG_SELECT . ' FROM profiles p LEFT JOIN images i ON i.id = p.image_id WHERE p.role_key = ?'
        );
        $st->execute([$roleKey]);
        $row = $st->fetch();
        return $row ? self::foldImage($row) : null;
    }

    public static function uniqueFeatures(bool $includeInactive = false): array
    {
        // Image via JOIN (one query) — keeps Home inside the ≤12-query budget
        // now that F2 renditions + F3 seo each cost a query per request.
        $sql = 'SELECT u.*, ' . self::IMG_SELECT . ' FROM unique_features u LEFT JOIN images i ON i.id = u.image_id'
             . ($includeInactive ? '' : ' WHERE u.is_active = 1') . ' ORDER BY u.position, u.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    public static function ticker(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM ticker_items' . ($includeInactive ? '' : ' WHERE is_active = 1') . ' ORDER BY position, id';
        return db()->query($sql)->fetchAll();
    }

    public static function updateSlides(bool $includeInactive = false): array
    {
        // Image via JOIN (one query) — see uniqueFeatures().
        $sql = 'SELECT u.*, ' . self::IMG_SELECT . ' FROM update_slides u LEFT JOIN images i ON i.id = u.image_id'
             . ($includeInactive ? '' : ' WHERE u.is_active = 1') . ' ORDER BY u.position, u.id';
        return \array_map([self::class, 'foldImage'], db()->query($sql)->fetchAll());
    }

    /** Latest N active years (oldest→newest for display), each with 'entries' grouped ready. */
    public static function marksBoard(?int $limit = null, bool $includeInactive = false): array
    {
        $limit = $limit ?? (int)self::setting('marks_years_shown', '3');
        $sql = 'SELECT * FROM mark_years' . ($includeInactive ? '' : ' WHERE is_active = 1')
             . ' ORDER BY year DESC LIMIT ' . \max(1, $limit);
        $years = db()->query($sql)->fetchAll();
        $years = \array_reverse($years); // display oldest → newest, as today

        // One batched query for every year's entries (query budget: no N+1 loops).
        $byYear = [];
        if ($years) {
            $in = \implode(',', \array_fill(0, \count($years), '?'));
            $st = db()->prepare("SELECT * FROM mark_entries WHERE year_id IN ($in) ORDER BY FIELD(standard, \"12\",\"11\",\"10\"), position, id");
            $st->execute(\array_map(static fn ($y) => $y['id'], $years));
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
}
