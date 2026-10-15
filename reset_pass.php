<?php
session_start();
include __DIR__ . '/includes/db.php'; // Provides $pdo connection

$error = "";
$success = "";

// Ensure the user went through the forgot_password.php step first
if (!isset($_SESSION['reset_username'])) {
    header("Location: forgot_password.php");
    exit();
}

$username = $_SESSION['reset_username'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (empty($new_pass) || empty($confirm_pass)) {
        $error = "Please fill in all fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Hash the new password securely
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

        // Update the database via PDO prepared statement
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        if ($stmt->execute([$hashed_password, $username])) {
            // Clear the session flag and show success
            unset($_SESSION['reset_username']);
            $success = "Password successfully updated! You can now log in.";
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { box-sizing: border-box; }

        body { 
            background: #0a0a0a; 
            color: #ffffff; 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0;
            background-image: 
                linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.3)), 
                url('./assets/gym.jpg');
            background-size: cover; 
            background-attachment: fixed;
            background-position: center;
            height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .glass-card {
            background: rgba(18, 18, 18, 0.95); 
            backdrop-filter: blur(10px);
            color: #ffffff; 
            padding: 35px; 
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5); 
            width: 100%; 
            max-width: 420px; 
            text-align: center;
            border: 1px solid #262626;
        }

        .page-title {
            color: #ffffff; 
            background: #dc2626; 
            display: inline-block; 
            padding: 8px 20px; 
            border-radius: 6px; 
            text-transform: uppercase; 
            font-weight: 800; 
            margin-bottom: 20px; 
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }

        input { 
            width: 100%; 
            padding: 12px; 
            margin: 6px 0 12px 0; 
            background: #0a0a0a; 
            border: 1px solid #262626; 
            border-radius: 8px; 
            color: white; 
            font-size: 14px; 
            box-sizing: border-box; 
        }
        input:focus { border-color: #dc2626; outline: none; }

        .btn-action { 
            width: 100%; 
            padding: 12px; 
            background: #dc2626; 
            border: none; 
            border-radius: 10px; 
            color: #ffffff; 
            font-weight: bold; 
            cursor: pointer; 
            text-transform: uppercase;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            transition: 0.2s;
        }
        .btn-action:hover { background: #b91c1c; box-shadow: 0 0 15px rgba(220, 38, 38, 0.4); }
    </style>
</head>
<body>

<div class="glass-card">
    <h1 class="page-title">Set New Password</h1>
    
    <?php if(!empty($error)): ?>
        <div style="color:#ef4444; margin-bottom:15px; font-size:0.85rem; font-weight:bold; background: #450a0a; padding: 8px; border-radius: 6px; border: 1px solid #7f1d1d;"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <div style="color:#22c55e; margin-bottom:15px; font-size:0.85rem; font-weight:bold; background: #052e16; padding: 8px; border-radius: 6px; border: 1px solid #14532d;"><?php echo $success; ?></div>
        <div style="margin-top: 15px;">
            <a href="index.php" class="btn-action" style="display: block; text-decoration: none; text-align: center;">Proceed to Login</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <p style="color: #a3a3a3; font-size: 0.85rem; margin-top: 0; margin-bottom: 20px;">
                Resetting password for: <strong style="color: white;"><?php echo htmlspecialchars($username); ?></strong>
            </p>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="update_password" class="btn-action">Update Password</button>
            
            <div style="margin-top: 20px;">
                <a href="index.php" style="color: #a3a3a3; font-size: 0.8rem; text-decoration: none; font-weight: bold;">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>