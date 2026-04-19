<?php
// /index.php (Front Controller / Router)

// Determine the requested URI path
$request_uri = $_SERVER['REQUEST_URI'];
// Strip out query parameters e.g., ?id=1
$path = parse_url($request_uri, PHP_URL_PATH);

// Base directory path depending on Apache Alias/DocumentRoot setups.
// Change this if the app is hosted directly at the domain root (e.g. replacing it with '')
$base_path = '/bathyal'; 
// Remove the base path (case-insensitive to support /bathyal symlink) to figure out the internal app route
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
    case 'project.php':
    case 'project':
        require 'views/pages/project.php';
        break;
    case 'project_settings.php':
    case 'project_settings':
        require 'views/pages/project_settings.php';
        break;
    case 'settings.php':
    case 'settings':
        require 'views/pages/settings.php';
        break;
    case 'teams.php':
    case 'teams':
        require 'views/pages/teams.php';
        break;
        
    default:
        http_response_code(404);
        echo "<h1 style='text-align:center; margin-top: 50px; font-family:sans-serif;'>404 - Page Not Found</h1>";
        break;
}
?>