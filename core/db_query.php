<?php
// /includes/db_query.php

class DBQueries {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // --- USERS & AUTH ---
    public function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function createUser($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (name, email, password_hash, team_id, role) 
            VALUES (:name, :email, :password_hash, :team_id, :role)
        ");
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $data['password_hash'], PDO::PARAM_STR);
        $stmt->bindValue(':team_id', $data['team_id'] ?? null, $data['team_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':role', $data['role'] ?? 'member', PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    public function getTeamMembers($teamId) {
        $stmt = $this->pdo->prepare("SELECT id, name, email, role, created_at FROM users WHERE team_id = :team_id ORDER BY role ASC, name ASC");
        $stmt->bindValue(':team_id', (int)$teamId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateUserRole($userId, $teamId, $role) {
        $stmt = $this->pdo->prepare("UPDATE users SET role = :role WHERE id = :user_id AND team_id = :team_id");
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':team_id', (int)$teamId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // --- PROJECTS AND SECTIONS ---
    public function getProjectById($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   GROUP_CONCAT(u.id ORDER BY u.id SEPARATOR ',') AS member_ids,
                   GROUP_CONCAT(u.name ORDER BY u.id SEPARATOR ',') AS member_names,
                   GROUP_CONCAT(pm.role ORDER BY u.id SEPARATOR ',') AS member_roles
            FROM projects p
            LEFT JOIN project_members pm ON p.id = pm.project_id
            LEFT JOIN users u ON pm.user_id = u.id
            WHERE p.id = :project_id
            GROUP BY p.id
        ");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        $project = $stmt->fetch();
        return $project;
    }

    public function getSectionsByProjectId($projectId) {
        $stmt = $this->pdo->prepare("SELECT * FROM sections WHERE project_id = :project_id ORDER BY position ASC");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createSection($projectId, $name, $position = 0) {
        $stmt = $this->pdo->prepare("INSERT INTO sections (project_id, name, position) VALUES (:pid, :name, :pos)");
        $stmt->bindValue(':pid', (int)$projectId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':pos', (int)$position, PDO::PARAM_INT);
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    // --- TASKS ---
    public function getTasksByProjectId($projectId) {
        // Fetch top-level tasks
        $stmt = $this->pdo->prepare("
            SELECT t.*, tp.section_id, tp.position as tp_position, 
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids 
            FROM tasks t
            JOIN task_projects tp ON t.id = tp.task_id
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE tp.project_id = :project_id AND t.parent_task_id IS NULL
            GROUP BY t.id, tp.section_id, tp.position
            ORDER BY tp.position ASC
        ");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch subtasks recursively
        $stmtAll = $this->pdo->prepare("
            SELECT t.*, 
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids 
            FROM tasks t
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE t.id IN (
                WITH RECURSIVE TaskTree AS (
                    SELECT task_id FROM task_projects WHERE project_id = :pid
                    UNION ALL
                    SELECT t2.id FROM tasks t2 INNER JOIN TaskTree tt ON t2.parent_task_id = tt.task_id
                ) SELECT task_id FROM TaskTree
            ) AND t.parent_task_id IS NOT NULL
            GROUP BY t.id
        ");
        $stmtAll->bindValue(':pid', (int)$projectId, PDO::PARAM_INT);
        $stmtAll->execute();
        $allSubtasks = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // Build tree
        $subtasksByParent = [];
        foreach ($allSubtasks as $sub) {
            $subtasksByParent[$sub['parent_task_id']][] = $sub;
        }

        $buildTree = function(&$parentTasks) use (&$buildTree, &$subtasksByParent) {
            foreach ($parentTasks as &$task) {
                $task['subtasks'] = $subtasksByParent[$task['id']] ?? [];
                if (!empty($task['subtasks'])) {
                    $buildTree($task['subtasks']);
                }
            }
        };

        $buildTree($tasks);
        return $tasks;
    }

    public function getAllTasks() {
        $stmt = $this->pdo->query("SELECT * FROM tasks");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTask($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (parent_task_id, title, description, status, due_date) 
            VALUES (:parent_task_id, :title, :description, :status, :due_date)
        ");
        $stmt->bindValue(':parent_task_id', isset($data['parent_task_id']) ? (int)$data['parent_task_id'] : null, isset($data['parent_task_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, $data['description'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $data['status'] ?? 'todo', PDO::PARAM_STR);
        $stmt->bindValue(':due_date', $data['due_date'] ?? null, $data['due_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        $stmt->execute();
        $newTaskId = $this->pdo->lastInsertId();

        // Add assignee if provided
        if (!empty($data['assignees']) && is_array($data['assignees'])) {
            $stmtA = $this->pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (:tid, :uid)");
            foreach ($data['assignees'] as $uid) {
                $stmtA->bindValue(':tid', $newTaskId, PDO::PARAM_INT);
                $stmtA->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
                $stmtA->execute();
            }
        }

        // If this is a top-level task for a project, link it
        if (isset($data['project_id']) && empty($data['parent_task_id'])) {
            $stmt2 = $this->pdo->prepare("INSERT INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, :pos)");
            $stmt2->bindValue(':tid', $newTaskId, PDO::PARAM_INT);
            $stmt2->bindValue(':pid', (int)$data['project_id'], PDO::PARAM_INT);
            $stmt2->bindValue(':sid', isset($data['section_id']) ? (int)$data['section_id'] : null, isset($data['section_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt2->bindValue(':pos', 0, PDO::PARAM_INT);
            $stmt2->execute();
        }

        return $newTaskId;
    }

    public function updateTaskStatus($taskId, $status) {
        $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function reorderTasks($sectionId, $taskIds, $parentTaskId = null, $draggedTaskId = null) {
        if (!empty($draggedTaskId)) {
            // Update the single dragged task's parent
            $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = :pid WHERE id = :tid");
            $stmtTask->execute([
                'pid' => $parentTaskId ?: null,
                'tid' => (int)$draggedTaskId
            ]);
            
            if ($parentTaskId) {
                // Remove from task_projects if it's now a subtask
                $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid");
                $stmtDel->execute(['tid' => (int)$draggedTaskId]);
            }
            
            // Re-order top-level tasks based on visual order
            if ($sectionId) {
                $pos = 1;
                foreach ($taskIds as $tid) {
                    $tid = (int)$tid;
                    // Check if parent is null
                    $stmtCheckParent = $this->pdo->prepare("SELECT parent_task_id FROM tasks WHERE id = :tid");
                    $stmtCheckParent->execute(['tid' => $tid]);
                    $tData = $stmtCheckParent->fetch();
                    if ($tData && is_null($tData['parent_task_id'])) {
                        $stmtPos = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid, position = :pos WHERE task_id = :tid");
                        $stmtPos->execute(['sid' => (int)$sectionId, 'pos' => $pos, 'tid' => $tid]);
                        
                        // We must ensure the row exists, rowCount() might be 0 if the values are identical
                        $stmtCheckExist = $this->pdo->prepare("SELECT COUNT(*) FROM task_projects WHERE task_id = :tid AND section_id = :sid");
                        $stmtCheckExist->execute(['tid' => $tid, 'sid' => (int)$sectionId]);
                        $exists = $stmtCheckExist->fetchColumn();
                        
                        if ($exists == 0) {
                            $stmtCheck = $this->pdo->prepare("SELECT project_id FROM sections WHERE id = :sid");
                            $stmtCheck->execute(['sid' => (int)$sectionId]);
                            $sec = $stmtCheck->fetch();
                            if ($sec) {
                                $stmtIns = $this->pdo->prepare("INSERT IGNORE INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, :pos)");
                                $stmtIns->execute(['tid' => $tid, 'pid' => $sec['project_id'], 'sid' => (int)$sectionId, 'pos' => $pos]);
                            }
                        }
                        $pos++;
                    }
                }
            }
        } else {
            // Kanban View scenario
            if ($parentTaskId) {
                // Dropped in a subtask list. All task_ids now belong to parentTaskId
                $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = :pid WHERE id = :tid");
                $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid");
                foreach ($taskIds as $tid) {
                    $stmtTask->execute(['pid' => (int)$parentTaskId, 'tid' => (int)$tid]);
                    $stmtDel->execute(['tid' => (int)$tid]);
                }
            } else {
                // Dropped in a section column. All task_ids are top-level and belong to sectionId.
                $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = NULL WHERE id = :tid");
                $stmtPos = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid, position = :pos WHERE task_id = :tid");
                
                foreach ($taskIds as $index => $tid) {
                    $tid = (int)$tid;
                    $stmtTask->execute(['tid' => $tid]);
                    $stmtPos->execute(['sid' => (int)$sectionId, 'pos' => $index + 1, 'tid' => $tid]);
                    
                    $stmtCheckExist = $this->pdo->prepare("SELECT COUNT(*) FROM task_projects WHERE task_id = :tid AND section_id = :sid");
                    $stmtCheckExist->execute(['tid' => $tid, 'sid' => (int)$sectionId]);
                    $exists = $stmtCheckExist->fetchColumn();
                    
                    if ($exists == 0) {
                        $stmtCheck = $this->pdo->prepare("SELECT project_id FROM sections WHERE id = :sid");
                        $stmtCheck->execute(['sid' => (int)$sectionId]);
                        $sec = $stmtCheck->fetch();
                        if ($sec) {
                            $stmtIns = $this->pdo->prepare("INSERT IGNORE INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, :pos)");
                            $stmtIns->execute(['tid' => $tid, 'pid' => $sec['project_id'], 'sid' => (int)$sectionId, 'pos' => $index + 1]);
                        }
                    }
                }
            }
        }
        
        return true;
    }

    public function getTaskById($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids 
            FROM tasks t 
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id 
            WHERE t.id = :id
            GROUP BY t.id
        ");
        $stmt->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTaskSubtasks($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.title, t.status, 
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids 
            FROM tasks t 
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id 
            WHERE t.parent_task_id = :task_id
               OR t.id IN (SELECT subtask_id FROM task_links WHERE parent_id = :task_id_link)
            GROUP BY t.id
        ");
        $stmt->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
        $stmt->bindValue(':task_id_link', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateTaskDetails($taskId, $data) {
        $setClauses = [];
        $params = [':id' => (int)$taskId];

        if (array_key_exists('title', $data)) {
            $setClauses[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $setClauses[] = "description = :description";
            $params[':description'] = $data['description'];
        }
        if (array_key_exists('status', $data)) {
            $setClauses[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        if (array_key_exists('due_date', $data)) {
            $setClauses[] = "due_date = :due_date";
            $params[':due_date'] = $data['due_date'] ?: null;
        }

        if (array_key_exists('parent_task_id', $data)) {
            $setClauses[] = "parent_task_id = :parent_task_id";
            $params[':parent_task_id'] = $data['parent_task_id'];
        }

        if (!empty($setClauses)) {
            $sql = "UPDATE tasks SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $val) {
                // Determine type
                $type = is_int($val) ? PDO::PARAM_INT : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue($key, $val, $type);
            }
            $stmt->execute();
        }

        if (array_key_exists('assignee_ids', $data)) {
            // Delete existing
            $stmtDel = $this->pdo->prepare("DELETE FROM task_assignees WHERE task_id = :id");
            $stmtDel->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
            $stmtDel->execute();
            
            // Insert new ones
            if (!empty($data['assignee_ids']) && is_array($data['assignee_ids'])) {
                $stmtIns = $this->pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (:tid, :uid)");
                foreach ($data['assignee_ids'] as $uid) {
                    $stmtIns->bindValue(':tid', (int)$taskId, PDO::PARAM_INT);
                    $stmtIns->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
                    $stmtIns->execute();
                }
            }
        }
        
        return true;
    }

    // --- COMMENTS ---
    public function getCommentsByTaskId($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.name as user_name 
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.task_id = :task_id
            ORDER BY c.created_at DESC
        ");
        $stmt->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createComment($taskId, $userId, $content) {
        $stmt = $this->pdo->prepare("
            INSERT INTO comments (task_id, user_id, content) 
            VALUES (:task_id, :user_id, :content)
        ");
        $stmt->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    public function linkSubtask($parentId, $subtaskId) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO task_links (parent_id, subtask_id) 
            VALUES (:pid, :sid)
        ");
        $stmt->execute(['pid' => (int)$parentId, 'sid' => (int)$subtaskId]);
        return true;
    }
}
?>