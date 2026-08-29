<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\GroupEngine;
use App\Services\PermEngine;

class UserGroupController extends BaseController
{
    public function index(Request $request): void
    {
        $userId = $this->getUserId($request);
        $workspaceId = $request->getInt('workspace_id');

        if ($workspaceId <= 0) {
            $this->error('workspace_id parameter is required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!PermEngine::canViewWorkspace($userId, $workspaceId)) {
            $this->error('Access denied to workspace groups', Response::HTTP_FORBIDDEN);
        }

        $groups = GroupEngine::getWorkspaceGroups($workspaceId);
        $this->json($groups);
    }

    public function store(Request $request): void
    {
        $userId = $this->getUserId($request);
        $payload = $request->getJson();
        $this->validate($payload, [
            'workspace_id' => 'required',
            'name' => 'required|min:2',
        ]);

        $workspaceId = (int)$payload['workspace_id'];
        if (!PermEngine::canManageGroups($userId, $workspaceId)) {
            $this->error('Permission denied to create groups in this workspace', Response::HTTP_FORBIDDEN);
        }

        $name = trim($payload['name']);
        $description = $payload['description'] ?? null;
        $colorHex = $payload['color_hex'] ?? '#6366F1';

        $group = GroupEngine::createGroup($workspaceId, $name, $description, $colorHex);
        
        // Auto-add creator to the group
        GroupEngine::addMember((int)$group['id'], $userId);

        $this->json($group, Response::HTTP_CREATED);
    }

    public function show(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $group = Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $id]);
        if (!$group) {
            $this->error('User group not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canViewWorkspace($userId, (int)$group['workspace_id'])) {
            $this->error('Access denied', Response::HTTP_FORBIDDEN);
        }

        $group['members'] = GroupEngine::getGroupMembers($id);
        $this->json($group);
    }

    public function update(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');
        $payload = $request->getJson();

        $group = Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $id]);
        if (!$group) {
            $this->error('User group not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageGroups($userId, (int)$group['workspace_id'])) {
            $this->error('Permission denied', Response::HTTP_FORBIDDEN);
        }

        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('name', $payload)) {
            $fields[] = "name = :name";
            $params['name'] = trim($payload['name']);
        }
        if (array_key_exists('description', $payload)) {
            $fields[] = "description = :desc";
            $params['desc'] = $payload['description'];
        }
        if (array_key_exists('color_hex', $payload)) {
            $fields[] = "color_hex = :color";
            $params['color'] = $payload['color_hex'];
        }

        if (!empty($fields)) {
            Database::execute("UPDATE user_groups SET " . implode(', ', $fields) . " WHERE id = :id", $params);
        }

        $updated = Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $id]);
        $this->json($updated);
    }

    public function destroy(Request $request): void
    {
        $userId = $this->getUserId($request);
        $id = (int)$request->param('id');

        $group = Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $id]);
        if (!$group) {
            $this->error('User group not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageGroups($userId, (int)$group['workspace_id'])) {
            $this->error('Permission denied to delete group', Response::HTTP_FORBIDDEN);
        }

        Database::execute("DELETE FROM user_groups WHERE id = :id", ['id' => $id]);
        $this->json(['message' => 'User group deleted successfully', 'id' => $id]);
    }

    public function addMember(Request $request): void
    {
        $userId = $this->getUserId($request);
        $groupId = (int)$request->param('id');
        $payload = $request->getJson();
        $this->validate($payload, [
            'user_id' => 'required',
        ]);

        $group = Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $groupId]);
        if (!$group) {
            $this->error('User group not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageGroups($userId, (int)$group['workspace_id'])) {
            $this->error('Permission denied to add group members', Response::HTTP_FORBIDDEN);
        }

        $targetUserId = (int)$payload['user_id'];
        GroupEngine::addMember($groupId, $targetUserId);

        $members = GroupEngine::getGroupMembers($groupId);
        $this->json(['message' => 'Member added to group', 'members' => $members]);
    }

    public function removeMember(Request $request): void
    {
        $userId = $this->getUserId($request);
        $groupId = (int)$request->param('id');
        $targetUserId = (int)$request->param('userId');

        $group = Database::fetchOne("SELECT * FROM user_groups WHERE id = :id", ['id' => $groupId]);
        if (!$group) {
            $this->error('User group not found', Response::HTTP_NOT_FOUND);
        }

        if (!PermEngine::canManageGroups($userId, (int)$group['workspace_id'])) {
            $this->error('Permission denied to remove group members', Response::HTTP_FORBIDDEN);
        }

        GroupEngine::removeMember($groupId, $targetUserId);

        $members = GroupEngine::getGroupMembers($groupId);
        $this->json(['message' => 'Member removed from group', 'members' => $members]);
    }

    public function assignTaskGroup(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = (int)$request->param('id');
        $payload = $request->getJson();
        $this->validate($payload, [
            'group_id' => 'required',
        ]);

        $groupId = (int)$payload['group_id'];
        if (!PermEngine::canEditTask($userId, $taskId)) {
            $this->error('Permission denied to assign task', Response::HTTP_FORBIDDEN);
        }

        GroupEngine::assignTaskToGroup($taskId, $groupId);
        $groupAssignees = GroupEngine::getTaskGroupAssignees($taskId);

        $this->json(['message' => 'Task assigned to group', 'group_assignees' => $groupAssignees]);
    }

    public function unassignTaskGroup(Request $request): void
    {
        $userId = $this->getUserId($request);
        $taskId = (int)$request->param('id');
        $groupId = (int)$request->param('groupId');

        if (!PermEngine::canEditTask($userId, $taskId)) {
            $this->error('Permission denied to unassign task', Response::HTTP_FORBIDDEN);
        }

        GroupEngine::unassignTaskFromGroup($taskId, $groupId);
        $groupAssignees = GroupEngine::getTaskGroupAssignees($taskId);

        $this->json(['message' => 'Task unassigned from group', 'group_assignees' => $groupAssignees]);
    }
}
