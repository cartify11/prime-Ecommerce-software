<?php
/**
 * Prime E Commerce Hub - Extended Reports and Analytics Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

Auth::requirePermission('reports_view');

$type      = trim($_GET['type'] ?? 'date_range');
$startDate = trim($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));

$pdo = Database::getConnection();

if ($type === 'date_range') {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(sale_date) as report_date,
            COUNT(*) as orders_count,
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(discount), 0) as discount,
            COALESCE(SUM(tax_amount), 0) as tax,
            COALESCE(SUM(grand_total), 0) as grand_total,
            COALESCE(SUM(paid_amount), 0) as paid_amount,
            COALESCE(SUM(total_cost), 0) as total_cogs,
            COALESCE(SUM(net_profit), 0) as net_profit
        FROM sales
        WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ?
          AND delivery_status != 'Returned / Failed Delivery'
        GROUP BY DATE(sale_date)
        ORDER BY report_date DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();
    Response::success('Date range report loaded', $rows);
} elseif ($type === 'product_sales') {
    $stmt = $pdo->prepare("
        SELECT 
            si.product_sku,
            si.product_name,
            SUM(si.quantity) as units_sold,
            SUM(si.subtotal) as gross_sales,
            SUM(si.total_cost) as total_cost,
            SUM(si.profit) as net_profit
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.sale_date) >= ? AND DATE(s.sale_date) <= ?
          AND s.delivery_status != 'Returned / Failed Delivery'
        GROUP BY si.product_id, si.product_sku, si.product_name
        ORDER BY units_sold DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();
    Response::success('Product sales report loaded', $rows);
} elseif ($type === 'customer_outstanding') {
    $stmt = $pdo->query("
        SELECT 
            c.id, c.name, c.phone, c.email,
            COALESCE((
                SELECT SUM(s.grand_total - s.paid_amount)
                FROM sales s 
                WHERE s.customer_id = c.id 
                  AND s.delivery_status != 'Returned / Failed Delivery'
                  AND s.payment_status != 'Paid'
            ), 0.00) as unpaid_sales,
            COALESCE((
                SELECT SUM(cp.amount)
                FROM customer_payments cp
                WHERE cp.customer_id = c.id
            ), 0.00) as direct_payments
        FROM customers c
        WHERE c.status = 'active'
        HAVING (unpaid_sales - direct_payments) > 0
        ORDER BY (unpaid_sales - direct_payments) DESC
    ");
    $rows = $stmt->fetchAll();
    $result = array_map(function($r) {
        $r['outstanding_balance'] = (float)$r['unpaid_sales'] - (float)$r['direct_payments'];
        return $r;
    }, $rows);
    Response::success('Customer outstanding report loaded', $result);
} else {
    Response::error('Invalid report type requested');
}
