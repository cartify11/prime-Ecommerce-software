<?php
/**
 * Prime E Commerce Hub - Customer Ledger Statement Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

Auth::requirePermission('customers_view');

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    Response::error('Valid Customer ID is required');
}

$pdo = Database::getConnection();

// 1. Fetch customer details
$custStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$custStmt->execute([$customerId]);
$customer = $custStmt->fetch();
if (!$customer) {
    Response::notFound('Customer not found');
}

// 2. Fetch sales debits
$salesStmt = $pdo->prepare("
    SELECT 
        s.id,
        s.invoice_no,
        s.sale_date,
        s.platform,
        s.payment_method,
        s.payment_status,
        s.delivery_status,
        s.grand_total,
        s.paid_amount,
        GROUP_CONCAT(CONCAT(si.product_name, ' (x', si.quantity, ')') SEPARATOR ', ') as items_summary
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE s.customer_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date ASC
");
$salesStmt->execute([$customerId]);
$salesRows = $salesStmt->fetchAll();

// 3. Fetch direct customer payments credits
$payStmt = $pdo->prepare("
    SELECT id, amount, payment_method, reference, notes, payment_date
    FROM customer_payments
    WHERE customer_id = ?
    ORDER BY payment_date ASC
");
$payStmt->execute([$customerId]);
$paymentRows = $payStmt->fetchAll();

// 4. Assemble unified chronological ledger entries
$entries = [];

foreach ($salesRows as $s) {
    $grandTotal = (float)$s['grand_total'];
    $paidAmount = (float)$s['paid_amount'];
    $isPaid = $s['payment_status'] === 'Paid';
    $isReturned = $s['delivery_status'] === 'Returned / Failed Delivery';

    if ($isReturned) {
        $desc = "Purchase Returned (RTO): {$s['invoice_no']}";
        $entries[] = [
            'date'    => $s['sale_date'],
            'desc'    => $desc,
            'meta'    => "Status: {$s['delivery_status']} | Items: " . ($s['items_summary'] ?: 'No items'),
            'debit'   => 0.00,
            'credit'  => 0.00,
            'type'    => 'sale_rto'
        ];
    } else {
        $desc = $isPaid ? "Purchase: {$s['invoice_no']} (Paid)" : "Credit Purchase: {$s['invoice_no']}";
        $entries[] = [
            'date'    => $s['sale_date'],
            'desc'    => $desc,
            'meta'    => "Platform: {$s['platform']} | Method: {$s['payment_method']} | Items: " . ($s['items_summary'] ?: 'No items'),
            'debit'   => $grandTotal,
            'credit'  => $isPaid ? $grandTotal : $paidAmount,
            'type'    => 'sale'
        ];
    }
}

foreach ($paymentRows as $p) {
    $note = $p['notes'] ? " | Note: {$p['notes']}" : "";
    $ref = $p['reference'] ? " | Ref: {$p['reference']}" : "";
    $entries[] = [
        'date'   => $p['payment_date'],
        'desc'   => "Payment Received",
        'meta'   => "Method: {$p['payment_method']}{$ref}{$note}",
        'debit'  => 0.00,
        'credit' => (float)$p['amount'],
        'type'   => 'payment'
    ];
}

// Sort by date ascending
usort($entries, function($a, $b) {
    return strtotime($a['date']) <=> strtotime($b['date']);
});

// Compute running balance
$running = 0.0;
$ledgerLines = [];
foreach ($entries as $e) {
    $running += ($e['debit'] - $e['credit']);
    $e['balance'] = $running;
    $ledgerLines[] = $e;
}

// 5. Fetch store settings for ledger branding
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

Response::success('Customer ledger statement loaded', [
    'customer'            => $customer,
    'outstanding_balance' => $running,
    'lines'               => $ledgerLines,
    'settings'            => $settings
]);
