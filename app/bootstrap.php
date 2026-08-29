<?php

declare(strict_types=1);

namespace App;

use App\Core\Config;
use Throwable;

// 1. PSR-4 Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 2. Load Configuration
Config::load();

// 3. Set Timezone & Error Reporting
date_default_timezone_set('UTC');
error_reporting(E_ALL);

// 4. Global Exception Handler
set_exception_handler(function (Throwable $e) {
    $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($statusCode);

    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'status' => 'error',
        'code' => $statusCode,
        'message' => $e->getMessage(),
    ];

    if (Config::isDebug()) {
        $response['file'] = $e->getFile();
        $response['line'] = $e->getLine();
        $response['trace'] = explode("\n", $e->getTraceAsString());
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
});

// 5. Global Error Handler
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
