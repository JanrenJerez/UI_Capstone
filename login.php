<?php
session_start();
include __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password']; 

    $query = "SELECT * FROM users WHERE username='$user' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($pass, $row['password'])) {

            $_SESSION['isAdminLoggedIn'] = true; 
            $_SESSION['role'] = $row['role'];
            $_SESSION['user_name'] = $row['username'];

            if ($row['role'] == 'admin') {
                header("Location: admin/admin.php"); 
            } else {
                header("Location: scanner.php"); 
            }
            exit();
        } else {
            $error = "Invalid Password";
        }
    } else {
        $error = "User not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Access | Project E</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { 
            background: #0f172a; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            background-image: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.8)), url('./assets/gym.jpg');
            background-size: cover;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            text-align: center;
        }
        .login-card h2 { color: #0f172a; margin-bottom: 30px; }
        input { 
            width: 100%; padding: 12px; margin: 10px 0; 
            border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box; 
        }
        .btn-login {
            width: 100%; padding: 15px; background: #38bdf8; 
            border: none; border-radius: 10px; color: #0f172a; 
            font-weight: bold; cursor: pointer; text-transform: uppercase;
            margin-top: 10px;
        }
        .error-msg { 
            background: #fee2e2; color: #991b1b; padding: 10px; 
            border-radius: 8px; font-size: 0.8rem; margin-bottom: 15px; 
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1 style="color: #38bdf8; background: #000; display: inline-block; padding: 5px 15px; border-radius: 5px; font-size: 1.2rem; margin-bottom: 20px;">PROJECT E</h1>
    <h2>System Access</h2>
    
    <?php if(!empty($error)): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" class="btn-login">Login to System</button>

        <div style="margin-top: 20px; text-align: center; border-top: 1px solid #eee; pt-15px;">
            <a href="forgot_password.php" style="color: #64748b; font-size: 0.8rem; text-decoration: none; display: block; margin-top: 10px;">
                Forgot Password?
            </a>
            </a>
	<a href="index.php" style="color: #38bdf8; font-size: 0.8rem; text-decoration: none; font-weight: bold; display: block; margin-top: 10px;">
                Home 🏠
            </a>

        </div>
    </form>
</div>

</body>
</html>