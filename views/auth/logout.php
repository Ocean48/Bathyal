<?php
// /logout.php
session_start();
session_destroy();
header("Location: /Bathyal/login.php");
exit;
?>