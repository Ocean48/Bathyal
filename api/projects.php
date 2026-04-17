<?php
// /api/projects.php

require_once '../core/database.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = new DBQueries($pdo);

if ($method === 'GET') {
    // Default to project ID 1 for testing purposes if not provided
    $projectId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

    try {
        // Fetch Project details
        $project = $db->getProjectById($projectId);

        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }

        // Fetch Sections
        $sections = $db->getSectionsByProjectId($projectId);

        // Fetch Tasks mapped to this project, including assignee name and subtasks recursively
        $tasks = $db->getTasksByProjectId($projectId);

        // Group tasks into their respective sections
        foreach ($sections as &$section) {
            // Filter tasks for this section
            $sectionTasks = array_filter($tasks, function($task) use ($section) {
                return $task['section_id'] == $section['id'];
            });
            // Re-index array
            $section['tasks'] = array_values($sectionTasks);
        }

        $project['sections'] = $sections;

        echo json_encode($project);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
    }

    $projectId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['project_id']) ? (int)$data['project_id'] : 1);

    if ($data['action'] === 'add_section') {
        try {
            $name = $data['name'];
            $db->createSection($projectId, $name);
            echo json_encode(['status' => 'success']);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($data['action'] === 'update_members') {
        try {
            $memberIds = isset($data['member_ids']) ? $data['member_ids'] : [];
            
            // For now, let's just make everyone 'member' role initially. 
            // The request could optionally send roles, but we'll default to 'member'.
            
            $pdo->beginTransaction();
            // Delete old members
            $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = :pid");
            $stmt->execute(['pid' => $projectId]);

            // Insert new ones
            if (!empty($memberIds)) {
                // Ensure unique IDs
                $memberIds = array_unique(array_filter($memberIds, function($id) { return (int)$id > 0; }));
                $insertStmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (:pid, :uid, 'member')");
                foreach ($memberIds as $uid) {
                    $insertStmt->execute(['pid' => $projectId, 'uid' => (int)$uid]);
                }
            }
            $pdo->commit();
            
            echo json_encode(['status' => 'success']);
        } catch (\PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>