<?php
/**
 * Prime E Commerce Hub - Sale Details and Delete Management
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($saleId <= 0) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    $saleId = (int)($data['id'] ?? 0);
}

if ($saleId <= 0) {
    Response::error('Valid Sale ID is required');
}

if ($method === 'GET') {
    Auth::requirePermission('sales_view');

    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            c.name as cust_name, c.phone as cust_phone, c.address as cust_address, c.email as cust_email,
            u.name as cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();

    if (!$sale) {
        Response::notFound('Sale record not found');
    }

    // Fetch items
    $itemStmt = $pdo->prepare("
        SELECT si.*, p.sku as product_sku
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id ASC
    ");
    $itemStmt->execute([$saleId]);
    $sale['items'] = $itemStmt->fetchAll();

    // Fetch settings for invoice branding
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $sale['settings'] = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    Response::success('Sale details loaded', $sale);
} elseif ($method === 'DELETE') {
    Auth::requirePermission('sales_delete');

    try {
        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
        $checkStmt->execute([$saleId]);
        $sale = $checkStmt->fetch();
        if (!$sale) {
            $pdo->rollBack();
            Response::notFound('Sale record not found');
        }

        // If sale was not returned, return stock items before deletion
        if ($sale['delivery_status'] !== 'Returned / Failed Delivery') {
            $itemStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $itemStmt->execute([$saleId]);
            $items = $itemStmt->fetchAll();

            $upStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $logStock = $pdo->prepare("
                INSERT INTO inventory_transactions (
                    product_id, product_sku, product_name, transaction_type,
                    quantity_change, previous_stock, new_stock_level, reason, created_by, created_at
                ) VALUES (?, ?, ?, 'ADD_STOCK', ?, 0, 0, ?, ?, NOW())
            ");

            foreach ($items as $it) {
                if ($it['product_id']) {
                    $upStock->execute([$it['quantity'], $it['product_id']]);
                    $logStock->execute([
                        $it['product_id'], $it['product_sku'], $it['product_name'],
                        $it['quantity'], "Restored from deleted invoice {$sale['invoice_no']}", Auth::id()
                    ]);
                }
            }
        }

        // Delete sale (cascades to sale_items)
        $del = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        $del->execute([$saleId]);

        Audit::log('delete', 'sale', (string)$saleId, "Deleted sale invoice {$sale['invoice_no']}");

        $pdo->commit();
        Response::success("Sale {$sale['invoice_no']} deleted and stock restored.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Sale delete error: " . $e->getMessage());
        Response::error('Failed to delete sale record', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
