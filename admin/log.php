<?php
session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/nav.php'; 

if (!isset($_SESSION['isAdminLoggedIn'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_POST['clear_logs'])) {
    $conn->query("TRUNCATE TABLE attendance");
    header("Location: log.php");
    exit();
}

$sql = "SELECT attendance.scan_time, members.id, members.username 
        FROM attendance 
        JOIN members ON attendance.member_id = members.id 
        ORDER BY attendance.scan_time DESC";

$logs = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Log | Project E</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container container-wide">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Daily Attendance Log</h1>
        <form method="POST" onsubmit="return confirm('Are you sure you want to delete all attendance history?')">
            <button type="submit" name="clear_logs" class="btn-delete" style="width: auto; margin-top: 0;">
                Clear All Logs
            </button>
        </form>
    </div>
    
    <div class="controls">
        <input type="text" id="logSearch" placeholder="Search by name or ID..." onkeyup="filterLogs()">
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Scan Time</th>
                    <th>Member ID</th>
                    <th>Member Name</th>
                </tr>
            </thead>
            <tbody id="attendanceBody">
                <?php if ($logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y - h:i A', strtotime($row['scan_time'])); ?></td>
                            <td><code><?php echo $row['id']; ?></code></td>
                            <td><strong><?php echo $row['username']; ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 20px;">No attendance recorded yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterLogs() {
    let input = document.getElementById("logSearch").value.toLowerCase();
    let rows = document.querySelectorAll("#attendanceBody tr");

    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
