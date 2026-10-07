<?php
/**
 * Prime E Commerce Hub - Preview Next Invoice Number
 */

require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/Response.php';

$pdo = Database::getConnection();

// Look at maximum invoice sequence
$stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(invoice_no, 5) AS UNSIGNED)) as max_inv FROM sales WHERE invoice_no LIKE 'INV-%'");
$existingMax = (int)($stmtMax->fetchColumn() ?: 1000);

$stmtSeq = $pdo->query("SELECT current_val FROM invoice_sequences WHERE prefix = 'INV'");
$seqVal = (int)($stmtSeq->fetchColumn() ?: 1000);

$nextNumber = max($existingMax, $seqVal) + 1;

Response::success('Next invoice number preview', [
    'next_invoice_no' => "INV-{$nextNumber}"
]);
