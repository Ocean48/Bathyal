<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Config;
use Tests\TestCase;

class SystemResetTest extends TestCase
{
    public function testDatabaseSchemaAndSeedIntegrity(): void
    {
        $schemaFile = dirname(__DIR__, 2) . '/docker/mysql/init/01-schema.sql';
        $seedFile = dirname(__DIR__, 2) . '/docker/mysql/init/02-seed.sql';

        $this->assertTrue(file_exists($schemaFile), 'Schema file should exist');
        $this->assertTrue(file_exists($seedFile), 'Seed file should exist');

        $userCount = Database::fetchColumn("SELECT COUNT(*) FROM users");
        $this->assertTrue((int)$userCount >= 2);

        $wsCount = Database::fetchColumn("SELECT COUNT(*) FROM workspaces");
        $this->assertTrue((int)$wsCount >= 2);

        $taskCount = Database::fetchColumn("SELECT COUNT(*) FROM tasks");
        $this->assertTrue((int)$taskCount >= 3);
    }
}

