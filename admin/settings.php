<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_unlocked();

$message = '';
$error = '';

// Folder for automatic safety copies made before a restore
function backup_dir() {
    $base = getenv('USER_DATA_PATH') ?: __DIR__ . '/../database';
    $dir = $base . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return $dir;
}

// --- Download a backup -------------------------------------------------------
if (isset($_GET['download_backup'])) {
    $tmp = tempnam(sys_get_temp_dir(), 'pe_backup_');
    @unlink($tmp); // VACUUM INTO needs a path that doesn't exist yet
    try {
        $pdo->prepare("VACUUM INTO ?")->execute([$tmp]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="project-e-backup-' . date('Y-m-d_His') . '.db"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
    } catch (Exception $ex) {
        error_log('Backup failed: ' . $ex->getMessage());
        http_response_code(500);
        echo 'Backup failed. Please try again.';
    }
    @unlink($tmp);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Change PIN ----------------------------------------------------------
    if (isset($_POST['change_pin'])) {
        $newPin = $_POST['new_pin'] ?? '';
        if (!verify_pin($pdo, $_POST['current_pin'] ?? '')) {
            $error = 'Current PIN is incorrect.';
        } elseif (!is_valid_pin($newPin)) {
            $error = 'New PIN must be exactly 4 digits.';
        } elseif ($newPin !== ($_POST['confirm_pin'] ?? '')) {
            $error = 'New PINs do not match.';
        } else {
            set_setting($pdo, 'app_pin', password_hash($newPin, PASSWORD_DEFAULT));
            $message = 'PIN updated successfully.';
        }
    }

    // --- Change admin password -----------------------------------------------
    elseif (isset($_POST['change_password'])) {
        $newPass = $_POST['new_password'] ?? '';
        if (!verify_admin_password($pdo, 'admin', $_POST['current_password'] ?? '')) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($newPass !== ($_POST['confirm_password'] ?? '')) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'")
                ->execute([password_hash($newPass, PASSWORD_DEFAULT)]);
            set_setting($pdo, 'admin_password_is_default', '0');
            $message = 'Admin password updated successfully.';
        }
    }

    // --- Restore from a backup -----------------------------------------------
    elseif (isset($_POST['restore_backup'])) {
        $upload = $_FILES['backup_file'] ?? null;

        if (!verify_admin_password($pdo, 'admin', $_POST['restore_password'] ?? '')) {
            $error = 'Admin password is incorrect.';
        } elseif (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a backup file to restore.';
        } elseif (file_get_contents($upload['tmp_name'], false, null, 0, 16) !== "SQLite format 3\0") {
            $error = 'That file is not a Project-E database backup.';
        } else {
            $valid = false;
            try {
                $check = new PDO('sqlite:' . $upload['tmp_name']);
                $check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $tables = $check->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
                $integrity = $check->query('PRAGMA integrity_check')->fetchColumn();
                $valid = $integrity === 'ok' && !array_diff(['members', 'sales', 'users'], $tables);
                $check = null;
            } catch (Exception $ex) {
                error_log('Restore check failed: ' . $ex->getMessage());
            }

            if (!$valid) {
                $error = 'That file is damaged or is not a Project-E database backup.';
            } else {
                // Keep a safety copy of the current data first
                $safety = backup_dir() . DIRECTORY_SEPARATOR . 'before-restore-' . date('Y-m-d_His') . '.db';
                $pdo->prepare("VACUUM INTO ?")->execute([$safety]);

                // Close our connection so the file can be replaced (required on Windows)
                $pdo = null;
                $conn = null;

                if (copy($upload['tmp_name'], $db_file)) {
                    lock_app();
                    header('Location: /lock.php?next=' . urlencode('/admin/settings.php?restored=1'));
                    exit();
                }
                $error = 'Could not replace the database file. Your data was not changed.';
                require __DIR__ . '/../includes/db.php';
            }
        }
    }
}

// After a successful save, redirect so refreshing the page doesn't submit the form again
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $message !== '' && $error === '') {
    header('Location: settings.php?msg=' . urlencode($message));
    exit();
}
if (isset($_GET['msg'])) {
    $message = (string) $_GET['msg'];
}
if (isset($_GET['restored'])) {
    $message = 'Backup restored successfully.';
}

