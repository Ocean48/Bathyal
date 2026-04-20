<?php
// /logout.php
session_start();
session_destroy();
header("Location: /bathyal/login.php");
exit;
?>