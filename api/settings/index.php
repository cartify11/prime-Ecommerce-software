<?php
/**
 * Prime E Commerce Hub - Store Settings Management Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Read settings (accessible by authenticated users)
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Filter out internal flags if any
    Response::success('Settings loaded', $settings);
} elseif ($method === 'POST') {
    Auth::requirePermission('settings_manage');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $allowedKeys = [
        'store_name',
        'store_tagline',
        'store_phone',
        'store_email',
        'store_address',
        'currency_symbol',
        'default_tax',
        'system_theme'
    ];

    try {
        $pdo->beginTransaction();
        $upStmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        $updated = [];
        foreach ($allowedKeys as $key) {
            if (isset($data[$key])) {
                $val = trim((string)$data[$key]);
                $upStmt->execute([$key, $val]);
                $updated[$key] = $val;
            }
        }

        Audit::log('update', 'settings', null, "Updated store settings: " . implode(', ', array_keys($updated)));

        $pdo->commit();

        Response::success('Settings updated successfully', $updated);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Settings update error: " . $e->getMessage());
        Response::error('Failed to update settings', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
