<?php
// Router for PHP's built-in server (php -S ... router.php).
// Blocks direct access to the database, internal includes and build files.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = str_replace('\\', '/', $path);

$blocked = '#^/(database|includes|exports|resources|node_modules|dist)(/|$)|^/(main\.js|package(-lock)?\.json|router\.php)$|\.(db|sqlite|md)$#i';
if (preg_match($blocked, $path) || str_contains($path, '..')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

if ($path === '/') {
    header('Location: /index.php');
    return true;
}

return false; // let the built-in server handle it
