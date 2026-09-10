    <?php
    session_start();
    include __DIR__ . '/../includes/db.php';


    if (!isset($_SESSION['isAdminLoggedIn'])) {
        header("Location: ../login.php");
        exit();
    }

    include __DIR__ . '/../includes/nav.php';

    $resMembers = $conn->query("SELECT COUNT(*) as total FROM members");
    $totalMembers = $resMembers->fetch_assoc()['total'] ?? 0;

    $resAttendance = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE DATE(scan_time) = CURDATE()");
    $todayAttendance = $resAttendance->fetch_assoc()['total'] ?? 0;
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Dashboard | Project E</title>
        <link rel="stylesheet" href="../assets/style.css">
        <style>
            body { background: #0f172a; color: white; font-family: sans-serif;background-image: url('../assets/gym.jpg');background-repeat: no-repeat;background-attachment: fixed;background-size: cover;background-position: center; }
            .container { max-width: 1100px; margin: 50px auto; padding: 20px; }
            h1 { color: #38bdf8; margin-bottom: 40px; }
            .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; }
            .admin-card { background: white; color: #0f172a; padding: 30px; border-radius: 20px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
            .admin-card p { color: #64748b; text-transform: uppercase; font-size: 0.85rem; font-weight: 700; margin-bottom: 10px; }
            .admin-card h2 { font-size: 2.5rem; margin: 0; color: #0f172a; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        <div class="admin-grid">
            <div class="admin-card">
                <p>Total Registered Members</p>
                <h2><?php echo $totalMembers; ?></h2>
            </div>
            <div class="admin-card">
                <p>Attendance Today</p>
                <h2><?php echo $todayAttendance; ?></h2>
            </div>
            <div class="admin-card">
                <p>System Status</p>
                <h2 style="color: #22c55e;">ONLINE</h2>
            </div>
        </div>
    </div>
    </body>
    </html>