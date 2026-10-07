<?php
/**
 * Prime E Commerce Hub - Inventory Transaction Logs Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

Auth::requirePermission('inventory_view');

$pdo = Database::getConnection();

$limit = max(10, min(200, (int)($_GET['limit'] ?? 100)));
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

$sql = "
    SELECT 
        it.*,
        u.name as user_name
    FROM inventory_transactions it
    LEFT JOIN users u ON it.created_by = u.id
";

$params = [];
if ($productId > 0) {
    $sql .= " WHERE it.product_id = ?";
    $params[] = $productId;
}

$sql .= " ORDER BY it.created_at DESC, it.id DESC LIMIT {$limit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

Response::success('Inventory logs loaded', $logs);
