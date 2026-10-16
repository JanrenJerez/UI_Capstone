<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = "";
$success = "";

// Must come from forgotpass.php (username + PIN confirmed) within the last 10 minutes
if (!isset($_SESSION['reset_username']) || time() > ($_SESSION['reset_expires'] ?? 0)) {
    unset($_SESSION['reset_username'], $_SESSION['reset_expires']);
    header("Location: forgotpass.php");
    exit();
}

$username = $_SESSION['reset_username'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_pass) || empty($confirm_pass)) {
        $error = "Please fill in all fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        if ($stmt->execute([password_hash($new_pass, PASSWORD_DEFAULT), $username])) {
            set_setting($pdo, 'admin_password_is_default', '0');
            unset($_SESSION['reset_username'], $_SESSION['reset_expires']);
            $success = "Password successfully updated!";
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project-E - New Password</title>
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<div class="glass-card">
    <h1 class="page-title">Set New Password</h1>

    <?php if ($error): ?><div class="msg-error"><?php echo e($error); ?></div><?php endif; ?>

    <?php if ($success): ?>
        <div class="msg-success"><?php echo e($success); ?></div>
        <a href="/lock.php" class="btn-action">Back to Unlock</a>
    <?php else: ?>
        <form method="POST">
            <p class="hint">Resetting password for: <strong style="color: white;"><?php echo e($username); ?></strong></p>
            <input type="password" name="new_password" placeholder="New Password (min. 6 characters)" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="update_password" class="btn-action">Update Password</button>
        </form>
        <div class="links"><a href="/lock.php">Cancel</a></div>
    <?php endif; ?>
</div>
</body>
</html>
