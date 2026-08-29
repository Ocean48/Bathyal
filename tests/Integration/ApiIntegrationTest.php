<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Core\SessionManager;
use Tests\TestCase;

class ApiIntegrationTest extends TestCase
{
    public function testDatabaseConnection(): void
    {
        $pdo = Database::getConnection();
        $this->assertNotNull($pdo, 'Database PDO connection should not be null');
    }

    public function testUserAuthenticationRetrieval(): void
    {
        $user = Database::fetchOne("SELECT id, email, password_hash FROM users WHERE email = :email", [
            'email' => 'admin@bathyal.local'
        ]);
        $this->assertNotNull($user);
        $this->assertEquals(1, (int)$user['id']);

        $verified = SessionManager::verifyPassword('password123', $user['password_hash']);
        $this->assertTrue($verified, 'Admin password should verify against stored hash');
    }

    public function testWorkspaceTreeData(): void
    {
        $workspace = Database::fetchOne("SELECT * FROM workspaces WHERE id = :id", ['id' => 2]);
        $this->assertNotNull($workspace);
        $this->assertTrue(str_contains($workspace['name'], 'Acme'));

        $folders = Database::fetchAll("SELECT * FROM folders WHERE workspace_id = :ws_id", ['ws_id' => 2]);
        $this->assertTrue(count($folders) >= 2);

        $projects = Database::fetchAll("SELECT * FROM projects WHERE workspace_id = :ws_id", ['ws_id' => 2]);
        $this->assertTrue(count($projects) >= 2);
    }

    public function testTaskOperations(): void
    {
        $tasks = Database::fetchAll("SELECT * FROM tasks WHERE workspace_id = :ws_id", ['ws_id' => 2]);
        $this->assertTrue(count($tasks) >= 3);
    }

    public function testSubtaskHierarchyAndFiltering(): void
    {
        // 1. Create Parent Task
        $parentId = Database::insertGetId(
            "INSERT INTO tasks (workspace_id, project_id, parent_id, status_id, title, priority, created_by, created_at)
             VALUES (2, 1, NULL, 1, 'Parent Test Task', 'high', 1, NOW())"
        );
        $this->assertNotNull($parentId);

        // 2. Create Child Subtasks
        $sub1Id = Database::insertGetId(
            "INSERT INTO tasks (workspace_id, project_id, parent_id, status_id, title, priority, created_by, created_at)
             VALUES (2, 1, :p_id, 1, 'Child Subtask 1', 'medium', 1, NOW())",
            ['p_id' => $parentId]
        );

        $sub2Id = Database::insertGetId(
            "INSERT INTO tasks (workspace_id, project_id, parent_id, status_id, title, priority, created_by, created_at)
             VALUES (2, 1, :p_id, 3, 'Child Subtask 2 (Done)', 'low', 1, NOW())",
            ['p_id' => $parentId]
        );

        // 3. Verify subtask queries
        $subtasks = Database::fetchAll("SELECT * FROM tasks WHERE parent_id = :p_id", ['p_id' => $parentId]);
        $this->assertEquals(2, count($subtasks));

        // 4. Verify parent task subtask aggregation query
        $parentRecord = Database::fetchOne(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_id = t.id) AS subtask_count,
                    (SELECT COUNT(*) FROM tasks sub JOIN statuses sub_s ON sub.status_id = sub_s.id WHERE sub.parent_id = t.id AND (sub_s.type = 'completed' OR sub.status_id = 3)) AS completed_subtask_count
             FROM tasks t
             WHERE t.id = :id",
            ['id' => $parentId]
        );

        $this->assertEquals(2, (int)$parentRecord['subtask_count']);
        $this->assertEquals(1, (int)$parentRecord['completed_subtask_count']);

        // Clean up test tasks
        Database::execute("DELETE FROM tasks WHERE id IN (:p, :s1, :s2)", [
            'p' => $parentId,
            's1' => $sub1Id,
            's2' => $sub2Id,
        ]);
    }
}
