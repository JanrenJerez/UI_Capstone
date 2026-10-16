<?php
// Schema upgrades, tracked with SQLite's PRAGMA user_version.
// Runs on every request but only does work when the database is behind,
// so existing installs (AppData\PROJECT - E\gym.db) upgrade automatically.

const DEFAULT_APP_PIN = '1234';
const DEFAULT_ADMIN_PASSWORD = 'admin123';

function run_migrations(PDO $pdo) {
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

    if ($version < 1) {
        $pdo->beginTransaction();

        // App settings (hashed PIN, flags)
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");
        $setting = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
        $setting->execute(['app_pin', password_hash(DEFAULT_APP_PIN, PASSWORD_DEFAULT)]);
        $setting->execute(['admin_password_is_default', '1']);

        // Reset the admin password (the original one was unknown)
        $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
        if ($exists) {
            $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'")->execute([$hash]);
        } else {
            $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')")->execute([$hash]);
        }

        // Walk-in day pass visits (attendance.member_id is NOT NULL, so they get their own log)
        $pdo->exec("CREATE TABLE IF NOT EXISTS walkin_visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER,
            customer_name TEXT,
            visit_time DATETIME
        )");

        // Every member needs a QR id to be scannable
        $missing = $pdo->query("SELECT id FROM members WHERE qr_id IS NULL OR qr_id = ''")->fetchAll(PDO::FETCH_COLUMN);
        $setQr = $pdo->prepare("UPDATE members SET qr_id = ? WHERE id = ?");
        foreach ($missing as $memberId) {
            $setQr->execute([migrate_new_qr_id($pdo), $memberId]);
        }

        // membership_type was never written; keep it in sync with tier
        $pdo->exec("UPDATE members SET membership_type = tier WHERE (membership_type IS NULL OR membership_type = '') AND tier IS NOT NULL");

        // Old rows were stored in UTC (CURRENT_TIMESTAMP / datetime('now')); store local time from now on
        $pdo->exec("UPDATE sales SET sale_date = datetime(sale_date, 'localtime') WHERE sale_date IS NOT NULL");
        $pdo->exec("UPDATE members SET created_at = datetime(created_at, 'localtime') WHERE created_at IS NOT NULL");

        $pdo->exec('PRAGMA user_version = 1');
        $pdo->commit();
    }
}

function migrate_new_qr_id(PDO $pdo) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM members WHERE qr_id = ?");
    do {
        $id = 'PE-' . strtoupper(bin2hex(random_bytes(5)));
        $check->execute([$id]);
    } while ($check->fetchColumn() > 0);
    return $id;
}
?>
