<?php
/**
 * Prime E Commerce Hub - Customer Management (View, Update, Archive)
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Audit.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($data['id'] ?? 0);
if ($customerId <= 0) {
    Response::error('Valid Customer ID is required');
}

if ($method === 'GET') {
    Auth::requirePermission('customers_view');

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        Response::notFound('Customer not found');
    }
    Response::success('Customer details', $customer);
} elseif ($method === 'POST' || $method === 'PUT') {
    Auth::requirePermission('customers_manage');

    $name    = Security::sanitizeString($data['name'] ?? '');
    $phone   = Security::sanitizeString($data['phone'] ?? '');
    $email   = Security::sanitizeString($data['email'] ?? '');
    $address = Security::sanitizeString($data['address'] ?? '');

    if (empty($name) || empty($phone)) {
        Response::error('Customer Name and Phone Number are required');
    }

    // Check duplicate phone on other customers
    $check = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND id != ? AND status = 'active'");
    $check->execute([$phone, $customerId]);
    if ($check->fetch()) {
        Response::error("Another customer with phone number '{$phone}' already exists.");
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = ?, phone = ?, email = ?, address = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $phone, $email ?: null, $address ?: null, $customerId]);

        // Keep sales record customer_name in sync
        $sync = $pdo->prepare("UPDATE sales SET customer_name = ? WHERE customer_id = ?");
        $sync->execute([$name, $customerId]);

        Audit::log('update', 'customer', (string)$customerId, "Updated customer {$name} ({$phone})");

        Response::success("Customer profile updated successfully");
    } catch (Exception $e) {
        error_log("Customer update error: " . $e->getMessage());
        Response::error('Failed to update customer', 500);
    }
} elseif ($method === 'DELETE') {
    Auth::requirePermission('customers_manage');

    try {
        // Soft delete/archive customer to preserve sales invoices and ledger history
        $stmt = $pdo->prepare("UPDATE customers SET status = 'archived' WHERE id = ?");
        $stmt->execute([$customerId]);

        Audit::log('archive', 'customer', (string)$customerId, "Archived customer ID {$customerId}");

        Response::success("Customer archived successfully. Sales history is preserved.");
    } catch (Exception $e) {
        error_log("Customer delete error: " . $e->getMessage());
        Response::error('Failed to remove customer', 500);
    }
} else {
    Response::error('Method not allowed', 405);
}
