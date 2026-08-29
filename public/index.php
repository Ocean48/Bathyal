<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ActivityLogController;
use App\Controllers\AIController;
use App\Controllers\AuthController;
use App\Controllers\CustomFieldController;
use App\Controllers\DependencyController;
use App\Controllers\DocumentController;
use App\Controllers\FolderController;
use App\Controllers\ProjectController;
use App\Controllers\StatusController;
use App\Controllers\SystemController;
use App\Controllers\TaskController;
use App\Controllers\TimeLogController;
use App\Controllers\UserGroupController;
use App\Controllers\WorkspaceController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\RateLimitMiddleware;

$router = new Router();

// Global Middlewares
$router->use(new CorsMiddleware());
$router->use(new JsonBodyParserMiddleware());
$router->use(new RateLimitMiddleware(120, 60)); // 120 requests per minute global

// Public Health Check Endpoint
$router->get('/api/v1/health', function (Request $req) {
    $db = Database::getConnection();
    $dbStatus = $db !== null ? 'connected' : 'unavailable';

    // Ping AI microservice
    $aiStatus = 'unavailable';
    $aiUrl = Config::get('ai.url', 'http://ai:8000') . '/api/v1/ai/health';
    $ctx = stream_context_create(['http' => ['timeout' => 1.0, 'ignore_errors' => true]]);
    $aiPing = @file_get_contents($aiUrl, false, $ctx);
    if ($aiPing !== false) {
        $aiData = json_decode($aiPing, true);
        if (isset($aiData['status']) && $aiData['status'] === 'healthy') {
            $aiStatus = 'connected';
        }
    }

    Response::json([
        'service' => 'bathyal-core',
        'status' => ($dbStatus === 'connected') ? 'healthy' : 'degraded',
        'environment' => Config::get('app_env', 'development'),
        'components' => [
            'php' => PHP_VERSION,
            'database' => $dbStatus,
            'ai_service' => $aiStatus,
        ],
        'timestamp' => date('c'),
    ]);
});

// Public Statuses List
$router->get('/api/v1/statuses', [StatusController::class, 'index']);

// Public Auth Endpoints
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);
$router->post('/api/v1/auth/register', [AuthController::class, 'register']);

// Development System Endpoints
$router->post('/api/v1/system/reset-db', [SystemController::class, 'resetDatabase']);

