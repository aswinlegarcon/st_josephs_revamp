<?php
// Admin session helpers + the data-edit-* attribute emitters. All emitters return ''
// for public visitors, so public output is byte-identical to a static render.

// Session lifetime policy (SECURITY.md SEC-07).
const SJ_SESSION_IDLE_MAX = 1800;   // 30 min of inactivity
const SJ_SESSION_ABS_MAX  = 43200;  // 12 h hard cap
const SJ_SESSION_REGEN    = 900;    // rotate the session id every 15 min

function sj_session_boot(bool $force = false): void
{
    static $booted = false;
    if ($booted || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        $booted = true;
        return;
    }
    if (!$force && empty($_COOKIE['SJADMIN'])) {
        return; // public visitor without an admin cookie: zero session cost
    }
    $cfg = sj_config();
    if (!empty($cfg['session_save_path'])) {
        session_save_path($cfg['session_save_path']); // private dir on prod (SEC-07/22)
    }
    // Secure cookies over real HTTPS, behind an HTTPS-terminating proxy, or when forced by config.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || !empty($cfg['force_secure_cookies']);
    session_name('SJADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_start();
    $booted = true;

    // Enforce idle + absolute timeouts and periodic id rotation for logged-in admins.
    // On expiry the session is destroyed so is_admin() becomes false and each caller's
    // existing behaviour fires (panel → login redirect, API → 401 JSON, public → visitor).
    if (!empty($_SESSION['admin_id'])) {
        $now   = time();
        $last  = $_SESSION['last_seen'] ?? $now;
        $start = $_SESSION['login_at']  ?? $now;
        if (($now - $last) > SJ_SESSION_IDLE_MAX || ($now - $start) > SJ_SESSION_ABS_MAX) {
            sj_session_kill();
            return;
        }
        $_SESSION['last_seen'] = $now;
        if (($now - ($_SESSION['last_regen'] ?? 0)) > SJ_SESSION_REGEN) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = $now;
        }
    }
}

/** Fully tear down the current session (data, file, cookie). */
function sj_session_kill(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_admin(): bool
{
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_id']);
}

function is_edit(): bool
{
    return is_admin() && !empty($_SESSION['edit_mode']);
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// data-edit-* attribute emitters (grammar per DYNAMIC_MIGRATION_PLAN.md §5.3)
// ---------------------------------------------------------------------------

/** Editable single field on a text element. */
function ed_field(string $entity, $id, string $field): string
{
    if (!is_edit()) {
        return '';
    }
    $def = sj_registry_entity($entity)['fields'][$field] ?? null;
    if ($def === null) {
        return '';
    }
    $attrs = sprintf(' data-edit-field="%s:%d:%s" data-edit-type="%s"', e($entity), (int)$id, e($field), e($def['type']));
    if ($def['type'] === 'enum') {
        $attrs .= ' data-edit-options="' . e(implode(',', $def['values'])) . '"';
    }
    return $attrs;
}

/** Deletable/reorderable repeating item (slide, card, row…). */
function ed_item(string $entity, $id, string $label = ''): string
{
    if (!is_edit()) {
        return '';
    }
    $reg = sj_registry_entity($entity) ?? [];
    $flags = (!empty($reg['orderable']) ? 'o' : '') . (!empty($reg['deletable']) ? 'd' : '');
    $a = sprintf(' data-edit-item="%s:%d" data-edit-flags="%s"', e($entity), (int)$id, $flags);
    if ($label !== '') {
        $a .= ' data-edit-label="' . e($label) . '"';
    }
    return $a;
}

/** "+ Add" affordance on a list container. $preset = column values fixed at creation. */
function ed_add(string $entity, array $preset = [], string $label = 'Add'): string
{
    if (!is_edit()) {
        return '';
    }
    $reg = sj_registry_entity($entity);
    if ($reg === null || empty($reg['creatable'])) {
        return '';
    }
    $fields = [];
    foreach ($reg['fields'] as $name => $def) {
        $fields[] = [
            'name'     => $name,
            'label'    => $def['label'] ?? ucfirst(str_replace('_', ' ', $name)),
            'type'     => $def['type'],
            'options'  => $def['values'] ?? null,
            'preset'   => $def['preset'] ?? null,
            'required' => !empty($def['required']),
        ];
    }
    $payload = ['entity' => $entity, 'preset' => $preset, 'label' => $label, 'fields' => $fields];
    return " data-edit-add='" . str_replace("'", '&#39;', json_encode($payload, JSON_UNESCAPED_SLASHES)) . "'";
}

/** Echo a raw *_html value; in edit mode it gets a layout-neutral editable wrapper. */
function ed_rich(string $entity, $id, string $field, ?string $html): void
{
    if (is_edit()) {
        echo '<div style="display:contents"' . ed_field($entity, $id, $field) . '>' . $html . '</div>';
    } else {
        echo (string)$html;
    }
}

/** Image slot (entity image field). Camera overlay → upload/pick modal. */
function ed_img(string $entity, $id, string $field = 'image_id'): string
{
    if (!is_edit()) {
        return '';
    }
    $def = sj_registry_entity($entity)['fields'][$field] ?? null;
    if ($def === null || $def['type'] !== 'image') {
        return '';
    }
    return sprintf(' data-edit-img="%s:%d:%s:%s"', e($entity), (int)$id, e($field), e($def['preset'] ?? ''));
}
