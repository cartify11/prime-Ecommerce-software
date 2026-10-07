<?php
/**
 * Prime E Commerce Hub - Secure Password Change Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$currentPassword = $data['current_password'] ?? '';
$newPassword     = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';
$targetUserId    = isset($data['user_id']) ? (int)$data['user_id'] : Auth::id();

if (empty($newPassword) || strlen($newPassword) < 6) {
    Response::error('New password must be at least 6 characters long');
}

if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
    Response::error('Password confirmation does not match');
}

try {
    $pdo = Database::getConnection();

    // If changing own password, verify current password
    if ($targetUserId === Auth::id()) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($currentPassword, $hash)) {
            Response::error('Current password verification failed', 401);
        }
    } else {
        // If changing someone else's password, must have users_manage permission
        Auth::requirePermission('users_manage');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $upStmt->execute([$newHash, $targetUserId]);

    Audit::log('password_change', 'user', (string)$targetUserId, "Password changed for user ID: {$targetUserId}");

    Response::success('Password updated successfully');
} catch (Exception $e) {
    error_log("Password update error: " . $e->getMessage());
    Response::error('Failed to update password', 500);
}
