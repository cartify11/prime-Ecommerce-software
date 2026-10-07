<?php
/**
 * Prime E Commerce Hub - Authentication & RBAC Engine
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth {
    private static ?array $cachedPermissions = null;

    public static function check(): bool {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name'     => $_SESSION['user_name'],
            'email'    => $_SESSION['user_email'] ?? '',
            'role'     => $_SESSION['user_role']
        ];
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function username(): ?string {
        return $_SESSION['username'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function login(array $user): void {
        // Prevent session fixation
        if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['login_time'] = time();

        // Update last login in database
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([(int)$user['id']]);
        } catch (Exception $e) {
            // Silently continue
        }

        self::loadPermissions((string)$user['role'], true);
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function loadPermissions(string $role, bool $forceReload = false): array {
        if (self::$cachedPermissions !== null && !$forceReload) {
            return self::$cachedPermissions;
        }

        if (isset($_SESSION['permissions']) && !$forceReload) {
            self::$cachedPermissions = $_SESSION['permissions'];
            return self::$cachedPermissions;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT permission FROM role_permissions WHERE role = ?");
            $stmt->execute([$role]);
            $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $_SESSION['permissions'] = $perms;
            self::$cachedPermissions = $perms;
            return $perms;
        } catch (Exception $e) {
            return [];
        }
    }

    public static function hasPermission(string $permission): bool {
        if (!self::check()) {
            return false;
        }

        // Owner role has universal access
        if (self::role() === 'owner') {
            return true;
        }

        $perms = self::loadPermissions(self::role());
        return in_array($permission, $perms, true);
    }

    public static function requirePermission(string $permission): void {
        if (!self::check()) {
            Response::unauthorized('Session expired or not logged in.');
        }

        if (!self::hasPermission($permission)) {
            Response::forbidden("Permission '{$permission}' is required to perform this action.");
        }
    }
}
