<?php
// Global helper functions — the historic names every view, controller and admin
// endpoint calls. Loaded via Composer's "files" autoload (composer.json), so
// they exist before any page code runs. Each delegates to its SJ\ class; the
// behaviour is identical to the pre-R2 _libs implementations.

use SJ\Admin\Audit;
use SJ\Admin\Auth;
use SJ\Admin\Csrf;
use SJ\Content\Registry;
use SJ\Content\Repo;
use SJ\Content\Sanitizer;
use SJ\Core\Config;
use SJ\Core\Db;
use SJ\Media\Html as MediaHtml;
use SJ\Media\Pipeline;
use SJ\View\EditAttrs;

// ---------------------------------------------------------------------------
// core
// ---------------------------------------------------------------------------

function sj_config(): array
{
    return Config::all();
}

function db(): PDO
{
    return Db::pdo();
}

/** Number of SQL statements executed this request (0 unless debug is on). */
function db_query_count(): int
{
    return $GLOBALS['__sj_qcount'] ?? 0;
}

/** htmlspecialchars(ENT_QUOTES, UTF-8) — EVERY echoed dynamic value goes through this. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * N7: professional inline-SVG icons (Feather-style, MIT) for every admin
 * surface — replaces the emoji set. Inline SVG over PNG deliberately: crisp at
 * any DPI, inherits currentColor (hover/danger states for free), zero extra
 * requests, CSP-clean. Names are code literals; unknown names render nothing.
 */
