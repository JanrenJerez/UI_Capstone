<?php
// "Lock" the app: drop the unlocked state and go back to the PIN screen.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

lock_app();
header('Location: /lock.php');
exit();
