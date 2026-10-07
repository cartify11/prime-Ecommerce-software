<?php
/**
 * Prime E Commerce Hub - Investor Management & Sponsored Products Statement
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

$investorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($investorId <= 0) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    $investorId = (int)($data['id'] ?? 0);
}

if ($investorId <= 0) {
    Response::error('Valid Investor ID is required');
}

if ($method === 'GET') {
    Auth::requirePermission('investors_view');

    // 1. Fetch investor details
    $stmt = $pdo->prepare("SELECT * FROM investors WHERE id = ?");
    $stmt->execute([$investorId]);
    $investor = $stmt->fetch();
    if (!$investor) {
        Response::notFound('Investor account not found');
    }

    // 2. Fetch linked products
    $prodStmt = $pdo->prepare("
        SELECT id, sku, name, category, purchase_price, selling_price, stock_quantity, low_stock_threshold
        FROM products
        WHERE investor_id = ? AND status = 'active'
        ORDER BY name ASC
    ");
    $prodStmt->execute([$investorId]);
    $products = $prodStmt->fetchAll();

    // 3. Calculate detailed stats for each product
    $activeStockCost = 0.0;
    $totalSalesGen = 0.0;
    $totalProfitGen = 0.0;

    foreach ($products as &$p) {
        $pId = (int)$p['id'];
        $stockQty = (int)$p['stock_quantity'];
        $buyPrice = (float)$p['purchase_price'];

        $activeStockCost += ($stockQty * $buyPrice);

        // Calculate sold and returned quantities and profit
        $itemStatsStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN s.delivery_status != 'Returned / Failed Delivery' THEN si.quantity ELSE 0 END), 0) as total_sold,
                COALESCE(SUM(CASE WHEN s.delivery_status = 'Returned / Failed Delivery' THEN si.quantity ELSE 0 END), 0) as total_returned,
                COALESCE(SUM(CASE WHEN s.delivery_status != 'Returned / Failed Delivery' THEN si.subtotal ELSE 0 END), 0) as sales_amount,
                COALESCE(SUM(CASE WHEN s.delivery_status != 'Returned / Failed Delivery' THEN si.profit ELSE 0 END), 0) as net_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE si.product_id = ?
        ");
        $itemStatsStmt->execute([$pId]);
        $stats = $itemStatsStmt->fetch();

        $p['total_sold']     = (int)$stats['total_sold'];
        $p['total_returned'] = (int)$stats['total_returned'];
        $p['sales_amount']   = (float)$stats['sales_amount'];
        $p['profit_gen']     = (float)$stats['net_profit'];

        $totalSalesGen += $p['sales_amount'];
        $totalProfitGen += $p['profit_gen'];
    }
    unset($p);

    $remainingCash = (float)$investor['investment_amount'] - $activeStockCost;

    // Fetch store settings for printable statement
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    Response::success('Investor statement details', [
        'investor'          => $investor,
        'sponsored_products'=> $products,
        'active_stock_cost' => $activeStockCost,
        'remaining_cash'    => $remainingCash,
        'total_sales'       => $totalSalesGen,
        'total_profit'      => $totalProfitGen,
        'settings'          => $settings
    ]);
} elseif ($method === 'DELETE') {
    Auth::requirePermission('investors_manage');

    try {
        $pdo->beginTransaction();

        $invStmt = $pdo->prepare("SELECT name FROM investors WHERE id = ?");
        $invStmt->execute([$investorId]);
        $name = $invStmt->fetchColumn();

        if (!$name) {
            $pdo->rollBack();
            Response::notFound('Investor account not found');
        }

        // Unbind sponsored products (set investor_id to NULL)
        $unbind = $pdo->prepare("UPDATE products SET investor_id = NULL WHERE investor_id = ?");
        $unbind->execute([$investorId]);

        // Soft delete/archive or remove investor
        $del = $pdo->prepare("DELETE FROM investors WHERE id = ?");
        $del->execute([$investorId]);

        Audit::log('delete', 'investor', (string)$investorId, "Deleted investor {$name}. Unlinked sponsored products.");

        $pdo->commit();
        Response::success("Investor '{$name}' deleted. Sponsored products returned to self-funded status.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Investor delete error: " . $e->getMessage());
        Response::error('Failed to delete investor account', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
