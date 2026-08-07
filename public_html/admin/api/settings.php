<?php
// POST {values:{key:value,…}} → upsert whitelisted site settings (C1).
//
// `settings` is deliberately NOT in the content registry (CLAUDE.md rule) —
// this dedicated endpoint edits ONLY the keys hardcoded below, so the generic
// field/item APIs can never touch arbitrary settings rows.
require __DIR__ . '/_bootstrap.php';

/** key => [max length, validator] — the ONLY editable settings. */
$SETTING_KEYS = [
    'contact_email'         => [160, 'email'],
    'contact_phone'         => [40,  'text'],
    'contact_address_line1' => [120, 'text'],
    'contact_address_line2' => [120, 'text'],
    'facebook_url'          => [200, 'url'],
    'youtube_url'           => [200, 'url'],
    'timing_morning'        => [60,  'text'],
    'timing_lunch'          => [60,  'text'],
    'timing_afternoon'      => [60,  'text'],
    'jumbotron_heading'     => [120, 'text'],
    'jumbotron_sub'         => [120, 'text'],
    'jumbotron_btn'         => [40,  'text'],
    'footer_copyright'      => [160, 'text'],
    'marks_years_shown'     => [2,   'int'],
];

$in     = api_input();
$values = $in['values'] ?? null;
if (!is_array($values) || !$values) {
    api_fail('No values');
}

$clean = [];
foreach ($values as $key => $value) {
    if (!isset($SETTING_KEYS[$key])) {
        api_fail('Unknown setting');
    }
    [$max, $type] = $SETTING_KEYS[$key];
    $value = trim((string)$value);
    if ($value === '' || mb_strlen($value) > $max) {
        api_fail("'$key' must be 1–$max characters");
    }
    if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        api_fail("'$key' must be a valid email address");
    }
    if ($type === 'url' && !preg_match('#^https://[^\s<>"\']+$#', $value)) {
        api_fail("'$key' must be an https:// URL");
    }
    if ($type === 'int' && (!ctype_digit($value) || (int)$value < 1 || (int)$value > 10)) {
        api_fail("'$key' must be a number from 1 to 10");
    }
    $clean[$key] = $value;
}

$st = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
foreach ($clean as $key => $value) {
    $st->execute([$key, $value]);
}

sj_audit('settings.save', 'settings', null, implode(',', array_keys($clean)));
api_out(['saved' => count($clean)]);
