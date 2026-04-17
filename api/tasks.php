<?php
// /api/tasks.php

require_once '../config/database.php';
require_once '../includes/db_query.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = new DBQueries($pdo);

switch ($method) {
    case 'GET':
        echo json_encode($db->getAllTasks());
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($db->createTask($data)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create task']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
?>