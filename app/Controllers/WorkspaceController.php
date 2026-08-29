<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class WorkspaceController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);

        $workspaces = Database::fetchAll(
            "SELECT w.*, wm.role,
                    (SELECT COUNT(*) FROM projects p WHERE p.workspace_id = w.id) AS project_count,
                    (SELECT COUNT(*) FROM tasks t WHERE t.workspace_id = w.id) AS task_count
             FROM workspaces w
             JOIN workspace_members wm ON w.id = wm.workspace_id
             WHERE wm.user_id = :user_id
             ORDER BY w.is_personal DESC, w.created_at ASC",
            ['user_id' => $userId]
        );

        $this->json($workspaces);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'name' => 'required|min:2',
        ]);

        $name = trim($payload['name']);
        $baseSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
        $slug = $baseSlug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $isPersonal = !empty($payload['is_personal']) ? 1 : 0;

        $workspaceId = Database::transaction(function () use ($name, $slug, $isPersonal, $userId) {
            $wsId = Database::insertGetId(
                "INSERT INTO workspaces (name, slug, is_personal, created_at)
                 VALUES (:name, :slug, :is_personal, NOW())",
                [
                    'name' => $name,
                    'slug' => $slug,
                    'is_personal' => $isPersonal,
                ]
            );

            Database::execute(
                "INSERT INTO workspace_members (workspace_id, user_id, role)
                 VALUES (:ws_id, :user_id, 'owner')",
                [
                    'ws_id' => $wsId,
                    'user_id' => $userId,
                ]
            );

            return $wsId;
        });

        $workspace = Database::fetchOne("SELECT * FROM workspaces WHERE id = :id", ['id' => $workspaceId]);
        $this->json($workspace, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = $request->param('id');

        $workspace = Database::fetchOne(
            "SELECT w.*, wm.role
             FROM workspaces w
             JOIN workspace_members wm ON w.id = wm.workspace_id
             WHERE w.id = :id AND wm.user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );

        if (!$workspace) {
            $this->error('Workspace not found or access denied', Response::HTTP_NOT_FOUND);
        }

        $this->json($workspace);
    }

    public function tree(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = $request->param('id');

        $workspace = Database::fetchOne(
            "SELECT w.*, wm.role
             FROM workspaces w
             JOIN workspace_members wm ON w.id = wm.workspace_id
             WHERE w.id = :id AND wm.user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );

        if (!$workspace) {
            $this->error('Workspace not found or access denied', Response::HTTP_NOT_FOUND);
        }

        // Get Folders
        $folders = Database::fetchAll(
            "SELECT * FROM folders WHERE workspace_id = :ws_id ORDER BY position ASC, id ASC",
            ['ws_id' => $id]
        );

        // Get Projects with task counts
        $projects = Database::fetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status_id = 3) AS completed_task_count
             FROM projects p
             WHERE p.workspace_id = :ws_id
             ORDER BY p.name ASC",
            ['ws_id' => $id]
        );

        // Group projects by folder
        $foldersMap = [];
        foreach ($folders as $folder) {
            $folder['projects'] = [];
            $foldersMap[$folder['id']] = $folder;
        }

        $standaloneProjects = [];
        foreach ($projects as $project) {
            if ($project['folder_id'] && isset($foldersMap[$project['folder_id']])) {
                $foldersMap[$project['folder_id']]['projects'][] = $project;
            } else {
                $standaloneProjects[] = $project;
            }
        }

        $tree = [
            'workspace' => $workspace,
            'folders' => array_values($foldersMap),
            'standalone_projects' => $standaloneProjects,
        ];

        $this->json($tree);
    }
}
