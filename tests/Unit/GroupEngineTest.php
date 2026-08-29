<?php

namespace Tests\Unit;

use App\Services\GroupEngine;
use App\Services\PermEngine;
use Tests\TestCase;

class GroupEngineTest extends TestCase
{
    public function testCreateGroupAndMembers(): void
    {
        $groupName = 'Backend Engineering ' . bin2hex(random_bytes(2));
        $group = GroupEngine::createGroup(2, $groupName, 'API & DB squad', '#10B981');
        
        $this->assertNotNull($group);
        $this->assertEquals($groupName, $group['name']);
        $this->assertEquals(2, (int)$group['workspace_id']);

        $groupId = (int)$group['id'];

        // Add member
        $added = GroupEngine::addMember($groupId, 1);
        $this->assertTrue($added);

        // Check user group IDs
        $groupIds = GroupEngine::getUserGroupIds(1, 2);
        $this->assertTrue(in_array($groupId, $groupIds, true));

        // Check members list
        $members = GroupEngine::getGroupMembers($groupId);
        $this->assertTrue(count($members) >= 1);
        $this->assertEquals(1, (int)$members[0]['id']);

        // Remove member
        $removed = GroupEngine::removeMember($groupId, 1);
        $this->assertTrue($removed);
    }

    public function testTaskGroupAssignment(): void
    {
        $group = GroupEngine::createGroup(2, 'Design Team ' . bin2hex(random_bytes(2)), 'UI/UX designers');
        $groupId = (int)$group['id'];
        $taskId = 5;

        $assigned = GroupEngine::assignTaskToGroup($taskId, $groupId);
        $this->assertTrue($assigned);

        $assignees = GroupEngine::getTaskGroupAssignees($taskId);
        $this->assertTrue(count($assignees) >= 1);
        $found = false;
        foreach ($assignees as $a) {
            if ((int)$a['id'] === $groupId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $unassigned = GroupEngine::unassignTaskFromGroup($taskId, $groupId);
        $this->assertTrue($unassigned);
    }

    public function testProjectGroupPermissionInheritance(): void
    {
        $group = GroupEngine::createGroup(2, 'QA Squad ' . bin2hex(random_bytes(2)));
        $groupId = (int)$group['id'];
        $projectId = 1;

        $set = GroupEngine::setProjectGroupPermission($projectId, $groupId, 'member');
        $this->assertTrue($set);
    }
}