$passwordIsDefault = get_setting($pdo, 'admin_password_is_default') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Project-E</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #0a0a0a; color: #ffffff; font-family: 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.4)), url('/assets/gym.jpg');
            background-attachment: fixed; background-size: cover; background-position: center;
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
        .page-title { color: #dc2626; font-size: 2rem; font-weight: 800; text-transform: uppercase; margin: 0 0 25px; }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .card { background: rgba(18, 18, 18, 0.95); border: 1px solid #262626; padding: 25px; border-radius: 16px; }
        .card h3 { color: #dc2626; margin: 0 0 6px; font-size: 1.05rem; text-transform: uppercase; }
        .card p.hint { color: #a3a3a3; font-size: 0.85rem; margin: 0 0 18px; }
        label { display: block; color: #a3a3a3; font-size: 0.8rem; margin-top: 8px; }
        input[type=password], input[type=file] {
            width: 100%; padding: 10px; margin: 6px 0 8px; background: #0a0a0a;
            border: 1px solid #262626; border-radius: 8px; color: white;
        }
        input:focus { border-color: #dc2626; outline: none; }
        .btn { display: inline-block; width: 100%; margin-top: 12px; padding: 12px; border: none; border-radius: 10px;
               color: #fff; font-weight: 700; cursor: pointer; text-transform: uppercase; text-align: center; text-decoration: none; font-size: 0.9rem; }
        .btn-red { background: #dc2626; }
        .btn-blue { background: #2563eb; }
        .btn-dark { background: #7f1d1d; color: #fca5a5; border: 1px solid #991b1b; }
        .msg { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 0.9rem; }
        .msg-success { background: rgba(34, 197, 94, 0.12); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.35); }
        .msg-error { background: rgba(220, 38, 38, 0.12); color: #f87171; border: 1px solid rgba(220, 38, 38, 0.35); }
        .msg-warn { background: rgba(234, 179, 8, 0.12); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.35); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <h1 class="page-title">Settings</h1>

    <?php if ($message): ?><div class="msg msg-success"><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg msg-error"><?php echo e($error); ?></div><?php endif; ?>
    <?php if ($passwordIsDefault): ?>
        <div class="msg msg-warn">The admin password is still the default (<b>admin123</b>). Change it below; it is needed to reset a forgotten PIN and to restore backups.</div>
    <?php endif; ?>

    <div class="settings-grid">
        <div class="card">
            <h3>Change App PIN</h3>
            <p class="hint">The 4-digit PIN unlocks the app.</p>
            <form method="POST" autocomplete="off">
                <label>Current PIN</label>
                <input type="password" name="current_pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" required>
                <label>New 4-Digit PIN</label>
                <input type="password" name="new_pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" required>
                <label>Confirm New PIN</label>
                <input type="password" name="confirm_pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" required>
                <button type="submit" name="change_pin" class="btn btn-red">Save New PIN</button>
            </form>
        </div>

        <div class="card">
            <h3>Change Admin Password</h3>
            <p class="hint">Used to reset a forgotten PIN and to restore backups.</p>
            <form method="POST" autocomplete="off">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
                <label>New Password (min. 6 characters)</label>
                <input type="password" name="new_password" minlength="6" required>
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" minlength="6" required>
                <button type="submit" name="change_password" class="btn btn-red">Save New Password</button>
            </form>
            <p class="hint" style="margin-top: 14px;">Forgot it? Use <a href="/forgotpass.php" style="color:#38bdf8;">Password Recovery</a> with the app PIN.</p>
        </div>

        <div class="card">
            <h3>Backup Database</h3>
            <p class="hint">Download a copy of all members, sales, expenses and settings. Keep it on a USB drive or another PC.</p>
            <a href="?download_backup=1" class="btn btn-blue">⬇ Download Backup</a>
        </div>

        <div class="card">
            <h3>Restore Backup</h3>
            <p class="hint">Replaces <b>all</b> current data with the backup file. A safety copy of the current data is saved first.</p>
            <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Replace ALL current data with this backup?');">
                <label>Backup File (.db)</label>
                <input type="file" name="backup_file" accept=".db" required>
                <label>Admin Password</label>
                <input type="password" name="restore_password" required>
                <button type="submit" name="restore_backup" class="btn btn-dark">Restore Backup</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
