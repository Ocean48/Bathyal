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
}
