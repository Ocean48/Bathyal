<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CustomFieldEngine;
use App\Services\PermEngine;

class CustomFieldController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');
        $projectId = $request->get('project_id');

        if ($workspaceId <= 0) {
            $this->error('workspace_id query parameter is required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!PermEngine::canViewWorkspace($userId, $workspaceId)) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $fields = CustomFieldEngine::getFieldsForContext(
            $workspaceId,
            ($projectId !== null && $projectId !== '') ? (int)$projectId : null
        );

        $this->json($fields);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'workspace_id' => 'required',
            'field_name' => 'required|min:1',
            'field_type' => 'required|in:text,number,select,multi_select,date,checkbox,user,url,formula',
        ]);

        $workspaceId = (int)$payload['workspace_id'];
        if (!PermEngine::canManageWorkspace($userId, $workspaceId)) {
            $this->error('Permission denied to create custom fields in this workspace', Response::HTTP_FORBIDDEN);
        }

        $fieldName = trim($payload['field_name']);
        $fieldType = $payload['field_type'];
        $projectId = !empty($payload['project_id']) ? (int)$payload['project_id'] : null;
        $options = !empty($payload['options']) ? json_encode($payload['options']) : null;

        $fieldId = Database::insertGetId(
            "INSERT INTO custom_fields (workspace_id, project_id, field_name, field_type, options)
             VALUES (:ws_id, :proj_id, :name, :type, :opts)",
            [
                'ws_id' => $workspaceId,
                'proj_id' => $projectId,
                'name' => $fieldName,
                'type' => $fieldType,
                'opts' => $options,
            ]
        );

        $field = Database::fetchOne("SELECT * FROM custom_fields WHERE id = :id", ['id' => $fieldId]);
        if (!empty($field['options'])) {
            $field['options'] = json_decode($field['options'], true);
        }

        $this->json($field, Response::HTTP_CREATED);
    }

    public function updateTaskValue(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = (int)$request->param('id');
        $payload = $request->getJson();

        $this->validate($payload, [
            'custom_field_id' => 'required',
        ]);

        if (!PermEngine::canEditTask($userId, $taskId)) {
            $this->error('Access denied to edit task custom field value', Response::HTTP_FORBIDDEN);
        }

        $customFieldId = (int)$payload['custom_field_id'];
        $value = $payload['value'] ?? null;

        try {
            CustomFieldEngine::setFieldValue($taskId, $customFieldId, $value);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pivoted = CustomFieldEngine::pivotFieldsForTasks([$taskId]);
        $this->json([
            'message' => 'Custom field value saved',
            'custom_fields' => $pivoted[$taskId] ?? [],
        ]);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $field = Database::fetchOne("SELECT * FROM custom_fields WHERE id = :id", ['id' => $id]);
        if (!$field) {
            $this->error('Custom field not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageWorkspace($userId, (int)$field['workspace_id'])) {
            $this->error('Permission denied to delete custom field', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM custom_fields WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Custom field deleted successfully', 'id' => $id]);
    }
}
