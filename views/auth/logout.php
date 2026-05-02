<?php
// /logout.php
session_start();
session_destroy();
$config = require __DIR__ . '/../../config.php';
$base_path = $config['base_path'] ?? '';
header("Location: {$base_path}/login");
exit;
?>
