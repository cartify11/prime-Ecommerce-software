<?php
/**
 * Prime E Commerce Hub - Inventory Overview Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

Auth::requirePermission('inventory_view');

$pdo = Database::getConnection();

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$filter   = trim($_GET['filter'] ?? '');

$sql = "
    SELECT 
        p.id,
        p.sku,
        p.name,
        p.category,
        p.purchase_price,
        p.selling_price,
        p.stock_quantity,
        p.low_stock_threshold,
        p.investor_id,
        i.name as investor_name,
        CASE 
            WHEN p.stock_quantity = 0 THEN 'Out of Stock'
            WHEN p.stock_quantity <= p.low_stock_threshold THEN 'Low Stock Alert'
            ELSE 'Healthy'
        END as stock_status
    FROM products p
    LEFT JOIN investors i ON p.investor_id = i.id
    WHERE p.status = 'active'
";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.sku LIKE ? OR p.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category !== '' && $category !== 'All') {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

if ($filter === 'Low') {
    $sql .= " AND p.stock_quantity <= p.low_stock_threshold";
} elseif ($filter === 'Out') {
    $sql .= " AND p.stock_quantity = 0";
}

$sql .= " ORDER BY p.stock_quantity ASC, p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

Response::success('Inventory records loaded', $items);
