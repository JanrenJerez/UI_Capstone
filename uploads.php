<?php
// Serves uploaded product images from the writable upload folder.
include __DIR__ . '/includes/upload_paths.php';

$name = basename($_GET['f'] ?? '');
$path = upload_dir() . $name;

if ($name === '' || $name === '.' || $name === '..' || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
if (strpos($mime, 'image/') !== 0) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
