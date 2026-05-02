<?php
// /includes/auth_check.php
session_start();

$config = require __DIR__ . '/../config.php';
$base_path = $config['base_path'] ?? '';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$base_path}/login");
    exit;
}

// Fetch the current user details to have globally available
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/db_query.php';

$db = new DBQueries($pdo);
$currentUser = $db->getUserById($_SESSION['user_id']);

if (!$currentUser) {
    session_destroy();
    header("Location: {$base_path}/login");
    exit;
}
?>
