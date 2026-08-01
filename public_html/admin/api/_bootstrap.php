<?php
// Shared guard for every admin API endpoint: session + CSRF + JSON I/O + validation.
require dirname(__DIR__, 2) . '/_libs/load.php';
sj_session_boot(true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_out(array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $code < 400] + $data, JSON_UNESCAPED_SLASHES);
    exit;
}

function api_fail(string $msg, int $code = 400): void
{
    api_out(['error' => $msg], $code);
}

if (!is_admin()) {
    api_fail('Not authenticated', 401);
}

// CSRF on every mutating request (all endpoints except GET images.php).
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        api_fail('Invalid CSRF token', 403);
    }
}

/** Decoded JSON body for application/json POSTs (multipart endpoints use $_POST). */
function api_input(): array
{
    static $in = null;
    if ($in === null) {
        $raw = file_get_contents('php://input');
        $in = json_decode($raw ?: '[]', true);
        if (!is_array($in)) {
            $in = [];
        }
    }
    return $in;
}

/**
 * Validate one field value against its registry definition.
 * Returns the normalised value ready for the DB (may be NULL). Calls api_fail() on error.
 */
function api_validate_field(string $entity, string $field, array $def, $value)
{
    $t = $def['type'];
    if ($value === null || (is_string($value) && trim($value) === '')) {
        if (!empty($def['required'])) {
            api_fail("Field '$field' is required");
        }
        if (!empty($def['nullable'])) {
            return null;
        }
        if ($t === 'text' || $t === 'html' || $t === 'url') {
            return '';
        }
        api_fail("Field '$field' cannot be empty");
    }
    switch ($t) {
        case 'text':
            $v = trim(strip_tags((string)$value));
            if (isset($def['max']) && mb_strlen($v) > $def['max']) {
                api_fail("Field '$field' too long (max {$def['max']})");
            }
            return $v;
        case 'html':
            $v = sj_sanitize_html((string)$value);
            if (isset($def['max']) && mb_strlen($v) > $def['max']) {
                api_fail("Field '$field' too long");
            }
            return $v;
        case 'url':
            $v = trim((string)$value);
            if (preg_match('/^(javascript|data|vbscript):/i', $v) || preg_match('/[\x00-\x1f]/', $v)) {
                api_fail("Field '$field' is not a valid link");
            }
            if (isset($def['max']) && mb_strlen($v) > $def['max']) {
                api_fail("Field '$field' too long");
            }
            return $v;
        case 'int':
            if (!is_numeric($value)) {
                api_fail("Field '$field' must be a number");
            }
            $v = (int)$value;
            if ((isset($def['min']) && $v < $def['min']) || (isset($def['max']) && $v > $def['max'])) {
                api_fail("Field '$field' out of range");
            }
            return $v;
        case 'enum':
            $v = (string)$value;
            if (!in_array($v, $def['values'], true)) {
                api_fail("Field '$field' has an invalid value");
            }
            return $v;
        case 'bool':
            return !empty($value) && $value !== '0' ? 1 : 0;
        case 'image':
            $v = (int)$value;
            if ($v <= 0 || !repo_image($v)) {
                api_fail("Field '$field': image not found");
            }
            return $v;
    }
    api_fail("Unknown field type for '$field'");
}

/** Resolve entity from a payload or fail. */
function api_entity(string $entity): array
{
    $reg = sj_registry_entity($entity);
    if ($reg === null) {
        api_fail('Unknown entity', 400);
    }
    return $reg;
}
