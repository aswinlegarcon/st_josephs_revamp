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

function ed_add(string $entity, array $preset = [], string $label = 'Add'): string
{
    return EditAttrs::add($entity, $preset, $label);
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
