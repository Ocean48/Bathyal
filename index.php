<?php
// /index.php (Front Controller / Router)

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Determine the requested URI path
$request_uri = $_SERVER['REQUEST_URI'];
// Strip out query parameters e.g., ?id=1
$path = parse_url($request_uri, PHP_URL_PATH);

// Load the config file
$config = require_once __DIR__ . '/config.php';
$base_path = $config['base_path'] ?? '';

// Remove the base path (case-insensitive to support symlinks) to figure out the internal app route
$route = str_ireplace($base_path, '', $path);
$route = trim($route, '/');
$route = strtolower($route); // Force lowercase for case-insensitive routing

// Simple Routing Switch
switch ($route) {
    case '':
    case 'index.php':
        require 'views/pages/dashboard.php';
        break;
    
    // Auth Routes
    case 'login.php':
    case 'login':
        require 'views/auth/login.php';
        break;
    case 'register.php':
    case 'register':
        require 'views/auth/register.php';
        break;
    case 'logout.php':
    case 'logout':
        require 'views/auth/logout.php';
        break;
        
    // Pages
    case 'tasks.php':
    case 'tasks':
        require 'views/pages/tasks.php';
        break;
    case 'project.php':
    case 'project':
        require 'views/pages/project.php';
        break;
    case 'projects.php':
    case 'projects':
        require 'views/pages/projects.php';
        break;
    case 'project_settings.php':
    case 'project_settings':
        require 'views/pages/project_settings.php';
        break;
    case 'settings.php':
    case 'settings':
        require 'views/pages/settings.php';
        break;
    case 'inbox.php':
    case 'inbox':
        require 'views/pages/inbox.php';
        break;
    case 'teams.php':
    case 'teams':
        require 'views/pages/teams.php';
        break;
    case 'reports.php':
    case 'reports':
        require 'views/pages/reports.php';
        break;
        
    case 'labels.php':
    case 'labels':
        require 'views/pages/labels.php';
        break;
        
    default:
        http_response_code(404);
        echo "<h1 style='text-align:center; margin-top: 50px; font-family:sans-serif;'>404 - Page Not Found</h1>";
        break;
}
?>
