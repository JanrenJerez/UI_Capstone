<?php
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['isAdminLoggedIn'])) {
    header("Location: ../login.php");
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        * { box-sizing: border-box; }

        body {
            background: #0a0a0a;
            color: #ffffff;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            background-image:
                linear-gradient(rgba(10, 10, 10, 0.90), rgba(10, 10, 10, 0.90)),
                url('assets/gym.jpg');
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
            max-width: 580px;
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

        .form-group label {
            display: block;
            color: #d4d4d4;
            font-weight: 600;
            font-size: 0.9rem;
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

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .field-hint {
            color: #737373;
            font-size: 0.78rem;
            margin-top: 6px;
            display: block;
        }

        /* Expiry Selectors */
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
            padding: 12px 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .duration-btn strong {
            font-size: 0.9rem;
        }

        .duration-btn span {
            color: #737373;
            font-size: 0.72rem;
            margin-top: 3px;
        }

        .duration-btn:hover {
            border-color: #dc2626;
            background: #262626;
        }

        .duration-btn.active {
            background: #dc2626;
            border-color: #dc2626;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .duration-btn.active span {
            color: #fca5a5;
        }

        .expiry-date-display {
            margin-top: 15px;
            padding: 12px;
            background: #141414;
            border: 1px dashed #333333;
            border-radius: 8px;
            color: #a3a3a3;
            font-size: 0.88rem;
            text-align: center;
            font-weight: 500;
        }

        /* Buttons */
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
            transition: background 0.2s, transform 0.1s;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-primary:active {
            transform: scale(0.99);
        }

        /* Alerts */
        .error-message {
            background: rgba(153, 27, 27, 0.3);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success-msg {
            background: rgba(22, 101, 52, 0.3);
            border: 1px solid #22c55e;
            color: #86efac;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Registered Details Card */
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #262626;
            font-size: 0.95rem;
        }

        .detail-row span:first-child { color: #a3a3a3; }
        .detail-row span:last-child { color: #ffffff; font-weight: 600; }

        .qr-wrapper {
            background: #ffffff;
            padding: 15px;
            border-radius: 12px;
            display: inline-block;
            margin: 20px 0;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        #contact { letter-spacing: 1px; }

        @media (max-width: 500px) {
            .expiry-options {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container">

    <div class="form-card">

        <?php if ($success): ?>

            <div id="qrSection" style="text-align:center;">

                <h2 class="form-title">Member Registered</h2>

                <div class="success-msg">
                    Member profile created successfully!
                </div>

                <div style="text-align: left; margin-bottom: 20px;">
                    <div class="detail-row">
                        <span>Name:</span>
                        <span><?php echo htmlspecialchars($newName); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Contact:</span>
                        <span><?php echo htmlspecialchars($contact); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Address:</span>
                        <span><?php echo htmlspecialchars($address); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Expiry Date:</span>
                        <span><?php echo htmlspecialchars($expiry); ?></span>
                    </div>
                </div>

                <p style="font-size: 0.85rem; color: #a3a3a3; margin-bottom: 5px;">
                    Scan or Download Digital Pass QR Code
                </p>

                <div class="qr-wrapper">
                    <div id="qrcode"></div>
                </div>

                <button
                    class="btn-primary"
                    onclick="window.location.href='register.php'">
                    + Register Another Member
                </button>

            </div>

            <script>
                new QRCode(
                    document.getElementById("qrcode"),
                    {
                        text: "<?php echo htmlspecialchars($newID, ENT_QUOTES); ?>",
                        width: 180,
                        height: 180,
                        colorDark: "#0a0a0a",
                        colorLight: "#ffffff"
                    }
                );
            </script>

        <?php else: ?>

            <h2 class="form-title">Member Registration</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">

                <div class="form-group">
                    <label for="memberName">Full Name</label>
                    <input
                        type="text"
                        name="memberName"
                        id="memberName"
                        class="form-control"
                        placeholder="e.g. John Doe"
                        value="<?php echo htmlspecialchars($newName); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input
                        type="tel"
                        name="contact"
                        id="contact"
                        class="form-control"
                        placeholder="09XXXXXXXXX"
                        value="<?php echo htmlspecialchars($contact); ?>"
                        maxlength="11"
                        minlength="11"
                        pattern="[0-9]{11}"
                        inputmode="numeric"
                        required>
                    <span class="field-hint">Must contain exactly 11 digits.</span>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea
                        name="address"
                        id="address"
                        class="form-control"
                        placeholder="Enter primary address"
                        required><?php echo htmlspecialchars($address); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="expiryDate">Membership Expiry</label>
                    <input
                        type="date"
                        id="expiryDate"
                        name="expiryDate"
                        class="form-control"
                        value="<?php echo htmlspecialchars($expiry); ?>"
                        required>

                    <div class="expiry-options">
                        <button type="button" class="duration-btn" onclick="setExpiry(1, this)">
                            <strong>1 Month</strong>
                            <span>30 Days</span>
                        </button>
                        <button type="button" class="duration-btn" onclick="setExpiry(3, this)">
                            <strong>3 Months</strong>
                            <span>90 Days</span>
                        </button>
                        <button type="button" class="duration-btn" onclick="setExpiry(6, this)">
                            <strong>6 Months</strong>
                            <span>180 Days</span>
                        </button>
                        <button type="button" class="duration-btn" onclick="setExpiry(9, this)">
                            <strong>9 Months</strong>
                            <span>270 Days</span>
                        </button>
                        <button type="button" class="duration-btn" onclick="setExpiry(12, this)">
                            <strong>1 Year</strong>
                            <span>12 Months</span>
                        </button>
                    </div>

                    <div id="expiryDisplay" class="expiry-date-display">
                        Select a preset duration or pick a date above
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 10px;">
                    Register & Generate Pass
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

        document.getElementById("expiryDate").value = `${year}-${month}-${day}`;

        document.querySelectorAll(".duration-btn").forEach(btn => {
            btn.classList.remove("active");
        });

        button.classList.add("active");

        document.getElementById("expiryDisplay").innerText =
            "Membership expires: " + formatDate(date);
    }

    window.addEventListener("load", function () {
        const expiryInput = document.getElementById("expiryDate");

        if (expiryInput) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');

            const todayString = `${year}-${month}-${day}`;
            expiryInput.min = todayString;

            if (!expiryInput.value) {
                expiryInput.value = todayString;
            }
        }
    });

    const expiryInput = document.getElementById("expiryDate");

    if (expiryInput) {
        expiryInput.addEventListener("change", function () {
            document.querySelectorAll(".duration-btn").forEach(btn => {
                btn.classList.remove("active");
            });

            if (this.value) {
                const selectedDate = new Date(this.value + "T00:00:00");
                document.getElementById("expiryDisplay").innerText =
                    "Membership expires: " + formatDate(selectedDate);
            }
        });
    }
</script>

</body>