<?php
// POST {values:{key:value,…}} → upsert whitelisted site settings (C1).
//
// `settings` is deliberately NOT in the content registry (CLAUDE.md rule) —
// this dedicated endpoint edits ONLY the keys hardcoded below, so the generic
// field/item APIs can never touch arbitrary settings rows.
require __DIR__ . '/_bootstrap.php';

/** key => [max length, validator, allowEmpty?] — the ONLY editable settings.
 *  allowEmpty (J2): '' is stored as-is and means "feature off" — used by the
 *  optional keys whose consumers hide themselves when blank. */
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
    'whatsapp_number'       => [15,  'digits', true], // J2: floating WhatsApp button; blank hides it
    // K4: About rules block — diary line + download target
    'diary_text'            => [200, 'text'],
    'diary_url'             => [200, 'text'],
    // K7: Home "Our Motto" block
    'motto_heading'         => [80,  'text'],
    'motto_sub'             => [200, 'text'],
    'motto1_title'          => [60,  'text'],
    'motto1_body'           => [400, 'text'],
    'motto2_title'          => [60,  'text'],
    'motto2_body'           => [400, 'text'],
    // J3: Home stat band (value = number + optional suffix, e.g. "2200+")
    'home_stat1_value'      => [12,  'text'],
    'home_stat1_label'      => [40,  'text'],
    'home_stat2_value'      => [12,  'text'],
    'home_stat2_label'      => [40,  'text'],
    'home_stat3_value'      => [12,  'text'],
    'home_stat3_label'      => [40,  'text'],
    'home_stat4_value'      => [12,  'text'],
    'home_stat4_label'      => [40,  'text'],
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
    $allowEmpty = $SETTING_KEYS[$key][2] ?? false;
    $value = trim((string)$value);
    if ($value === '' && $allowEmpty) {
        $clean[$key] = ''; // feature off — stored empty on purpose
        continue;
    }
    if ($value === '' || mb_strlen($value) > $max) {
        api_fail("'$key' must be 1–$max characters");
    }
    if ($type === 'digits' && !preg_match('/^\d{8,15}$/', $value)) {
        api_fail("'$key' must be 8–15 digits (country code + number, no spaces)");
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
