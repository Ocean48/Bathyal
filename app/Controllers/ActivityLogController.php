<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PermEngine;

class ActivityLogController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');
        $taskId = $request->getInt('task_id');
        $limit = min(100, max(10, $request->getInt('limit', 50)));

        $sql = "SELECT al.*, u.full_name AS user_name, u.email AS user_email, t.title AS task_title
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                LEFT JOIN tasks t ON al.task_id = t.id
                WHERE al.workspace_id IN (SELECT workspace_id FROM workspace_members WHERE user_id = :u_id)";
        $params = ['u_id' => $userId];

        if ($workspaceId > 0) {
            $sql .= " AND al.workspace_id = :ws_id";
            $params['ws_id'] = $workspaceId;
        }

        if ($taskId > 0) {
            $sql .= " AND al.task_id = :t_id";
            $params['t_id'] = $taskId;
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT {$limit}";

        $logs = Database::fetchAll($sql, $params);
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $decoded = json_decode($log['details'], true);
                $log['details'] = is_array($decoded) ? $decoded : $log['details'];
            }
        }

        $this->json($logs);
    }
}
