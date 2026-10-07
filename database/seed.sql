-- ==============================================================
-- Prime E Commerce Hub - Default Seed Data
-- ==============================================================

-- 1. Initialize Sequence Numbers
INSERT INTO `invoice_sequences` (`prefix`, `current_val`) VALUES
('INV', 1000),
('WCL', 1000)
ON DUPLICATE KEY UPDATE `prefix` = `prefix`;

-- 2. Role Permissions Definition
INSERT INTO `role_permissions` (`role`, `permission`) VALUES
-- Owner / Super Admin (Full system control)
('owner', 'dashboard_view'),
('owner', 'products_view'),
('owner', 'products_manage'),
('owner', 'customers_view'),
('owner', 'customers_manage'),
('owner', 'sales_view'),
('owner', 'sales_create'),
('owner', 'sales_return'),
('owner', 'sales_delete'),
('owner', 'inventory_view'),
('owner', 'inventory_adjust'),
('owner', 'reports_view'),
('owner', 'reports_export'),
('owner', 'invoices_view'),
('owner', 'invoices_print'),
('owner', 'weekly_closing_view'),
('owner', 'weekly_closing_create'),
('owner', 'investors_view'),
('owner', 'investors_manage'),
('owner', 'payments_record'),
('owner', 'settings_manage'),
('owner', 'users_manage'),
('owner', 'backup_manage'),
('owner', 'demo_manage'),

-- Manager (Operations manager, no user credentials access or destructive reset)
('manager', 'dashboard_view'),
('manager', 'products_view'),
('manager', 'products_manage'),
('manager', 'customers_view'),
('manager', 'customers_manage'),
('manager', 'sales_view'),
('manager', 'sales_create'),
('manager', 'sales_return'),
('manager', 'inventory_view'),
('manager', 'inventory_adjust'),
('manager', 'reports_view'),
('manager', 'reports_export'),
('manager', 'invoices_view'),
('manager', 'invoices_print'),
('manager', 'weekly_closing_view'),
('manager', 'investors_view'),
('manager', 'payments_record'),

-- Sales Staff (POS / Sales transactions, inventory check, invoice generation)
('sales', 'dashboard_view'),
('sales', 'products_view'),
('sales', 'customers_view'),
('sales', 'customers_manage'),
('sales', 'sales_view'),
('sales', 'sales_create'),
('sales', 'inventory_view'),
('sales', 'invoices_view'),
('sales', 'invoices_print'),

-- Accountant (Ledgers, reports, payments, weekly closing)
('accountant', 'dashboard_view'),
('accountant', 'customers_view'),
('accountant', 'sales_view'),
('accountant', 'reports_view'),
('accountant', 'reports_export'),
('accountant', 'invoices_view'),
('accountant', 'invoices_print'),
('accountant', 'weekly_closing_view'),
('accountant', 'weekly_closing_create'),
('accountant', 'payments_record'),
('accountant', 'investors_view')
ON DUPLICATE KEY UPDATE `permission` = `permission`;

-- 3. Default Store Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'Prime E commerce Hub'),
('store_tagline', 'Mujahid colony warehouse'),
('store_phone', '03126772989'),
('store_email', 'admin@primehub.local'),
('store_address', 'Mujahid colony warehouse'),
('currency_symbol', 'Rs.'),
('default_tax', '0'),
('system_theme', 'dark'),
('system_installed', '0')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
