<?php
// Enable error reporting for troubleshooting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include __DIR__ . '/includes/db.php'; // Provides $pdo connection

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if form was submitted via either button name convention
    if (isset($_POST['reset_request']) || isset($_POST['username'])) {
        $user = trim($_POST['username']);

        if (empty($user)) {
            $error = "Please enter your username.";
        } else {
            try {
                // Query the users table
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$user]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    // Username found, store in session and redirect using JavaScript fallback
                    $_SESSION['reset_username'] = $row['username'];
                    echo "<script>window.location.href='reset_password.php';</script>";
                    exit();
                } else {
                    $error = "Username '" . htmlspecialchars($user) . "' not found in database.";
                }
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project-E - Forgot Password</title>
    <link rel="stylesheet" href="assets/style.css">
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
        }
        .btn-action:hover { background: #b91c1c; }
    </style>
</head>
<body>

<div class="glass-card">
    <h1 class="page-title">Password Recovery</h1>
    
    <?php if(!empty($error)): ?>
        <div style="color:#ef4444; margin-bottom:15px; font-size:0.85rem; font-weight:bold; background: #450a0a; padding: 8px; border-radius: 6px; border: 1px solid #7f1d1d;"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <p style="color: #a3a3a3; font-size: 0.85rem; margin-top: 0; margin-bottom: 20px;">
            Enter your staff account username below to proceed.
        </p>
        <input type="text" name="username" placeholder="Enter Username (e.g. admin)" required>
        <button type="submit" name="reset_request" class="btn-action">Reset Password</button>
        
        <div style="margin-top: 20px;">
            <a href="index.php" style="color: #a3a3a3; font-size: 0.80rem; text-decoration: none; font-weight: bold;">← Back to Staff Login</a>
        </div>
    </form>
</div>

</body>
</html>