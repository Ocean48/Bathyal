<?php
// /api/projects.php

require_once '../core/database.php';
require_once '../core/auth_check.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = new DBQueries($pdo);

if ($method === 'GET') {
    // Default to project ID 1 for testing purposes if not provided
    $projectId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

    try {
        if (!isset($currentUser['id']) || !$db->isProjectMember($projectId, $currentUser['id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

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

        // Fetch Default Notify Users
        $project['default_notify_ids'] = $db->getProjectDefaultNotifyIds($projectId);
        $project['default_notify_names'] = $db->getProjectDefaultNotifyNames($projectId);

        // Fetch the current user's role in this project
        // So the frontend can selectively hide/show management UI features
        $project['user_role'] = $db->getProjectMemberRole($projectId, $currentUser['id']) ?: 'viewer';

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

    if (!isset($currentUser['id']) || !$db->isProjectMember($projectId, $currentUser['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    if ($data['action'] === 'add_section') {
        try {
            $name = $data['name'];
            $db->createSection($projectId, $name);
            echo json_encode(['status' => 'success']);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($data['action'] === 'update_default_notify') {
        try {            // Verify permission
            $role = $db->getProjectMemberRole($projectId, $currentUser['id']);
            if ($role !== 'manager' && $role !== 'member') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
                exit;
            }
            $memberIds = isset($data['member_ids']) ? $data['member_ids'] : [];
            $db->updateProjectDefaultNotify($projectId, $memberIds);
            echo json_encode(['status' => 'success']);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($data['action'] === 'update_status') {
        try {
            // Verify permission
            $role = $db->getProjectMemberRole($projectId, $currentUser['id']);
            if ($role !== 'manager' && $role !== 'member') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
                exit;
            }
            $status = isset($data['status']) ? $data['status'] : 'todo';
            $db->updateProjectStatus($projectId, $status);
            echo json_encode(['status' => 'success']);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($data['action'] === 'search') {
        try {
            $query = isset($data['query']) ? $data['query'] : '';
            $userId = isset($currentUser['id']) ? $currentUser['id'] : 1;
            $projects = $db->searchProjects($query, $userId, 10);
            echo json_encode(['status' => 'success', 'projects' => $projects]);
        } catch (\PDOException $e) {
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
