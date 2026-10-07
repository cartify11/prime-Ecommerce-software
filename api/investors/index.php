<?php
/**
 * Prime E Commerce Hub - Investors List & Create Endpoint
 * Computes live investor metrics, active inventory exposure, sales, and net profit
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requirePermission('investors_view');

    $stmt = $pdo->query("SELECT * FROM investors WHERE status = 'active' ORDER BY name ASC");
    $investors = $stmt->fetchAll();

    // Calculate metrics for each investor
    $enriched = [];
    foreach ($investors as $inv) {
        $invId = (int)$inv['id'];
        $capital = (float)$inv['investment_amount'];

        // 1. Active stock cost = sum(product.stock_quantity * product.purchase_price) for linked active products
        $stockStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as sponsored_count,
                COALESCE(SUM(stock_quantity * purchase_price), 0.00) as active_stock_cost
            FROM products 
            WHERE investor_id = ? AND status = 'active'
        ");
        $stockStmt->execute([$invId]);
        $stockRow = $stockStmt->fetch();

        $activeStockCost = (float)$stockRow['active_stock_cost'];
        $remainingCash = $capital - $activeStockCost;

        // 2. Sales and profits generated (excluding Returned orders!)
        $salesStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(si.subtotal), 0.00) as sales_generated,
                COALESCE(SUM(si.total_cost), 0.00) as cost_of_sold,
                COALESCE(SUM(si.profit), 0.00) as profit_generated
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE (si.investor_id = ? OR si.product_id IN (SELECT id FROM products WHERE investor_id = ?))
              AND s.delivery_status != 'Returned / Failed Delivery'
        ");
        $salesStmt->execute([$invId, $invId]);
        $salesRow = $salesStmt->fetch();

        $inv['sponsored_products_count'] = (int)$stockRow['sponsored_count'];
        $inv['active_stock_cost']        = $activeStockCost;
        $inv['remaining_cash']           = $remainingCash;
        $inv['sales_generated']          = (float)$salesRow['sales_generated'];
        $inv['cost_of_sold']             = (float)$salesRow['cost_of_sold'];
        $inv['profit_generated']          = (float)$salesRow['profit_generated'];

        $enriched[] = $inv;
    }

    Response::success('Investors loaded', $enriched);
} elseif ($method === 'POST') {
    Auth::requirePermission('investors_manage');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $name    = Security::sanitizeString($data['name'] ?? '');
    $capital = (float)($data['capital'] ?? $data['investment_amount'] ?? 0);
    $notes   = Security::sanitizeString($data['notes'] ?? '');

    if (empty($name)) {
        Response::error('Investor name is required');
    }

    if ($capital < 0) {
        Response::error('Capital amount cannot be negative');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO investors (name, investment_amount, notes, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$name, $capital, $notes ?: null]);
        $newId = (int)$pdo->lastInsertId();

        Audit::log('create', 'investor', (string)$newId, "Registered investor {$name} with capital {$capital}");

        Response::success("Investor '{$name}' registered successfully", [
            'id'                => $newId,
            'name'              => $name,
            'investment_amount' => $capital
        ]);
    } catch (Exception $e) {
        error_log("Investor create error: " . $e->getMessage());
        Response::error('Failed to create investor account', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
