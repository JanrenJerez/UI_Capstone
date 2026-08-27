<?php
session_start();
include __DIR__ . '/includes/db.php';

$error = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_staff'])) {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password']; 

    $query = "SELECT * FROM users WHERE username='$user' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($pass, $row['password'])) {
            $_SESSION['isAdminLoggedIn'] = true;
            $_SESSION['role'] = $row['role']; 
            $_SESSION['user_name'] = $row['username'];

            if ($row['role'] == 'admin') {
                header("Location: pos/dashboard.php");
            } else {
                header("Location: pos/scanner.php"); 
            }
            exit(); 
        } else {
            $error = "Invalid Password";
        }
    } else {
        $error = "User not found";
    }
}

$recent_query = "SELECT members.name as fullname, attendance.scan_time 
                 FROM attendance 
                 JOIN members ON attendance.member_id = members.id 
                 ORDER BY attendance.id DESC LIMIT 5";
$recent_res = $conn->query($recent_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project E | Entrance Portal</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { box-sizing: border-box; }

        body { 
            background: #0a0a0a; 
            color: #ffffff; 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0;
            background-image: 
                linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.3)), 
                url('./assets/gym.jpg');
            background-size: cover; 
            background-attachment: fixed;
            background-position: center;
            height: 100vh; 
            overflow: hidden;
        }

        #gym-clock {
            position: absolute; 
            top: 20px; 
            left: 50%; 
            transform: translateX(-50%); 
            z-index: 100; 
            text-align: center; 
            background: rgba(18, 18, 18, 0.95);
            padding: 10px 30px; 
            border-radius: 50px; 
            border: 1px solid #262626;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
        }
        #clock-time { color: #dc2626; font-size: 1.8rem; font-weight: 800; letter-spacing: 2px; }
        #clock-date { color: #a3a3a3; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; }

        .split-container { display: flex; height: 100vh; width: 100%; padding-top: 40px; }
        .scanner-section { flex: 1.2; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; }
        .login-section { flex: 0.8; display: flex; align-items: center; justify-content: center; padding: 40px; }

        .glass-card {
            background: rgba(18, 18, 18, 0.95); 
            backdrop-filter: blur(10px);
            color: #ffffff; 
            padding: 35px; 
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5); 
            width: 100%; 
            max-width: 420px; 
            text-align: center;
            border: 1px solid #262626;
        }

        .page-title {
            color: #ffffff; 
            background: #dc2626; 
            display: inline-block; 
            padding: 8px 20px; 
            border-radius: 6px; 
            text-transform: uppercase; 
            font-weight: 800; 
            margin-bottom: 20px; 
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }

        #reader { 
            width: 100%; 
            border-radius: 12px; 
            overflow: hidden; 
            border: 3px solid #dc2626 !important; 
            background: #000000;
            margin-bottom: 15px; 
        }
        
        #result { 
            padding: 15px; 
            border-radius: 10px; 
            font-weight: 700; 
            min-height: 25px; 
            transition: 0.3s; 
            margin-top: 10px;
            background: #18181b;
            color: #a1a1aa;
            border: 1px solid #262626;
        }

        input { 
            width: 100%; 
            padding: 12px; 
            margin: 6px 0 12px 0; 
            background: #0a0a0a; 
            border: 1px solid #262626; 
            border-radius: 8px; 
            color: white; 
            font-size: 14px; 
            box-sizing: border-box; 
        }
        input:focus { border-color: #dc2626; outline: none; }

        .btn-action { 
            width: 100%; 
            padding: 12px; 
            background: #dc2626; 
            border: none; 
            border-radius: 10px; 
            color: #ffffff; 
            font-weight: bold; 
            cursor: pointer; 
            text-transform: uppercase;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            transition: 0.2s;
        }
        .btn-action:hover { background: #b91c1c; box-shadow: 0 0 15px rgba(220, 38, 38, 0.4); }

        #attendance-ticker {
            position: absolute; 
            bottom: 0; 
            left: 0; 
            width: 100%; 
            background: rgba(18, 18, 18, 0.95); 
            border-top: 1px solid #262626; 
            padding: 15px 0; 
            overflow: hidden; 
            backdrop-filter: blur(10px); 
            z-index: 100;
        }
        #ticker-content { display: flex; justify-content: center; gap: 20px; }
        .ticker-item { 
            background: rgba(26, 26, 26, 0.95); 
            border: 1px solid #262626; 
            padding: 8px 15px; 
            border-radius: 8px; 
            animation: slideIn 0.5s ease; 
        }

        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 900px) {
            .split-container { flex-direction: column; overflow-y: auto; height: auto; }
            body { height: auto; overflow: visible; }
            #gym-clock { position: relative; top: 10px; margin-bottom: 10px; }
        }
    </style>
</head>
<body>

<div id="gym-clock">
    <div id="clock-time">00:00:00</div>
    <div id="clock-date">LOADING DATE...</div>
</div>

