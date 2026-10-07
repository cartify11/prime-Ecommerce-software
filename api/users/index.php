<?php
/**
 * Prime E Commerce Hub - Users List and Create Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

Auth::requirePermission('users_manage');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = Database::getConnection();

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, username, name, email, role, status, last_login, created_at FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
    Response::success('Users loaded', $users);
} elseif ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $username = Security::sanitizeString($data['username'] ?? '');
    $name     = Security::sanitizeString($data['name'] ?? '');
    $email    = Security::sanitizeString($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role     = $data['role'] ?? 'sales';

    if (empty($username) || empty($name) || empty($password)) {
        Response::error('Username, Name, and Password are required');
    }

    if (!in_array($role, ['owner', 'manager', 'sales', 'accountant'], true)) {
        Response::error('Invalid user role selected');
    }

    // Check duplicate username
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        Response::error('Username already taken. Please choose a different username.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$username, $name, $email, $hash, $role]);
    $newId = (int)$pdo->lastInsertId();

    Audit::log('create', 'user', (string)$newId, "Created user {$username} with role {$role}");

    Response::success("User '{$username}' created successfully", [
        'id'       => $newId,
        'username' => $username,
        'name'     => $name,
        'role'     => $role,
        'status'   => 'active'
    ]);
} else {
    Response::error('Method not allowed', 405);
}
