<?php
/**
 * Prime E Commerce Hub - Database Export / Backup Endpoint
 * Fully includes investors, products, customers, sales, items, payments, closings, logs, and settings
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

Auth::requirePermission('backup_manage');

$pdo = Database::getConnection();

try {
    // 1. Settings
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Investors (Never excluded!)
    $investors = $pdo->query("SELECT * FROM investors ORDER BY id ASC")->fetchAll();

    // 3. Products
    $products = $pdo->query("SELECT * FROM products ORDER BY id ASC")->fetchAll();

    // 4. Customers
    $customers = $pdo->query("SELECT * FROM customers ORDER BY id ASC")->fetchAll();

    // 5. Sales
    $sales = $pdo->query("SELECT * FROM sales ORDER BY id ASC")->fetchAll();

    // 6. Sale Items
    $saleItems = $pdo->query("SELECT * FROM sale_items ORDER BY id ASC")->fetchAll();

    // 7. Customer Payments
    $payments = $pdo->query("SELECT * FROM customer_payments ORDER BY id ASC")->fetchAll();

    // 8. Inventory Transactions
    $invLogs = $pdo->query("SELECT * FROM inventory_transactions ORDER BY id ASC")->fetchAll();

    // 9. Weekly Closings
    $closings = $pdo->query("SELECT * FROM weekly_closings ORDER BY id ASC")->fetchAll();

    $backup = [
        'metadata' => [
            'app'         => APP_NAME,
            'version'     => APP_VERSION,
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => Auth::username()
        ],
        'settings'               => $settings,
        'investors'              => $investors,
        'products'               => $products,
        'customers'              => $customers,
        'sales'                  => $sales,
        'sale_items'             => $saleItems,
        'customer_payments'      => $payments,
        'inventory_transactions' => $invLogs,
        'weekly_closings'        => $closings
    ];

    Audit::log('export_backup', 'system', null, "Exported full database backup");

    $filename = "PrimeHub_Backup_" . date('Y-m-d_His') . ".json";

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    error_log("Backup export error: " . $e->getMessage());
    Response::error('Failed to generate database backup: ' . $e->getMessage(), 500);
}
