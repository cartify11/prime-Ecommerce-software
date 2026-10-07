<?php
/**
 * Prime E Commerce Hub - Sale Return / RTO Processing Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::requirePermission('sales_return');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$saleId = (int)($data['sale_id'] ?? $data['id'] ?? 0);
if ($saleId <= 0) {
    Response::error('Valid Sale ID is required');
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // 1. Fetch sale with row lock
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();

    if (!$sale) {
        $pdo->rollBack();
        Response::notFound('Sale not found');
    }

    // 2. Prevent duplicate return
    if ($sale['delivery_status'] === 'Returned / Failed Delivery') {
        $pdo->rollBack();
        Response::error("Invoice {$sale['invoice_no']} is already marked as Returned / RTO.");
    }

    // 3. Mark sale as returned
    $upSale = $pdo->prepare("
        UPDATE sales 
        SET delivery_status = 'Returned / Failed Delivery',
            returned_at = NOW(),
            returned_by = ?
        WHERE id = ?
    ");
    $upSale->execute([Auth::id(), $saleId]);

    // 4. Fetch sale items and restore stock
    $itemStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $itemStmt->execute([$saleId]);
    $items = $itemStmt->fetchAll();

    $stockRestoreStmt = $pdo->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity + ? 
        WHERE id = ?
    ");

    $invLogStmt = $pdo->prepare("
        INSERT INTO inventory_transactions (
            product_id, product_sku, product_name, transaction_type,
            quantity_change, previous_stock, new_stock_level, sale_id, reason, created_by, created_at
        ) VALUES (?, ?, ?, 'RETURN_RTO', ?, ?, ?, ?, ?, ?, NOW())
    ");

    $fetchProd = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");

    foreach ($items as $it) {
        if ($it['product_id']) {
            $fetchProd->execute([$it['product_id']]);
            $prevStock = (int)$fetchProd->fetchColumn();

            $stockRestoreStmt->execute([$it['quantity'], $it['product_id']]);
            $newStock = $prevStock + (int)$it['quantity'];

            $invLogStmt->execute([
                $it['product_id'], $it['product_sku'], $it['product_name'],
                $it['quantity'], $prevStock, $newStock, $saleId,
                "RTO Return from invoice {$sale['invoice_no']}", Auth::id()
            ]);
        }
    }

    // 5. Update customer spent if customer was linked
    if ($sale['customer_id']) {
        $custUp = $pdo->prepare("
            UPDATE customers 
            SET total_spent = GREATEST(0, total_spent - ?) 
            WHERE id = ?
        ");
        $custUp->execute([(float)$sale['grand_total'], $sale['customer_id']]);
    }

    Audit::log('rto_return', 'sale', (string)$saleId, "Marked sale {$sale['invoice_no']} as Returned / RTO. Restored stock.");

    $pdo->commit();

    Response::success("Invoice {$sale['invoice_no']} marked as Returned (RTO). Stock restored successfully.");
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Sale return error: " . $e->getMessage());
    Response::error('Failed to process order return: ' . $e->getMessage(), 500);
}
