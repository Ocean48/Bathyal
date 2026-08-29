<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class TaskController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');
        $projectId = $request->get('project_id');
        $view = $request->getString('view', 'all');
        $statusId = $request->getInt('status_id');
        $priority = $request->getString('priority');

        $sql = "SELECT t.*, s.name AS status_name, s.color_hex AS status_color, s.type AS status_type,
                       p.name AS project_name, p.color_hex AS project_color,
                       u.full_name AS creator_name
                FROM tasks t
                JOIN statuses s ON t.status_id = s.id
                JOIN users u ON t.created_by = u.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE 1=1";

        $params = [];

        if ($workspaceId > 0) {
            $sql .= " AND t.workspace_id = :workspace_id";
            $params['workspace_id'] = $workspaceId;
        } else {
            // Only tasks from user's workspaces
            $sql .= " AND t.workspace_id IN (SELECT workspace_id FROM workspace_members WHERE user_id = :user_id)";
            $params['user_id'] = $userId;
        }

        if ($projectId !== null && $projectId !== '') {
            if ($projectId === 'null' || $projectId === '0') {
                $sql .= " AND t.project_id IS NULL";
            } else {
                $sql .= " AND t.project_id = :project_id";
                $params['project_id'] = (int)$projectId;
            }
        }

        if ($statusId > 0) {
            $sql .= " AND t.status_id = :status_id";
            $params['status_id'] = $statusId;
        }

        if (!empty($priority)) {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $priority;
        }

        // View filters for Simple and Focus modes
        if ($view === 'inbox') {
            $sql .= " AND t.project_id IS NULL AND s.type != 'completed'";
        } elseif ($view === 'today') {
            $sql .= " AND (DATE(t.due_date) = CURDATE() OR (t.due_date IS NULL AND DATE(t.created_at) = CURDATE())) AND s.type != 'completed'";
        } elseif ($view === 'upcoming') {
            $sql .= " AND DATE(t.due_date) > CURDATE() AND s.type != 'completed'";
        } elseif ($view === 'completed') {
            $sql .= " AND s.type = 'completed'";
        }

        $sql .= " ORDER BY t.position ASC, t.id DESC";

        $tasks = Database::fetchAll($sql, $params);

        // Attach assignees to each task
        if (!empty($tasks)) {
            $taskIds = array_column($tasks, 'id');
            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $assignees = Database::fetchAll(
                "SELECT ta.task_id, u.id AS user_id, u.full_name, u.email
                 FROM task_assignees ta
                 JOIN users u ON ta.user_id = u.id
                 WHERE ta.task_id IN ({$placeholders})",
                $taskIds
            );

            $assigneeMap = [];
            foreach ($assignees as $assignee) {
                $assigneeMap[$assignee['task_id']][] = $assignee;
            }

            foreach ($tasks as &$task) {
                $task['assignees'] = $assigneeMap[$task['id']] ?? [];
            }
        }

        $this->json($tasks);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();

        $rawTitle = $payload['title'] ?? ($payload['raw_input'] ?? '');
        if (empty(trim($rawTitle))) {
            $this->error('Task title is required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Simple Natural Language Processing (NLP) Parser for QuickAdd
        $parsed = $this->parseQuickInput($rawTitle);
        $title = $parsed['title'];
        $priority = $payload['priority'] ?? $parsed['priority'];
        $dueDate = $payload['due_date'] ?? $parsed['due_date'];

        $workspaceId = !empty($payload['workspace_id']) ? (int)$payload['workspace_id'] : null;
        if (!$workspaceId) {
            // Find or use user's personal workspace
            $personalWs = Database::fetchOne(
                "SELECT w.id FROM workspaces w
                 JOIN workspace_members wm ON w.id = wm.workspace_id
                 WHERE wm.user_id = :user_id AND w.is_personal = 1
                 LIMIT 1",
                ['user_id' => $userId]
            );
            $workspaceId = $personalWs ? (int)$personalWs['id'] : 1;
        }

        $projectId = !empty($payload['project_id']) ? (int)$payload['project_id'] : null;
        $statusId = !empty($payload['status_id']) ? (int)$payload['status_id'] : 1; // Default: To Do
        $description = $payload['description'] ?? null;
        $estimatedHours = !empty($payload['estimated_hours']) ? (float)$payload['estimated_hours'] : null;
        $startDate = $payload['start_date'] ?? null;

        // Position at bottom of list
        $maxPos = Database::fetchColumn(
            "SELECT COALESCE(MAX(position), 0) FROM tasks WHERE workspace_id = :ws_id",
            ['ws_id' => $workspaceId]
        );
        $position = ((int)$maxPos) + 1;

        $taskId = Database::insertGetId(
            "INSERT INTO tasks (workspace_id, project_id, parent_id, status_id, title, description, priority, start_date, due_date, estimated_hours, position, created_by, created_at)
             VALUES (:ws_id, :proj_id, NULL, :status_id, :title, :desc, :prio, :start_date, :due_date, :est, :pos, :created_by, NOW())",
            [
                'ws_id' => $workspaceId,
                'proj_id' => $projectId,
                'status_id' => $statusId,
                'title' => $title,
                'desc' => $description,
                'prio' => $priority,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'est' => $estimatedHours,
                'pos' => $position,
                'created_by' => $userId,
            ]
        );

        // Auto-assign creator
        Database::execute(
            "INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (:task_id, :user_id)",
            ['task_id' => $taskId, 'user_id' => $userId]
        );

        $task = $this->getTaskWithDetails((int)$taskId);
        $this->json($task, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $id = (int)$request->param('id');
        $task = $this->getTaskWithDetails($id);

        if (!$task) {
            $this->error('Task not found', Response::HTTP_NOT_FOUND);
        }

        $this->json($task);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $payload = $request->getJson();

        $existing = Database::fetchOne("SELECT * FROM tasks WHERE id = :id", ['id' => $id]);
        if (!$existing) {
            $this->error('Task not found', Response::HTTP_NOT_FOUND);
        }

        $fields = [];
        $params = ['id' => $id];

        $updatable = ['title', 'description', 'status_id', 'priority', 'start_date', 'due_date', 'estimated_hours', 'position', 'project_id'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $payload)) {
                $fields[] = "`{$field}` = :{$field}";
                $params[$field] = $payload[$field];
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE tasks SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
            Database::execute($sql, $params);
        }

        $task = $this->getTaskWithDetails($id);
        $this->json($task);
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $existing = Database::fetchOne("SELECT id FROM tasks WHERE id = :id", ['id' => $id]);
        if (!$existing) {
            $this->error('Task not found', Response::HTTP_NOT_FOUND);
        }

        Database::execute("DELETE FROM tasks WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Task deleted successfully', 'id' => $id]);
    }

    public function reorder(Request $request): void
    {
        $payload = $request->getJson();
        $items = $payload['items'] ?? []; // [{id: 1, position: 1, status_id: 2}, ...]

        if (!is_array($items)) {
            $this->error('Items array required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        Database::transaction(function () use ($items) {
            foreach ($items as $item) {
                if (isset($item['id'], $item['position'])) {
                    $sql = "UPDATE tasks SET position = :pos";
                    $params = ['pos' => (int)$item['position'], 'id' => (int)$item['id']];
                    if (isset($item['status_id'])) {
                        $sql .= ", status_id = :status_id";
                        $params['status_id'] = (int)$item['status_id'];
                    }
                    $sql .= " WHERE id = :id";
                    Database::execute($sql, $params);
                }
            }
        });

        $this->json(['message' => 'Tasks reordered successfully']);
    }

    public function batch(Request $request): void
    {
        $payload = $request->getJson();
        $taskIds = $payload['task_ids'] ?? [];
        $action = $payload['action'] ?? '';

        if (empty($taskIds) || !is_array($taskIds)) {
            $this->error('Task IDs array required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

        if ($action === 'set_status') {
            $statusId = (int)($payload['status_id'] ?? 1);
            $params = array_merge([$statusId], $taskIds);
            Database::execute("UPDATE tasks SET status_id = ? WHERE id IN ({$placeholders})", $params);
        } elseif ($action === 'set_priority') {
            $priority = $payload['priority'] ?? 'none';
            $params = array_merge([$priority], $taskIds);
            Database::execute("UPDATE tasks SET priority = ? WHERE id IN ({$placeholders})", $params);
        } elseif ($action === 'delete') {
            Database::execute("DELETE FROM tasks WHERE id IN ({$placeholders})", $taskIds);
        } else {
            $this->error('Invalid batch action', Response::HTTP_BAD_REQUEST);
        }

        $this->json(['message' => 'Batch operation completed successfully']);
    }

    private function getTaskWithDetails(int $id): ?array
    {
        $task = Database::fetchOne(
            "SELECT t.*, s.name AS status_name, s.color_hex AS status_color, s.type AS status_type,
                    p.name AS project_name, p.color_hex AS project_color,
                    u.full_name AS creator_name
             FROM tasks t
             JOIN statuses s ON t.status_id = s.id
             JOIN users u ON t.created_by = u.id
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.id = :id",
            ['id' => $id]
        );

        if (!$task) {
            return null;
        }

        $task['assignees'] = Database::fetchAll(
            "SELECT u.id, u.full_name, u.email
             FROM task_assignees ta
             JOIN users u ON ta.user_id = u.id
             WHERE ta.task_id = :id",
            ['id' => $id]
        );

        return $task;
    }

    private function parseQuickInput(string $input): array
    {
        $priority = 'none';
        $dueDate = null;

        // Parse Priority: !urgent, !high, !medium, !low
        if (preg_match('/!(urgent|high|medium|low)/i', $input, $matches)) {
            $priority = strtolower($matches[1]);
            $input = trim(str_replace($matches[0], '', $input));
        }

        // Parse Dates: @today, @tomorrow, @friday, etc.
        if (preg_match('/@(today|tomorrow|mon|tue|wed|thu|fri|sat|sun)/i', $input, $matches)) {
            $dateStr = strtolower($matches[1]);
            if ($dateStr === 'today') {
                $dueDate = date('Y-m-d 23:59:59');
            } elseif ($dateStr === 'tomorrow') {
                $dueDate = date('Y-m-d 23:59:59', strtotime('+1 day'));
            } else {
                $dueDate = date('Y-m-d 23:59:59', strtotime("next {$dateStr}"));
            }
            $input = trim(str_replace($matches[0], '', $input));
        }

        return [
            'title' => preg_replace('/\s+/', ' ', $input),
            'priority' => $priority,
            'due_date' => $dueDate,
        ];
    }
}
