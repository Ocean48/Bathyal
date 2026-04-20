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
                
                // Fetch attachments for this task
                $task['attachments'] = $db->getTaskAttachments($taskId);
                
                // Fetch subtasks strictly bound to this id
                $task['subtasks'] = $db->getTaskSubtasks($taskId);

                // Fetch time log status
                $userId = 1; // Mock user ID for now
                $task['time_log_status'] = $db->getTaskTimeLogStatus($taskId, $userId);

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
        if (isset($_POST['action']) && $_POST['action'] === 'add_attachment') {
            $taskId = $_POST['task_id'];
            $file = $_FILES['file'];
            $userId = 1; // Mock user ID
            
            $uploadDir = '../assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $dest = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $filePath = 'assets/uploads/' . $filename;
                $insertedId = $db->createTaskAttachment($taskId, $userId, $file['name'], $filePath, $file['type']);
                if ($insertedId) {
                    $taskDetails = $db->getTaskById($taskId);
                    $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $taskId;
                    $subject = "New Attachment on Task: " . $taskTitle;
                    $bodyHtml = "<h2>A new attachment was added to the task: " . $taskTitle . "</h2>";
                    $bodyHtml .= "<p><strong>File:</strong> " . htmlspecialchars($file['name']) . "</p>";
                    $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $taskId . "'>Click here to view the task</a></p>";
                    $notifResult = $db->sendTaskNotification($taskId, "New attachment added: " . $file['name'], 'task_update', $subject, $bodyHtml);

                    $response = ['status' => 'success', 'attachment_id' => $insertedId];
                    if (isset($notifResult['success']) && !$notifResult['success']) {
                        $response['email_error'] = 'Attachment uploaded, but failed to send email notification: ' . $notifResult['error'];
                    }
                    
                    echo json_encode($response);
                    exit;
                }
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload attachment']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['action'])) {
            if ($data['action'] === 'update_status') {
                if ($db->updateTaskStatus($data['task_id'], $data['status'])) {
                    $taskDetails = $db->getTaskById($data['task_id']);
                    $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $data['task_id'];
                    $subject = "Task Status Updated: " . $taskTitle;
                    $bodyHtml = "<h2>Task status was updated</h2>";
                    $bodyHtml .= "<p><strong>Task:</strong> " . $taskTitle . "</p>";
                    $bodyHtml .= "<p><strong>New Status:</strong> " . htmlspecialchars($data['status']) . "</p>";
                    $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $data['task_id'] . "'>Click here to view the task</a></p>";
                    $notifResult = $db->sendTaskNotification($data['task_id'], "Task status updated to " . $data['status'], 'task_update', $subject, $bodyHtml);

                    $response = ['status' => 'success'];
                    if (isset($notifResult['success']) && !$notifResult['success']) {
                        $response['email_error'] = 'Task status updated, but failed to send email notification: ' . $notifResult['error'];
                    }
                    
                    echo json_encode($response);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update task status']);
                }
            } elseif ($data['action'] === 'update_details') {
                if ($db->updateTaskDetails($data['task_id'], $data['details'])) {
                    $taskDetails = $db->getTaskById($data['task_id']);
                    $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $data['task_id'];
                    $subject = "Task Details Updated: " . $taskTitle;
                    $bodyHtml = "<h2>Task details were updated</h2>";
                    $bodyHtml .= "<p><strong>Task:</strong> " . $taskTitle . "</p>";
                    $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $data['task_id'] . "'>Click here to view the task</a></p>";
                    $notifResult = $db->sendTaskNotification($data['task_id'], "Task details updated", 'task_update', $subject, $bodyHtml);

                    $response = ['status' => 'success'];
                    if (isset($notifResult['success']) && !$notifResult['success']) {
                        $response['email_error'] = 'Task details updated, but failed to send email notification: ' . $notifResult['error'];
                    }
                    
                    echo json_encode($response);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update task details']);
                }
            } elseif ($data['action'] === 'add_comment') {
                // Mock user ID 1 for now (admin user)
                $userId = 1;
                $commentId = $db->createComment($data['task_id'], $userId, $data['content']);
                if ($commentId) {
                    $taskDetails = $db->getTaskById($data['task_id']);
                    $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $data['task_id'];
                    $subject = "New Comment on Task: " . $taskTitle;
                    $bodyHtml = "<h2>A new comment was added to the task: " . $taskTitle . "</h2>";
                    $bodyHtml .= "<p><strong>Comment:</strong><br>" . nl2br(htmlspecialchars($data['content'])) . "</p>";
                    $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $data['task_id'] . "'>Click here to view the task</a></p>";
                    $notifResult = $db->sendTaskNotification($data['task_id'], "New comment added", 'comment', $subject, $bodyHtml);

                    $response = ['status' => 'success', 'comment_id' => $commentId];
                    if (isset($notifResult['success']) && !$notifResult['success']) {
                        $response['email_error'] = 'Comment added, but failed to send email notification: ' . $notifResult['error'];
                    }
                    
                    echo json_encode($response);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to create comment']);
                }
            } elseif ($data['action'] === 'edit_comment') {
                $userId = 1; // Assuming mock user ID 1
                $success = $db->updateComment($data['comment_id'], $userId, $data['content']);
                if ($success) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update comment or unauthorized']);
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
            } elseif ($data['action'] === 'delete_attachment') {
                $attachmentId = $data['attachment_id'];
                if ($db->deleteTaskAttachment($attachmentId)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to delete attachment']);
                }
            } elseif ($data['action'] === 'toggle_time_track') {
                $userId = 1; // Mock user ID
                $result = $db->toggleTaskTimeTrack($data['task_id'], $userId);
                echo json_encode(['status' => 'success', 'action' => $result['action']]);
            }
        } else { // Create task
            $taskId = $db->createTask($data);
            if ($taskId) {
                // Send Email Notification using the centralized notification method
                $subject = "New Task Created: " . $data['title'];
                $bodyHtml = "<h2>A new task was created!</h2>";
                $bodyHtml .= "<p><strong>Task:</strong> " . htmlspecialchars($data['title']) . "</p>";
                if (!empty($data['description'])) {
                    $bodyHtml .= "<p><strong>Description:</strong> " . nl2br(htmlspecialchars($data['description'])) . "</p>";
                }
                if (!empty($data['due_date'])) {
                    $bodyHtml .= "<p><strong>Due Date:</strong> " . htmlspecialchars($data['due_date']) . "</p>";
                }
                $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $taskId . "'>Click here to view the task</a></p>";
                
                $notifResult = $db->sendTaskNotification($taskId, "New task created: " . $data['title'], 'task_update', $subject, $bodyHtml);
                
                $response = ['status' => 'success', 'task_id' => $taskId];
                if (isset($notifResult['success']) && !$notifResult['success']) {
                    $response['email_error'] = 'Task created, but failed to send email notification: ' . $notifResult['error'];
                }
                
                echo json_encode($response);
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