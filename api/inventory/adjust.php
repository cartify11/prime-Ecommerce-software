<?php
/**
 * Prime E Commerce Hub - Inventory Adjustment Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::requirePermission('inventory_adjust');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$productId      = (int)($data['product_id'] ?? 0);
$adjustmentType = $data['adjustment_type'] ?? 'add'; // 'add', 'subtract', 'set'
$quantity       = (int)($data['quantity'] ?? 0);
$reason         = Security::sanitizeString($data['reason'] ?? 'Manual stock adjustment') ?: 'Manual stock adjustment';

if ($productId <= 0) {
    Response::error('Please select a product');
}

if ($quantity < 0) {
    Response::error('Quantity value cannot be negative');
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $pdo->rollBack();
        Response::notFound('Product not found');
    }

    $currentStock = (int)$product['stock_quantity'];
    $change = 0;
    $newStock = $currentStock;

    if ($adjustmentType === 'add') {
        if ($quantity <= 0) {
            $pdo->rollBack();
            Response::error('Quantity to add must be greater than zero');
        }
        $change = $quantity;
        $newStock = $currentStock + $quantity;
        $txType = 'ADD_STOCK';
    } elseif ($adjustmentType === 'subtract') {
        if ($quantity <= 0) {
            $pdo->rollBack();
            Response::error('Quantity to deduct must be greater than zero');
        }
        if ($quantity > $currentStock) {
            $pdo->rollBack();
            Response::error("Cannot deduct {$quantity} units. Current stock is only {$currentStock} units.");
        }
        $change = -$quantity;
        $newStock = $currentStock - $quantity;
        $txType = 'ADJUSTMENT';
    } elseif ($adjustmentType === 'set') {
        $change = $quantity - $currentStock;
        $newStock = $quantity;
        $txType = 'ADJUSTMENT';
    } else {
        $pdo->rollBack();
        Response::error('Invalid adjustment type specified');
    }

    // Update stock quantity
    $upStmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
    $upStmt->execute([$newStock, $productId]);

    // Insert transaction record
    $logStmt = $pdo->prepare("
        INSERT INTO inventory_transactions (
            product_id, product_sku, product_name, transaction_type,
            quantity_change, previous_stock, new_stock_level, reason, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $productId, $product['sku'], $product['name'], $txType,
        $change, $currentStock, $newStock, $reason, Auth::id()
    ]);

    Audit::log('stock_adjust', 'product', (string)$productId, "Adjusted stock for {$product['sku']} ({$product['name']}) by {$change} units to {$newStock}. Reason: {$reason}");

    $pdo->commit();

    Response::success("Stock updated successfully for {$product['name']}. New level: {$newStock} units.", [
        'product_id' => $productId,
        'new_stock'  => $newStock,
        'change'     => $change
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Stock adjust error: " . $e->getMessage());
    Response::error('Failed to adjust stock', 500);
}
