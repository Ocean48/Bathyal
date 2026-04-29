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
                // Fetch projects this task belongs to
                $taskProjects = $db->getTaskProjects($taskId);
                $isMember = false;
                
                if (empty($taskProjects)) {
                    $isMember = true; // Allow access if task is not attached to any project
                } else {
                    foreach ($taskProjects as $tp) {
                        if ($db->isProjectMember($tp['project_id'], $userId)) {
                            $isMember = true;
                            break;
                        }
                    }
                }
                
                if (!$isMember) {
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
        global $pdo;
        $stmtChanger = $pdo->prepare("SELECT name FROM users WHERE id = :uid");
        $stmtChanger->execute(['uid' => $userId]);
        $changerName = $stmtChanger->fetchColumn() ?: 'Someone';
        
        $taskIdForCheck = isset($_POST['task_id']) ? $_POST['task_id'] : null;
        if (!$taskIdForCheck) {
            $dataCheck = json_decode(file_get_contents('php://input'), true);
            $taskIdForCheck = isset($dataCheck['task_id']) ? $dataCheck['task_id'] : null;
        }
        
        if ($taskIdForCheck) {
            $taskCheck = $db->getTaskById($taskIdForCheck);
            if ($taskCheck) {
                $taskProjects = $db->getTaskProjects($taskIdForCheck);
                $isMember = false;
                if (empty($taskProjects)) {
                    $isMember = true;
                } else {
                    foreach ($taskProjects as $tp) {
                        if ($db->isProjectMember($tp['project_id'], $userId)) {
                            $isMember = true;
                            break;
                        }
                    }
                }
                if (!$isMember) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Forbidden']);
                    exit;
                }
            }
        }

        if (isset($_POST['action']) && $_POST['action'] === 'add_attachment') {
            $taskId = $_POST['task_id'];
            $file = $_FILES['file'];
            // use the $userId from above instead of hardcoding 1
            
            $uploadDir = '../assets/uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
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
                    $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> attached the file: " . htmlspecialchars($file['name']) . "</p>";
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
            
            $error_message = 'Failed to upload attachment.';
            if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_message = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'The uploaded file was only partially uploaded.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'No file was uploaded.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = 'Missing a temporary folder.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = 'Failed to write file to disk. Check folder permissions.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = 'A PHP extension stopped the file upload.';
                        break;
                    default:
                        $error_message .= ' Error code: ' . $file['error'];
                        break;
                }
            } elseif (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                $error_message = 'Upload directory does not exist or is not writable by the web server.';
            }
            
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $error_message, 'file_error' => isset($file['error']) ? $file['error'] : 'none']);
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
                    $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> updated the status to: <strong>" . htmlspecialchars($data['status']) . "</strong></p>";
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
                $oldTaskDetails = $db->getTaskById($data['task_id']);
                
                if ($db->updateTaskDetails($data['task_id'], $data['details'], $projectId)) {
                    $response = ['status' => 'success'];
                    
                    // Mention Notification for Description Update
                    if (isset($data['details']['description'])) {
                        $oldDesc = isset($oldTaskDetails['description']) ? $oldTaskDetails['description'] : '';
                        $newDesc = $data['details']['description'];
                        
                        preg_match_all('/data-mention-user-id="(\d+)"/', $oldDesc, $oldMatches);
                        $oldMentions = isset($oldMatches[1]) ? array_unique(array_map('intval', $oldMatches[1])) : [];
                        
                        preg_match_all('/data-mention-user-id="(\d+)"/', $newDesc, $newMatches);
                        $newMentions = isset($newMatches[1]) ? array_unique(array_map('intval', $newMatches[1])) : [];
                        
                        $addedMentions = array_diff($newMentions, $oldMentions);
                        
                        if (!empty($addedMentions)) {
                            $db->sendMentionNotification($data['task_id'], $addedMentions, $changerName, nl2br(htmlspecialchars($newDesc)));
                        }
                    }

                    // Notify admins and collaborators on ANY detail update
                    $taskDetails = $db->getTaskById($data['task_id']);
                    $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $data['task_id'];
                    $subject = "Task Details Updated: " . $taskTitle;
                    
                    $changes = [];
                    foreach ($data['details'] as $k => $v) {
                        if ($k === 'assignee_ids') {
                            $oldDisplay = !empty($oldTaskDetails['assignee_name']) ? htmlspecialchars($oldTaskDetails['assignee_name']) : '<em>Unassigned</em>';
                            $newDisplay = !empty($taskDetails['assignee_name']) ? htmlspecialchars($taskDetails['assignee_name']) : '<em>Unassigned</em>';
                            if ($oldDisplay !== $newDisplay) {
                                $changes[] = "<li><strong>Assignees:</strong> changed from {$oldDisplay} to {$newDisplay}</li>";
                            }
                            continue;
                        }
                        if ($k === 'collaborator_ids') {
                            $oldDisplay = !empty($oldTaskDetails['collaborator_name']) ? htmlspecialchars($oldTaskDetails['collaborator_name']) : '<em>None</em>';
                            $newDisplay = !empty($taskDetails['collaborator_name']) ? htmlspecialchars($taskDetails['collaborator_name']) : '<em>None</em>';
                            if ($oldDisplay !== $newDisplay) {
                                $changes[] = "<li><strong>Collaborators:</strong> changed from {$oldDisplay} to {$newDisplay}</li>";
                            }
                            continue;
                        }
                        if ($k === 'label_ids') {
                            $oldDisplay = !empty($oldTaskDetails['label_names']) ? htmlspecialchars($oldTaskDetails['label_names']) : '<em>None</em>';
                            $newDisplay = !empty($taskDetails['label_names']) ? htmlspecialchars($taskDetails['label_names']) : '<em>None</em>';
                            if ($oldDisplay !== $newDisplay) {
                                $changes[] = "<li><strong>Labels:</strong> changed from {$oldDisplay} to {$newDisplay}</li>";
                            }
                            continue;
                        }
                        
                        $oldVal = isset($oldTaskDetails[$k]) ? (string)$oldTaskDetails[$k] : '';
                        $newVal = isset($taskDetails[$k]) ? (string)$taskDetails[$k] : '';
                        
                        if ($oldVal === $newVal) continue; // No change
                        
                        $fieldName = ucwords(str_replace('_', ' ', $k));
                        if ($k === 'description') {
                            $changes[] = "<li><strong>{$fieldName}:</strong> [Content updated]</li>";
                        } else {
                            $oldDisplay = $oldVal === '' ? '<em>(empty)</em>' : htmlspecialchars($oldVal);
                            $newDisplay = $newVal === '' ? '<em>(empty)</em>' : htmlspecialchars($newVal);
                            
                            // Format dates nicely
                            if (in_array($k, ['start_date', 'expected_start_date', 'expected_due_date'])) {
                                $oldDisplay = $oldVal === '' ? '<em>(empty)</em>' : htmlspecialchars(explode(' ', $oldVal)[0]);
                                $newDisplay = $newVal === '' ? '<em>(empty)</em>' : htmlspecialchars(explode(' ', $newVal)[0]);
                            }
                            
                            // It's possible that formatting makes them look the same (e.g. same date, different time)
                            if ($oldDisplay !== $newDisplay || !in_array($k, ['start_date', 'expected_start_date', 'expected_due_date'])) {
                                $changes[] = "<li><strong>{$fieldName}:</strong> changed from {$oldDisplay} to {$newDisplay}</li>";
                            }
                        }
                    }
                    $changesStr = implode('', $changes);
                    
                    if (!empty($changesStr)) {
                        $bodyHtml = "<h2>Task details were updated</h2>";
                        $bodyHtml .= "<p><strong>Task:</strong> " . $taskTitle . "</p>";
                        $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> updated the following fields:</p>";
                        $bodyHtml .= "<ul>" . $changesStr . "</ul>";
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
            } elseif ($data['action'] === 'reset_timer') {
                $userId = $_SESSION['user_id'] ?? 1;
                $projectId = isset($data['project_id']) ? (int)$data['project_id'] : 1;
                $role = $db->getProjectMemberRole($projectId, $userId);
                
                $stmtUser = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmtUser->execute([(int)$userId]);
                $sysRole = $stmtUser->fetchColumn();
                
                if ($sysRole === 'admin' || in_array($role, ['admin', 'manager', 'owner'])) {
                    if ($db->resetTaskTime($data['task_id'])) {
                        echo json_encode(['status' => 'success']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['status' => 'error', 'message' => 'Failed to reset timer']);
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
                }
            } elseif ($data['action'] === 'add_comment') {
                // Mock user ID 1 for now (admin user)
                $userId = 1;
                $commentId = $db->createComment($data['task_id'], $userId, $data['content']);
                if ($commentId) {
                    // Mention Notification for New Comment
                    preg_match_all('/data-mention-user-id="(\d+)"/', $data['content'], $matches);
                    $mentions = isset($matches[1]) ? array_unique(array_map('intval', $matches[1])) : [];
                    if (!empty($mentions)) {
                        $db->sendMentionNotification($data['task_id'], $mentions, $changerName, nl2br(htmlspecialchars($data['content'])));
                    }

                    $taskDetails = $db->getTaskById($data['task_id']);
                    $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $data['task_id'];
                    $subject = "New Comment on Task: " . $taskTitle;
                    $bodyHtml = "<h2>A new comment was added to the task: " . $taskTitle . "</h2>";
                    $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> commented:</p>";
                    $bodyHtml .= "<blockquote style='border-left: 4px solid #ddd; padding-left: 10px; margin-left: 0;'>" . nl2br(htmlspecialchars($data['content'])) . "</blockquote>";
                    $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $data['task_id'] . "'>Click here to view the task</a></p>";
                    $notifResult = $db->sendTaskNotification($data['task_id'], "New comment added", 'task_update', $subject, $bodyHtml);

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
                
                // Fetch old comment content
                global $pdo;
                $stmtOldComm = $pdo->prepare("SELECT content FROM comments WHERE id = :cid");
                $stmtOldComm->execute(['cid' => $data['comment_id']]);
                $oldContent = $stmtOldComm->fetchColumn();

                $success = $db->updateComment($data['comment_id'], $userId, $data['content']);
                if ($success) {
                    $response = ['status' => 'success'];
                    
                    // Add notification for comment edit
                    $stmtComm = $pdo->prepare("SELECT task_id FROM comments WHERE id = :cid");
                    $stmtComm->execute(['cid' => $data['comment_id']]);
                    $taskId = $stmtComm->fetchColumn();
                    if ($taskId) {
                        // Mention Notification for Edited Comment
                        $oldContentStr = $oldContent ? $oldContent : '';
                        $newContentStr = $data['content'] ? $data['content'] : '';
                        
                        preg_match_all('/data-mention-user-id="(\d+)"/', $oldContentStr, $oldMatches);
                        $oldMentions = isset($oldMatches[1]) ? array_unique(array_map('intval', $oldMatches[1])) : [];
                        
                        preg_match_all('/data-mention-user-id="(\d+)"/', $newContentStr, $newMatches);
                        $newMentions = isset($newMatches[1]) ? array_unique(array_map('intval', $newMatches[1])) : [];
                        
                        $addedMentions = array_diff($newMentions, $oldMentions);
                        
                        if (!empty($addedMentions)) {
                            $db->sendMentionNotification($taskId, $addedMentions, $changerName, nl2br(htmlspecialchars($newContentStr)));
                        }

                        $taskDetails = $db->getTaskById($taskId);
                        $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $taskId;
                        $subject = "Comment Edited on Task: " . $taskTitle;
                        $bodyHtml = "<h2>A comment was edited on the task: " . $taskTitle . "</h2>";
                        $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> updated their comment:</p>";
                        $bodyHtml .= "<blockquote style='border-left: 4px solid #ddd; padding-left: 10px; margin-left: 0;'>" . nl2br(htmlspecialchars($data['content'])) . "</blockquote>";
                        $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/tasks?id=" . $taskId . "'>Click here to view the task</a></p>";
                        $notifResult = $db->sendTaskNotification($taskId, "Comment edited", 'task_update', $subject, $bodyHtml);

                        if (isset($notifResult['success']) && !$notifResult['success']) {
                            $response['email_error'] = 'Comment edited, but failed to send email notification: ' . $notifResult['error'];
                        }
                    }
                    echo json_encode($response);
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
                $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> created the task: " . htmlspecialchars($data['title']) . "</p>";
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