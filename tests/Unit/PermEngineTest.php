<?php

namespace Tests\Unit;

use App\Services\PermEngine;
use Tests\TestCase;

class PermEngineTest extends TestCase
{
    public function testWorkspaceRoleHierarchy(): void
    {
        $this->assertTrue(PermEngine::hasMinRole(1, 2, PermEngine::ROLE_OWNER));
        $this->assertTrue(PermEngine::hasMinRole(1, 2, PermEngine::ROLE_ADMIN));
        $this->assertTrue(PermEngine::hasMinRole(1, 2, PermEngine::ROLE_MEMBER));
        $this->assertTrue(PermEngine::hasMinRole(1, 2, PermEngine::ROLE_GUEST));

        $this->assertTrue(PermEngine::canViewWorkspace(1, 2));
        $this->assertTrue(PermEngine::canEditWorkspace(1, 2));
        $this->assertTrue(PermEngine::canManageWorkspace(1, 2));
        $this->assertTrue(PermEngine::canDeleteWorkspace(1, 2));
    }

    public function testMemberPermissions(): void
    {
        $this->assertTrue(PermEngine::canViewWorkspace(2, 2));
        $this->assertTrue(PermEngine::canEditWorkspace(2, 2));
        $this->assertFalse(PermEngine::canDeleteWorkspace(2, 2));
    }
}
