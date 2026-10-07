<?php
/**
 * Prime E Commerce Hub - Authentication Status Endpoint
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

$isLoggedIn = Auth::check();
$user = $isLoggedIn ? Auth::user() : null;
$role = $user['role'] ?? null;
$permissions = $role ? Auth::loadPermissions($role) : [];

Response::json([
    'success'     => true,
    'logged_in'   => $isLoggedIn,
    'user'        => $user,
    'role'        => $role,
    'permissions' => $permissions,
    'csrf_token'  => Security::getCsrfToken()
]);
