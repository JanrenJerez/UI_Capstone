<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($_POST['qr_id']) || trim($_POST['qr_id']) === '') {
    echo json_encode(['status' => 'error', 'message' => 'No ID detected.']);
    exit;
}

$scanned_code = trim($_POST['qr_id']);

try {
    $stmt = $conn->prepare("SELECT * FROM members WHERE qr_id = ? LIMIT 1");
    $stmt->execute([$scanned_code]);
    $member_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member_data) {
        $member_name = $member_data['name'];
        $member_id = $member_data['id']; 
        
        $expiry_raw = $member_data['expiry_date'];
        $expiry_date = strtotime($expiry_raw);
        
        $today = strtotime(date("Y-m-d"));

        if ($expiry_date < $today) {
            echo json_encode([
                'status' => 'error', 
                'message' => "Expired: $member_name"
            ]);
            exit;
        }

        $log_stmt = $conn->prepare("INSERT INTO attendance (member_id, scan_time) VALUES (?, NOW())");
        
        if ($log_stmt->execute([$member_id])) {
            echo json_encode([
                'status' => 'success', 
                'message' => "Welcome!",
                'member_name' => $member_name
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Database log error.'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => "Invalid QR Code."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error.'
    ]);
}
?>