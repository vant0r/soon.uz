<?php
require_once __DIR__ . '/config.php';

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = '/api';
$path = trim(substr($requestUri, strlen($basePath)), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path === '') {
    apiResponse(['success' => true, 'name' => 'SOON API', 'version' => '1.0.0']);
}

$routes = [
    'GET' => [
        'services' => 'services.php',
        'portfolio' => 'portfolio.php',
        'blog' => 'blog.php',
        'settings' => 'settings.php',
        'user/profile' => 'user-profile.php',
        'user/applications' => 'user-applications.php',
        'notifications' => 'notifications.php'
    ],
    'POST' => [
        'auth/google' => 'auth-google.php',
        'user/applications' => 'user-applications.php',
        'notifications/read' => 'notifications.php'
    ],
    'PUT' => [
        'user/profile' => 'user-profile.php'
    ]
];

$routeFile = null;
if (preg_match('#^chat/(\d+)$#', $path, $matches)) {
    $_GET['thread_id'] = (int)$matches[1];
    $routeFile = 'chat-thread.php';
} elseif (isset($routes[$method][$path])) {
    $routeFile = $routes[$method][$path];
}

if ($routeFile === null) {
    apiError('API endpoint topilmadi.', 404);
}

$routePath = __DIR__ . '/' . $routeFile;
if (!is_file($routePath)) {
    apiError('Server xatoligi: endpoint fayli topilmadi.', 500);
}

require $routePath;
