<?php
$db_file = __DIR__ . '/../database/gym.db';

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