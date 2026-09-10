<?php
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['isAdminLoggedIn'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['delete_id'])) {
    $deleteID = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $conn->query("DELETE FROM members WHERE id = '$deleteID'");
    header("Location: Manage.php");
    exit();
}

$sql = "SELECT id, name, expiry_date, contact, address, qr_id FROM members";
$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | Project E</title>
    
    <style>
        * { box-sizing: border-box; }

        body {
            background: #0a0a0a;
            color: #ffffff;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            background-image:
                linear-gradient(rgba(10, 10, 10, 0.92), rgba(10, 10, 10, 0.92)),
                url('../assets/gym.jpg');
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

        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #dc2626;
            color: #ffffff;
            border: none;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            font-size: 1.8rem;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, background 0.2s;
            text-decoration: none;
        }

        .floating-btn:hover {
            background: #b91c1c;
            transform: scale(1.05);
        }

        @media print {
            .no-print, .floating-btn, #searchInput { display: none !important; }
            body { background: white; color: black; }
            .glass-panel { box-shadow: none; border: none; background: white; color: black; }
            td { color: black; }
            th { color: #333; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container-wide">

    <div class="glass-panel">
        <h1 class="page-title">Management Dashboard</h1>

        <div class="table-controls">
            <input 
                type="text" 
                id="searchInput" 
                placeholder="Search by name, contact, address..." 
                onkeyup="filterMembers()">
            
            <button onclick="window" class="btn-action btn-secondary no-print">
                Download Report (PDF)
            </button>
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
                    <?php while($row = $result->fetch_assoc()): 
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
                                    <button 
                                        onclick="viewQR('<?php echo htmlspecialchars($row['qr_id'], ENT_QUOTES); ?>')" 
                                        class="btn-action btn-secondary">
                                        QR
                                    </button>
                                    
                                    <a href="edit_members.php?id=<?php echo $row['id']; ?>" class="btn-action">
                                        Edit
                                    </a>
                                
                                    <a 
                                        href="Manage.php?delete_id=<?php echo $row['id']; ?>" 
                                        onclick="return confirm('Are you sure you want to delete this member?')" 
                                        class="btn-action btn-delete">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<a href="../admin/register.php" class="floating-btn no-print" title="Register New Member">+</a>

<script>
function filterMembers() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.querySelectorAll("#memberList tr");
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function viewQR(qrCode) {
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&color=0a0a0a&bgcolor=ffffff&data=${encodeURIComponent(qrCode)}`;
    const w = window.open("", "QR Code", "width=340,height=380");
    w.document.write(`
        <html>
        <head>
            <title>QR Pass - ${qrCode}</title>
            <style>
                body {
                    background: #0a0a0a;
                    color: #ffffff;
                    font-family: 'Segoe UI', system-ui, sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                }
                .card {
                    background: #121212;
                    border: 1px solid #262626;
                    border-radius: 12px;
                    padding: 24px;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.8);
                }
                img {
                    background: #ffffff;
                    padding: 12px;
                    border-radius: 8px;
                    margin-top: 12px;
                }
                h3 {
                    margin: 0;
                    color: #dc2626;
                    text-transform: uppercase;
                    font-size: 1rem;
                    letter-spacing: 1px;
                }
                p {
                    color: #a3a3a3;
                    font-size: 0.85rem;
                    margin-top: 10px;
                    font-family: monospace;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <h3>Member Access Key</h3>
                <img src="${qrUrl}" alt="QR Code">
                <p>ID: ${qrCode}</p>
            </div>
        </body>
        </html>
    `);
}
</script>

</body>
</html>