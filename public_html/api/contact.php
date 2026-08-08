<?php
// Public contact-form endpoint (C1, SECURITY.md SEC-23).
//
// Defense layers, in order:
//   1. POST-only, JSON body.
//   2. Same-origin check — a browser cross-site POST carries a foreign
//      Origin/Referer and is refused (this is an anonymous form, so there is
//      no session to CSRF; origin pinning + the layers below are the guard).
//   3. Honeypot — the hidden "website" field is empty for humans; bots that
//      fill it get a fake success and nothing is stored or sent.
//   4. Rate limit — max 5 submissions per IP per hour (contact_submissions).
//   5. Server-side reCAPTCHA verification when config['recaptcha_secret'] is
//      set (production). Without it (dev), the widget is still shown but the
//      token is not verifiable, so this layer is skipped.
//   6. Every accepted enquiry is STORED (inbox + audit trail), then relayed
//      by email when a transport is configured:
//        config['contact']['mail_to']  → PHP mail() (works on MilesWeb).
//      The client-side EmailJS SDK + public key are gone from the site.
//
// Output values never include internals; all input is length-capped and the
// stored values are inserted via placeholders only (SEC-01).

require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function contact_out(bool $ok, array $extra = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    contact_out(false, ['error' => 'Method not allowed'], 405);
}

// --- 2. same-origin (when the browser sends Origin/Referer) ---
$host = $_SERVER['HTTP_HOST'] ?? '';
foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
    if (!empty($_SERVER[$h])) {
        $srcHost = parse_url($_SERVER[$h], PHP_URL_HOST);
        $srcPort = parse_url($_SERVER[$h], PHP_URL_PORT);
        $src = $srcHost . ($srcPort ? ':' . $srcPort : '');
        // Host header may or may not carry the port — compare both forms.
        if ($src !== $host && $srcHost !== $host) {
            contact_out(false, ['error' => 'Bad origin'], 403);
        }
    }
}

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) {
    contact_out(false, ['error' => 'Bad request'], 400);
}

// --- 3. honeypot: pretend success, store nothing ---
if (!empty($in['website'])) {
    contact_out(true);
}

// --- field validation (mirrors the client rules) ---
$first  = trim((string)($in['first_name'] ?? ''));
$last   = trim((string)($in['last_name'] ?? ''));
$email  = trim((string)($in['email'] ?? ''));
$mobile = trim((string)($in['mobile'] ?? ''));
$msg    = trim((string)($in['message'] ?? ''));

if ($first === '' || $last === '' || mb_strlen($first) > 80 || mb_strlen($last) > 80) {
    contact_out(false, ['error' => 'Please fill in all required fields.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    contact_out(false, ['error' => 'Invalid email format.'], 400);
}
if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    contact_out(false, ['error' => 'Invalid mobile number format.'], 400);
}
if (mb_strlen($msg) > 5000) {
    contact_out(false, ['error' => 'Message too long.'], 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// --- 4. rate limit: 5 / hour / IP ---
$st = db()->prepare('SELECT COUNT(*) FROM contact_submissions WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
$st->execute([$ip]);
if ((int)$st->fetchColumn() >= 5) {
    contact_out(false, ['error' => 'Too many messages. Please try again later.'], 429);
}

// --- 5. reCAPTCHA server-side verification (production) ---
$secret = (string)(sj_config()['recaptcha_secret'] ?? '');
if ($secret !== '') {
    $token = (string)($in['recaptcha'] ?? '');
    if ($token === '') {
        contact_out(false, ['error' => 'Please verify that you are not a robot.'], 400);
    }
    $resp = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]),
            'timeout' => 5,
        ],
    ]));
    $verdict = json_decode((string)$resp, true);
    if (empty($verdict['success'])) {
        contact_out(false, ['error' => 'Robot check failed. Please try again.'], 400);
    }
}

// --- 6. store, then relay ---
db()->prepare('INSERT INTO contact_submissions (first_name, last_name, email, mobile, message, ip) VALUES (?,?,?,?,?,?)')
    ->execute([$first, $last, $email, $mobile, $msg, $ip]);
$subId = (int)db()->lastInsertId();

$mailTo = (string)(sj_config()['contact']['mail_to'] ?? '');
if ($mailTo !== '' && filter_var($mailTo, FILTER_VALIDATE_EMAIL)) {
    // Header values are fully server-controlled; user text goes only in the
    // body (no CR/LF can reach a header — SEC-23 mail-injection guard).
    $body = "New enquiry from the website contact form\n\n"
          . "Name:   $first $last\n"
          . "Email:  $email\n"
          . "Mobile: $mobile\n\n"
          . "Message:\n" . str_replace(["\r"], '', $msg) . "\n";
    $sent = @mail(
        $mailTo,
        'Website enquiry - St. Josephs MHSS',
        $body,
        'From: no-reply@' . preg_replace('/[^a-z0-9.\-]/i', '', explode(':', $host)[0]) . "\r\n"
        . 'Reply-To: ' . $email
    );
    if ($sent) {
        db()->prepare('UPDATE contact_submissions SET sent = 1 WHERE id = ?')->execute([$subId]);
    }
}

contact_out(true);
