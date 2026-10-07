<?php
/**
 * Prime E Commerce Hub - Daily Report Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

Auth::requirePermission('reports_view');

$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$pdo = Database::getConnection();

// Summary metrics for the specific day
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(subtotal), 0.00) as subtotal,
        COALESCE(SUM(discount), 0.00) as discount,
        COALESCE(SUM(tax_amount), 0.00) as tax,
        COALESCE(SUM(grand_total), 0.00) as grand_total,
        COALESCE(SUM(total_cost), 0.00) as total_cogs,
        COALESCE(SUM(net_profit), 0.00) as net_profit
    FROM sales
    WHERE DATE(sale_date) = ?
      AND delivery_status != 'Returned / Failed Delivery'
");
$statsStmt->execute([$date]);
$stats = $statsStmt->fetch();

// Sales items for that day
$salesStmt = $pdo->prepare("
    SELECT 
        s.id,
        s.invoice_no,
        s.sale_date,
        s.customer_name,
        s.platform,
        s.payment_method,
        s.payment_status,
        s.delivery_status,
        s.subtotal,
        s.discount,
        s.tax_amount,
        s.grand_total,
        s.net_profit,
        COALESCE(SUM(si.quantity), 0) as items_qty
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE DATE(s.sale_date) = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC, s.id DESC
");
$salesStmt->execute([$date]);
$sales = $salesStmt->fetchAll();

// Product sales breakdown for that day
$prodBreakdownStmt = $pdo->prepare("
    SELECT 
        si.product_sku,
        si.product_name,
        SUM(si.quantity) as qty_sold,
        SUM(si.subtotal) as total_sales,
        SUM(si.profit) as total_profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) = ?
      AND s.delivery_status != 'Returned / Failed Delivery'
    GROUP BY si.product_id, si.product_sku, si.product_name
    ORDER BY qty_sold DESC
");
$prodBreakdownStmt->execute([$date]);
$productBreakdown = $prodBreakdownStmt->fetchAll();

// Settings for reports branding
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

Response::success('Daily report loaded', [
    'date'              => $date,
    'stats'             => $stats,
    'sales'             => $sales,
    'product_breakdown' => $productBreakdown,
    'settings'          => $settings
]);
