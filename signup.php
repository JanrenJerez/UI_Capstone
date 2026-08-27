<?php
session_start();
include __DIR__ . '/includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $q = mysqli_real_escape_string($conn, $_POST['recovery_question']);
    $a = password_hash($_POST['recovery_answer'], PASSWORD_DEFAULT);

    $checkUser = "SELECT * FROM users WHERE username = '$user'";
    $result = $conn->query($checkUser);

    if ($result->num_rows > 0) {
        $accError = "Username already taken. Please choose another.";
    } else {
        $sql = "INSERT INTO users (username, password, role, recovery_question, recovery_answer) 
                VALUES ('$user', '$pass', '$role', '$q', '$a')";

        if ($conn->query($sql)) {
            $accSuccess = "New $role account created! Redirecting to login...";
            header("refresh:2;url=login.php"); 
        } else {
            $accError = "Database Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Signup | Project E</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div class="login-box" style="margin: 50px auto; max-width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); background: #fff;">
        <h2 style="text-align: center; color: #0f172a;">Create System Account</h2>
        <p style="font-size: 0.85rem; color: #64748b; text-align: center; margin-bottom: 25px;">Register new Admin or Staff members for the POS system.</p>

        <?php if(isset($accSuccess)): ?>
            <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <?php echo $accSuccess; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($accError)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <?php echo $accError; ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Username</label>
                <input type="text" name="username" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Password</label>
                <input type="password" name="password" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Account Role</label>
                <select name="role" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: white;">
                    <option value="staff">Staff (Limited Access)</option>
                    <option value="admin">Admin (Full Control)</option>
                </select>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">
            <p style="font-size: 0.8rem; font-weight: bold; color: #475569; margin-bottom: 10px;">Security Recovery</p>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Recovery Question</label>
                <select name="recovery_question" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: white;">
                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                    <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                    <option value="In what city were you born?">In what city were you born?</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Recovery Answer</label>
                <input type="text" name="recovery_answer" placeholder="Your secret answer" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;" required>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; background: #0f172a; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
                Create Account
            </button>
            
            <p style="text-align: center; margin-top: 15px; font-size: 0.9rem;">
                Already have an account? <a href="login.php" style="color: #2563eb; text-decoration: none;">Login here</a>
            </p>
        </form>
    </div>
</div>
</body>
</html>