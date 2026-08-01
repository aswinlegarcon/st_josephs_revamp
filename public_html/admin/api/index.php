<?php
// Admin API front controller — the single entry point for every admin API call.
// Routes come ONLY from this hardcoded map; the request can never name a file or
// class (SECURITY.md SEC-01/11). Each impl file requires _bootstrap.php first
// (auth + forced-password gate + CSRF + JSON), so the guard order is preserved.

$routes = [
    'field'  => 'field.php',   // POST  {entity,id,field,value}
    'item'   => 'item.php',    // POST  {action:get|create|update|delete,…}
    'order'  => 'order.php',   // POST  {entity,ids[]}
    'upload' => 'upload.php',  // POST  multipart
    'images' => 'images.php',  // GET   ?q=&page=
];

$r = (string)($_GET['r'] ?? '');
if (!isset($routes[$r])) {
    require __DIR__ . '/_bootstrap.php'; // sets JSON headers + guards, then:
    api_fail('Unknown route', 404);
}

require __DIR__ . '/' . $routes[$r];
