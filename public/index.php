<?php
/**
 * Front controller / router. Every request enters through this one file
 * (see public/.htaccess). It figures out which feature's page should
 * handle the request, then hands off to templates/layout.php.
 */
session_start();

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/autoload.php';

// --- Work out the request path, even if the app sits in a subfolder ---
// (e.g. http://localhost/lms/public/login instead of http://localhost/login)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = $requestUri;
if ($scriptDir !== '' && str_starts_with($requestUri, $scriptDir)) {
    $path = substr($requestUri, strlen($scriptDir));
}
$path = '/' . ltrim($path, '/');
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// --- Collect routes from every feature's routes.php ---
$routes = [];
foreach (glob(dirname(__DIR__) . '/features/*/routes.php') as $routeFile) {
    $routes = array_merge($routes, require $routeFile);
}

// --- Find a matching route ---
$pageFile = null;
foreach ($routes as [$routeMethod, $routePath, $routeFile]) {
    if ($routeMethod === $method && $routePath === $path) {
        $pageFile = $routeFile;
        break;
    }
}

if ($pageFile === null) {
    http_response_code(404);
    require __DIR__ . '/../templates/header.php';
    echo '<h1>404</h1><p>Page not found.</p>';
    require __DIR__ . '/../templates/footer.php';
    exit;
}

require __DIR__ . '/../templates/layout.php';