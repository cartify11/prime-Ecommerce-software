<?php
/**
 * Prime E Commerce Hub - Sales Transaction Processing & Listing Endpoint
 * Multi-user concurrency safe with MySQL ACID transactions and server-side validation
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';
require_once __DIR__ . '/../../helpers/NumberSequence.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requirePermission('sales_view');

    $search   = trim($_GET['search'] ?? '');
    $customer = trim($_GET['customer'] ?? '');
    $date     = trim($_GET['date'] ?? '');
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = max(10, min(100, (int)($_GET['limit'] ?? 50)));
    $offset   = ($page - 1) * $limit;

    $sql = "
        SELECT 
            s.*,
            u.name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.created_by = u.id
        WHERE 1=1
    ";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (s.invoice_no LIKE ? OR s.customer_name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($customer !== '' && $customer !== 'All') {
        if ($customer === 'Guest') {
            $sql .= " AND (s.customer_id IS NULL OR s.customer_id = 0)";
        } else {
            $sql .= " AND s.customer_id = ?";
            $params[] = (int)$customer;
        }
    }

    if ($date !== '') {
        $sql .= " AND DATE(s.sale_date) = ?";
        $params[] = $date;
    }

    $sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT {$limit} OFFSET {$offset}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    // Fetch items for each sale
    if (!empty($sales)) {
        $saleIds = array_column($sales, 'id');
        $inClause = implode(',', array_fill(0, count($saleIds), '?'));
        $itemStmt = $pdo->prepare("
            SELECT si.*, p.sku as product_sku
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.id
            WHERE si.sale_id IN ({$inClause})
            ORDER BY si.id ASC
        ");
        $itemStmt->execute($saleIds);
        $itemsGrouped = [];
        while ($row = $itemStmt->fetch()) {
            $itemsGrouped[$row['sale_id']][] = $row;
        }

        foreach ($sales as &$sale) {
            $sale['items'] = $itemsGrouped[$sale['id']] ?? [];
        }
        unset($sale);
    }

    Response::success('Sales loaded', $sales);
} elseif ($method === 'POST') {
    Auth::requirePermission('sales_create');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $customerId    = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    $platform      = Security::sanitizeString($data['platform'] ?? 'Manual') ?: 'Manual';
    $paymentMethod = Security::sanitizeString($data['payment_method'] ?? 'Cash') ?: 'Cash';
    $paymentStatus = in_array($data['payment_status'] ?? '', ['Paid', 'Pending', 'Partial']) ? $data['payment_status'] : 'Paid';
    $discountInput = max(0, (float)($data['discount'] ?? 0));
    $taxPercent    = max(0, (float)($data['tax_percent'] ?? 0));
    $notes         = Security::sanitizeString($data['notes'] ?? '');
    $rawDate       = !empty($data['sale_date']) ? $data['sale_date'] : null;
    $saleDate      = $rawDate ? date('Y-m-d H:i:s', strtotime($rawDate)) : date('Y-m-d H:i:s');
    
    $rawItems = $data['items'] ?? [];
    if (!is_array($rawItems) || empty($rawItems)) {
        Response::error('Sale must contain at least one product item');
    }

    try {
        // Begin Transaction for concurrency and atomic consistency
        $pdo->beginTransaction();

        // 1. Verify customer if selected
        $customerName = 'Guest Customer';
        if ($customerId !== null) {
            $custStmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = ? FOR UPDATE");
            $custStmt->execute([$customerId]);
            $customer = $custStmt->fetch();
            if ($customer) {
                $customerName = $customer['name'];
            } else {
                $customerId = null;
            }
        }

        // 2. Process and validate all items server-side with row-level locks
        $subtotal = 0.0;
        $totalCost = 0.0;
        $verifiedItems = [];

        foreach ($rawItems as $itemInput) {
            $productId = (int)($itemInput['product_id'] ?? $itemInput['productId'] ?? 0);
            $qty = (int)($itemInput['qty'] ?? $itemInput['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                $pdo->rollBack();
                Response::error('Invalid product or quantity specified in sale items');
            }

            // Lock product row to prevent race conditions during concurrent sales
            $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
            $prodStmt->execute([$productId]);
            $product = $prodStmt->fetch();

            if (!$product || $product['status'] !== 'active') {
                $pdo->rollBack();
                Response::error("Selected product (ID: {$productId}) is unavailable or not found.");
            }

            $currentStock = (int)$product['stock_quantity'];
            if ($currentStock < $qty) {
                $pdo->rollBack();
                Response::error("Insufficient stock for '{$product['name']}'. Available: {$currentStock}, Requested: {$qty}");
            }

            // Authoritative server-side prices
            $sellingPrice  = (float)$product['selling_price'];
            $purchasePrice = (float)$product['purchase_price'];
            $lineSubtotal  = round($sellingPrice * $qty, 2);
            $lineCost      = round($purchasePrice * $qty, 2);
            $lineProfit    = round($lineSubtotal - $lineCost, 2);

            $subtotal += $lineSubtotal;
            $totalCost += $lineCost;

            $verifiedItems[] = [
                'product_id'     => $productId,
                'product_sku'    => $product['sku'],
                'product_name'   => $product['name'],
                'investor_id'    => $product['investor_id'],
                'purchase_price' => $purchasePrice,
                'selling_price'  => $sellingPrice,
                'quantity'       => $qty,
                'subtotal'       => $lineSubtotal,
                'total_cost'     => $lineCost,
                'profit'         => $lineProfit,
                'current_stock'  => $currentStock
            ];
        }

        // 3. Authoritative server calculations
        $discountAmount = min($discountInput, $subtotal);
        $taxableAmount  = max(0, $subtotal - $discountAmount);
        $taxAmount      = round($taxableAmount * ($taxPercent / 100), 2);
        $grandTotal     = round($subtotal - $discountAmount + $taxAmount, 2);
        $netProfit      = round($grandTotal - $totalCost, 2);
        
        $paidAmount = ($paymentStatus === 'Paid') ? $grandTotal : (float)($data['paid_amount'] ?? 0);
        if ($paidAmount > $grandTotal) {
            $paidAmount = $grandTotal;
        }

        // 4. Generate unique, concurrency-safe invoice number
        $invoiceNo = NumberSequence::nextInvoiceNo($pdo);

        // 5. Insert Sale Header
        $saleStmt = $pdo->prepare("
            INSERT INTO sales (
                invoice_no, sale_date, customer_id, customer_name, platform,
                payment_method, payment_status, delivery_status, subtotal,
                discount, tax_percent, tax_amount, grand_total, paid_amount,
                total_cost, net_profit, created_by, notes, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, 'Delivered', ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, NOW()
            )
        ");
        $saleStmt->execute([
            $invoiceNo, $saleDate, $customerId, $customerName, $platform,
            $paymentMethod, $paymentStatus, $subtotal,
            $discountAmount, $taxPercent, $taxAmount, $grandTotal, $paidAmount,
            $totalCost, $netProfit, Auth::id(), $notes ?: null
        ]);
        $saleId = (int)$pdo->lastInsertId();

        // 6. Insert Sale Items, Decrement Product Stocks, and Log Transactions
        $itemInsertStmt = $pdo->prepare("
            INSERT INTO sale_items (
                sale_id, product_id, product_sku, product_name, investor_id,
                purchase_price, selling_price, quantity, subtotal, total_cost, profit
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stockUpdateStmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ? AND stock_quantity >= ?
        ");

        $invLogStmt = $pdo->prepare("
            INSERT INTO inventory_transactions (
                product_id, product_sku, product_name, transaction_type,
                quantity_change, previous_stock, new_stock_level, sale_id, reason, created_by, created_at
            ) VALUES (?, ?, ?, 'SALE', ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($verifiedItems as $it) {
            // Insert line item
            $itemInsertStmt->execute([
                $saleId, $it['product_id'], $it['product_sku'], $it['product_name'], $it['investor_id'],
                $it['purchase_price'], $it['selling_price'], $it['quantity'], $it['subtotal'], $it['total_cost'], $it['profit']
            ]);

            // Atomic stock reduction with safety verification
            $stockUpdateStmt->execute([$it['quantity'], $it['product_id'], $it['quantity']]);
            if ($stockUpdateStmt->rowCount() === 0) {
                $pdo->rollBack();
                Response::error("Concurrent purchase conflict: Stock changed for '{$it['product_name']}'. Please retry.");
            }

            $newStock = $it['current_stock'] - $it['quantity'];

            // Log stock movement
            $invLogStmt->execute([
                $it['product_id'], $it['product_sku'], $it['product_name'],
                -$it['quantity'], $it['current_stock'], $newStock, $saleId,
                "Sold via Invoice {$invoiceNo}", Auth::id()
            ]);
        }

        // 7. Update customer statistics if registered customer
        if ($customerId !== null) {
            $custUpStmt = $pdo->prepare("
                UPDATE customers 
                SET total_visits = total_visits + 1, total_spent = total_spent + ?
                WHERE id = ?
            ");
            $custUpStmt->execute([$grandTotal, $customerId]);
        }

        Audit::log('create', 'sale', (string)$saleId, "Created sale invoice {$invoiceNo} (Total: {$grandTotal})");

        // Commit full transaction
        $pdo->commit();

        Response::success("Sale {$invoiceNo} completed successfully", [
            'sale_id'        => $saleId,
            'invoice_no'     => $invoiceNo,
            'grand_total'    => $grandTotal,
            'net_profit'     => $netProfit,
            'payment_status' => $paymentStatus
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Sale creation failed: " . $e->getMessage());
        Response::error('Failed to create sale: ' . $e->getMessage(), 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
