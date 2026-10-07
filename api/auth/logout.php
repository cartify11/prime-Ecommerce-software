<?php
/**
 * Prime E Commerce Hub - Logout Endpoint
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if (Auth::check()) {
    Audit::log('logout', 'user', (string)Auth::id(), "User " . Auth::username() . " logged out");
    Auth::logout();
}

Response::success('Logged out successfully');
