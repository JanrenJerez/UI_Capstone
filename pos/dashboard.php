<?php
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['isAdminLoggedIn'])) {
    header("Location: ../login.php");
    exit();
}

$resMembers = $conn->query("SELECT COUNT(*) as total FROM members");
$totalMembers = ($resMembers && $resMembers->num_rows > 0) ? ($resMembers->fetch_assoc()['total'] ?? 0) : 0;

$resAttendance = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE DATE(scan_time) = CURDATE()");
$todayAttendance = ($resAttendance && $resAttendance->num_rows > 0) ? ($resAttendance->fetch_assoc()['total'] ?? 0) : 0;

$resSalesCount = $conn->query("SELECT COUNT(*) as total FROM sales WHERE status = 'Paid'");
$totalSalesCount = ($resSalesCount && $resSalesCount->num_rows > 0) ? ($resSalesCount->fetch_assoc()['total'] ?? 0) : 0;

$resSalesAmount = $conn->query("SELECT SUM(amount) as total FROM sales WHERE status = 'Paid'");
$totalSalesAmount = ($resSalesAmount && $resSalesAmount->num_rows > 0) ? ($resSalesAmount->fetch_assoc()['total'] ?? 0) : 0;

$resActive = $conn->query("SELECT COUNT(*) as total FROM members WHERE expiry_date >= CURDATE()");
$activeMembers = ($resActive && $resActive->num_rows > 0) ? ($resActive->fetch_assoc()['total'] ?? 0) : 0;

$resExpired = $conn->query("SELECT COUNT(*) as total FROM members WHERE expiry_date < CURDATE()");
$expiredMembers = ($resExpired && $resExpired->num_rows > 0) ? ($resExpired->fetch_assoc()['total'] ?? 0) : 0;

$activePercentage = $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100) : 0;
$expiredPercentage = $totalMembers > 0 ? round(($expiredMembers / $totalMembers) * 100) : 0;

// Analytics: Today's Day Passes vs Active Memberships
$resTodayDayPasses = $conn->query("SELECT SUM(si.qty) as total FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE s.status = 'Paid' AND DATE(s.sale_date) = CURDATE() AND si.product_id = 0");
$todayDayPasses = ($resTodayDayPasses && $resTodayDayPasses->num_rows > 0) ? ($resTodayDayPasses->fetch_assoc()['total'] ?? 0) : 0;
if ($todayDayPasses === null) $todayDayPasses = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Project E</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #0a0a0a; color: #ffffff; font-family: 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.1)), url('../assets/gym.jpg');
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
        .scanner-section { max-width: 1000px; margin: 0 auto; padding-bottom: 60px; }
        .scanner-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .page-title { color: #ffffff; background: #dc2626; padding: 8px 20px; border-radius: 6px; text-transform: uppercase; font-weight: 800; margin: 0; }
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
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <h1 class="dashboard-title">Admin Dashboard</h1>

    <div class="admin-grid">
        <div class="admin-card"><p>Total Members</p><h2><?php echo $totalMembers; ?></h2></div>
        <div class="admin-card"><p>Attendance Today</p><h2><?php echo $todayAttendance; ?></h2></div>
        <div class="admin-card"><p>Total POS Orders</p><h2><?php echo $totalSalesCount; ?></h2></div>
        <div class="admin-card"><p>Total Revenue</p><h2>₱<?php echo number_format($totalSalesAmount, 2); ?></h2></div>
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
                <p style="color: #a3a3a3; font-size: 0.9rem; margin-bottom: 20px;">Manage active user passes, process walk-ins, or register members.</p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="../admin/Manage.php" style="padding: 10px 18px; background: #ff0000; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">Manage Members</a>
                    <a href="../pos/sales.php" style="padding: 10px 18px; background: #fa0000; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">Point of Sales</a>
                    <button type="button" onclick="openModal('walkInModal')" style="padding: 10px 18px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">⚡ Walk-In Day Pass</button>
                    <a href="../admin/register.php" style="padding: 10px 18px; background: #262626; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem; border: 1px solid #404040;">+ Register</a>
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
            <img src="../assets/gcash.jpg" style="width: 200px; height: 200px; object-fit: contain; display: block;">
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

        let dayPassItem = [{ id: 0, name: 'Day Pass', qty: 1, price: price }];

        let formData = new FormData();
        formData.append('ajax_sale', '1');
        formData.append('total_amount', price.toFixed(2));
        formData.append('payment_method', paymentMethod);
        formData.append('customer_name', customerName + ' (Day Pass)');
        formData.append('items_json', JSON.stringify(dayPassItem));

        fetch('sales.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if(data.trim() === "success") {
                alert("Day Pass issued successfully via " + paymentMethod + "!");
                location.reload(); 
            } else {
                alert("Transaction failed.");
            }
        });
    }

    // QR scanner functionality
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

    scannerToggle.addEventListener("change", async function () {
        if (this.checked) { toggleText.innerText = "Starting..."; await startScanner(); } 
        else { await stopScanner(); toggleText.innerText = "Scanner Off"; resultDiv.innerText = "Scanner is turned off."; }
    });

    function onScanSuccess(decodedText) {
        if (isProcessing || !scannerRunning) return;
        isProcessing = true; html5QrCode.pause(true);
        resultDiv.innerText = "Verifying...";

        let formData = new FormData();
        formData.append('qr_id', decodedText);

        fetch('check_member.php', { method: 'POST', body: formData })
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
        entry.innerHTML = `<span>${name}</span><span>${time}</span>`;
        historyContainer.insertBefore(entry, historyContainer.firstChild);
    }
</script>
</body>
</html>