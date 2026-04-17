<?php
// /api/projects.php

require_once '../config/database.php';
require_once '../includes/db_query.php';

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

        // Fetch Tasks mapped to this project, including assignee name
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
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>