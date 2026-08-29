<?php

declare(strict_types=1);

// Basic PSR-4 autoloader for /app namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Core\Router;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$router = new Router();

// Health check endpoint
$router->get('/api/v1/health', function () {
    $db = Database::getConnection();
    $dbStatus = $db !== null ? 'connected' : 'unavailable';

    echo json_encode([
        'status' => 'healthy',
        'service' => 'bathyal-php-core',
        'environment' => getenv('APP_ENV') ?: 'development',
        'database' => $dbStatus,
        'timestamp' => date('c')
    ]);
});

// Workspace list endpoint (initial stub)
$router->get('/api/v1/workspaces', function () {
    $db = Database::getConnection();
    if (!$db) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        return;
    }

    try {
        $stmt = $db->query("SELECT id, name, slug, is_personal, created_at FROM workspaces ORDER BY id DESC LIMIT 50");
        $workspaces = $stmt->fetchAll();
        echo json_encode(['data' => $workspaces]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
});

// Statuses list endpoint
$router->get('/api/v1/statuses', function () {
    $db = Database::getConnection();
    if (!$db) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        return;
    }

    try {
        $stmt = $db->query("SELECT * FROM statuses ORDER BY position ASC");
        $statuses = $stmt->fetchAll();
        echo json_encode(['data' => $statuses]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
});

// Dispatch current request
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
