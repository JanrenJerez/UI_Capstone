<?php
session_start();
include __DIR__ . '/includes/db.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $answer_input = $_POST['recovery_answer'];
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $query = "SELECT * FROM users WHERE username='$user' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($answer_input, $row['recovery_answer'])) {
            
            $update = $conn->query("UPDATE users SET password='$new_pass' WHERE username='$user'");
            
            if ($update) {
                $message = "Password for " . htmlspecialchars($user) . " updated successfully!";
            } else {
                $error = "Error updating database.";
            }
        } else {
            $error = "Incorrect security answer.";
        }
    } else {
        $error = "Username not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Project E</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { 
            background: #0f172a; 
            display: flex; align-items: center; justify-content: center; height: 100vh;
            background-image: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.8)), url('./assets/gym.jpg');
            background-size: cover; font-family: sans-serif; margin: 0;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95); padding: 40px; border-radius: 20px;
            width: 100%; max-width: 400px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box; }
        .btn-reset { 
            width: 100%; padding: 15px; background: #38bdf8; border: none; border-radius: 10px; 
            font-weight: bold; cursor: pointer; color: #0f172a; transition: 0.3s;
        }
        .btn-reset:hover { background: #0ea5e9; }
        .msg { padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="glass-card">
        <h2 style="color: #0f172a; margin-bottom: 5px;">Reset Password</h2>
        <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 25px;">Enter details to regain system access.</p>

        <?php if($error): ?>
            <div class="msg" style="background: #fee2e2; color: #991b1b;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($message): ?>
            <div class="msg" style="background: #dcfce7; color: #166534;">
                <?php echo $message; ?> <br>
                <a href="login.php" style="font-weight: bold; color: #166534;">Go to Login</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="text" name="recovery_answer" placeholder="Your Security Answer" required autocomplete="off">
            <input type="password" name="new_password" placeholder="New Password" required>
            <button type="submit" name="reset_password" class="btn-reset">Update Password</button>
            <div style="margin-top: 20px;">
                <a href="login.php" style="color: #64748b; font-size: 0.8rem; text-decoration: none;">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>