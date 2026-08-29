<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\SessionManager;
use Throwable;

class SystemController extends BaseController
{
    public function resetDatabase(Request $request): void
    {
        $env = Config::get('app_env', 'development');
        if ($env !== 'development' && $env !== 'local' && $env !== 'testing') {
            $this->error('Database reset is only permitted in development environments.', Response::HTTP_FORBIDDEN);
        }

        $schemaFile = dirname(__DIR__, 2) . '/docker/mysql/init/01-schema.sql';
        $seedFile = dirname(__DIR__, 2) . '/docker/mysql/init/02-seed.sql';

        if (!file_exists($schemaFile)) {
            $this->error('Schema initialization file not found.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $pdo = Database::getConnection();
        if (!$pdo) {
            $this->error('Database connection unavailable.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            // Drop all tables
            $tables = Database::fetchAll("SHOW TABLES");
            foreach ($tables as $tableRow) {
                $tableName = array_values($tableRow)[0];
                $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`;");
            }

            // Execute schema
            $schemaSql = file_get_contents($schemaFile);
            $pdo->exec($schemaSql);

            // Execute seed if available
            if (file_exists($seedFile)) {
                $seedSql = file_get_contents($seedFile);
                $pdo->exec($seedSql);
            }

            // Ensure default test accounts have guaranteed valid bcrypt hashes for 'password123'
            $defaultHash = SessionManager::hashPassword('password123');
            Database::execute(
                "UPDATE users SET password_hash = :hash WHERE email IN ('admin@bathyal.local', 'demo@bathyal.local')",
                ['hash' => $defaultHash]
            );

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $userCount = Database::fetchColumn("SELECT COUNT(*) FROM users");
            $taskCount = Database::fetchColumn("SELECT COUNT(*) FROM tasks");
            $wsCount = Database::fetchColumn("SELECT COUNT(*) FROM workspaces");

            $this->json([
                'message' => 'Database schema and seed data reset successfully.',
                'stats' => [
                    'users' => (int)$userCount,
                    'tasks' => (int)$taskCount,
                    'workspaces' => (int)$wsCount,
                ],
                'default_credentials' => [
                    ['email' => 'admin@bathyal.local', 'password' => 'password123', 'role' => 'owner'],
                    ['email' => 'demo@bathyal.local', 'password' => 'password123', 'role' => 'member'],
                ],
            ]);
        } catch (Throwable $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $this->error('Database reset failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
