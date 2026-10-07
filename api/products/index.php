<?php
/**
 * Prime E Commerce Hub - Products List and Create Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requirePermission('products_view');

    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $stock    = trim($_GET['stock'] ?? '');

    $sql = "
        SELECT p.*, i.name as investor_name 
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

    if ($stock === 'Low') {
        $sql .= " AND p.stock_quantity <= p.low_stock_threshold";
    } elseif ($stock === 'Out') {
        $sql .= " AND p.stock_quantity = 0";
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    Response::success('Products loaded', $products);
} elseif ($method === 'POST') {
    Auth::requirePermission('products_manage');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $sku           = strtoupper(Security::sanitizeString($data['sku'] ?? ''));
    $name          = Security::sanitizeString($data['name'] ?? '');
    $category      = Security::sanitizeString($data['category'] ?? 'General') ?: 'General';
    $purchasePrice = (float)($data['purchase_price'] ?? 0);
    $sellingPrice  = (float)($data['selling_price'] ?? 0);
    $stockQuantity = (int)($data['stock_quantity'] ?? 0);
    $lowStockThresh= isset($data['low_stock_threshold']) ? (int)$data['low_stock_threshold'] : 5;
    $investorId    = !empty($data['investor_id']) ? (int)$data['investor_id'] : null;

    if (empty($sku) || empty($name)) {
        Response::error('SKU and Product Name are required');
    }

    if ($purchasePrice < 0 || $sellingPrice < 0 || $stockQuantity < 0) {
        Response::error('Prices and stock quantity cannot be negative');
    }

    // Check duplicate SKU
    $check = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND status = 'active'");
    $check->execute([$sku]);
    if ($check->fetch()) {
        Response::error("Product with SKU '{$sku}' already exists.");
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO products (sku, name, category, purchase_price, selling_price, stock_quantity, low_stock_threshold, investor_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $sku, $name, $category, $purchasePrice, $sellingPrice, $stockQuantity, $lowStockThresh, $investorId
        ]);
        $newId = (int)$pdo->lastInsertId();

        // Record opening stock transaction log
        if ($stockQuantity > 0) {
            $logStmt = $pdo->prepare("
                INSERT INTO inventory_transactions (product_id, product_sku, product_name, transaction_type, quantity_change, previous_stock, new_stock_level, reason, created_by, created_at)
                VALUES (?, ?, ?, 'OPENING_BALANCE', ?, 0, ?, 'Initial stock setup', ?, NOW())
            ");
            $logStmt->execute([$newId, $sku, $name, $stockQuantity, $stockQuantity, Auth::id()]);
        }

        Audit::log('create', 'product', (string)$newId, "Created product {$sku} ({$name})");

        $pdo->commit();

        Response::success("Product '{$name}' created successfully", ['id' => $newId, 'sku' => $sku]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Product creation error: " . $e->getMessage());
        Response::error('Failed to create product', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