// Protected Route Group
$router->group(['prefix' => '/api/v1', 'middleware' => [AuthMiddleware::class]], function (Router $r) {
    // 1. Auth & User Profile
    $r->get('/auth/me', [AuthController::class, 'me']);
    $r->post('/auth/logout', [AuthController::class, 'logout']);
    $r->patch('/auth/preferences', [AuthController::class, 'updatePreferences']);

    // 2. Workspaces & User Groups
    $r->get('/workspaces', [WorkspaceController::class, 'index']);
    $r->post('/workspaces', [WorkspaceController::class, 'store']);
    $r->get('/workspaces/{id:\d+}', [WorkspaceController::class, 'show']);
    $r->get('/workspaces/{id:\d+}/tree', [WorkspaceController::class, 'tree']);

    $r->get('/user-groups', [UserGroupController::class, 'index']);
    $r->post('/user-groups', [UserGroupController::class, 'store']);
    $r->get('/user-groups/{id:\d+}', [UserGroupController::class, 'show']);
    $r->patch('/user-groups/{id:\d+}', [UserGroupController::class, 'update']);
    $r->delete('/user-groups/{id:\d+}', [UserGroupController::class, 'destroy']);
    $r->post('/user-groups/{id:\d+}/members', [UserGroupController::class, 'addMember']);
    $r->delete('/user-groups/{id:\d+}/members/{userId:\d+}', [UserGroupController::class, 'removeMember']);
    $r->post('/tasks/{id:\d+}/group-assignees', [UserGroupController::class, 'assignTaskGroup']);
    $r->delete('/tasks/{id:\d+}/group-assignees/{groupId:\d+}', [UserGroupController::class, 'unassignTaskGroup']);

    // 3. Folders & Projects
    $r->get('/folders', [FolderController::class, 'index']);
    $r->post('/folders', [FolderController::class, 'store']);
    $r->get('/folders/{id:\d+}', [FolderController::class, 'show']);
    $r->patch('/folders/{id:\d+}', [FolderController::class, 'update']);
    $r->delete('/folders/{id:\d+}', [FolderController::class, 'destroy']);

    $r->get('/projects', [ProjectController::class, 'index']);
    $r->post('/projects', [ProjectController::class, 'store']);
    $r->get('/projects/{id:\d+}', [ProjectController::class, 'show']);
    $r->patch('/projects/{id:\d+}', [ProjectController::class, 'update']);
    $r->delete('/projects/{id:\d+}', [ProjectController::class, 'destroy']);
    $r->post('/projects/{id:\d+}/duplicate', [ProjectController::class, 'duplicate']);

    // 4. Tasks & Dependencies
    $r->get('/tasks', [TaskController::class, 'index']);
    $r->post('/tasks', [TaskController::class, 'store']);
    $r->get('/tasks/{id:\d+}', [TaskController::class, 'show']);
    $r->patch('/tasks/{id:\d+}', [TaskController::class, 'update']);
    $r->delete('/tasks/{id:\d+}', [TaskController::class, 'destroy']);
    $r->post('/tasks/reorder', [TaskController::class, 'reorder']);
    $r->post('/tasks/batch', [TaskController::class, 'batch']);
    $r->get('/tasks/{id:\d+}/documents', [DocumentController::class, 'taskDocuments']);
    $r->post('/tasks/{id:\d+}/documents', [DocumentController::class, 'attachToTask']);
    $r->delete('/tasks/{id:\d+}/documents/{docId:\d+}', [DocumentController::class, 'detachFromTask']);

    $r->get('/dependencies', [DependencyController::class, 'index']);
    $r->post('/dependencies', [DependencyController::class, 'store']);
    $r->delete('/dependencies/{id:\d+}', [DependencyController::class, 'destroy']);

    // 5. Custom Fields
    $r->get('/custom-fields', [CustomFieldController::class, 'index']);
    $r->post('/custom-fields', [CustomFieldController::class, 'store']);
    $r->put('/tasks/{id:\d+}/custom-fields', [CustomFieldController::class, 'updateTaskValue']);
    $r->delete('/custom-fields/{id:\d+}', [CustomFieldController::class, 'destroy']);

    // 6. Documents & File Attachments
    $r->get('/documents', [DocumentController::class, 'index']);
    $r->post('/documents', [DocumentController::class, 'store']);
    $r->post('/documents/upload', [DocumentController::class, 'upload']);
    $r->get('/documents/{id:\d+}', [DocumentController::class, 'show']);
    $r->patch('/documents/{id:\d+}', [DocumentController::class, 'update']);
    $r->delete('/documents/{id:\d+}', [DocumentController::class, 'destroy']);
    $r->get('/documents/{id:\d+}/download', [DocumentController::class, 'download']);
    $r->get('/documents/{id:\d+}/preview', [DocumentController::class, 'preview']);

    // 7. Time Logs & Activity Logs
    $r->get('/time-logs', [TimeLogController::class, 'index']);
    $r->post('/time-logs', [TimeLogController::class, 'store']);
    $r->post('/time-logs/{id:\d+}/stop', [TimeLogController::class, 'stop']);
    $r->delete('/time-logs/{id:\d+}', [TimeLogController::class, 'destroy']);

    $r->get('/activity-logs', [ActivityLogController::class, 'index']);

    // 8. AI Microservice Proxy
    $r->post('/ai/estimate-task', [AIController::class, 'estimateTask']);
    $r->post('/ai/optimize-schedule', [AIController::class, 'optimizeSchedule']);
    $r->post('/ai/decompose-task', [AIController::class, 'decomposeTask']);
    $r->post('/ai/parse-prompt', [AIController::class, 'parsePrompt']);
    $r->post('/ai/recommend-dependencies', [AIController::class, 'recommendDependencies']);
});

// Dispatch request
$router->dispatch();


