<?php
// Admin API front controller — the single entry point for every admin API call.
// Routes come ONLY from this hardcoded map; the request can never name a file or
// class (SECURITY.md SEC-01/11). Each impl file requires _bootstrap.php first
// (auth + forced-password gate + CSRF + JSON), so the guard order is preserved.

$routes = [
    'field'    => 'field.php',    // POST  {entity,id,field,value}
    'item'     => 'item.php',     // POST  {action:get|create|update|delete,…}
    'order'    => 'order.php',    // POST  {entity,ids[]}
    'upload'   => 'upload.php',   // POST  multipart
    'images'   => 'images.php',   // GET   ?q=&page=
    'settings' => 'settings.php', // POST  {values:{key:value,…}} — whitelisted keys only (C1)
    'link'     => 'link.php',     // POST  {action:list|attach|detach|reorder, owner_type,…} (M1)
    'recrop'   => 'recrop.php',   // POST  {image_id, crop_rect} — re-render renditions (M3)
    'image'    => 'image.php',    // POST  {action:meta|usage|delete, image_id} — media library ops (M4)
    'stats'    => 'stats.php',    // GET   dashboard vitals (N7)
    'admins'   => 'admins.php',   // POST  {action:list|create|reset|unlock|role|delete} — owners only (N7)
    'diary'    => 'diary.php',    // POST  multipart — replace the school-diary PDF + update diary_url (K11)
];

$r = (string)($_GET['r'] ?? '');
if (!isset($routes[$r])) {
    require __DIR__ . '/_bootstrap.php'; // sets JSON headers + guards, then:
    api_fail('Unknown route', 404);
}

require __DIR__ . '/' . $routes[$r];
