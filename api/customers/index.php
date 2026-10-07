<?php
/**
 * Prime E Commerce Hub - Customers List & Create Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requirePermission('customers_view');

    $search = trim($_GET['search'] ?? '');

    $sql = "
        SELECT 
            c.*,
            COALESCE((
                SELECT SUM(s.grand_total - s.paid_amount)
                FROM sales s 
                WHERE s.customer_id = c.id 
                  AND s.delivery_status != 'Returned / Failed Delivery'
                  AND s.payment_status != 'Paid'
            ), 0.00) as total_unpaid_sales,
            COALESCE((
                SELECT SUM(cp.amount)
                FROM customer_payments cp
                WHERE cp.customer_id = c.id
            ), 0.00) as total_direct_payments
        FROM customers c
        WHERE c.status = 'active'
    ";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $sql .= " ORDER BY c.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $customers = array_map(function($c) {
        $unpaid = (float)$c['total_unpaid_sales'];
        $directPayments = (float)$c['total_direct_payments'];
        $outstanding = $unpaid - $directPayments;
        $c['outstanding_balance'] = $outstanding;
        return $c;
    }, $rows);

    Response::success('Customers loaded', $customers);
} elseif ($method === 'POST') {
    Auth::requirePermission('customers_manage');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $name    = Security::sanitizeString($data['name'] ?? '');
    $phone   = Security::sanitizeString($data['phone'] ?? '');
    $email   = Security::sanitizeString($data['email'] ?? '');
    $address = Security::sanitizeString($data['address'] ?? '');

    if (empty($name) || empty($phone)) {
        Response::error('Customer Name and Phone Number are required');
    }

    // Check existing customer with same phone
    $check = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND status = 'active'");
    $check->execute([$phone]);
    if ($check->fetch()) {
        Response::error("A customer with phone number '{$phone}' already exists.");
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO customers (name, phone, email, address, total_visits, total_spent, status, created_at)
            VALUES (?, ?, ?, ?, 0, 0.00, 'active', NOW())
        ");
        $stmt->execute([$name, $phone, $email ?: null, $address ?: null]);
        $newId = (int)$pdo->lastInsertId();

        Audit::log('create', 'customer', (string)$newId, "Added customer {$name} ({$phone})");

        Response::success("Customer '{$name}' created successfully", [
            'id'    => $newId,
            'name'  => $name,
            'phone' => $phone
        ]);
    } catch (Exception $e) {
        error_log("Customer create error: " . $e->getMessage());
        Response::error('Failed to create customer', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
