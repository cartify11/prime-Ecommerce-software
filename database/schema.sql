-- ==============================================================
-- Prime E Commerce Hub - Relational Database Schema
-- Production Ready for cPanel Shared Hosting (MySQL 5.7+ / MariaDB 10.3+)
-- ==============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `weekly_closings`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `customer_payments`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `invoice_sequences`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `investors`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('owner', 'manager', 'sales', 'accountant') NOT NULL DEFAULT 'sales',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ROLE PERMISSIONS TABLE
CREATE TABLE `role_permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role` ENUM('owner', 'manager', 'sales', 'accountant') NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  UNIQUE KEY `uk_role_perm` (`role`, `permission`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CUSTOMERS TABLE
CREATE TABLE `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NULL,
  `address` TEXT NULL,
  `total_visits` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_spent` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cust_phone` (`phone`),
  INDEX `idx_cust_name` (`name`),
  INDEX `idx_cust_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. INVESTORS TABLE
CREATE TABLE `investors` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `investment_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_investors_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PRODUCTS TABLE
CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(60) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'General',
  `purchase_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
  `investor_id` INT UNSIGNED NULL,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_products_category` (`category`),
  INDEX `idx_products_investor` (`investor_id`),
  INDEX `idx_products_stock` (`stock_quantity`),
  INDEX `idx_products_status` (`status`),
  CONSTRAINT `fk_products_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. INVOICE SEQUENCES TABLE (Concurrency-safe Sequence Generator)
CREATE TABLE `invoice_sequences` (
  `prefix` VARCHAR(20) NOT NULL PRIMARY KEY,
  `current_val` BIGINT UNSIGNED NOT NULL DEFAULT 1000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. SALES TABLE
CREATE TABLE `sales` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(60) NOT NULL UNIQUE,
  `sale_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `customer_id` INT UNSIGNED NULL,
  `customer_name` VARCHAR(150) NOT NULL DEFAULT 'Guest Customer',
  `platform` VARCHAR(60) NOT NULL DEFAULT 'Manual',
  `payment_method` VARCHAR(60) NOT NULL DEFAULT 'Cash',
  `payment_status` ENUM('Paid', 'Pending', 'Partial') NOT NULL DEFAULT 'Paid',
  `delivery_status` VARCHAR(60) NOT NULL DEFAULT 'Delivered',
  `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `tax_percent` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `net_profit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `created_by` INT UNSIGNED NULL,
  `returned_at` DATETIME NULL,
  `returned_by` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sales_date` (`sale_date`),
  INDEX `idx_sales_customer` (`customer_id`),
  INDEX `idx_sales_pay_status` (`payment_status`),
  INDEX `idx_sales_deliv_status` (`delivery_status`),
  INDEX `idx_sales_creator` (`created_by`),
  CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. SALE ITEMS TABLE
CREATE TABLE `sale_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `product_sku` VARCHAR(60) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `investor_id` INT UNSIGNED NULL,
  `purchase_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `profit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  INDEX `idx_sale_items_sale` (`sale_id`),
  INDEX `idx_sale_items_prod` (`product_id`),
  INDEX `idx_sale_items_investor` (`investor_id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_items_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_items_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. CUSTOMER PAYMENTS TABLE
CREATE TABLE `customer_payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `payment_method` VARCHAR(60) NOT NULL DEFAULT 'Cash',
  `reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cust_pay_customer` (`customer_id`),
  INDEX `idx_cust_pay_date` (`payment_date`),
  CONSTRAINT `fk_cust_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cust_pay_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. INVENTORY TRANSACTIONS TABLE
CREATE TABLE `inventory_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `product_sku` VARCHAR(60) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `transaction_type` ENUM('ADD_STOCK', 'SALE', 'RETURN_RTO', 'ADJUSTMENT', 'OPENING_BALANCE') NOT NULL,
  `quantity_change` INT NOT NULL,
  `previous_stock` INT NOT NULL DEFAULT 0,
  `new_stock_level` INT NOT NULL DEFAULT 0,
  `sale_id` INT UNSIGNED NULL,
  `reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_inv_tx_prod` (`product_id`),
  INDEX `idx_inv_tx_type` (`transaction_type`),
  INDEX `idx_inv_tx_date` (`created_at`),
  CONSTRAINT `fk_inv_tx_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_tx_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_tx_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. WEEKLY CLOSINGS TABLE
CREATE TABLE `weekly_closings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `closing_code` VARCHAR(50) NOT NULL UNIQUE,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `sales_volume` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_revenue` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `outstanding_payments` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_profit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `closing_summary` TEXT NULL,
  `closed_by` INT UNSIGNED NULL,
  `closed_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_closing_dates` (`start_date`, `end_date`),
  INDEX `idx_closing_code` (`closing_code`),
  CONSTRAINT `fk_closings_user` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. SETTINGS TABLE
CREATE TABLE `settings` (
  `setting_key` VARCHAR(60) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. AUDIT LOGS TABLE
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `username` VARCHAR(60) NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` VARCHAR(60) NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. LOGIN ATTEMPTS (BRUTE FORCE PROTECTION)
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `attempt_time` INT UNSIGNED NOT NULL,
  INDEX `idx_login_attempts` (`ip_address`, `username`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
