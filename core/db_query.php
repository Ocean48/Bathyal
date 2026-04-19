<?php
// /includes/db_query.php

class DBQueries {
    private $pdo;

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
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.name 
            FROM projects p
            JOIN user_recent_projects urp ON p.id = urp.project_id
            WHERE urp.user_id = :userId
            ORDER BY urp.last_accessed DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':userId', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback to latest created globally if user has no recent history
        if (empty($recentProjects)) {
            $recentProjectsStmt = $this->pdo->prepare("SELECT id, name FROM projects ORDER BY created_at DESC LIMIT :limit");
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

    public function getAllSystemUsers() {
        $stmt = $this->pdo->prepare("SELECT id, name, email, role, created_at, team_id FROM users ORDER BY role ASC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateSystemUserRole($userId, $role) {
        $stmt = $this->pdo->prepare("UPDATE users SET role = :role WHERE id = :user_id");
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
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
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) + 
                       (SELECT COUNT(*) FROM task_links tl WHERE tl.parent_id = t.id)
                   ) AS subtask_count
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
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) + 
                       (SELECT COUNT(*) FROM task_links tl WHERE tl.parent_id = t.id)
                   ) AS subtask_count
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

        // Fetch explicitly linked subtasks (cross-project links)
        $stmtLinked = $this->pdo->prepare("
            SELECT tl.parent_id as _tl_parent, t.*, tl.position as link_position,
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) +
                       (SELECT COUNT(*) FROM task_links tl2 WHERE tl2.parent_id = t.id)
                   ) AS subtask_count
            FROM task_links tl
            JOIN tasks t ON tl.subtask_id = t.id
            LEFT JOIN task_assignees ta ON t.id = ta.task_id
            LEFT JOIN users u ON ta.user_id = u.id
            WHERE tl.parent_id IN (
                WITH RECURSIVE TaskTree AS (
                    SELECT task_id FROM task_projects WHERE project_id = :pid
                    UNION ALL
                    SELECT t2.id FROM tasks t2 INNER JOIN TaskTree tt ON t2.parent_task_id = tt.task_id
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
        return $tasks;
    }

    public function getAllTasks() {
        $stmt = $this->pdo->query("
            SELECT t.*,
                   (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') FROM task_projects tp JOIN projects p ON tp.project_id = p.id WHERE tp.task_id = t.id) AS project_names,
                   (
                       (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) + 
                       (SELECT COUNT(*) FROM task_links tl WHERE tl.parent_id = t.id)
                   ) AS subtask_count
            FROM tasks t
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTasksByAssigneeId($userId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   COALESCE(p1.name, p2.name, p3.name) AS project_name, 
                   COALESCE(tp1.project_id, tp2.project_id, tp3.project_id) AS project_id
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
            ORDER BY t.due_date ASC, t.id DESC
        ");
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTask($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (parent_task_id, title, description, status, due_date, start_date, completed_date) 
            VALUES (:parent_task_id, :title, :description, :status, :due_date, :start_date, :completed_date)
        ");
        $stmt->bindValue(':parent_task_id', isset($data['parent_task_id']) ? (int)$data['parent_task_id'] : null, isset($data['parent_task_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, $data['description'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        $status = $data['status'] ?? 'todo';
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        
        $stmt->bindValue(':due_date', $data['due_date'] ?? null, $data['due_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':start_date', $data['start_date'] ?? null, $data['start_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
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
        return $stmt->execute();
    }

    // --- NEW DB QUERIES ---

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
        $stmt = $this->pdo->prepare("SELECT role FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmt->execute(['pid' => (int)$projectId, 'uid' => (int)$userId]);
        return $stmt->fetchColumn();
    }

    public function updateProjectMemberRole($projectId, $userId, $role) {
        $stmt = $this->pdo->prepare("UPDATE project_members SET role = :role WHERE project_id = :pid AND user_id = :uid");
        return $stmt->execute(['role' => $role, 'pid' => (int)$projectId, 'uid' => (int)$userId]);
    }

    public function removeProjectMember($projectId, $userId) {
        $stmt = $this->pdo->prepare("DELETE FROM project_members WHERE project_id = :pid AND user_id = :uid");
        return $stmt->execute(['pid' => (int)$projectId, 'uid' => (int)$userId]);
    }

    public function addProjectMember($projectId, $userId, $role) {
        $stmtCheck = $this->pdo->prepare("SELECT 1 FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtCheck->execute(['pid' => (int)$projectId, 'uid' => (int)$userId]);
        if (!$stmtCheck->fetchColumn()) {
            $stmt = $this->pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (:pid, :uid, :role)");
            return $stmt->execute(['pid' => (int)$projectId, 'uid' => (int)$userId, 'role' => $role]);
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
        $query = "SELECT id, name, email FROM users WHERE team_id = ? AND id NOT IN ($placeholders) ORDER BY name ASC";
        $params = array_merge([(int)$teamId], count($memberIds) > 0 ? $memberIds : []);

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

    public function searchUsers($search, $projectId = null) {
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

            if ($search !== '') {
                $sql .= " WHERE name LIKE ? OR email LIKE ?";
                $params[] = "%$search%";
                $params[] = "%$search%";
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
        if ($systemRole === 'admin' || $systemRole === 'member') {
            // Can see all teams
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name, t.created_by, t.created_at, 
                       COALESCE(tm.role, 'viewer') as role 
                FROM teams t
                LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = :uid
                ORDER BY t.name ASC
            ");
            $stmt->execute(['uid' => (int)$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Only see teams they are a member of
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name, t.created_by, t.created_at, tm.role 
                FROM teams t
                JOIN team_members tm ON t.id = tm.team_id
                WHERE tm.user_id = :uid
                ORDER BY t.name ASC
            ");
            $stmt->execute(['uid' => (int)$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addTeamMember($teamId, $userId, $role = 'member') {
        $stmtCheck = $this->pdo->prepare("SELECT 1 FROM team_members WHERE team_id = :tid AND user_id = :uid");
        $stmtCheck->execute(['tid' => (int)$teamId, 'uid' => (int)$userId]);
        if (!$stmtCheck->fetchColumn()) {
            $stmt = $this->pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (:tid, :uid, :role)");
            return $stmt->execute(['tid' => (int)$teamId, 'uid' => (int)$userId, 'role' => $role]);
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

    public function reorderTasks($sectionId, $taskIds, $parentTaskId = null, $draggedTaskId = null) {
        if (!empty($draggedTaskId)) {
            // List view drag & drop
            // First, check if draggedTaskId is a linked subtask to this parent
            $isLinkedToNewParent = false;
            if ($parentTaskId) {
                $stmtCheckLin = $this->pdo->prepare("SELECT 1 FROM task_links WHERE subtask_id = :tid AND parent_id = :pid");
                $stmtCheckLin->execute(['tid' => (int)$draggedTaskId, 'pid' => (int)$parentTaskId]);
                $isLinkedToNewParent = $stmtCheckLin->fetchColumn();
            }

            if (!$isLinkedToNewParent) {
                // Update the single dragged task's parent if it was shifted to natively belong to a parent
                // We do NOT alter tasks table if it's already a linked subtask to this parent
                $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = :pid WHERE id = :tid");
                $stmtTask->execute([
                    'pid' => $parentTaskId ?: null,
                    'tid' => (int)$draggedTaskId
                ]);
                
                if ($parentTaskId) {
                    $stmtDel = $this->pdo->prepare("DELETE FROM task_projects WHERE task_id = :tid");
                    $stmtDel->execute(['tid' => (int)$draggedTaskId]);
                }
            }
            
            if ($sectionId) {
                $stmtProj = $this->pdo->prepare("SELECT project_id FROM sections WHERE id = :sid");
                $stmtProj->execute(['sid' => (int)$sectionId]);
                $currentProjectId = $stmtProj->fetchColumn();

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

                    if ($isNativeToProject) {
                        // Top-level / Native check
                        $stmtCheckParent = $this->pdo->prepare("SELECT parent_task_id FROM tasks WHERE id = :tid");
                        $stmtCheckParent->execute(['tid' => $tid]);
                        $tData = $stmtCheckParent->fetch();
                        
                        if ($tData && is_null($tData['parent_task_id'])) {
                            // It's a top level task in this section
                            $stmtPos = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid, position = :pos WHERE task_id = :tid");
                            $stmtPos->execute(['sid' => (int)$sectionId, 'pos' => $posTopLevel, 'tid' => $tid]);
                            
                            $stmtCheckExist = $this->pdo->prepare("SELECT COUNT(*) FROM task_projects WHERE task_id = :tid AND section_id = :sid");
                            $stmtCheckExist->execute(['tid' => $tid, 'sid' => (int)$sectionId]);
                            
                            if ($stmtCheckExist->fetchColumn() == 0) {
                                $stmtIns = $this->pdo->prepare("INSERT IGNORE INTO task_projects (task_id, project_id, section_id, position) VALUES (:tid, :pid, :sid, :pos)");
                                $stmtIns->execute(['tid' => $tid, 'pid' => $currentProjectId, 'sid' => (int)$sectionId, 'pos' => $posTopLevel]);
                            }
                            $posTopLevel++;
                        } elseif ($tData && !is_null($tData['parent_task_id'])) {
                            // Native subtask
                            $parent = $tData['parent_task_id'];
                            if (!isset($posBySharedParent[$parent])) $posBySharedParent[$parent] = 1;
                            
                            $stmtSubPos = $this->pdo->prepare("UPDATE tasks SET position = :pos WHERE id = :tid");
                            $stmtSubPos->execute(['pos' => $posBySharedParent[$parent], 'tid' => $tid]);
                            $posBySharedParent[$parent]++;
                        }
                    }

                    // Also check if this task is a linked subtask ANYWHERE in the system and order it based on its appearance array
                    // Since it appears in the list view, we update its link position for whatever parent it's linked to.
                    // But in a flat list we don't know which parent context it sits in, so we update it for ALL its linked parents where it appears.
                    // This is a sensible fallback.
                    $stmtLinks = $this->pdo->prepare("SELECT parent_id FROM task_links WHERE subtask_id = :tid");
                    $stmtLinks->execute(['tid' => $tid]);
                    $links = $stmtLinks->fetchAll();
                    foreach ($links as $link) {
                        $lp = $link['parent_id'];
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
            } else {
                // Dropped in a section column. All task_ids are top-level and belong to sectionId.
                $stmtTask = $this->pdo->prepare("UPDATE tasks SET parent_task_id = NULL WHERE id = :tid");
                $stmtPos = $this->pdo->prepare("UPDATE task_projects SET section_id = :sid, position = :pos WHERE task_id = :tid");
                
                foreach ($taskIds as $index => $tid) {
                    $tid = (int)$tid;
                    
                    // We must avoid breaking linked tasks. Linked tasks have parent_task_id = NULL naturally.
                    // Doing parent_task_id = NULL is fine. But we shouldn't wipe out task_links if they had any, we just ignore links.
                    $stmtTask->execute(['tid' => $tid]);
                    $stmtPos->execute(['sid' => (int)$sectionId, 'pos' => $index + 1, 'tid' => $tid]);
                    
                    $stmtCheckExist = $this->pdo->prepare("SELECT COUNT(*) FROM task_projects WHERE task_id = :tid AND section_id = :sid");
                    $stmtCheckExist->execute(['tid' => $tid, 'sid' => (int)$sectionId]);
                    
                    if ($stmtCheckExist->fetchColumn() == 0) {
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
            SELECT t.id, t.title, t.status, t.parent_task_id, t.position as native_pos,
                   (SELECT position FROM task_links WHERE parent_id = :task_id_link AND subtask_id = t.id LIMIT 1) as link_pos,
                   GROUP_CONCAT(u.name SEPARATOR ', ') as assignee_name,
                   GROUP_CONCAT(u.id SEPARATOR ',') as assignee_ids 
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
            $setClauses[] = "completed_date = :completed_date";
            $params[':completed_date'] = ($data['status'] === 'completed' || $data['status'] === 'done') ? date('Y-m-d H:i:s') : null;
        }
        if (array_key_exists('start_date', $data)) {
            $setClauses[] = "start_date = :start_date";
            $params[':start_date'] = $data['start_date'] ?: null;
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
            'running_since' => $runningLog ? $runningLog['start_time'] : null,
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
            return ['action' => 'started'];
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
}
?>