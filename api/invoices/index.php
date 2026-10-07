<?php
/**
 * Prime E Commerce Hub - Invoices Registry Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';

Auth::requirePermission('invoices_view');

$pdo = Database::getConnection();

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');
$limit  = max(10, min(200, (int)($_GET['limit'] ?? 100)));

$sql = "
    SELECT 
        s.id,
        s.invoice_no,
        s.sale_date,
        s.customer_name,
        s.subtotal,
        s.discount,
        s.tax_amount,
        s.grand_total,
        s.payment_method,
        s.payment_status,
        s.delivery_status,
        COALESCE(SUM(si.quantity), 0) as total_items_count
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.invoice_no LIKE ? OR s.customer_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($date !== '') {
    $sql .= " AND DATE(s.sale_date) = ?";
    $params[] = $date;
}

$sql .= " GROUP BY s.id ORDER BY s.sale_date DESC, s.id DESC LIMIT {$limit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

Response::success('Invoices loaded', $invoices);
