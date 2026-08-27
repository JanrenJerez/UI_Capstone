<?php
session_start();
include __DIR__ . '/includes/db.php';

if (!isset($_SESSION['isAdminLoggedIn'])) {
    header("Location: login.php");
    exit();
}

$newID = "";
$newName = "";
$contact = "";
$address = "";
$expiry = "";
$success = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $newName = trim($_POST['memberName'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $expiry = $_POST['expiryDate'] ?? '';

    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        $error = "Contact number must contain exactly 11 digits.";
    } elseif (empty($newName) || empty($address) || empty($expiry)) {
        $error = "Please fill in all required fields.";
    } else {

        $newID = "GYM-" . time() . rand(10, 99);

        $stmt = $conn->prepare("
            INSERT INTO members
            (name, contact, address, expiry_date, qr_id)
            VALUES (?, ?, ?, ?, ?)
        ");

        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {

            $stmt->bind_param(
                "sssss",
                $newName,
                $contact,
                $address,
                $expiry,
                $newID
            );

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Error: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register Member | Project E</title>

    <link rel="stylesheet" href="assets/style.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        .expiry-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .duration-btn {
            background: #03192e;
            color: #fcfcfc;
            border: 2px solid #e2e8f0;
            padding: 14px 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .duration-btn strong {
            font-size: 1rem;
        }

        .duration-btn span {
            color: #64748b;
            font-size: 0.75rem;
            margin-top: 3px;
        }

        .duration-btn:hover {
            border-color: #38bdf8;
            background: #f0f9ff;
            transform: translateY(-2px);
        }

        .duration-btn.active {
            background: #38bdf8;
            border-color: #38bdf8;
            color: white;
            box-shadow: 0 5px 15px rgba(56, 189, 248, 0.3);
        }

        .duration-btn.active span {
            color: #e0f2fe;
        }

        .expiry-date-display {
            margin-top: 12px;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 8px;
            color: #334155;
            font-size: 0.9rem;
            text-align: center;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success-msg {
            background: #dcfce7;
            color: #166534;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        #contact {
            letter-spacing: 1px;
        }

        @media (max-width: 500px) {
            .expiry-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

<div class="container">

    <div class="login-box" style="margin-top:10px;max-width:550px;margin-left:auto;margin-right:auto;">

        <?php if ($success): ?>

            <div id="qrSection" style="text-align:center;">

                <h2>Member Registration</h2>

                <div class="success-msg">
                    Member Registered Successfully!
                </div>

                <p>
                    <strong>Name:</strong>
                    <?php echo htmlspecialchars($newName); ?>
                </p>

                <p>
                    <strong>Contact:</strong>
                    <?php echo htmlspecialchars($contact); ?>
                </p>

                <p>
                    <strong>Address:</strong>
                    <?php echo htmlspecialchars($address); ?>
                </p>

                <p>
                    <strong>Expiry:</strong>
                    <?php echo htmlspecialchars($expiry); ?>
                </p>

                <p style="font-size:.85rem;color:#64748b;">
                    Scan or Download this QR Code
                </p>

                <div
                    id="qrcode"
                    style="display:flex;justify-content:center;margin:20px 0;">
                </div>

                <button
                    class="btn-secondary"
                    style="width:100%;"
                    onclick="window.location.href='register.php'">
                    Register Another
                </button>

            </div>

            <script>
                new QRCode(
                    document.getElementById("qrcode"),
                    {
                        text: "<?php echo htmlspecialchars($newID, ENT_QUOTES); ?>",
                        width: 200,
                        height: 200,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff"
                    }
                );
            </script>

        <?php else: ?>

            <h2 style="margin-bottom: 15px;">Member Registration</h2>

            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="register.php">

                <div class="form-group">

                    <label for="memberName">
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="memberName"
                        id="memberName"
                        placeholder="Enter full name"
                        value="<?php echo htmlspecialchars($newName); ?>"
                        required
                        style="width:100%;padding:8px;margin-bottom:10px;">

                </div>

                <div class="form-group">

                    <label for="contact">
                        Contact Number
                    </label>

                    <input
                        type="tel"
                        name="contact"
                        id="contact"
                        placeholder="09XXXXXXXXX"
                        value="<?php echo htmlspecialchars($contact); ?>"
                        maxlength="11"
                        minlength="11"
                        pattern="[0-9]{11}"
                        inputmode="numeric"
                        required
                        style="width:100%;padding:8px;margin-bottom:10px;">

                    <small style="color:#64748b;">
                        Enter exactly 11 digits.
                    </small>

                </div>

                <div class="form-group">

                    <label for="address">
                        Address
                    </label>

                    <textarea
                        name="address"
                        id="address"
                        placeholder="Enter address"
                        required
                        style="width:100%;padding:8px;margin-bottom:10px;"><?php echo htmlspecialchars($address); ?></textarea>

                </div>

                <div class="form-group">

                    <label for="expiryDate">
                        Membership Expiry
                    </label>

                    <input
                        type="date"
                        id="expiryDate"
                        name="expiryDate"
                        value="<?php echo htmlspecialchars($expiry); ?>"
                        required
                        style="width:100%;padding:8px;margin-bottom:10px;">

                    <div class="expiry-options">

                        <button
                            type="button"
                            class="duration-btn"
                            onclick="setExpiry(1, this)">
                            <strong>1 Month</strong>
                            <span>30 Days</span>
                        </button>

                        <button
                            type="button"
                            class="duration-btn"
                            onclick="setExpiry(3, this)">
                            <strong>3 Months</strong>
                            <span>90 Days</span>
                        </button>

                        <button
                            type="button"
                            class="duration-btn"
                            onclick="setExpiry(6, this)">
                            <strong>6 Months</strong>
                            <span>180 Days</span>
                        </button>

                        <button
                            type="button"
                            class="duration-btn"
                            onclick="setExpiry(9, this)">
                            <strong>9 Months</strong>
                            <span>270 Days</span>
                        </button>

                        <button
                            type="button"
                            class="duration-btn"
                            onclick="setExpiry(12, this)">
                            <strong>1 Year</strong>
                            <span>12 Months</span>
                        </button>

                    </div>

                    <div
                        id="expiryDisplay"
                        class="expiry-date-display">
                        Select a membership duration
                    </div>

                </div>

                <button
                    type="submit"
                    class="btn-secondary"
                    style="width:100%;margin-top:10px;padding:12px;">
                    Register & Generate QR
                </button>

            </form>

        <?php endif; ?>

    </div>

</div>

<script>
    const contactInput = document.getElementById("contact");

    if (contactInput) {
        contactInput.addEventListener("input", function () {
            this.value = this.value
                .replace(/\D/g, '')
                .slice(0, 11);
        });
    }

    function formatDate(date) {

        const options = {
            year: "numeric",
            month: "long",
            day: "numeric"
        };

        return date.toLocaleDateString("en-US", options);
    }

    function setExpiry(months, button) {

        const date = new Date();

        date.setMonth(date.getMonth() + months);

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        document.getElementById("expiryDate").value =
            `${year}-${month}-${day}`;

        document.querySelectorAll(".duration-btn").forEach(btn => {
            btn.classList.remove("active");
        });

        button.classList.add("active");

        document.getElementById("expiryDisplay").innerText =
            "Membership expires: " + formatDate(date);
    }

    window.addEventListener("load", function () {

        const expiryInput =
            document.getElementById("expiryDate");

        if (expiryInput) {

            const today = new Date();

            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');

            const todayString =
                `${year}-${month}-${day}`;

            expiryInput.min = todayString;

            if (!expiryInput.value) {
                expiryInput.value = todayString;
            }
        }
    });

    const expiryInput =
        document.getElementById("expiryDate");

    if (expiryInput) {

        expiryInput.addEventListener("change", function () {

            document.querySelectorAll(".duration-btn").forEach(btn => {
                btn.classList.remove("active");
            });

            if (this.value) {

                const selectedDate =
                    new Date(this.value + "T00:00:00");

                document.getElementById("expiryDisplay").innerText =
                    "Membership expires: " +
                    formatDate(selectedDate);
            }
        });
    }
</script>

</body>
</html>
```
