<?php
// /api/sections.php
require_once '../core/database.php';
require_once '../core/auth_check.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$db = new DBQueries($pdo);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$action = $input['action'] ?? '';

try {
    if ($action === 'create') {
        $projectId = (int)$input['project_id'];
        $name = trim($input['name']);

        if (!$name || !$projectId) {
            throw new Exception("Project ID and Section Name are required.");
        }

        // Determine next position using DBQueries inside
        $nextPos = $db->getNextSectionPosition($projectId);
        $sectionId = $db->createSection($projectId, $name, $nextPos);

        echo json_encode(['success' => true, 'section_id' => $sectionId]);
        exit;
    }

    if ($action === 'update') {
        $sectionId = (int)$input['section_id'];
        $name = trim($input['name']);

        if (!$name || !$sectionId) {
            throw new Exception("Section ID and new Name are required.");
        }

        $db->updateSection($sectionId, $name);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $sectionId = (int)$input['section_id'];

        if (!$sectionId) {
            throw new Exception("Section ID is required.");
        }

        $db->deleteSection($sectionId);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reorder') {
        $sectionIds = $input['section_ids'] ?? [];
        
        $db->reorderSections($sectionIds);
        
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception("Invalid action.");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

