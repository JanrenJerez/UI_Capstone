<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($_POST['qr_id']) || trim($_POST['qr_id']) === '') {
    echo json_encode(['status' => 'error', 'message' => 'No ID detected.']);
    exit;
}

$scanned_code = mysqli_real_escape_string($conn, trim($_POST['qr_id']));

$member_check = "SELECT * FROM members WHERE qr_id = '$scanned_code' LIMIT 1";
$member_result = mysqli_query($conn, $member_check);

if ($member_result && mysqli_num_rows($member_result) > 0) {
    $member_data = mysqli_fetch_assoc($member_result);
    
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

    $log_query = "INSERT INTO attendance (member_id, scan_time) VALUES ('$member_id', NOW())";
    
    if (mysqli_query($conn, $log_query)) {
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
?>