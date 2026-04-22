<?php
require_once '../core/database.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$db = new DBQueries($pdo);

$search = $_GET['search'] ?? '';
$projectId = $_GET['project_id'] ?? null;
$excludeProjectId = $_GET['exclude_project_id'] ?? null;

$users = $db->searchUsers($search, $projectId, null, $excludeProjectId);

echo json_encode($users);


