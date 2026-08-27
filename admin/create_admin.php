<?php
include __DIR__ . '/includes/db.php';

$user = 'admin';
$pass = 'admin123';
$hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

$sql = "UPDATE admin SET password = '$hashed_pass', fullname = 'System Admin' WHERE username = 'admin'";

if ($conn->query($sql)) {
    echo "<h2>Admin Password Reset!</h2>";
    echo "<p>The 'admin' account has been updated.</p>";
    echo "<p>Try logging in now at <a href='login.php'>login.php</a> with:</p>";
    echo "<ul><li><b>Username:</b> admin</li><li><b>Password:</b> admin123</li></ul>";
} else {
    echo "Error: " . $conn->error;
}
?>