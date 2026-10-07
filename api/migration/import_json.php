<?php
/**
 * Prime E Commerce Hub - Legacy database.json Migration Importer
 * Migrates client data from desktop JSON format into MySQL relational database
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
if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
    $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
} else {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (isset($decoded['json_data'])) {
        $jsonContent = is_string($decoded['json_data']) ? $decoded['json_data'] : json_encode($decoded['json_data']);
    } else {
        $jsonContent = $raw;
    }
}

if (empty($jsonContent)) {
    Response::error('No file or data provided for migration');
}

$legacy = json_decode($jsonContent, true);
if (!is_array($legacy)) {
    Response::error('Invalid JSON: File could not be parsed');
}

$pdo = Database::getConnection();

$report = [
    'products_imported'  => 0,
    'customers_imported' => 0,
    'sales_imported'     => 0,
    'items_imported'     => 0,
    'investors_imported' => 0,
    'payments_imported'  => 0,
    'closings_imported'  => 0,
    'logs_imported'      => 0,
    'skipped_duplicates' => 0,
    'errors'             => []
];

try {
    $pdo->beginTransaction();

    // 1. Migrate Investors First
    $investorIdMap = []; // old_id => new_id
    if (!empty($legacy['investors']) && is_array($legacy['investors'])) {
        $checkInv = $pdo->prepare("SELECT id FROM investors WHERE name = ?");
        $insInv = $pdo->prepare("INSERT INTO investors (name, investment_amount, notes, status, created_at) VALUES (?, ?, ?, 'active', ?)");

        foreach ($legacy['investors'] as $inv) {
            $oldId = $inv['id'] ?? null;
            $name = trim($inv['name'] ?? '');
            if (!$name) continue;

            $checkInv->execute([$name]);
            $existingId = $checkInv->fetchColumn();

            if ($existingId) {
                $investorIdMap[$oldId] = (int)$existingId;
                $report['skipped_duplicates']++;
            } else {
                $capital = (float)($inv['investment_amount'] ?? $inv['capital'] ?? 0);
                $notes = $inv['notes'] ?? null;
                $createdAt = !empty($inv['created_at']) ? date('Y-m-d H:i:s', strtotime($inv['created_at'])) : date('Y-m-d H:i:s');

                $insInv->execute([$name, $capital, $notes, $createdAt]);
                $newId = (int)$pdo->lastInsertId();
                if ($oldId !== null) {
                    $investorIdMap[$oldId] = $newId;
                }
                $report['investors_imported']++;
            }
        }
    }

    // 2. Migrate Products
    $productIdMap = []; // old_id => new_id
    if (!empty($legacy['products']) && is_array($legacy['products'])) {
        $checkProd = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $insProd = $pdo->prepare("
            INSERT INTO products (sku, name, category, purchase_price, selling_price, stock_quantity, low_stock_threshold, investor_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
        ");

        foreach ($legacy['products'] as $p) {
            $oldId = $p['id'] ?? null;
            $sku = trim($p['sku'] ?? '');
            $name = trim($p['name'] ?? '');
            if (!$sku || !$name) continue;

            $checkProd->execute([$sku]);
            $existingId = $checkProd->fetchColumn();

            if ($existingId) {
                $productIdMap[$oldId] = (int)$existingId;
                $report['skipped_duplicates']++;
            } else {
                $cat = $p['category'] ?? 'General';
                $buyPrice = (float)($p['purchase_price'] ?? 0);
                $sellPrice = (float)($p['selling_price'] ?? 0);
                $stock = (int)($p['stock_quantity'] ?? 0);
                $threshold = (int)($p['low_stock_threshold'] ?? 5);
                $oldInvId = $p['investor_id'] ?? null;
                $newInvId = $oldInvId && isset($investorIdMap[$oldInvId]) ? $investorIdMap[$oldInvId] : null;
                $createdAt = !empty($p['created_at']) ? date('Y-m-d H:i:s', strtotime($p['created_at'])) : date('Y-m-d H:i:s');

                $insProd->execute([$sku, $name, $cat, $buyPrice, $sellPrice, $stock, $threshold, $newInvId, $createdAt]);
                $newId = (int)$pdo->lastInsertId();
                if ($oldId !== null) {
                    $productIdMap[$oldId] = $newId;
                }
                $report['products_imported']++;
            }
        }
    }

    // 3. Migrate Customers
    $customerIdMap = []; // old_id => new_id
    if (!empty($legacy['customers']) && is_array($legacy['customers'])) {
        $checkCust = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
        $insCust = $pdo->prepare("
            INSERT INTO customers (name, phone, email, address, total_visits, total_spent, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
        ");

        foreach ($legacy['customers'] as $c) {
            $oldId = $c['id'] ?? null;
            $name = trim($c['name'] ?? '');
            $phone = trim($c['phone'] ?? '');
            if (!$name || !$phone) continue;

            $checkCust->execute([$phone]);
            $existingId = $checkCust->fetchColumn();

            if ($existingId) {
                $customerIdMap[$oldId] = (int)$existingId;
                $report['skipped_duplicates']++;
            } else {
                $email = $c['email'] ?? null;
                $address = $c['address'] ?? null;
                $visits = (int)($c['total_visits'] ?? 0);
                $spent = (float)($c['total_spent'] ?? 0);
                $createdAt = !empty($c['created_at']) ? date('Y-m-d H:i:s', strtotime($c['created_at'])) : date('Y-m-d H:i:s');

                $insCust->execute([$name, $phone, $email, $address, $visits, $spent, $createdAt]);
                $newId = (int)$pdo->lastInsertId();
                if ($oldId !== null) {
                    $customerIdMap[$oldId] = $newId;
                }
                $report['customers_imported']++;
            }
        }
    }

    // 4. Migrate Sales & Nested Items
    if (!empty($legacy['sales']) && is_array($legacy['sales'])) {
        $checkSale = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = ?");
        $insSale = $pdo->prepare("
            INSERT INTO sales (
                invoice_no, sale_date, customer_id, customer_name, platform,
                payment_method, payment_status, delivery_status, subtotal,
                discount, tax_percent, tax_amount, grand_total, paid_amount,
                total_cost, net_profit, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insItem = $pdo->prepare("
            INSERT INTO sale_items (
                sale_id, product_id, product_sku, product_name, investor_id,
                purchase_price, selling_price, quantity, subtotal, total_cost, profit
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($legacy['sales'] as $s) {
            $invNo = trim($s['invoice_no'] ?? $s['invoiceNo'] ?? '');
            if (!$invNo) continue;

            $checkSale->execute([$invNo]);
            if ($checkSale->fetch()) {
                $report['skipped_duplicates']++;
                continue;
            }

            $date = !empty($s['sale_date']) ? $s['sale_date'] : ($s['datetime'] ?? date('Y-m-d H:i:s'));
            $dateStr = date('Y-m-d H:i:s', strtotime($date));

            $oldCustId = $s['customer_id'] ?? null;
            $newCustId = $oldCustId && isset($customerIdMap[$oldCustId]) ? $customerIdMap[$oldCustId] : null;
            $custName = $s['customer_name'] ?? $s['customerName'] ?? 'Guest Customer';

            $subtotal = (float)($s['subtotal'] ?? 0);
            $discount = (float)($s['discount'] ?? 0);
            $taxPercent = (float)($s['tax_percent'] ?? 0);
            $taxAmt = (float)($s['tax_amount'] ?? $s['tax'] ?? 0);
            $grandTotal = (float)($s['grand_total'] ?? $s['grandTotal'] ?? 0);
            $paidAmt = (float)($s['paid_amount'] ?? $grandTotal);
            $profit = (float)($s['net_profit'] ?? $s['profit'] ?? 0);
            $cost = $grandTotal - $profit;

            $platform = $s['platform'] ?? 'Manual';
            $paymentMethod = $s['payment_method'] ?? 'Cash';
            $paymentStatus = $s['payment_status'] ?? 'Paid';
            $deliveryStatus = $s['delivery_status'] ?? 'Delivered';

            $insSale->execute([
                $invNo, $dateStr, $newCustId, $custName, $platform,
                $paymentMethod, $paymentStatus, $deliveryStatus, $subtotal,
                $discount, $taxPercent, $taxAmt, $grandTotal, $paidAmt,
                $cost, $profit, Auth::id(), $dateStr
            ]);
            $newSaleId = (int)$pdo->lastInsertId();
            $report['sales_imported']++;

            // Import nested items
            if (!empty($s['items']) && is_array($s['items'])) {
                foreach ($s['items'] as $it) {
                    $oldProdId = $it['productId'] ?? $it['product_id'] ?? null;
                    $newProdId = $oldProdId && isset($productIdMap[$oldProdId]) ? $productIdMap[$oldProdId] : null;

                    $prodName = $it['name'] ?? $it['product_name'] ?? 'Product';
                    $buyP = (float)($it['purchasePrice'] ?? $it['purchase_price'] ?? 0);
                    $sellP = (float)($it['sellingPrice'] ?? $it['selling_price'] ?? 0);
                    $qty = (int)($it['qty'] ?? $it['quantity'] ?? 1);
                    $itSub = (float)($it['subtotal'] ?? ($sellP * $qty));
                    $itProfit = (float)($it['profit'] ?? (($sellP - $buyP) * $qty));
                    $itCost = $itSub - $itProfit;

                    $insItem->execute([
                        $newSaleId, $newProdId, "SKU-{$newProdId}", $prodName, null,
                        $buyP, $sellP, $qty, $itSub, $itCost, $itProfit
                    ]);
                    $report['items_imported']++;
                }
            }
        }
    }

    // 5. Customer Payments
    if (!empty($legacy['customerPayments']) && is_array($legacy['customerPayments'])) {
        $insPay = $pdo->prepare("
            INSERT INTO customer_payments (customer_id, amount, payment_method, reference, notes, payment_date, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($legacy['customerPayments'] as $cp) {
            $oldCustId = $cp['customer_id'] ?? null;
            $newCustId = $oldCustId && isset($customerIdMap[$oldCustId]) ? $customerIdMap[$oldCustId] : null;
            if (!$newCustId) continue;

            $amt = (float)($cp['amount'] ?? 0);
            $payDate = !empty($cp['payment_date']) ? date('Y-m-d H:i:s', strtotime($cp['payment_date'])) : date('Y-m-d H:i:s');

            $insPay->execute([
                $newCustId, $amt, $cp['payment_method'] ?? 'Cash',
                $cp['reference'] ?? null, $cp['notes'] ?? null,
                $payDate, Auth::id(), $payDate
            ]);
            $report['payments_imported']++;
        }
    }

    // 6. Weekly Closings
    if (!empty($legacy['weeklyClosings']) && is_array($legacy['weeklyClosings'])) {
        $insClosing = $pdo->prepare("
            INSERT INTO weekly_closings (
                closing_code, start_date, end_date, sales_volume, total_revenue,
                paid_amount, outstanding_payments, total_cost, total_profit,
                closing_summary, closed_by, closed_on, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $checkClose = $pdo->prepare("SELECT id FROM weekly_closings WHERE closing_code = ?");

        foreach ($legacy['weeklyClosings'] as $wc) {
            $code = $wc['closing_code'] ?? '';
            if (!$code) continue;

            $checkClose->execute([$code]);
            if ($checkClose->fetch()) {
                $report['skipped_duplicates']++;
                continue;
            }

            $start = $wc['start_date'] ?? date('Y-m-d');
            $end = $wc['end_date'] ?? date('Y-m-d');
            $vol = (int)($wc['sales_volume'] ?? 0);
            $rev = (float)($wc['total_revenue'] ?? 0);
            $paid = (float)($wc['paid_amount'] ?? $rev);
            $out = (float)($wc['outstanding_payments'] ?? 0);
            $prof = (float)($wc['total_profit'] ?? 0);
            $cost = max(0, $rev - $prof);
            $closedOn = !empty($wc['closed_on']) ? date('Y-m-d H:i:s', strtotime($wc['closed_on'])) : date('Y-m-d H:i:s');

            $insClosing->execute([
                $code, $start, $end, $vol, $rev, $paid, $out, $cost, $prof,
                $wc['closing_summary'] ?? null, Auth::id(), $closedOn, $closedOn
            ]);
            $report['closings_imported']++;
        }
    }

    // 7. Inventory Logs
    if (!empty($legacy['inventoryLogs']) && is_array($legacy['inventoryLogs'])) {
        $insLog = $pdo->prepare("
            INSERT INTO inventory_transactions (
                product_id, product_sku, product_name, transaction_type,
                quantity_change, previous_stock, new_stock_level, reason, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($legacy['inventoryLogs'] as $il) {
            $oldProdId = $il['product_id'] ?? null;
            $newProdId = $oldProdId && isset($productIdMap[$oldProdId]) ? $productIdMap[$oldProdId] : null;
            if (!$newProdId) continue;

            $sku = $il['productSku'] ?? '';
            $name = $il['productName'] ?? '';
            $txType = in_array($il['transaction_type'] ?? '', ['ADD_STOCK', 'SALE', 'RETURN_RTO', 'ADJUSTMENT']) ? $il['transaction_type'] : 'ADJUSTMENT';
            $change = (int)($il['quantity_change'] ?? 0);
            $newLevel = (int)($il['new_stock_level'] ?? 0);
            $prev = $newLevel - $change;
            $reason = $il['reason'] ?? 'Imported log';
            $logDate = !empty($il['created_at']) ? date('Y-m-d H:i:s', strtotime($il['created_at'])) : date('Y-m-d H:i:s');

            $insLog->execute([
                $newProdId, $sku, $name, $txType,
                $change, $prev, $newLevel, $reason, Auth::id(), $logDate
            ]);
            $report['logs_imported']++;
        }
    }

    // 8. Settings
    if (!empty($legacy['settings']) && is_array($legacy['settings'])) {
        $insSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if (isset($legacy['settings']['storeName'])) $insSet->execute(['store_name', $legacy['settings']['storeName']]);
        if (isset($legacy['settings']['storeTagline'])) $insSet->execute(['store_tagline', $legacy['settings']['storeTagline']]);
        if (isset($legacy['settings']['storePhone'])) $insSet->execute(['store_phone', $legacy['settings']['storePhone']]);
        if (isset($legacy['settings']['storeEmail'])) $insSet->execute(['store_email', $legacy['settings']['storeEmail']]);
        if (isset($legacy['settings']['storeAddress'])) $insSet->execute(['store_address', $legacy['settings']['storeAddress']]);
        if (isset($legacy['settings']['currencySymbol'])) $insSet->execute(['currency_symbol', $legacy['settings']['currencySymbol']]);
        if (isset($legacy['settings']['defaultTax'])) $insSet->execute(['default_tax', $legacy['settings']['defaultTax']]);
    }

    Audit::log('migrate_json', 'system', null, "Imported legacy database.json: {$report['products_imported']} products, {$report['customers_imported']} customers, {$report['sales_imported']} sales, {$report['investors_imported']} investors");

    $pdo->commit();

    Response::success('Migration from database.json completed successfully', $report);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Migration error: " . $e->getMessage());
    Response::error('Migration failed: ' . $e->getMessage(), 500, $report);
}
