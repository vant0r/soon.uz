# API Router for WebHub.uz
# All requests to /api/* are routed through this file

<?php
/**
 * API Entry Point - Routes all /api/* requests
 */

require_once __DIR__ . '/config.php';

// Get request URI and method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/api/';

// Remove base path
$path = substr($requestUri, strlen($basePath));

if ($path === false || $path === '') {
    apiError('API endpoint topilmadi.', 404);
}

// Remove leading slash
$path = ltrim($path, '/');

$method = $_SERVER['REQUEST_METHOD'];

// Route mapping
$routes = [
    'GET' => [
        'services' => 'services.php',
        'portfolio' => 'portfolio.php',
        'blog' => 'blog.php',
        'settings' => 'settings.php',
        'user/profile' => 'user-profile.php',
        'user/applications' => 'user-applications.php',
        'notifications' => 'notifications.php',
    ],
    'POST' => [
        'auth/google' => 'auth-google.php',
        'user/profile' => 'user-profile.php',
        'user/applications' => 'user-applications.php',
        'notifications/read' => 'notifications.php',
    ],
    'PUT' => [
        'user/profile' => 'user-profile.php',
    ],
];

// Handle chat endpoints with dynamic thread_id
if (preg_match('^chat/(\d+)$^', $path, $matches)) {
    $routeFile = 'chat-thread.php';
} elseif (isset($routes[$method][$path])) {
    $routeFile = $routes[$method][$path];
} else {
    // Try without method specificity
    foreach ($routes['GET'] as $route => $file) {
        if ($path === $route || strpos($path, $route . '/') === 0) {
            $routeFile = $file;
            break;
        }
    }
    
    if (!isset($routeFile)) {
        apiError('API endpoint topilmadi: ' . e($path), 404);
    }
}

// Include and execute the route handler
$routePath = __DIR__ . '/' . $routeFile;

if (!file_exists($routePath)) {
    apiError('Server xatoligi: Endpoint fayli topilmadi.', 500);
}

require $routePath;
