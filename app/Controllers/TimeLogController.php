<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PermEngine;

class TimeLogController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = $request->getInt('task_id');
        $projectId = $request->getInt('project_id');

        $sql = "SELECT tl.*, t.title AS task_title, u.full_name AS user_name
                FROM time_logs tl
                JOIN tasks t ON tl.task_id = t.id
                JOIN users u ON tl.user_id = u.id
                WHERE t.workspace_id IN (SELECT workspace_id FROM workspace_members WHERE user_id = :u_id)";
        $params = ['u_id' => $userId];

        if ($taskId > 0) {
            $sql .= " AND tl.task_id = :t_id";
            $params['t_id'] = $taskId;
        }

        if ($projectId > 0) {
            $sql .= " AND t.project_id = :p_id";
            $params['p_id'] = $projectId;
        }

        $sql .= " ORDER BY tl.start_time DESC LIMIT 100";

        $logs = Database::fetchAll($sql, $params);
        $this->json($logs);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'task_id' => 'required',
        ]);

        $taskId = (int)$payload['task_id'];
        if (!PermEngine::canEditTask($userId, $taskId)) {
            $this->error('Permission denied to log time on this task', Response::HTTP_FORBIDDEN);
        }

        $startTime = !empty($payload['start_time']) ? date('Y-m-d H:i:s', strtotime($payload['start_time'])) : date('Y-m-d H:i:s');
        $endTime = !empty($payload['end_time']) ? date('Y-m-d H:i:s', strtotime($payload['end_time'])) : null;
        $durationSeconds = isset($payload['duration_seconds']) ? (int)$payload['duration_seconds'] : null;

        if ($endTime && !$durationSeconds) {
            $durationSeconds = max(0, strtotime($endTime) - strtotime($startTime));
        }

        $billable = !empty($payload['billable']) ? 1 : 0;
        $notes = $payload['notes'] ?? null;

        $logId = Database::insertGetId(
            "INSERT INTO time_logs (task_id, user_id, start_time, end_time, duration_seconds, billable, notes)
             VALUES (:t_id, :u_id, :s_time, :e_time, :dur, :bill, :notes)",
            [
                't_id' => $taskId,
                'u_id' => $userId,
                's_time' => $startTime,
                'e_time' => $endTime,
                'dur' => $durationSeconds,
                'bill' => $billable,
                'notes' => $notes,
            ]
        );

        $log = Database::fetchOne("SELECT * FROM time_logs WHERE id = :id", ['id' => $logId]);
        $this->json($log, Response::HTTP_CREATED);
    }

    public function stop(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $log = Database::fetchOne("SELECT * FROM time_logs WHERE id = :id AND user_id = :u_id", ['id' => $id, 'u_id' => $userId]);
        if (!$log) {
            $this->error('Active time log not found', Response::HTTP_NOT_FOUND);
        }

        $endTime = date('Y-m-d H:i:s');
        $duration = max(0, strtotime($endTime) - strtotime($log['start_time']));

        Database::execute(
            "UPDATE time_logs SET end_time = :e_time, duration_seconds = :dur WHERE id = :id",
            [
                'e_time' => $endTime,
                'dur' => $duration,
                'id' => $id,
            ]
        );

        $updated = Database::fetchOne("SELECT * FROM time_logs WHERE id = :id", ['id' => $id]);
        $this->json($updated);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $log = Database::fetchOne("SELECT * FROM time_logs WHERE id = :id", ['id' => $id]);
        if (!$log) {
            $this->error('Time log not found', Response::HTTP_NOT_FOUND);
        }

        if ((int)$log['user_id'] !== $userId && !PermEngine::canEditTask($userId, (int)$log['task_id'])) {
            $this->error('Permission denied to delete time log', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM time_logs WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Time log deleted successfully', 'id' => $id]);
    }
}
