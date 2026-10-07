<?php
/**
 * Prime E Commerce Hub - Main Web Application
 * Secure Multi-User Business Management System for cPanel Shared Hosting
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/helpers/Auth.php';

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$currentUser = Auth::user();
$csrfToken = Security::getCsrfToken();
$userRole = Auth::role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Prime E Commerce Hub</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/png" href="assets/images/icon.png">
    <!-- Local Charts and Icons - 100% Offline / Shared Hosting Ready (No External CDN Dependency) -->
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/lucide.min.js"></script>
    <script>
        window.CURRENT_USER = <?php echo json_encode($currentUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        window.CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
        window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
    </script>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="brand-section">
            <div class="brand-icon">
                <i data-lucide="layers"></i>
            </div>
            <span class="brand-name" id="sidebar-brand-title">Prime E commerce Hub</span>
        </div>
        
        <nav class="nav-menu">
            <li class="nav-item active" data-tab="dashboard">
                <a href="#dashboard">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item" data-tab="products">
                <a href="#products">
                    <i data-lucide="package"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item" data-tab="customers">
                <a href="#customers">
                    <i data-lucide="users"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="nav-item" data-tab="sales">
                <a href="#sales">
                    <i data-lucide="shopping-bag"></i>
                    <span>Sales</span>
                </a>
            </li>
            <li class="nav-item" data-tab="inventory">
                <a href="#inventory">
                    <i data-lucide="clipboard-list"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item" data-tab="reports">
                <a href="#reports">
                    <i data-lucide="bar-chart-3"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item" data-tab="invoices">
                <a href="#invoices">
                    <i data-lucide="receipt"></i>
                    <span>Invoices</span>
                </a>
            </li>
            <li class="nav-item" data-tab="owner" id="nav-owner-item">
                <a href="#owner">
                    <i data-lucide="trending-up"></i>
                    <span>Investors & Capital</span>
                </a>
            </li>
            <?php if ($userRole === 'owner'): ?>
            <li class="nav-item" data-tab="users">
                <a href="#users">
                    <i data-lucide="user-check"></i>
                    <span>Team & Roles</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item" data-tab="settings">
                <a href="#settings">
                    <i data-lucide="settings"></i>
                    <span>Settings</span>
                </a>
            </li>
        </nav>
        
        <div class="sidebar-footer">
            <div style="background: rgba(255,255,255,0.03); padding: 8px 10px; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #fff;"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    <div style="font-size: 0.7rem; color: var(--accent-blue); text-transform: uppercase; font-weight: 600;"><?php echo htmlspecialchars($currentUser['role']); ?></div>
                </div>
                <a href="logout.php" style="color: var(--accent-rose); padding: 4px; display: flex; align-items: center;" title="Sign Out">
                    <i data-lucide="log-out" style="width: 16px; height: 16px;"></i>
                </a>
            </div>
            <p style="font-size: 0.7rem; color: var(--text-secondary); text-align: center;">Prime Hub v2.0 Web Edition</p>
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px; padding-top: 6px; border-top: 1px solid var(--border-color); justify-content: center;">
                <img src="assets/images/mt_tech_logo.png" alt="MT Logo" style="width: 16px; height: 16px; object-fit: contain;">
                <span style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 500;">Online System</span>
            </div>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="header">
            <div class="header-search">
                <i data-lucide="search"></i>
                <input type="text" id="global-search-bar" placeholder="Search system...">
            </div>
            
            <div class="header-actions">
                <button id="theme-toggle-btn" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 8px; outline: none; margin-right: 8px;" title="Toggle Light/Dark Theme">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M22 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                </button>
                <div class="datetime-display">
                    <span class="time" id="header-time">00:00:00 PM</span>
                    <span class="date" id="header-date">Thursday, June 25, 2026</span>
                </div>
                
                <div class="notification-badge" id="notification-bell">
                    <i data-lucide="bell"></i>
                    <span class="badge-count" id="notif-count">0</span>
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notif-header">
                            <span>Alerts & Notifications</span>
                            <span class="badge danger" id="dropdown-notif-count">0 Low Stock</span>
                        </div>
                        <div class="notif-list" id="notification-items-list">
                            <!-- Items populated dynamically -->
                        </div>
                    </div>
                </div>

                <div style="margin-left: 12px; display: flex; align-items: center; gap: 8px;">
                    <button id="btn-change-password-modal" class="btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;" title="Change Password">
                        <i data-lucide="key" style="width: 14px; height: 14px;"></i>
                        <span>Password</span>
                    </button>
                    <a href="logout.php" class="btn-danger" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="log-out" style="width: 14px; height: 14px;"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="content-area">
            
            <!-- SECTION 1: DASHBOARD -->
            <section id="dashboard" class="page-section active">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Dashboard Summary</h1>
                        <p>Real-time metrics, profit calculations, and inventory warnings</p>
                    </div>
                    <?php if ($userRole === 'owner'): ?>
                    <div>
                        <button class="btn-secondary" id="load-demo-btn" style="background: rgba(226, 177, 60, 0.15); border-color: var(--accent-blue); color: var(--accent-blue);">
                            <i data-lucide="database"></i> Load Sample Demo Data
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="demo-banner" class="dashboard-card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(2, 132, 199, 0.05)); margin-bottom: 24px; border-color: rgba(99, 102, 241, 0.2); display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div>
                            <h3 style="font-size: 1rem; margin-bottom: 4px; color: var(--text-primary); display: flex; align-items: center; gap: 6px;"><i data-lucide="info" style="width: 18px; height: 18px; color: var(--accent-purple);"></i> Prime Hub is Currently Empty</h3>
                            <p style="font-size: 0.85rem; color: var(--text-secondary);">Start adding products manually under the "Products" tab, or load our demo database for testing.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Metric Summary Grid 1: Business Overview -->
                <div class="metrics-grid">
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Total Revenue</h3>
                            <div class="value" id="dash-revenue">Rs. 0.00</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="banknote"></i>
                        </div>
                    </div>
                    <div class="metric-card emerald-glow">
                        <div class="metric-info">
                            <h3>Net Profit</h3>
                            <div class="value" id="dash-profit">Rs. 0.00</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="trending-up"></i>
                        </div>
                    </div>
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Total Sales</h3>
                            <div class="value" id="dash-sales-count">0</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="shopping-bag"></i>
                        </div>
                    </div>
                    <div class="metric-card rose-glow" id="metric-low-stock-card" style="cursor: pointer;">
                        <div class="metric-info">
                            <h3>Low Stock Alerts</h3>
                            <div class="value" id="dash-low-stock-count">0</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="alert-triangle"></i>
                        </div>
                    </div>
                </div>

                <!-- Metric Summary Grid 2: Period Audits -->
                <div class="metrics-grid grid-secondary">
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Today's Sales</h3>
                            <div class="value" id="dash-today-sales">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card emerald-glow">
                        <div class="metric-info">
                            <h3>Today's Profit</h3>
                            <div class="value" id="dash-today-profit">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Weekly Sales</h3>
                            <div class="value" id="dash-weekly-sales">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card emerald-glow">
                        <div class="metric-info">
                            <h3>Monthly Sales</h3>
                            <div class="value" id="dash-monthly-sales">Rs. 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- Metric Summary Grid 3: Inventory Assets -->
                <div class="metrics-grid grid-secondary">
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Asset Value (Cost)</h3>
                            <div class="value" id="dash-inv-buying-val" style="color: var(--accent-blue);">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card emerald-glow">
                        <div class="metric-info">
                            <h3>Current Stock Value</h3>
                            <div class="value" id="dash-inv-selling-val">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Expected Profit</h3>
                            <div class="value" id="dash-inv-expected-profit">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Total Products</h3>
                            <div class="value" id="dash-total-products">0 items</div>
                        </div>
                    </div>
                </div>

                <!-- Metric Summary Grid 4: Orders & Payments -->
                <div class="metrics-grid grid-secondary">
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Total Customers</h3>
                            <div class="value" id="dash-total-customers">0 profiles</div>
                        </div>
                    </div>
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Total Orders</h3>
                            <div class="value" id="dash-total-orders">0 orders</div>
                        </div>
                    </div>
                    <div class="metric-card emerald-glow">
                        <div class="metric-info">
                            <h3>Paid Payments</h3>
                            <div class="value" id="dash-paid-payments">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="metric-card rose-glow">
                        <div class="metric-info">
                            <h3>Pending Payments</h3>
                            <div class="value" id="dash-pending-payments" style="color: var(--accent-rose);">Rs. 0.00</div>
                        </div>
                    </div>
                </div>
                
                <!-- Dashboard Grid -->
                <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px;">
                    <!-- Revenue & Profit Trend Chart -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2>Business Financial Trends (7-Day Performance)</h2>
                        </div>
                        <div class="chart-container" style="height: 280px; position: relative;">
                            <canvas id="revenueProfitChart"></canvas>
                        </div>
                    </div>

                    <!-- Platform-wise Sales Distribution Chart -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2>Platform Sales Distribution</h2>
                        </div>
                        <div class="chart-container" style="height: 280px; position: relative; display: flex; align-items: center; justify-content: center;">
                            <canvas id="platformShareChart"></canvas>
                        </div>
                    </div>

                    <!-- Low Stock Alerts & Summary List -->
                    <div class="dashboard-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="card-header">
                            <h2>Critical Low Stock Alerts</h2>
                            <span class="badge danger" id="low-stock-badge-lbl">0 items</span>
                        </div>
                        <div style="flex-grow: 1; overflow-y: auto;" id="dashboard-low-stock-list">
                            <!-- Items added dynamically -->
                        </div>
                    </div>
                </div>
                
                <!-- Recent Sales Table -->
                <div class="table-card">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 20px 24px;">
                        <h2>Recent Transactions</h2>
                        <button class="btn-secondary" id="dash-view-all-sales-btn">
                            View Sales Ledger
                        </button>
                    </div>
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Grand Total</th>
                                <th>Profit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dashboard-recent-sales-tbody">
                            <!-- Dynamic rows -->
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- SECTION 2: SALES LEDGER -->
            <section id="sales" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Sales Ledger</h1>
                        <p>View, search, and manually record customer sales records</p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn-secondary" id="btn-export-sales-csv" style="padding: 10px 16px; background-color: var(--accent-emerald); color: white; border: none; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="file-spreadsheet"></i> Export to Excel
                        </button>
                        <button class="btn-primary" id="add-sale-btn">
                            <i data-lucide="plus-circle"></i> Record New Sale
                        </button>
                    </div>
                </div>
                
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="sales-search-input" placeholder="Search by Invoice No or Customer Name...">
                    </div>
                    <div class="filter-wrapper">
                        <select id="sales-customer-filter">
                            <option value="All">All Customers</option>
                        </select>
                    </div>
                    <div class="filter-wrapper">
                        <input type="date" id="sales-date-filter">
                    </div>
                </div>
                
                <div class="table-card">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Date & Time</th>
                                <th>Customer Name</th>
                                <th>Items Qty</th>
                                <th>Subtotal</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th>Grand Total</th>
                                <th>Net Profit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sales-table-tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 3: PRODUCTS -->
            <section id="products" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Product Catalog</h1>
                        <p>Manage store inventory items, pricing, and reorder levels</p>
                    </div>
                    <button class="btn-primary" id="add-product-btn">
                        <i data-lucide="plus-circle"></i> Add New Product
                    </button>
                </div>
                
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="product-search-input" placeholder="Search by SKU or name...">
                    </div>
                    <div class="filter-wrapper">
                        <select id="product-category-filter">
                            <option value="All">All Categories</option>
                        </select>
                    </div>
                    <div class="filter-wrapper">
                        <select id="product-stock-filter">
                            <option value="All">All Stock Levels</option>
                            <option value="Low">Low Stock Alerts</option>
                            <option value="Out">Out of Stock</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-card">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Buying Cost</th>
                                <th>Selling Price</th>
                                <th>Stock Qty</th>
                                <th>Stock Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="products-table-tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 4: CUSTOMERS -->
            <section id="customers" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Customer Directory</h1>
                        <p>View client profiles, contact numbers, and purchase tracking details</p>
                    </div>
                    <button class="btn-primary" id="add-customer-btn">
                        <i data-lucide="plus-circle"></i> Add New Customer
                    </button>
                </div>
                
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="customer-search-input" placeholder="Search by name, phone or email...">
                    </div>
                </div>
                
                <div class="table-card">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Phone Number</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Total Visits</th>
                                <th>Total Spent</th>
                                <th>Outstanding Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customers-table-tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 5: INVENTORY -->
            <section id="inventory" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Inventory & Stock Control</h1>
                        <p>Adjust stock quantities, review reorder alerts, and view transaction logs</p>
                    </div>
                    <button class="btn-primary" id="adjust-stock-btn">
                        <i data-lucide="refresh-cw"></i> Adjust Stock Levels
                    </button>
                </div>
                
                <!-- Inventory Metrics Summary Grid -->
                <div class="metrics-grid grid-secondary" style="margin-bottom: 24px;">
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Asset Value (Buying Cost)</h3>
                            <div class="value" id="inv-val-buying">Rs. 0.00</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="banknote"></i>
                        </div>
                    </div>
                    <div class="metric-card emerald-glow">
                        <div class="metric-info">
                            <h3>Potential Value (Selling Price)</h3>
                            <div class="value" id="inv-val-selling">Rs. 0.00</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="trending-up"></i>
                        </div>
                    </div>
                    <div class="metric-card gold-glow">
                        <div class="metric-info">
                            <h3>Expected Profit</h3>
                            <div class="value" id="inv-expected-profit">Rs. 0.00</div>
                        </div>
                        <div class="metric-icon">
                            <i data-lucide="arrow-up-right"></i>
                        </div>
                    </div>
                </div>
                
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="inventory-search-input" placeholder="Search product name or SKU...">
                    </div>
                    <div class="filter-wrapper">
                        <select id="inventory-category-filter">
                            <option value="All">All Categories</option>
                        </select>
                    </div>
                    <div class="filter-wrapper">
                        <select id="inventory-stock-filter">
                            <option value="All">All Stock Levels</option>
                            <option value="Low">Low Stock Alerts</option>
                            <option value="Out">Out of Stock</option>
                        </select>
                    </div>
                </div>
                
                <!-- Stock Levels Table -->
                <div class="table-card">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Buying Cost</th>
                                <th>Selling Price</th>
                                <th>Stock Quantity</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-table-tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Stock Adjustment History -->
                <div class="dashboard-card" style="margin-top: 32px;">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 0 0 16px 0;">
                        <h2>Stock Adjustment History Log</h2>
                    </div>
                    <div style="overflow-x: auto; margin-top: 16px;">
                        <table class="responsive-table" style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>SKU</th>
                                    <th>Product Name</th>
                                    <th>Change Type</th>
                                    <th>Qty Change</th>
                                    <th>New Stock</th>
                                    <th>Recorded Reason</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-log-tbody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- SECTION 6: REPORTS & CLOSING -->
            <section id="reports" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Reports & Closing Logs</h1>
                        <p>Generate daily audits, perform weekly closures, and check margins</p>
                    </div>
                </div>
                
                <div class="reports-layout">
                    
                    <!-- Row 1: Daily Profit Report -->
                    <div class="dashboard-card">
                        <div class="card-header" style="margin-bottom: 24px;">
                            <div>
                                <h2>Daily Report Audit</h2>
                                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">Choose a date to analyze performance metrics</p>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
                                    <input type="date" id="daily-report-date-picker" style="padding: 10px;">
                                </div>
                                <button type="button" class="btn-secondary" id="btn-export-daily-pdf" style="padding: 10px 14px; background-color: var(--accent-purple);" title="Export Daily Report PDF">
                                    <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
                                </button>
                                <button type="button" class="btn-secondary" id="btn-export-reports-csv" style="padding: 10px 14px; background-color: var(--accent-emerald); color: white; border: none;" title="Export Reports to Excel">
                                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px;"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Daily summary stats -->
                        <div class="metrics-grid" style="margin-bottom: 24px;">
                            <div class="metric-card blue" style="padding: 16px;">
                                <div class="metric-info">
                                    <h3>Sales count</h3>
                                    <div class="value" id="daily-sales-count" style="font-size: 1.4rem;">0</div>
                                </div>
                            </div>
                            <div class="metric-card emerald" style="padding: 16px;">
                                <div class="metric-info">
                                    <h3>Daily Revenue</h3>
                                    <div class="value" id="daily-revenue" style="font-size: 1.4rem;">Rs. 0</div>
                                </div>
                            </div>
                            <div class="metric-card blue" style="padding: 16px; background-color: rgba(99, 102, 241, 0.1);">
                                <div class="metric-info">
                                    <h3>Daily COGS (Cost)</h3>
                                    <div class="value" id="daily-cogs" style="font-size: 1.4rem; color: var(--accent-purple);">Rs. 0</div>
                                </div>
                            </div>
                            <div class="metric-card emerald" style="padding: 16px;">
                                <div class="metric-info">
                                    <h3>Net daily Profit</h3>
                                    <div class="value" id="daily-profit" style="font-size: 1.4rem;">Rs. 0</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- List of daily sales -->
                        <div class="table-card" style="margin-bottom: 0;">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th>Invoice No.</th>
                                        <th>Time</th>
                                        <th>Customer</th>
                                        <th>Items Subtotal</th>
                                        <th>Discount</th>
                                        <th>Grand Total</th>
                                        <th>Net Profit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="daily-sales-table-tbody">
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--text-secondary);">Select a date to retrieve sales data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Row 2: Weekly Closing Log -->
                    <div class="dashboard-card">
                        <div class="card-header" style="margin-bottom: 24px;">
                            <div>
                                <h2>Weekly Closure & Archive</h2>
                                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">Compile, summarize, and download details of weekly transactions</p>
                            </div>
                            <button class="btn-success" id="generate-weekly-closing-btn">
                                <i data-lucide="archive"></i> Generate Weekly Closing
                            </button>
                        </div>
                        
                        <div class="closing-history-section">
                            <h3>Saved Weekly Closing Archives</h3>
                            <div class="table-card">
                                <table class="responsive-table">
                                    <thead>
                                        <tr>
                                            <th>Closing ID</th>
                                            <th>Period Date Range</th>
                                            <th>Sales Volume</th>
                                            <th>Net Revenue</th>
                                            <th>Total Profit</th>
                                            <th>Closed On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="weekly-closing-tbody">
                                        <!-- Dynamically generated rows -->
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-secondary);">No weekly closings logged yet.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </section>

            <!-- SECTION 7: INVOICES REGISTRY -->
            <section id="invoices" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Invoices Registry</h1>
                        <p>View, filter, and reprint generated customer purchase invoice receipts</p>
                    </div>
                    <button class="btn-primary" id="invoice-add-sale-btn">
                        <i data-lucide="plus-circle"></i> Record New Sale
                    </button>
                </div>
                
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="invoice-search-input" placeholder="Search by Invoice Number or Customer Name...">
                    </div>
                    <div class="filter-wrapper">
                        <input type="date" id="invoice-date-filter">
                    </div>
                </div>
                
                <div class="table-card">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Date & Time</th>
                                <th>Customer Name</th>
                                <th>Total Items</th>
                                <th>Subtotal</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th>Grand Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="invoice-registry-tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION 8: SETTINGS -->
            <section id="settings" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>System Settings</h1>
                        <p>Configure store profile, default invoice rates, and backup databases</p>
                    </div>
                </div>
                
                <div class="reports-layout">
                    <!-- Store Profile settings -->
                    <div class="dashboard-card">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px;">
                            <h2>Store Profile Settings</h2>
                        </div>
                        
                        <form id="settings-profile-form">
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="set-store-name">Store Name *</label>
                                    <input type="text" id="set-store-name" required placeholder="Prime E commerce Hub">
                                </div>
                                <div class="form-group">
                                    <label for="set-store-tagline">Store Tagline</label>
                                    <input type="text" id="set-store-tagline" placeholder="Mujahid colony warehouse">
                                </div>
                            </div>
                            
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="set-store-phone">Phone Number *</label>
                                    <input type="text" id="set-store-phone" required placeholder="+92 300 0000000">
                                </div>
                                <div class="form-group">
                                    <label for="set-store-email">Email Address</label>
                                    <input type="email" id="set-store-email" placeholder="support@primehub.com">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="set-store-address">Store Address</label>
                                <textarea id="set-store-address" rows="2" placeholder="Block B, Gulberg III, Lahore"></textarea>
                            </div>
                            
                            <button type="submit" class="btn-success">
                                <i data-lucide="save"></i> Save Profile Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- Defaults Settings -->
                    <div class="dashboard-card">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px;">
                            <h2>Financial Defaults Settings</h2>
                        </div>
                        <form id="settings-financial-form">
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="set-default-tax">Default Sales Tax (%)</label>
                                    <input type="number" id="set-default-tax" min="0" max="100" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="set-currency-symbol">Currency Symbol</label>
                                    <input type="text" id="set-currency-symbol" value="Rs.">
                                </div>
                            </div>
                            <button type="submit" class="btn-success" style="margin-top: 14px;">
                                <i data-lucide="save"></i> Save Financial Defaults
                            </button>
                        </form>
                    </div>

                    <!-- Backup & Restore Databases -->
                    <div class="dashboard-card">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px;">
                            <h2>Database Tools & Maintenance</h2>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 20px;">
                            Export complete MySQL system state (including investors, customers, sales, products, and logs) or import legacy JSON data.
                        </p>
                        
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <a href="api/backup/export.php" class="btn-primary" id="btn-export-db" style="text-decoration: none;">
                                <i data-lucide="download"></i> Backup Full Database
                            </a>
                            
                            <button type="button" class="btn-secondary" id="btn-import-trigger-db" style="position: relative;">
                                <i data-lucide="upload"></i> Restore Full Backup
                                <input type="file" id="btn-import-file-db" accept=".json" style="position: absolute; left:0; top:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                            </button>

                            <button type="button" class="btn-secondary" id="btn-migrate-trigger-db" style="position: relative; background: rgba(99, 102, 241, 0.15); border-color: var(--accent-purple); color: var(--text-primary);">
                                <i data-lucide="file-code"></i> Import database.json
                                <input type="file" id="btn-migrate-file-db" accept=".json" style="position: absolute; left:0; top:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 9: OWNER & INVESTOR PANEL -->
            <section id="owner" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>Investors & Capital Registry</h1>
                        <p>Track capital investments, stock asset allocations, sales revenue, and net profit payouts</p>
                    </div>
                </div>

                <div class="reports-layout">
                    <!-- Add Investor Card -->
                    <div class="dashboard-card">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                            <h2>Register New Investor</h2>
                        </div>
                        <form id="owner-investor-form">
                            <div class="form-group" style="margin-bottom: 14px;">
                                <label for="inv-name">Investor Name *</label>
                                <input type="text" id="inv-name" required placeholder="e.g. Sohail Ahmed" style="background-color: rgba(255, 255, 255, 0.02); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: var(--border-radius-md); outline: none; width: 100%;">
                            </div>
                            <div class="form-group" style="margin-bottom: 14px;">
                                <label for="inv-capital">Investment Amount (Capital) *</label>
                                <input type="number" id="inv-capital" min="1" required placeholder="e.g. 1000000" style="background-color: rgba(255, 255, 255, 0.02); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: var(--border-radius-md); outline: none; width: 100%;">
                            </div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label for="inv-notes">Investment Notes</label>
                                <textarea id="inv-notes" rows="2" placeholder="e.g. Kitchen gadgets flyer funding" style="background-color: rgba(255, 255, 255, 0.02); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: var(--border-radius-md); outline: none; width: 100%; font-family: inherit;"></textarea>
                            </div>
                            <button type="submit" class="btn-success">
                                <i data-lucide="plus-circle"></i> Add Investor Account
                            </button>
                        </form>
                    </div>

                    <!-- Investors List Card -->
                    <div class="dashboard-card" style="grid-column: span 2;">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px;">
                            <h2>Investor Capital Accounts</h2>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="data-table" id="owner-investors-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th style="text-align: right;">Total Capital</th>
                                        <th style="text-align: right;">Active Stock (Cost)</th>
                                        <th style="text-align: right;">Remaining Cash</th>
                                        <th style="text-align: right;">Sales Gen.</th>
                                        <th style="text-align: right;">Net Profit</th>
                                        <th style="text-align: center; width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Investor Ledger Details Section (Hidden by default, shown when viewing ledger) -->
                <div class="dashboard-card" id="investor-details-card" style="margin-top: 24px; display: none;">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px;">
                        <div>
                            <h2 id="inv-details-title" style="font-size: 1.25rem;">Investor Detail Ledger</h2>
                            <p id="inv-details-meta" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;"></p>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-primary" id="btn-print-investor-ledger" style="padding: 10px 16px; font-size: 0.9rem; background-color: var(--accent-blue); display: inline-flex; align-items: center; gap: 6px;">
                                <i data-lucide="printer" style="width: 14px; height: 14px;"></i> Print Statement
                            </button>
                            <button class="btn-secondary" onclick="document.getElementById('investor-details-card').style.display='none'">
                                Close Details
                            </button>
                        </div>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table" id="investor-products-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Product Name</th>
                                    <th style="text-align: right;">Buying Cost</th>
                                    <th style="text-align: right;">Selling Price</th>
                                    <th style="text-align: right;">Current Stock</th>
                                    <th style="text-align: right;">Total Sold</th>
                                    <th style="text-align: right;">Total Returned</th>
                                    <th style="text-align: right;">Net Profit Generated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <?php if ($userRole === 'owner'): ?>
            <!-- SECTION 10: USER MANAGEMENT (OWNER ONLY) -->
            <section id="users" class="page-section">
                <div class="section-header">
                    <div class="section-title">
                        <h1>User & Role Management</h1>
                        <p>Manage staff accounts, assign roles (Owner, Manager, Sales Staff, Accountant), and grant permissions</p>
                    </div>
                    <button class="btn-primary" id="btn-add-user-modal">
                        <i data-lucide="user-plus"></i> Add New User
                    </button>
                </div>

                <div class="table-card">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
            
        </main>
    </div>

    <!-- MODAL 1: ADD/EDIT PRODUCT -->
    <div class="modal-overlay" id="product-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="product-modal-title">Add New Product</h2>
                <button class="close-modal-btn" onclick="closeModal('product-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="product-form">
                <div class="modal-body">
                    <input type="hidden" id="product-id-field">
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="prod-sku">SKU Code *</label>
                            <input type="text" id="prod-sku" required placeholder="e.g. PRD-101">
                        </div>
                        <div class="form-group">
                            <label for="prod-category">Category *</label>
                            <input type="text" id="prod-category" required placeholder="e.g. Beverages" list="category-datalist">
                            <datalist id="category-datalist">
                                <!-- Suggestions populated dynamically -->
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="prod-name">Product Name *</label>
                        <input type="text" id="prod-name" required placeholder="e.g. Coca Cola 1.5L">
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="prod-purchase-price">Cost Price (Rs.) *</label>
                            <input type="number" id="prod-purchase-price" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="prod-selling-price">Selling Price (Rs.) *</label>
                            <input type="number" id="prod-selling-price" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="prod-stock">Stock Quantity *</label>
                            <input type="number" id="prod-stock" min="0" required placeholder="0">
                        </div>
                        <div class="form-group">
                            <label for="prod-threshold">Low Stock Threshold *</label>
                            <input type="number" id="prod-threshold" min="0" required value="5">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label for="prod-investor">Sponsor / Capital Investor (Optional)</label>
                        <select id="prod-investor" style="background-color: rgba(255, 255, 255, 0.02); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: var(--border-radius-md); outline: none; width: 100%; cursor: pointer;">
                            <option value="">-- Self-Funded / Store Owned --</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('product-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="save-product-submit-btn">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: ADD/EDIT CUSTOMER -->
    <div class="modal-overlay" id="customer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="customer-modal-title">Add Customer Profile</h2>
                <button class="close-modal-btn" onclick="closeModal('customer-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="customer-form">
                <div class="modal-body">
                    <input type="hidden" id="customer-id-field">
                    
                    <div class="form-group">
                        <label for="cust-name">Customer Name *</label>
                        <input type="text" id="cust-name" required placeholder="e.g. Ahmad Khan">
                    </div>
                    
                    <div class="form-group">
                        <label for="cust-phone">Phone Number *</label>
                        <input type="tel" id="cust-phone" required placeholder="e.g. 03001234567">
                    </div>
                    
                    <div class="form-group">
                        <label for="cust-email">Email Address</label>
                        <input type="email" id="cust-email" placeholder="e.g. ahmad@gmail.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="cust-address">Home/Office Address</label>
                        <textarea id="cust-address" rows="3" placeholder="e.g. Block B, Gulberg III, Lahore"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('customer-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="save-customer-submit-btn">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2B: RECORD CUSTOMER PAYMENT -->
    <div class="modal-overlay" id="customer-payment-modal">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header">
                <h2>Record Customer Payment</h2>
                <button class="close-modal-btn" onclick="closeModal('customer-payment-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="customer-payment-form">
                <div class="modal-body">
                    <input type="hidden" id="payment-customer-id">
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label>Customer Name</label>
                        <input type="text" id="payment-customer-name" readonly style="background-color: rgba(255,255,255,0.03); cursor: not-allowed; color: var(--text-secondary);">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label>Current Outstanding Balance</label>
                        <input type="text" id="payment-current-balance" readonly style="background-color: rgba(255,255,255,0.03); cursor: not-allowed; color: var(--accent-rose); font-weight: bold;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="payment-amount">Payment Amount Received (Rs.) *</label>
                        <input type="number" step="0.01" id="payment-amount" required placeholder="e.g. 5000" min="0.01">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="payment-date">Payment Date *</label>
                        <input type="datetime-local" id="payment-date" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="payment-method">Payment Method *</label>
                        <select id="payment-method" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="JazzCash">JazzCash</option>
                            <option value="EasyPaisa">EasyPaisa</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="payment-notes">Notes / Reference No.</label>
                        <textarea id="payment-notes" rows="2" placeholder="e.g. Bank receipt ref 87612, cash in hand, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('customer-payment-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="save-payment-submit-btn">Save Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2C: CUSTOMER LEDGER STATEMENT -->
    <div class="modal-overlay" id="customer-ledger-modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h2>Customer Account Ledger</h2>
                <button class="close-modal-btn" onclick="closeModal('customer-ledger-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" style="overflow-y: auto; flex-grow: 1; padding: 24px;">
                <div id="ledger-print-area">
                    <!-- Dynamic Ledger Statement Layout -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('customer-ledger-modal')">Close</button>
                <button type="button" class="btn-primary" id="btn-print-ledger"><i data-lucide="printer"></i> Print Statement</button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: INVOICE PRINT VIEW -->
    <div class="modal-overlay" id="invoice-modal">
        <div class="modal-content" style="max-width: 760px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h2>Invoice Preview</h2>
                <button class="close-modal-btn" onclick="closeModal('invoice-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" style="overflow-y: auto; background-color: #f8fafc; padding: 20px; flex-grow: 1;">
                
                <!-- Print area start -->
                <div class="invoice-container" id="invoice-print-area">
                    <div class="invoice-header">
                        <div class="invoice-logo-title">
                            <h2 id="invoice-brand-title">Prime E commerce Hub</h2>
                            <p id="invoice-brand-tagline">Mujahid colony warehouse</p>
                            <p style="font-size: 0.75rem; color: #64748b;" id="invoice-brand-contact">Owner: Asif Ghafoor | Phone: 03126772989</p>
                        </div>
                        <div class="invoice-meta">
                            <h3>INVOICE</h3>
                            <p><strong>Invoice No:</strong> <span id="inv-no">INV-1001</span></p>
                            <p><strong>Date:</strong> <span id="inv-date">2026-06-25</span></p>
                        </div>
                    </div>
                    
                    <div class="invoice-details">
                        <div class="invoice-bill-to">
                            <h4>Billed To:</h4>
                            <p><strong id="inv-cust-name">Guest Customer</strong></p>
                            <p id="inv-cust-phone">Phone: N/A</p>
                            <p id="inv-cust-address">Address: N/A</p>
                        </div>
                        <div class="invoice-ship-to" style="text-align: right;">
                            <h4>Payment & Channel:</h4>
                            <p>Method: <strong id="inv-payment-method">Cash</strong></p>
                            <p>Channel: <strong id="inv-platform">Manual</strong></p>
                            <p style="font-size: 0.8rem; color: #64748b; margin-top: 4px;" id="inv-payment-status">Status: Paid</p>
                        </div>
                    </div>
                    
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th class="num-col" style="width: 100px;">Unit Price</th>
                                <th class="num-col" style="width: 80px;">Qty</th>
                                <th class="num-col" style="width: 120px;">Total Price</th>
                            </tr>
                        </thead>
                        <tbody id="invoice-items-tbody">
                            <!-- Items added dynamically -->
                        </tbody>
                    </table>
                    
                    <div class="invoice-summary">
                        <table class="invoice-summary-table">
                            <tr>
                                <td>Subtotal:</td>
                                <td class="num-col" id="inv-subtotal">Rs. 0.00</td>
                            </tr>
                            <tr>
                                <td>Discount:</td>
                                <td class="num-col" id="inv-discount">-Rs. 0.00</td>
                            </tr>
                            <tr>
                                <td>Tax:</td>
                                <td class="num-col" id="inv-tax">Rs. 0.00</td>
                            </tr>
                            <tr class="grand-total">
                                <td>Grand Total:</td>
                                <td class="num-col" id="inv-grand-total">Rs. 0.00</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="invoice-footer">
                        <p>Thank you for shopping with us! Please come again.</p>
                        <p style="margin-top: 4px; font-size: 0.75rem; color: #cbd5e1;">Generated by Prime Hub Business Portal</p>
                        <p style="font-size: 0.65rem; color: #94a3b8; margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 8px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <img src="assets/images/mt_tech_logo.png" alt="MT Logo" style="width: 12px; height: 12px; object-fit: contain;">
                            Software developed by MT Technologies
                        </p>
                    </div>
                </div>
                <!-- Print area end -->
                
            </div>
            <div class="modal-footer" style="background-color: var(--bg-secondary); border-top: 1px solid var(--border-color);">
                <button class="btn-secondary" onclick="closeModal('invoice-modal')">Close</button>
                <button class="btn-danger" id="btn-mark-returned" style="display: none;">
                    <i data-lucide="corner-up-left"></i> Mark Returned (RTO)
                </button>
                <button class="btn-success" onclick="printInvoice()">
                    <i data-lucide="printer"></i> Print Invoice
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 4: WEEKLY CLOSING DETAIL / CONFIRMATION -->
    <div class="modal-overlay" id="weekly-closing-modal">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h2>Weekly Closing & Settle</h2>
                <button class="close-modal-btn" onclick="closeModal('weekly-closing-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 20px;">
                    This option summarizes transactions for the selected dates. When confirmed, this period's closing ledger is committed permanently to database history.
                </p>
                
                <div class="dashboard-card" style="background-color: rgba(255, 255, 255, 0.01); border-radius: var(--border-radius-md); padding: 16px; margin-bottom: 20px;">
                    <h3 style="font-size: 0.95rem; margin-bottom: 12px;">Closing Summary Details</h3>
                    <div class="summary-row" style="margin-bottom: 8px;">
                        <span>Closing Date Range:</span>
                        <span id="close-week-dates" style="font-weight: 600;">N/A</span>
                    </div>
                    <div class="summary-row" style="margin-bottom: 8px;">
                        <span>Sales Volume (Total Orders):</span>
                        <span id="close-week-sales-count" style="font-weight: 600;">0 transactions</span>
                    </div>
                    <div class="summary-row" style="margin-bottom: 8px;">
                        <span>Gross Revenue (Sales):</span>
                        <span id="close-week-revenue" style="color: var(--accent-emerald); font-weight: 600;">Rs. 0.00</span>
                    </div>
                    <div class="summary-row" style="margin-bottom: 8px;">
                        <span>Paid Amount:</span>
                        <span id="close-week-paid" style="color: var(--accent-emerald); font-weight: 600;">Rs. 0.00</span>
                    </div>
                    <div class="summary-row" style="margin-bottom: 8px;">
                        <span>Outstanding Payments:</span>
                        <span id="close-week-outstanding" style="color: var(--accent-rose); font-weight: 600;">Rs. 0.00</span>
                    </div>
                    <div class="summary-row" style="margin-bottom: 8px;">
                        <span>Cost of Goods (COGS):</span>
                        <span id="close-week-cogs" style="color: var(--accent-purple); font-weight: 600;">Rs. 0.00</span>
                    </div>
                    <div class="summary-row" style="border-top: 1px dashed var(--border-color); padding-top: 8px; font-weight: 700;">
                        <span>Net Profit:</span>
                        <span id="close-week-profit" style="color: var(--accent-emerald);">Rs. 0.00</span>
                    </div>
                    <div class="summary-row" style="margin-top: 12px; border-top: 1px dashed var(--border-color); padding-top: 8px; flex-direction: column; align-items: flex-start;">
                        <span style="font-weight: 600; font-size: 0.85rem; margin-bottom: 4px;">Closing Narrative Summary:</span>
                        <input type="text" id="close-week-summary-input" placeholder="e.g. Week 25 settlement concluded smoothly" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; color: #fff;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('weekly-closing-modal')">Cancel</button>
                <button class="btn-success" id="confirm-weekly-closing-btn">
                    Confirm & Settle Ledger
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 5: RECORD NEW SALE (DATABASE FORM) -->
    <div class="modal-overlay" id="sale-modal">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h2>Record New Sale Invoice</h2>
                <button class="close-modal-btn" onclick="closeModal('sale-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="sale-form" style="display: flex; flex-direction: column; overflow: hidden; flex-grow: 1;">
                <div class="modal-body" style="overflow-y: auto; flex-grow: 1;">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="sale-invoice-no">Invoice Number (Auto-assigned)</label>
                            <input type="text" id="sale-invoice-no" required placeholder="Generating..." readonly style="cursor: not-allowed; opacity: 0.8;">
                        </div>
                        <div class="form-group">
                            <label for="sale-date">Sale Date & Time *</label>
                            <input type="datetime-local" id="sale-date" required>
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="sale-customer-select">Customer Profile *</label>
                            <div style="display: flex; gap: 8px;">
                                <select id="sale-customer-select" required>
                                    <option value="">Guest Customer</option>
                                </select>
                                <button type="button" class="btn-secondary" id="sale-add-customer-quick" style="padding: 10px;" title="Quick Add Customer">
                                    <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="sale-platform">Sales Platform *</label>
                            <select id="sale-platform" required>
                                <option value="Manual" selected>Manual</option>
                                <option value="Daraz">Daraz</option>
                                <option value="Shopify">Shopify</option>
                                <option value="Facebook">Facebook</option>
                                <option value="WhatsApp">WhatsApp</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="sale-payment-method">Payment Method *</label>
                            <select id="sale-payment-method" required>
                                <option value="Cash" selected>Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="JazzCash">JazzCash</option>
                                <option value="EasyPaisa">EasyPaisa</option>
                                <option value="Credit">Credit (Udhaar)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sale-payment-status">Payment Status *</label>
                            <select id="sale-payment-status" required>
                                <option value="Paid" selected>Paid</option>
                                <option value="Pending">Pending</option>
                                <option value="Partial">Partial</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dynamic Sale Items Table -->
                    <h3 style="font-size: 1rem; margin: 24px 0 12px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; font-weight: 600;">Sale Items</h3>
                    <div id="sale-items-container" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Dynamically populated product lines -->
                    </div>
                    <button type="button" class="btn-secondary" id="add-sale-item-line-btn" style="margin-top: 12px; width: 100%; justify-content: center;">
                        <i data-lucide="plus"></i> Add Product Line
                    </button>

                    <!-- Financial calculation summaries -->
                    <div class="dashboard-card" style="background-color: rgba(255, 255, 255, 0.02); margin-top: 24px; padding: 20px; border-radius: var(--border-radius-md);">
                        <div class="discount-tax-row">
                            <div class="input-group-inline">
                                <label for="sale-discount">Discount (Rs.)</label>
                                <input type="number" id="sale-discount" min="0" value="0">
                            </div>
                            <div class="input-group-inline">
                                <label for="sale-tax">Tax (%)</label>
                                <input type="number" id="sale-tax" min="0" max="100" value="0">
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span id="sale-calc-subtotal">Rs. 0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Discount Amount:</span>
                                <span id="sale-calc-discount">-Rs. 0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Tax Amount:</span>
                                <span id="sale-calc-tax">Rs. 0.00</span>
                            </div>
                            <div class="summary-row total">
                                <span>Grand Total:</span>
                                <span class="total-val" id="sale-calc-grand-total">Rs. 0.00</span>
                            </div>
                            <div id="sale-calc-profit-row" class="summary-row" style="border-top: 1px dashed var(--border-color); padding-top: 8px; margin-top: 8px;">
                                <span>Net Profit Margin:</span>
                                <span id="sale-calc-profit" style="color: var(--accent-emerald); font-weight: 600;">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('sale-modal')">Cancel</button>
                    <button type="submit" class="btn-success" id="save-sale-submit-btn">Save Sale & Show Invoice</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 6: MANUAL STOCK ADJUSTMENT -->
    <div class="modal-overlay" id="adjust-stock-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manual Stock Adjustment</h2>
                <button class="close-modal-btn" onclick="closeModal('adjust-stock-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="adjust-stock-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="adj-product-select">Select Product *</label>
                        <select id="adj-product-select" required>
                            <option value="">-- Select Product --</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="adj-type">Adjustment Type *</label>
                            <select id="adj-type" required>
                                <option value="add" selected>Add Stock (+)</option>
                                <option value="subtract">Deduct Stock (-)</option>
                                <option value="set">Set Exact Qty (=)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="adj-qty">Quantity Change *</label>
                            <input type="number" id="adj-qty" min="1" required placeholder="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="adj-reason">Recorded Reason/Note *</label>
                        <input type="text" id="adj-reason" required placeholder="e.g. New Shipment received, Damaged stock write-off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('adjust-stock-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="save-adj-submit-btn">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 7: CHANGE PASSWORD (SECURE AUTHENTICATED) -->
    <div class="modal-overlay" id="change-password-modal">
        <div class="modal-content" style="max-width: 440px;">
            <div class="modal-header">
                <h2>Change Account Password</h2>
                <button class="close-modal-btn" onclick="closeModal('change-password-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="change-password-form">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="pwd-current">Current Password *</label>
                        <input type="password" id="pwd-current" required placeholder="Enter current password">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="pwd-new">New Password * (Min 6 chars)</label>
                        <input type="password" id="pwd-new" required minlength="6" placeholder="Enter new password">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="pwd-confirm">Confirm New Password *</label>
                        <input type="password" id="pwd-confirm" required minlength="6" placeholder="Confirm new password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('change-password-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="btn-save-new-pwd">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($userRole === 'owner'): ?>
    <!-- MODAL 8: CREATE / EDIT USER -->
    <div class="modal-overlay" id="user-modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="user-modal-title">Add Team Member</h2>
                <button class="close-modal-btn" onclick="closeModal('user-modal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="user-form">
                <div class="modal-body">
                    <input type="hidden" id="user-id-field">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="user-name">Full Name *</label>
                        <input type="text" id="user-name" required placeholder="e.g. Hamza Tariq">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="user-username">Username *</label>
                        <input type="text" id="user-username" required placeholder="e.g. hamza">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="user-email">Email Address</label>
                        <input type="email" id="user-email" placeholder="e.g. staff@primehub.local">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="user-role">Role *</label>
                        <select id="user-role" required style="width: 100%; padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; color: #fff;">
                            <option value="sales">Sales Staff (POS, Sales creation, Customer register)</option>
                            <option value="manager">Manager (Operational oversight, stock, reports)</option>
                            <option value="accountant">Accountant (Ledgers, customer payments, weekly closings)</option>
                            <option value="owner">Owner / Super Admin (Full access)</option>
                        </select>
                    </div>
                    <div class="form-group" id="user-password-group" style="margin-bottom: 14px;">
                        <label for="user-password">Account Password *</label>
                        <input type="password" id="user-password" placeholder="Min 6 characters">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('user-modal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="btn-save-user">Save User</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Core App Logic (Updated for Web API & Multi-User Support) -->
    <script src="assets/js/app.js"></script>
</body>
</html>
