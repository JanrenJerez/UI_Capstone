<?php
session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/nav.php';

if (!isset($_GET['id'])) { 
    header("Location: Manage.php"); 
    exit(); 
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$result = $conn->query("SELECT * FROM members WHERE id = '$id'");
$member = $result->fetch_assoc();

if (!$member) {
    die("Member not found.");
}

if (isset($_POST['update'])) {
    $username = mysqli_real_escape_string($conn, $_POST['name']);
    $expires_at = mysqli_real_escape_string($conn, $_POST['expiry_date']);

    $sql = "UPDATE members SET name='$username', expiry_date='$expires_at' WHERE id='$id'";
    
    if ($conn->query($sql)) {
        echo "<script>alert('Update Successful'); window.location='Manage.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Member | Project E</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { background: #0f172a; font-family: 'Segoe UI', sans-serif; color: white; }
        .edit-card { background: white; padding: 30px; border-radius: 15px; max-width: 450px; margin: 50px auto; color: #1e293b; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { margin-top: 0; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        label { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: #64748b; display: block; margin-top: 15px; }
        input { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 1rem; }
        .btn-update { background: #38bdf8; color: #0f172a; border: none; padding: 15px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 25px; transition: 0.2s; }
        .btn-update:hover { background: #0ea5e9; transform: translateY(-1px); }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="edit-card">
    <h2>Edit Member</h2>
    <form method="POST">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($member['name']); ?>" required>

        <label>Membership Expiry</label>
        <input type="datetime-local" name="expiry_date" 
               value="<?php echo date('Y-m-d\TH:i', strtotime($member['expiry_date'])); ?>" required>

        <label>Member ID (Fixed)</label>
        <input type="text" value="#<?php echo $member['id']; ?>" disabled style="background: #f8fafc; color: #94a3b8;">

        <button type="submit" name="update" class="btn-update">Save Changes</button>
        <a href="Manage.php" class="back-link">Cancel and Go Back</a>
    </form>
</div>

</body>
</html>