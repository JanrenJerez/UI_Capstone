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

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please restart the app.");
}

require_once __DIR__ . '/helpers.php';
?>