<div class="split-container">
    <div class="scanner-section">
        <div class="glass-card">
            <h1 class="page-title">Member Entrance</h1>
            <h3 style="margin-top:0; color: #ffffff;">QR Attendance</h3>
            <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                <span id="status-label" style="font-size: 0.8rem; font-weight: bold; color: #ef4444;">SCANNER OFF</span>
                <button id="scanner-toggle" class="btn-action" style="width: auto; padding: 10px 20px; margin: 0; background: #262626; border: 1px solid #404040;">
                    Turn Camera On
                </button>
            </div>
            <div id="reader" style="display: none;"></div>
            <div id="result">Scanner is currently disabled</div>
        </div>
    </div>

    <div class="login-section">
        <div class="glass-card">
            <h1 class="page-title">
                <a href="signup.php" style="text-decoration: none; color: inherit;">Staff Portal</a>
            </h1>
            <?php if(!empty($error)): ?>
                <div style="color:#ef4444; margin-bottom:15px; font-size:0.85rem; font-weight:bold; background: #450a0a; padding: 8px; border-radius: 6px; border: 1px solid #7f1d1d;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login_staff" class="btn-action">Login to System</button>
                <div style="margin-top: 15px;">
                    <a href="forgot_password.php" style="color: #a3a3a3; font-size: 0.8rem; text-decoration: none; font-weight: bold;">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="attendance-ticker">
    <div id="ticker-content">
        <?php if ($recent_res && $recent_res->num_rows > 0): ?>
            <?php while($row = $recent_res->fetch_assoc()): ?>
                <div class="ticker-item">
                    <span style="color: white; font-weight: bold;"><?php echo htmlspecialchars($row['fullname']); ?></span>
                    <span style="color: #dc2626; font-size: 0.8rem; margin-left: 10px; font-weight: 600;">
                        <?php echo date('h:i A', strtotime($row['scan_time'])); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #525252; margin: 0; font-style: italic;">No attendance recorded today.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock-time').textContent = `${h}:${m}:${s}`;
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('clock-date').textContent = now.toLocaleDateString('en-US', options);
    }
    setInterval(updateClock, 1000);
    updateClock();

    const html5QrCode = new Html5Qrcode("reader");
    const toggleBtn = document.getElementById('scanner-toggle');
    const statusLabel = document.getElementById('status-label');
    const readerDiv = document.getElementById('reader');
    const resultDiv = document.getElementById('result');
    let isScannerOn = false;

    toggleBtn.addEventListener('click', () => {
        if (!isScannerOn) {
            readerDiv.style.display = 'block';
            html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess)
            .then(() => {
                isScannerOn = true;
                toggleBtn.innerText = "Turn Camera Off";
                toggleBtn.style.background = "#dc2626";
                toggleBtn.style.border = "none";
                statusLabel.innerText = "SCANNER ON";
                statusLabel.style.color = "#22c55e";
                resultDiv.innerText = "Ready to scan...";
                resultDiv.style.background = "#0a0a0a";
                resultDiv.style.color = "#ffffff";
            })
            .catch(err => {
                console.error("Scanner Error:", err);
                isScannerOn = false;
                readerDiv.style.display = 'none';
                toggleBtn.innerText = "Turn Camera On";
                toggleBtn.style.background = "#262626";
                statusLabel.innerText = "SCANNER OFF";
                statusLabel.style.color = "#ef4444";
                resultDiv.innerText = "Camera access denied or unavailable.";
                resultDiv.style.background = "#450a0a";
                resultDiv.style.color = "#fca5a5";
            });
        } else {
            html5QrCode.stop().then(() => {
                isScannerOn = false;
                readerDiv.style.display = 'none';
                toggleBtn.innerText = "Turn Camera On";
                toggleBtn.style.background = "#262626";
                toggleBtn.style.border = "1px solid #404040";
                statusLabel.innerText = "SCANNER OFF";
                statusLabel.style.color = "#ef4444";
                resultDiv.innerText = "Scanner is currently disabled";
                resultDiv.style.background = "#18181b";
                resultDiv.style.color = "#a1a1aa";
            });
        }
    });

    function onScanSuccess(decodedText) {
        resultDiv.innerText = "Processing Scan...";
        resultDiv.style.background = "#262626";
        resultDiv.style.color = "#ffffff";
        html5QrCode.pause();
        let formData = new FormData();
        formData.append('qr_id', decodedText);

        fetch('check_member.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                try {
                    new Audio('https://assets.mixkit.co/active_storage/sfx/701/701-preview.mp3').play();
                } catch (e) {}

                resultDiv.innerText = "✔ " + data.message;
                resultDiv.style.background = "#991b1b";
                resultDiv.style.color = "#ffffff";

                setTimeout(() => location.reload(), 1500);
            } else {
                resultDiv.innerText = "✖ " + data.message;
                resultDiv.style.background = "#450a0a";
                resultDiv.style.color = "#fca5a5";
                setTimeout(() => html5QrCode.resume(), 3000);
            }
        })
        .catch(err => {
            resultDiv.innerText = "Connection Error";
            resultDiv.style.background = "#450a0a";
            resultDiv.style.color = "#fca5a5";
            setTimeout(() => html5QrCode.resume(), 3000);
        });
    }
</script>

</body>
</html>