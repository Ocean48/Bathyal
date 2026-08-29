<?php

namespace App\Services;

use App\Core\Database;
use App\Services\FieldHandlers\CheckboxFieldHandler;
use App\Services\FieldHandlers\DateFieldHandler;
use App\Services\FieldHandlers\FieldHandlerInterface;
use App\Services\FieldHandlers\FormulaFieldHandler;
use App\Services\FieldHandlers\MultiSelectFieldHandler;
use App\Services\FieldHandlers\NumberFieldHandler;
use App\Services\FieldHandlers\SelectFieldHandler;
use App\Services\FieldHandlers\TextFieldHandler;
use App\Services\FieldHandlers\UrlFieldHandler;
use App\Services\FieldHandlers\UserFieldHandler;

class CustomFieldEngine
{
    private static array $handlers = [];

    public static function getHandler(string $fieldType): FieldHandlerInterface
    {
        if (empty(self::$handlers)) {
            self::$handlers = [
                'text' => new TextFieldHandler(),
                'number' => new NumberFieldHandler(),
                'select' => new SelectFieldHandler(),
                'multi_select' => new MultiSelectFieldHandler(),
                'date' => new DateFieldHandler(),
                'checkbox' => new CheckboxFieldHandler(),
                'user' => new UserFieldHandler(),
                'url' => new UrlFieldHandler(),
                'formula' => new FormulaFieldHandler(),
            ];
        }

        return self::$handlers[$fieldType] ?? self::$handlers['text'];
    }

    /**
     * Get all custom field definitions for a workspace or project
     */
    public static function getFieldsForContext(int $workspaceId, ?int $projectId = null): array
    {
        $sql = "SELECT * FROM custom_fields WHERE workspace_id = :ws_id";
        $params = ['ws_id' => $workspaceId];

        if ($projectId !== null) {
            $sql .= " AND (project_id IS NULL OR project_id = :proj_id)";
            $params['proj_id'] = $projectId;
        } else {
            $sql .= " AND project_id IS NULL";
        }

        $fields = Database::fetchAll($sql, $params);
        foreach ($fields as &$field) {
            if (!empty($field['options'])) {
                $decoded = json_decode($field['options'], true);
                $field['options'] = is_array($decoded) ? $decoded : [];
            } else {
                $field['options'] = [];
            }
        }

        return $fields;
    }

    /**
     * Set a custom field value for a task
     */
    public static function setFieldValue(int $taskId, int $customFieldId, mixed $rawValue): bool
    {
        $field = Database::fetchOne("SELECT * FROM custom_fields WHERE id = :id", ['id' => $customFieldId]);
        if (!$field) {
            return false;
        }

        $handler = self::getHandler($field['field_type']);
        $options = !empty($field['options']) ? json_decode($field['options'], true) : null;

        if (!$handler->validate($rawValue, $options)) {
            throw new \InvalidArgumentException("Invalid value for custom field [{$field['field_name']}]");
        }

        $col = $handler->getStorageColumn();
        $serialized = $handler->serialize($rawValue);

        // Delete previous value
        Database::execute(
            "DELETE FROM custom_field_values WHERE task_id = :t_id AND custom_field_id = :cf_id",
            ['t_id' => $taskId, 'cf_id' => $customFieldId]
        );

        if ($serialized !== null) {
            $sql = "INSERT INTO custom_field_values (task_id, custom_field_id, `{$col}`) VALUES (:t_id, :cf_id, :val)";
            Database::execute($sql, [
                't_id' => $taskId,
                'cf_id' => $customFieldId,
                'val' => $serialized,
            ]);
        }

        return true;
    }

    /**
     * Single-Query EAV Bulk Pivoting for a list of task IDs
     */
    public static function pivotFieldsForTasks(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $rows = Database::fetchAll(
            "SELECT cfv.task_id, cfv.custom_field_id, cfv.value_text, cfv.value_number, cfv.value_date, cfv.value_json,
                    cf.field_name, cf.field_type, cf.options
             FROM custom_field_values cfv
             JOIN custom_fields cf ON cfv.custom_field_id = cf.id
             WHERE cfv.task_id IN ({$placeholders})",
            $taskIds
        );

        $map = [];
        foreach ($rows as $row) {
            $taskId = (int)$row['task_id'];
            $handler = self::getHandler($row['field_type']);
            $col = $handler->getStorageColumn();
            $storedVal = $row[$col] ?? null;
            $deserialized = $handler->deserialize($storedVal);

            $map[$taskId][] = [
                'field_id' => (int)$row['custom_field_id'],
                'field_name' => $row['field_name'],
                'field_type' => $row['field_type'],
                'value' => $deserialized,
            ];
        }

        return $map;
    }
}
