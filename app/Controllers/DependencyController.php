<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AutoScheduler;
use App\Services\PermEngine;

class DependencyController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = $request->getInt('task_id');
        $projectId = $request->getInt('project_id');

        if ($taskId > 0) {
            $deps = Database::fetchAll(
                "SELECT td.*, bt.title AS blocking_title, dt.title AS dependent_title
                 FROM task_dependencies td
                 JOIN tasks bt ON td.blocking_task_id = bt.id
                 JOIN tasks dt ON td.dependent_task_id = dt.id
                 WHERE td.blocking_task_id = :t_id OR td.dependent_task_id = :t_id",
                ['t_id' => $taskId]
            );
        } elseif ($projectId > 0) {
            $deps = Database::fetchAll(
                "SELECT td.*, bt.title AS blocking_title, dt.title AS dependent_title
                 FROM task_dependencies td
                 JOIN tasks bt ON td.blocking_task_id = bt.id
                 JOIN tasks dt ON td.dependent_task_id = dt.id
                 WHERE bt.project_id = :p_id OR dt.project_id = :p_id",
                ['p_id' => $projectId]
            );
        } else {
            $deps = Database::fetchAll(
                "SELECT td.*, bt.title AS blocking_title, dt.title AS dependent_title
                 FROM task_dependencies td
                 JOIN tasks bt ON td.blocking_task_id = bt.id
                 JOIN tasks dt ON td.dependent_task_id = dt.id
                 LIMIT 200"
            );
        }

        $this->json($deps);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'blocking_task_id' => 'required',
            'dependent_task_id' => 'required',
        ]);

        $blockingId = (int)$payload['blocking_task_id'];
        $dependentId = (int)$payload['dependent_task_id'];
        $depType = $payload['dependency_type'] ?? 'finish_to_start';

        $validTypes = ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'];
        if (!in_array($depType, $validTypes, true)) {
            $this->error('Invalid dependency type', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!PermEngine::canEditTask($userId, $blockingId) || !PermEngine::canEditTask($userId, $dependentId)) {
            $this->error('Permission denied to link tasks', Response::HTTP_FORBIDDEN);
        }

        // Cycle Detection
        if (AutoScheduler::wouldCreateCycle($blockingId, $dependentId)) {
            $this->error('Cannot create dependency: circular dependency loop detected', Response::HTTP_BAD_REQUEST);
        }

        $depId = Database::insertGetId(
            "INSERT INTO task_dependencies (blocking_task_id, dependent_task_id, dependency_type)
             VALUES (:b_id, :d_id, :type)",
            [
                'b_id' => $blockingId,
                'd_id' => $dependentId,
                'type' => $depType,
            ]
        );

        // Run auto-scheduler to adjust downstream task dates
        $rescheduled = AutoScheduler::rescheduleFromTask($blockingId);

        $dep = Database::fetchOne("SELECT * FROM task_dependencies WHERE id = :id", ['id' => $depId]);
        $this->json([
            'dependency' => $dep,
            'rescheduled_tasks' => $rescheduled,
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $dep = Database::fetchOne("SELECT * FROM task_dependencies WHERE id = :id", ['id' => $id]);
        if (!$dep) {
            $this->error('Dependency not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canEditTask($userId, (int)$dep['blocking_task_id'])) {
            $this->error('Permission denied', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM task_dependencies WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'Dependency removed successfully', 'id' => $id]);
    }
}
