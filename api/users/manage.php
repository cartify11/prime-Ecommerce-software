<?php
/**
 * Prime E Commerce Hub - User Management Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

Auth::requirePermission('users_manage');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;
$method = $_SERVER['REQUEST_METHOD'];
$pdo = Database::getConnection();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($data['id'] ?? 0);
if ($userId <= 0) {
    Response::error('User ID is required');
}

// Prevent modifying or disabling own account through management endpoint
if ($userId === Auth::id() && in_array($method, ['DELETE', 'POST']) && (isset($data['status']) && $data['status'] === 'inactive' || $method === 'DELETE')) {
    Response::error('You cannot deactivate or delete your own logged-in account.');
}

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT id, username, name, email, role, status, last_login, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        Response::notFound('User not found');
    }
    Response::success('User details', $user);
} elseif ($method === 'POST' || $method === 'PUT') {
    $action = $data['action'] ?? 'update';

    if ($action === 'toggle_status') {
        $newStatus = ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        Audit::log('status_change', 'user', (string)$userId, "Changed status to {$newStatus}");
        Response::success("User status updated to {$newStatus}");
    } elseif ($action === 'reset_password') {
        $newPassword = $data['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            Response::error('Password must be at least 6 characters long');
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        Audit::log('admin_reset_password', 'user', (string)$userId, "Admin reset password for user ID {$userId}");
        Response::success('User password reset successfully');
    } else {
        $name  = Security::sanitizeString($data['name'] ?? '');
        $email = Security::sanitizeString($data['email'] ?? '');
        $role  = $data['role'] ?? 'sales';

        if (empty($name)) {
            Response::error('Name is required');
        }
        if (!in_array($role, ['owner', 'manager', 'sales', 'accountant'], true)) {
            Response::error('Invalid user role selected');
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role, $userId]);
        Audit::log('update', 'user', (string)$userId, "Updated user profile for ID {$userId}");
        Response::success('User updated successfully');
    }
} elseif ($method === 'DELETE') {
    // Soft disable or delete if no sales linked
    $salesCheck = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE created_by = ?");
    $salesCheck->execute([$userId]);
    if ((int)$salesCheck->fetchColumn() > 0) {
        // Soft deactivate to preserve audit and transaction history
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$userId]);
        Audit::log('deactivate', 'user', (string)$userId, "Deactivated user ID {$userId} with existing sales");
        Response::success('User has existing sales records and has been safely deactivated instead of deleted.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        Audit::log('delete', 'user', (string)$userId, "Deleted user ID {$userId}");
        Response::success('User deleted successfully');
    }
}
