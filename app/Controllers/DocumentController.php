<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\FileUploadService;
use App\Services\PermEngine;
use Exception;

class DocumentController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');
        $projectId = $request->get('project_id');
        $taskId = $request->get('task_id');
        $docType = $request->getString('doc_type');
        $search = $request->getString('search');

        $sql = "SELECT d.id, d.workspace_id, d.project_id, d.task_id, d.title, d.doc_type,
                       d.file_path, d.file_name, d.original_name, d.file_size, d.mime_type, d.file_extension,
                       d.created_by, d.created_at, d.updated_at,
                       u.full_name AS author_name, p.name AS project_name,
                       t.title AS task_title, t.status_id AS task_status_id
                FROM documents d
                JOIN users u ON d.created_by = u.id
                LEFT JOIN projects p ON d.project_id = p.id
                LEFT JOIN tasks t ON d.task_id = t.id
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

        if ($taskId !== null && $taskId !== '') {
            $sql .= " AND d.task_id = :task_id";
            $params['task_id'] = (int)$taskId;
        }

        if (!empty($docType)) {
            $sql .= " AND d.doc_type = :doc_type";
            $params['doc_type'] = $docType;
        }

        if (!empty($search)) {
            $sql .= " AND (d.title LIKE :search OR d.original_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
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
        $taskId = !empty($payload['task_id']) ? (int)$payload['task_id'] : null;
        $docType = !empty($payload['doc_type']) ? $payload['doc_type'] : 'rich_text';
        $content = isset($payload['content']) ? (is_string($payload['content']) ? $payload['content'] : json_encode($payload['content'])) : null;

        $docId = Database::insertGetId(
            "INSERT INTO documents (workspace_id, project_id, task_id, title, content, doc_type, created_by, created_at, updated_at)
             VALUES (:ws_id, :proj_id, :task_id, :title, :content, :doc_type, :c_by, NOW(), NOW())",
            [
                'ws_id' => $workspaceId,
                'proj_id' => $projectId,
                'task_id' => $taskId,
                'title' => $title,
                'content' => $content,
                'doc_type' => $docType,
                'c_by' => $userId,
            ]
        );

        $doc = Database::fetchOne(
            "SELECT d.*, u.full_name AS author_name, p.name AS project_name, t.title AS task_title
             FROM documents d
             JOIN users u ON d.created_by = u.id
             LEFT JOIN projects p ON d.project_id = p.id
             LEFT JOIN tasks t ON d.task_id = t.id
             WHERE d.id = :id",
            ['id' => $docId]
        );
        $this->json($doc, Response::HTTP_CREATED);
    }

    public function upload(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = $request->getInt('task_id');
        $workspaceId = $request->getInt('workspace_id');
        $projectId = $request->getInt('project_id');

        // If taskId is provided, query task to infer workspace_id and project_id if not explicitly provided
        if ($taskId > 0) {
            $task = Database::fetchOne("SELECT id, workspace_id, project_id FROM tasks WHERE id = :id", ['id' => $taskId]);
            if (!$task) {
                $this->error('Target task not found', Response::HTTP_NOT_FOUND);
            }
            if ($workspaceId <= 0) {
                $workspaceId = (int)$task['workspace_id'];
            }
            if ($projectId <= 0 && !empty($task['project_id'])) {
                $projectId = (int)$task['project_id'];
            }
        }

        if ($workspaceId <= 0) {
            // Check default user workspace
            $membership = Database::fetchOne("SELECT workspace_id FROM workspace_members WHERE user_id = :uid LIMIT 1", ['uid' => $userId]);
            if ($membership) {
                $workspaceId = (int)$membership['workspace_id'];
            } else {
                $this->error('A valid workspace_id is required for uploading documents.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!PermEngine::canEditWorkspace($userId, $workspaceId)) {
            $this->error('Permission denied to upload in this workspace', Response::HTTP_FORBIDDEN);
        }

        // Collect uploaded files from request
        $uploadedFiles = [];
        if ($request->hasFile('file')) {
            $uploadedFiles = [$request->file('file')];
        } elseif ($request->hasFile('files')) {
            $uploadedFiles = $request->files('files');
        } else {
            $allFiles = $request->files();
            foreach ($allFiles as $fileEntry) {
                if (isset($fileEntry['name'])) {
                    $uploadedFiles[] = $fileEntry;
                } elseif (is_array($fileEntry)) {
                    foreach ($fileEntry as $subEntry) {
                        if (isset($subEntry['name'])) {
                            $uploadedFiles[] = $subEntry;
                        }
                    }
                }
            }
        }

        if (empty($uploadedFiles)) {
            $this->error('No file was uploaded.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $savedDocs = [];
        foreach ($uploadedFiles as $file) {
            try {
                $fileMeta = FileUploadService::store($file, $taskId > 0 ? $taskId : null);

                $title = $request->getString('title');
                if (empty($title)) {
                    $title = $fileMeta['original_name'];
                }

                $docId = Database::insertGetId(
                    "INSERT INTO documents (workspace_id, project_id, task_id, title, content, doc_type,
                                            file_path, file_name, original_name, file_size, mime_type, file_extension,
                                            created_by, created_at, updated_at)
                     VALUES (:ws_id, :proj_id, :task_id, :title, NULL, 'file',
                             :file_path, :file_name, :original_name, :file_size, :mime_type, :file_extension,
                             :c_by, NOW(), NOW())",
                    [
                        'ws_id' => $workspaceId,
                        'proj_id' => $projectId > 0 ? $projectId : null,
                        'task_id' => $taskId > 0 ? $taskId : null,
                        'title' => $title,
                        'file_path' => $fileMeta['file_path'],
                        'file_name' => $fileMeta['file_name'],
                        'original_name' => $fileMeta['original_name'],
                        'file_size' => $fileMeta['file_size'],
                        'mime_type' => $fileMeta['mime_type'],
                        'file_extension' => $fileMeta['file_extension'],
                        'c_by' => $userId,
                    ]
                );

                $savedDocs[] = Database::fetchOne(
                    "SELECT d.*, u.full_name AS author_name, p.name AS project_name, t.title AS task_title
                     FROM documents d
                     JOIN users u ON d.created_by = u.id
                     LEFT JOIN projects p ON d.project_id = p.id
                     LEFT JOIN tasks t ON d.task_id = t.id
                     WHERE d.id = :id",
                    ['id' => $docId]
                );
            } catch (Exception $e) {
                $this->error('File upload failed: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        $this->json(count($savedDocs) === 1 ? $savedDocs[0] : $savedDocs, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $doc = Database::fetchOne(
            "SELECT d.*, u.full_name AS author_name, p.name AS project_name, t.title AS task_title
             FROM documents d
             JOIN users u ON d.created_by = u.id
             LEFT JOIN projects p ON d.project_id = p.id
             LEFT JOIN tasks t ON d.task_id = t.id
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
        if (array_key_exists('task_id', $payload)) {
            $fields[] = "task_id = :task_id";
            $params['task_id'] = !empty($payload['task_id']) ? (int)$payload['task_id'] : null;
        }

        if (!empty($fields)) {
            Database::execute("UPDATE documents SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id", $params);
        }

        $updated = Database::fetchOne(
            "SELECT d.*, u.full_name AS author_name, p.name AS project_name, t.title AS task_title
             FROM documents d
             JOIN users u ON d.created_by = u.id
             LEFT JOIN projects p ON d.project_id = p.id
             LEFT JOIN tasks t ON d.task_id = t.id
             WHERE d.id = :id",
            ['id' => $id]
        );
        $this->json($updated);
    }

    public function taskDocuments(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = (int)$request->param('id');

        $task = Database::fetchOne("SELECT id, workspace_id, title FROM tasks WHERE id = :id", ['id' => $taskId]);
        if (!$task) {
            $this->error('Task not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$task['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $docs = Database::fetchAll(
            "SELECT d.*, u.full_name AS author_name, p.name AS project_name, t.title AS task_title
             FROM documents d
             JOIN users u ON d.created_by = u.id
             LEFT JOIN projects p ON d.project_id = p.id
             LEFT JOIN tasks t ON d.task_id = t.id
             WHERE d.task_id = :task_id
             ORDER BY d.created_at DESC",
            ['task_id' => $taskId]
        );

        $this->json($docs);
    }

    public function attachToTask(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = (int)$request->param('id');
        $payload = $request->getJson();

        $task = Database::fetchOne("SELECT id, workspace_id, project_id FROM tasks WHERE id = :id", ['id' => $taskId]);
        if (!$task) {
            $this->error('Task not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditWorkspace($userId, (int)$task['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $docId = !empty($payload['document_id']) ? (int)$payload['document_id'] : 0;
        $docIds = !empty($payload['document_ids']) && is_array($payload['document_ids']) ? $payload['document_ids'] : [];

        if ($docId > 0) {
            $docIds[] = $docId;
        }

        if (empty($docIds)) {
            $this->error('No document_id provided to attach.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($docIds as $dId) {
            Database::execute(
                "UPDATE documents SET task_id = :task_id, updated_at = NOW() WHERE id = :doc_id AND workspace_id = :ws_id",
                [
                    'task_id' => $taskId,
                    'doc_id' => (int)$dId,
                    'ws_id' => (int)$task['workspace_id'],
                ]
            );
        }

        $this->json(['message' => 'Documents successfully attached to task', 'task_id' => $taskId]);
    }

    public function detachFromTask(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = (int)$request->param('id');
        $docId = (int)$request->param('docId');

        $doc = Database::fetchOne("SELECT * FROM documents WHERE id = :id AND task_id = :task_id", [
            'id' => $docId,
            'task_id' => $taskId
        ]);

        if (!$doc) {
            $this->error('Document attachment not found for this task', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditWorkspace($userId, (int)$doc['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        Database::execute("UPDATE documents SET task_id = NULL, updated_at = NOW() WHERE id = :id", ['id' => $docId]);
        $this->json(['message' => 'Document detached from task', 'task_id' => $taskId, 'document_id' => $docId]);
    }

    public function download(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $doc = Database::fetchOne("SELECT * FROM documents WHERE id = :id", ['id' => $id]);
        if (!$doc || empty($doc['file_path'])) {
            $this->error('File not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$doc['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $publicPath = dirname(__DIR__, 2) . '/public';
        $fullPath = $publicPath . '/' . ltrim($doc['file_path'], '/');

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            $this->error('File attachment is missing from storage', Response::HTTP_NOT_FOUND);
        }

        $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
        $filename = $doc['original_name'] ?: ($doc['file_name'] ?: 'download');

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
        exit;
    }

    public function preview(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $doc = Database::fetchOne("SELECT * FROM documents WHERE id = :id", ['id' => $id]);
        if (!$doc || empty($doc['file_path'])) {
            $this->error('File not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$doc['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $publicPath = dirname(__DIR__, 2) . '/public';
        $fullPath = $publicPath . '/' . ltrim($doc['file_path'], '/');

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            $this->error('File attachment is missing from storage', Response::HTTP_NOT_FOUND);
        }

        $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
        $filename = $doc['original_name'] ?: ($doc['file_name'] ?: 'preview');

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: public, max-age=86400');
        readfile($fullPath);
        exit;
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

        // Clean up physical file if it exists
        if (!empty($doc['file_path'])) {
            FileUploadService::delete($doc['file_path']);
        }

        Database::execute("DELETE FROM documents WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Document deleted successfully', 'id' => $id]);
    }
}

