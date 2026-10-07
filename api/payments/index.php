<?php
/**
 * Prime E Commerce Hub - Customer Payments Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requirePermission('payments_record');

    $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    if ($customerId > 0) {
        $stmt = $pdo->prepare("
            SELECT cp.*, u.name as recorded_by_name
            FROM customer_payments cp
            LEFT JOIN users u ON cp.created_by = u.id
            WHERE cp.customer_id = ?
            ORDER BY cp.payment_date DESC
        ");
        $stmt->execute([$customerId]);
    } else {
        $stmt = $pdo->query("
            SELECT cp.*, c.name as customer_name, c.phone as customer_phone, u.name as recorded_by_name
            FROM customer_payments cp
            JOIN customers c ON cp.customer_id = c.id
            LEFT JOIN users u ON cp.created_by = u.id
            ORDER BY cp.payment_date DESC
            LIMIT 100
        ");
    }
    $payments = $stmt->fetchAll();
    Response::success('Payments loaded', $payments);
} elseif ($method === 'POST') {
    Auth::requirePermission('payments_record');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $customerId    = (int)($data['customer_id'] ?? 0);
    $amount        = (float)($data['amount'] ?? 0);
    $paymentMethod = Security::sanitizeString($data['payment_method'] ?? 'Cash') ?: 'Cash';
    $reference     = Security::sanitizeString($data['reference'] ?? '');
    $notes         = Security::sanitizeString($data['notes'] ?? '');
    $paymentDate   = !empty($data['payment_date']) ? date('Y-m-d H:i:s', strtotime($data['payment_date'])) : date('Y-m-d H:i:s');

    if ($customerId <= 0) {
        Response::error('Please select a valid customer');
    }

    if ($amount <= 0) {
        Response::error('Payment amount must be greater than zero');
    }

    try {
        $pdo->beginTransaction();

        // Verify customer exists
        $custStmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = ? FOR UPDATE");
        $custStmt->execute([$customerId]);
        $customer = $custStmt->fetch();
        if (!$customer) {
            $pdo->rollBack();
            Response::notFound('Customer record not found');
        }

        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO customer_payments (customer_id, amount, payment_method, reference, notes, payment_date, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $customerId, $amount, $paymentMethod, $reference ?: null, $notes ?: null, $paymentDate, Auth::id()
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        Audit::log('record_payment', 'customer_payment', (string)$paymentId, "Recorded payment of {$amount} for customer {$customer['name']}");

        $pdo->commit();

        Response::success("Payment of {$amount} successfully recorded for {$customer['name']}", [
            'id'          => $paymentId,
            'customer_id' => $customerId,
            'amount'      => $amount
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Record payment error: " . $e->getMessage());
        Response::error('Failed to record customer payment', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
