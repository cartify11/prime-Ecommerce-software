<?php
/**
 * Vercel Serverless Gateway & Front Controller
 * Handles routing for root scripts and dynamic requests on Vercel
 */

// Global Base URL detection for Vercel
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', "{$protocol}://{$host}");
}

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

// 3. API & Dynamic script routing
$targetFile = __DIR__ . '/../' . $cleanRoute;

// Avoid self-inclusion loop
if (realpath($targetFile) === realpath(__FILE__)) {
    require __DIR__ . '/../index.php';
    exit;
}

if (file_exists($targetFile) && !is_dir($targetFile)) {
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
if (file_exists($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// Default fallback to index.php
require __DIR__ . '/../index.php';
