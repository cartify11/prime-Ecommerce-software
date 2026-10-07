<?php
/**
 * Prime E Commerce Hub - Logout Page
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/Auth.php';

Auth::logout();
header('Location: ' . BASE_URL . '/login.php');
exit;
