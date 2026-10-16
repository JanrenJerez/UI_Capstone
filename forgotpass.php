<?php
// Password recovery: confirm the username and the current app PIN, then set a new password.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $wait = auth_wait_seconds($pdo);

    if ($wait > 0) {
        $error = "Too many wrong attempts. Try again in {$wait} seconds.";
    } elseif ($username === '' || !verify_pin($pdo, $_POST['pin'] ?? '')) {
        record_auth_failure($pdo);
        $error = 'Incorrect username or PIN.';
    } else {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $found = $stmt->fetchColumn();

        if ($found) {
            clear_auth_failures($pdo);
            $_SESSION['reset_username'] = $found;
            $_SESSION['reset_expires'] = time() + 10 * 60;
            header('Location: reset_pass.php');
            exit();
        }
        record_auth_failure($pdo);
        $error = 'Incorrect username or PIN.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project-E - Forgot Password</title>
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<div class="glass-card">
    <h1 class="page-title">Password Recovery</h1>

    <?php if ($error): ?><div class="msg-error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
        <p class="hint">Enter the account username and the current app PIN to set a new password.</p>
        <label>Username</label>
        <input type="text" name="username" value="admin" required>
        <label>App PIN</label>
        <input type="password" name="pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" required>
        <button type="submit" class="btn-action">Continue</button>
    </form>
    <div class="links"><a href="/lock.php">← Back to Unlock</a></div>
</div>
</body>
</html>
