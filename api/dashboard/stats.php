<?php
/**
 * Prime E Commerce Hub - Live Dashboard Analytics & Metrics Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';

Auth::requirePermission('dashboard_view');

$pdo = Database::getConnection();

try {
    // 1. Overall Revenue, Profit, Total Sales (excluding Returned orders)
    $salesStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(grand_total), 0) as total_revenue,
            COALESCE(SUM(net_profit), 0) as net_profit,
            COALESCE(SUM(paid_amount), 0) as total_paid
        FROM sales 
        WHERE delivery_status != 'Returned / Failed Delivery'
    ");
    $salesSummary = $salesStmt->fetch();

    // 2. Today's Sales & Profit
    $today = date('Y-m-d');
    $todayStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(grand_total), 0) as today_sales,
            COALESCE(SUM(net_profit), 0) as today_profit
        FROM sales 
        WHERE DATE(sale_date) = ? AND delivery_status != 'Returned / Failed Delivery'
    ");
    $todayStmt->execute([$today]);
    $todaySummary = $todayStmt->fetch();

    // 3. Weekly Sales (last 7 days)
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $weeklyStmt = $pdo->prepare("
        SELECT COALESCE(SUM(grand_total), 0) as weekly_sales 
        FROM sales 
        WHERE DATE(sale_date) >= ? AND delivery_status != 'Returned / Failed Delivery'
    ");
    $weeklyStmt->execute([$weekAgo]);
    $weeklySales = (float)$weeklyStmt->fetchColumn();

    // 4. Monthly Sales (this calendar month)
    $monthStart = date('Y-m-01');
    $monthlyStmt = $pdo->prepare("
        SELECT COALESCE(SUM(grand_total), 0) as monthly_sales 
        FROM sales 
        WHERE DATE(sale_date) >= ? AND delivery_status != 'Returned / Failed Delivery'
    ");
    $monthlyStmt->execute([$monthStart]);
    $monthlySales = (float)$monthlyStmt->fetchColumn();

    // 5. Inventory Metrics (Cost, Selling, Expected Profit, Counts)
    $invStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_products,
            COALESCE(SUM(CASE WHEN status = 'active' THEN stock_quantity * purchase_price ELSE 0 END), 0) as asset_cost_value,
            COALESCE(SUM(CASE WHEN status = 'active' THEN stock_quantity * selling_price ELSE 0 END), 0) as asset_selling_value,
            COALESCE(SUM(CASE WHEN status = 'active' AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END), 0) as low_stock_count
        FROM products
        WHERE status = 'active'
    ");
    $invSummary = $invStmt->fetch();
    $expectedProfit = (float)$invSummary['asset_selling_value'] - (float)$invSummary['asset_cost_value'];

    // 6. Customers & Orders Counts
    $custStmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'");
    $totalCustomers = (int)$custStmt->fetchColumn();

    $ordersStmt = $pdo->query("SELECT COUNT(*) FROM sales");
    $totalOrders = (int)$ordersStmt->fetchColumn();

    // 7. Payments status summary
    $payStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END), 0) as paid_sum,
            COALESCE(SUM(CASE WHEN payment_status != 'Paid' THEN (grand_total - paid_amount) ELSE 0 END), 0) as pending_sum
        FROM sales
        WHERE delivery_status != 'Returned / Failed Delivery'
    ");
    $paySummary = $payStmt->fetch();

    // 8. 7-Day Trend Chart Data
    $chartStmt = $pdo->query("
        SELECT 
            DATE(sale_date) as sdate,
            COALESCE(SUM(grand_total), 0) as revenue,
            COALESCE(SUM(net_profit), 0) as profit
        FROM sales
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          AND delivery_status != 'Returned / Failed Delivery'
        GROUP BY DATE(sale_date)
        ORDER BY sdate ASC
    ");
    $chartRows = $chartStmt->fetchAll();

    // Format 7 days even if some days had no sales
    $trendLabels = [];
    $trendRevenue = [];
    $trendProfit = [];
    $dateMap = [];
    foreach ($chartRows as $r) {
        $dateMap[$r['sdate']] = $r;
    }
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $trendLabels[] = date('D (M j)', strtotime($d));
        $trendRevenue[] = isset($dateMap[$d]) ? (float)$dateMap[$d]['revenue'] : 0.0;
        $trendProfit[] = isset($dateMap[$d]) ? (float)$dateMap[$d]['profit'] : 0.0;
    }

    // 9. Platform Distribution Chart Data
    $platformStmt = $pdo->query("
        SELECT 
            platform, 
            COUNT(*) as count, 
            COALESCE(SUM(grand_total), 0) as total 
        FROM sales 
        WHERE delivery_status != 'Returned / Failed Delivery' 
        GROUP BY platform
    ");
    $platformRows = $platformStmt->fetchAll();

    // 10. Low stock products for alerts
    $lowStockStmt = $pdo->query("
        SELECT id, sku, name, stock_quantity, low_stock_threshold 
        FROM products 
        WHERE status = 'active' AND stock_quantity <= low_stock_threshold 
        ORDER BY stock_quantity ASC 
        LIMIT 10
    ");
    $lowStockItems = $lowStockStmt->fetchAll();

    Response::success('Dashboard stats loaded', [
        'total_revenue'         => (float)$salesSummary['total_revenue'],
        'net_profit'            => (float)$salesSummary['net_profit'],
        'total_sales_count'     => (int)$salesSummary['total_sales'],
        'low_stock_count'       => (int)$invSummary['low_stock_count'],
        'today_sales'           => (float)$todaySummary['today_sales'],
        'today_profit'          => (float)$todaySummary['today_profit'],
        'weekly_sales'          => (float)$weeklySales,
        'monthly_sales'         => (float)$monthlySales,
        'asset_cost_value'      => (float)$invSummary['asset_cost_value'],
        'asset_selling_value'   => (float)$invSummary['asset_selling_value'],
        'expected_profit'       => (float)$expectedProfit,
        'total_products'        => (int)$invSummary['total_products'],
        'total_customers'       => (int)$totalCustomers,
        'total_orders'          => (int)$totalOrders,
        'paid_payments'         => (float)$paySummary['paid_sum'],
        'pending_payments'      => (float)$paySummary['pending_sum'],
        'chart' => [
            'labels'  => $trendLabels,
            'revenue' => $trendRevenue,
            'profit'  => $trendProfit
        ],
        'platforms' => $platformRows,
        'low_stock_items' => $lowStockItems
    ]);
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    Response::error('Failed to compute dashboard metrics', 500);
}
