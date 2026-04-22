<?php
// /api/tasks.php
session_start();

require_once '../core/database.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = new DBQueries($pdo);

switch ($method) {
    case 'GET':
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Current user ID fallback
        if (isset($_GET['id'])) {
            $taskId = (int)$_GET['id'];
            $task = $db->getTaskById($taskId);
            if ($task) {
                // Check if user is member of the project
                if (!$db->isProjectMember($task['project_id'], $userId)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Forbidden']);
                    exit;
                }

                // Fetch comments for this task
                $task['comments'] = $db->getCommentsByTaskId($taskId);
                
                // Fetch attachments for this task
                $task['attachments'] = $db->getTaskAttachments($taskId);
                
                // Fetch subtasks strictly bound to this id
                $task['subtasks'] = $db->getTaskSubtasks($taskId);

                // Fetch time log status
                $task['time_log_status'] = $db->getTaskTimeLogStatus($taskId, $userId);

                // Fetch projects this task belongs to
                $task['projects'] = $db->getTaskProjects($taskId);

                echo json_encode($task);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
            }
        } else {
            echo json_encode($db->getAllTasks($userId));
        }
        break;
    case 'POST':
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Current user ID fallback
        $taskIdForCheck = isset($_POST['task_id']) ? $_POST['task_id'] : null;
        if (!$taskIdForCheck) {
            $dataCheck = json_decode(file_get_contents('php://input'), true);
            $taskIdForCheck = isset($dataCheck['task_id']) ? $dataCheck['task_id'] : null;
        }
        
        if ($taskIdForCheck) {
            $taskCheck = $db->getTaskById($taskIdForCheck);
            if ($taskCheck && !$db->isProjectMember($taskCheck['project_id'], $userId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit;
            }
        }

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

        $data = isset($dataCheck) ? $dataCheck : json_decode(file_get_contents('php://input'), true);
        
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
                $projectId = isset($data['project_id']) ? $data['project_id'] : null;
                if ($db->updateTaskDetails($data['task_id'], $data['details'], $projectId)) {
                    $response = ['status' => 'success'];
                    
                    $detailsKeys = array_keys($data['details']);
                    $onlyAssigneesOrCollabs = count(array_diff($detailsKeys, ['assignee_ids', 'collaborator_ids'])) === 0;

                    if (!$onlyAssigneesOrCollabs) {
                        $taskDetails = $db->getTaskById($data['task_id']);
                        $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $data['task_id'];
                        $subject = "Task Details Updated: " . $taskTitle;
                        $bodyHtml = "<h2>Task details were updated</h2>";
                        $bodyHtml .= "<p><strong>Task:</strong> " . $taskTitle . "</p>";
                        $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $data['task_id'] . "'>Click here to view the task</a></p>";
                        $notifResult = $db->sendTaskNotification($data['task_id'], "Task details updated", 'task_update', $subject, $bodyHtml);

                        if (isset($notifResult['success']) && !$notifResult['success']) {
                            $response['email_error'] = 'Task details updated, but failed to send email notification: ' . $notifResult['error'];
                        }
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
            } elseif ($data['action'] === 'add_to_project') {
                $taskId = $data['task_id'];
                $projectId = $data['project_id'];
                
                $sectionId = $db->addTaskToProject($taskId, $projectId);
                if ($sectionId !== false) {
                    echo json_encode(['status' => 'success', 'section_id' => $sectionId]);
                    exit;
                }
                
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to add to project']);
            } elseif ($data['action'] === 'change_project_section') {
                $taskId = $data['task_id'];
                $projectId = $data['project_id'];
                $sectionId = $data['section_id'];
                
                if ($db->changeTaskProjectSection($taskId, $projectId, $sectionId)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to change project section']);
                }
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
                if (!empty($data['expected_due_date'])) {
                    $bodyHtml .= "<p><strong>Expected End Date:</strong> " . htmlspecialchars(explode(' ', $data['expected_due_date'])[0]) . "</p>";
                }
                $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $taskId . "'>Click here to view the task</a></p>";
                
                $notifResult = $db->sendTaskNotification($taskId, "New task created: " . $data['title'], 'task_update', $subject, $bodyHtml, $data['project_id'] ?? null);
                
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