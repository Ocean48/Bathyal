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

    // --- PROJECTS ---
    public function getProjectById($projectId) {
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :project_id");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getSectionsByProjectId($projectId) {
        $stmt = $this->pdo->prepare("SELECT * FROM sections WHERE project_id = :project_id ORDER BY position ASC");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- TASKS ---
    public function getTasksByProjectId($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, tp.section_id, tp.position as tp_position, u.name as assignee_name 
            FROM tasks t
            JOIN task_projects tp ON t.id = tp.task_id
            LEFT JOIN users u ON t.assignee_id = u.id
            WHERE tp.project_id = :project_id
            ORDER BY tp.position ASC
        ");
        $stmt->bindValue(':project_id', (int)$projectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllTasks() {
        $stmt = $this->pdo->query("SELECT * FROM tasks");
        return $stmt->fetchAll();
    }

    public function createTask($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (title, description, status, assignee_id, due_date) 
            VALUES (:title, :description, :status, :assignee_id, :due_date)
        ");
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? null, $data['description'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $data['status'] ?? 'todo', PDO::PARAM_STR);
        $stmt->bindValue(':assignee_id', isset($data['assignee_id']) ? (int)$data['assignee_id'] : null, isset($data['assignee_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':due_date', $data['due_date'] ?? null, $data['due_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        return $stmt->execute();
    }
}
?>