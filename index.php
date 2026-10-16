<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_unlocked();

if (isset($_POST['delete_transaction_id'])) {
    $delId = intval($_POST['delete_transaction_id']);
    $delSource = $_POST['delete_source_type'] ?? 'POS';
    
    if ($delSource === 'POS') {
        $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$delId]);
        $pdo->prepare("DELETE FROM walkin_visits WHERE sale_id = ?")->execute([$delId]);
        $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$delId]);
    } else {
        delete_member($pdo, $delId);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['delete_all_history'])) {
    $pdo->exec("DELETE FROM sale_items");
    $pdo->exec("DELETE FROM sales");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$totalMembers = 0;
$todayAttendance = 0;
$totalSalesCount = 0;
$todayRevenue = 0;
$monthRevenue = 0;
$monthExpenses = 0;
$netIncome = 0;
$activeMembers = 0;
$expiredMembers = 0;
$activePercentage = 0;
$expiredPercentage = 0;
$todayDayPasses = 0;
$history_rows = [];
$historyTotal = 0;
$perPage = 25;
$page = 1;

$filter = $_GET['history_filter'] ?? 'today';
$dateConditionSales = "";
$dateConditionMembers = "";

if ($filter === 'today') {
    $dateConditionSales = "AND DATE(s.sale_date) = DATE('now', 'localtime')";
    $dateConditionMembers = "AND DATE(m.created_at) = DATE('now', 'localtime')";
} elseif ($filter === 'month') {
    $dateConditionSales = "AND strftime('%Y-%m', s.sale_date) = strftime('%Y-%m', 'now', 'localtime')";
    $dateConditionMembers = "AND strftime('%Y-%m', m.created_at) = strftime('%Y-%m', 'now', 'localtime')";
}

try {
    $stmtMembers = $pdo->query("SELECT COUNT(*) FROM members");
    if ($stmtMembers) $totalMembers = $stmtMembers->fetchColumn();

    // Member check-ins plus walk-in day pass visits
    $todayAttendance = $pdo->query("
        SELECT (SELECT COUNT(*) FROM attendance WHERE DATE(scan_time) = DATE('now', 'localtime'))
             + (SELECT COUNT(*) FROM walkin_visits WHERE DATE(visit_time) = DATE('now', 'localtime'))
    ")->fetchColumn();

    $stmtSalesCount = $pdo->query("SELECT COUNT(*) FROM sales WHERE status = 'Paid'");
    if ($stmtSalesCount) $totalSalesCount = $stmtSalesCount->fetchColumn();

    $stmtTodaySalesRev = $pdo->query("SELECT SUM(amount) FROM sales WHERE status = 'Paid' AND DATE(sale_date) = DATE('now', 'localtime')");
    $todaySalesRev = $stmtTodaySalesRev ? ($stmtTodaySalesRev->fetchColumn() ?? 0) : 0;

    $stmtTodayMemberRev = $pdo->query("SELECT SUM(final_rate) FROM members WHERE DATE(created_at) = DATE('now', 'localtime')");
    $todayMemberRev = $stmtTodayMemberRev ? ($stmtTodayMemberRev->fetchColumn() ?? 0) : 0;

    $todayRevenue = $todaySalesRev + $todayMemberRev;

    $stmtMonthSalesRev = $pdo->query("SELECT SUM(amount) FROM sales WHERE status = 'Paid' AND strftime('%Y-%m', sale_date) = strftime('%Y-%m', 'now', 'localtime')");
    $monthSalesRev = $stmtMonthSalesRev ? ($stmtMonthSalesRev->fetchColumn() ?? 0) : 0;

    $stmtMonthMemberRev = $pdo->query("SELECT SUM(final_rate) FROM members WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'localtime')");
    $monthMemberRev = $stmtMonthMemberRev ? ($stmtMonthMemberRev->fetchColumn() ?? 0) : 0;

    $monthRevenue = $monthSalesRev + $monthMemberRev;

    $checkExpTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='expenses'");
    if ($checkExpTable && $checkExpTable->fetch()) {
        $stmtMonthExp = $pdo->query("SELECT SUM(amount) FROM expenses WHERE strftime('%Y-%m', expense_date) = strftime('%Y-%m', 'now', 'localtime')");
        $monthExpenses = $stmtMonthExp ? ($stmtMonthExp->fetchColumn() ?? 0) : 0;
    }

    $netIncome = $monthRevenue - $monthExpenses;

    $stmtActive = $pdo->query("SELECT COUNT(*) FROM members WHERE expiry_date >= DATE('now', 'localtime')");
    if ($stmtActive) $activeMembers = $stmtActive->fetchColumn();

    $stmtExpired = $pdo->query("SELECT COUNT(*) FROM members WHERE expiry_date < DATE('now', 'localtime')");
    if ($stmtExpired) $expiredMembers = $stmtExpired->fetchColumn();

    $activePercentage = $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100) : 0;
    $expiredPercentage = $totalMembers > 0 ? round(($expiredMembers / $totalMembers) * 100) : 0;

    $stmtTodayDayPasses = $pdo->query("
        SELECT COALESCE(SUM(si.qty), 0) 
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        WHERE s.status = 'Paid' 
          AND DATE(s.sale_date) = DATE('now', 'localtime') 
          AND s.customer_name LIKE '%(Day Pass)%'
    ");
    if ($stmtTodayDayPasses) $todayDayPasses = $stmtTodayDayPasses->fetchColumn();

    $perPage = 25;
    $page = current_page();
    $historySql = sales_history_sql($dateConditionSales, $dateConditionMembers);
    $historyTotal = (int) $pdo->query("SELECT COUNT(*) FROM ($historySql)")->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $history_rows = $pdo->query("$historySql ORDER BY date_recorded DESC LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Project-E</title>
    <script src="assets/vendor/html5-qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #0a0a0a; color: #ffffff; font-family: 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.1)), url('/assets/gym.jpg');
            background-attachment: fixed; background-size: cover; background-position: center;
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 50px 20px 30px; }
        .dashboard-title { color: #dc2626; margin-bottom: 35px; font-size: 2rem; font-weight: 800; text-transform: uppercase; }
        .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .admin-card { background: rgba(26, 26, 26, 0.95); padding: 25px; border-radius: 16px; text-align: center; border: 1px solid #262626; }
        .admin-card p { color: #a3a3a3; text-transform: uppercase; font-size: 0.8rem; font-weight: 700; margin: 0 0 10px; }
        .admin-card h2 { font-size: 2rem; margin: 0; }
        .analysis-section { margin-bottom: 40px; }
        .analysis-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .analysis-card { background: rgba(18, 18, 18, 0.95); border: 1px solid #262626; padding: 25px; border-radius: 16px; }
        .analysis-card h3 { color: #dc2626; margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #262626; padding-bottom: 10px; text-transform: uppercase; }
        .stat-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 600; }
        .progress-bar-bg { background: #262626; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 15px; }
        .progress-bar-fill { height: 100%; border-radius: 5px; }
        .fill-active { background: #ffffff; }
        .fill-expired { background: #dc2626; }
        .fill-passes { background: #16a34a; }
        .scanner-section { max-width: 1100px; margin: 0 auto; padding-bottom: 60px; }
        
        .scanner-header { 
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; 
        }
        .page-title { color: #ffffff; background: #dc2626; padding: 8px 20px; border-radius: 6px; text-transform: uppercase; font-weight: 800; margin: 0; font-size: 1.1rem; white-space: nowrap; }
        
        .scanner-toggle { display: flex; align-items: center; gap: 10px; font-weight: 700; }
        .switch { position: relative; width: 52px; height: 28px; display: inline-block; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; inset: 0; background: #404040; border-radius: 30px; cursor: pointer; transition: 0.3s; }
        .slider:before { content: ""; position: absolute; width: 22px; height: 22px; left: 3px; top: 3px; background: white; border-radius: 50%; transition: 0.3s; }
        .switch input:checked + .slider { background: #dc2626; }
        .switch input:checked + .slider:before { transform: translateX(24px); }
        .scanner-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; }
        .glass-card { background: rgba(18, 18, 18, 0.95); padding: 30px; border-radius: 16px; border: 1px solid #262626; }
        #reader { width: 100%; border-radius: 12px; overflow: hidden; border: 3px solid #dc2626 !important; background: #000; }
        #result { margin-top: 15px; padding: 15px; border-radius: 10px; font-weight: 700; text-align: center; border: 1px solid #262626; background: #18181b; color: #a1a1aa; }
        .history-card { background: rgba(18, 18, 18, 0.95); border: 1px solid #262626; padding: 20px; border-radius: 16px; }
        .history-card h3 { color: #dc2626; margin-top: 0; border-bottom: 1px solid #262626; padding-bottom: 10px; text-transform: uppercase; }
        .scan-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #262626; font-size: 0.9rem; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(6px); }
        .modal-content { background: #141414; margin: 6% auto; padding: 25px; width: 90%; max-width: 420px; border-radius: 16px; color: #ffffff; border: 1px solid #262626; text-align: center; }
        .modal-content label { display: block; font-size: 0.8rem; color: #a3a3a3; margin-top: 10px; text-align: left; }
        .modal-content input { width: 100%; padding: 10px; margin: 6px 0 12px 0; background: #0a0a0a; border: 1px solid #262626; border-radius: 8px; color: white; }
        .btn-checkout { width: 100%; padding: 12px; background: #16a34a; border: none; border-radius: 10px; color: white; font-weight: 700; cursor: pointer; text-transform: uppercase; }
        .btn-gcash { width: 100%; padding: 12px; background: #262626; border: 1px solid #404040; border-radius: 10px; color: #ffffff; font-weight: 600; cursor: pointer; margin-top: 8px; }
        .history-table th { background: rgba(220, 38, 38, 0.1); color: #ef4444; padding: 14px; text-align: left; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
        .history-table td { padding: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #d4d4d4; font-size: 0.9rem; }
        .badge-member { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; margin-top: 4px; }
        .filter-btn { padding: 6px 14px; background: #262626; color: #a3a3a3; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.8rem; border: 1px solid #404040; }
        .filter-btn.active { background: #dc2626; color: white; border-color: #dc2626; }
        .btn-action-edit { background: #2563eb; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.75rem; font-weight: 700; margin-right: 4px; display: inline-block; }
        .btn-action-del { background: #dc2626; color: white; padding: 5px 10px; border-radius: 4px; border: none; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        @media (max-width: 800px) {
            .container { padding: 30px 14px 20px; }
            .dashboard-title { font-size: 1.5rem; }
            .scanner-grid { grid-template-columns: 1fr; }
            .analysis-grid { grid-template-columns: 1fr; }
            .glass-card { padding: 18px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/nav.php'; ?>

<div class="container">
    <h1 class="dashboard-title">Admin Dashboard</h1>

    <div class="admin-grid">
        <div class="admin-card"><p>Total Members</p><h2><?php echo $totalMembers; ?></h2></div>
        <div class="admin-card"><p>Attendance Today</p><h2><?php echo $todayAttendance; ?></h2></div>
        <div class="admin-card"><p>Revenue Today</p><h2 style="color: #22c55e;">₱<?php echo number_format($todayRevenue, 2); ?></h2></div>
        <div class="admin-card"><p>Revenue this Month</p><h2 style="color: #3b82f6;">₱<?php echo number_format($monthRevenue, 2); ?></h2></div>
        <div class="admin-card"><p>Expenses this Month</p><h2 style="color: #ef4444;">₱<?php echo number_format($monthExpenses, 2); ?></h2></div>
        <div class="admin-card"><p>Net Income this Month</p><h2 style="color: <?php echo ($netIncome >= 0) ? '#22c55e' : '#ef4444'; ?>;">₱<?php echo number_format($netIncome, 2); ?></h2></div>
    </div>

    <div class="analysis-section">
        <div class="analysis-grid">
            <div class="analysis-card">
                <h3>Membership & Traffic Analytics</h3>
                <div class="stat-row"><span>Active Members</span><span><?php echo $activeMembers; ?> (<?php echo $activePercentage; ?>%)</span></div>
                <div class="progress-bar-bg"><div class="progress-bar-fill fill-active" style="width: <?php echo $activePercentage; ?>%;"></div></div>
                
                <div class="stat-row"><span style="color: #ef4444;">Expired Members</span><span><?php echo $expiredMembers; ?> (<?php echo $expiredPercentage; ?>%)</span></div>
                <div class="progress-bar-bg"><div class="progress-bar-fill fill-expired" style="width: <?php echo $expiredPercentage; ?>%;"></div></div>

                <div class="stat-row"><span style="color: #22c55e;">Day Passes Sold Today</span><span><?php echo $todayDayPasses; ?></span></div>
                <div class="progress-bar-bg"><div class="progress-bar-fill fill-passes" style="width: <?php echo min(100, $todayDayPasses * 10); ?>%;"></div></div>
            </div>

            <div class="analysis-card">
                <h3>Quick Links</h3>
                <p style="color: #a3a3a3; font-size: 0.9rem; margin-bottom: 20px;">Manage finances, active user passes, or register members.</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="/pos/expenses.php" style="padding: 10px 18px; background: #eab308; color: #000; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">💸 Manage Expenses</a>
                    <a href="/admin/manage.php" style="padding: 10px 18px; background: #ff0000; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">👨 Manage Members</a>
                    <a href="/pos/sales.php" style="padding: 10px 18px; background: #fa0000; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">💲Point of Sales</a>
                    <button type="button" onclick="openModal('walkInModal')" style="padding: 10px 18px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">⚡ Walk-In Day Pass</button>
                    <a href="/admin/settings.php" style="padding: 10px 18px; background: #4b5563; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">🔒 Change PIN</a>
                </div>
            </div>
        </div>
    </div>

    <div class="scanner-section">
        <div class="scanner-header">
            <h1 class="page-title">Live Attendance</h1>
            <div class="scanner-toggle">
                <span id="toggleText">Scanner Off</span>
                <label class="switch"><input type="checkbox" id="scannerToggle"><span class="slider"></span></label>
            </div>
        </div>
        <div class="scanner-grid">
            <div class="glass-card">
                <p style="color: #a3a3a3; font-size: 0.8rem; margin-bottom: 15px; font-weight: 700;">SCAN MEMBER QR CODE</p>
                <div id="reader"></div>
                <div id="result">Scanner is turned off.</div>
            </div>
            <div class="history-card">
                <h3>Recent Scans</h3>
                <div id="scan-history"><div style="color: #525252; font-style: italic;">No scans in this session...</div></div>
            </div>
        </div>
    </div>

    <div class="scanner-section" style="margin-top: 40px;">
        <div class="scanner-header">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <h1 class="page-title">POS History</h1>
                <a href="?history_filter=today" class="filter-btn <?php echo ($filter === 'today') ? 'active' : ''; ?>">Today</a>
                <a href="?history_filter=month" class="filter-btn <?php echo ($filter === 'month') ? 'active' : ''; ?>">This Month</a>
                <a href="?history_filter=all" class="filter-btn <?php echo ($filter === 'all') ? 'active' : ''; ?>">Overall</a>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="/pos/sales.php?export=csv" style="background: #2563eb; color: white; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.85rem;">📊 Export to Excel (CSV)</a>
                <?php if (!empty($history_rows)): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL POS sales history records?');" style="display:inline;">
                        <input type="hidden" name="delete_all_history" value="1">
                        <button type="submit" style="background: #7f1d1d; color: #fca5a5; border: 1px solid #991b1b; padding: 8px 14px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">🗑️ Delete All</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="glass-card" style="padding: 0; overflow-x: auto;">
            <table class="history-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Date</th>
                        <th>Items Bought / Description</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($history_rows)): ?>
                        <?php foreach ($history_rows as $row): ?>
                            <?php $is_member_rec = ($row['source_type'] === 'MEMBERSHIP'); ?>
                            <tr>
                                <td>
                                    #<?php echo $row['transaction_id']; ?>
                                    <?php if ($is_member_rec): ?><br><span class="badge-member">MEMBER</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['date_recorded'])); ?></td>
                                <td><?php echo htmlspecialchars($row['items_list']); ?></td>
                                <td style="font-weight:700;">₱<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td style="color: #22c55e; font-weight: 600;"><?php echo htmlspecialchars($row['status']); ?></td>
                                <td>
                                    <?php if (!$is_member_rec): ?>
                                        <a href="/pos/sales.php" class="btn-action-edit">Edit</a>
                                    <?php else: ?>
                                        <a href="/admin/manage.php?q=<?php echo urlencode($row['customer_name']); ?>" class="btn-action-edit" style="background:#4b5563;">Manage</a>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Delete this record?');" style="display:inline;">
                                        <input type="hidden" name="delete_transaction_id" value="<?php echo $row['transaction_id']; ?>">
                                        <input type="hidden" name="delete_source_type" value="<?php echo $row['source_type']; ?>">
                                        <button type="submit" class="btn-action-del">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align: center; padding: 30px; color: #737373;">No transactions recorded for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php echo render_pagination($historyTotal, $perPage, $page); ?>
        </div>
    </div>
</div>

<div id="walkInModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#16a34a; text-align: left;">Walk-In Day Pass</h3>
        <label>Customer Name</label>
        <input type="text" id="walkInCustomer" value="Walk-in Guest">
        <label>Day Pass Price (₱)</label>
        <input type="number" id="walkInPrice" value="100.00" step="100.00">
        <button class="btn-checkout" onclick="processWalkIn('Cash')">Pay Cash</button>
        <button class="btn-gcash" onclick="openWalkInGcash()">Pay via GCash</button>
        <button type="button" onclick="closeModal('walkInModal')" style="width:100%; background:none; border:none; margin-top:10px; color:#737373; cursor:pointer;">Cancel</button>
    </div>
</div>

<div id="gcashModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top: 0;">Scan GCash QR Code</h3>
        <p style="color: #a3a3a3; font-size: 0.9rem;">Amount: <b style="color: #ef4444;">₱<span id="gcashAmount">0.00</span></b></p>
        <div style="background: white; padding: 15px; border-radius: 12px; display: inline-block; margin-bottom: 15px;">
            <img src="/assets/gcash.jpg" style="width: 200px; height: 200px; object-fit: contain; display: block;">
        </div>
        <button class="btn-checkout" style="background:#007dfe;" onclick="confirmWalkInGcash()">Confirm Payment</button>
        <button type="button" onclick="closeModal('gcashModal')" style="width:100%; background:none; border:none; margin-top:10px; color:#737373; cursor:pointer;">Cancel</button>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'block'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function openWalkInGcash() {
        let price = parseFloat(document.getElementById('walkInPrice').value) || 0;
        if (price <= 0) return alert("Enter valid price.");
        document.getElementById('gcashAmount').innerText = price.toFixed(2);
        closeModal('walkInModal');
        openModal('gcashModal');
    }

    function confirmWalkInGcash() {
        closeModal('gcashModal');
        processWalkIn('GCash');
    }

    function processWalkIn(paymentMethod) {
        let customerName = document.getElementById('walkInCustomer').value.trim() || 'Walk-in Guest';
        let price = parseFloat(document.getElementById('walkInPrice').value) || 0;
        if (price <= 0) return alert("Enter valid amount.");

        let calculatedQty = Math.floor(price / 100) || 1;
        let dayPassItem = [{ id: 0, product_id: 0, name: 'Day Pass', qty: calculatedQty, price: price / calculatedQty }];

        let formData = new FormData();
        formData.append('ajax_sale', '1');
        formData.append('total_amount', price.toFixed(2));
        formData.append('payment_method', paymentMethod);
        formData.append('customer_name', customerName + ' (Day Pass)');
        formData.append('items_json', JSON.stringify(dayPassItem));
        formData.append('day_pass', '1');

        fetch('/pos/sales.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                alert("Day Pass (" + calculatedQty + "x) transaction processed successfully via " + paymentMethod + "!");
                window.location.href = window.location.pathname;
            } else {
                alert(data.trim() || "Transaction failed.");
            }
        })
        .catch(() => alert("Transaction failed. Please try again."));
    }

    const resultDiv = document.getElementById('result');
    const historyContainer = document.getElementById('scan-history');
    const scannerToggle = document.getElementById('scannerToggle');
    const toggleText = document.getElementById('toggleText');
    let html5QrCode, isProcessing = false, scannerRunning = false;

    document.addEventListener("DOMContentLoaded", () => { html5QrCode = new Html5Qrcode("reader"); });

    async function startScanner() {
        if (scannerRunning) return;
        try {
            await html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess);
            scannerRunning = true; scannerToggle.checked = true; toggleText.innerText = "Scanner On";
            resultDiv.innerText = "Ready to scan...";
        } catch (err) {
            scannerRunning = false; scannerToggle.checked = false; toggleText.innerText = "Scanner Off";
            resultDiv.innerText = "Camera unavailable.";
        }
    }

    async function stopScanner() {
        if (!scannerRunning) return;
        try { await html5QrCode.stop(); scannerRunning = false; } catch (err) {}
    }

    if (scannerToggle) {
        scannerToggle.addEventListener("change", async function () {
            if (this.checked) { toggleText.innerText = "Starting..."; await startScanner(); } 
            else { await stopScanner(); toggleText.innerText = "Scanner Off"; resultDiv.innerText = "Scanner is turned off."; }
        });
    }

    function onScanSuccess(decodedText) {
        if (isProcessing || !scannerRunning) return;
        isProcessing = true; html5QrCode.pause(true);
        resultDiv.innerText = "Verifying...";

        let formData = new FormData();
        formData.append('qr_id', decodedText);

        fetch('/pos/check_member.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                resultDiv.innerText = "✔ " + data.member_name;
                addToHistory(data.member_name);
            } else {
                resultDiv.innerText = "✖ " + data.message;
            }
            setTimeout(() => {
                isProcessing = false;
                if (scannerToggle.checked && scannerRunning) {
                    resultDiv.innerText = "Ready to scan...";
                    html5QrCode.resume();
                }
            }, 2500);
        }).catch(() => {
            isProcessing = false;
            if (scannerToggle.checked && scannerRunning) html5QrCode.resume();
        });
    }

    function addToHistory(name) {
        let now = new Date();
        let time = now.toLocaleTimeString();
        if (historyContainer.innerText.includes("No scans")) historyContainer.innerHTML = "";
        let entry = document.createElement('div');
        entry.className = 'scan-item';
        const nameEl = document.createElement('span'); nameEl.textContent = name;
        const timeEl = document.createElement('span'); timeEl.textContent = time;
        entry.append(nameEl, timeEl);
        historyContainer.insertBefore(entry, historyContainer.firstChild);
    }

</script>
</body>
</html>