function sj_icon(string $name, int $size = 18, string $cls = ''): string
{
    static $icons = [
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'images'   => '<rect x="7" y="7" width="14" height="14" rx="2"/><path d="M3 15V5a2 2 0 0 1 2-2h10"/><circle cx="12" cy="12" r="1.5"/><path d="M21 18l-4-5-6 7"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'quote'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'layers'   => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'award'    => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'flag'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
        'trophy'   => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M17 5h3a1 1 0 0 1 1 1c0 2-1.5 4-4 4"/><path d="M7 5H4a1 1 0 0 0-1 1c0 2 1.5 4 4 4"/>',
        'star'     => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'monitor'  => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'folder'   => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'sliders'  => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'globe'    => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'edit'     => '<path d="M17 3a2.83 2.83 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'up'       => '<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>',
        'down'     => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
        'eye'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'  => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'camera'   => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'x'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'key'      => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
        'unlock'   => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'refresh'  => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'zap'      => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'disk'     => '<line x1="22" y1="12" x2="2" y2="12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" y1="16" x2="6.01" y2="16"/><line x1="10" y1="16" x2="10.01" y2="16"/>',
        'cpu'      => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'crop'     => '<path d="M6.13 1L6 16a2 2 0 0 0 2 2h15"/><path d="M1 6.13L16 6a2 2 0 0 1 2 2v15"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'alert'    => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ];
    $body = $icons[$name] ?? '';
    if ($body === '') {
        return '';
    }
    return '<svg class="sj-svg' . ($cls !== '' ? ' ' . e($cls) : '') . '" width="' . (int)$size . '" height="' . (int)$size
         . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
         . ' stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/** N7: session admin role, re-verified against the DB once per request —
 *  a deleted admin is cut off on their next request; role edits apply live. */
function sj_admin_role(): string
{
    if (!is_admin()) {
        return '';
    }
    static $role = null;
    if ($role === null) {
        $st = db()->prepare('SELECT role FROM admin_users WHERE id = ?');
        $st->execute([(int)$_SESSION['admin_id']]);
        $r = $st->fetchColumn();
        if ($r === false) {
            sj_session_kill(); // account removed while a session survived
            return '';
        }
        $role = (string)$r;
        $_SESSION['admin_role'] = $role;
    }
    return $role;
}

// ---------------------------------------------------------------------------
// admin session / csrf / headers (SJ\Admin\Auth, SJ\Admin\Csrf)
// ---------------------------------------------------------------------------

function sj_session_boot(bool $force = false): void
{
    Auth::boot($force);
}

function sj_session_kill(): void
{
    Auth::kill();
}

function is_admin(): bool
{
    return Auth::isAdmin();
}

function is_edit(): bool
{
    return Auth::isEdit();
}

function csrf_token(): string
{
    return Csrf::token();
}

function sj_admin_headers(): void
{
    Auth::headers();
}

// ---------------------------------------------------------------------------
// content security (SJ\Content\Sanitizer, SJ\Content\Registry, SJ\Admin\Audit)
// ---------------------------------------------------------------------------

function sj_sanitize_html(string $html): string
{
    return Sanitizer::html($html);
}

function sj_registry(): array
{
    return Registry::all();
}

function sj_registry_entity(string $entity): ?array
{
    return Registry::entity($entity);
}

function sj_audit(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void
{
    Audit::log($action, $entity, $entityId, $detail);
}

// ---------------------------------------------------------------------------
// data-edit-* emitters (SJ\View\EditAttrs)
// ---------------------------------------------------------------------------

function ed_field(string $entity, $id, string $field): string
{
    return EditAttrs::field($entity, $id, $field);
}

function ed_item(string $entity, $id, string $label = ''): string
{
    return EditAttrs::item($entity, $id, $label);
}

function ed_add(string $entity, array $preset = [], string $label = 'Add', ?int $currentCount = null): string
{
    return EditAttrs::add($entity, $preset, $label, $currentCount);
}

function ed_rich(string $entity, $id, string $field, ?string $html): void
{
    EditAttrs::rich($entity, $id, $field, $html);
}

function ed_img(string $entity, $id, string $field = 'image_id'): string
{
    return EditAttrs::img($entity, $id, $field);
}

// ---------------------------------------------------------------------------
// media (SJ\Media\Pipeline, SJ\Media\Html)
// ---------------------------------------------------------------------------

function sj_media_dir(): string
{
    return Pipeline::dir();
}

function media_preset(string $key): ?array
{
    return Pipeline::preset($key);
}

function media_renditions(int $imageId, string $presetKey, bool $refresh = false): array
{
    return Pipeline::renditions($imageId, $presetKey, $refresh);
}

function sj_ext_for_mime(string $mime): string
{
    return Pipeline::extForMime($mime);
}

function media_process_upload(array $file, string $presetKey, string $alt = '', ?string $cropRect = null): array
{
    return Pipeline::processUpload($file, $presetKey, $alt, $cropRect);
}

function media_parse_crop(?string $rect, int $srcW, int $srcH): ?array
{
    return Pipeline::parseCrop($rect, $srcW, $srcH);
}

function media_generate(string $src, int $imageId, array $preset, ?array $crop = null): void
{
    Pipeline::generate($src, $imageId, $preset, $crop);
}

function media_ensure_rendition(array $img, string $presetKey): bool
{
    return Pipeline::ensureRendition($img, $presetKey);
}

function sj_gd_fix_orientation($gd, string $path)
{
    return Pipeline::fixOrientation($gd, $path);
}

function img_url(?array $img, string $presetKey): string
{
    return MediaHtml::url($img, $presetKey);
}

function img_tag(?array $img, string $presetKey, array $attrs = []): string
{
    return MediaHtml::tag($img, $presetKey, $attrs);
}

function bg_style(?array $img, string $presetKey): string
{
    return MediaHtml::bgStyle($img, $presetKey);
}

// ---------------------------------------------------------------------------
// repositories (SJ\Content\Repo)
// ---------------------------------------------------------------------------

/** SELECT fragment aliasing every images column as img_* (admin API queries use it too). */
const SJ_IMG_SELECT = Repo::IMG_SELECT;

function repo_setting(string $key, ?string $default = null): ?string
{
    return Repo::setting($key, $default);
}

function repo_seo(string $slug): ?array
{
    return Repo::seo($slug);
}

function repo_image(int $id): ?array
{
    return Repo::image($id);
}

function repo_attach_images(array $rows, string $fk = 'image_id'): array
{
    return Repo::attachImages($rows, $fk);
}

function repo_fold_image(array $row): array
{
    return Repo::foldImage($row);
}

function repo_page(string $slug): ?array
{
    return Repo::page($slug);
}

function repo_hero_slides(int $pageId, bool $includeInactive = false): array
{
    return Repo::heroSlides($pageId, $includeInactive);
}

function repo_testimonials(bool $includeInactive = false): array
{
    return Repo::testimonials($includeInactive);
}

function repo_section(string $slug): ?array
{
    return Repo::section($slug);
}

function repo_sections(): array
{
    return Repo::sections();
}

function repo_rules_timings(): array
{
    return \SJ\Content\Repo::rulesTimings();
}

function repo_timeline(int $sectionId): array
{
    return Repo::timeline($sectionId);
}

function repo_section_events(int $sectionId): array
{
    return Repo::sectionEvents($sectionId);
}

function repo_academy(string $slug): ?array
{
    return Repo::academy($slug);
}

/**
 * N3: public URL for an academy — the 18 shipped slugs keep their own .php
 * files; admin-created ones serve via the generic /academy.php controller.
 */
function academy_url(string $slug): string
{
    return in_array($slug, Registry::legacySlugs()['academy'], true)
        ? '/' . $slug . '.php'
        : '/academy.php?slug=' . rawurlencode($slug);
}

/** N4: same rule for gallery albums (gal-*.php vs /album.php?slug=…). */
function album_url(string $slug): string
{
    return in_array($slug, Registry::legacySlugs()['gallery_album'], true)
        ? '/' . $slug . '.php'
        : '/album.php?slug=' . rawurlencode($slug);
}

function repo_academies(bool $includeInactive = false): array
{
    return Repo::academies($includeInactive);
}

function repo_sports(bool $includeInactive = false): array
{
    return Repo::sports($includeInactive);
}

function repo_facilities(bool $includeInactive = false): array
{
    return Repo::facilities($includeInactive);
}

function repo_achievements(string $type, bool $includeInactive = false): array
{
    return Repo::achievements($type, $includeInactive);
}

function repo_albums(bool $includeInactive = false): array
{
    return Repo::albums($includeInactive);
}

function repo_album(string $slug, bool $includeInactive = false): ?array
{
    return Repo::album($slug, $includeInactive);
}

function repo_linked_images(string $ownerType, int $ownerId, string $role = 'carousel'): array
{
    return Repo::linkedImages($ownerType, $ownerId, $role);
}

function repo_profile(string $roleKey): ?array
{
    return Repo::profile($roleKey);
}

function repo_unique_features(bool $includeInactive = false): array
{
    return Repo::uniqueFeatures($includeInactive);
}

function repo_ticker(bool $includeInactive = false): array
{
    return Repo::ticker($includeInactive);
}

function repo_update_slides(bool $includeInactive = false): array
{
    return Repo::updateSlides($includeInactive);
}

function repo_marks_board(?int $limit = null, bool $includeInactive = false): array
{
    return Repo::marksBoard($limit, $includeInactive);
}
