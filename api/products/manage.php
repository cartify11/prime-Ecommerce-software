<?php
/**
 * Prime E Commerce Hub - Product Manage (View, Update, Archive)
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$productId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($data['id'] ?? 0);
if ($productId <= 0) {
    Response::error('Valid Product ID is required');
}

if ($method === 'GET') {
    Auth::requirePermission('products_view');

    $stmt = $pdo->prepare("
        SELECT p.*, i.name as investor_name 
        FROM products p 
        LEFT JOIN investors i ON p.investor_id = i.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        Response::notFound('Product not found');
    }
    Response::success('Product details', $product);
} elseif ($method === 'POST' || $method === 'PUT') {
    Auth::requirePermission('products_manage');

    $sku           = strtoupper(Security::sanitizeString($data['sku'] ?? ''));
    $name          = Security::sanitizeString($data['name'] ?? '');
    $category      = Security::sanitizeString($data['category'] ?? 'General') ?: 'General';
    $purchasePrice = (float)($data['purchase_price'] ?? 0);
    $sellingPrice  = (float)($data['selling_price'] ?? 0);
    $stockQuantity = isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : null;
    $lowStockThresh= isset($data['low_stock_threshold']) ? (int)$data['low_stock_threshold'] : 5;
    $investorId    = !empty($data['investor_id']) ? (int)$data['investor_id'] : null;

    if (empty($sku) || empty($name)) {
        Response::error('SKU and Product Name are required');
    }

    if ($purchasePrice < 0 || $sellingPrice < 0) {
        Response::error('Prices cannot be negative');
    }

    // Check duplicate SKU on other products
    $check = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ? AND status = 'active'");
    $check->execute([$sku, $productId]);
    if ($check->fetch()) {
        Response::error("Another product with SKU '{$sku}' already exists.");
    }

    try {
        $pdo->beginTransaction();

        // Get current product state
        $currStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $currStmt->execute([$productId]);
        $current = $currStmt->fetch();
        if (!$current) {
            $pdo->rollBack();
            Response::notFound('Product not found');
        }

        // Handle stock change if explicitly modified through edit form
        $finalStock = $current['stock_quantity'];
        if ($stockQuantity !== null && $stockQuantity !== (int)$current['stock_quantity']) {
            $diff = $stockQuantity - (int)$current['stock_quantity'];
            $finalStock = $stockQuantity;

            $logStmt = $pdo->prepare("
                INSERT INTO inventory_transactions (product_id, product_sku, product_name, transaction_type, quantity_change, previous_stock, new_stock_level, reason, created_by, created_at)
                VALUES (?, ?, ?, 'ADJUSTMENT', ?, ?, ?, 'Stock updated via Product Edit', ?, NOW())
            ");
            $logStmt->execute([$productId, $sku, $name, $diff, (int)$current['stock_quantity'], $finalStock, Auth::id()]);
        }

        $upStmt = $pdo->prepare("
            UPDATE products 
            SET sku = ?, name = ?, category = ?, purchase_price = ?, selling_price = ?, stock_quantity = ?, low_stock_threshold = ?, investor_id = ?
            WHERE id = ?
        ");
        $upStmt->execute([
            $sku, $name, $category, $purchasePrice, $sellingPrice, $finalStock, $lowStockThresh, $investorId, $productId
        ]);

        Audit::log('update', 'product', (string)$productId, "Updated product {$sku} ({$name})");

        $pdo->commit();
        Response::success("Product '{$name}' updated successfully");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Product update error: " . $e->getMessage());
        Response::error('Failed to update product', 500);
    }
} elseif ($method === 'DELETE') {
    Auth::requirePermission('products_manage');

    try {
        $pdo->beginTransaction();
        // Check if product is referenced in sale_items
        $checkSales = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = ?");
        $checkSales->execute([$productId]);
        $hasSales = (int)$checkSales->fetchColumn() > 0;

        if ($hasSales) {
            // Soft delete/archive to preserve historical invoices & reports
            $stmt = $pdo->prepare("UPDATE products SET status = 'archived' WHERE id = ?");
            $stmt->execute([$productId]);
            Audit::log('archive', 'product', (string)$productId, "Archived product ID {$productId} due to existing sales history");
            $msg = "Product has historical sales records and has been safely archived.";
        } else {
            // Safe hard delete
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            Audit::log('delete', 'product', (string)$productId, "Deleted product ID {$productId}");
            $msg = "Product deleted successfully.";
        }

        $pdo->commit();
        Response::success($msg);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Product delete error: " . $e->getMessage());
        Response::error('Failed to delete product', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
