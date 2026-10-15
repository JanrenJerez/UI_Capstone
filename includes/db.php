<?php
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

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
