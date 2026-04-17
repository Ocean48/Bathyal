<?php
require_once '../core/database.php';
header('Content-Type: application/json');

$search = $_GET['search'] ?? '';

$sql = "SELECT id, name, email FROM users";
$params = [];

if ($search !== '') {
    $sql .= " WHERE name LIKE ? OR email LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY name ASC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($users);
