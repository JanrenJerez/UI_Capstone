<?php
// Product image storage helpers.
// Packaged app: images live in USER_DATA_PATH/assets and are served through /uploads.php.
// Dev (no USER_DATA_PATH): images live in the project's assets/ folder.

function upload_dir() {
    $userDataPath = getenv('USER_DATA_PATH');
    return $userDataPath
        ? $userDataPath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
        : __DIR__ . '/../assets/';
}

// Value stored in products.image_url for a newly uploaded file (relative to /pos/)
function upload_url($filename) {
    return getenv('USER_DATA_PATH')
        ? '../uploads.php?f=' . rawurlencode($filename)
        : '../assets/' . $filename;
}

// Browser-usable src for a stored image_url (also fixes old rows that stored a disk path)
function upload_src($image_url) {
    if (empty($image_url)) return '../assets/logo.jpg';
    if (strpos($image_url, '../') === 0) return $image_url;
    return '../uploads.php?f=' . rawurlencode(basename(str_replace('\\', '/', $image_url)));
}

// Disk path of a stored image_url, for deleting old files
function upload_disk_path($image_url) {
    if (empty($image_url)) return null;
    if (preg_match('/[?&]f=([^&]+)/', $image_url, $m)) {
        return upload_dir() . basename(rawurldecode($m[1]));
    }
    if (strpos($image_url, '../assets/') === 0) {
        return upload_dir() . basename($image_url);
    }
    return $image_url;
}
?>
