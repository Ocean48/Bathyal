<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PermEngine;

class DocumentController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');
        $projectId = $request->get('project_id');

        $sql = "SELECT d.id, d.workspace_id, d.project_id, d.title, d.created_by, d.created_at, d.updated_at,
                       u.full_name AS author_name, p.name AS project_name
                FROM documents d
                JOIN users u ON d.created_by = u.id
                LEFT JOIN projects p ON d.project_id = p.id
                WHERE d.workspace_id IN (SELECT workspace_id FROM workspace_members WHERE user_id = :user_id)";
        $params = ['user_id' => $userId];

        if ($workspaceId > 0) {
            $sql .= " AND d.workspace_id = :ws_id";
            $params['ws_id'] = $workspaceId;
        }

        if ($projectId !== null && $projectId !== '') {
            $sql .= " AND d.project_id = :p_id";
            $params['p_id'] = (int)$projectId;
        }

        $sql .= " ORDER BY d.updated_at DESC";

        $docs = Database::fetchAll($sql, $params);
        $this->json($docs);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'workspace_id' => 'required',
            'title' => 'required|min:1',
        ]);

        $workspaceId = (int)$payload['workspace_id'];
        if (!PermEngine::canEditWorkspace($userId, $workspaceId)) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $title = trim($payload['title']);
        $projectId = !empty($payload['project_id']) ? (int)$payload['project_id'] : null;
        $content = isset($payload['content']) ? (is_string($payload['content']) ? $payload['content'] : json_encode($payload['content'])) : null;

        $docId = Database::insertGetId(
            "INSERT INTO documents (workspace_id, project_id, title, content, created_by, created_at, updated_at)
             VALUES (:ws_id, :proj_id, :title, :content, :c_by, NOW(), NOW())",
            [
                'ws_id' => $workspaceId,
                'proj_id' => $projectId,
                'title' => $title,
                'content' => $content,
                'c_by' => $userId,
            ]
        );

        $doc = Database::fetchOne("SELECT * FROM documents WHERE id = :id", ['id' => $docId]);
        $this->json($doc, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $doc = Database::fetchOne(
            "SELECT d.*, u.full_name AS author_name, p.name AS project_name
             FROM documents d
             JOIN users u ON d.created_by = u.id
             LEFT JOIN projects p ON d.project_id = p.id
             WHERE d.id = :id",
            ['id' => $id]
        );

        if (!$doc) {
            $this->error('Document not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$doc['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $this->json($doc);
    }

    public function update(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');
        $payload = $request->getJson();

        $doc = Database::fetchOne("SELECT * FROM documents WHERE id = :id", ['id' => $id]);
        if (!$doc) {
            $this->error('Document not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditWorkspace($userId, (int)$doc['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('title', $payload)) {
            $fields[] = "title = :title";
            $params['title'] = trim($payload['title']);
        }
        if (array_key_exists('content', $payload)) {
            $fields[] = "content = :content";
            $content = $payload['content'];
            $params['content'] = is_string($content) ? $content : json_encode($content);
        }
        if (array_key_exists('project_id', $payload)) {
            $fields[] = "project_id = :project_id";
            $params['project_id'] = !empty($payload['project_id']) ? (int)$payload['project_id'] : null;
        }

        if (!empty($fields)) {
            Database::execute("UPDATE documents SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id", $params);
        }

        $updated = Database::fetchOne("SELECT * FROM documents WHERE id = :id", ['id' => $id]);
        $this->json($updated);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $doc = Database::fetchOne("SELECT * FROM documents WHERE id = :id", ['id' => $id]);
        if (!$doc) {
            $this->error('Document not found', Response::HTTP_NOT_FOUND);
        }

        if ((int)$doc['created_by'] !== $userId && !PermEngine::canManageWorkspace($userId, (int)$doc['workspace_id'])) {
            $this->error('Permission denied to delete document', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM documents WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Document deleted successfully', 'id' => $id]);
    }
}
