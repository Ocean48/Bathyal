<?php
// /api/labels.php
session_start();

require_once '../core/database.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new DBQueries($pdo);
$method = $_SERVER['REQUEST_METHOD'];

// Fetch user role for permission checks
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRole = $stmt->fetchColumn();

if ($method === 'GET') {
    $labels = $db->getLabels();
    echo json_encode(['status' => 'success', 'data' => $labels]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    // Only admin and member can modify labels
    if (!in_array($userRole, ['admin', 'member'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    if ($action === 'create') {
        $name = trim($data['name'] ?? '');
        $color = trim($data['color'] ?? '');
        
        // Generate random color if not provided
        if (empty($color)) {
            $color = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
        }

        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Name is required']);
            exit;
        }
        $id = $db->createLabel($name, $color);
        echo json_encode(['status' => 'success', 'data' => ['id' => $id, 'name' => $name, 'color' => $color]]);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $color = trim($data['color'] ?? '');
        if (!$id || empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
            exit;
        }
        $db->updateLabel($id, $name, $color);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
            exit;
        }
        $db->deleteLabel($id);
        echo json_encode(['status' => 'success']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
