<?php
date_default_timezone_set('Asia/Manila');

// In the packaged app, Electron passes USER_DATA_PATH so the DB lives in a writable,
// reinstall-safe folder. In dev, fall back to the bundled database/gym.db.
$userDataPath = getenv('USER_DATA_PATH');
$db_file = $userDataPath
    ? $userDataPath . DIRECTORY_SEPARATOR . 'gym.db'
    : __DIR__ . '/../database/gym.db';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Define BOTH so it works whether your code looks for $pdo or $conn
    $conn = $pdo;

    require_once __DIR__ . '/migrate.php';
    run_migrations($pdo);

} catch (Throwable $e) {
    // This is a local desktop app, so show the real reason: it is the only way to diagnose a PC we can't see
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    $folder = dirname($db_file);
    $details = [
        'Reason' => $e->getMessage(),
        'Database file' => $db_file,
        'File exists' => file_exists($db_file) ? 'yes' : 'no',
        'Folder writable' => is_writable($folder) ? 'yes' : 'no',
        'SQLite driver loaded' => class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true) ? 'yes' : 'no',
        'PHP' => PHP_VERSION . ' (' . php_ini_loaded_file() . ')',
    ];
    echo '<div style="font-family:Segoe UI,sans-serif;max-width:720px;margin:60px auto;padding:24px;border:1px solid #7f1d1d;border-radius:12px;background:#1a0a0a;color:#fca5a5">';
    echo '<h2 style="margin-top:0;color:#ef4444">Database connection failed</h2>';
    echo '<p style="color:#e5e5e5">Please take a screenshot of this box and send it to the developer.</p><table style="color:#e5e5e5;font-size:14px">';
    foreach ($details as $label => $value) {
        echo '<tr><td style="padding:4px 12px 4px 0;color:#a3a3a3;vertical-align:top">' . htmlspecialchars($label) . '</td><td style="word-break:break-all">' . htmlspecialchars((string) $value) . '</td></tr>';
    }
    echo '</table></div>';
    exit();
}

require_once __DIR__ . '/helpers.php';
?>
