<?php
/**
 * Prime E Commerce Hub - Weekly Closings History & Generation Endpoint
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
    Auth::requirePermission('weekly_closing_view');

    $closingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($closingId > 0) {
        $stmt = $pdo->prepare("
            SELECT wc.*, u.name as closed_by_name 
            FROM weekly_closings wc
            LEFT JOIN users u ON wc.closed_by = u.id
            WHERE wc.id = ?
        ");
        $stmt->execute([$closingId]);
        $closing = $stmt->fetch();
        if (!$closing) {
            Response::notFound('Weekly closing not found');
        }

        // Fetch settings for report branding
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $closing['settings'] = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        Response::success('Weekly closing report loaded', $closing);
    } else {
        $stmt = $pdo->query("
            SELECT wc.*, u.name as closed_by_name
            FROM weekly_closings wc
            LEFT JOIN users u ON wc.closed_by = u.id
            ORDER BY wc.closed_on DESC, wc.id DESC
        ");
        $closings = $stmt->fetchAll();
        Response::success('Weekly closings history loaded', $closings);
    }
} elseif ($method === 'POST') {
    Auth::requirePermission('weekly_closing_create');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $action = $data['action'] ?? 'save';

    if ($action === 'preview') {
        // Calculate preview metrics for the selected date range
        $startDate = !empty($data['start_date']) ? date('Y-m-d', strtotime($data['start_date'])) : date('Y-m-d', strtotime('-7 days'));
        $endDate   = !empty($data['end_date']) ? date('Y-m-d', strtotime($data['end_date'])) : date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as sales_count,
                COALESCE(SUM(grand_total), 0.00) as revenue,
                COALESCE(SUM(paid_amount), 0.00) as paid_amount,
                COALESCE(SUM(total_cost), 0.00) as total_cost,
                COALESCE(SUM(net_profit), 0.00) as profit
            FROM sales
            WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ?
              AND delivery_status != 'Returned / Failed Delivery'
        ");
        $stmt->execute([$startDate, $endDate]);
        $summary = $stmt->fetch();

        $revenue = (float)$summary['revenue'];
        $paid = (float)$summary['paid_amount'];
        $outstanding = max(0, $revenue - $paid);

        Response::success('Weekly closing summary preview', [
            'start_date'           => $startDate,
            'end_date'             => $endDate,
            'sales_volume'         => (int)$summary['sales_count'],
            'total_revenue'        => $revenue,
            'paid_amount'          => $paid,
            'outstanding_payments' => $outstanding,
            'total_cost'           => (float)$summary['total_cost'],
            'total_profit'         => (float)$summary['profit']
        ]);
    } else {
        // Commit closing record
        $startDate   = !empty($data['start_date']) ? date('Y-m-d', strtotime($data['start_date'])) : date('Y-m-d', strtotime('-7 days'));
        $endDate     = !empty($data['end_date']) ? date('Y-m-d', strtotime($data['end_date'])) : date('Y-m-d');
        $narrative   = Security::sanitizeString($data['closing_summary'] ?? '');

        // Recalculate authoritative server totals for period
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as sales_count,
                COALESCE(SUM(grand_total), 0.00) as revenue,
                COALESCE(SUM(paid_amount), 0.00) as paid_amount,
                COALESCE(SUM(total_cost), 0.00) as total_cost,
                COALESCE(SUM(net_profit), 0.00) as profit
            FROM sales
            WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ?
              AND delivery_status != 'Returned / Failed Delivery'
        ");
        $stmt->execute([$startDate, $endDate]);
        $summary = $stmt->fetch();

        $salesVolume = (int)$summary['sales_count'];
        $revenue     = (float)$summary['revenue'];
        $paid        = (float)$summary['paid_amount'];
        $outstanding = max(0, $revenue - $paid);
        $cost        = (float)$summary['total_cost'];
        $profit      = (float)$summary['profit'];

        try {
            $pdo->beginTransaction();

            $closingCode = NumberSequence::nextClosingCode($pdo);

            $insStmt = $pdo->prepare("
                INSERT INTO weekly_closings (
                    closing_code, start_date, end_date, sales_volume, total_revenue,
                    paid_amount, outstanding_payments, total_cost, total_profit,
                    closing_summary, closed_by, closed_on, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insStmt->execute([
                $closingCode, $startDate, $endDate, $salesVolume, $revenue,
                $paid, $outstanding, $cost, $profit, $narrative ?: null, Auth::id()
            ]);
            $newId = (int)$pdo->lastInsertId();

            Audit::log('create', 'weekly_closing', (string)$newId, "Generated weekly closing {$closingCode} ({$startDate} to {$endDate})");

            $pdo->commit();

            Response::success("Weekly Closing {$closingCode} logged successfully", [
                'id'           => $newId,
                'closing_code' => $closingCode
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Weekly closing error: " . $e->getMessage());
            Response::error('Failed to log weekly closing', 500);
        }
    }
} else {
    Response::error('Method not allowed', 405);
}
