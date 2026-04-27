<?php
// /includes/db_query.php

class DBQueries {
    private $pdo;
    
    // Track users who have already received a notification for a task in the current request
    public $notifiedUsers = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // --- RECENT PROJECTS ---
    public function trackProjectAccess($userId, $projectId) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO user_recent_projects (user_id, project_id, last_accessed) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_accessed=NOW()");
            $stmt->execute([(int)$userId, (int)$projectId]);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getRecentProjects($userId, $limit = 10) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.name 
                FROM projects p
                JOIN user_recent_projects urp ON p.id = urp.project_id
                WHERE urp.user_id = :userId
                ORDER BY urp.last_accessed DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':userId', (int)$userId, PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.name 
                FROM projects p
                JOIN user_recent_projects urp ON p.id = urp.project_id
                JOIN project_members pm ON p.id = pm.project_id
                WHERE urp.user_id = :userId1 AND pm.user_id = :userId2
                ORDER BY urp.last_accessed DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':userId1', (int)$userId, PDO::PARAM_INT);
            $stmt->bindValue(':userId2', (int)$userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback to latest created projects the user is a member of if user has no recent history
        if (empty($recentProjects)) {
            if ($userRole === 'admin') {
                $recentProjectsStmt = $this->pdo->prepare("
                    SELECT p.id, p.name 
                    FROM projects p
                    ORDER BY p.created_at DESC 
                    LIMIT :limit
                ");
            } else {
                $recentProjectsStmt = $this->pdo->prepare("
                    SELECT p.id, p.name 
                    FROM projects p
                    JOIN project_members pm ON p.id = pm.project_id
                    WHERE pm.user_id = :userId
                    ORDER BY p.created_at DESC 
                    LIMIT :limit
                ");
                $recentProjectsStmt->bindValue(':userId', (int)$userId, PDO::PARAM_INT);
            }
            $recentProjectsStmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $recentProjectsStmt->execute();
            $recentProjects = $recentProjectsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $recentProjects;
    }

    public function updateProjectStatus($projectId, $status) {
        $stmt = $this->pdo->prepare("UPDATE projects SET status = :status WHERE id = :project_id");
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // --- USERS & AUTH ---
    public function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user && isset($user['created_at'])) {
            $user['created_at'] = convertUtcToToronto($user['created_at']);
        }
        return $user;
    }

    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function createUser($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (name, email, password_hash, role) 
            VALUES (:name, :email, :password_hash, :role)
        ");
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $data['password_hash'], PDO::PARAM_STR);
        $stmt->bindValue(':role', $data['role'] ?? 'member', PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    public function getTeamMembers($teamId) {
        // Fallback to the join query, though might be unused
        $stmt = $this->pdo->prepare("SELECT u.id, u.name, u.email, u.role, u.created_at FROM users u JOIN team_members tm ON u.id = tm.user_id WHERE tm.team_id = :team_id ORDER BY tm.role ASC, u.name ASC");
        $stmt->bindValue(':team_id', (int)$teamId, PDO::PARAM_INT);
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($members as &$m) {
            if (isset($m['created_at'])) {
                $m['created_at'] = convertUtcToToronto($m['created_at']);
            }
        }
        return $members;
    }

    public function updateUserRole($userId, $teamId, $role) {
        $stmt = $this->pdo->prepare("UPDATE team_members SET role = :role WHERE user_id = :user_id AND team_id = :team_id");
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':team_id', (int)$teamId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getAllSystemUsers() {
        $stmt = $this->pdo->prepare("SELECT id, name, email, role, created_at FROM users ORDER BY role ASC, name ASC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            if (isset($u['created_at'])) {
                $u['created_at'] = convertUtcToToronto($u['created_at']);
            }
        }
        return $users;
    }

    public function updateSystemUserRole($userId, $role) {
        $stmt = $this->pdo->prepare("UPDATE users SET role = :role WHERE id = :user_id");
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function isProjectMember($projectId, $userId) {
        // Check if user is a system admin
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();
        if ($userRole === 'admin') {
            return true;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmt->execute([(int)$projectId, (int)$userId]);
        return (bool)$stmt->fetchColumn();
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
        if ($project && isset($project['created_at'])) {
            $project['created_at'] = convertUtcToToronto($project['created_at']);
        }
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
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') FROM task_collaborators tc JOIN users u2 ON tc.user_id = u2.id WHERE tc.task_id = t.id) as collaborator_name,
                   (SELECT GROUP_CONCAT(tc.user_id SEPARATOR ',') FROM task_collaborators tc WHERE tc.task_id = t.id) as collaborator_ids,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) + 
                       (SELECT COUNT(*) FROM task_links tl WHERE tl.parent_id = t.id)
                   ) AS subtask_count,
                   (SELECT COUNT(*) FROM task_projects tp2 WHERE tp2.task_id = t.id) AS project_count
            FROM tasks t
            JOIN task_projects tp ON t.id = tp.task_id
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE tp.project_id = :project_id
            GROUP BY t.id, tp.section_id, tp.position
            ORDER BY tp.position ASC
        ");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $topLevelTaskIds = array_column($tasks, 'id');
        
        foreach ($tasks as &$task) {
            $task['parent_task_id'] = null; // Force rendering as root in THIS project's list view
        }

        // Fetch subtasks recursively (both native and linked, so their nested children are grabbed too)
        $stmtAll = $this->pdo->prepare("
            SELECT t.*, 
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') FROM task_collaborators tc JOIN users u2 ON tc.user_id = u2.id WHERE tc.task_id = t.id) as collaborator_name,
                   (SELECT GROUP_CONCAT(tc.user_id SEPARATOR ',') FROM task_collaborators tc WHERE tc.task_id = t.id) as collaborator_ids,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) + 
                       (SELECT COUNT(*) FROM task_links tl WHERE tl.parent_id = t.id)
                   ) AS subtask_count,
                   (SELECT COUNT(*) FROM task_projects tp2 WHERE tp2.task_id = t.id) AS project_count
            FROM tasks t
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE t.id IN (
                WITH RECURSIVE TaskTree(task_id) AS (
                    SELECT task_id FROM task_projects WHERE project_id = :pid
                    UNION ALL
                    SELECT t2.id FROM tasks t2 INNER JOIN TaskTree tt ON t2.parent_task_id = tt.task_id
                    UNION ALL
                    SELECT tl.subtask_id FROM task_links tl INNER JOIN TaskTree tt ON tl.parent_id = tt.task_id
                ) SELECT task_id FROM TaskTree
            ) AND t.parent_task_id IS NOT NULL
            GROUP BY t.id
        ");
        $stmtAll->bindValue(':pid', (int)$projectId, PDO::PARAM_INT);
        $stmtAll->execute();
        $allSubtasks = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // Fetch explicitly linked subtasks (cross-project links)
        $stmtLinked = $this->pdo->prepare("
            SELECT tl.parent_id as _tl_parent, t.*, tl.position as link_position,
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') FROM task_collaborators tc JOIN users u2 ON tc.user_id = u2.id WHERE tc.task_id = t.id) as collaborator_name,
                   (SELECT GROUP_CONCAT(tc.user_id SEPARATOR ',') FROM task_collaborators tc WHERE tc.task_id = t.id) as collaborator_ids,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) +
                       (SELECT COUNT(*) FROM task_links tl2 WHERE tl2.parent_id = t.id)
                   ) AS subtask_count,
                   (SELECT COUNT(*) FROM task_projects tp2 WHERE tp2.task_id = t.id) AS project_count
            FROM task_links tl
            JOIN tasks t ON tl.subtask_id = t.id
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE tl.parent_id IN (
                WITH RECURSIVE TaskTree(task_id) AS (
                    SELECT task_id FROM task_projects WHERE project_id = :pid
                    UNION ALL
                    SELECT t2.id FROM tasks t2 INNER JOIN TaskTree tt ON t2.parent_task_id = tt.task_id
                    UNION ALL
                    SELECT tl2.subtask_id FROM task_links tl2 INNER JOIN TaskTree tt ON tl2.parent_id = tt.task_id
                ) SELECT task_id FROM TaskTree
            )
            GROUP BY tl.parent_id, t.id
        ");
        $stmtLinked->bindValue(':pid', (int)$projectId, PDO::PARAM_INT);
        $stmtLinked->execute();
        $linkedSubtasks = $stmtLinked->fetchAll(PDO::FETCH_ASSOC);

        // Build tree
        $subtasksByParent = [];
        foreach ($allSubtasks as $sub) {
            if (in_array($sub['id'], $topLevelTaskIds)) continue; // Prevent duplication in DOM if it's already a root task in this project
            $subtasksByParent[$sub['parent_task_id']][] = $sub;
        }
        foreach ($linkedSubtasks as $sub) {
            $parentId = $sub['_tl_parent'];
            unset($sub['_tl_parent']); // remove tracker
            $subtasksByParent[$parentId][] = $sub;
        }

        $buildTree = function(&$parentTasks) use (&$buildTree, &$subtasksByParent) {
            foreach ($parentTasks as &$task) {
                if (isset($subtasksByParent[$task['id']])) {
                    usort($subtasksByParent[$task['id']], function($a, $b) {
                        $ap = isset($a['link_position']) ? (int)$a['link_position'] : (int)($a['position'] ?? 0);
                        $bp = isset($b['link_position']) ? (int)$b['link_position'] : (int)($b['position'] ?? 0);
                        return $ap - $bp;
                    });
                }
                $task['subtasks'] = $subtasksByParent[$task['id']] ?? [];
                if (!empty($task['subtasks'])) {
                    $buildTree($task['subtasks']);
                }
            }
        };

        $buildTree($tasks);
        foreach ($tasks as &$t) {
            $t = $this->formatTaskDates($t);
        }
        return $tasks;
    }

    // Helper to format task dates for rendering
    private function formatTaskDates($task) {
        if (!$task) return $task;
        $dateFields = ['start_date', 'expected_start_date', 'expected_due_date', 'completed_date', 'created_at', 'updated_at'];
        foreach ($dateFields as $field) {
            if (isset($task[$field]) && !empty($task[$field])) {
                $task[$field] = convertUtcToToronto($task[$field]);
            }
        }
        if (isset($task['subtasks']) && is_array($task['subtasks'])) {
            foreach ($task['subtasks'] as &$sub) {
                $sub = $this->formatTaskDates($sub);
            }
        }
        return $task;
    }

    public function getAllTasks($userId = null) {
        $sql = "
            SELECT t.*,
                   (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') FROM task_projects tp JOIN projects p ON tp.project_id = p.id WHERE tp.task_id = t.id) AS project_names,
                   (SELECT GROUP_CONCAT(tp.project_id SEPARATOR ',') FROM task_projects tp WHERE tp.task_id = t.id) AS project_ids,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) + 
                       (SELECT COUNT(*) FROM task_links tl WHERE tl.parent_id = t.id)
                   ) AS subtask_count
            FROM tasks t
        ";
        
        if ($userId) {
            $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtUser->execute([(int)$userId]);
            $userRole = $stmtUser->fetchColumn();

            if ($userRole === 'admin') {
                $stmt = $this->pdo->query($sql);
                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql .= " WHERE EXISTS (SELECT 1 FROM task_projects tp JOIN project_members pm ON tp.project_id = pm.project_id WHERE tp.task_id = t.id AND pm.user_id = :userId)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':userId', (int)$userId, PDO::PARAM_INT);
                $stmt->execute();
                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $stmt = $this->pdo->query($sql);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($tasks as &$t) {
            $t = $this->formatTaskDates($t);
        }
        return $tasks;
    }
    
    public function getTasksByAssigneeId($userId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   COALESCE(p1.name, p2.name, p3.name) AS project_name, 
                   COALESCE(tp1.project_id, tp2.project_id, tp3.project_id) AS project_id,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors
            FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            
            LEFT JOIN task_projects tp1 ON tp1.task_id = t.id
            LEFT JOIN projects p1 ON tp1.project_id = p1.id
            
            LEFT JOIN tasks t2 ON t.parent_task_id = t2.id
            LEFT JOIN task_projects tp2 ON tp2.task_id = t2.id
            LEFT JOIN projects p2 ON tp2.project_id = p2.id
            
            LEFT JOIN tasks t3 ON t2.parent_task_id = t3.id
            LEFT JOIN task_projects tp3 ON tp3.task_id = t3.id
            LEFT JOIN projects p3 ON tp3.project_id = p3.id
            
            WHERE ta.user_id = :user_id
            ORDER BY t.id DESC
        ");
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tasks as &$t) {
            $t = $this->formatTaskDates($t);
        }
        return $tasks;
    }

    public function createTask($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (parent_task_id, title, description, status, start_date, completed_date) 
            VALUES (:parent_task_id, :title, :description, :status, :start_date, :completed_date)
        ");
        $stmt->bindValue(':parent_task_id', isset($data['parent_task_id']) ? (int)$data['parent_task_id'] : null, isset($data['parent_task_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        
        $desc = isset($data['description']) ? $data['description'] : null;
        $stmt->bindValue(':description', $desc, $desc !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        $status = isset($data['status']) ? $data['status'] : 'todo';
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        
        $start = isset($data['start_date']) ? convertTorontoToUtc($data['start_date']) : null;
        $stmt->bindValue(':start_date', $start, $start !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        $completed_date = ($status === 'completed' || $status === 'done') ? date('Y-m-d H:i:s') : null;
        $stmt->bindValue(':completed_date', $completed_date, $completed_date ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        $stmt->execute();
        $newTaskId = $this->pdo->lastInsertId();

        // Add assignee if provided
        if (!empty($data['assignees']) && is_array($data['assignees'])) {
            $stmtA = $this->pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (:tid, :uid)");
            foreach ($data['assignees'] as $uid) {
                $stmtA->bindValue(':tid', $newTaskId, PDO::PARAM_INT);
                $stmtA->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
                $stmtA->execute();
                $this->sendAssigneeNotification($newTaskId, $uid);
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
        $completed_date = ($status === 'completed' || $status === 'done') ? date('Y-m-d H:i:s') : null;
        $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status, completed_date = :completed_date WHERE id = :id");
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':completed_date', $completed_date, $completed_date ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
        $res = $stmt->execute();

        if ($res) {
            $this->checkAndFireTaskTriggers($taskId, 'on_status_change');
            if ($completed_date) {
                $this->checkAndFireTaskTriggers($taskId, 'on_complete');
            }
        }
        return $res;
    }

    public function checkAndFireTaskTriggers($taskId, $event) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM task_triggers WHERE source_task_id = :id AND trigger_event = :event");
            $stmt->execute(['id' => $taskId, 'event' => $event]);
            $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($triggers as $trigger) {
                if ($trigger['action'] === 'notify_assignee' || $trigger['action'] === 'notify_admin') {
                    $this->sendTaskNotification($taskId, "Task automation trigger: " . $event, 'system');
                } elseif ($trigger['action'] === 'start_next_task' && $trigger['target_task_id']) {
                    $this->updateTaskStatus($trigger['target_task_id'], 'in_progress');
                }
            }
        } catch (\PDOException $e) {
            error_log("Trigger error: " . $e->getMessage());
        }
    }

    public function sendTaskNotification($taskId, $message, $category = 'task_update', $subject = null, $bodyHtml = null, $projectId = null, $excludeUserId = null) {
        try {
            $notifyUsers = [];

            // By default we do NOT exclude the user making the change so testing works as expected
            // unless an excludeUserId is explicitly provided.
            /*
            if ($excludeUserId === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
                $excludeUserId = $_SESSION['user_id'];
            }
            */

            // Find collaborators (Assignees are only notified when assigned)
            $stmtCollab = $this->pdo->prepare("SELECT u.id, u.email, u.name FROM task_collaborators tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = :tid");
            $stmtCollab->execute(['tid' => $taskId]);
            $collaborators = $stmtCollab->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($collaborators)) {
                foreach ($collaborators as $c) {
                    // Deduplicate by using user ID as key
                    if ($excludeUserId && (int)$c['id'] === (int)$excludeUserId) continue;
                    if (isset($this->notifiedUsers[$taskId]) && in_array($c['id'], $this->notifiedUsers[$taskId])) continue;
                    $notifyUsers[$c['id']] = $c;
                }
            }

            // Find project ID if not provided
            if (!$projectId) {
                $stmtProj = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :tid LIMIT 1");
                $stmtProj->execute(['tid' => $taskId]);
                $projectId = $stmtProj->fetchColumn();

                // If not found in task_projects, it might be a subtask, look up recursively
                if (!$projectId) {
                    $projectId = $this->getProjectIdRecursive($taskId);
                }
            }

            if ($projectId) {
                // Find project admins
                $stmtAdmins = $this->pdo->prepare("SELECT u.id, u.email, u.name FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid AND pm.role = 'admin'");
                $stmtAdmins->execute(['pid' => $projectId]);
                $projectAdmins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
                foreach ($projectAdmins as $admin) {
                    if ($excludeUserId && (int)$admin['id'] === (int)$excludeUserId) continue;
                    if (isset($this->notifiedUsers[$taskId]) && in_array($admin['id'], $this->notifiedUsers[$taskId])) continue;
                    $notifyUsers[$admin['id']] = $admin;
                }
            }

            if (empty($notifyUsers)) return ['success' => true, 'message' => 'No users to notify'];

            $stmtInsertNotif = $this->pdo->prepare("INSERT INTO notifications (user_id, task_id, category, message, is_read) VALUES (:uid, :tid, :cat, :msg, 0)");

            require_once __DIR__ . '/email_service.php';
            $emailService = new EmailService();

            $allSent = true;
            $errors = [];
            
            $projectUrlId = $projectId ? $projectId : 1;
            $taskUrl = "http://" . $_SERVER['HTTP_HOST'] . "/bathyal/project?id=" . $projectUrlId . "&task_id=" . $taskId;
            
            // Replace any generic /bathyal/tasks?id= URL in the provided body with the rich taskUrl
            $bodyHtmlFixed = preg_replace('/http:\/\/[^\'"]+\/bathyal\/tasks\?id=\d+/', $taskUrl, $bodyHtml);

            foreach ($notifyUsers as $u) {
                // Mark user as notified for this task
                $this->notifiedUsers[$taskId][] = $u['id'];

                // Insert DB notification
                $stmtInsertNotif->execute([
                    'uid' => $u['id'],
                    'tid' => $taskId,
                    'cat' => $category,
                    'msg' => $message
                ]);

                // Send email
                $to = $u['email'];
                $mailSubject = $subject ? $subject : "App Notification: Task {$taskId}";
                $mailBody = $bodyHtmlFixed ? $bodyHtmlFixed : "Hello {$u['name']},<br><br>{$message}<br><br>Task ID: {$taskId}<br><p><a href='{$taskUrl}'>Click here to view the task</a></p>";
                
                $emailResult = $emailService->sendEmail($to, $u['name'], $mailSubject, $mailBody);
                if (!$emailResult['success']) {
                    $allSent = false;
                    $errors[] = $emailResult['error'];
                }
            }
            return [
                'success' => $allSent, 
                'error' => $allSent ? null : implode('; ', $errors)
            ];
        } catch (\PDOException $e) {
            error_log("Notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getProjectIdRecursive($taskId) {
        $stmt = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :tid LIMIT 1");
        $stmt->execute(['tid' => $taskId]);
        $pid = $stmt->fetchColumn();
        if ($pid) return $pid;

        $stmt = $this->pdo->prepare("SELECT parent_task_id FROM tasks WHERE id = :tid");
        $stmt->execute(['tid' => $taskId]);
        $parent = $stmt->fetchColumn();
        if ($parent) {
            return $this->getProjectIdRecursive($parent);
        }
        return null;
    }

    // --- NEW DB QUERIES ---

    public function getUserNotifications($userId, $limit = 50, $category = null) {
        $sql = "SELECT * FROM notifications WHERE user_id = :uid";
        $params = ['uid' => (int)$userId];
        
        if ($category && $category !== 'all') {
            $sql .= " AND category = :cat";
            $params['cat'] = $category;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($notifications as &$n) {
            if (isset($n['created_at'])) {
                $n['created_at'] = convertUtcToToronto($n['created_at']);
            }
        }
        return $notifications;
    }
    
    public function markNotificationRead($notificationId, $userId) {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        return $stmt->execute(['id' => (int)$notificationId, 'uid' => (int)$userId]);
    }

    public function markAllNotificationsRead($userId) {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        return $stmt->execute(['uid' => (int)$userId]);
    }

    public function getUnreadNotificationCount($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt->execute(['uid' => (int)$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getProjectDefaultNotifyIds($projectId) {
        $stmtNotify = $this->pdo->prepare("SELECT user_id FROM project_default_notify WHERE project_id = :pid");
        $stmtNotify->execute(['pid' => (int)$projectId]);
        return $stmtNotify->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getProjectDefaultNotifyNames($projectId) {
        $stmtNotifyInfo = $this->pdo->prepare("SELECT GROUP_CONCAT(u.name SEPARATOR ',') as names FROM project_default_notify pdn JOIN users u ON pdn.user_id = u.id WHERE pdn.project_id = :pid");
        $stmtNotifyInfo->execute(['pid' => (int)$projectId]);
        return $stmtNotifyInfo->fetchColumn();
    }

    public function updateProjectDefaultNotify($projectId, $memberIds) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM project_default_notify WHERE project_id = :pid");
            $stmt->execute(['pid' => (int)$projectId]);

            if (!empty($memberIds)) {
                $memberIds = array_unique(array_filter($memberIds, function($id) { return (int)$id > 0; }));
                $insertStmt = $this->pdo->prepare("INSERT INTO project_default_notify (project_id, user_id) VALUES (:pid, :uid)");
                foreach ($memberIds as $uid) {
                    $insertStmt->execute(['pid' => (int)$projectId, 'uid' => (int)$uid]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getProjectMemberRole($projectId, $userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT role FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmt->execute(['pid' => (int)$projectId, 'uid' => (int)$userId]);
        $role = $stmt->fetchColumn();
        
        if (!$role && $userRole === 'admin') {
            return 'manager';
        }
        
        return $role;
    }

    public function sendProjectMemberNotification($projectId, $userId, $role, $action) {
        try {
            // Get Project details
            $stmtProj = $this->pdo->prepare("SELECT name FROM projects WHERE id = :pid");
            $stmtProj->execute(['pid' => $projectId]);
            $projectName = $stmtProj->fetchColumn() ?: "Project " . $projectId;
            
            // Get User details
            $stmtUser = $this->pdo->prepare("SELECT email, name FROM users WHERE id = :uid");
            $stmtUser->execute(['uid' => $userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;

            // Get Changer details
            $changerId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            $stmtChanger = $this->pdo->prepare("SELECT name FROM users WHERE id = :uid");
            $stmtChanger->execute(['uid' => $changerId]);
            $changerName = $stmtChanger->fetchColumn() ?: 'Someone';
            
            require_once __DIR__ . '/email_service.php';
            $emailService = new EmailService();
            
            $subject = "";
            $bodyHtml = "";
            $messageText = "";
            
            if ($action === 'add') {
                $subject = "You have been added to the project: " . $projectName;
                $messageText = $changerName . " added you to the project: " . $projectName;
                $bodyHtml = "<h2>Welcome to the project!</h2>";
                $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> added you to the project: <strong>" . htmlspecialchars($projectName) . "</strong></p>";
                $bodyHtml .= "<p>Your role is: <strong>" . htmlspecialchars($role) . "</strong></p>";
            } elseif ($action === 'remove') {
                $subject = "You have been removed from the project: " . $projectName;
                $messageText = $changerName . " removed you from the project: " . $projectName;
                $bodyHtml = "<h2>You were removed from the project</h2>";
                $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> removed you from the project: <strong>" . htmlspecialchars($projectName) . "</strong></p>";
            } elseif ($action === 'update_role') {
                $subject = "Your role was updated in the project: " . $projectName;
                $messageText = $changerName . " updated your role in the project: " . $projectName;
                $bodyHtml = "<h2>Project Role Updated</h2>";
                $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> updated your role in the project: <strong>" . htmlspecialchars($projectName) . "</strong></p>";
                $bodyHtml .= "<p>Your new role is: <strong>" . htmlspecialchars($role) . "</strong></p>";
            }
            
            $bodyHtml .= "<p><a href='http://" . $_SERVER['HTTP_HOST'] . "/bathyal/projects'>Click here to view your projects</a></p>";
            
            // Insert DB notification
            $stmtInsertNotif = $this->pdo->prepare("INSERT INTO notifications (user_id, category, message, is_read) VALUES (:uid, :cat, :msg, 0)");
            $stmtInsertNotif->execute([
                'uid' => $userId,
                'cat' => 'general', // project member changes go to general
                'msg' => $messageText
            ]);
            
            $emailService->sendEmail($user['email'], $user['name'], $subject, $bodyHtml);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send project member notification: " . $e->getMessage());
            return false;
        }
    }

    public function updateProjectMemberRole($projectId, $userId, $role) {
        $stmt = $this->pdo->prepare("UPDATE project_members SET role = :role WHERE project_id = :pid AND user_id = :uid");
        $result = $stmt->execute(['role' => $role, 'pid' => (int)$projectId, 'uid' => (int)$userId]);
        if ($result) {
            $this->sendProjectMemberNotification($projectId, $userId, $role, 'update_role');
        }
        return $result;
    }

    public function removeProjectMember($projectId, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $result = $stmt->execute(['pid' => (int)$projectId, 'uid' => (int)$userId]);
        if ($result) {
            $this->sendProjectMemberNotification($projectId, $userId, null, 'remove');
        }
        return $result;
    }

    public function addProjectMember($projectId, $userId, $role) {
        $stmtCheck = $this->pdo->prepare("SELECT 1 FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtCheck->execute(['pid' => (int)$projectId, 'uid' => (int)$userId]);
        if (!$stmtCheck->fetchColumn()) {
            $stmt = $this->pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (:pid, :uid, :role)");
            $result = $stmt->execute(['pid' => (int)$projectId, 'uid' => (int)$userId, 'role' => $role]);
            if ($result) {
                $this->sendProjectMemberNotification($projectId, $userId, $role, 'add');
            }
            return $result;
        }
        return false;
    }

    public function addProjectTeam($projectId, $teamId) {
        $stmtCheck = $this->pdo->prepare("SELECT 1 FROM project_teams WHERE project_id = :pid AND team_id = :tid");
        $stmtCheck->execute(['pid' => (int)$projectId, 'tid' => (int)$teamId]);
        if (!$stmtCheck->fetchColumn()) {
            $stmt = $this->pdo->prepare("INSERT INTO project_teams (project_id, team_id) VALUES (:pid, :tid)");
            return $stmt->execute(['pid' => (int)$projectId, 'tid' => (int)$teamId]);
        }
        return false;
    }

    public function getProjectMembersWithRoles($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, pm.role 
            FROM project_members pm
            JOIN users u ON pm.user_id = u.id
            WHERE pm.project_id = :pid
        ");
        $stmt->execute(['pid' => (int)$projectId]);
        return $stmt->fetchAll();
    }

    public function getAvailableUsersForProject($teamId, $memberIds) {
        $placeholders = count($memberIds) > 0 ? implode(',', array_fill(0, count($memberIds), '?')) : '0';
        $query = "SELECT id, name, email FROM users WHERE id NOT IN ($placeholders) ORDER BY name ASC";
        $params = count($memberIds) > 0 ? $memberIds : [];

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateSection($sectionId, $name) {
        $stmt = $this->pdo->prepare("UPDATE sections SET name = :name WHERE id = :sid");
        return $stmt->execute(['name' => $name, 'sid' => (int)$sectionId]);
    }

    public function deleteSection($sectionId) {
        $stmt = $this->pdo->prepare("DELETE FROM sections WHERE id = :sid");
        return $stmt->execute(['sid' => (int)$sectionId]);
    }

    public function reorderSections($sectionIds) {
        if (is_array($sectionIds) && count($sectionIds) > 0) {
            $stmt = $this->pdo->prepare("UPDATE sections SET position = :pos WHERE id = :sid");
            foreach ($sectionIds as $index => $sid) {
                $stmt->execute(['pos' => $index + 1, 'sid' => (int)$sid]);
            }
            return true;
        }
        return false;
    }

    public function getNextSectionPosition($projectId) {
        $stmtPos = $this->pdo->prepare("SELECT IFNULL(MAX(position), 0) + 1 FROM sections WHERE project_id = :pid");
        $stmtPos->execute(['pid' => (int)$projectId]);
        return (int)$stmtPos->fetchColumn();
    }

    public function searchUsers($search, $projectId = null, $excludeTeamId = null, $excludeProjectId = null) {
        if ($projectId) {
            $sql = "SELECT u.id, u.name, u.email FROM users u 
                    INNER JOIN project_members pm ON pm.user_id = u.id 
                    WHERE pm.project_id = :project_id";
            $params = [':project_id' => (int)$projectId];
            
            if ($search !== '') {
                $sql .= " AND (u.name LIKE :search OR u.email LIKE :search)";
                $params[':search'] = "%$search%";
            }
            $sql .= " ORDER BY u.name ASC LIMIT 20";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT id, name, email FROM users";
            $params = [];
            $whereConditions = [];

            if ($search !== '') {
                $whereConditions[] = "(name LIKE ? OR email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($excludeTeamId) {
                $whereConditions[] = "id NOT IN (SELECT user_id FROM team_members WHERE team_id = ?)";
                $params[] = (int)$excludeTeamId;
            }

            if ($excludeProjectId) {
                $whereConditions[] = "id NOT IN (SELECT user_id FROM project_members WHERE project_id = ?)";
                $params[] = (int)$excludeProjectId;
            }
            
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $sql .= " ORDER BY name ASC LIMIT 20";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function createTeam($name, $userId) {
        $stmt = $this->pdo->prepare("INSERT INTO teams (name, created_by) VALUES (:name, :created_by)");
        $stmt->execute(['name' => $name, 'created_by' => (int)$userId]);
        $teamId = $this->pdo->lastInsertId();
        
        $this->addTeamMember($teamId, $userId, 'owner');
        
        return $teamId;
    }

    public function deleteTeam($teamId) {
        $stmt = $this->pdo->prepare("DELETE FROM teams WHERE id = :id");
        return $stmt->execute(['id' => (int)$teamId]);
    }

    public function getTeamsForUser($userId, $systemRole = null) {
        // Everyone can see all teams
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name, t.created_by, t.created_at, 
                   COALESCE(tm.role, '') as role 
            FROM teams t
            LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = :uid
            ORDER BY t.name ASC
        ");
        $stmt->execute(['uid' => (int)$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeamById($teamId) {
        $stmt = $this->pdo->prepare("SELECT * FROM teams WHERE id = :id");
        $stmt->execute(['id' => (int)$teamId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTeamMembersWithRoles($teamId) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.role as global_role, tm.role as team_role, tm.joined_at
            FROM users u
            JOIN team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = :tid
            ORDER BY tm.role = 'owner' DESC, tm.role = 'admin' DESC, u.name ASC
        ");
        $stmt->execute(['tid' => (int)$teamId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($members as &$m) {
            if (isset($m['joined_at'])) {
                $m['joined_at'] = convertUtcToToronto($m['joined_at']);
            }
        }
        return $members;
    }

    public function addTeamMember($teamId, $userId, $role = 'member') {
        $stmtCheck = $this->pdo->prepare("SELECT 1 FROM team_members WHERE team_id = :tid AND user_id = :uid");
        $stmtCheck->execute(['tid' => (int)$teamId, 'uid' => (int)$userId]);
        if (!$stmtCheck->fetchColumn()) {
            $stmt = $this->pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (:tid, :uid, :role)");
            $success = $stmt->execute(['tid' => (int)$teamId, 'uid' => (int)$userId, 'role' => $role]);
            
            if ($success) {
                // Add the user to all projects this team is linked to (as member)
                $stmtProjects = $this->pdo->prepare("SELECT project_id FROM project_teams WHERE team_id = :tid");
                $stmtProjects->execute(['tid' => (int)$teamId]);
                $projectIds = $stmtProjects->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($projectIds as $pid) {
                    $this->addProjectMember($pid, $userId, 'member');
                }
            }
            return $success;
        }
        return false;
    }

    public function removeTeamMember($teamId, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM team_members WHERE team_id = :tid AND user_id = :uid");
        return $stmt->execute(['tid' => (int)$teamId, 'uid' => (int)$userId]);
    }

    public function getTeamMemberRole($teamId, $userId) {
        $stmt = $this->pdo->prepare("SELECT role FROM team_members WHERE team_id = :tid AND user_id = :uid");
        $stmt->execute(['tid' => (int)$teamId, 'uid' => (int)$userId]);
        return $stmt->fetchColumn();
    }

    public function updateTeamMemberRole($teamId, $userId, $role) {
        $stmt = $this->pdo->prepare("UPDATE team_members SET role = :role WHERE team_id = :tid AND user_id = :uid");
        return $stmt->execute(['role' => $role, 'tid' => (int)$teamId, 'uid' => (int)$userId]);
    }

    public function reorderTasks($sectionId, $taskIds, $parentTaskId = null, $draggedTaskId = null) {
        if (!empty($draggedTaskId) && $parentTaskId) {
            // Prevent dragging a multi-project (shared) task into another task as a subtask
            $stmtCheckShared = $this->pdo->prepare("SELECT COUNT(*) FROM task_projects WHERE task_id = :tid");
            $stmtCheckShared->execute(['tid' => (int)$draggedTaskId]);
            if ($stmtCheckShared->fetchColumn() > 1) {
                return false; // Illegal move: Shared tasks cannot be subtasks
            }

            // Check for circular dependency: is parentTaskId a descendant of draggedTaskId?
            $stmtCheckCirc = $this->pdo->prepare("
                WITH RECURSIVE descendants AS (
                    SELECT id FROM tasks WHERE parent_task_id = :tid
                    UNION ALL
                    SELECT t.id FROM tasks t INNER JOIN descendants d ON t.parent_task_id = d.id
                )
                SELECT 1 FROM descendants WHERE id = :pid
            ");
            $stmtCheckCirc->execute(['tid' => (int)$draggedTaskId, 'pid' => (int)$parentTaskId]);
            if ($stmtCheckCirc->fetchColumn()) {
                return false; // Illegal move: Cannot make a task a subtask of its own descendant
            }
            
            // Also prevent a task from being its own parent
            if ((int)$draggedTaskId === (int)$parentTaskId) {
                return false;
            }
        }

        // Always check if it's dropping into the subtask list (modal) where sectionId is null but parentTaskId is set
        if (!empty($draggedTaskId)) {
            // First, if it's dragging entirely within the modal, just update subtask positions safely
            if (is_null($sectionId) && $parentTaskId) {
                // Reordering natively inside the modal
                $posTop = 1;
                foreach ($taskIds as $tid) {
                    $tid = (int)$tid;
                    
                    // Is it currently a native subtask of parentTaskId?
                    $stmtCheckNat = $this->pdo->prepare("SELECT 1 FROM tasks WHERE id = :tid AND parent_task_id = :pid");
                    $stmtCheckNat->execute(['tid' => $tid, 'pid' => (int)$parentTaskId]);
                    $isNative = $stmtCheckNat->fetchColumn();
                    
                    // Is it currently a linked subtask of parentTaskId?
                    $stmtCheckLin = $this->pdo->prepare("SELECT 1 FROM task_links WHERE subtask_id = :tid AND parent_id = :pid");
                    $stmtCheckLin->execute(['tid' => $tid, 'pid' => (int)$parentTaskId]);
                    $isLinked = $stmtCheckLin->fetchColumn();
                    
                    if (!$isNative && !$isLinked) {
                        // New addition to this parent! Default to native parenting
                        $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = :pid WHERE id = :tid");
                        $stmtTask->execute(['pid' => (int)$parentTaskId, 'tid' => $tid]);
                        
                        // Delete from task_projects
                        $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid");
                        $stmtDel->execute(['tid' => $tid]);
                        
                        $isNative = true;
                    }
                    
                    if ($isNative) {
                        $stmtPos = $this->pdo->prepare("UPDATE tasks SET position = :pos WHERE id = :tid");
                        $stmtPos->execute(['pos' => $posTop, 'tid' => $tid]);
                    }
                    if ($isLinked) {
                        $stmtPos = $this->pdo->prepare("UPDATE task_links SET position = :pos WHERE subtask_id = :tid AND parent_id = :pid");
                        $stmtPos->execute(['pos' => $posTop, 'tid' => $tid, 'pid' => (int)$parentTaskId]);
                    }
                    $posTop++;
                }
                return true;
            }

            // List view drag & drop
            // First, check if draggedTaskId is a linked subtask to this parent
            $isLinkedToNewParent = false;
            if ($parentTaskId) {
                $stmtCheckLin = $this->pdo->prepare("SELECT 1 FROM task_links WHERE subtask_id = :tid AND parent_id = :pid");
                $stmtCheckLin->execute(['tid' => (int)$draggedTaskId, 'pid' => (int)$parentTaskId]);
                $isLinkedToNewParent = $stmtCheckLin->fetchColumn();
            }

            if (!$isLinkedToNewParent) {
                // If it is NOT a linked subtask, we need to be careful!
                // If we are moving it to a top-level section (parentTaskId is null), we must ensure we aren't stealing a linked subtask from another project natively.
                $stmtProj = null;
                $currentProjectId = null;
                if ($sectionId) {
                    $stmtProj = $this->pdo->prepare("SELECT project_id FROM sections WHERE id = :sid");
                    $stmtProj->execute(['sid' => (int)$sectionId]);
                    $currentProjectId = $stmtProj->fetchColumn();
                }
                
                // Find if the task belongs natively to the CURRENT project BEFORE resetting parent_task_id
                $stmtAncestor = $this->pdo->prepare("
                    WITH RECURSIVE ancestor AS (
                        SELECT id, parent_task_id FROM tasks WHERE id = :tid
                        UNION ALL
                        SELECT t.id, t.parent_task_id FROM tasks t INNER JOIN ancestor a ON a.parent_task_id = t.id
                    )
                    SELECT id FROM ancestor WHERE parent_task_id IS NULL
                ");
                $stmtAncestor->execute(['tid' => (int)$draggedTaskId]);
                $ancestorId = $stmtAncestor->fetchColumn();

                $isNativeToProject = false;
                if ($ancestorId && $currentProjectId) {
                    $stmtCheckNative = $this->pdo->prepare("SELECT 1 FROM task_projects WHERE task_id = :aid AND project_id = :pid");
                    $stmtCheckNative->execute(['aid' => $ancestorId, 'pid' => $currentProjectId]);
                    $isNativeToProject = (bool)$stmtCheckNative->fetchColumn();
                }

                // If it is NOT native to this project, we should NOT change its native parent_task_id 
                // (destroying its native tree). We should only manipulate task_projects or task_links for THIS project.
                // However, if we drag a task to root, we only process logic if $isNativeToProject is false OR if it IS false but we drag it across columns.
                // A native move (reordering) is handled underneath.
                
                // Fetch the current state of dragged task before modifications
                $currParent = $this->pdo->prepare("SELECT parent_task_id FROM tasks WHERE id = :tid");
                $currParent->execute(['tid' => (int)$draggedTaskId]);
                $cpId = $currParent->fetchColumn();

                if (!$isNativeToProject && $currentProjectId) {
                    if (!$parentTaskId) {
                        // Link to the new project section as a top-level task
                        $stmtIns = $this->pdo->prepare("INSERT IGNORE INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, 0)");
                        $stmtIns->execute(['tid' => (int)$draggedTaskId, 'pid' => $currentProjectId, 'sid' => (int)$sectionId]);
                        
                        // Remove OLD linkages in this specific project so it's not shown as a subtask anymore here
                        $stmtCleanLinks = $this->pdo->prepare("
                            DELETE tl FROM task_links tl
                            JOIN tasks p ON tl.parent_id = p.id
                            JOIN task_projects tp ON p.id = tp.task_id
                            WHERE tl.subtask_id = :tid AND tp.project_id = :pid
                        ");
                        $stmtCleanLinks->execute(['tid' => (int)$draggedTaskId, 'pid' => $currentProjectId]);
                    } else {
                        // Moving to be a subtask in this project
                        $stmtLink = $this->pdo->prepare("INSERT IGNORE INTO task_links (parent_id, subtask_id, position) VALUES (:pid, :tid, 0)");
                        $stmtLink->execute(['pid' => (int)$parentTaskId, 'tid' => (int)$draggedTaskId]);

                        // Remove from task_projects for this project if it was top-level
                        $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid AND project_id = :pid");
                        $stmtDel->execute(['tid' => (int)$draggedTaskId, 'pid' => $currentProjectId]);

                        // Clean other task_links in this project to avoid duplicate appearances 
                        $stmtCleanOtherLinks = $this->pdo->prepare("
                            DELETE tl FROM task_links tl
                            JOIN tasks p ON tl.parent_id = p.id
                            JOIN task_projects tp ON p.id = tp.task_id
                            WHERE tl.subtask_id = :tid AND tp.project_id = :pid AND tl.parent_id != :new_pid
                        ");
                        $stmtCleanOtherLinks->execute(['tid' => (int)$draggedTaskId, 'pid' => $currentProjectId, 'new_pid' => (int)$parentTaskId]);
                    }
                } else {
                    // It's a native move or we are explicitly making it a subtask 
                    
                    // Only update parent_task_id if we are making it a subtask, OR if it's currently a subtask of the SAME project.
                    // If it's a subtask of ANOTHER project and we are just moving it to the root of THIS project, we shouldn't sever its original parent.
                    $shouldUpdateParent = true;
                    
                    // Allow dropping into modal where sectionId is null but parentTaskId is defined
                    if (!$currentProjectId && $parentTaskId) {
                        $shouldUpdateParent = true;
                    } else if (!$parentTaskId && $cpId && $currentProjectId) {
                        // Dragging to root. Does cpId belong to currentProjectId?
                        $stmtAnc = $this->pdo->prepare("
                            WITH RECURSIVE ancestor AS (
                                SELECT id, parent_task_id FROM tasks WHERE id = :tid
                                UNION ALL
                                SELECT t.id, t.parent_task_id FROM tasks t INNER JOIN ancestor a ON a.parent_task_id = t.id
                            )
                            SELECT id FROM ancestor WHERE parent_task_id IS NULL
                        ");
                        $stmtAnc->execute(['tid' => $cpId]);
                        $cpAncestor = $stmtAnc->fetchColumn();
                        
                        $stmtCheckP = $this->pdo->prepare("SELECT 1 FROM task_projects WHERE task_id = :aid AND project_id = :pid");
                        $stmtCheckP->execute(['aid' => $cpAncestor, 'pid' => $currentProjectId]);
                        $isCpInThisProject = $stmtCheckP->fetchColumn();
                        
                        if (!$isCpInThisProject) {
                            $shouldUpdateParent = false;
                        }
                    }

                    // Special fix: If we are ONLY dragging to reorder sibling tasks in the same parent (or root),
                    // we DO update the parent task ID to the SAME parent, which is harmless.
                    // But we MUST also ensure that we do not mistakenly strip its parent_task_id to NULL
                    // if $parentTaskId is not passed to us properly by the front-end during a sibling reorder!
                    // If the front-end passes $parentTaskId correctly based on depth computation, then we are safe.
                    
                    // IF we are just reordering root items (which we can determine because $parentTaskId is null and $cpId is null),
                    // OR if we are reordering subtasks ($parentTaskId is NOT null, and $parentTaskId == $cpId),
                    // we only do this.
                    // WAIT: if $parentTaskId === null, and $cpId is NOT null (so it IS currently a subtask),
                    // and we are trying to reorder it as a root task, that means we are promoting it!
                    // But we explicitly decided Drag and Drop cannot promote/demote tasks anymore.
                    // So if $parentTaskId is different from $cpId during drag-and-drop, we should IGNORE the parent update 
                    // completely unless we're deliberately moving it!
                    // Actually, since Drag and Drop strictly passes the existing parent, we shouldn't even NEED to update parent_task_id!
                    // Let's just bypass updating parent_task_id if it's the exact same as $cpId to save unnecessary DB writes.
                    
                    if ($shouldUpdateParent && (string)$parentTaskId !== (string)$cpId) {
                        $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = :pid WHERE id = :tid");
                        $stmtTask->execute([
                            'pid' => $parentTaskId ?: null,
                            'tid' => (int)$draggedTaskId
                        ]);
                    }
                    
                    if ($parentTaskId) {
                        $targetProjectId = $currentProjectId;
                        if (!$targetProjectId) {
                            $stmtCheckP = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :parent_id LIMIT 1");
                            $stmtCheckP->execute(['parent_id' => (int)$parentTaskId]);
                            $targetProjectId = $stmtCheckP->fetchColumn();
                        }
                        if ($targetProjectId && (string)$parentTaskId !== (string)$cpId) {
                            // Only delete from task_projects if we are genuinely moving it TO be a new subtask!
                            // If we are just reordering an existing subtask, it's ALREADY deleted from task_projects.
                            $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid AND project_id = :pid");
                            $stmtDel->execute(['tid' => (int)$draggedTaskId, 'pid' => $targetProjectId]);
                        }
                    } else if ($currentProjectId && $sectionId && (string)$parentTaskId !== (string)$cpId) {
                        // It's genuinely promoted to a top-level task, so it MUST be in task_projects for this project
                        $stmtIns = $this->pdo->prepare("INSERT IGNORE INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, 0)");
                        $stmtIns->execute(['tid' => (int)$draggedTaskId, 'pid' => $currentProjectId, 'sid' => (int)$sectionId]);
                    }
                }
            }
            
            if ($sectionId || $parentTaskId) {
                // If sectionId is available, use it for currentProjectId, else default
                $currentProjectId = null;
                if ($sectionId) {
                    $stmtProj = $this->pdo->prepare("SELECT project_id FROM sections WHERE id = :sid");
                    $stmtProj->execute(['sid' => (int)$sectionId]);
                    $currentProjectId = $stmtProj->fetchColumn();
                }

                $posTopLevel = 1;
                $posBySharedParent = []; // Unified counter for both native and linked children

                foreach ($taskIds as $tid) {
                    $tid = (int)$tid;

                    // Figure out if task natively belongs to the current project
                    $stmtAncestor = $this->pdo->prepare("
                        WITH RECURSIVE ancestor AS (
                            SELECT id, parent_task_id FROM tasks WHERE id = :tid
                            UNION ALL
                            SELECT t.id, t.parent_task_id FROM tasks t INNER JOIN ancestor a ON a.parent_task_id = t.id
                        )
                        SELECT id FROM ancestor WHERE parent_task_id IS NULL
                    ");
                    $stmtAncestor->execute(['tid' => $tid]);
                    $ancestorId = $stmtAncestor->fetchColumn();

                    $isNativeToProject = false;
                    if ($ancestorId && $currentProjectId) {
                        $stmtCheckNative = $this->pdo->prepare("SELECT 1 FROM task_projects WHERE task_id = :aid AND project_id = :pid");
                        $stmtCheckNative->execute(['aid' => $ancestorId, 'pid' => $currentProjectId]);
                        $isNativeToProject = (bool)$stmtCheckNative->fetchColumn();
                    }

                    // Always update position for nested subtasks globally so their order is strictly respected
                    $stmtCheckParent = $this->pdo->prepare("SELECT parent_task_id FROM tasks WHERE id = :tid");
                    $stmtCheckParent->execute(['tid' => $tid]);
                    $tData = $stmtCheckParent->fetch();
                    
                    // Task is top-level (either naturally because parent_task_id is NULL OR it's a linked task masquerading as top-level via task_projects)
                    $isInTaskProjects = false;
                    if ($currentProjectId) {
                        $stmtCheckTP = $this->pdo->prepare("SELECT 1 FROM task_projects WHERE task_id = :tid AND project_id = :pid");
                        $stmtCheckTP->execute(['tid' => $tid, 'pid' => $currentProjectId]);
                        $isInTaskProjects = $stmtCheckTP->fetchColumn();
                    }

                    if ($isInTaskProjects && $sectionId && !$parentTaskId) {
                        // It's a top level task in this project section
                        $stmtPos = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid, position = :pos WHERE task_id = :tid AND project_id = :pid");
                        $stmtPos->execute(['sid' => (int)$sectionId, 'pos' => $posTopLevel, 'tid' => $tid, 'pid' => $currentProjectId]);
                        $posTopLevel++;
                    } elseif ($tData && !is_null($tData['parent_task_id'])) {
                        // Inherently nested task (subtask, sub-subtask, etc). Enforce order structurally.
                        $parent = $tData['parent_task_id'];
                        // Only force order if we're sorting within THIS parent, OR if this is a general list sort
                        if ($parentTaskId && $parent != $parentTaskId) continue;

                        if (!isset($posBySharedParent[$parent])) $posBySharedParent[$parent] = 1;
                        
                        $stmtSubPos = $this->pdo->prepare("UPDATE tasks SET position = :pos WHERE id = :tid");
                        $stmtSubPos->execute(['pos' => $posBySharedParent[$parent], 'tid' => $tid]);
                        $posBySharedParent[$parent]++;
                    }

                    // Also check if this task is a linked subtask ANYWHERE in the system and order it based on its appearance array
                    $stmtLinks = $this->pdo->prepare("SELECT parent_id FROM task_links WHERE subtask_id = :tid");
                    $stmtLinks->execute(['tid' => $tid]);
                    $links = $stmtLinks->fetchAll();
                    foreach ($links as $link) {
                        $lp = $link['parent_id'];
                        // If sorting within a specific parent, don't accidentally update order for other parents
                        if ($parentTaskId && $lp != $parentTaskId) continue;

                        if (!isset($posBySharedParent[$lp])) $posBySharedParent[$lp] = 1;
                        $stmtLp = $this->pdo->prepare("UPDATE task_links SET position = :pos WHERE subtask_id = :tid AND parent_id = :pid");
                        $stmtLp->execute(['pos' => $posBySharedParent[$lp], 'tid' => $tid, 'pid' => $lp]);
                        $posBySharedParent[$lp]++;
                    }
                }
            }
        } else {
            // Kanban View scenario
            if ($parentTaskId) {
                // Dropped in a subtask list. We only want to set order here, not blindly overwrite native parent!
                // Wait! If they drop a NEW unlinked task into a subtask zone, they wanted to parent it native.
                // If it was already a subtask (native or linked), we just reorder it.
                $pos = 1;
                foreach ($taskIds as $tid) {
                    $tid = (int)$tid;
                    
                    // Is it currently a native subtask of parentTaskId?
                    $stmtCheckNat = $this->pdo->prepare("SELECT 1 FROM tasks WHERE id = :tid AND parent_task_id = :pid");
                    $stmtCheckNat->execute(['tid' => $tid, 'pid' => (int)$parentTaskId]);
                    $isNative = $stmtCheckNat->fetchColumn();
                    
                    // Is it currently a linked subtask of parentTaskId?
                    $stmtCheckLin = $this->pdo->prepare("SELECT 1 FROM task_links WHERE subtask_id = :tid AND parent_id = :pid");
                    $stmtCheckLin->execute(['tid' => $tid, 'pid' => (int)$parentTaskId]);
                    $isLinked = $stmtCheckLin->fetchColumn();
                    
                    if (!$isNative && !$isLinked) {
                        // New addition to this parent! Default to native parenting
                        $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = :pid WHERE id = :tid");
                        $stmtTask->execute(['pid' => (int)$parentTaskId, 'tid' => $tid]);
                        
                        // Delete from ALL task_projects so it doesn't appear as a top level anywhere natively anymore
                        $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid");
                        $stmtDel->execute(['tid' => $tid]);
                        
                        $isNative = true;
                    }
                    
                    if ($isNative) {
                        $stmtPos = $this->pdo->prepare("UPDATE tasks SET position = :pos WHERE id = :tid");
                        $stmtPos->execute(['pos' => $pos, 'tid' => $tid]);
                    }
                    if ($isLinked) {
                        $stmtPos = $this->pdo->prepare("UPDATE task_links SET position = :pos WHERE subtask_id = :tid AND parent_id = :pid");
                        $stmtPos->execute(['pos' => $pos, 'tid' => $tid, 'pid' => (int)$parentTaskId]);
                    }
                    $pos++;
                }
            } elseif ($sectionId) {
                // Dropped in a section column. All task_ids are top-level and belong to sectionId.
                // Be careful not to wipe out parent_task_id if it's a multi-homed subtask!
                $stmtCheckProj = $this->pdo->prepare("SELECT project_id FROM sections WHERE id = :sid");
                $stmtCheckProj->execute(['sid' => (int)$sectionId]);
                $cProjId = $stmtCheckProj->fetchColumn();

                $stmtPos = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid, position = :pos WHERE task_id = :tid AND project_id = :pid");
                
                $posTop = 1;
                foreach ($taskIds as $tid) {
                    $stmtPos->execute(['sid' => (int)$sectionId, 'pos' => $posTop, 'tid' => (int)$tid, 'pid' => $cProjId]);
                    $posTop++;
                }
            }
        }
        
        return true;
    }

    public function getTaskById($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = t.id) as assignee_name,
                   (SELECT GROUP_CONCAT(ta.user_id SEPARATOR ',') FROM task_assignees ta WHERE ta.task_id = t.id) as assignee_ids,
                   (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM task_collaborators tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = t.id) as collaborator_name,
                   (SELECT GROUP_CONCAT(tc.user_id SEPARATOR ',') FROM task_collaborators tc WHERE tc.task_id = t.id) as collaborator_ids,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors
            FROM tasks t 
            WHERE t.id = :id
        ");
        $stmt->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            $stmtParents = $this->pdo->prepare("
                SELECT id, title, (SELECT GROUP_CONCAT(project_id SEPARATOR ',') FROM task_projects WHERE task_id = tasks.id) as project_ids 
                FROM tasks 
                WHERE id = :native_parent OR id IN (SELECT parent_id FROM task_links WHERE subtask_id = :tid)
            ");
            $stmtParents->execute(['native_parent' => $task['parent_task_id'] ?: 0, 'tid' => $taskId]);
            $task['parents'] = $stmtParents->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $this->formatTaskDates($task);
    }

    public function getTaskSubtasks($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.title, t.status, t.parent_task_id, t.position as native_pos,
                   (SELECT position FROM task_links WHERE parent_id = :task_id_link AND subtask_id = t.id LIMIT 1) as link_pos,
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') FROM task_collaborators tc JOIN users u2 ON tc.user_id = u2.id WHERE tc.task_id = t.id) as collaborator_name,
                   (SELECT GROUP_CONCAT(tc.user_id SEPARATOR ',') FROM task_collaborators tc WHERE tc.task_id = t.id) as collaborator_ids,
                   (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_names,
                   (SELECT GROUP_CONCAT(tg.id SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_ids,
                   (SELECT GROUP_CONCAT(tg.color SEPARATOR ',') FROM task_labels tt JOIN labels tg ON tt.label_id = tg.id WHERE tt.task_id = t.id) as label_colors,
                   (SELECT COUNT(*) FROM task_projects tp2 WHERE tp2.task_id = t.id) AS project_count
            FROM tasks t 
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id 
            WHERE t.parent_task_id = :task_id
               OR t.id IN (SELECT subtask_id FROM task_links WHERE parent_id = :task_id_link2)
            GROUP BY t.id
            ORDER BY COALESCE(link_pos, t.position) ASC, t.id ASC
        ");
        $stmt->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
        $stmt->bindValue(':task_id_link', (int)$taskId, PDO::PARAM_INT);
        $stmt->bindValue(':task_id_link2', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tasks as &$t) {
            $t = $this->formatTaskDates($t);
        }
        return $tasks;
    }

    public function updateTaskDetails($taskId, $data, $projectId = null) {
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
            $setClauses[] = "completed_date = :completed_date";
            $params[':completed_date'] = ($data['status'] === 'completed' || $data['status'] === 'done') ? date('Y-m-d H:i:s') : null;
        }
        if (array_key_exists('start_date', $data)) {
            $setClauses[] = "start_date = :start_date";
            $params[':start_date'] = $data['start_date'] ? convertTorontoToUtc($data['start_date']) : null;
        }
        if (array_key_exists('expected_start_date', $data)) {
            $setClauses[] = "expected_start_date = :expected_start_date";
            $params[':expected_start_date'] = $data['expected_start_date'] ? convertTorontoToUtc($data['expected_start_date']) : null;
        }
        if (array_key_exists('expected_due_date', $data)) {
            $setClauses[] = "expected_due_date = :expected_due_date";
            $params[':expected_due_date'] = $data['expected_due_date'] ? convertTorontoToUtc($data['expected_due_date']) : null;
        }

        if (array_key_exists('parent_task_id', $data)) {
            // Did it change from subtask to top level, or top level to subtask?
            $currStmt = $this->pdo->prepare("SELECT parent_task_id FROM tasks WHERE id = :tid");
            $currStmt->execute(['tid' => (int)$taskId]);
            $oldParentId = $currStmt->fetchColumn();

            $setClauses[] = "parent_task_id = :parent_task_id";
            $params[':parent_task_id'] = $data['parent_task_id'];

            if ($oldParentId !== $data['parent_task_id'] && $projectId) {
                if ($data['parent_task_id'] === null) {
                    // Changing from subtask to top-level task
                    $this->addTaskToProject($taskId, $projectId);
                } else {
                    // Changing to subtask inside the same project
                    // To support multi-homing accurately, we DO delete from task_projects for the current project.
                    // Doing so removes it from being top-level in THIS project, but keeps it top-level in OTHER projects.
                    $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid AND project_id = :pid");
                    $stmtDel->execute(['tid' => (int)$taskId, 'pid' => $projectId]);
                }
            }
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

            // Fire triggers if status changed
            if (array_key_exists('status', $data)) {
                $this->checkAndFireTaskTriggers($taskId, 'on_status_change');
                if ($data['status'] === 'completed' || $data['status'] === 'done') {
                    $this->checkAndFireTaskTriggers($taskId, 'on_complete');
                }
            }
        }

        if (array_key_exists('assignee_ids', $data)) {
            // Get existing assignees before deleting to know who is newly assigned
            $stmtOldAsg = $this->pdo->prepare("SELECT user_id FROM task_assignees WHERE task_id = :id");
            $stmtOldAsg->execute([':id' => (int)$taskId]);
            $oldAssignees = $stmtOldAsg->fetchAll(PDO::FETCH_COLUMN);

            // Delete existing
            $stmtDel = $this->pdo->prepare("DELETE FROM task_assignees WHERE task_id = :id");
            $stmtDel->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
            $stmtDel->execute();
            
            // Insert new ones and notify newly assigned users
            if (!empty($data['assignee_ids']) && is_array($data['assignee_ids'])) {
                $stmtIns = $this->pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (:tid, :uid)");
                foreach ($data['assignee_ids'] as $uid) {
                    $stmtIns->bindValue(':tid', (int)$taskId, PDO::PARAM_INT);
                    $stmtIns->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
                    $stmtIns->execute();
                    
                    if (!in_array($uid, $oldAssignees)) {
                        // This user is newly assigned
                        $this->sendAssigneeNotification($taskId, $uid);
                    }
                }
            }
            
            // Notify removed users
            foreach ($oldAssignees as $oldUid) {
                if (empty($data['assignee_ids']) || !in_array($oldUid, $data['assignee_ids'])) {
                    $this->sendAssigneeRemovedNotification($taskId, $oldUid);
                }
            }
        }
        
        if (array_key_exists('collaborator_ids', $data)) {
            // Get existing collaborators before deleting
            $stmtOldCollab = $this->pdo->prepare("SELECT user_id FROM task_collaborators WHERE task_id = :id");
            $stmtOldCollab->execute([':id' => (int)$taskId]);
            $oldCollaborators = $stmtOldCollab->fetchAll(PDO::FETCH_COLUMN);

            // Delete existing
            $stmtDelCollab = $this->pdo->prepare("DELETE FROM task_collaborators WHERE task_id = :id");
            $stmtDelCollab->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
            $stmtDelCollab->execute();
            
            // Insert new ones
            if (!empty($data['collaborator_ids']) && is_array($data['collaborator_ids'])) {
                $stmtInsCollab = $this->pdo->prepare("INSERT INTO task_collaborators (task_id, user_id) VALUES (:tid, :uid)");
                foreach ($data['collaborator_ids'] as $uid) {
                    $stmtInsCollab->bindValue(':tid', (int)$taskId, PDO::PARAM_INT);
                    $stmtInsCollab->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
                    $stmtInsCollab->execute();

                    if (!in_array($uid, $oldCollaborators)) {
                        $this->sendCollaboratorNotification($taskId, $uid);
                    }
                }
            }
            
            // Notify removed users
            foreach ($oldCollaborators as $oldUid) {
                if (empty($data['collaborator_ids']) || !in_array($oldUid, $data['collaborator_ids'])) {
                    $this->sendCollaboratorRemovedNotification($taskId, $oldUid);
                }
            }
        }
        
        if (array_key_exists('label_ids', $data)) {
            $stmtDelLabels = $this->pdo->prepare("DELETE FROM task_labels WHERE task_id = :id");
            $stmtDelLabels->bindValue(':id', (int)$taskId, PDO::PARAM_INT);
            $stmtDelLabels->execute();
            
            if (!empty($data['label_ids']) && is_array($data['label_ids'])) {
                $stmtInsLabels = $this->pdo->prepare("INSERT INTO task_labels (task_id, label_id) VALUES (:tid, :lid)");
                foreach ($data['label_ids'] as $lid) {
                    $stmtInsLabels->bindValue(':tid', (int)$taskId, PDO::PARAM_INT);
                    $stmtInsLabels->bindValue(':lid', (int)$lid, PDO::PARAM_INT);
                    $stmtInsLabels->execute();
                }
            }
        }

        return true;
    }

    public function getTaskTimeLogStatus($taskId, $userId) {
        $stmtRunning = $this->pdo->prepare("
            SELECT start_time FROM task_time_logs 
            WHERE task_id = :task_id AND user_id = :user_id AND status = 'running'
            LIMIT 1
        ");
        $stmtRunning->execute([':task_id' => $taskId, ':user_id' => $userId]);
        $runningLog = $stmtRunning->fetch();

        $stmtTotal = $this->pdo->prepare("
            SELECT SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as total_seconds 
            FROM task_time_logs 
            WHERE task_id = :task_id AND user_id = :user_id AND status = 'completed' AND end_time IS NOT NULL
        ");
        $stmtTotal->execute([':task_id' => $taskId, ':user_id' => $userId]);
        $totalSeconds = (int)$stmtTotal->fetchColumn();

        return [
            'is_running' => !!$runningLog,
            'running_since' => $runningLog ? convertUtcToToronto($runningLog['start_time']) : null,
            'total_tracked_seconds' => $totalSeconds
        ];
    }

    public function toggleTaskTimeTrack($taskId, $userId) {
        $status = $this->getTaskTimeLogStatus($taskId, $userId);
        if ($status['is_running']) {
            $stmt = $this->pdo->prepare("
                UPDATE task_time_logs 
                SET end_time = NOW(), status = 'completed' 
                WHERE task_id = :task_id AND user_id = :user_id AND status = 'running'
            ");
            $stmt->execute([':task_id' => $taskId, ':user_id' => $userId]);
            return ['action' => 'paused'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO task_time_logs (task_id, user_id, start_time, status) 
                VALUES (:task_id, :user_id, NOW(), 'running')
            ");
            $stmt->execute([':task_id' => $taskId, ':user_id' => $userId]);
            
            // Auto-populate start_date if null
            $stmtCheck = $this->pdo->prepare("SELECT start_date FROM tasks WHERE id = :id");
            $stmtCheck->execute([':id' => $taskId]);
            $currentStartDate = $stmtCheck->fetchColumn();
            if (empty($currentStartDate)) {
                $stmtSetStart = $this->pdo->prepare("UPDATE tasks SET start_date = NOW() WHERE id = :id");
                $stmtSetStart->execute([':id' => $taskId]);
            }
            
            return ['action' => 'started'];
        }
    }

    public function sendAssigneeNotification($taskId, $userId) {
        try {
            if (isset($this->notifiedUsers[$taskId]) && in_array($userId, $this->notifiedUsers[$taskId])) {
                return true; // Already notified in this request
            }
            
            // Get Task details
            $taskDetails = $this->getTaskById($taskId);
            $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $taskId;
            
            // Get User details
            $stmtUser = $this->pdo->prepare("SELECT email, name FROM users WHERE id = :uid");
            $stmtUser->execute(['uid' => $userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;

            // Get Changer details
            $changerId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            $stmtChanger = $this->pdo->prepare("SELECT name FROM users WHERE id = :uid");
            $stmtChanger->execute(['uid' => $changerId]);
            $changerName = $stmtChanger->fetchColumn() ?: 'Someone';
            
            $this->notifiedUsers[$taskId][] = $userId;
            
            // Insert DB notification
            $stmtInsertNotif = $this->pdo->prepare("INSERT INTO notifications (user_id, task_id, category, message, is_read) VALUES (:uid, :tid, :cat, :msg, 0)");
            $stmtInsertNotif->execute([
                'uid' => $userId,
                'tid' => $taskId,
                'cat' => 'task_update',
                'msg' => $changerName . " assigned you to: " . $taskTitle
            ]);
            
            // Get Project ID for URL
            $stmtProj = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :tid LIMIT 1");
            $stmtProj->execute(['tid' => $taskId]);
            $projectId = $stmtProj->fetchColumn() ?: 1;
            $taskUrl = "http://" . $_SERVER['HTTP_HOST'] . "/bathyal/project?id=" . $projectId . "&task_id=" . $taskId;
            
            // Send Email
            require_once __DIR__ . '/email_service.php';
            $emailService = new EmailService();
            
            $subject = "You have been assigned to: " . $taskTitle;
            $bodyHtml = "<h2>You have been assigned to a task</h2>";
            $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> assigned you to the task: " . $taskTitle . "</p>";
            $bodyHtml .= "<p><a href='{$taskUrl}'>Click here to view the task</a></p>";
            
            $emailService->sendEmail($user['email'], $user['name'], $subject, $bodyHtml);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send assignee notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendAssigneeRemovedNotification($taskId, $userId) {
        try {
            // Get Task details
            $taskDetails = $this->getTaskById($taskId);
            $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $taskId;
            
            // Get User details
            $stmtUser = $this->pdo->prepare("SELECT email, name FROM users WHERE id = :uid");
            $stmtUser->execute(['uid' => $userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;

            // Get Changer details
            $changerId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            $stmtChanger = $this->pdo->prepare("SELECT name FROM users WHERE id = :uid");
            $stmtChanger->execute(['uid' => $changerId]);
            $changerName = $stmtChanger->fetchColumn() ?: 'Someone';
            
            // Insert DB notification
            $stmtInsertNotif = $this->pdo->prepare("INSERT INTO notifications (user_id, task_id, category, message, is_read) VALUES (:uid, :tid, :cat, :msg, 0)");
            $stmtInsertNotif->execute([
                'uid' => $userId,
                'tid' => $taskId,
                'cat' => 'task_update',
                'msg' => $changerName . " removed you from the assignees of: " . $taskTitle
            ]);
            
            // Get Project ID for URL
            $stmtProj = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :tid LIMIT 1");
            $stmtProj->execute(['tid' => $taskId]);
            $projectId = $stmtProj->fetchColumn() ?: 1;
            $taskUrl = "http://" . $_SERVER['HTTP_HOST'] . "/bathyal/project?id=" . $projectId . "&task_id=" . $taskId;
            
            // Send Email
            require_once __DIR__ . '/email_service.php';
            $emailService = new EmailService();
            
            $subject = "You have been removed from task: " . $taskTitle;
            $bodyHtml = "<h2>You are no longer assigned to a task</h2>";
            $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> removed you from the assignees of the task: " . $taskTitle . "</p>";
            $bodyHtml .= "<p><a href='{$taskUrl}'>Click here to view the task</a></p>";
            
            $emailService->sendEmail($user['email'], $user['name'], $subject, $bodyHtml);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send assignee removed notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendCollaboratorNotification($taskId, $userId) {
        try {
            if (isset($this->notifiedUsers[$taskId]) && in_array($userId, $this->notifiedUsers[$taskId])) {
                return true; // Already notified in this request
            }
            
            // Get Task details
            $taskDetails = $this->getTaskById($taskId);
            $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $taskId;
            
            // Get User details
            $stmtUser = $this->pdo->prepare("SELECT email, name FROM users WHERE id = :uid");
            $stmtUser->execute(['uid' => $userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;

            // Get Changer details
            $changerId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            $stmtChanger = $this->pdo->prepare("SELECT name FROM users WHERE id = :uid");
            $stmtChanger->execute(['uid' => $changerId]);
            $changerName = $stmtChanger->fetchColumn() ?: 'Someone';
            
            $this->notifiedUsers[$taskId][] = $userId;
            
            // Insert DB notification
            $stmtInsertNotif = $this->pdo->prepare("INSERT INTO notifications (user_id, task_id, category, message, is_read) VALUES (:uid, :tid, :cat, :msg, 0)");
            $stmtInsertNotif->execute([
                'uid' => $userId,
                'tid' => $taskId,
                'cat' => 'task_update',
                'msg' => $changerName . " added you as a collaborator to: " . $taskTitle
            ]);
            
            // Get Project ID for URL
            $stmtProj = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :tid LIMIT 1");
            $stmtProj->execute(['tid' => $taskId]);
            $projectId = $stmtProj->fetchColumn() ?: 1;
            $taskUrl = "http://" . $_SERVER['HTTP_HOST'] . "/bathyal/project?id=" . $projectId . "&task_id=" . $taskId;
            
            // Send Email
            require_once __DIR__ . '/email_service.php';
            $emailService = new EmailService();
            
            $subject = "You have been added as a collaborator to: " . $taskTitle;
            $bodyHtml = "<h2>You have been added as a collaborator</h2>";
            $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> added you as a collaborator to the task: " . $taskTitle . "</p>";
            $bodyHtml .= "<p><a href='{$taskUrl}'>Click here to view the task</a></p>";
            
            $emailService->sendEmail($user['email'], $user['name'], $subject, $bodyHtml);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send collaborator notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendCollaboratorRemovedNotification($taskId, $userId) {
        try {
            // Get Task details
            $taskDetails = $this->getTaskById($taskId);
            $taskTitle = $taskDetails ? htmlspecialchars($taskDetails['title']) : "Task " . $taskId;
            
            // Get User details
            $stmtUser = $this->pdo->prepare("SELECT email, name FROM users WHERE id = :uid");
            $stmtUser->execute(['uid' => $userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;

            // Get Changer details
            $changerId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            $stmtChanger = $this->pdo->prepare("SELECT name FROM users WHERE id = :uid");
            $stmtChanger->execute(['uid' => $changerId]);
            $changerName = $stmtChanger->fetchColumn() ?: 'Someone';
            
            // Insert DB notification
            $stmtInsertNotif = $this->pdo->prepare("INSERT INTO notifications (user_id, task_id, category, message, is_read) VALUES (:uid, :tid, :cat, :msg, 0)");
            $stmtInsertNotif->execute([
                'uid' => $userId,
                'tid' => $taskId,
                'cat' => 'task_update',
                'msg' => $changerName . " removed you from the collaborators of: " . $taskTitle
            ]);
            
            // Get Project ID for URL
            $stmtProj = $this->pdo->prepare("SELECT project_id FROM task_projects WHERE task_id = :tid LIMIT 1");
            $stmtProj->execute(['tid' => $taskId]);
            $projectId = $stmtProj->fetchColumn() ?: 1;
            $taskUrl = "http://" . $_SERVER['HTTP_HOST'] . "/bathyal/project?id=" . $projectId . "&task_id=" . $taskId;
            
            // Send Email
            require_once __DIR__ . '/email_service.php';
            $emailService = new EmailService();
            
            $subject = "You have been removed from task collaborators: " . $taskTitle;
            $bodyHtml = "<h2>You are no longer a collaborator</h2>";
            $bodyHtml .= "<p><strong>" . htmlspecialchars($changerName) . "</strong> removed you from the collaborators of the task: " . $taskTitle . "</p>";
            $bodyHtml .= "<p><a href='{$taskUrl}'>Click here to view the task</a></p>";
            
            $emailService->sendEmail($user['email'], $user['name'], $subject, $bodyHtml);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send collaborator removed notification: " . $e->getMessage());
            return false;
        }
    }

    // --- ATTACHMENTS ---
    public function getTaskAttachments($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM task_attachments 
            WHERE task_id = :task_id 
            ORDER BY created_at DESC
        ");
        $stmt->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attachments as &$att) {
            if (isset($att['created_at'])) {
                $att['created_at'] = convertUtcToToronto($att['created_at']);
            }
        }
        return $attachments;
    }

    public function createTaskAttachment($taskId, $userId, $fileName, $filePath, $fileType) {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type) 
            VALUES (:task_id, :user_id, :file_name, :file_path, :file_type)
        ");
        $stmt->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':file_name', $fileName, PDO::PARAM_STR);
        $stmt->bindValue(':file_path', $filePath, PDO::PARAM_STR);
        $stmt->bindValue(':file_type', $fileType, PDO::PARAM_STR);
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    public function deleteTaskAttachment($attachmentId) {
        $stmt = $this->pdo->prepare("SELECT file_path FROM task_attachments WHERE id = :id");
        $stmt->execute([':id' => $attachmentId]);
        $attachment = $stmt->fetch();
        if ($attachment) {
            $fullPath = __DIR__ . '/../' . $attachment['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            $del = $this->pdo->prepare("DELETE FROM task_attachments WHERE id = :id");
            return $del->execute([':id' => $attachmentId]);
        }
        return false;
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
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($comments as &$c) {
            if (isset($c['created_at'])) {
                $c['created_at'] = convertUtcToToronto($c['created_at']);
            }
        }
        return $comments;
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

    public function updateComment($commentId, $userId, $content) {
        $stmt = $this->pdo->prepare("
            UPDATE comments 
            SET content = :content 
            WHERE id = :comment_id AND user_id = :user_id
        ");
        $stmt->bindValue(':comment_id', (int)$commentId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function linkSubtask($parentId, $subtaskId) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO task_links (parent_id, subtask_id) 
            VALUES (:pid, :sid)
        ");
        $stmt->execute(['pid' => (int)$parentId, 'sid' => (int)$subtaskId]);
        return true;
    }

    public function unlinkSubtask($parentId, $subtaskId) {
        // Only delink from pivot table, not native table
        $stmt = $this->pdo->prepare("
            DELETE FROM task_links 
            WHERE parent_id = :pid AND subtask_id = :sid
        ");
        $stmt->execute(['pid' => (int)$parentId, 'sid' => (int)$subtaskId]);

        return true;
    }

    public function getTaskProjects($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT tp.project_id, tp.section_id, p.name as project_name, s.name as section_name
            FROM task_projects tp
            JOIN projects p ON tp.project_id = p.id
            JOIN sections s ON tp.section_id = s.id
            WHERE tp.task_id = :tid
        ");
        $stmt->bindValue(':tid', (int)$taskId, PDO::PARAM_INT);
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($projects as &$proj) {
            $proj['all_sections'] = $this->getSectionsByProjectId($proj['project_id']);
        }
        
        return $projects;
    }

    public function searchProjects($query, $userId, $limit = 10) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.name 
                FROM projects p 
                WHERE p.name LIKE :query 
                LIMIT " . (int)$limit
            );
            $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.name 
                FROM projects p 
                JOIN project_members pm ON p.id = pm.project_id
                WHERE pm.user_id = :userId AND p.name LIKE :query 
                LIMIT " . (int)$limit
            );
            $stmt->bindValue(':userId', (int)$userId, PDO::PARAM_INT);
            $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addTaskToProject($taskId, $projectId) {
        // Get first section of the project
        $stmtSec = $this->pdo->prepare("SELECT id FROM sections WHERE project_id = :pid ORDER BY position ASC LIMIT 1");
        $stmtSec->execute(['pid' => $projectId]);
        $sectionId = $stmtSec->fetchColumn();
        
        if ($sectionId) {
            $stmtIns = $this->pdo->prepare("INSERT IGNORE INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, 0)");
            if ($stmtIns->execute(['tid' => $taskId, 'pid' => $projectId, 'sid' => $sectionId])) {
                return $sectionId;
            }
        }
        return false;
    }

    public function changeTaskProjectSection($taskId, $projectId, $sectionId) {
        $stmtUpd = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid WHERE task_id = :tid AND project_id = :pid");
        return $stmtUpd->execute(['sid' => $sectionId, 'tid' => $taskId, 'pid' => $projectId]);
    }

    public function getActiveTimerForUser($userId) {
        $stmt = $this->pdo->prepare("
            SELECT t.title, tl.start_time, t.id as task_id, tp.project_id
            FROM task_time_logs tl
            JOIN tasks t ON tl.task_id = t.id
            LEFT JOIN task_projects tp ON t.id = tp.task_id
            WHERE tl.user_id = :user_id AND tl.status = 'running'
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch();
    }

    public function updateUserPreferences($userId, $jsonStr) {
        $stmt = $this->pdo->prepare("UPDATE users SET dashboard_preferences = :prefs WHERE id = :user_id");
        return $stmt->execute([':prefs' => $jsonStr, ':user_id' => $userId]);
    }

    public function getDashboardStats($userId) {
        // Tasks completed
        $stmt = $this->pdo->prepare("
            SELECT COUNT(t.id) as count 
            FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id = :user_id AND t.status IN ('done', 'completed')
        ");
        $stmt->execute([':user_id' => $userId]);
        $tasksCompleted = $stmt->fetchColumn();

        // Collaborators
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT pm2.user_id) as count 
            FROM project_members pm1
            JOIN project_members pm2 ON pm1.project_id = pm2.project_id
            WHERE pm1.user_id = :user_id1 AND pm2.user_id != :user_id2
        ");
        $stmt->execute([':user_id1' => $userId, ':user_id2' => $userId]);
        $collaborators = $stmt->fetchColumn();

        // Tasks due soon (next 3 days)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(t.id) as count 
            FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id = :user_id 
              AND t.status NOT IN ('done', 'completed')
              AND t.expected_due_date IS NOT NULL
              AND t.expected_due_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)
              AND t.expected_due_date >= NOW()
        ");
        $stmt->execute([':user_id' => $userId]);
        $tasksDueSoon = $stmt->fetchColumn();

        return [
            'tasks_completed' => (int)$tasksCompleted,
            'collaborators' => (int)$collaborators,
            'tasks_due_soon' => (int)$tasksDueSoon
        ];
    }

    // --- LABELS ---
    public function getLabels() {
        $stmt = $this->pdo->prepare("SELECT * FROM labels ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createLabel($name, $color) {
        $stmt = $this->pdo->prepare("INSERT INTO labels (name, color) VALUES (:name, :color)");
        $stmt->execute([':name' => $name, ':color' => $color]);
        return $this->pdo->lastInsertId();
    }

    public function updateLabel($id, $name, $color) {
        $stmt = $this->pdo->prepare("UPDATE labels SET name = :name, color = :color WHERE id = :id");
        return $stmt->execute([':name' => $name, ':color' => $color, ':id' => (int)$id]);
    }

    public function deleteLabel($id) {
        $stmt = $this->pdo->prepare("DELETE FROM labels WHERE id = :id");
        return $stmt->execute([':id' => (int)$id]);
    }

    public function getProjectsForUser($userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmt = $this->pdo->prepare("
                SELECT p.* 
                FROM projects p
                ORDER BY p.created_at DESC
            ");
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT p.* 
                FROM projects p
                JOIN project_members pm ON p.id = pm.project_id
                WHERE pm.user_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([(int)$userId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDashboardProjects($userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmt = $this->pdo->prepare("
                SELECT p.* 
                FROM projects p
                ORDER BY p.created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT p.* 
                FROM projects p
                JOIN project_members pm ON p.id = pm.project_id
                WHERE pm.user_id = ?
                ORDER BY p.created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([(int)$userId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportTaskStatus($userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmtStatus = $this->pdo->prepare("
                SELECT t.status, COUNT(DISTINCT t.id) as count 
                FROM tasks t
                GROUP BY t.status
            ");
            $stmtStatus->execute();
        } else {
            $stmtStatus = $this->pdo->prepare("
                SELECT t.status, COUNT(DISTINCT t.id) as count 
                FROM tasks t
                JOIN task_projects tp ON t.id = tp.task_id
                JOIN project_members pm ON tp.project_id = pm.project_id
                WHERE pm.user_id = :userId
                GROUP BY t.status
            ");
            $stmtStatus->execute(['userId' => (int)$userId]);
        }
        return $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportTasksByProject($userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmtProject = $this->pdo->prepare("
                SELECT p.name, COUNT(tp.task_id) as task_count 
                FROM projects p 
                LEFT JOIN task_projects tp ON p.id = tp.project_id 
                GROUP BY p.id 
                ORDER BY task_count DESC 
                LIMIT 5
            ");
            $stmtProject->execute();
        } else {
            $stmtProject = $this->pdo->prepare("
                SELECT p.name, COUNT(tp.task_id) as task_count 
                FROM projects p 
                JOIN project_members pm ON p.id = pm.project_id
                LEFT JOIN task_projects tp ON p.id = tp.project_id 
                WHERE pm.user_id = :userId
                GROUP BY p.id 
                ORDER BY task_count DESC 
                LIMIT 5
            ");
            $stmtProject->execute(['userId' => (int)$userId]);
        }
        return $stmtProject->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportUserWorkload($userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmtWorkload = $this->pdo->prepare("
                SELECT u.name as assignee_name, COUNT(DISTINCT ta.task_id) as count 
                FROM users u 
                JOIN task_assignees ta ON u.id = ta.user_id 
                JOIN tasks t ON ta.task_id = t.id
                WHERE t.status != 'completed'
                GROUP BY u.id 
                ORDER BY count DESC 
                LIMIT 5
            ");
            $stmtWorkload->execute();
        } else {
            $stmtWorkload = $this->pdo->prepare("
                SELECT u.name as assignee_name, COUNT(DISTINCT ta.task_id) as count 
                FROM users u 
                JOIN task_assignees ta ON u.id = ta.user_id 
                JOIN tasks t ON ta.task_id = t.id
                JOIN task_projects tp ON t.id = tp.task_id
                JOIN project_members pm ON tp.project_id = pm.project_id
                WHERE t.status != 'completed' AND pm.user_id = :userId
                GROUP BY u.id 
                ORDER BY count DESC 
                LIMIT 5
            ");
            $stmtWorkload->execute(['userId' => (int)$userId]);
        }
        return $stmtWorkload->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReportOverdueTasksCount($userId) {
        $stmtUser = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([(int)$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'admin') {
            $stmtOverdue = $this->pdo->prepare("
                SELECT COUNT(DISTINCT t.id) as count 
                FROM tasks t
                WHERE t.expected_due_date < NOW() 
                AND t.status != 'completed'
            ");
            $stmtOverdue->execute();
        } else {
            $stmtOverdue = $this->pdo->prepare("
                SELECT COUNT(DISTINCT t.id) as count 
                FROM tasks t
                JOIN task_projects tp ON t.id = tp.task_id
                JOIN project_members pm ON tp.project_id = pm.project_id
                WHERE t.expected_due_date < NOW() 
                AND t.status != 'completed'
                AND pm.user_id = :userId
            ");
            $stmtOverdue->execute(['userId' => (int)$userId]);
        }
        return $stmtOverdue->fetchColumn();
    }
}
