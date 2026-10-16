<?php
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_unlocked();

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: manage.php");
    exit();
}

// Shown after the redirect that follows a save (so refreshing never saves twice)
$success = isset($_GET['saved']);
$renewalRecorded = !empty($_GET['renewed']);
$error = "";

try {
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        header("Location: manage.php");
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $newName = trim($_POST['memberName'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $expiry = $_POST['expiryDate'] ?? '';
        $tier = strtoupper(trim($_POST['tier'] ?? 'GYM USE - REGULAR'));
        $isRenewal = !empty($_POST['record_renewal']);
        $baseRate = floatval($_POST['baseRate'] ?? 0);
        $discountAmount = floatval($_POST['discountAmount'] ?? 0);

        $finalRate = max(0, $baseRate - $discountAmount);

        if (!preg_match('/^[0-9]{11}$/', $contact)) {
            $error = "Contact number must contain exactly 11 digits.";
        } elseif (empty($newName) || empty($address) || empty($expiry)) {
            $error = "Please fill in all required fields.";
        } else {

            $updateStmt = $conn->prepare("
                UPDATE members 
                SET name = ?, contact = ?, address = ?, expiry_date = ?, tier = ?, membership_type = ?, base_rate = ?, discount_amount = ?, final_rate = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $newName,
                $contact,
                $address,
                $expiry,
                $tier,
                $tier,
                $baseRate,
                $discountAmount,
                $finalRate,
                $id
            ]);

            // A renewal is new money received, so it counts as revenue
            if ($isRenewal) {
                $months = max(1, intval($_POST['renewal_months'] ?? 1));
                record_membership_payment($conn, $id, $newName, $finalRate,
                    "Renewal - $tier ($months month" . ($months > 1 ? 's' : '') . ")");
            }

            header("Location: log.php?id=" . intval($id) . "&saved=1" . ($isRenewal ? "&renewed=1" : ""));
            exit();
            $member['name'] = $newName;
            $member['contact'] = $contact;
            $member['address'] = $address;
            $member['expiry_date'] = $expiry;
            $member['tier'] = $tier;
            $member['base_rate'] = $baseRate;
            $member['discount_amount'] = $discountAmount;
            $member['final_rate'] = $finalRate;
        }
    }
} catch (PDOException $e) {
    error_log("Member edit failed: " . $e->getMessage());
    $error = "Could not save the member. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member | System Admin</title>

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
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .form-card {
            background: rgba(18, 18, 18, 0.95);
            border: 1px solid #262626;
            border-radius: 16px;
            padding: 35px;
            max-width: 620px;
            margin: 20px auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
        }

        .form-title {
            color: #dc2626;
            font-size: 1.75rem;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #262626;
            padding-bottom: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group label {
            display: block;
            color: #d4d4d4;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #0a0a0a;
            border: 1px solid #333333;
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);
        }

        .tier-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 8px;
        }

        .tier-card {
            background: #141414;
            border: 1px solid #333333;
            border-radius: 8px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tier-card:hover {
            border-color: #dc2626;
            background: #1f1f1f;
        }

        .tier-card.active {
            border-color: #dc2626;
            background: rgba(220, 38, 38, 0.15);
            box-shadow: 0 0 0 1px #dc2626;
        }

        .tier-card strong {
            display: block;
            font-size: 0.9rem;
            color: #ffffff;
        }

        .tier-card span {
            font-size: 0.75rem;
            color: #a3a3a3;
        }

        .expiry-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 12px;
        }

        .duration-btn {
            background: #171717;
            color: #ffffff;
            border: 1px solid #333333;
            padding: 10px 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .duration-btn strong { font-size: 0.85rem; }
        .duration-btn span { color: #737373; font-size: 0.7rem; margin-top: 2px; }

        .duration-btn:hover { border-color: #dc2626; background: #262626; }
        .duration-btn.active {
            background: #dc2626;
            border-color: #dc2626;
            color: #ffffff;
        }
        .duration-btn.active span { color: #fca5a5; }

        .summary-box {
            margin-top: 15px;
            padding: 12px;
            background: #141414;
            border: 1px dashed #333333;
            border-radius: 8px;
            color: #a3a3a3;
            font-size: 0.88rem;
        }

        .summary-box div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .summary-box div:last-child {
            margin-bottom: 0;
            padding-top: 4px;
            border-top: 1px solid #262626;
            color: #22c55e;
            font-weight: 700;
        }

        .btn-primary {
            width: 100%;
            background: #dc2626;
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary:hover { background: #b91c1c; }

        .btn-secondary {
            display: block;
            text-align: center;
            width: 100%;
            background: transparent;
            color: #a3a3a3;
            border: 1px solid #333333;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            margin-top: 10px;
            transition: all 0.2s;
        }

        .btn-secondary:hover { background: #262626; color: #fff; }

        .error-message {
            background: rgba(153, 27, 27, 0.3);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success-msg {
            background: rgba(22, 101, 52, 0.3);
            border: 1px solid #22c55e;
            color: #86efac;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .form-row, .tier-options, .expiry-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <div class="form-card">

        <h2 class="form-title">Edit Member Profile</h2>

        <?php if ($success): ?>
            <div class="success-msg">Member record updated successfully!<?php if ($renewalRecorded): ?> Renewal payment recorded.<?php endif; ?>
                <a href="/member_profile.php?id=<?php echo urlencode($id); ?>" style="color:#86efac; display:block; margin-top:6px;">🖨 View / reprint QR card →</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="log.php?id=<?php echo urlencode($id); ?>">

            <div class="form-group">
                <label for="memberName">Full Name</label>
                <input type="text" name="memberName" id="memberName" class="form-control" value="<?php echo htmlspecialchars($member['name'] ?? ''); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="tel" name="contact" id="contact" class="form-control" value="<?php echo htmlspecialchars($member['contact'] ?? ''); ?>" maxlength="11" minlength="11" pattern="[0-9]{11}" inputmode="numeric" required>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" name="address" id="address" class="form-control" value="<?php echo htmlspecialchars($member['address'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Membership Tier</label>
                <input type="hidden" name="tier" id="tierInput" value="<?php echo htmlspecialchars(strtoupper($member['tier'] ?? 'GYM USE - REGULAR')); ?>">
                
                <div class="tier-options">
                    <?php 
                    $currentTier = strtoupper($member['tier'] ?? 'GYM USE - REGULAR');
                    $currentMonthlyRate = MEMBERSHIP_TIERS[$currentTier] ?? ($member['base_rate'] ?? 900);

                    foreach (MEMBERSHIP_TIERS as $tierName => $rate): 
                        $isActive = ($currentTier === $tierName) ? 'active' : '';
                    ?>
                        <div class="tier-card <?php echo $isActive; ?>" onclick="selectTier('<?php echo $tierName; ?>', <?php echo $rate; ?>, this)">
                            <strong><?php echo ucwords(strtolower($tierName)); ?></strong>
                            <span>₱<?php echo number_format($rate); ?> / mo</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="baseRate">Base Rate (₱)</label>
                    <input type="number" step="0.01" name="baseRate" id="baseRate" class="form-control" value="<?php echo htmlspecialchars($member['base_rate'] ?? '900.00'); ?>" data-monthly-rate="<?php echo htmlspecialchars($currentMonthlyRate); ?>" oninput="updateTotal()" required>
                </div>

                <div class="form-group">
                    <label for="discountAmount">Discount Amount (₱)</label>
                    <input type="number" step="0.01" name="discountAmount" id="discountAmount" class="form-control" value="<?php echo htmlspecialchars($member['discount_amount'] ?? '0.00'); ?>" min="0" oninput="updateTotal()">
                </div>
            </div>

            <div class="form-group">
                <label for="expiryDate">Membership Expiry</label>
                <input type="date" id="expiryDate" name="expiryDate" class="form-control" value="<?php echo htmlspecialchars($member['expiry_date'] ?? ''); ?>" required>

                <div class="expiry-options">
                    <button type="button" class="duration-btn" onclick="addExpiryMonths(1, this)">
                        <strong>+1 Month</strong><span>Extend 30 Days</span>
                    </button>
                    <button type="button" class="duration-btn" onclick="addExpiryMonths(2, this)">
                        <strong>+2 Months</strong><span>Extend 60 Days</span>
                    </button>
                    <button type="button" class="duration-btn" onclick="addExpiryMonths(3, this)">
                        <strong>+3 Months</strong><span>Extend 90 Days</span>
                    </button>
                    <button type="button" class="duration-btn" onclick="addExpiryMonths(6, this)">
                        <strong>+6 Months</strong><span>Extend 180 Days</span>
                    </button>
                    <button type="button" class="duration-btn" onclick="addExpiryMonths(12, this)">
                        <strong>+1 Year</strong><span>Extend 12 Months</span>
                    </button>
                </div>
            </div>

            <input type="hidden" name="renewal_months" id="renewalMonths" value="0">
            <label id="renewalOption" style="display:none; align-items:center; gap:8px; margin: 10px 0; color:#d4d4d4; font-size:0.9rem; cursor:pointer;">
                <input type="checkbox" name="record_renewal" id="recordRenewal" value="1" style="width:auto;">
                Record this as a renewal payment (counts as revenue today)
            </label>

            <div class="summary-box">
                <div><span>Selected Tier:</span> <span id="summaryTier" style="color:#fff;"><?php echo htmlspecialchars($member['tier'] ?? 'Standard'); ?></span></div>
                <div><span>Updated Expiry:</span> <span id="summaryExpiry" style="color:#fff;"><?php echo htmlspecialchars($member['expiry_date'] ?? '-'); ?></span></div>
                <div><span>Total Payable / Recorded:</span> <span id="summaryTotal">₱0.00</span></div>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 15px;">
                Save Changes
            </button>
            <a href="/member_profile.php?id=<?php echo urlencode($id); ?>" class="btn-secondary">Back to Member Profile</a>
            <a href="manage.php" class="btn-secondary">Back to Members List</a>

        </form>

    </div>
</div>

<script>
    let addedMonths = 0;
    // Extensions always count from the saved expiry (or today, if already expired),
    // so clicking several duration buttons doesn't stack months.
    const originalExpiry = <?php echo json_encode($member['expiry_date'] ?? ''); ?>;

    const contactInput = document.getElementById("contact");
    if (contactInput) {
        contactInput.addEventListener("input", function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });
    }

    function selectTier(tierName, defaultRate, element) {
        document.getElementById("tierInput").value = tierName;
        document.getElementById("baseRate").dataset.monthlyRate = defaultRate;

        document.querySelectorAll(".tier-card").forEach(card => card.classList.remove("active"));
        element.classList.add("active");

        document.getElementById("summaryTier").innerText = tierName;
        recalculateBase();
    }

    // Base rate = monthly rate x months being added (only when renewing)
    function recalculateBase() {
        const baseInput = document.getElementById("baseRate");
        const monthlyRate = parseFloat(baseInput.dataset.monthlyRate) || 0;
        baseInput.value = (monthlyRate * Math.max(1, addedMonths)).toFixed(2);
        updateTotal();
    }

    function updateTotal() {
        const base = parseFloat(document.getElementById("baseRate").value) || 0;
        const discountAmt = parseFloat(document.getElementById("discountAmount").value) || 0;
        const total = Math.max(0, base - discountAmt);
        document.getElementById("summaryTotal").innerText = "₱" + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDate(date) {
        return date.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
    }

    // Jan 31 + 1 month = Feb 28/29 (not Mar 3)
    function addMonthsClamped(date, months) {
        const d = new Date(date.getFullYear(), date.getMonth() + months, 1);
        const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
        d.setDate(Math.min(date.getDate(), lastDay));
        return d;
    }

    function toIsoDate(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function addExpiryMonths(months, button) {
        addedMonths = months;
        const today = new Date(); today.setHours(0, 0, 0, 0);
        let start = originalExpiry ? new Date(originalExpiry + "T00:00:00") : today;
        if (start < today) start = today;

        const newExpiry = addMonthsClamped(start, months);
        document.getElementById("expiryDate").value = toIsoDate(newExpiry);

        document.querySelectorAll(".duration-btn").forEach(btn => btn.classList.remove("active"));
        button.classList.add("active");
        document.getElementById("summaryExpiry").innerText = formatDate(newExpiry);

        // Renewing: offer to record the payment (checked by default)
        document.getElementById("renewalMonths").value = months;
        document.getElementById("renewalOption").style.display = "flex";
        document.getElementById("recordRenewal").checked = true;
        recalculateBase();
    }

    window.addEventListener("load", function () {
        updateTotal();
        const expiryVal = document.getElementById("expiryDate").value;
        if (expiryVal) {
            document.getElementById("summaryExpiry").innerText = formatDate(new Date(expiryVal + "T00:00:00"));
        }
    });

    const expiryInput = document.getElementById("expiryDate");
    if (expiryInput) {
        expiryInput.addEventListener("change", function () {
            document.querySelectorAll(".duration-btn").forEach(btn => btn.classList.remove("active"));
            if (this.value) {
                const selectedDate = new Date(this.value + "T00:00:00");
                document.getElementById("summaryExpiry").innerText = formatDate(selectedDate);
            }
        });
    }
</script>

</body>
</html>