<?php
// Keeps the session unlocked while the dashboard's QR scanner is running (kiosk mode).
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_unlocked();

header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
