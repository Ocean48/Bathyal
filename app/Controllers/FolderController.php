<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PermEngine;

class FolderController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');

        if ($workspaceId <= 0) {
            $this->error('workspace_id query parameter is required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!PermEngine::canViewWorkspace($userId, $workspaceId)) {
            $this->error('Access denied to workspace', Response::HTTP_FORBIDDEN);
        }

        $folders = Database::fetchAll(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM projects p WHERE p.folder_id = f.id) AS project_count
             FROM folders f
             WHERE f.workspace_id = :ws_id
             ORDER BY f.position ASC, f.id ASC",
            ['ws_id' => $workspaceId]
        );

        $this->json($folders);
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
            $this->error('Permission denied to create folder in this workspace', Response::HTTP_FORBIDDEN);
        }

        $name = trim($payload['name']);
        $position = (int)($payload['position'] ?? 0);

        if ($position === 0) {
            $maxPos = Database::fetchColumn(
                "SELECT COALESCE(MAX(position), 0) FROM folders WHERE workspace_id = :ws_id",
                ['ws_id' => $workspaceId]
            );
            $position = ((int)$maxPos) + 1;
        }

        $folderId = Database::insertGetId(
            "INSERT INTO folders (workspace_id, name, position) VALUES (:ws_id, :name, :pos)",
            [
                'ws_id' => $workspaceId,
                'name' => $name,
                'pos' => $position,
            ]
        );

        $folder = Database::fetchOne("SELECT * FROM folders WHERE id = :id", ['id' => $folderId]);
        $this->json($folder, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $folder = Database::fetchOne("SELECT * FROM folders WHERE id = :id", ['id' => $id]);
        if (!$folder) {
            $this->error('Folder not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$folder['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $folder['projects'] = Database::fetchAll(
            "SELECT * FROM projects WHERE folder_id = :f_id ORDER BY name ASC",
            ['f_id' => $id]
        );

        $this->json($folder);
    }

    public function update(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');
        $payload = $request->getJson();

        $folder = Database::fetchOne("SELECT * FROM folders WHERE id = :id", ['id' => $id]);
        if (!$folder) {
            $this->error('Folder not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditWorkspace($userId, (int)$folder['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('name', $payload)) {
            $fields[] = "name = :name";
            $params['name'] = trim($payload['name']);
        }
        if (array_key_exists('position', $payload)) {
            $fields[] = "position = :pos";
            $params['pos'] = (int)$payload['position'];
        }

        if (!empty($fields)) {
            Database::execute("UPDATE folders SET " . implode(', ', $fields) . " WHERE id = :id", $params);
        }

        $updated = Database::fetchOne("SELECT * FROM folders WHERE id = :id", ['id' => $id]);
        $this->json($updated);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $folder = Database::fetchOne("SELECT * FROM folders WHERE id = :id", ['id' => $id]);
        if (!$folder) {
            $this->error('Folder not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageWorkspace($userId, (int)$folder['workspace_id'])) {
            $this->error('Permission denied to delete folder', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM folders WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Folder deleted successfully', 'id' => $id]);
    }
}
