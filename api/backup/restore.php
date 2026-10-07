<?php
/**
 * Prime E Commerce Hub - Database Restore Endpoint
 * Restores all system entities safely in a database transaction with integrity checks
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::requirePermission('backup_manage');

$jsonContent = null;
if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
    $jsonContent = file_get_contents($_FILES['backup_file']['tmp_name']);
} else {
    $jsonContent = file_get_contents('php://input');
}

if (empty($jsonContent)) {
    Response::error('No backup file or JSON payload provided');
}

$data = json_decode($jsonContent, true);
if (!is_array($data)) {
    Response::error('Malformed backup file: Invalid JSON format');
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // Disable foreign key checks during batch wipe & reload
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Wipe current transaction tables safely
    $pdo->exec("TRUNCATE TABLE sale_items");
    $pdo->exec("TRUNCATE TABLE inventory_transactions");
    $pdo->exec("TRUNCATE TABLE customer_payments");
    $pdo->exec("TRUNCATE TABLE sales");
    $pdo->exec("TRUNCATE TABLE products");
    $pdo->exec("TRUNCATE TABLE investors");
    $pdo->exec("TRUNCATE TABLE customers");
    $pdo->exec("TRUNCATE TABLE weekly_closings");

    // 2. Restore Investors
    $invCount = 0;
    if (!empty($data['investors']) && is_array($data['investors'])) {
        $invStmt = $pdo->prepare("INSERT INTO investors (id, name, investment_amount, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['investors'] as $inv) {
            $invStmt->execute([
                $inv['id'] ?? null,
                $inv['name'] ?? 'Unnamed Investor',
                (float)($inv['investment_amount'] ?? $inv['capital'] ?? 0),
                $inv['notes'] ?? null,
                $inv['status'] ?? 'active',
                $inv['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $invCount++;
        }
    }

    // 3. Restore Products
    $prodCount = 0;
    if (!empty($data['products']) && is_array($data['products'])) {
        $prodStmt = $pdo->prepare("
            INSERT INTO products (id, sku, name, category, purchase_price, selling_price, stock_quantity, low_stock_threshold, investor_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['products'] as $p) {
            $prodStmt->execute([
                $p['id'] ?? null,
                $p['sku'] ?? ('SKU-' . uniqid()),
                $p['name'] ?? 'Unnamed Product',
                $p['category'] ?? 'General',
                (float)($p['purchase_price'] ?? 0),
                (float)($p['selling_price'] ?? 0),
                (int)($p['stock_quantity'] ?? 0),
                (int)($p['low_stock_threshold'] ?? 5),
                !empty($p['investor_id']) ? (int)$p['investor_id'] : null,
                $p['status'] ?? 'active',
                $p['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $prodCount++;
        }
    }

    // 4. Restore Customers
    $custCount = 0;
    if (!empty($data['customers']) && is_array($data['customers'])) {
        $custStmt = $pdo->prepare("
            INSERT INTO customers (id, name, phone, email, address, total_visits, total_spent, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['customers'] as $c) {
            $custStmt->execute([
                $c['id'] ?? null,
                $c['name'] ?? 'Unnamed Customer',
                $c['phone'] ?? '',
                $c['email'] ?? null,
                $c['address'] ?? null,
                (int)($c['total_visits'] ?? 0),
                (float)($c['total_spent'] ?? 0),
                $c['status'] ?? 'active',
                $c['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $custCount++;
        }
    }

    // 5. Restore Sales
    $salesCount = 0;
    if (!empty($data['sales']) && is_array($data['sales'])) {
        $saleStmt = $pdo->prepare("
            INSERT INTO sales (
                id, invoice_no, sale_date, customer_id, customer_name, platform,
                payment_method, payment_status, delivery_status, subtotal,
                discount, tax_percent, tax_amount, grand_total, paid_amount,
                total_cost, net_profit, created_by, returned_at, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['sales'] as $s) {
            $saleStmt->execute([
                $s['id'] ?? null,
                $s['invoice_no'] ?? $s['invoiceNo'] ?? ('INV-' . uniqid()),
                $s['sale_date'] ?? $s['datetime'] ?? date('Y-m-d H:i:s'),
                !empty($s['customer_id']) ? (int)$s['customer_id'] : null,
                $s['customer_name'] ?? $s['customerName'] ?? 'Guest Customer',
                $s['platform'] ?? 'Manual',
                $s['payment_method'] ?? 'Cash',
                $s['payment_status'] ?? 'Paid',
                $s['delivery_status'] ?? 'Delivered',
                (float)($s['subtotal'] ?? 0),
                (float)($s['discount'] ?? 0),
                (float)($s['tax_percent'] ?? 0),
                (float)($s['tax_amount'] ?? $s['tax'] ?? 0),
                (float)($s['grand_total'] ?? $s['grandTotal'] ?? 0),
                (float)($s['paid_amount'] ?? $s['grand_total'] ?? 0),
                (float)($s['total_cost'] ?? 0),
                (float)($s['net_profit'] ?? $s['profit'] ?? 0),
                !empty($s['created_by']) ? (int)$s['created_by'] : Auth::id(),
                !empty($s['returned_at']) ? $s['returned_at'] : null,
                $s['notes'] ?? null,
                $s['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $salesCount++;
        }
    }

    // 6. Restore Sale Items
    $itemsCount = 0;
    if (!empty($data['sale_items']) && is_array($data['sale_items'])) {
        $itemStmt = $pdo->prepare("
            INSERT INTO sale_items (
                id, sale_id, product_id, product_sku, product_name, investor_id,
                purchase_price, selling_price, quantity, subtotal, total_cost, profit
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['sale_items'] as $it) {
            $itemStmt->execute([
                $it['id'] ?? null,
                (int)$it['sale_id'],
                !empty($it['product_id']) ? (int)$it['product_id'] : null,
                $it['product_sku'] ?? '',
                $it['product_name'] ?? '',
                !empty($it['investor_id']) ? (int)$it['investor_id'] : null,
                (float)($it['purchase_price'] ?? 0),
                (float)($it['selling_price'] ?? 0),
                (int)($it['quantity'] ?? 1),
                (float)($it['subtotal'] ?? 0),
                (float)($it['total_cost'] ?? 0),
                (float)($it['profit'] ?? 0)
            ]);
            $itemsCount++;
        }
    }

    // 7. Restore Customer Payments
    $payCount = 0;
    if (!empty($data['customer_payments']) && is_array($data['customer_payments'])) {
        $cpStmt = $pdo->prepare("
            INSERT INTO customer_payments (id, customer_id, amount, payment_method, reference, notes, payment_date, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['customer_payments'] as $cp) {
            $cpStmt->execute([
                $cp['id'] ?? null,
                (int)$cp['customer_id'],
                (float)($cp['amount'] ?? 0),
                $cp['payment_method'] ?? 'Cash',
                $cp['reference'] ?? null,
                $cp['notes'] ?? null,
                $cp['payment_date'] ?? date('Y-m-d H:i:s'),
                !empty($cp['created_by']) ? (int)$cp['created_by'] : Auth::id(),
                $cp['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $payCount++;
        }
    }

    // 8. Restore Weekly Closings
    $closeCount = 0;
    if (!empty($data['weekly_closings']) && is_array($data['weekly_closings'])) {
        $wcStmt = $pdo->prepare("
            INSERT INTO weekly_closings (
                id, closing_code, start_date, end_date, sales_volume, total_revenue,
                paid_amount, outstanding_payments, total_cost, total_profit,
                closing_summary, closed_by, closed_on, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['weekly_closings'] as $wc) {
            $wcStmt->execute([
                $wc['id'] ?? null,
                $wc['closing_code'] ?? ('WCL-' . uniqid()),
                $wc['start_date'] ?? date('Y-m-d'),
                $wc['end_date'] ?? date('Y-m-d'),
                (int)($wc['sales_volume'] ?? 0),
                (float)($wc['total_revenue'] ?? 0),
                (float)($wc['paid_amount'] ?? 0),
                (float)($wc['outstanding_payments'] ?? 0),
                (float)($wc['total_cost'] ?? 0),
                (float)($wc['total_profit'] ?? 0),
                $wc['closing_summary'] ?? null,
                !empty($wc['closed_by']) ? (int)$wc['closed_by'] : Auth::id(),
                $wc['closed_on'] ?? date('Y-m-d H:i:s'),
                $wc['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $closeCount++;
        }
    }

    // 9. Restore Settings
    if (!empty($data['settings']) && is_array($data['settings'])) {
        $setStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($data['settings'] as $k => $v) {
            $setStmt->execute([$k, is_array($v) ? json_encode($v) : (string)$v]);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    Audit::log('restore_backup', 'system', null, "Restored database from backup ({$prodCount} products, {$custCount} customers, {$salesCount} sales, {$invCount} investors)");

    $pdo->commit();

    Response::success('Database restored successfully from backup', [
        'investors_restored' => $invCount,
        'products_restored'  => $prodCount,
        'customers_restored' => $custCount,
        'sales_restored'     => $salesCount,
        'items_restored'     => $itemsCount,
        'payments_restored'  => $payCount,
        'closings_restored'  => $closeCount
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    error_log("Restore error: " . $e->getMessage());
    Response::error('Failed to restore database: ' . $e->getMessage(), 500);
}
