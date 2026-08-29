<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;

class StatusController extends BaseController
{
    public function index(Request $request): void
    {
        $projectId = $request->get('project_id');

        if ($projectId) {
            $statuses = Database::fetchAll(
                "SELECT * FROM statuses WHERE project_id = :project_id OR project_id IS NULL ORDER BY position ASC",
                ['project_id' => (int)$projectId]
            );
        } else {
            $statuses = Database::fetchAll(
                "SELECT * FROM statuses WHERE project_id IS NULL ORDER BY position ASC"
            );
        }

        $this->json($statuses);
    }
}
