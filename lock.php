<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '/index.php');
$error = '';

if (is_unlocked()) {
    header('Location: ' . $next);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wait = auth_wait_seconds($pdo);
    if ($wait > 0) {
        $error = "Too many wrong attempts. Try again in {$wait} seconds.";
    } elseif (verify_pin($pdo, $_POST['pin'] ?? '')) {
        clear_auth_failures($pdo);
        unlock_app();
        header('Location: ' . $next);
        exit();
    } else {
        record_auth_failure($pdo);
        $error = 'Incorrect PIN. Try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Locked | Project-E</title>
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<div class="glass-card">
    <div class="brand">PROJECT -E<img src="/assets/logo.jpg" alt="Logo"></div>
    <h1 class="page-title">App Locked</h1>
    <p class="hint">Enter your 4-digit PIN to unlock</p>

    <?php if ($error): ?><div class="msg-error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="next" value="<?php echo e($next); ?>">
        <input type="password" name="pin" class="pin-input" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" placeholder="••••" required autofocus>
        <button type="submit" class="btn-action">Unlock</button>
    </form>

    <div class="links">
        <a href="/forgot_pin.php">Forgot PIN?</a>
        <a href="/forgotpass.php">Forgot admin password?</a>
    </div>
</div>
</body>
</html>
