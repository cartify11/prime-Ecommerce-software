<?php
/**
 * Prime E Commerce Hub - Audit Logger
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/Auth.php';

class Audit {
    public static function log(string $action, string $entityType, ?string $entityId = null, $details = null): void {
        try {
            $pdo = Database::getConnection();
            $userId = Auth::id();
            $username = Auth::username() ?? 'system';
            $ip = Security::getClientIp();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250);

            $detailsStr = is_string($details) ? $details : (is_array($details) || is_object($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null);

            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $userId,
                $username,
                $action,
                $entityType,
                $entityId !== null ? (string)$entityId : null,
                $detailsStr,
                $ip,
                $userAgent
            ]);
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}
