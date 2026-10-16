<?php
// Reset the app PIN using the admin account's username and password.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $newPin = $_POST['new_pin'] ?? '';
    $confirmPin = $_POST['confirm_pin'] ?? '';
    $wait = auth_wait_seconds($pdo);

    if ($wait > 0) {
        $error = "Too many wrong attempts. Try again in {$wait} seconds.";
    } elseif (!verify_admin_password($pdo, $username, $password)) {
        record_auth_failure($pdo);
        $error = 'Incorrect username or password.';
    } elseif (!is_valid_pin($newPin)) {
        $error = 'New PIN must be exactly 4 digits.';
    } elseif ($newPin !== $confirmPin) {
        $error = 'PINs do not match.';
    } else {
        clear_auth_failures($pdo);
        set_setting($pdo, 'app_pin', password_hash($newPin, PASSWORD_DEFAULT));
        $success = 'PIN updated. You can now unlock the app with your new PIN.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset PIN | Project-E</title>
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<div class="glass-card">
    <h1 class="page-title">Reset PIN</h1>

    <?php if ($error): ?><div class="msg-error"><?php echo e($error); ?></div><?php endif; ?>

    <?php if ($success): ?>
        <div class="msg-success"><?php echo e($success); ?></div>
        <a href="/lock.php" class="btn-action">Back to Unlock</a>
    <?php else: ?>
        <p class="hint">Confirm with the admin account to set a new app PIN.</p>
        <form method="POST" autocomplete="off">
            <label>Admin Username</label>
            <input type="text" name="username" value="admin" required>
            <label>Admin Password</label>
            <input type="password" name="password" required>
            <label>New 4-Digit PIN</label>
            <input type="password" name="new_pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" required>
            <label>Confirm New PIN</label>
            <input type="password" name="confirm_pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" required>
            <button type="submit" class="btn-action">Set New PIN</button>
        </form>
        <div class="links"><a href="/lock.php">← Back to Unlock</a></div>
    <?php endif; ?>
</div>
</body>
</html>
