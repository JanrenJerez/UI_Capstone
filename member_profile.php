<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_unlocked();

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: /admin/manage.php");
    exit();
}

$isExpired = $member['expiry_date'] < date('Y-m-d');

$attStmt = $conn->prepare("SELECT scan_time FROM attendance WHERE member_id = ? ORDER BY scan_time DESC LIMIT 5");
$attStmt->execute([$id]);
$attendanceLogs = $attStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($member['name']); ?> | Profile</title>
    <script src="/assets/vendor/qrcode.min.js"></script>
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
        .btn { display: block; text-decoration: none; margin-top: 12px; padding: 10px; border-radius: 8px; font-weight: bold; border: none; width: 100%; cursor: pointer; font-size: 0.95rem; }
        .btn-update { background: #38bdf8; color: #0f172a; }
        .btn-print { background: #0f172a; color: #fff; }
        .qr-code-text { font-family: monospace; color: #64748b; font-size: 0.85rem; letter-spacing: 1px; }
        @media (max-width: 700px) {
            .profile-container { grid-template-columns: 1fr; margin: 25px auto; }
        }
        @media print {
            .pe-nav, .history-card, .no-print { display: none !important; }
            body { background: white; }
            .profile-container { display: block; margin: 0; }
            .glass-card { box-shadow: none; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>

<div class="profile-container">
    <div class="glass-card">
        <div id="qrcode" style="display: flex; justify-content: center; margin-bottom: 10px;"></div>
        <div class="qr-code-text"><?php echo e($member['qr_id']); ?></div>
        <h2 style="margin: 10px 0;"><?php echo e($member['name']); ?></h2>
        <p style="color: #64748b; font-size: 0.9rem;">Member #<?php echo e($member['id']); ?> · <?php echo e($member['tier']); ?></p>

        <span class="badge <?php echo $isExpired ? 'badge-expired' : 'badge-active'; ?>"><?php echo $isExpired ? 'EXPIRED' : 'ACTIVE'; ?></span>

        <div class="stats-grid">
            <div class="stat-box">
                <small>Expiry Date</small>
                <span><?php echo date('M d, Y', strtotime($member['expiry_date'])); ?></span>
            </div>
            <div class="stat-box">
                <small>Join Date</small>
                <span><?php echo $member['created_at'] ? date('M d, Y', strtotime($member['created_at'])) : '-'; ?></span>
            </div>
        </div>
        <button type="button" class="btn btn-print no-print" onclick="window.print()">🖨 Print QR Card</button>
        <a href="/admin/log.php?id=<?php echo $member['id']; ?>" class="btn btn-update no-print">Update Membership</a>
    </div>

    <div class="history-card">
        <h3 style="color: #38bdf8; margin-top: 0;">Recent Activity</h3>
        <p style="color: #94a3b8; font-size: 0.85rem;">Member check-in history</p>

        <?php if (!empty($attendanceLogs)): ?>
            <?php foreach ($attendanceLogs as $row): ?>
                <div class="history-item">
                    <span>Check-in Recorded</span>
                    <span style="color: #38bdf8;"><?php echo date('M d, g:i A', strtotime($row['scan_time'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="history-item">
                <span style="color: #94a3b8;">No recent activity</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    new QRCode(document.getElementById("qrcode"), {
        text: <?php echo json_encode($member['qr_id']); ?>,
        width: 180, height: 180, colorDark: "#0f172a"
    });
</script>
</body>
</html>
