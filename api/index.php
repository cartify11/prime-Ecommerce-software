<?php
/**
 * Vercel Serverless Gateway & Front Controller
 * Handles routing for root scripts and dynamic requests on Vercel
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = urldecode($path);

// 1. Root route -> load index.php
if ($path === '/' || $path === '' || $path === '/index' || $path === '/index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

// 2. Specific top-level routes
$cleanRoute = ltrim($path, '/');
if ($cleanRoute === 'login' || $cleanRoute === 'login.php') {
    require __DIR__ . '/../login.php';
    exit;
}
if ($cleanRoute === 'logout' || $cleanRoute === 'logout.php') {
    require __DIR__ . '/../logout.php';
    exit;
}
if ($cleanRoute === 'install' || $cleanRoute === 'install.php') {
    require __DIR__ . '/../install.php';
    exit;
}

// 3. API endpoint routing
$targetFile = realpath(__DIR__ . '/../' . $cleanRoute);
$rootDir = realpath(__DIR__ . '/../');

// Security check: ensure target stays inside project root
if ($targetFile && strpos($targetFile, $rootDir) === 0 && file_exists($targetFile) && !is_dir($targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        require $targetFile;
        exit;
    }
    
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon'
    ];
    if (isset($mimes[$ext])) {
        header("Content-Type: {$mimes[$ext]}");
    }
    readfile($targetFile);
    exit;
}

// Check with .php extension appended
if (file_exists(__DIR__ . '/../' . $cleanRoute . '.php')) {
    require __DIR__ . '/../' . $cleanRoute . '.php';
    exit;
}

// Default fallback
require __DIR__ . '/../index.php';
