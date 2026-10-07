<?php
/**
 * Prime E Commerce Hub - Safe Demo Data Seeder Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::requirePermission('demo_manage');

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // 1. Demo Investor
    $invStmt = $pdo->prepare("INSERT INTO investors (name, investment_amount, notes, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
    $invStmt->execute(['Ali Raza (Capital Partner)', 500000.00, 'Seed investor for audio equipment line']);
    $investorId = (int)$pdo->lastInsertId();

    // 2. Demo Products
    $products = [
        ['APP-WCH-01', 'Apple Watch Series 9', 'Electronics', 85000.00, 95000.00, 15, 5, null],
        ['SND-EAR-02', 'Sony WH-1000XM5', 'Audio', 70000.00, 78000.00, 8, 3, $investorId],
        ['LOG-MOU-03', 'Logitech MX Master 3S', 'Accessories', 22000.00, 26000.00, 20, 4, null],
        ['DKS-MEC-04', 'Ducky One 3 Mechanical Keyboard', 'Accessories', 32000.00, 38000.00, 12, 3, null],
        ['ANK-BNK-05', 'Anker 737 Power Bank 24000mAh', 'Power', 28000.00, 34000.00, 25, 6, null],
        ['JBL-FL6-06', 'JBL Flip 6 Portable Speaker', 'Audio', 29000.00, 35000.00, 10, 2, $investorId]
    ];

    $prodInsert = $pdo->prepare("
        INSERT INTO products (sku, name, category, purchase_price, selling_price, stock_quantity, low_stock_threshold, investor_id, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity)
    ");

    $logStmt = $pdo->prepare("
        INSERT INTO inventory_transactions (
            product_id, product_sku, product_name, transaction_type,
            quantity_change, previous_stock, new_stock_level, reason, created_by, created_at
        ) VALUES (?, ?, ?, 'OPENING_BALANCE', ?, 0, ?, 'Demo opening stock', ?, NOW())
    ");

    $createdProdIds = [];
    foreach ($products as $p) {
        $prodInsert->execute($p);
        $prodId = (int)$pdo->lastInsertId();
        if ($prodId) {
            $createdProdIds[$p[0]] = $prodId;
            $logStmt->execute([$prodId, $p[0], $p[1], $p[5], $p[5], Auth::id()]);
        }
    }

    // 3. Demo Customers
    $customers = [
        ['Muhammad Usman', '03001234567', 'usman@example.com', 'House 42, Street 8, F-10, Islamabad'],
        ['Zainab Fatima', '03219876543', 'zainab@example.com', 'Plaza 14, Commercial Market, Rawalpindi'],
        ['Bilal Tariq', '03335557788', 'bilal.t@example.com', 'Sector G-9/2, Islamabad']
    ];

    $custInsert = $pdo->prepare("
        INSERT INTO customers (name, phone, email, address, total_visits, total_spent, status, created_at)
        VALUES (?, ?, ?, ?, 0, 0.00, 'active', NOW())
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");

    $custIds = [];
    foreach ($customers as $c) {
        $custInsert->execute($c);
        $cid = (int)$pdo->lastInsertId();
        if ($cid) $custIds[] = $cid;
    }

    Audit::log('load_demo', 'system', null, "Loaded demo products, customers, and investors");

    $pdo->commit();

    Response::success('Demo data loaded successfully into MySQL database!');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Demo data error: " . $e->getMessage());
    Response::error('Failed to load demo data: ' . $e->getMessage(), 500);
}
