<?php
$host = getenv('DB_HOST') ;
$user = getenv('DB_USER') ;
$pass = getenv('DB_PASS') ;
$db   = getenv('DB_NAME') ;
$port = (int)(getenv('DB_PORT') );

$conn = mysqli_init();

// Set 5-second connection timeout to prevent hanging
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Attempt connection
if (!@$conn->real_connect($host, $user, $pass, $db, $port)) {
    die("Database Connection Failed (" . mysqli_connect_errno() . "): " . mysqli_connect_error());
}
?>