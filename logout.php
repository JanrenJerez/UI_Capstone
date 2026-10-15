<?php
session_start();
session_unset();
session_destroy();

echo "<script>sessionStorage.clear(); window.location.href='index.php';</script>";
exit();
?>