<?php

namespace App\Services;

use App\Core\Database;

class PermEngine
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';
    public const ROLE_GUEST = 'guest';

    private static array $roleHierarchy = [
        self::ROLE_OWNER => 4,
        self::ROLE_ADMIN => 3,
        self::ROLE_MEMBER => 2,
        self::ROLE_GUEST => 1,
    ];

    /**
     * Get user's role in a specific workspace
     */
    public static function getWorkspaceRole(int $userId, int $workspaceId): ?string
    {
        $member = Database::fetchOne(
            "SELECT role FROM workspace_members WHERE workspace_id = :ws_id AND user_id = :user_id",
            ['ws_id' => $workspaceId, 'user_id' => $userId]
        );

        return $member['role'] ?? null;
    }

    /**
     * Check if user has at least a specific minimum role in the workspace
     */
    public static function hasMinRole(int $userId, int $workspaceId, string $minRole): bool
    {
        $role = self::getWorkspaceRole($userId, $workspaceId);
        if (!$role) {
            return false;
        }

        $userLevel = self::$roleHierarchy[$role] ?? 0;
        $requiredLevel = self::$roleHierarchy[$minRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Check if user can view a workspace
     */
    public static function canViewWorkspace(int $userId, int $workspaceId): bool
    {
        return self::hasMinRole($userId, $workspaceId, self::ROLE_GUEST);
    }

    /**
     * Check if user can edit/create items in a workspace
     */
    public static function canEditWorkspace(int $userId, int $workspaceId): bool
    {
        return self::hasMinRole($userId, $workspaceId, self::ROLE_MEMBER);
    }

    /**
     * Check if user can manage members and settings in a workspace
     */
    public static function canManageWorkspace(int $userId, int $workspaceId): bool
    {
        return self::hasMinRole($userId, $workspaceId, self::ROLE_ADMIN);
    }

    /**
     * Check if user can delete a workspace
     */
    public static function canDeleteWorkspace(int $userId, int $workspaceId): bool
    {
        return self::hasMinRole($userId, $workspaceId, self::ROLE_OWNER);
    }

    /**
     * Check if user can manage user groups in a workspace
     */
    public static function canManageGroups(int $userId, int $workspaceId): bool
    {
        return self::hasMinRole($userId, $workspaceId, self::ROLE_ADMIN);
    }

    /**
     * Check access to a project (evaluating workspace role and group-level permissions)
     */
    public static function canViewProject(int $userId, int $projectId): bool
    {
        $project = Database::fetchOne("SELECT workspace_id FROM projects WHERE id = :id", ['id' => $projectId]);
        if (!$project) {
            return false;
        }

        $wsId = (int)$project['workspace_id'];
        if (self::canViewWorkspace($userId, $wsId)) {
            return true;
        }

        // Check group permissions
        $userGroupIds = GroupEngine::getUserGroupIds($userId, $wsId);
        if (!empty($userGroupIds)) {
            $placeholders = implode(',', array_fill(0, count($userGroupIds), '?'));
            $params = array_merge([$projectId], $userGroupIds);
            $hasGroupPerm = Database::fetchOne(
                "SELECT role FROM project_group_permissions WHERE project_id = ? AND group_id IN ({$placeholders})",
                $params
            );
            if ($hasGroupPerm) {
                return true;
            }
        }

        return false;
    }

    public static function canEditProject(int $userId, int $projectId): bool
    {
        $project = Database::fetchOne("SELECT workspace_id FROM projects WHERE id = :id", ['id' => $projectId]);
        if (!$project) {
            return false;
        }

        $wsId = (int)$project['workspace_id'];
        if (self::canEditWorkspace($userId, $wsId)) {
            return true;
        }

        // Check group-level edit permission
        $userGroupIds = GroupEngine::getUserGroupIds($userId, $wsId);
        if (!empty($userGroupIds)) {
            $placeholders = implode(',', array_fill(0, count($userGroupIds), '?'));
            $params = array_merge([$projectId], $userGroupIds);
            $groupPerm = Database::fetchOne(
                "SELECT role FROM project_group_permissions WHERE project_id = ? AND group_id IN ({$placeholders}) AND role IN ('admin', 'member')",
                $params
            );
            if ($groupPerm) {
                return true;
            }
        }

        return false;
    }

    public static function canDeleteProject(int $userId, int $projectId): bool
    {
        $project = Database::fetchOne("SELECT workspace_id FROM projects WHERE id = :id", ['id' => $projectId]);
        if (!$project) {
            return false;
        }
        return self::canManageWorkspace($userId, (int)$project['workspace_id']);
    }

    /**
     * Check access to a task (individual or group assignee)
     */
    public static function canViewTask(int $userId, int $taskId): bool
    {
        $task = Database::fetchOne("SELECT workspace_id FROM tasks WHERE id = :id", ['id' => $taskId]);
        if (!$task) {
            return false;
        }
        return self::canViewWorkspace($userId, (int)$task['workspace_id']);
    }

    public static function canEditTask(int $userId, int $taskId): bool
    {
        $task = Database::fetchOne("SELECT workspace_id FROM tasks WHERE id = :id", ['id' => $taskId]);
        if (!$task) {
            return false;
        }
        return self::canEditWorkspace($userId, (int)$task['workspace_id']);
    }

    public static function canDeleteTask(int $userId, int $taskId): bool
    {
        $task = Database::fetchOne("SELECT workspace_id, created_by FROM tasks WHERE id = :id", ['id' => $taskId]);
        if (!$task) {
            return false;
        }

        // Creator can delete or Admin/Owner can delete
        if ((int)$task['created_by'] === $userId) {
            return true;
        }

        return self::canManageWorkspace($userId, (int)$task['workspace_id']);
    }
}
