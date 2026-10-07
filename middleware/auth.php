<?php
/**
 * Prime E Commerce Hub - Auth & CSRF Middleware
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';

// Check session timeout
$timeout = 28800; // 8 hours
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
    Auth::logout();
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        Response::unauthorized('Your session has expired. Please login again.');
    } else {
        header('Location: ' . BASE_URL . '/login.php?expired=1');
        exit;
    }
}

// Require login
if (!Auth::check()) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        Response::unauthorized('Authentication required to access this resource.');
    } else {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Check CSRF on mutating requests
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    if (!Security::verifyCsrfToken()) {
        Response::error('Invalid or missing CSRF token. Please refresh the page.', 403);
    }
}
