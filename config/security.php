<?php
/**
 * Prime E Commerce Hub - Security and Protection Functions
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

class Security {
    /**
     * Generate or return current CSRF token
     */
    public static function getCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token from header or post body
     */
    public static function verifyCsrfToken(?string $token = null): bool {
        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
            if (!$token) {
                // Check in raw JSON input
                $raw = file_get_contents('php://input');
                if ($raw) {
                    $json = json_decode($raw, true);
                    if (is_array($json) && isset($json['csrf_token'])) {
                        $token = $json['csrf_token'];
                    }
                }
            }
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Check brute force attempts for IP and username
     */
    public static function isRateLimited(string $username, string $ip): bool {
        try {
            $pdo = Database::getConnection();
            $cutoff = time() - 900; // 15 minutes window
            
            // Delete old attempts
            $cleanStmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
            $cleanStmt->execute([$cutoff]);

            // Count recent attempts
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE (ip_address = ? OR username = ?) AND attempt_time >= ?");
            $stmt->execute([$ip, $username, $cutoff]);
            $attempts = (int)$stmt->fetchColumn();

            return $attempts >= 5; // Lock after 5 failed attempts in 15 mins
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Record a failed login attempt
     */
    public static function recordFailedLogin(string $username, string $ip): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, ?, ?)");
            $stmt->execute([$ip, $username, time()]);
        } catch (Exception $e) {
            // Silently continue
        }
    }

    /**
     * Clear failed login attempts on successful authentication
     */
    public static function clearLoginAttempts(string $username, string $ip): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
            $stmt->execute([$ip, $username]);
        } catch (Exception $e) {
            // Silently continue
        }
    }

    /**
     * Escape string for HTML output (XSS defense)
     */
    public static function e(?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize input strings
     */
    public static function sanitizeString(?string $str): string {
        return trim(strip_tags($str ?? ''));
    }

    /**
     * Get client IP address
     */
    public static function getClientIp(): string {
        return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
