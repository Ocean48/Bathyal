<?php
session_start();
require_once '../core/database.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$db = new DBQueries($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'update_preferences') {
        $userId = $_SESSION['user_id'] ?? 1; // Fallback to 1
        $preferences = isset($data['preferences']) ? json_encode($data['preferences']) : null;
        if ($db->updateUserPreferences($userId, $preferences)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update preferences']);
        }
        exit;
    }
}

$search = $_GET['search'] ?? '';
$projectId = $_GET['project_id'] ?? null;
$excludeProjectId = $_GET['exclude_project_id'] ?? null;

$users = $db->searchUsers($search, $projectId, null, $excludeProjectId);

echo json_encode($users);



