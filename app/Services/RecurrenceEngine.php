<?php

namespace App\Services;

use App\Core\Database;

class RecurrenceEngine
{
    /**
     * Calculate next occurrence date given a rule and base date
     * Supported patterns: daily, weekdays, weekly, monthly, yearly, interval:N (days)
     */
    public static function calculateNextDate(string $pattern, ?string $baseDate = null): ?string
    {
        $baseTs = $baseDate ? strtotime($baseDate) : time();
        if ($baseTs === false) {
            $baseTs = time();
        }

        $pattern = strtolower(trim($pattern));

        if ($pattern === 'daily') {
            return date('Y-m-d H:i:s', strtotime('+1 day', $baseTs));
        }

        if ($pattern === 'weekdays') {
            $dayOfWeek = (int)date('N', $baseTs); // 1 = Mon, 5 = Fri, 6 = Sat, 7 = Sun
            if ($dayOfWeek >= 5) {
                return date('Y-m-d H:i:s', strtotime('next Monday', $baseTs));
            }
            return date('Y-m-d H:i:s', strtotime('+1 day', $baseTs));
        }

        if ($pattern === 'weekly') {
            return date('Y-m-d H:i:s', strtotime('+1 week', $baseTs));
        }

        if ($pattern === 'monthly') {
            return date('Y-m-d H:i:s', strtotime('+1 month', $baseTs));
        }

        if ($pattern === 'yearly') {
            return date('Y-m-d H:i:s', strtotime('+1 year', $baseTs));
        }

        if (str_starts_with($pattern, 'interval:')) {
            $days = (int)substr($pattern, 9);
            if ($days > 0) {
                return date('Y-m-d H:i:s', strtotime("+{$days} days", $baseTs));
            }
        }

        return null;
    }

    /**
     * Handle task completion for recurring tasks
     */
    public static function handleTaskCompletion(int $taskId, string $recurrencePattern): ?int
    {
        $task = Database::fetchOne("SELECT * FROM tasks WHERE id = :id", ['id' => $taskId]);
        if (!$task) {
            return null;
        }

        $nextDue = self::calculateNextDate($recurrencePattern, $task['due_date']);
        if (!$nextDue) {
            return null;
        }

        $nextStart = null;
        if (!empty($task['start_date']) && !empty($task['due_date'])) {
            $duration = strtotime($task['due_date']) - strtotime($task['start_date']);
            $nextStart = date('Y-m-d H:i:s', strtotime($nextDue) - $duration);
        }

        // Clone task for next occurrence
        $newTaskId = Database::insertGetId(
            "INSERT INTO tasks (workspace_id, project_id, parent_id, status_id, title, description, priority, start_date, due_date, estimated_hours, position, created_by, created_at)
             VALUES (:ws_id, :proj_id, :p_id, 1, :title, :desc, :prio, :start_d, :due_d, :est, :pos, :c_by, NOW())",
            [
                'ws_id' => $task['workspace_id'],
                'proj_id' => $task['project_id'],
                'p_id' => $task['parent_id'],
                'title' => $task['title'],
                'desc' => $task['description'],
                'prio' => $task['priority'],
                'start_d' => $nextStart,
                'due_d' => $nextDue,
                'est' => $task['estimated_hours'],
                'pos' => ((int)$task['position']) + 1,
                'c_by' => $task['created_by'],
            ]
        );

        // Copy assignees
        $assignees = Database::fetchAll("SELECT user_id FROM task_assignees WHERE task_id = :id", ['id' => $taskId]);
        foreach ($assignees as $assignee) {
            Database::execute(
                "INSERT INTO task_assignees (task_id, user_id) VALUES (:t_id, :u_id)",
                ['t_id' => $newTaskId, 'u_id' => $assignee['user_id']]
            );
        }

        return (int)$newTaskId;
    }
}
