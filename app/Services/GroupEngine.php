<?php

namespace App\Services;

use App\Core\Database;

class GroupEngine
{
    /**
     * Create a new user group within a workspace
     */
    public static function createGroup(int $workspaceId, string $name, ?string $description = null, string $colorHex = '#6366F1'): array
    {
        $baseSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($name)));
        $slug = $baseSlug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $groupId = Database::insertGetId(
            "INSERT INTO user_groups (workspace_id, name, slug, description, color_hex, created_at)
             VALUES (:ws_id, :name, :slug, :desc, :color, NOW())",
            [
                'ws_id' => $workspaceId,
                'name' => trim($name),
                'slug' => $slug,
                'desc' => $description,
                'color' => $colorHex,
            ]
        );

        return Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $groupId]);
    }

    /**
     * Add a user to a group
     */
    public static function addMember(int $groupId, int $userId): bool
    {
        return Database::execute(
            "INSERT IGNORE INTO user_group_members (group_id, user_id, created_at)
             VALUES (:g_id, :u_id, NOW())",
            ['g_id' => $groupId, 'u_id' => $userId]
        ) > 0;
    }

    /**
     * Remove a user from a group
     */
    public static function removeMember(int $groupId, int $userId): bool
    {
        return Database::execute(
            "DELETE FROM user_group_members WHERE group_id = :g_id AND user_id = :u_id",
            ['g_id' => $groupId, 'u_id' => $userId]
        ) > 0;
    }

    /**
     * Get all group IDs a user belongs to within a workspace
     */
    public static function getUserGroupIds(int $userId, int $workspaceId): array
    {
        $rows = Database::fetchAll(
            "SELECT ugm.group_id
             FROM user_group_members ugm
             JOIN user_groups ug ON ugm.group_id = ug.id
             WHERE ugm.user_id = :u_id AND ug.workspace_id = :ws_id",
            ['u_id' => $userId, 'ws_id' => $workspaceId]
        );

        return array_map(fn($r) => (int)$r['group_id'], $rows);
    }

    /**
     * Get list of members in a group
     */
    public static function getGroupMembers(int $groupId): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.email, u.full_name, u.default_mode, ugm.created_at AS joined_at
             FROM user_group_members ugm
             JOIN users u ON ugm.user_id = u.id
             WHERE ugm.group_id = :g_id
             ORDER BY u.full_name ASC",
            ['g_id' => $groupId]
        );
    }

    /**
     * Get all groups in a workspace with member counts
     */
    public static function getWorkspaceGroups(int $workspaceId): array
    {
        $groups = Database::fetchAll(
            "SELECT ug.*,
                    (SELECT COUNT(*) FROM user_group_members ugm WHERE ugm.group_id = ug.id) AS member_count
             FROM user_groups ug
             WHERE ug.workspace_id = :ws_id
             ORDER BY ug.name ASC",
            ['ws_id' => $workspaceId]
        );

        return $groups;
    }

    /**
     * Assign task to a user group
     */
    public static function assignTaskToGroup(int $taskId, int $groupId): bool
    {
        return Database::execute(
            "INSERT IGNORE INTO task_group_assignees (task_id, group_id)
             VALUES (:t_id, :g_id)",
            ['t_id' => $taskId, 'g_id' => $groupId]
        ) > 0;
    }

    /**
     * Unassign task from a user group
     */
    public static function unassignTaskFromGroup(int $taskId, int $groupId): bool
    {
        return Database::execute(
            "DELETE FROM task_group_assignees WHERE task_id = :t_id AND group_id = :g_id",
            ['t_id' => $taskId, 'g_id' => $groupId]
        ) > 0;
    }

    /**
     * Get group assignees for a task
     */
    public static function getTaskGroupAssignees(int $taskId): array
    {
        return Database::fetchAll(
            "SELECT ug.id, ug.name, ug.color_hex, ug.slug
             FROM task_group_assignees tga
             JOIN user_groups ug ON tga.group_id = ug.id
             WHERE tga.task_id = :t_id",
            ['t_id' => $taskId]
        );
    }

    /**
     * Set group-level role for a project
     */
    public static function setProjectGroupPermission(int $projectId, int $groupId, string $role = 'member'): bool
    {
        Database::execute(
            "DELETE FROM project_group_permissions WHERE project_id = :p_id AND group_id = :g_id",
            ['p_id' => $projectId, 'g_id' => $groupId]
        );

        return Database::execute(
            "INSERT INTO project_group_permissions (project_id, group_id, role)
             VALUES (:p_id, :g_id, :role)",
            ['p_id' => $projectId, 'g_id' => $groupId, 'role' => $role]
        ) > 0;
    }
}
