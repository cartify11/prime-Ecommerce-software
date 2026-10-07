<?php
/**
 * Prime E Commerce Hub - Concurrency-Safe Sequence Generator
 */

class NumberSequence {
    /**
     * Generate the next unique invoice number safely inside or outside a transaction
     */
    public static function nextInvoiceNo(PDO $pdo): string {
        $prefix = 'INV';
        
        // Ensure row exists
        $pdo->exec("INSERT INTO invoice_sequences (prefix, current_val) VALUES ('{$prefix}', 1000) ON DUPLICATE KEY UPDATE prefix = prefix");
        
        // Find maximum number existing in sales or sequence
        $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(invoice_no, 5) AS UNSIGNED)) as max_inv FROM sales WHERE invoice_no LIKE 'INV-%'");
        $existingMax = (int)($stmtMax->fetchColumn() ?: 1000);

        // Atomic increment of sequence
        $pdo->exec("UPDATE invoice_sequences SET current_val = GREATEST(current_val, {$existingMax}) + 1 WHERE prefix = '{$prefix}'");
        
        $stmtVal = $pdo->prepare("SELECT current_val FROM invoice_sequences WHERE prefix = ?");
        $stmtVal->execute([$prefix]);
        $nextVal = (int)$stmtVal->fetchColumn();

        $invoiceNo = "{$prefix}-{$nextVal}";

        // Secondary sanity check to guarantee uniqueness
        $check = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = ?");
        $check->execute([$invoiceNo]);
        if ($check->fetch()) {
            // Collision resolution if sequence lagged behind custom imports
            $pdo->exec("UPDATE invoice_sequences SET current_val = current_val + 1 WHERE prefix = '{$prefix}'");
            $nextVal++;
            $invoiceNo = "{$prefix}-{$nextVal}";
        }

        return $invoiceNo;
    }

    /**
     * Generate next closing code safely (e.g. WCL-1001)
     */
    public static function nextClosingCode(PDO $pdo): string {
        $prefix = 'WCL';
        $pdo->exec("INSERT INTO invoice_sequences (prefix, current_val) VALUES ('{$prefix}', 1000) ON DUPLICATE KEY UPDATE prefix = prefix");
        
        $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(closing_code, 5) AS UNSIGNED)) as max_code FROM weekly_closings WHERE closing_code LIKE 'WCL-%'");
        $existingMax = (int)($stmtMax->fetchColumn() ?: 1000);

        $pdo->exec("UPDATE invoice_sequences SET current_val = GREATEST(current_val, {$existingMax}) + 1 WHERE prefix = '{$prefix}'");
        
        $stmtVal = $pdo->prepare("SELECT current_val FROM invoice_sequences WHERE prefix = ?");
        $stmtVal->execute([$prefix]);
        $nextVal = (int)$stmtVal->fetchColumn();

        return "{$prefix}-{$nextVal}";
    }
}
