<?php
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_unlocked();

$error = "";
$successMsg = "";
$newMemberCreated = false;
$registeredData = [];
$qrMember = null; // member whose QR is shown after an import or edit

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_member_id'])) {
    delete_member($pdo, intval($_POST['delete_member_id']));
    header("Location: manage.php?deleted=1");
    exit();
}

// Shared validation for the register, legacy import and edit forms
function read_member_form($uppercase) {
    $name = trim($_POST['memberName'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $tier = $_POST['tier'] ?? 'GYM USE - REGULAR';
    if ($uppercase) {
        $name = strtoupper($name);
        $address = strtoupper($address);
        $tier = strtoupper($tier);
    }
    $baseRate = floatval($_POST['baseRate'] ?? 0);
    $discountAmount = floatval($_POST['discountAmount'] ?? 0);
    return [
        'name' => $name,
        'contact' => trim($_POST['contact'] ?? ''),
        'address' => $address,
        'expiry_date' => $_POST['expiryDate'] ?? '',
        'tier' => $tier,
        'base_rate' => $baseRate,
        'discount_amount' => $discountAmount,
        'final_rate' => max(0, $baseRate - $discountAmount),
    ];
}

function member_form_error($m) {
    if (!preg_match('/^[0-9]{11}$/', $m['contact'])) return "CONTACT NUMBER MUST CONTAIN EXACTLY 11 DIGITS.";
    if ($m['name'] === '' || $m['address'] === '' || $m['expiry_date'] === '') return "PLEASE FILL IN ALL REQUIRED FIELDS.";
    return '';
}

function insert_member(PDO $pdo, array $m) {
    $m['qr_id'] = generate_qr_id($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO members
        (qr_id, name, contact, address, expiry_date, tier, membership_type, base_rate, discount_amount, final_rate, created_at)
        VALUES (:qr_id, :name, :contact, :address, :expiry_date, :tier, :tier, :base_rate, :discount_amount, :final_rate, datetime('now', 'localtime'))
    ");
    $stmt->execute($m);
    $m['id'] = $pdo->lastInsertId();
    return $m;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['delete_member_id'])) {

    if (!empty($_POST['edit_member_id'])) {
        $id = intval($_POST['edit_member_id']);
        $m = read_member_form(false);
        $error = member_form_error($m);

        if (!$error) {
            try {
                $updateStmt = $pdo->prepare("
                    UPDATE members
                    SET name = ?, contact = ?, address = ?, expiry_date = ?, tier = ?, membership_type = ?, base_rate = ?, discount_amount = ?, final_rate = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $m['name'], $m['contact'], $m['address'], $m['expiry_date'], $m['tier'], $m['tier'],
                    $m['base_rate'], $m['discount_amount'], $m['final_rate'], $id
                ]);
                $successMsg = "Member record updated successfully!";

                $q = $pdo->prepare("SELECT id, name, qr_id, tier, expiry_date FROM members WHERE id = ?");
                $q->execute([$id]);
                $qrMember = $q->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Member update failed: " . $e->getMessage());
                $error = "Could not save the member. Please try again.";
            }
        }
    } elseif (($_POST['form_type'] ?? '') === 'legacy') {
        $m = read_member_form(true);
        $error = member_form_error($m);

        if (!$error) {
            try {
                $qrMember = insert_member($pdo, $m);
                $successMsg = "Existing member successfully imported into the system!";
            } catch (PDOException $e) {
                error_log("Legacy import failed: " . $e->getMessage());
                $error = "Could not save the member. Please try again.";
            }
        }
    } else {
        $m = read_member_form(true);
        $error = member_form_error($m);

        if (!$error) {
            try {
                $registeredData = insert_member($pdo, $m);
                $newMemberCreated = true;
            } catch (PDOException $e) {
                error_log("Registration failed: " . $e->getMessage());
                $error = "Could not save the member. Please try again.";
            }
        }
    }
}

if (isset($_GET['deleted'])) {
    $successMsg = "Member deleted.";
}

// Member list: server-side search + pagination
$search = trim($_GET['q'] ?? '');
$perPage = 20;
$page = current_page();
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE name LIKE :q OR contact LIKE :q OR address LIKE :q OR qr_id LIKE :q OR tier LIKE :q OR CAST(id AS TEXT) = :exact";
    $params = ['q' => '%' . $search . '%', 'exact' => ltrim($search, '#')];
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM members $where");
$countStmt->execute($params);
$memberTotal = (int) $countStmt->fetchColumn();

$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT * FROM members $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management Dashboard | Project E</title>
    <script src="/assets/vendor/qrcode.min.js"></script>
    
    <style>
        * { box-sizing: border-box; }

        body {
            background: #0a0a0a;
            color: #ffffff;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            background-image:
                linear-gradient(rgba(10, 10, 10, 0.92), rgba(10, 10, 10, 0.92)),
                url('/assets/gym.jpg');
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            min-height: 100vh;
        }

        .container-wide {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .glass-panel {
            background: rgba(18, 18, 18, 0.95);
            border: 1px solid #262626;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
        }

        .page-title {
            color: #dc2626;
            font-size: 1.75rem;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #262626;
            padding-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tabs-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid #262626;
            padding-bottom: 15px;
        }

        .tab-btn {
            background: #171717;
            border: 1px solid #333;
            color: #a3a3a3;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            border-color: #dc2626;
            color: #fff;
        }

        .tab-btn.active {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }

        #searchInput {
            width: 320px;
            padding: 12px 16px;
            background: #0a0a0a;
            border: 1px solid #333333;
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        #searchInput:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            text-align: left;
        }

        th {
            padding: 14px 16px;
            border-bottom: 2px solid #262626;
            color: #a3a3a3;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.5px;
            font-weight: 700;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #262626;
            font-size: 0.92rem;
            color: #d4d4d4;
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(220, 38, 38, 0.04);
        }

        .member-id {
            color: #ef4444;
            font-family: monospace;
            font-weight: bold;
            background: rgba(220, 38, 38, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-active {
            background: rgba(22, 101, 52, 0.3);
            border: 1px solid #22c55e;
            color: #86efac;
        }

        .status-expired {
            background: rgba(153, 27, 27, 0.3);
            border: 1px solid #ef4444;
            color: #fca5a5;
        }

        .action-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action {
            padding: 8px 14px;
            background: #dc2626;
            border: none;
            border-radius: 6px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
            transition: background 0.2s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
        }

        .btn-action:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #262626;
            color: #ffffff;
            border: 1px solid #333333;
        }

        .btn-secondary:hover {
            background: #333333;
            border-color: #525252;
        }

        .btn-delete {
            background: transparent;
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 8px 12px;
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.15);
            border-color: #ef4444;
            color: #fca5a5;
        }

        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group label {
            display: block; color: #d4d4d4; font-weight: 600; font-size: 0.85rem;
            margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-control {
            width: 100%; padding: 12px 16px; background: #0a0a0a;
            border: 1px solid #333333; border-radius: 8px; color: #ffffff;
            font-size: 0.95rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25); }
        .form-control[readonly] { background: #141414; color: #a3a3a3; cursor: not-allowed; border-color: #262626; }

        .tier-options { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 8px; }
        .tier-card {
            position: relative; background: #141414; border: 1px solid #333333; border-radius: 8px;
            padding: 12px; text-align: left; cursor: pointer; transition: all 0.2s ease; text-transform: uppercase;
        }
        .tier-card:hover { border-color: #dc2626; background: #1f1f1f; }
        .tier-card.active { border-color: #dc2626; background: rgba(220, 38, 38, 0.15); box-shadow: 0 0 0 1px #dc2626; }
        .tier-info { padding-right: 25px; }
        .tier-card strong { display: block; font-size: 0.88rem; color: #ffffff; line-height: 1.2; }
        .tier-card span { font-size: 0.75rem; color: #a3a3a3; display: inline-block; margin-top: 4px; }

        .edit-tier-btn {
            position: absolute; top: 10px; right: 10px; background: transparent; border: none;
            color: #737373; cursor: pointer; font-size: 0.8rem; padding: 2px 4px; border-radius: 4px;
        }
        .edit-tier-btn:hover { color: #ffffff; background: #262626; }

        .expiry-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
        .duration-btn {
            background: #171717; color: #ffffff; border: 1px solid #333333;
            padding: 10px 8px; border-radius: 8px; cursor: pointer; display: flex;
            flex-direction: column; align-items: center; text-transform: uppercase;
        }
        .duration-btn strong { font-size: 0.85rem; }
        .duration-btn span { color: #737373; font-size: 0.7rem; margin-top: 2px; }
        .duration-btn:hover { border-color: #dc2626; background: #262626; }
        .duration-btn.active { background: #dc2626; border-color: #dc2626; color: #ffffff; }

        .summary-box {
            margin-top: 15px; padding: 12px; background: #141414;
            border: 1px dashed #333333; border-radius: 8px; color: #a3a3a3; font-size: 0.88rem; text-transform: uppercase;
        }
        .summary-box div { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .summary-box div:last-child {
            margin-bottom: 0; padding-top: 4px; border-top: 1px solid #262626;
            color: #22c55e; font-weight: 700;
        }
        
        .error-message { background: rgba(153, 27, 27, 0.3); border: 1px solid #ef4444; color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; text-transform: uppercase; }
        .success-msg { background: rgba(22, 101, 52, 0.3); border: 1px solid #22c55e; color: #86efac; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; }

        .detail-row {
            display: flex; justify-content: space-between; padding: 8px 0;
            border-bottom: 1px solid #262626; font-size: 0.95rem; text-transform: uppercase;
        }
        .detail-row span:first-child { color: #a3a3a3; }
        .detail-row span:last-child { color: #ffffff; font-weight: 600; }

        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px);
            justify-content: center; align-items: center; z-index: 1000; overflow-y: auto; padding: 20px;
        }
        .modal-card {
            background: rgba(18, 18, 18, 0.98); border: 1px solid #262626; border-radius: 16px;
            padding: 30px; width: 100%; max-width: 620px; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8);
            position: relative; max-height: 90vh; overflow-y: auto;
        }
        .close-modal {
            position: absolute; top: 20px; right: 20px; background: transparent; border: none;
            color: #a3a3a3; font-size: 1.5rem; cursor: pointer;
        }
        .close-modal:hover { color: #fff; }

        @media print {
            .no-print, #searchInput { display: none !important; }
            body { background: white; color: black; }
            .glass-panel { box-shadow: none; border: none; background: white; color: black; }
            td { color: black; } th { color: #333; }
        }
        @media (max-width: 600px) {
            .form-row, .tier-options, .expiry-options { grid-template-columns: 1fr; }
        }
        .qr-box { background: #fff; padding: 14px; border-radius: 12px; display: inline-block; margin: 10px auto; }
        .qr-id-text { font-family: monospace; color: #a3a3a3; letter-spacing: 1px; font-size: 0.85rem; }
        .btn-qr { background: #16a34a; }
        .search-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; width: 100%; }
        .search-form #searchInput { flex: 1; min-width: 200px; }
        @media print {
            body.printing-qr .container-wide, body.printing-qr .pe-nav { display: none !important; }
            body.printing-qr #qrModal { position: static; display: block !important; background: white; }
            body.printing-qr #qrModal .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container-wide">

    <div class="glass-panel">
        <h2 class="page-title">Member Onboarding Portal</h2>

        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab('new', this)">+ Register New Member</button>
            <button class="tab-btn" onclick="switchTab('legacy', this)">📥 Encode Existing / Legacy Member</button>
        </div>

        <?php if (!empty($error) && !isset($_POST['edit_member_id'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMsg) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'legacy'): ?>
            <div class="success-msg"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <div id="tabNew" class="tab-content active">
            <?php if ($newMemberCreated): ?>
                <div style="text-align:center;">
                    <div class="success-msg">MEMBER PROFILE CREATED SUCCESSFULLY!</div>
                    <div style="text-align: left; margin-bottom: 20px;">
                        <div class="detail-row"><span>NAME:</span><span><?php echo htmlspecialchars($registeredData['name']); ?></span></div>
                        <div class="detail-row"><span>TIER:</span><span><?php echo htmlspecialchars($registeredData['tier']); ?></span></div>
                        <div class="detail-row"><span>BASE RATE:</span><span>₱<?php echo number_format($registeredData['base_rate'], 2); ?></span></div>
                        <div class="detail-row"><span>DISCOUNT:</span><span>₱<?php echo number_format($registeredData['discount_amount'], 2); ?></span></div>
                        <div class="detail-row"><span>TOTAL PAID:</span><span style="color: #22c55e;">₱<?php echo number_format($registeredData['final_rate'], 2); ?></span></div>
                        <div class="detail-row"><span>EXPIRY DATE:</span><span><?php echo htmlspecialchars($registeredData['expiry_date']); ?></span></div>
                    </div>
                    <p style="color:#a3a3a3; font-size:0.85rem; margin: 0;">Member QR code (scan this at the front desk):</p>
                    <div class="qr-box"><div id="newMemberQr"></div></div>
                    <div class="qr-id-text"><?php echo e($registeredData['qr_id']); ?></div>
                    <br>
                    <a href="/member_profile.php?id=<?php echo $registeredData['id']; ?>" class="btn-action btn-qr">🖨 Open / Print QR Card</a>
                    <br>
                    <button class="btn-action" onclick="window.location.href='manage.php'">+ Register Another Member</button>
                </div>
            <?php else: ?>
                <form method="POST" action="manage.php" id="registrationForm">
                    <input type="hidden" name="form_type" value="new">
                    <div class="form-group">
                        <label for="memberName">Full Name</label>
                        <input type="text" name="memberName" id="memberName" class="form-control" placeholder="E.G. JUAN DELA CRUZ" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact">Contact Number</label>
                            <input type="tel" name="contact" id="contact" class="form-control" placeholder="09XXXXXXXXX" maxlength="11" minlength="11" pattern="[0-9]{11}" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" name="address" id="address" class="form-control" placeholder="CITY / BARANGAY" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Membership / Entry Tier</label>
                        <input type="hidden" name="tier" id="tierInput" value="GYM USE - REGULAR">
                        <div class="tier-options">
                            <div class="tier-card active" data-rate="900" onclick="selectTier('GYM USE - REGULAR', this)">
                                <button type="button" class="edit-tier-btn" onclick="editTierRate(event, this)" title="Edit Rate">✏️</button>
                                <div class="tier-info"><strong>Gym Use Only (Regular)</strong><span class="rate-label">₱900 / Month</span></div>
                            </div>
                            <div class="tier-card" data-rate="700" onclick="selectTier('GYM USE - STUDENT', this)">
                                <button type="button" class="edit-tier-btn" onclick="editTierRate(event, this)" title="Edit Rate">✏️</button>
                                <div class="tier-info"><strong>Gym Use Only (Student)</strong><span class="rate-label">₱700 / Month</span></div>
                            </div>
                            <div class="tier-card" data-rate="1500" onclick="selectTier('SUPERVISION - REGULAR', this)">
                                <button type="button" class="edit-tier-btn" onclick="editTierRate(event, this)" title="Edit Rate">✏️</button>
                                <div class="tier-info"><strong>W/ Supervision (Regular)</strong><span class="rate-label">₱1,500 / Month</span></div>
                            </div>
                            <div class="tier-card" data-rate="1200" onclick="selectTier('SUPERVISION - STUDENT', this)">
                                <button type="button" class="edit-tier-btn" onclick="editTierRate(event, this)" title="Edit Rate">✏️</button>
                                <div class="tier-info"><strong>W/ Supervision (Student)</strong><span class="rate-label">₱1,200 / Month</span></div>
                            </div>
                            <div class="tier-card" data-rate="3000" onclick="selectTier('1-ON-1 COACHING - REGULAR', this)">
                                <button type="button" class="edit-tier-btn" onclick="editTierRate(event, this)" title="Edit Rate">✏️</button>
                                <div class="tier-info"><strong>1-on-1 Coaching (Regular)</strong><span class="rate-label">₱3,000 / Month</span></div>
                            </div>
                            <div class="tier-card" data-rate="2500" onclick="selectTier('1-ON-1 COACHING - STUDENT', this)">
                                <button type="button" class="edit-tier-btn" onclick="editTierRate(event, this)" title="Edit Rate">✏️</button>
                                <div class="tier-info"><strong>1-on-1 Coaching (Student)</strong><span class="rate-label">₱2,500 / Month</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="baseRate">Base Rate (₱)</label>
                            <input type="number" step="50.00" name="baseRate" id="baseRate" class="form-control" value="900.00" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="discountAmount">Discount Amount (₱)</label>
                            <input type="number" step="50.00" name="discountAmount" id="discountAmount" class="form-control" value="0.00" min="0" oninput="calculateRegTotal()">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="expiryDate">Membership Expiry</label>
                        <input type="date" id="expiryDate" name="expiryDate" class="form-control" required>
                        <div class="expiry-options">
                            <button type="button" class="duration-btn active" onclick="setRegExpiry(1, this)"><strong>1 Month</strong><span>30 Days</span></button>
                            <button type="button" class="duration-btn" onclick="setRegExpiry(2, this)"><strong>2 Months</strong><span>60 Days</span></button>
                            <button type="button" class="duration-btn" onclick="setRegExpiry(3, this)"><strong>3 Months</strong><span>90 Days</span></button>
                            <button type="button" class="duration-btn" onclick="setRegExpiry(4, this)"><strong>4 Months</strong><span>120 Days</span></button>
                            <button type="button" class="duration-btn" onclick="setRegExpiry(5, this)"><strong>5 Months</strong><span>150 Days</span></button>
                            <button type="button" class="duration-btn" onclick="setRegExpiry(6, this)"><strong>6 Months</strong><span>180 Days</span></button>
                            <button type="button" class="duration-btn" onclick="setRegExpiry(12, this)" style="grid-column: span 3;"><strong>1 Year</strong><span>12 Months</span></button>
                        </div>
                    </div>

                    <div class="summary-box">
                        <div><span>Selected Tier:</span> <span id="summaryTier" style="color:#fff;">GYM USE - REGULAR</span></div>
                        <div><span>Expiration:</span> <span id="summaryExpiry" style="color:#fff;">-</span></div>
                        <div><span>Total Payable:</span> <span id="summaryTotal">₱900.00</span></div>
                    </div>

                    <button type="submit" class="btn-action" style="width: 100%; margin-top: 20px; padding: 14px; font-size: 0.95rem;">Complete Registration</button>
                </form>
            <?php endif; ?>
        </div>

        <div id="tabLegacy" class="tab-content">
            <p style="color: #a3a3a3; font-size: 0.9rem; margin-bottom: 20px;">
                Use this form to add long-standing or pre-existing gym members into the system. You can manually assign their exact current membership expiration date.
            </p>
            <form method="POST" action="manage.php" id="legacyForm">
                <input type="hidden" name="form_type" value="legacy">
                
                <div class="form-group">
                    <label for="legacyName">Full Name</label>
                    <input type="text" name="memberName" id="legacyName" class="form-control" placeholder="E.G. MARIA SANTOS" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="legacyContact">Contact Number</label>
                        <input type="tel" name="contact" id="legacyContact" class="form-control" placeholder="09XXXXXXXXX" maxlength="11" minlength="11" pattern="[0-9]{11}" required>
                    </div>
                    <div class="form-group">
                        <label for="legacyAddress">Address</label>
                        <input type="text" name="address" id="legacyAddress" class="form-control" placeholder="CITY / BARANGAY" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Membership / Entry Tier</label>
                    <input type="hidden" name="tier" id="legacyTierInput" value="GYM USE - REGULAR">
                    <div class="tier-options">
                        <div class="tier-card active" data-rate="900" onclick="selectLegacyTier('GYM USE - REGULAR', this)">
                            <div class="tier-info"><strong>Gym Use Only (Regular)</strong><span>₱900 / Month</span></div>
                        </div>
                        <div class="tier-card" data-rate="700" onclick="selectLegacyTier('GYM USE - STUDENT', this)">
                            <div class="tier-info"><strong>Gym Use Only (Student)</strong><span>₱700 / Month</span></div>
                        </div>
                        <div class="tier-card" data-rate="1500" onclick="selectLegacyTier('SUPERVISION - REGULAR', this)">
                            <div class="tier-info"><strong>W/ Supervision (Regular)</strong><span>₱1,500 / Month</span></div>
                        </div>
                        <div class="tier-card" data-rate="1200" onclick="selectLegacyTier('SUPERVISION - STUDENT', this)">
                            <div class="tier-info"><strong>W/ Supervision (Student)</strong><span>₱1,200 / Month</span></div>
                        </div>
                        <div class="tier-card" data-rate="3000" onclick="selectLegacyTier('1-ON-1 COACHING - REGULAR', this)">
                            <div class="tier-info"><strong>1-on-1 Coaching (Regular)</strong><span>₱3,000 / Month</span></div>
                        </div>
                        <div class="tier-card" data-rate="2500" onclick="selectLegacyTier('1-ON-1 COACHING - STUDENT', this)">
                            <div class="tier-info"><strong>1-on-1 Coaching (Student)</strong><span>₱2,500 / Month</span></div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="legacyBaseRate">Recorded / Historical Base Rate (₱)</label>
                        <input type="number" step="50.00" name="baseRate" id="legacyBaseRate" class="form-control" value="900.00" required>
                    </div>
                    <div class="form-group">
                        <label for="legacyDiscount">Recorded Discount (₱)</label>
                        <input type="number" step="50.00" name="discountAmount" id="legacyDiscount" class="form-control" value="0.00" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="legacyExpiryDate">Current Membership Expiry Date (From Old Records)</label>
                    <input type="date" id="legacyExpiryDate" name="expiryDate" class="form-control" required>
                    <span style="font-size: 0.75rem; color: #737373; margin-top: 4px; display: block;">Select the exact expiration date calculated from their existing physical or legacy records.</span>
                </div>

                <button type="submit" class="btn-action" style="width: 100%; margin-top: 20px; padding: 14px; font-size: 0.95rem;">Import Existing Member</button>
            </form>
        </div>
    </div>

    <div class="glass-panel">
        <h2 class="page-title">
            Management Dashboard
            <button onclick="window.print()" class="btn-action btn-secondary no-print" style="font-size:0.8rem;">Download Report (PDF)</button>
        </h2>

        <?php if (!empty($successMsg) && (!isset($_POST['form_type']) || $_POST['form_type'] !== 'legacy')): ?>
            <div class="success-msg"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if (!empty($error) && isset($_POST['edit_member_id'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="table-controls">
            <form method="GET" class="search-form no-print">
                <input type="text" id="searchInput" name="q" value="<?php echo e($search); ?>" placeholder="Search by name, contact, address, QR id or #ID...">
                <button type="submit" class="btn-action">Search</button>
                <?php if ($search !== ''): ?><a href="manage.php" class="btn-action btn-secondary">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Full Name</th>
                        <th>Expiry Date</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="memberList">
                    <?php foreach($members as $row): 
                        $expiryDate = strtotime($row['expiry_date']);
                        $isExpired = $expiryDate < strtotime(date("Y-m-d"));
                    ?>
                        <tr>
                            <td><span class="member-id">#<?php echo htmlspecialchars($row['id']); ?></span></td>
                            <td><strong style="color: #ffffff;"><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td><?php echo date('M d, Y', $expiryDate); ?></td>
                            <td><?php echo htmlspecialchars($row['contact']); ?></td>
                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $isExpired ? 'status-expired' : 'status-active'; ?>">
                                    <?php echo $isExpired ? 'Expired' : 'Active'; ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <div class="action-group">
                                    <a href="/member_profile.php?id=<?php echo $row['id']; ?>" class="btn-action btn-qr">QR</a>
                                    <button onclick="openEditModal(<?php echo e(json_encode($row)); ?>)" class="btn-action">Edit</button>
                                    <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Are you sure you want to delete this member? Their attendance history will also be removed.');">
                                        <input type="hidden" name="delete_member_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$members): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 30px; color:#737373;"><?php echo $search !== '' ? 'No members match your search.' : 'No members yet.'; ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php echo render_pagination($memberTotal, $perPage, $page); ?>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-card">
        <button class="close-modal" onclick="closeEditModal()">&times;</button>
        <h2 class="page-title" style="font-size: 1.35rem; margin-bottom: 20px;">Edit Member Profile</h2>

        <form method="POST" action="manage.php">
            <input type="hidden" name="edit_member_id" id="modalMemberId">

            <div class="form-group">
                <label for="modalName">Full Name</label>
                <input type="text" name="memberName" id="modalName" class="form-control" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="modalContact">Contact Number</label>
                    <input type="tel" name="contact" id="modalContact" class="form-control" maxlength="11" minlength="11" pattern="[0-9]{11}" required>
                </div>
                <div class="form-group">
                    <label for="modalAddress">Address</label>
                    <input type="text" name="address" id="modalAddress" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Membership Tier</label>
                <input type="hidden" name="tier" id="modalTierInput" value="GYM USE - REGULAR">
                <div class="tier-options">
                    <?php 
                    $tiers = [
                        'GYM USE - REGULAR' => 900,
                        'GYM USE - STUDENT' => 700,
                        'SUPERVISION - REGULAR' => 1500,
                        'SUPERVISION - STUDENT' => 1200,
                        '1-ON-1 COACHING - REGULAR' => 3000,
                        '1-ON-1 COACHING - STUDENT' => 2500,
                    ];
                    foreach ($tiers as $tierName => $rate): 
                    ?>
                        <div class="tier-card modal-tier-card" data-tier="<?php echo $tierName; ?>" data-rate="<?php echo $rate; ?>" onclick="selectModalTier('<?php echo $tierName; ?>', <?php echo $rate; ?>, this)">
                            <strong><?php echo ucwords(strtolower($tierName)); ?></strong>
                            <span>₱<?php echo number_format($rate); ?> / mo</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="modalBaseRate">Base Rate (₱)</label>
                    <input type="number" step="0.01" name="baseRate" id="modalBaseRate" class="form-control" oninput="calculateModalTotal()" required>
                </div>
                <div class="form-group">
                    <label for="modalDiscountAmount">Discount Amount (₱)</label>
                    <input type="number" step="0.01" name="discountAmount" id="modalDiscountAmount" class="form-control" value="0.00" min="0" oninput="calculateModalTotal()">
                </div>
            </div>

            <div class="form-group">
                <label for="modalExpiryDate">Membership Expiry</label>
                <input type="date" id="modalExpiryDate" name="expiryDate" class="form-control" required>
            </div>

            <div class="summary-box">
                <div><span>Selected Tier:</span> <span id="modalSummaryTier" style="color:#fff;">-</span></div>
                <div><span>Updated Expiry:</span> <span id="modalSummaryExpiry" style="color:#fff;">-</span></div>
                <div><span>Total Payable:</span> <span id="modalSummaryTotal">₱0.00</span></div>
            </div>

            <button type="submit" class="btn-action" style="width: 100%; margin-top: 15px; padding: 12px; font-size: 0.95rem;">Save Changes</button>
        </form>
    </div>
</div>

<?php if ($qrMember): ?>
<div id="qrModal" class="modal-overlay" style="display:flex;">
    <div class="modal-card" style="text-align:center; max-width: 420px;">
        <button class="close-modal no-print" onclick="document.getElementById('qrModal').style.display='none'">&times;</button>
        <h2 class="page-title no-print" style="font-size: 1.2rem; margin-bottom: 10px;">Member QR Code</h2>
        <p class="no-print" style="color:#a3a3a3; font-size:0.85rem;">Print a new card if the member's details changed.</p>
        <div class="qr-box"><div id="savedMemberQr"></div></div>
        <h3 style="margin: 8px 0 4px;"><?php echo e($qrMember['name']); ?></h3>
        <div class="qr-id-text"><?php echo e($qrMember['qr_id']); ?></div>
        <div class="qr-id-text">Valid until <?php echo e(date('M d, Y', strtotime($qrMember['expiry_date']))); ?></div>
        <button type="button" class="btn-action btn-qr no-print" style="width:100%; margin-top:18px; padding: 12px;" onclick="printQrCard()">🖨 Print QR Card</button>
    </div>
</div>
<?php endif; ?>

<script>
    function makeQr(elementId, text) {
        const el = document.getElementById(elementId);
        if (el && text) new QRCode(el, { text: text, width: 180, height: 180, colorDark: "#0f172a" });
    }
    makeQr('newMemberQr', <?php echo json_encode($registeredData['qr_id'] ?? null); ?>);
    makeQr('savedMemberQr', <?php echo json_encode($qrMember['qr_id'] ?? null); ?>);

    function printQrCard() {
        document.body.classList.add('printing-qr');
        window.print();
        document.body.classList.remove('printing-qr');
    }

    let selectedMonths = 1;
    let selectedMonthlyRate = 900;

    function switchTab(tabName, btnElement) {
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

        if (tabName === 'new') {
            document.getElementById('tabNew').classList.add('active');
        } else if (tabName === 'legacy') {
            document.getElementById('tabLegacy').classList.add('active');
        }
        btnElement.classList.add('active');
    }

    ['registrationForm', 'legacyForm'].forEach(formId => {
        const f = document.getElementById(formId);
        if (f) {
            f.addEventListener("submit", function () {
                f.querySelectorAll("input[type='text']").forEach(input => {
                    input.value = input.value.toUpperCase();
                });
            });
        }
    });

    ['contact', 'modalContact', 'legacyContact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 11);
            });
        }
    });

    function updateBaseRate() {
        const baseInput = document.getElementById("baseRate");
        baseInput.value = (selectedMonthlyRate * selectedMonths).toFixed(2);
        calculateRegTotal();
    }

    function calculateRegTotal() {
        const baseRate = parseFloat(document.getElementById("baseRate").value) || 0;
        const discountAmt = parseFloat(document.getElementById("discountAmount").value) || 0;
        const total = Math.max(0, baseRate - discountAmt);
        document.getElementById("summaryTotal").innerText = "₱" + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function selectTier(tierName, element) {
        document.getElementById("tierInput").value = tierName.toUpperCase();
        selectedMonthlyRate = parseFloat(element.getAttribute("data-rate")) || 0;
        document.querySelectorAll("#tabNew .tier-card").forEach(card => card.classList.remove("active"));
        element.classList.add("active");
        document.getElementById("summaryTier").innerText = tierName.toUpperCase();
        updateBaseRate();
    }

    function selectLegacyTier(tierName, element) {
        document.getElementById("legacyTierInput").value = tierName.toUpperCase();
        document.getElementById("legacyBaseRate").value = parseFloat(element.getAttribute("data-rate")).toFixed(2);
        document.querySelectorAll("#tabLegacy .tier-card").forEach(card => card.classList.remove("active"));
        element.classList.add("active");
    }

    function editTierRate(event, buttonElement) {
        event.stopPropagation();
        const tierCard = buttonElement.closest(".tier-card");
        const currentRate = tierCard.getAttribute("data-rate");
        const newRate = prompt("ENTER NEW MONTHLY RATE FOR THIS TIER (₱):", currentRate);
        if (newRate !== null && !isNaN(newRate) && newRate.trim() !== "") {
            const formattedRate = parseFloat(newRate);
            tierCard.setAttribute("data-rate", formattedRate);
            tierCard.querySelector(".rate-label").innerText = `₱${formattedRate.toLocaleString('en-US')} / Month`;
            if (tierCard.classList.contains("active")) {
                selectedMonthlyRate = formattedRate;
                updateBaseRate();
            }
        }
    }

    function setRegExpiry(months, button) {
        selectedMonths = months;
        const date = new Date();
        date.setMonth(date.getMonth() + months);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        document.getElementById("expiryDate").value = `${year}-${month}-${day}`;
        document.querySelectorAll(".duration-btn").forEach(btn => btn.classList.remove("active"));
        button.classList.add("active");
        document.getElementById("summaryExpiry").innerText = date.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" }).toUpperCase();
        updateBaseRate();
    }

    window.addEventListener("load", function () {
        const expiryInput = document.getElementById("expiryDate");
        if (expiryInput && !expiryInput.value) {
            setRegExpiry(1, document.querySelector('.duration-btn'));
        }
    });

    function openEditModal(member) {
        document.getElementById("modalMemberId").value = member.id;
        document.getElementById("modalName").value = member.name;
        document.getElementById("modalContact").value = member.contact;
        document.getElementById("modalAddress").value = member.address;
        document.getElementById("modalExpiryDate").value = member.expiry_date;
        document.getElementById("modalBaseRate").value = member.base_rate;
        document.getElementById("modalDiscountAmount").value = member.discount_amount || 0;
        
        document.querySelectorAll(".modal-tier-card").forEach(card => {
            if(card.getAttribute("data-tier") === member.tier) {
                selectModalTier(member.tier, parseFloat(card.getAttribute("data-rate")), card);
            }
        });

        calculateModalTotal();
        if (member.expiry_date) {
            document.getElementById("modalSummaryExpiry").innerText = new Date(member.expiry_date + "T00:00:00").toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
        }
        document.getElementById("editModal").style.display = "flex";
    }

    function closeEditModal() {
        document.getElementById("editModal").style.display = "none";
    }

    function selectModalTier(tierName, defaultRate, element) {
        document.getElementById("modalTierInput").value = tierName;
        const baseInput = document.getElementById("modalBaseRate");
        baseInput.dataset.monthlyRate = defaultRate;
        document.querySelectorAll(".modal-tier-card").forEach(card => card.classList.remove("active"));
        element.classList.add("active");
        document.getElementById("modalSummaryTier").innerText = tierName;
        calculateModalTotal();
    }

    function calculateModalTotal() {
        const baseInput = document.getElementById("modalBaseRate");
        const discountAmt = parseFloat(document.getElementById("modalDiscountAmount").value) || 0;
        const total = Math.max(0, parseFloat(baseInput.value) - discountAmt);
        document.getElementById("modalSummaryTotal").innerText = "₱" + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>

</body>
</html>