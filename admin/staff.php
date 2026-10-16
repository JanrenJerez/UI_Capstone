<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_unlocked();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        role TEXT NOT NULL,
        contact TEXT,
        created_at DATE DEFAULT (DATE('now'))
    )");
} catch (PDOException $e) {
    // handle error
}

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_staff'])) {
        $id = intval($_POST['staff_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        if (!empty($name) && !empty($role)) {
            if ($id > 0) {
                // Update staff
                $stmt = $pdo->prepare("UPDATE staff SET name = ?, role = ?, contact = ? WHERE id = ?");
                if ($stmt->execute([$name, $role, $contact, $id])) {
                    $message = "Staff member updated successfully!";
                } else {
                    $error = "Failed to update staff member.";
                }
            } else {
                // Insert new staff
                $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact) VALUES (?, ?, ?)");
                if ($stmt->execute([$name, $role, $contact])) {
                    $message = "Staff member added successfully!";
                } else {
                    $error = "Failed to add staff member.";
                }
            }
        } else {
            $error = "Name and Role are required fields.";
        }
    } elseif (isset($_POST['delete_staff_id'])) {
        $delId = intval($_POST['delete_staff_id']);
        $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$delId]);
        $message = "Staff member removed successfully.";
    }
}

// Fetch Staff List
$staff_members = [];
try {
    $stmt = $pdo->query("SELECT * FROM staff ORDER BY name ASC");
    if ($stmt) $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not fetch staff members.";
}

// Check if editing
$editData = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$editId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff & Roles | Project-E</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #0a0a0a; color: #ffffff; font-family: 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.1)), url('../assets/gym.jpg');
            background-attachment: fixed; background-size: cover; background-position: center;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 50px 20px 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .dashboard-title { color: #dc2626; margin: 0; font-size: 2rem; font-weight: 800; text-transform: uppercase; }
        .back-btn { background: #262626; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.85rem; border: 1px solid #404040; }
        
        .alert-success { background: rgba(22, 163, 74, 0.2); border: 1px solid #16a34a; color: #4ade80; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626; color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }

        .content-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; }
        @media (max-width: 768px) { .content-grid { grid-template-columns: 1fr; } }

        .glass-card { background: rgba(18, 18, 18, 0.95); padding: 25px; border-radius: 16px; border: 1px solid #262626; }
        .glass-card h3 { color: #dc2626; margin-top: 0; border-bottom: 1px solid #262626; padding-bottom: 10px; text-transform: uppercase; font-size: 1.1rem; }
        
        label { display: block; font-size: 0.8rem; color: #a3a3a3; margin-top: 12px; margin-bottom: 4px; text-transform: uppercase; font-weight: 700; }
        input { width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid #262626; border-radius: 8px; color: white; font-family: inherit; font-size: 0.9rem; }
        
        .btn-submit { width: 100%; padding: 12px; background: #dc2626; border: none; border-radius: 8px; color: white; font-weight: 700; cursor: pointer; text-transform: uppercase; margin-top: 20px; transition: 0.2s; }
        .btn-submit:hover { background: #b91c1c; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(220, 38, 38, 0.1); color: #ef4444; padding: 12px; text-align: left; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
        td { padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #d4d4d4; font-size: 0.9rem; }
        .badge-role { background: rgba(37, 99, 235, 0.15); color: #60a5fa; border: 1px solid rgba(37, 99, 235, 0.3); padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .btn-action-edit { background: #2563eb; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 0.75rem; font-weight: 700; margin-right: 4px; display: inline-block; }
        .btn-del { background: #7f1d1d; color: #fca5a5; border: 1px solid #991b1b; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; cursor: pointer; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <div class="page-header">
        <h1 class="dashboard-title">Staff & Role Manager</h1>
        <a href="/../pos/expenses.php" class="back-btn">💸 Go to Expenses</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="content-grid">
        <!-- Add / Edit Staff Form -->
        <div class="glass-card">
            <h3><?php echo $editData ? 'Edit Staff Member' : 'Add New Staff'; ?></h3>
            <form method="POST">
                <input type="hidden" name="save_staff" value="1">
                <?php if ($editData): ?>
                    <input type="hidden" name="staff_id" value="<?php echo $editData['id']; ?>">
                <?php endif; ?>

                <label>Full Name</label>
                <input type="text" name="name" required placeholder="e.g. Juan Dela Cruz" value="<?php echo htmlspecialchars($editData['name'] ?? ''); ?>">

                <label>Custom Role / Position</label>
                <input type="text" name="role" required placeholder="e.g. Front Desk, Gym Coach, Cleaner" value="<?php echo htmlspecialchars($editData['role'] ?? ''); ?>">

                <label>Contact Number (Optional)</label>
                <input type="text" name="contact" placeholder="09123456789" value="<?php echo htmlspecialchars($editData['contact'] ?? ''); ?>">

                <button type="submit" class="btn-submit"><?php echo $editData ? 'Update Staff' : '+ Save Staff'; ?></button>
                <?php if ($editData): ?>
                    <a href="staff.php" style="display:block; text-align:center; margin-top:10px; color:#a3a3a3; font-size:0.85rem; text-decoration:none;">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Staff List Table -->
        <div class="glass-card" style="padding: 20px; overflow-x: auto;">
            <h3>Staff Roster</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($staff_members)): ?>
                        <?php foreach ($staff_members as $member): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><span class="badge-role"><?php echo htmlspecialchars($member['role']); ?></span></td>
                                <td><?php echo htmlspecialchars($member['contact'] ?: 'N/A'); ?></td>
                                <td>
                                    <a href="staff.php?edit=<?php echo $member['id']; ?>" class="btn-action-edit">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Remove this staff member?');" style="display:inline;">
                                        <input type="hidden" name="delete_staff_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="btn-del">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; padding: 30px; color: #737373;">No staff added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>