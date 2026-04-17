<?php
// /includes/db_query.php

class DBQueries {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
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