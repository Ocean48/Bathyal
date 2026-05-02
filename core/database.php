<?php
// /config/database.php

// Enable error reporting to help with debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set PHP default timezone to UTC
date_default_timezone_set('UTC');

// Fetching secure credentials from Apache environment variables (SetEnv in .htaccess)
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'bathyal_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'", // Ensure MySQL connection is UTC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

/**
 * Helper to convert a UTC datetime string (from DB) to America/Toronto
 */
function convertUtcToToronto($utcDateStr, $format = 'Y-m-d H:i:s') {
    if (empty($utcDateStr)) return null;
    try {
        $dt = new DateTime($utcDateStr, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Toronto'));
        return $dt->format($format);
    } catch (Exception $e) {
        return $utcDateStr;
    }
}

/**
 * Helper to convert an America/Toronto datetime string (from frontend) to UTC for DB storage
 */
function convertTorontoToUtc($torontoDateStr, $format = 'Y-m-d H:i:s') {
    if (empty($torontoDateStr)) return null;
    try {
        $dt = new DateTime($torontoDateStr, new DateTimeZone('America/Toronto'));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format($format);
    } catch (Exception $e) {
        return $torontoDateStr;
    }
}
?>
