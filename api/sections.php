<?php
// /api/sections.php
require_once '../core/database.php';
require_once '../core/auth_check.php';

header('Content-Type: application/json');

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

        // Determine next position
        $stmtPos = $pdo->prepare("SELECT IFNULL(MAX(position), 0) + 1 FROM sections WHERE project_id = :pid");
        $stmtPos->execute(['pid' => $projectId]);
        $nextPos = $stmtPos->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO sections (project_id, name, position) VALUES (:pid, :name, :pos)");
        $stmt->execute(['pid' => $projectId, 'name' => $name, 'pos' => $nextPos]);

        echo json_encode(['success' => true, 'section_id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'update') {
        $sectionId = (int)$input['section_id'];
        $name = trim($input['name']);

        if (!$name || !$sectionId) {
            throw new Exception("Section ID and new Name are required.");
        }

        $stmt = $pdo->prepare("UPDATE sections SET name = :name WHERE id = :sid");
        $stmt->execute(['name' => $name, 'sid' => $sectionId]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $sectionId = (int)$input['section_id'];

        if (!$sectionId) {
            throw new Exception("Section ID is required.");
        }

        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = :sid");
        $stmt->execute(['sid' => $sectionId]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reorder') {
        $sectionIds = $input['section_ids'] ?? [];
        
        if (is_array($sectionIds) && count($sectionIds) > 0) {
            $stmt = $pdo->prepare("UPDATE sections SET position = :pos WHERE id = :sid");
            foreach ($sectionIds as $index => $sid) {
                $stmt->execute(['pos' => $index + 1, 'sid' => (int)$sid]);
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception("Invalid action.");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
