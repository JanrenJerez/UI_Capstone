<?php
session_start();
session_unset();
session_destroy();
// Clear JS session too by redirecting to a page that handles it
echo "<script>sessionStorage.clear(); window.location.href='index.php';</script>";
exit();
?>