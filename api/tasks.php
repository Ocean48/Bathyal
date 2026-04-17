<?php
// /api/tasks.php

require_once '../core/database.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = new DBQueries($pdo);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $taskId = (int)$_GET['id'];
            $task = $db->getTaskById($taskId);
            if ($task) {
                // Fetch comments for this task
                $task['comments'] = $db->getCommentsByTaskId($taskId);
                
                // Fetch subtasks strictly bound to this id
                $task['subtasks'] = $db->getTaskSubtasks($taskId);

                echo json_encode($task);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
            }
        } else {
            echo json_encode($db->getAllTasks());
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['action'])) {
            if ($data['action'] === 'update_status') {
                if ($db->updateTaskStatus($data['task_id'], $data['status'])) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update task status']);
                }
            } elseif ($data['action'] === 'update_details') {
                if ($db->updateTaskDetails($data['task_id'], $data['details'])) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update task details']);
                }
            } elseif ($data['action'] === 'add_comment') {
                // Mock user ID 1 for now (admin user)
                $userId = 1; 
                $commentId = $db->createComment($data['task_id'], $userId, $data['content']);
                if ($commentId) {
                    echo json_encode(['status' => 'success', 'comment_id' => $commentId]);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to create comment']);
                }
            } elseif ($data['action'] === 'link_subtask') {
                $parentId = $data['parent_task_id'];
                $subtaskId = $data['subtask_id'];
                if ($db->linkSubtask($parentId, $subtaskId)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to link subtask']);
                }
            } elseif ($data['action'] === 'unlink_subtask') {
                $parentId = $data['parent_task_id'];
                $subtaskId = $data['subtask_id'];
                if ($db->unlinkSubtask($parentId, $subtaskId)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to unlink subtask']);
                }
            } elseif ($data['action'] === 'reorder') {
                $sectionId = isset($data['section_id']) ? $data['section_id'] : null;
                $taskIds = isset($data['task_ids']) ? $data['task_ids'] : [];
                $parentTaskId = isset($data['parent_task_id']) ? $data['parent_task_id'] : null;
                $draggedTaskId = isset($data['dragged_task_id']) ? $data['dragged_task_id'] : null;
                
                if ($db->reorderTasks($sectionId, $taskIds, $parentTaskId, $draggedTaskId)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to reorder tasks']);
                }
            }
        } else { // Create task
            $taskId = $db->createTask($data);
            if ($taskId) {
                echo json_encode(['status' => 'success', 'task_id' => $taskId]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to create task']);
            }
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
?>