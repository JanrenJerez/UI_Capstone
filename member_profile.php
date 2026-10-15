<?php
session_start();
include __DIR__ . '/includes/db.php';
include __DIR__ . '/includes/nav.php'; 

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'];

try {
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) { 
        die("Member not found."); 
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($member['name'] ?? ''); ?> | Profile</title>
    <script src="assets/vendor/qrcode.min.js"></script>
    <style>
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .profile-container { max-width: 900px; margin: 50px auto; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; padding: 0 20px; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); color: #1e293b; padding: 30px; border-radius: 20px; text-align: center; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .stat-box { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .stat-box small { color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; }
        .stat-box span { font-weight: 800; color: #0f172a; font-size: 1.1rem; }
        .history-card { background: rgba(15, 23, 42, 0.9); border: 1px solid #334155; padding: 25px; border-radius: 20px; }
        .history-item { border-bottom: 1px solid #1e293b; padding: 12px 0; display: flex; justify-content: space-between; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-expired { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="profile-container">
    <div class="glass-card">
        <div id="qrcode" style="display: flex; justify-content: center; margin-bottom: 20px;"></div>
        <h2 style="margin: 10px 0;"><?php echo htmlspecialchars($member['name'] ?? ''); ?></h2>
        <p style="color: #64748b; font-size: 0.9rem;">ID: <?php echo htmlspecialchars($member['id'] ?? ''); ?></p>
        
        <?php 
        $isExpired = strtotime($member['expiry_date']) < time();
        $statusClass = $isExpired ? 'badge-expired' : 'badge-active';
        $statusText = $isExpired ? 'EXPIRED' : 'ACTIVE';
        ?>
        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>

        <div class="stats-grid">
            <div class="stat-box">
                <small>Expiry Date</small>
                <span><?php echo date('M d, Y', strtotime($member['expiry_date'])); ?></span>
            </div>
            <div class="stat-box">
                <small>Join Date</small>
                <span><?php echo date('M d, Y', strtotime($member['created_at'] ?? 'now')); ?></span>
            </div>
        </div>
        <a href="edit_members.php?id=<?php echo $member['id']; ?>" class="btn-update" style="display:block; text-decoration:none; margin-top:20px; background:#38bdf8; color:#0f172a; padding:10px; border-radius:8px; font-weight:bold;">Update Membership</a>
    </div>

    <div class="history-card">
        <h3 style="color: #38bdf8; margin-top: 0;">Recent Activity</h3>
        <p style="color: #94a3b8; font-size: 0.85rem;">Member check-in history</p>
        
        <?php
        $attStmt = $conn->prepare("SELECT scan_time FROM attendance WHERE member_id = ? ORDER BY scan_time DESC LIMIT 5");
        $attStmt->execute([$id]);
        $attendanceLogs = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($attendanceLogs)):
            foreach($attendanceLogs as $row): ?>
                <div class="history-item">
                    <span>Check-in Recorded</span>
                    <span style="color: #38bdf8;"><?php echo date('M d, g:i A', strtotime($row['scan_time'])); ?></span>
                </div>
            <?php endforeach;
        else: ?>
            <div class="history-item">
                <span style="color: #94a3b8;">No recent activity</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    new QRCode(document.getElementById("qrcode"), {
        text: "<?php echo $member['qr_code']; ?>",
        width: 150, height: 150, colorDark : "#0f172a"
    });
</script>
</body>
</html>