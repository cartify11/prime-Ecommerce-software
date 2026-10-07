<?php
/**
 * Prime E Commerce Hub - Product Categories Endpoint
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';

Auth::requirePermission('products_view');

$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category ASC");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

Response::success('Categories loaded', $categories);
