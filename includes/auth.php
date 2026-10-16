<?php
// PIN-based access control. Include on every protected page, after db.php.
// The unlocked state lives in the PHP session, so it can't be bypassed from the browser.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const IDLE_LOCK_SECONDS = 15 * 60; // server-side backstop; the page itself locks after 5 minutes

function is_unlocked() {
    if (empty($_SESSION['unlocked'])) return false;
    if (time() - ($_SESSION['last_activity'] ?? 0) > IDLE_LOCK_SECONDS) {
        lock_app();
        return false;
    }
    return true;
}

function lock_app() {
    unset($_SESSION['unlocked'], $_SESSION['last_activity']);
}

function unlock_app() {
    session_regenerate_id(true);
    $_SESSION['unlocked'] = true;
    $_SESSION['last_activity'] = time();
}

function require_unlocked() {
    if (is_unlocked()) {
        $_SESSION['last_activity'] = time();
        return;
    }

    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
        || isset($_POST['ajax_sale']) || isset($_POST['qr_id']);
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'App is locked. Enter your PIN.']);
        exit();
    }

    $next = $_SERVER['REQUEST_URI'] ?? '/index.php';
    header('Location: /lock.php?next=' . urlencode($next));
    exit();
}

// Only allow redirects back into this app
function safe_next($next) {
    if (!is_string($next) || $next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return '/index.php';
    }
    return $next;
}

function verify_pin(PDO $pdo, $pin) {
    $hash = get_setting($pdo, 'app_pin');
    return $hash && password_verify((string) $pin, $hash);
}

function verify_admin_password(PDO $pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $hash = $stmt->fetchColumn();
    return $hash && password_verify((string) $password, $hash);
}

// Slow down guessing: after 5 wrong PIN/password attempts, wait 30 seconds.
// Stored in the database so clearing cookies doesn't reset it.
const MAX_AUTH_FAILURES = 5;
const AUTH_LOCKOUT_SECONDS = 30;

function auth_wait_seconds(PDO $pdo) {
    $until = (int) get_setting($pdo, 'auth_locked_until', 0);
    return max(0, $until - time());
}

function record_auth_failure(PDO $pdo) {
    $failures = (int) get_setting($pdo, 'auth_failures', 0) + 1;
    if ($failures >= MAX_AUTH_FAILURES) {
        set_setting($pdo, 'auth_locked_until', time() + AUTH_LOCKOUT_SECONDS);
        $failures = 0;
    }
    set_setting($pdo, 'auth_failures', $failures);
}

function clear_auth_failures(PDO $pdo) {
    set_setting($pdo, 'auth_failures', 0);
}

function is_valid_pin($pin) {
    return is_string($pin) && preg_match('/^[0-9]{4}$/', $pin);
}
?>
