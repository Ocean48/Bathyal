<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PermEngine;

class ProjectController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');
        $folderId = $request->get('folder_id');

        $sql = "SELECT p.*, f.name AS folder_name,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status_id = 3) AS completed_task_count
                FROM projects p
                LEFT JOIN folders f ON p.folder_id = f.id
                WHERE p.workspace_id IN (SELECT workspace_id FROM workspace_members WHERE user_id = :user_id)";
        $params = ['user_id' => $userId];

        if ($workspaceId > 0) {
            $sql .= " AND p.workspace_id = :ws_id";
            $params['ws_id'] = $workspaceId;
        }

        if ($folderId !== null && $folderId !== '') {
            if ($folderId === 'null' || $folderId === '0') {
                $sql .= " AND p.folder_id IS NULL";
            } else {
                $sql .= " AND p.folder_id = :folder_id";
                $params['folder_id'] = (int)$folderId;
            }
        }

        $sql .= " ORDER BY p.name ASC";

        $projects = Database::fetchAll($sql, $params);
        $this->json($projects);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'workspace_id' => 'required',
            'name' => 'required|min:1',
        ]);

        $workspaceId = (int)$payload['workspace_id'];
        if (!PermEngine::canEditWorkspace($userId, $workspaceId)) {
            $this->error('Permission denied to create project in this workspace', Response::HTTP_FORBIDDEN);
        }

        $name = trim($payload['name']);
        $description = $payload['description'] ?? null;
        $folderId = !empty($payload['folder_id']) ? (int)$payload['folder_id'] : null;
        $icon = $payload['icon'] ?? 'list';
        $colorHex = $payload['color_hex'] ?? '#4A90E2';
        $isTemplate = !empty($payload['is_template']) ? 1 : 0;

        $projectId = Database::insertGetId(
            "INSERT INTO projects (workspace_id, folder_id, name, description, icon, color_hex, is_template, created_at)
             VALUES (:ws_id, :f_id, :name, :desc, :icon, :color, :tmpl, NOW())",
            [
                'ws_id' => $workspaceId,
                'f_id' => $folderId,
                'name' => $name,
                'desc' => $description,
                'icon' => $icon,
                'color' => $colorHex,
                'tmpl' => $isTemplate,
            ]
        );

        $project = Database::fetchOne("SELECT * FROM projects WHERE id = :id", ['id' => $projectId]);
        $this->json($project, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $project = Database::fetchOne(
            "SELECT p.*, f.name AS folder_name
             FROM projects p
             LEFT JOIN folders f ON p.folder_id = f.id
             WHERE p.id = :id",
            ['id' => $id]
        );

        if (!$project) {
            $this->error('Project not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$project['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        // Project Statuses
        $project['statuses'] = Database::fetchAll(
            "SELECT * FROM statuses WHERE project_id = :p_id OR project_id IS NULL ORDER BY position ASC",
            ['p_id' => $id]
        );

        $this->json($project);
    }

    public function update(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');
        $payload = $request->getJson();

        $project = Database::fetchOne("SELECT * FROM projects WHERE id = :id", ['id' => $id]);
        if (!$project) {
            $this->error('Project not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditWorkspace($userId, (int)$project['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $fields = [];
        $params = ['id' => $id];

        $updatable = ['name', 'description', 'folder_id', 'icon', 'color_hex', 'is_template'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $payload)) {
                $fields[] = "`{$field}` = :{$field}";
                $params[$field] = $payload[$field];
            }
        }

        if (!empty($fields)) {
            Database::execute("UPDATE projects SET " . implode(', ', $fields) . " WHERE id = :id", $params);
        }

        $updated = Database::fetchOne("SELECT * FROM projects WHERE id = :id", ['id' => $id]);
        $this->json($updated);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $project = Database::fetchOne("SELECT * FROM projects WHERE id = :id", ['id' => $id]);
        if (!$project) {
            $this->error('Project not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageWorkspace($userId, (int)$project['workspace_id'])) {
            $this->error('Permission denied to delete project', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM projects WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Project deleted successfully', 'id' => $id]);
    }

    public function duplicate(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $srcProject = Database::fetchOne("SELECT * FROM projects WHERE id = :id", ['id' => $id]);
        if (!$srcProject) {
            $this->error('Project not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditWorkspace($userId, (int)$srcProject['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $newProjectId = Database::transaction(function () use ($srcProject, $id, $userId) {
            // 1. Duplicate Project
            $newProjId = Database::insertGetId(
                "INSERT INTO projects (workspace_id, folder_id, name, description, icon, color_hex, is_template, created_at)
                 VALUES (:ws_id, :f_id, :name, :desc, :icon, :color, 0, NOW())",
                [
                    'ws_id' => $srcProject['workspace_id'],
                    'f_id' => $srcProject['folder_id'],
                    'name' => $srcProject['name'] . ' (Copy)',
                    'desc' => $srcProject['description'],
                    'icon' => $srcProject['icon'],
                    'color' => $srcProject['color_hex'],
                ]
            );

            // 2. Duplicate Tasks
            $tasks = Database::fetchAll("SELECT * FROM tasks WHERE project_id = :p_id ORDER BY id ASC", ['p_id' => $id]);
            $idMap = [];

            foreach ($tasks as $task) {
                $newTaskId = Database::insertGetId(
                    "INSERT INTO tasks (workspace_id, project_id, parent_id, status_id, title, description, priority, start_date, due_date, estimated_hours, position, created_by, created_at)
                     VALUES (:ws_id, :proj_id, NULL, :status_id, :title, :desc, :prio, :start_d, :due_d, :est, :pos, :c_by, NOW())",
                    [
                        'ws_id' => $task['workspace_id'],
                        'proj_id' => $newProjId,
                        'status_id' => $task['status_id'],
                        'title' => $task['title'],
                        'desc' => $task['description'],
                        'prio' => $task['priority'],
                        'start_d' => $task['start_date'],
                        'due_d' => $task['due_date'],
                        'est' => $task['estimated_hours'],
                        'pos' => $task['position'],
                        'c_by' => $userId,
                    ]
                );
                $idMap[$task['id']] = $newTaskId;
            }

            // Fix parent_id hierarchy for cloned tasks
            foreach ($tasks as $task) {
                if ($task['parent_id'] && isset($idMap[$task['parent_id']], $idMap[$task['id']])) {
                    Database::execute(
                        "UPDATE tasks SET parent_id = :parent_id WHERE id = :id",
                        ['parent_id' => $idMap[$task['parent_id']], 'id' => $idMap[$task['id']]]
                    );
                }
            }

            return $newProjId;
        });

        $newProject = Database::fetchOne("SELECT * FROM projects WHERE id = :id", ['id' => $newProjectId]);
        $this->json($newProject, Response::HTTP_CREATED);
    }
}
