<?php
// Admin session helpers + the data-edit-* attribute emitters. All emitters return ''
// for public visitors, so public output is byte-identical to a static render.

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
    session_name('SJADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    $booted = true;
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
