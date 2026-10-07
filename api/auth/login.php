<?php
/**
 * Prime E Commerce Hub - Authentication Login Endpoint
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$username = Security::sanitizeString($data['username'] ?? '');
$password = $data['password'] ?? '';
$ip = Security::getClientIp();

if (empty($username) || empty($password)) {
    Response::error('Username and password are required');
}

// Check rate limiting / brute force
if (Security::isRateLimited($username, $ip)) {
    Response::error('Too many failed login attempts. Please wait 15 minutes before trying again.', 429);
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        Security::recordFailedLogin($username, $ip);
        Audit::log('failed_login', 'user', null, "Failed login attempt for username: {$username}");
        Response::error('Invalid credentials or account is inactive', 401);
    }

    // Success! Clear failed attempts
    Security::clearLoginAttempts($username, $ip);

    // Login user session
    Auth::login($user);
    Audit::log('login', 'user', (string)$user['id'], "User {$user['username']} logged in successfully");

    Response::success('Login successful', [
        'user' => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role'     => $user['role']
        ],
        'csrf_token' => Security::getCsrfToken()
    ]);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    Response::error('Authentication error occurred', 500);
}
