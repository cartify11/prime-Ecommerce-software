<?php
/**
 * Prime E Commerce Hub - Application Configuration
 */

if (!defined('PRIME_APP_LOADED')) {
    define('PRIME_APP_LOADED', true);
}

define('APP_NAME', 'Prime E Commerce Hub');
define('APP_VERSION', '2.0.0');

// Default Timezone
date_default_timezone_set('Asia/Karachi');

// Base URL detection for cPanel or localhost
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim($protocol . $host . $scriptDir, '/');
    define('BASE_URL', $base);
}

// Session security configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '28800'); // 8 hours
    session_name('PRIME_HUB_SESSID');
    session_start();
}
