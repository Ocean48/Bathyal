<?php

namespace App\Services;

use App\Core\Database;

class AutoScheduler
{
    /**
     * Check if adding a dependency would create a cycle (Kahn's algorithm)
     */
    public static function wouldCreateCycle(int $blockingTaskId, int $dependentTaskId): bool
    {
        if ($blockingTaskId === $dependentTaskId) {
            return true;
        }

        // Fetch all existing dependencies
        $dependencies = Database::fetchAll("SELECT blocking_task_id, dependent_task_id FROM task_dependencies");
        
        $adj = [];
        $inDegree = [];
        $nodes = [];

        foreach ($dependencies as $dep) {
            $u = (int)$dep['blocking_task_id'];
            $v = (int)$dep['dependent_task_id'];
            $adj[$u][] = $v;
            $inDegree[$v] = ($inDegree[$v] ?? 0) + 1;
            $nodes[$u] = true;
            $nodes[$v] = true;
        }

        // Add hypothetical edge
        $adj[$blockingTaskId][] = $dependentTaskId;
        $inDegree[$dependentTaskId] = ($inDegree[$dependentTaskId] ?? 0) + 1;
        $nodes[$blockingTaskId] = true;
        $nodes[$dependentTaskId] = true;

        // Kahn's algorithm
        $queue = [];
        foreach (array_keys($nodes) as $node) {
            if (($inDegree[$node] ?? 0) === 0) {
                $queue[] = $node;
            }
        }

        $visitedCount = 0;
        while (!empty($queue)) {
            $u = array_shift($queue);
            $visitedCount++;

            if (isset($adj[$u])) {
                foreach ($adj[$u] as $v) {
                    $inDegree[$v]--;
                    if ($inDegree[$v] === 0) {
                        $queue[] = $v;
                    }
                }
            }
        }

        return $visitedCount < count($nodes);
    }

    /**
     * Recalculate schedule starting from a modified task
     */
    public static function rescheduleFromTask(int $taskId): array
    {
        $updatedTasks = [];
        $queue = [$taskId];
        $visited = [];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $currentTask = Database::fetchOne("SELECT id, start_date, due_date FROM tasks WHERE id = :id", ['id' => $currentId]);
            if (!$currentTask || empty($currentTask['due_date'])) {
                continue;
            }

            $currentDueTs = strtotime($currentTask['due_date']);
            $currentStartTs = !empty($currentTask['start_date']) ? strtotime($currentTask['start_date']) : $currentDueTs;

            // Fetch downstream dependencies where current task is blocking
            $downstream = Database::fetchAll(
                "SELECT td.dependency_type, t.id, t.start_date, t.due_date
                 FROM task_dependencies td
                 JOIN tasks t ON td.dependent_task_id = t.id
                 WHERE td.blocking_task_id = :b_id",
                ['b_id' => $currentId]
            );

            foreach ($downstream as $down) {
                $depId = (int)$down['id'];
                $depType = $down['dependency_type'] ?? 'finish_to_start';
                $depStartTs = !empty($down['start_date']) ? strtotime($down['start_date']) : null;
                $depDueTs = !empty($down['due_date']) ? strtotime($down['due_date']) : null;
                $duration = ($depStartTs && $depDueTs && $depDueTs > $depStartTs) ? ($depDueTs - $depStartTs) : 86400; // default 1 day

                $newStartTs = $depStartTs;
                $newDueTs = $depDueTs;
                $modified = false;

                if ($depType === 'finish_to_start') {
                    // Dependent cannot start before blocking finishes
                    if (!$depStartTs || $depStartTs < $currentDueTs) {
                        $newStartTs = $currentDueTs;
                        $newDueTs = $newStartTs + $duration;
                        $modified = true;
                    }
                } elseif ($depType === 'start_to_start') {
                    // Dependent cannot start before blocking starts
                    if (!$depStartTs || $depStartTs < $currentStartTs) {
                        $newStartTs = $currentStartTs;
                        $newDueTs = $newStartTs + $duration;
                        $modified = true;
                    }
                } elseif ($depType === 'finish_to_finish') {
                    // Dependent cannot finish before blocking finishes
                    if (!$depDueTs || $depDueTs < $currentDueTs) {
                        $newDueTs = $currentDueTs;
                        $newStartTs = $newDueTs - $duration;
                        $modified = true;
                    }
                } elseif ($depType === 'start_to_finish') {
                    // Dependent cannot finish before blocking starts
                    if (!$depDueTs || $depDueTs < $currentStartTs) {
                        $newDueTs = $currentStartTs;
                        $newStartTs = $newDueTs - $duration;
                        $modified = true;
                    }
                }

                if ($modified) {
                    Database::execute(
                        "UPDATE tasks SET start_date = :start_d, due_date = :due_d, updated_at = NOW() WHERE id = :id",
                        [
                            'start_d' => date('Y-m-d H:i:s', $newStartTs),
                            'due_d' => date('Y-m-d H:i:s', $newDueTs),
                            'id' => $depId,
                        ]
                    );

                    $updatedTasks[$depId] = [
                        'id' => $depId,
                        'start_date' => date('Y-m-d H:i:s', $newStartTs),
                        'due_date' => date('Y-m-d H:i:s', $newDueTs),
                    ];

                    $queue[] = $depId;
                }
            }
        }

        return array_values($updatedTasks);
    }
}
