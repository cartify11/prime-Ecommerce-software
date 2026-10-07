/**
 * Prime E Commerce Hub - Client Application Logic (Web Edition)
 * Connected directly to PHP REST API + MySQL Backend
 */

// Global Application State Cache
const App = {
    user: window.CURRENT_USER || null,
    csrfToken: window.CSRF_TOKEN || '',
    settings: {
        store_name: "Prime E commerce Hub",
        store_tagline: "Mujahid colony warehouse",
        store_phone: "03126772989",
        store_email: "admin@primehub.local",
        store_address: "Mujahid colony warehouse",
        currency_symbol: "Rs.",
        default_tax: 0,
        system_theme: "dark"
    },
    products: [],
    customers: [],
    investors: [],
    charts: {
        trend: null,
        platforms: null
    }
};

// --- CSRF & API REQUEST HELPER ---
async function apiRequest(endpoint, options = {}) {
    const url = endpoint.startsWith('http') ? endpoint : (endpoint.startsWith('/') ? endpoint.substring(1) : endpoint);
    const headers = options.headers || {};

    if (!headers['X-CSRF-Token']) {
        headers['X-CSRF-Token'] = App.csrfToken;
    }

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }

    options.headers = headers;

    try {
        const response = await fetch(url, options);
        if (response.status === 401) {
            window.location.href = 'login.php?expired=1';
            return { success: false, message: 'Session expired' };
        }
        const data = await response.json();
        return data;
    } catch (err) {
        console.error(`API Error on ${url}:`, err);
        return { success: false, message: 'Network or server error occurred.' };
    }
}

// --- UTILITIES & FORMATTERS ---
function formatCurrency(val) {
    const sym = App.settings.currency_symbol || "Rs.";
    const num = parseFloat(val) || 0;
    return sym + " " + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(isoStr) {
    if (!isoStr) return "N/A";
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return isoStr;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getLocalDateString(date = new Date()) {
    const d = new Date(date);
    if (isNaN(d.getTime())) return "";
    const offset = d.getTimezoneOffset();
    const localDate = new Date(d.getTime() - (offset * 60 * 1000));
    return localDate.toISOString().split('T')[0];
}

// --- MODAL UTILS ---
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add("active");
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove("active");
}

// --- CLOCK & THEME ---
function initializeClock() {
    function updateClock() {
        const now = new Date();
        const timeEl = document.getElementById("header-time");
        const dateEl = document.getElementById("header-date");
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US');
        if (dateEl) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('en-US', options);
        }
    }
    updateClock();
    setInterval(updateClock, 1000);
}

function applyTheme(theme = null) {
    const activeTheme = theme || localStorage.getItem("prime_theme") || App.settings.system_theme || "dark";
    if (activeTheme === "light") {
        document.body.classList.add("light-theme");
    } else {
        document.body.classList.remove("light-theme");
    }
    localStorage.setItem("prime_theme", activeTheme);
}

// --- TAB ROUTING ---
function setupRouting() {
    const navItems = document.querySelectorAll(".nav-menu .nav-item");
    const sections = document.querySelectorAll(".page-section");

    function navigate(tabId) {
        navItems.forEach(item => {
            if (item.getAttribute("data-tab") === tabId) {
                item.classList.add("active");
            } else {
                item.classList.remove("active");
            }
        });

        sections.forEach(sec => {
            if (sec.id === tabId) {
                sec.classList.add("active");
            } else {
                sec.classList.remove("active");
            }
        });

        // Trigger tab-specific refresh
        if (tabId === "dashboard") loadDashboard();
        if (tabId === "products") loadProducts();
        if (tabId === "customers") loadCustomers();
        if (tabId === "sales") loadSales();
        if (tabId === "inventory") loadInventory();
        if (tabId === "reports") loadReports();
        if (tabId === "invoices") loadInvoices();
        if (tabId === "owner") loadInvestors();
        if (tabId === "users") loadUsers();
        if (tabId === "settings") loadSettings();

        lucide.createIcons();
    }

    navItems.forEach(item => {
        item.addEventListener("click", (e) => {
            e.preventDefault();
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                window.location.hash = tabId;
                navigate(tabId);
            }
        });
    });

    const hash = window.location.hash.replace("#", "") || "dashboard";
    navigate(hash);

    window.addEventListener("hashchange", () => {
        const newHash = window.location.hash.replace("#", "") || "dashboard";
        navigate(newHash);
    });

    const dashViewSalesBtn = document.getElementById("dash-view-all-sales-btn");
    if (dashViewSalesBtn) {
        dashViewSalesBtn.addEventListener("click", () => {
            window.location.hash = "sales";
            navigate("sales");
        });
    }

    const lowStockCard = document.getElementById("metric-low-stock-card");
    if (lowStockCard) {
        lowStockCard.addEventListener("click", () => {
            window.location.hash = "inventory";
            navigate("inventory");
            const filter = document.getElementById("inventory-stock-filter");
            if (filter) {
                filter.value = "Low";
                loadInventory();
            }
        });
    }
}

// --- SETTINGS ENGINE ---
async function loadSettings() {
    const res = await apiRequest('api/settings/index.php');
    if (res.success && res.data) {
        App.settings = { ...App.settings, ...res.data };
        populateSettingsForms();
        applyStoreSettings();
    }
}

function populateSettingsForms() {
    const s = App.settings;
    const nameEl = document.getElementById("set-store-name");
    const taglineEl = document.getElementById("set-store-tagline");
    const phoneEl = document.getElementById("set-store-phone");
    const emailEl = document.getElementById("set-store-email");
    const addrEl = document.getElementById("set-store-address");
    const taxEl = document.getElementById("set-default-tax");
    const currEl = document.getElementById("set-currency-symbol");

    if (nameEl) nameEl.value = s.store_name || "";
    if (taglineEl) taglineEl.value = s.store_tagline || "";
    if (phoneEl) phoneEl.value = s.store_phone || "";
    if (emailEl) emailEl.value = s.store_email || "";
    if (addrEl) addrEl.value = s.store_address || "";
    if (taxEl) taxEl.value = s.default_tax || 0;
    if (currEl) currEl.value = s.currency_symbol || "Rs.";
}

function applyStoreSettings() {
    const s = App.settings;
    const title = s.store_name || "Prime E commerce Hub";
    const brandTitle = document.getElementById("sidebar-brand-title");
    if (brandTitle) brandTitle.textContent = title;

    const invTitle = document.getElementById("invoice-brand-title");
    const invTag = document.getElementById("invoice-brand-tagline");
    const invContact = document.getElementById("invoice-brand-contact");
    if (invTitle) invTitle.textContent = title;
    if (invTag) invTag.textContent = s.store_tagline || "";
    if (invContact) invContact.textContent = `Phone: ${s.store_phone || ''} | Email: ${s.store_email || ''}`;
}

function setupSettingsHandlers() {
    const profForm = document.getElementById("settings-profile-form");
    if (profForm) {
        profForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const payload = {
                store_name: document.getElementById("set-store-name").value.trim(),
                store_tagline: document.getElementById("set-store-tagline").value.trim(),
                store_phone: document.getElementById("set-store-phone").value.trim(),
                store_email: document.getElementById("set-store-email").value.trim(),
                store_address: document.getElementById("set-store-address").value.trim()
            };

            const btn = profForm.querySelector("button[type=submit]");
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader"></i> Saving...`;

            const res = await apiRequest('api/settings/index.php', { method: 'POST', body: payload });
            btn.disabled = false;
            btn.innerHTML = `<i data-lucide="save"></i> Save Profile Settings`;
            lucide.createIcons();

            if (res.success) {
                App.settings = { ...App.settings, ...payload };
                applyStoreSettings();
                alert("Store profile settings updated successfully!");
            } else {
                alert(res.message || "Failed to save settings");
            }
        });
    }

    const finForm = document.getElementById("settings-financial-form");
    if (finForm) {
        finForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const payload = {
                default_tax: parseFloat(document.getElementById("set-default-tax").value) || 0,
                currency_symbol: document.getElementById("set-currency-symbol").value.trim() || "Rs."
            };

            const btn = finForm.querySelector("button[type=submit]");
            btn.disabled = true;
            const res = await apiRequest('api/settings/index.php', { method: 'POST', body: payload });
            btn.disabled = false;
            if (res.success) {
                App.settings = { ...App.settings, ...payload };
                alert("Financial default settings updated successfully!");
                loadDashboard();
            } else {
                alert(res.message || "Failed to update financial defaults");
            }
        });
    }

    // Restore Database Trigger
    const importFile = document.getElementById("btn-import-file-db");
    if (importFile) {
        importFile.addEventListener("change", async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (!confirm(`Warning: Restoring backup will replace current database tables with the backup contents. Proceed?`)) {
                importFile.value = "";
                return;
            }

            const formData = new FormData();
            formData.append('backup_file', file);
            formData.append('csrf_token', App.csrfToken);

            const res = await apiRequest('api/backup/restore.php', { method: 'POST', body: formData });
            importFile.value = "";

            if (res.success) {
                alert("Database backup restored successfully!");
                location.reload();
            } else {
                alert("Restore failed: " + (res.message || "Unknown error"));
            }
        });
    }

    // Import Legacy database.json Trigger
    const migrateFile = document.getElementById("btn-migrate-file-db");
    if (migrateFile) {
        migrateFile.addEventListener("change", async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (!confirm(`Import client data from ${file.name} into MySQL?`)) {
                migrateFile.value = "";
                return;
            }

            const formData = new FormData();
            formData.append('json_file', file);
            formData.append('csrf_token', App.csrfToken);

            const res = await apiRequest('api/migration/import_json.php', { method: 'POST', body: formData });
            migrateFile.value = "";

            if (res.success) {
                const rep = res.data;
                alert(`Migration Complete!\n- Products: ${rep.products_imported}\n- Customers: ${rep.customers_imported}\n- Sales: ${rep.sales_imported}\n- Investors: ${rep.investors_imported}\n- Payments: ${rep.payments_imported}`);
                location.reload();
            } else {
                alert("Migration failed: " + (res.message || "Unknown error"));
            }
        });
    }

    // Demo Data Button
    const demoBtn = document.getElementById("load-demo-btn");
    if (demoBtn) {
        demoBtn.addEventListener("click", async () => {
            if (!confirm("Load sample demo products, customers, and investors into MySQL?")) return;
            demoBtn.disabled = true;
            demoBtn.innerHTML = "Loading Demo Data...";
            const res = await apiRequest('api/demo/load.php', { method: 'POST' });
            demoBtn.disabled = false;
            demoBtn.innerHTML = `<i data-lucide="database"></i> Load Sample Demo Data`;
            lucide.createIcons();

            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message || "Failed to load demo data");
            }
        });
    }
}

// --- DASHBOARD ENGINE ---
async function loadDashboard() {
    const res = await apiRequest('api/dashboard/stats.php');
    if (!res.success || !res.data) return;

    const d = res.data;

    // Overview cards
    document.getElementById("dash-revenue").textContent = formatCurrency(d.total_revenue);
    document.getElementById("dash-profit").textContent = formatCurrency(d.net_profit);
    document.getElementById("dash-sales-count").textContent = d.total_sales_count;
    document.getElementById("dash-low-stock-count").textContent = d.low_stock_count;

    // Period Audits
    document.getElementById("dash-today-sales").textContent = formatCurrency(d.today_sales);
    document.getElementById("dash-today-profit").textContent = formatCurrency(d.today_profit);
    document.getElementById("dash-weekly-sales").textContent = formatCurrency(d.weekly_sales);
    document.getElementById("dash-monthly-sales").textContent = formatCurrency(d.monthly_sales);

    // Inventory Assets
    document.getElementById("dash-inv-buying-val").textContent = formatCurrency(d.asset_cost_value);
    document.getElementById("dash-inv-selling-val").textContent = formatCurrency(d.asset_selling_value);
    document.getElementById("dash-inv-expected-profit").textContent = formatCurrency(d.expected_profit);
    document.getElementById("dash-total-products").textContent = `${d.total_products} items`;

    // Orders & Payments
    document.getElementById("dash-total-customers").textContent = `${d.total_customers} profiles`;
    document.getElementById("dash-total-orders").textContent = `${d.total_orders} orders`;
    document.getElementById("dash-paid-payments").textContent = formatCurrency(d.paid_payments);
    document.getElementById("dash-pending-payments").textContent = formatCurrency(d.pending_payments);

    // Low stock badge & list
    const badge = document.getElementById("low-stock-badge-lbl");
    if (badge) badge.textContent = `${d.low_stock_count} items`;

    const lowStockList = document.getElementById("dashboard-low-stock-list");
    const notifItemsList = document.getElementById("notification-items-list");
    const notifBellCount = document.getElementById("notif-count");
    const dropdownCount = document.getElementById("dropdown-notif-count");

    if (notifBellCount) notifBellCount.textContent = d.low_stock_count;
    if (dropdownCount) dropdownCount.textContent = `${d.low_stock_count} Low Stock`;

    if (lowStockList) {
        lowStockList.innerHTML = "";
        if (!d.low_stock_items || d.low_stock_items.length === 0) {
            lowStockList.innerHTML = `<div style="text-align:center; color:var(--text-secondary); padding:20px;">All stock levels healthy!</div>`;
        } else {
            d.low_stock_items.forEach(item => {
                const row = document.createElement("div");
                row.className = "low-stock-item";
                row.style.cssText = "display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;";
                row.innerHTML = `
                    <div>
                        <strong>${item.name}</strong> <span style="font-size:0.75rem; color:var(--text-secondary);">(${item.sku})</span>
                    </div>
                    <div>
                        <span class="badge ${item.stock_quantity === 0 ? 'danger' : 'warning'}">${item.stock_quantity} left</span>
                    </div>
                `;
                lowStockList.appendChild(row);
            });
        }
    }

    if (notifItemsList) {
        notifItemsList.innerHTML = "";
        if (!d.low_stock_items || d.low_stock_items.length === 0) {
            notifItemsList.innerHTML = `<div style="text-align:center; padding:12px; color:var(--text-secondary); font-size:0.8rem;">No active alerts.</div>`;
        } else {
            d.low_stock_items.forEach(item => {
                const el = document.createElement("div");
                el.style.cssText = "padding:8px 12px; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.8rem;";
                el.innerHTML = `<strong>${item.name}</strong>: only <span style="color:var(--accent-rose); font-weight:bold;">${item.stock_quantity}</span> remaining.`;
                notifItemsList.appendChild(el);
            });
        }
    }

    // Render Charts
    renderTrendChart(d.chart);
    renderPlatformChart(d.platforms);

    // Recent Transactions
    loadRecentSales();
}

function renderTrendChart(chartData) {
    const canvas = document.getElementById("revenueProfitChart");
    if (!canvas || !chartData) return;

    if (App.charts.trend) {
        App.charts.trend.destroy();
    }

    const ctx = canvas.getContext('2d');
    App.charts.trend = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Revenue (Rs.)',
                    data: chartData.revenue,
                    backgroundColor: 'rgba(226, 177, 60, 0.75)',
                    borderColor: '#e2b13c',
                    borderRadius: 6
                },
                {
                    label: 'Net Profit (Rs.)',
                    data: chartData.profit,
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderColor: '#10b981',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#94a3b8', font: { size: 12 } }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                }
            }
        }
    });
}

function renderPlatformChart(platforms) {
    const canvas = document.getElementById("platformShareChart");
    if (!canvas) return;

    if (App.charts.platforms) {
        App.charts.platforms.destroy();
    }

    const labels = platforms && platforms.length > 0 ? platforms.map(p => p.platform) : ['Manual'];
    const dataVals = platforms && platforms.length > 0 ? platforms.map(p => parseFloat(p.total)) : [1];

    const ctx = canvas.getContext('2d');
    App.charts.platforms = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: dataVals,
                backgroundColor: [
                    '#e2b13c',
                    '#10b981',
                    '#6366f1',
                    '#0284c7',
                    '#f43f5e',
                    '#8b5cf6'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#94a3b8', boxWidth: 12 }
                }
            }
        }
    });
}

async function loadRecentSales() {
    const res = await apiRequest('api/sales/index.php?limit=5');
    const tbody = document.getElementById("dashboard-recent-sales-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const sales = res.data || [];

    if (sales.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-secondary); padding:20px;">No sales recorded yet.</td></tr>`;
        return;
    }

    sales.forEach(s => {
        const tr = document.createElement("tr");
        const itemsSummary = s.items ? s.items.reduce((acc, it) => acc + (parseInt(it.quantity) || 0), 0) : 'N/A';
        tr.innerHTML = `
            <td><strong>${s.invoice_no}</strong></td>
            <td>${formatDate(s.sale_date)}</td>
            <td>${s.customer_name || 'Guest Customer'}</td>
            <td>${itemsSummary} items</td>
            <td><strong style="color:var(--accent-emerald)">${formatCurrency(s.grand_total)}</strong></td>
            <td style="color:var(--accent-emerald)">${formatCurrency(s.net_profit)}</td>
            <td>
                <button class="btn-primary" style="padding:4px 8px; font-size:0.75rem;" onclick="viewInvoice(${s.id})">
                    <i data-lucide="eye" style="width:12px; height:12px;"></i> View
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    lucide.createIcons();
}

// --- PRODUCTS ENGINE ---
async function loadProducts() {
    const search = document.getElementById("product-search-input") ? document.getElementById("product-search-input").value.trim() : "";
    const cat = document.getElementById("product-category-filter") ? document.getElementById("product-category-filter").value : "All";
    const stock = document.getElementById("product-stock-filter") ? document.getElementById("product-stock-filter").value : "All";

    const res = await apiRequest(`api/products/index.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(cat)}&stock=${encodeURIComponent(stock)}`);
    const tbody = document.getElementById("products-table-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    App.products = res.data || [];

    if (App.products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--text-secondary); padding:24px;">No products found.</td></tr>`;
        return;
    }

    App.products.forEach(p => {
        const stockQty = parseInt(p.stock_quantity);
        const threshold = parseInt(p.low_stock_threshold);
        let statusBadge = `<span class="badge success">Healthy</span>`;
        if (stockQty === 0) {
            statusBadge = `<span class="badge danger">Out of Stock</span>`;
        } else if (stockQty <= threshold) {
            statusBadge = `<span class="badge warning">Low Stock</span>`;
        }

        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${p.sku}</strong></td>
            <td>${p.name} ${p.investor_name ? `<br><span style="font-size:0.75rem; color:var(--accent-blue);">Sponsor: ${p.investor_name}</span>` : ''}</td>
            <td><span class="category-tag">${p.category || 'General'}</span></td>
            <td>${formatCurrency(p.purchase_price)}</td>
            <td>${formatCurrency(p.selling_price)}</td>
            <td><strong>${stockQty}</strong></td>
            <td>${statusBadge}</td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="btn-icon edit" onclick="editProduct(${p.id})" title="Edit Product">
                        <i data-lucide="edit-3" style="width:14px; height:14px;"></i>
                    </button>
                    <button class="btn-icon delete" onclick="deleteProduct(${p.id}, '${p.name.replace(/'/g, "\\'")}')" title="Delete Product">
                        <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateCategoryFilters();
    lucide.createIcons();
}

async function updateCategoryFilters() {
    const res = await apiRequest('api/products/categories.php');
    if (!res.success || !res.data) return;

    const cats = res.data;
    const catSelects = [
        document.getElementById("product-category-filter"),
        document.getElementById("inventory-category-filter")
    ];

    catSelects.forEach(select => {
        if (!select) return;
        const currentVal = select.value;
        let html = `<option value="All">All Categories</option>`;
        cats.forEach(c => {
            html += `<option value="${c}">${c}</option>`;
        });
        select.innerHTML = html;
        if (cats.includes(currentVal)) select.value = currentVal;
    });

    const dataList = document.getElementById("category-datalist");
    if (dataList) {
        dataList.innerHTML = cats.map(c => `<option value="${c}">`).join('');
    }
}

async function populateInvestorDropdown(selectedId = null) {
    const res = await apiRequest('api/investors/index.php');
    const select = document.getElementById("prod-investor");
    if (!select) return;

    let html = `<option value="">-- Self-Funded / Store Owned --</option>`;
    if (res.success && res.data) {
        App.investors = res.data;
        res.data.forEach(inv => {
            const isSel = selectedId && String(selectedId) === String(inv.id) ? 'selected' : '';
            html += `<option value="${inv.id}" ${isSel}>${inv.name} (Cap: ${formatCurrency(inv.investment_amount)})</option>`;
        });
    }
    select.innerHTML = html;
}

function setupProductHandlers() {
    const addBtn = document.getElementById("add-product-btn");
    const form = document.getElementById("product-form");

    if (addBtn) {
        addBtn.addEventListener("click", () => {
            form.reset();
            document.getElementById("product-id-field").value = "";
            document.getElementById("product-modal-title").textContent = "Add New Product";
            populateInvestorDropdown();
            openModal("product-modal");
        });
    }

    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const id = document.getElementById("product-id-field").value;
            const payload = {
                sku: document.getElementById("prod-sku").value.trim(),
                name: document.getElementById("prod-name").value.trim(),
                category: document.getElementById("prod-category").value.trim(),
                purchase_price: parseFloat(document.getElementById("prod-purchase-price").value) || 0,
                selling_price: parseFloat(document.getElementById("prod-selling-price").value) || 0,
                stock_quantity: parseInt(document.getElementById("prod-stock").value) || 0,
                low_stock_threshold: parseInt(document.getElementById("prod-threshold").value) || 5,
                investor_id: document.getElementById("prod-investor").value || null
            };

            const submitBtn = document.getElementById("save-product-submit-btn");
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";

            const endpoint = id ? `api/products/manage.php?id=${id}` : 'api/products/index.php';
            const res = await apiRequest(endpoint, { method: 'POST', body: payload });

            submitBtn.disabled = false;
            submitBtn.textContent = "Save Product";

            if (res.success) {
                closeModal("product-modal");
                loadProducts();
                loadDashboard();
            } else {
                alert(res.message || "Failed to save product");
            }
        });
    }

    const searchInput = document.getElementById("product-search-input");
    if (searchInput) searchInput.addEventListener("input", loadProducts);

    const catFilter = document.getElementById("product-category-filter");
    if (catFilter) catFilter.addEventListener("change", loadProducts);

    const stockFilter = document.getElementById("product-stock-filter");
    if (stockFilter) stockFilter.addEventListener("change", loadProducts);
}

async function editProduct(id) {
    const res = await apiRequest(`api/products/manage.php?id=${id}`);
    if (!res.success || !res.data) {
        alert("Failed to load product details");
        return;
    }

    const p = res.data;
    document.getElementById("product-id-field").value = p.id;
    document.getElementById("prod-sku").value = p.sku;
    document.getElementById("prod-name").value = p.name;
    document.getElementById("prod-category").value = p.category;
    document.getElementById("prod-purchase-price").value = p.purchase_price;
    document.getElementById("prod-selling-price").value = p.selling_price;
    document.getElementById("prod-stock").value = p.stock_quantity;
    document.getElementById("prod-threshold").value = p.low_stock_threshold;

    populateInvestorDropdown(p.investor_id);
    document.getElementById("product-modal-title").textContent = "Edit Product Details";
    openModal("product-modal");
}

async function deleteProduct(id, name) {
    if (!confirm(`Are you sure you want to remove "${name}"? If it has prior sales records, it will be safely archived.`)) return;

    const res = await apiRequest(`api/products/manage.php?id=${id}`, { method: 'DELETE' });
    if (res.success) {
        alert(res.message);
        loadProducts();
        loadDashboard();
    } else {
        alert(res.message || "Failed to delete product");
    }
}

// --- CUSTOMERS ENGINE ---
async function loadCustomers() {
    const search = document.getElementById("customer-search-input") ? document.getElementById("customer-search-input").value.trim() : "";
    const res = await apiRequest(`api/customers/index.php?search=${encodeURIComponent(search)}`);
    const tbody = document.getElementById("customers-table-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    App.customers = res.data || [];

    if (App.customers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--text-secondary); padding:24px;">No customer profiles found.</td></tr>`;
        return;
    }

    App.customers.forEach(c => {
        const bal = parseFloat(c.outstanding_balance) || 0;
        const balDisplay = bal < 0 ? `Advance: ${formatCurrency(Math.abs(bal))}` : formatCurrency(bal);
        const balColor = bal > 0 ? 'var(--accent-rose)' : (bal < 0 ? 'var(--accent-emerald)' : 'var(--text-secondary)');

        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${c.name}</strong></td>
            <td>${c.phone}</td>
            <td>${c.email || '<span style="color:var(--text-secondary)">N/A</span>'}</td>
            <td>${c.address || '<span style="color:var(--text-secondary)">N/A</span>'}</td>
            <td>${c.total_visits} orders</td>
            <td><strong style="color:var(--accent-emerald)">${formatCurrency(c.total_spent)}</strong></td>
            <td><strong style="color:${balColor}">${balDisplay}</strong></td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="btn-icon info" onclick="viewCustomerLedger(${c.id})" title="View Ledger" style="background-color:rgba(99,102,241,0.1); color:#6366f1;">
                        <i data-lucide="book-open" style="width:14px; height:14px;"></i>
                    </button>
                    <button class="btn-icon add" onclick="openRecordPaymentModal(${c.id})" title="Record Payment" style="background-color:rgba(16,185,129,0.1); color:#10b981;">
                        <i data-lucide="plus-circle" style="width:14px; height:14px;"></i>
                    </button>
                    <button class="btn-icon edit" onclick="editCustomer(${c.id})" title="Edit Profile">
                        <i data-lucide="edit-3" style="width:14px; height:14px;"></i>
                    </button>
                    <button class="btn-icon delete" onclick="deleteCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}')" title="Archive Profile">
                        <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateSalesCustomerDropdowns();
    lucide.createIcons();
}

function updateSalesCustomerDropdowns() {
    const mainSelect = document.getElementById("sale-customer-select");
    const filterSelect = document.getElementById("sales-customer-filter");
    if (!mainSelect) return;

    let mainHtml = `<option value="">Guest Customer</option>`;
    let filterHtml = `<option value="All">All Customers</option><option value="Guest">Guest Customer</option>`;

    App.customers.forEach(c => {
        const opt = `<option value="${c.id}">${c.name} (${c.phone})</option>`;
        mainHtml += opt;
        filterHtml += opt;
    });

    mainSelect.innerHTML = mainHtml;
    if (filterSelect) {
        const cur = filterSelect.value;
        filterSelect.innerHTML = filterHtml;
        if (cur) filterSelect.value = cur;
    }
}

function setupCustomerHandlers() {
    const addBtn = document.getElementById("add-customer-btn");
    const form = document.getElementById("customer-form");

    if (addBtn) {
        addBtn.addEventListener("click", () => {
            form.reset();
            document.getElementById("customer-id-field").value = "";
            document.getElementById("customer-modal-title").textContent = "Add Customer Profile";
            openModal("customer-modal");
        });
    }

    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const id = document.getElementById("customer-id-field").value;
            const payload = {
                name: document.getElementById("cust-name").value.trim(),
                phone: document.getElementById("cust-phone").value.trim(),
                email: document.getElementById("cust-email").value.trim(),
                address: document.getElementById("cust-address").value.trim()
            };

            const submitBtn = document.getElementById("save-customer-submit-btn");
            submitBtn.disabled = true;

            const endpoint = id ? `api/customers/manage.php?id=${id}` : 'api/customers/index.php';
            const res = await apiRequest(endpoint, { method: 'POST', body: payload });

            submitBtn.disabled = false;

            if (res.success) {
                closeModal("customer-modal");
                loadCustomers();
            } else {
                alert(res.message || "Failed to save customer");
            }
        });
    }

    const search = document.getElementById("customer-search-input");
    if (search) search.addEventListener("input", loadCustomers);

    // Customer Payment Submission Form
    const payForm = document.getElementById("customer-payment-form");
    if (payForm) {
        payForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const custId = document.getElementById("payment-customer-id").value;
            const payload = {
                customer_id: custId,
                amount: parseFloat(document.getElementById("payment-amount").value) || 0,
                payment_date: document.getElementById("payment-date").value,
                payment_method: document.getElementById("payment-method").value,
                notes: document.getElementById("payment-notes").value.trim()
            };

            const btn = document.getElementById("save-payment-submit-btn");
            btn.disabled = true;

            const res = await apiRequest('api/payments/index.php', { method: 'POST', body: payload });
            btn.disabled = false;

            if (res.success) {
                closeModal("customer-payment-modal");
                alert(res.message);
                loadCustomers();
                loadDashboard();
            } else {
                alert(res.message || "Payment recording failed");
            }
        });
    }
}

async function editCustomer(id) {
    const res = await apiRequest(`api/customers/manage.php?id=${id}`);
    if (!res.success || !res.data) return;

    const c = res.data;
    document.getElementById("customer-id-field").value = c.id;
    document.getElementById("cust-name").value = c.name;
    document.getElementById("cust-phone").value = c.phone;
    document.getElementById("cust-email").value = c.email || "";
    document.getElementById("cust-address").value = c.address || "";
    document.getElementById("customer-modal-title").textContent = "Edit Customer Profile";
    openModal("customer-modal");
}

async function deleteCustomer(id, name) {
    if (!confirm(`Archive customer "${name}"? Historical invoices and ledgers will remain safely intact.`)) return;

    const res = await apiRequest(`api/customers/manage.php?id=${id}`, { method: 'DELETE' });
    if (res.success) {
        alert(res.message);
        loadCustomers();
    } else {
        alert(res.message || "Failed to archive customer");
    }
}

function openRecordPaymentModal(customerId) {
    const cust = App.customers.find(c => String(c.id) === String(customerId));
    if (!cust) return;

    document.getElementById("payment-customer-id").value = cust.id;
    document.getElementById("payment-customer-name").value = cust.name;

    const bal = parseFloat(cust.outstanding_balance) || 0;
    document.getElementById("payment-current-balance").value = bal < 0 ? `Advance: ${formatCurrency(Math.abs(bal))}` : formatCurrency(bal);

    document.getElementById("payment-amount").value = "";
    document.getElementById("payment-notes").value = "";

    const localNow = new Date();
    const localISO = new Date(localNow.getTime() - localNow.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    document.getElementById("payment-date").value = localISO;

    openModal("customer-payment-modal");
}

async function viewCustomerLedger(customerId) {
    const res = await apiRequest(`api/customers/ledger.php?id=${customerId}`);
    if (!res.success || !res.data) {
        alert("Failed to load customer ledger statement.");
        return;
    }

    const { customer, outstanding_balance, lines, settings } = res.data;
    const printArea = document.getElementById("ledger-print-area");
    if (!printArea) return;

    const sName = settings.store_name || App.settings.store_name || 'Prime E commerce Hub';
    const sTag = settings.store_tagline || App.settings.store_tagline || '';
    const sPhone = settings.store_phone || App.settings.store_phone || '';
    const sEmail = settings.store_email || App.settings.store_email || '';

    let rowsHtml = "";
    if (lines.length === 0) {
        rowsHtml = `<tr><td colspan="5" style="padding:24px; text-align:center; color:var(--text-secondary);">No ledger records found for this customer.</td></tr>`;
    } else {
        rowsHtml = lines.map(line => {
            const deb = line.debit > 0 ? formatCurrency(line.debit) : '-';
            const cred = line.credit > 0 ? formatCurrency(line.credit) : '-';
            const balVal = line.balance;
            const balText = balVal < 0 ? `(${formatCurrency(Math.abs(balVal))})` : formatCurrency(balVal);
            const balColor = balVal > 0 ? 'var(--accent-rose)' : (balVal < 0 ? 'var(--accent-emerald)' : 'inherit');

            return `
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem;">
                    <td style="padding: 10px 8px;">${formatDate(line.date)}</td>
                    <td style="padding: 10px 8px;">
                        <strong>${line.desc}</strong>
                        ${line.meta ? `<br><span style="font-size:0.75rem; color:var(--text-secondary);">${line.meta}</span>` : ''}
                    </td>
                    <td style="padding: 10px 8px; text-align: right; color: ${line.debit > 0 ? 'var(--accent-rose)' : 'inherit'};">${deb}</td>
                    <td style="padding: 10px 8px; text-align: right; color: ${line.credit > 0 ? 'var(--accent-emerald)' : 'inherit'};">${cred}</td>
                    <td style="padding: 10px 8px; text-align: right; font-weight: 600; color: ${balColor};">${balText}</td>
                </tr>
            `;
        }).join('');
    }

    printArea.innerHTML = `
        <div style="color: var(--text-primary);">
            <div style="display: flex; justify-content: space-between; border-bottom: 2px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
                <div>
                    <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--accent-blue); margin-bottom: 4px;">${sName}</h1>
                    <p style="font-size: 0.8rem; color: var(--text-secondary);">${sTag}</p>
                    <p style="font-size: 0.8rem; color: var(--text-secondary);">Phone: ${sPhone} | Email: ${sEmail}</p>
                </div>
                <div style="text-align: right;">
                    <h2 style="font-size: 1.2rem; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Customer Account Statement</h2>
                    <p style="font-size: 0.8rem; color: var(--text-secondary);">Generated: ${new Date().toLocaleString()}</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div style="background: rgba(255,255,255,0.02); padding: 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h3 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Customer Details</h3>
                    <p style="font-size: 1rem; font-weight: 700; margin-bottom: 2px;">${customer.name}</p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Phone: ${customer.phone}</p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Email: ${customer.email || 'N/A'}</p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Address: ${customer.address || 'N/A'}</p>
                </div>
                <div style="background: ${outstanding_balance < 0 ? 'rgba(16,185,129,0.05)' : 'rgba(244,63,94,0.05)'}; padding: 14px; border-radius: 8px; border: 1px solid ${outstanding_balance < 0 ? 'rgba(16,185,129,0.2)' : 'rgba(244,63,94,0.2)'}; display:flex; flex-direction:column; justify-content:center;">
                    <h3 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 4px;">${outstanding_balance < 0 ? 'Advance Balance' : 'Outstanding Balance Due'}</h3>
                    <p style="font-size: 1.6rem; font-weight: 700; color: ${outstanding_balance < 0 ? 'var(--accent-emerald)' : 'var(--accent-rose)'};">${outstanding_balance < 0 ? formatCurrency(Math.abs(outstanding_balance)) : formatCurrency(outstanding_balance)}</p>
                </div>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left; font-size: 0.8rem; text-transform: uppercase; color: var(--text-secondary);">
                        <th style="padding: 8px; width: 15%;">Date</th>
                        <th style="padding: 8px; width: 40%;">Description & Meta</th>
                        <th style="padding: 8px; width: 15%; text-align: right;">Debit (Purchases)</th>
                        <th style="padding: 8px; width: 15%; text-align: right;">Credit (Paid)</th>
                        <th style="padding: 8px; width: 15%; text-align: right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        </div>
    `;

    const printBtn = document.getElementById("btn-print-ledger");
    if (printBtn) {
        printBtn.onclick = () => window.print();
    }

    openModal("customer-ledger-modal");
}

// --- SALES ENGINE ---
async function loadSales() {
    const search = document.getElementById("sales-search-input") ? document.getElementById("sales-search-input").value.trim() : "";
    const customer = document.getElementById("sales-customer-filter") ? document.getElementById("sales-customer-filter").value : "All";
    const date = document.getElementById("sales-date-filter") ? document.getElementById("sales-date-filter").value : "";

    const res = await apiRequest(`api/sales/index.php?search=${encodeURIComponent(search)}&customer=${encodeURIComponent(customer)}&date=${encodeURIComponent(date)}`);
    const tbody = document.getElementById("sales-table-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const sales = res.data || [];

    if (sales.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; color:var(--text-secondary); padding:24px;">No sales records found.</td></tr>`;
        return;
    }

    sales.forEach(s => {
        const qtyCount = s.items ? s.items.reduce((acc, it) => acc + (parseInt(it.quantity) || 0), 0) : 'N/A';
        const isReturned = s.delivery_status === 'Returned / Failed Delivery';

        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${s.invoice_no}</strong></td>
            <td>${formatDate(s.sale_date)}</td>
            <td>${s.customer_name || 'Guest Customer'}</td>
            <td>${qtyCount} units</td>
            <td>${formatCurrency(s.subtotal)}</td>
            <td>-${formatCurrency(s.discount)}</td>
            <td>${formatCurrency(s.tax_amount)}</td>
            <td><strong style="color:var(--accent-emerald)">${formatCurrency(s.grand_total)}</strong></td>
            <td style="color:${isReturned ? 'var(--accent-rose)' : 'var(--accent-emerald)'}; font-weight:600;">${isReturned ? 'RETURNED' : formatCurrency(s.net_profit)}</td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="btn-primary" style="padding:4px 8px; font-size:0.75rem;" onclick="viewInvoice(${s.id})" title="View Invoice">
                        <i data-lucide="eye" style="width:12px; height:12px;"></i> View
                    </button>
                    <button class="btn-icon delete" onclick="deleteSale(${s.id}, '${s.invoice_no}')" title="Delete Sale">
                        <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    lucide.createIcons();
}

function addSaleItemLine() {
    const container = document.getElementById("sale-items-container");
    if (!container) return;

    const lineId = "line_" + Date.now() + "_" + Math.floor(Math.random() * 100);
    const div = document.createElement("div");
    div.className = "sale-item-line";
    div.id = lineId;

    let options = `<option value="">-- Choose Product --</option>`;
    App.products.forEach(p => {
        const stockQty = parseInt(p.stock_quantity);
        options += `<option value="${p.id}" ${stockQty <= 0 ? 'disabled' : ''} data-price="${p.selling_price}" data-cost="${p.purchase_price}" data-stock="${stockQty}">${p.sku} | ${p.name} (Qty: ${stockQty})</option>`;
    });

    div.innerHTML = `
        <select class="line-product-select" required>
            ${options}
        </select>
        <input type="number" class="line-qty-input" min="1" value="1" required disabled>
        <span class="line-price-label">Rs. 0.00</span>
        <span class="line-subtotal-label">Rs. 0.00</span>
        <button type="button" class="btn-icon delete" title="Delete Row" onclick="deleteSaleItemLine('${lineId}')">
            <i data-lucide="x" style="width:14px; height:14px;"></i>
        </button>
    `;

    container.appendChild(div);
    lucide.createIcons();

    const select = div.querySelector(".line-product-select");
    const qtyInput = div.querySelector(".line-qty-input");

    select.addEventListener("change", (e) => {
        const selectedOpt = select.options[select.selectedIndex];
        const price = parseFloat(selectedOpt.getAttribute('data-price')) || 0;
        const stock = parseInt(selectedOpt.getAttribute('data-stock')) || 0;

        if (select.value && stock > 0) {
            qtyInput.disabled = false;
            qtyInput.max = stock;
            qtyInput.value = 1;
            div.querySelector(".line-price-label").textContent = formatCurrency(price);
        } else {
            qtyInput.disabled = true;
            qtyInput.value = "";
            div.querySelector(".line-price-label").textContent = "Rs. 0.00";
        }
        calculateSaleTotals();
    });

    qtyInput.addEventListener("input", () => {
        const selectedOpt = select.options[select.selectedIndex];
        const stock = parseInt(selectedOpt.getAttribute('data-stock')) || 0;
        let val = parseInt(qtyInput.value) || 0;
        if (val > stock) {
            alert(`Selected quantity ${val} exceeds available stock of ${stock} units.`);
            qtyInput.value = stock;
        } else if (val < 1) {
            qtyInput.value = 1;
        }
        calculateSaleTotals();
    });
}

function deleteSaleItemLine(lineId) {
    const container = document.getElementById("sale-items-container");
    const lines = container.querySelectorAll(".sale-item-line");
    if (lines.length <= 1) {
        alert("You must include at least one product row in the invoice!");
        return;
    }
    const line = document.getElementById(lineId);
    if (line) {
        line.remove();
        calculateSaleTotals();
    }
}

function calculateSaleTotals() {
    const container = document.getElementById("sale-items-container");
    const lines = container.querySelectorAll(".sale-item-line");

    let subtotal = 0;
    let cost = 0;

    lines.forEach(line => {
        const select = line.querySelector(".line-product-select");
        const qtyInput = line.querySelector(".line-qty-input");
        const subtotalEl = line.querySelector(".line-subtotal-label");

        if (select && select.value && qtyInput && parseInt(qtyInput.value) > 0) {
            const opt = select.options[select.selectedIndex];
            const price = parseFloat(opt.getAttribute('data-price')) || 0;
            const buyCost = parseFloat(opt.getAttribute('data-cost')) || 0;
            const qty = parseInt(qtyInput.value) || 1;

            const lineSub = price * qty;
            subtotal += lineSub;
            cost += (buyCost * qty);

            subtotalEl.textContent = formatCurrency(lineSub);
        } else {
            subtotalEl.textContent = "Rs. 0.00";
        }
    });

    const discountVal = parseFloat(document.getElementById("sale-discount").value) || 0;
    const taxVal = parseFloat(document.getElementById("sale-tax").value) || 0;

    const discountAmt = Math.min(discountVal, subtotal);
    const taxAmt = Math.max(0, (subtotal - discountAmt) * (taxVal / 100));
    const grandTotal = Math.max(0, subtotal - discountAmt + taxAmt);
    const profit = grandTotal - cost;

    document.getElementById("sale-calc-subtotal").textContent = formatCurrency(subtotal);
    document.getElementById("sale-calc-discount").textContent = `-${formatCurrency(discountAmt)}`;
    document.getElementById("sale-calc-tax").textContent = formatCurrency(taxAmt);
    document.getElementById("sale-calc-grand-total").textContent = formatCurrency(grandTotal);

    const profitEl = document.getElementById("sale-calc-profit");
    if (profitEl) {
        profitEl.textContent = formatCurrency(profit);
        profitEl.style.color = profit >= 0 ? "var(--accent-emerald)" : "var(--accent-rose)";
    }
}

function setupSalesHandlers() {
    const addSaleBtn = document.getElementById("add-sale-btn");
    const addLineBtn = document.getElementById("add-sale-item-line-btn");
    const saleForm = document.getElementById("sale-form");

    if (addSaleBtn) {
        addSaleBtn.addEventListener("click", async () => {
            saleForm.reset();

            // Preview next invoice number
            const nextRes = await apiRequest('api/invoices/next_number.php');
            if (nextRes.success && nextRes.data) {
                document.getElementById("sale-invoice-no").value = nextRes.data.next_invoice_no;
            } else {
                document.getElementById("sale-invoice-no").value = "Auto Assigned";
            }

            const localNow = new Date();
            const localISO = new Date(localNow.getTime() - localNow.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById("sale-date").value = localISO;
            document.getElementById("sale-discount").value = 0;
            document.getElementById("sale-tax").value = App.settings.default_tax || 0;

            const container = document.getElementById("sale-items-container");
            container.innerHTML = "";

            // Ensure products & customers are loaded
            await loadProducts();
            await loadCustomers();

            addSaleItemLine();
            calculateSaleTotals();
            openModal("sale-modal");
        });
    }

    if (addLineBtn) {
        addLineBtn.addEventListener("click", addSaleItemLine);
    }

    if (saleForm) {
        saleForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            const container = document.getElementById("sale-items-container");
            const lines = container.querySelectorAll(".sale-item-line");
            const items = [];

            lines.forEach(line => {
                const select = line.querySelector(".line-product-select");
                const qtyInput = line.querySelector(".line-qty-input");
                if (select && select.value && qtyInput && parseInt(qtyInput.value) > 0) {
                    items.push({
                        product_id: parseInt(select.value),
                        quantity: parseInt(qtyInput.value)
                    });
                }
            });

            if (items.length === 0) {
                alert("Please add at least one valid product line to the sale.");
                return;
            }

            const payload = {
                customer_id: document.getElementById("sale-customer-select").value || null,
                sale_date: document.getElementById("sale-date").value,
                platform: document.getElementById("sale-platform").value,
                payment_method: document.getElementById("sale-payment-method").value,
                payment_status: document.getElementById("sale-payment-status").value,
                discount: parseFloat(document.getElementById("sale-discount").value) || 0,
                tax_percent: parseFloat(document.getElementById("sale-tax").value) || 0,
                items: items
            };

            const submitBtn = document.getElementById("save-sale-submit-btn");
            submitBtn.disabled = true;
            submitBtn.textContent = "Processing Transaction...";

            const res = await apiRequest('api/sales/index.php', { method: 'POST', body: payload });
            submitBtn.disabled = false;
            submitBtn.textContent = "Save Sale & Show Invoice";

            if (res.success) {
                closeModal("sale-modal");
                // Open created invoice preview
                viewInvoice(res.data.sale_id);
                loadSales();
                loadDashboard();
            } else {
                alert(res.message || "Sale transaction failed");
            }
        });
    }

    const discountInput = document.getElementById("sale-discount");
    if (discountInput) discountInput.addEventListener("input", calculateSaleTotals);

    const taxInput = document.getElementById("sale-tax");
    if (taxInput) taxInput.addEventListener("input", calculateSaleTotals);

    const searchInput = document.getElementById("sales-search-input");
    if (searchInput) searchInput.addEventListener("input", loadSales);

    const custFilter = document.getElementById("sales-customer-filter");
    if (custFilter) custFilter.addEventListener("change", loadSales);

    const dateFilter = document.getElementById("sales-date-filter");
    if (dateFilter) dateFilter.addEventListener("change", loadSales);

    const quickAddCust = document.getElementById("sale-add-customer-quick");
    if (quickAddCust) {
        quickAddCust.addEventListener("click", () => {
            const cform = document.getElementById("customer-form");
            if (cform) cform.reset();
            document.getElementById("customer-id-field").value = "";
            document.getElementById("customer-modal-title").textContent = "Quick Add Customer";
            openModal("customer-modal");
        });
    }
}

async function viewInvoice(saleId) {
    const res = await apiRequest(`api/sales/manage.php?id=${saleId}`);
    if (!res.success || !res.data) {
        alert("Failed to load invoice details");
        return;
    }

    const sale = res.data;
    const settings = sale.settings || App.settings;

    // Apply store settings to invoice header
    const brandTitle = document.getElementById("invoice-brand-title");
    const brandTag = document.getElementById("invoice-brand-tagline");
    const brandContact = document.getElementById("invoice-brand-contact");
    if (brandTitle) brandTitle.textContent = settings.store_name || "Prime E commerce Hub";
    if (brandTag) brandTag.textContent = settings.store_tagline || "";
    if (brandContact) brandContact.textContent = `Phone: ${settings.store_phone || ''} | Email: ${settings.store_email || ''}`;

    document.getElementById("inv-no").textContent = sale.invoice_no;
    document.getElementById("inv-date").textContent = formatDate(sale.sale_date);
    document.getElementById("inv-cust-name").textContent = sale.customer_name || "Guest Customer";
    document.getElementById("inv-cust-phone").textContent = `Phone: ${sale.cust_phone || 'N/A'}`;
    document.getElementById("inv-cust-address").textContent = `Address: ${sale.cust_address || 'N/A'}`;
    document.getElementById("inv-payment-method").textContent = sale.payment_method || "Cash";
    document.getElementById("inv-platform").textContent = sale.platform || "Manual";

    const statusEl = document.getElementById("inv-payment-status");
    let statusText = `Status: ${sale.payment_status || 'Paid'}`;
    if (sale.delivery_status) statusText += ` | Delivery: ${sale.delivery_status}`;
    statusEl.textContent = statusText;

    const tbody = document.getElementById("invoice-items-tbody");
    tbody.innerHTML = "";

    if (sale.items) {
        sale.items.forEach(it => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${it.product_name}</td>
                <td class="num-col">${formatCurrency(it.selling_price)}</td>
                <td class="num-col">${it.quantity}</td>
                <td class="num-col">${formatCurrency(it.subtotal)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById("inv-subtotal").textContent = formatCurrency(sale.subtotal);
    document.getElementById("inv-discount").textContent = `-${formatCurrency(sale.discount)}`;
    document.getElementById("inv-tax").textContent = formatCurrency(sale.tax_amount);
    document.getElementById("inv-grand-total").textContent = formatCurrency(sale.grand_total);

    // RTO Return Button
    const markReturnedBtn = document.getElementById("btn-mark-returned");
    if (markReturnedBtn) {
        if (sale.delivery_status !== "Returned / Failed Delivery") {
            markReturnedBtn.style.display = "inline-flex";
            markReturnedBtn.onclick = async () => {
                if (!confirm(`Mark invoice ${sale.invoice_no} as Returned (RTO)? Stock will be restored to inventory.`)) return;
                markReturnedBtn.disabled = true;
                const rtoRes = await apiRequest('api/sales/return.php', { method: 'POST', body: { sale_id: sale.id } });
                markReturnedBtn.disabled = false;
                if (rtoRes.success) {
                    alert(rtoRes.message);
                    closeModal("invoice-modal");
                    loadSales();
                    loadDashboard();
                } else {
                    alert(rtoRes.message || "Failed to mark return");
                }
            };
        } else {
            markReturnedBtn.style.display = "none";
        }
    }

    openModal("invoice-modal");
}

function printInvoice() {
    window.print();
}

async function deleteSale(saleId, invoiceNo) {
    if (!confirm(`Are you sure you want to delete invoice ${invoiceNo}? Stock will be returned to inventory.`)) return;

    const res = await apiRequest(`api/sales/manage.php?id=${saleId}`, { method: 'DELETE' });
    if (res.success) {
        alert(res.message);
        loadSales();
        loadDashboard();
    } else {
        alert(res.message || "Failed to delete sale");
    }
}

// --- INVOICES REGISTRY ENGINE ---
async function loadInvoices() {
    const search = document.getElementById("invoice-search-input") ? document.getElementById("invoice-search-input").value.trim() : "";
    const date = document.getElementById("invoice-date-filter") ? document.getElementById("invoice-date-filter").value : "";

    const res = await apiRequest(`api/invoices/index.php?search=${encodeURIComponent(search)}&date=${encodeURIComponent(date)}`);
    const tbody = document.getElementById("invoice-registry-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const invoices = res.data || [];

    if (invoices.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color:var(--text-secondary); padding:24px;">No invoices registered.</td></tr>`;
        return;
    }

    invoices.forEach(inv => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${inv.invoice_no}</strong></td>
            <td>${formatDate(inv.sale_date)}</td>
            <td>${inv.customer_name || 'Guest Customer'}</td>
            <td>${inv.total_items_count} units</td>
            <td>${formatCurrency(inv.subtotal)}</td>
            <td>-${formatCurrency(inv.discount)}</td>
            <td>${formatCurrency(inv.tax_amount)}</td>
            <td><strong style="color:var(--accent-emerald)">${formatCurrency(inv.grand_total)}</strong></td>
            <td>
                <button class="btn-primary" style="padding:4px 10px; font-size:0.8rem;" onclick="viewInvoice(${inv.id})">
                    <i data-lucide="printer" style="width:12px; height:12px;"></i> View & Print
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    lucide.createIcons();
}

function setupInvoiceRegistryHandlers() {
    const search = document.getElementById("invoice-search-input");
    if (search) search.addEventListener("input", loadInvoices);

    const dateFilter = document.getElementById("invoice-date-filter");
    if (dateFilter) dateFilter.addEventListener("change", loadInvoices);

    const addSaleBtn = document.getElementById("invoice-add-sale-btn");
    if (addSaleBtn) {
        addSaleBtn.addEventListener("click", () => {
            const btn = document.getElementById("add-sale-btn");
            if (btn) btn.click();
        });
    }
}

// --- INVENTORY ENGINE ---
async function loadInventory() {
    const search = document.getElementById("inventory-search-input") ? document.getElementById("inventory-search-input").value.trim() : "";
    const cat = document.getElementById("inventory-category-filter") ? document.getElementById("inventory-category-filter").value : "All";
    const stock = document.getElementById("inventory-stock-filter") ? document.getElementById("inventory-stock-filter").value : "All";

    const res = await apiRequest(`api/inventory/index.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(cat)}&filter=${encodeURIComponent(stock)}`);
    const tbody = document.getElementById("inventory-table-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const items = res.data || [];

    let totalBuying = 0;
    let totalSelling = 0;

    items.forEach(p => {
        const qty = parseInt(p.stock_quantity);
        const buy = parseFloat(p.purchase_price);
        const sell = parseFloat(p.selling_price);

        totalBuying += (qty * buy);
        totalSelling += (qty * sell);

        let badgeClass = "success";
        if (p.stock_status === "Out of Stock") badgeClass = "danger";
        else if (p.stock_status === "Low Stock Alert") badgeClass = "warning";

        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${p.sku}</strong></td>
            <td>${p.name}</td>
            <td><span class="category-tag">${p.category || 'General'}</span></td>
            <td>${formatCurrency(p.purchase_price)}</td>
            <td>${formatCurrency(p.selling_price)}</td>
            <td><strong>${qty}</strong></td>
            <td>${p.low_stock_threshold} units</td>
            <td><span class="badge ${badgeClass}">${p.stock_status}</span></td>
        `;
        tbody.appendChild(tr);
    });

    const buyEl = document.getElementById("inv-val-buying");
    const sellEl = document.getElementById("inv-val-selling");
    const expEl = document.getElementById("inv-expected-profit");
    if (buyEl) buyEl.textContent = formatCurrency(totalBuying);
    if (sellEl) sellEl.textContent = formatCurrency(totalSelling);
    if (expEl) expEl.textContent = formatCurrency(totalSelling - totalBuying);

    loadInventoryLogs();
    lucide.createIcons();
}

async function loadInventoryLogs() {
    const res = await apiRequest('api/inventory/logs.php?limit=50');
    const tbody = document.getElementById("inventory-log-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const logs = res.data || [];

    if (logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-secondary); padding:20px;">No inventory transaction logs recorded.</td></tr>`;
        return;
    }

    logs.forEach(log => {
        const tr = document.createElement("tr");
        const changeSign = log.quantity_change > 0 ? `+${log.quantity_change}` : `${log.quantity_change}`;
        const changeColor = log.quantity_change > 0 ? "var(--accent-emerald)" : "var(--accent-rose)";

        tr.innerHTML = `
            <td>${formatDate(log.created_at)}</td>
            <td><strong>${log.product_sku}</strong></td>
            <td>${log.product_name}</td>
            <td><span class="badge" style="background:rgba(255,255,255,0.05); color:#fff;">${log.transaction_type}</span></td>
            <td><strong style="color:${changeColor}">${changeSign} units</strong></td>
            <td>${log.new_stock_level}</td>
            <td>${log.reason || 'N/A'}</td>
        `;
        tbody.appendChild(tr);
    });
}

function setupInventoryHandlers() {
    const adjBtn = document.getElementById("adjust-stock-btn");
    const form = document.getElementById("adjust-stock-form");

    if (adjBtn) {
        adjBtn.addEventListener("click", async () => {
            form.reset();
            const prodSelect = document.getElementById("adj-product-select");
            await loadProducts();

            let opts = `<option value="">-- Select Product --</option>`;
            App.products.forEach(p => {
                opts += `<option value="${p.id}">${p.sku} | ${p.name} (Current: ${p.stock_quantity})</option>`;
            });
            prodSelect.innerHTML = opts;
            openModal("adjust-stock-modal");
        });
    }

    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const payload = {
                product_id: parseInt(document.getElementById("adj-product-select").value),
                adjustment_type: document.getElementById("adj-type").value,
                quantity: parseInt(document.getElementById("adj-qty").value) || 0,
                reason: document.getElementById("adj-reason").value.trim()
            };

            const btn = document.getElementById("save-adj-submit-btn");
            btn.disabled = true;

            const res = await apiRequest('api/inventory/adjust.php', { method: 'POST', body: payload });
            btn.disabled = false;

            if (res.success) {
                closeModal("adjust-stock-modal");
                alert(res.message);
                loadInventory();
                loadDashboard();
            } else {
                alert(res.message || "Failed to adjust stock");
            }
        });
    }

    const search = document.getElementById("inventory-search-input");
    if (search) search.addEventListener("input", loadInventory);

    const cat = document.getElementById("inventory-category-filter");
    if (cat) cat.addEventListener("change", loadInventory);

    const stock = document.getElementById("inventory-stock-filter");
    if (stock) stock.addEventListener("change", loadInventory);
}

// --- REPORTS & CLOSING ENGINE ---
async function loadReports() {
    const datePicker = document.getElementById("daily-report-date-picker");
    const dateVal = datePicker ? datePicker.value || getLocalDateString() : getLocalDateString();
    if (datePicker && !datePicker.value) datePicker.value = dateVal;

    const res = await apiRequest(`api/reports/daily.php?date=${dateVal}`);
    if (!res.success || !res.data) return;

    const { stats, sales } = res.data;

    document.getElementById("daily-sales-count").textContent = stats.total_orders;
    document.getElementById("daily-revenue").textContent = formatCurrency(stats.grand_total);
    document.getElementById("daily-cogs").textContent = formatCurrency(stats.total_cogs);
    document.getElementById("daily-profit").textContent = formatCurrency(stats.net_profit);

    const tbody = document.getElementById("daily-sales-table-tbody");
    if (tbody) {
        tbody.innerHTML = "";
        if (sales.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--text-secondary); padding:20px;">No sales transactions on this date.</td></tr>`;
        } else {
            sales.forEach(s => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td><strong>${s.invoice_no}</strong></td>
                    <td>${new Date(s.sale_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</td>
                    <td>${s.customer_name || 'Guest Customer'}</td>
                    <td>${formatCurrency(s.subtotal)}</td>
                    <td>-${formatCurrency(s.discount)}</td>
                    <td><strong style="color:var(--accent-emerald)">${formatCurrency(s.grand_total)}</strong></td>
                    <td style="color:var(--accent-emerald)">${formatCurrency(s.net_profit)}</td>
                    <td>
                        <button class="btn-primary" style="padding:4px 8px; font-size:0.75rem;" onclick="viewInvoice(${s.id})">
                            <i data-lucide="eye" style="width:12px; height:12px;"></i> View
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    }

    loadWeeklyClosingHistory();
    lucide.createIcons();
}

async function loadWeeklyClosingHistory() {
    const res = await apiRequest('api/closings/index.php');
    const tbody = document.getElementById("weekly-closing-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const closings = res.data || [];

    if (closings.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-secondary); padding:20px;">No weekly closings logged yet.</td></tr>`;
        return;
    }

    closings.forEach(c => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${c.closing_code}</strong></td>
            <td>${formatDate(c.start_date)} to ${formatDate(c.end_date)}</td>
            <td>${c.sales_volume} sales</td>
            <td><strong style="color:var(--accent-emerald)">${formatCurrency(c.total_revenue)}</strong></td>
            <td><strong style="color:var(--accent-emerald)">${formatCurrency(c.total_profit)}</strong></td>
            <td>${formatDate(c.closed_on)}</td>
            <td>
                <button class="btn-primary" style="padding:4px 10px; font-size:0.75rem; background-color:var(--accent-purple);" onclick="printWeeklyClosing(${c.id})">
                    <i data-lucide="printer" style="width:12px; height:12px;"></i> Print PDF
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    lucide.createIcons();
}

function setupReportsHandlers() {
    const datePicker = document.getElementById("daily-report-date-picker");
    if (datePicker) {
        datePicker.value = getLocalDateString();
        datePicker.addEventListener("change", loadReports);
    }

    const genClosingBtn = document.getElementById("generate-weekly-closing-btn");
    if (genClosingBtn) {
        genClosingBtn.addEventListener("click", async () => {
            const today = getLocalDateString();
            const d7 = new Date();
            d7.setDate(d7.getDate() - 7);
            const start7 = getLocalDateString(d7);

            const res = await apiRequest('api/closings/index.php', {
                method: 'POST',
                body: { action: 'preview', start_date: start7, end_date: today }
            });

            if (!res.success || !res.data) {
                alert("Failed to compute closing summary.");
                return;
            }

            const sm = res.data;
            document.getElementById("close-week-dates").textContent = `${formatDate(sm.start_date)} to ${formatDate(sm.end_date)}`;
            document.getElementById("close-week-sales-count").textContent = `${sm.sales_volume} transactions`;
            document.getElementById("close-week-revenue").textContent = formatCurrency(sm.total_revenue);
            document.getElementById("close-week-paid").textContent = formatCurrency(sm.paid_amount);
            document.getElementById("close-week-outstanding").textContent = formatCurrency(sm.outstanding_payments);
            document.getElementById("close-week-cogs").textContent = formatCurrency(sm.total_cost);
            document.getElementById("close-week-profit").textContent = formatCurrency(sm.total_profit);

            window.currentClosingPayload = {
                start_date: sm.start_date,
                end_date: sm.end_date
            };

            openModal("weekly-closing-modal");
        });
    }

    const confirmClosingBtn = document.getElementById("confirm-weekly-closing-btn");
    if (confirmClosingBtn) {
        confirmClosingBtn.addEventListener("click", async () => {
            if (!window.currentClosingPayload) return;

            confirmClosingBtn.disabled = true;
            confirmClosingBtn.textContent = "Committing Settlement...";

            const payload = {
                ...window.currentClosingPayload,
                action: 'save',
                closing_summary: document.getElementById("close-week-summary-input").value.trim()
            };

            const res = await apiRequest('api/closings/index.php', { method: 'POST', body: payload });
            confirmClosingBtn.disabled = false;
            confirmClosingBtn.textContent = "Confirm & Settle Ledger";

            if (res.success) {
                closeModal("weekly-closing-modal");
                alert(res.message);
                loadWeeklyClosingHistory();
                loadDashboard();
            } else {
                alert(res.message || "Failed to commit weekly closing");
            }
        });
    }

    const printDailyBtn = document.getElementById("btn-export-daily-pdf");
    if (printDailyBtn) {
        printDailyBtn.addEventListener("click", () => window.print());
    }
}

async function printWeeklyClosing(closingId) {
    const res = await apiRequest(`api/closings/index.php?id=${closingId}`);
    if (!res.success || !res.data) {
        alert("Failed to load closing report details");
        return;
    }

    const c = res.data;
    const settings = c.settings || App.settings;
    const sName = settings.store_name || "Prime E commerce Hub";
    const sTag = settings.store_tagline || "";
    const sPhone = settings.store_phone || "";
    const cogs = parseFloat(c.total_revenue) - parseFloat(c.total_profit);

    const win = window.open("", "_blank");
    win.document.write(`
        <html>
        <head>
            <title>Weekly Closing - ${c.closing_code}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; padding: 24px; line-height: 1.5; }
                h1 { color: #d97706; border-bottom: 2px solid #d97706; padding-bottom: 8px; margin-top: 0; }
                .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 24px 0; }
                .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #f8fafc; }
                .card h4 { margin: 0 0 6px 0; color: #64748b; font-size: 0.75rem; text-transform: uppercase; }
                .card .val { font-size: 1.3rem; font-weight: bold; color: #0284c7; }
                .card.profit .val { color: #10b981; }
                .narrative { background: #f0fdf4; border-left: 4px solid #10b981; padding: 14px; border-radius: 4px; font-style: italic; margin-bottom: 24px; }
                .no-print { display: flex; justify-content: flex-end; margin-bottom: 20px; }
                button { background: #d97706; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="no-print"><button onclick="window.print()">Print Report</button></div>
            <div style="display:flex; justify-content:space-between; border-bottom:2px solid #e2e8f0; padding-bottom:16px;">
                <div>
                    <h2 style="margin:0; color:#d97706;">${sName}</h2>
                    <p style="margin:4px 0; color:#64748b; font-size:0.85rem;">${sTag}</p>
                    <p style="margin:2px 0; color:#64748b; font-size:0.75rem;">Phone: ${sPhone}</p>
                </div>
                <div style="text-align:right;">
                    <h2 style="margin:0;">Weekly Closing Settlement</h2>
                    <p style="margin:4px 0; font-weight:bold;">${c.closing_code}</p>
                    <p style="margin:2px 0; font-size:0.85rem; color:#64748b;">${formatDate(c.start_date)} to ${formatDate(c.end_date)}</p>
                </div>
            </div>

            <div class="narrative" style="margin-top:20px;">
                <strong>Summary Narrative:</strong><br>
                "${c.closing_summary || 'Standard weekly settlement concluded.'}"
            </div>

            <div class="grid">
                <div class="card">
                    <h4>Orders Count</h4>
                    <div class="val">${c.sales_volume} orders</div>
                </div>
                <div class="card">
                    <h4>Total Revenue</h4>
                    <div class="val">${formatCurrency(c.total_revenue)}</div>
                </div>
                <div class="card">
                    <h4>Paid & Settled</h4>
                    <div class="val">${formatCurrency(c.paid_amount)}</div>
                </div>
                <div class="card">
                    <h4>Outstanding</h4>
                    <div class="val" style="color:#f43f5e;">${formatCurrency(c.outstanding_payments)}</div>
                </div>
                <div class="card">
                    <h4>COGS (Cost)</h4>
                    <div class="val">${formatCurrency(cogs)}</div>
                </div>
                <div class="card profit">
                    <h4>Net Weekly Profit</h4>
                    <div class="val">${formatCurrency(c.total_profit)}</div>
                </div>
            </div>

            <div style="margin-top:50px; display:flex; justify-content:space-between; border-top:1px dashed #cbd5e1; padding-top:20px; font-size:0.8rem; color:#64748b;">
                <div>Settled By: <strong>${c.closed_by_name || 'Administrator'}</strong></div>
                <div>Generated: ${new Date(c.closed_on).toLocaleString()}</div>
            </div>
        </body>
        </html>
    `);
    win.document.close();
}

// --- INVESTORS ENGINE ---
async function loadInvestors() {
    const res = await apiRequest('api/investors/index.php');
    const tbody = document.querySelector("#owner-investors-table tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    App.investors = res.data || [];

    if (App.investors.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-secondary); padding:20px;">No investors registered yet.</td></tr>`;
        return;
    }

    App.investors.forEach(inv => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${inv.name}</strong></td>
            <td style="text-align: right; font-weight:600;">${formatCurrency(inv.investment_amount)}</td>
            <td style="text-align: right; color: var(--accent-blue);">${formatCurrency(inv.active_stock_cost)}</td>
            <td style="text-align: right; color: ${inv.remaining_cash >= 0 ? 'var(--accent-emerald)' : 'var(--accent-rose)'};">${formatCurrency(inv.remaining_cash)}</td>
            <td style="text-align: right; color: var(--accent-blue);">${formatCurrency(inv.sales_generated)}</td>
            <td style="text-align: right; font-weight:600; color: var(--accent-emerald);">${formatCurrency(inv.profit_generated)}</td>
            <td style="text-align: center;">
                <div style="display:flex; gap:6px; justify-content:center;">
                    <button class="btn-icon edit" onclick="viewInvestorDetails(${inv.id})" title="View Ledger">
                        <i data-lucide="book-open" style="width:14px; height:14px;"></i>
                    </button>
                    <button class="btn-icon delete" onclick="deleteInvestor(${inv.id}, '${inv.name.replace(/'/g, "\\'")}')" title="Delete Account">
                        <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    lucide.createIcons();
}

function setupInvestorHandlers() {
    const form = document.getElementById("owner-investor-form");
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById("inv-name").value.trim(),
                capital: parseFloat(document.getElementById("inv-capital").value) || 0,
                notes: document.getElementById("inv-notes").value.trim()
            };

            const btn = form.querySelector("button[type=submit]");
            btn.disabled = true;

            const res = await apiRequest('api/investors/index.php', { method: 'POST', body: payload });
            btn.disabled = false;

            if (res.success) {
                form.reset();
                alert(res.message);
                loadInvestors();
            } else {
                alert(res.message || "Failed to register investor");
            }
        });
    }
}

async function viewInvestorDetails(investorId) {
    const res = await apiRequest(`api/investors/manage.php?id=${investorId}`);
    if (!res.success || !res.data) {
        alert("Failed to load investor ledger details");
        return;
    }

    const { investor, sponsored_products, active_stock_cost, remaining_cash, total_sales, total_profit } = res.data;

    const detailsCard = document.getElementById("investor-details-card");
    const detailsTitle = document.getElementById("inv-details-title");
    const detailsMeta = document.getElementById("inv-details-meta");
    const tbody = document.querySelector("#investor-products-table tbody");

    if (!detailsCard || !tbody) return;

    detailsCard.style.display = "block";
    detailsTitle.textContent = `${investor.name}'s Sponsored Products Ledger`;
    detailsMeta.textContent = `Capital: ${formatCurrency(investor.investment_amount)} | Active Stock: ${formatCurrency(active_stock_cost)} | Remaining Cash: ${formatCurrency(remaining_cash)} | Total Profit: ${formatCurrency(total_profit)}`;

    tbody.innerHTML = "";

    if (sponsored_products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--text-secondary); padding:20px;">No products currently sponsored by this investor.</td></tr>`;
    } else {
        sponsored_products.forEach(p => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td><strong>${p.sku}</strong></td>
                <td>${p.name}</td>
                <td style="text-align: right;">${formatCurrency(p.purchase_price)}</td>
                <td style="text-align: right;">${formatCurrency(p.selling_price)}</td>
                <td style="text-align: right; font-weight:600;">${p.stock_quantity} units</td>
                <td style="text-align: right; color: var(--accent-emerald);">${p.total_sold} units</td>
                <td style="text-align: right; color: var(--accent-rose);">${p.total_returned} units</td>
                <td style="text-align: right; font-weight:600; color: var(--accent-emerald);">${formatCurrency(p.profit_gen)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    const printBtn = document.getElementById("btn-print-investor-ledger");
    if (printBtn) {
        printBtn.onclick = () => printInvestorStatement(investorId);
    }
}

async function printInvestorStatement(investorId) {
    const res = await apiRequest(`api/investors/manage.php?id=${investorId}`);
    if (!res.success || !res.data) return;

    const { investor, sponsored_products, active_stock_cost, remaining_cash, total_sales, total_profit, settings } = res.data;
    const sName = settings.store_name || App.settings.store_name || "Prime E commerce Hub";
    const sTag = settings.store_tagline || "";
    const sPhone = settings.store_phone || "";

    const win = window.open("", "_blank");
    win.document.write(`
        <html>
        <head>
            <title>Investor Statement - ${investor.name}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; padding: 24px; line-height: 1.5; }
                h1 { color: #d97706; border-bottom: 2px solid #d97706; padding-bottom: 8px; margin-top: 0; }
                .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 24px 0; }
                .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #f8fafc; }
                .card h4 { margin: 0 0 6px 0; color: #64748b; font-size: 0.75rem; text-transform: uppercase; }
                .card .val { font-size: 1.3rem; font-weight: bold; color: #0284c7; }
                .card.profit .val { color: #10b981; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.85rem; }
                th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
                th { background: #f1f5f9; font-weight: 600; color: #475569; }
                .num { text-align: right; }
                .no-print { display: flex; justify-content: flex-end; margin-bottom: 20px; }
                button { background: #d97706; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="no-print"><button onclick="window.print()">Print Statement</button></div>
            <div style="display:flex; justify-content:space-between; border-bottom:2px solid #e2e8f0; padding-bottom:16px;">
                <div>
                    <h2 style="margin:0; color:#d97706;">${sName}</h2>
                    <p style="margin:4px 0; color:#64748b; font-size:0.85rem;">${sTag}</p>
                    <p style="margin:2px 0; color:#64748b; font-size:0.75rem;">Phone: ${sPhone}</p>
                </div>
                <div style="text-align:right;">
                    <h2 style="margin:0;">Investor Capital Statement</h2>
                    <p style="margin:4px 0; font-size:1.1rem; font-weight:bold;">${investor.name}</p>
                    <p style="margin:2px 0; font-size:0.85rem; color:#64748b;">Statement Date: ${new Date().toLocaleDateString()}</p>
                </div>
            </div>

            <div class="grid">
                <div class="card">
                    <h4>Total Capital</h4>
                    <div class="val">${formatCurrency(investor.investment_amount)}</div>
                </div>
                <div class="card">
                    <h4>Active Stock Cost</h4>
                    <div class="val">${formatCurrency(active_stock_cost)}</div>
                </div>
                <div class="card">
                    <h4>Remaining Cash</h4>
                    <div class="val" style="color:${remaining_cash >= 0 ? '#10b981' : '#f43f5e'}">${formatCurrency(remaining_cash)}</div>
                </div>
                <div class="card profit">
                    <h4>Net Profit Generated</h4>
                    <div class="val">${formatCurrency(total_profit)}</div>
                </div>
            </div>

            <h3>Sponsored Products Breakdown</h3>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th class="num">Cost Price</th>
                        <th class="num">Sell Price</th>
                        <th class="num">In Stock</th>
                        <th class="num">Units Sold</th>
                        <th class="num">Returned (RTO)</th>
                        <th class="num">Net Profit</th>
                    </tr>
                </thead>
                <tbody>
                    ${sponsored_products.map(p => `
                        <tr>
                            <td><strong>${p.sku}</strong></td>
                            <td>${p.name}</td>
                            <td class="num">${formatCurrency(p.purchase_price)}</td>
                            <td class="num">${formatCurrency(p.selling_price)}</td>
                            <td class="num">${p.stock_quantity}</td>
                            <td class="num">${p.total_sold}</td>
                            <td class="num">${p.total_returned}</td>
                            <td class="num" style="font-weight:bold; color:#10b981;">${formatCurrency(p.profit_gen)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>

            <div style="margin-top:40px; font-size:0.75rem; color:#94a3b8; text-align:center;">
                Generated by Prime E Commerce Hub Management Terminal
            </div>
        </body>
        </html>
    `);
    win.document.close();
}

async function deleteInvestor(investorId, name) {
    if (!confirm(`Are you sure you want to delete investor "${name}"? Linked products will return to self-funded status.`)) return;

    const res = await apiRequest(`api/investors/manage.php?id=${investorId}`, { method: 'DELETE' });
    if (res.success) {
        alert(res.message);
        loadInvestors();
        document.getElementById("investor-details-card").style.display = "none";
    } else {
        alert(res.message || "Failed to delete investor");
    }
}

// --- USERS MANAGEMENT ENGINE (OWNER ONLY) ---
async function loadUsers() {
    if (App.user.role !== 'owner') return;

    const res = await apiRequest('api/users/index.php');
    const tbody = document.getElementById("users-table-tbody");
    if (!tbody || !res.success) return;

    tbody.innerHTML = "";
    const users = res.data || [];

    users.forEach(u => {
        const isSelf = App.user.id === u.id;
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><strong>${u.name}</strong> ${isSelf ? `<span class="badge success">You</span>` : ''}</td>
            <td>${u.username}</td>
            <td>${u.email || '<span style="color:var(--text-secondary)">N/A</span>'}</td>
            <td><span class="badge" style="background:rgba(226,177,60,0.15); color:var(--accent-blue); text-transform:uppercase;">${u.role}</span></td>
            <td><span class="badge ${u.status === 'active' ? 'success' : 'danger'}">${u.status}</span></td>
            <td>${u.last_login ? formatDate(u.last_login) : 'Never'}</td>
            <td>
                ${!isSelf ? `
                    <div style="display:flex; gap:6px;">
                        <button class="btn-icon edit" onclick="toggleUserStatus(${u.id}, '${u.status}')" title="${u.status === 'active' ? 'Deactivate' : 'Activate'}">
                            <i data-lucide="${u.status === 'active' ? 'user-x' : 'user-check'}" style="width:14px; height:14px;"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteUserAccount(${u.id}, '${u.username}')" title="Delete User">
                            <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                        </button>
                    </div>
                ` : `<span style="font-size:0.75rem; color:var(--text-secondary)">Active Session</span>`}
            </td>
        `;
        tbody.appendChild(tr);
    });

    lucide.createIcons();
}

function setupUserHandlers() {
    const addBtn = document.getElementById("btn-add-user-modal");
    const form = document.getElementById("user-form");

    if (addBtn) {
        addBtn.addEventListener("click", () => {
            if (form) form.reset();
            openModal("user-modal");
        });
    }

    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById("user-name").value.trim(),
                username: document.getElementById("user-username").value.trim(),
                email: document.getElementById("user-email").value.trim(),
                role: document.getElementById("user-role").value,
                password: document.getElementById("user-password").value
            };

            const btn = document.getElementById("btn-save-user");
            btn.disabled = true;

            const res = await apiRequest('api/users/index.php', { method: 'POST', body: payload });
            btn.disabled = false;

            if (res.success) {
                closeModal("user-modal");
                alert(res.message);
                loadUsers();
            } else {
                alert(res.message || "Failed to create user");
            }
        });
    }

    // Password Change Modal
    const changePwdBtn = document.getElementById("btn-change-password-modal");
    const changePwdForm = document.getElementById("change-password-form");

    if (changePwdBtn) {
        changePwdBtn.addEventListener("click", () => {
            if (changePwdForm) changePwdForm.reset();
            openModal("change-password-modal");
        });
    }

    if (changePwdForm) {
        changePwdForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const currentPassword = document.getElementById("pwd-current").value;
            const newPassword = document.getElementById("pwd-new").value;
            const confirmPassword = document.getElementById("pwd-confirm").value;

            if (newPassword !== confirmPassword) {
                alert("New passwords do not match!");
                return;
            }

            const btn = document.getElementById("btn-save-new-pwd");
            btn.disabled = true;

            const res = await apiRequest('api/auth/password.php', {
                method: 'POST',
                body: { current_password: currentPassword, new_password: newPassword, confirm_password: confirmPassword }
            });
            btn.disabled = false;

            if (res.success) {
                closeModal("change-password-modal");
                alert("Password changed successfully!");
            } else {
                alert(res.message || "Failed to update password");
            }
        });
    }
}

async function toggleUserStatus(userId, currentStatus) {
    const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const res = await apiRequest('api/users/manage.php', {
        method: 'POST',
        body: { id: userId, action: 'toggle_status', status: nextStatus }
    });
    if (res.success) {
        loadUsers();
    } else {
        alert(res.message || "Failed to toggle user status");
    }
}

async function deleteUserAccount(userId, username) {
    if (!confirm(`Delete user account "${username}"?`)) return;
    const res = await apiRequest(`api/users/manage.php?id=${userId}`, { method: 'DELETE' });
    if (res.success) {
        alert(res.message);
        loadUsers();
    } else {
        alert(res.message || "Failed to delete user");
    }
}

// --- GLOBAL SEARCH ---
function setupGlobalSearch() {
    const searchBar = document.getElementById("global-search-bar");
    if (!searchBar) return;

    searchBar.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            const q = searchBar.value.trim();
            if (!q) return;

            window.location.hash = "sales";
            const salesInput = document.getElementById("sales-search-input");
            if (salesInput) {
                salesInput.value = q;
                loadSales();
            }
        }
    });
}

// --- NOTIFICATION BELL ---
function setupNotifications() {
    const bell = document.getElementById("notification-bell");
    const dropdown = document.getElementById("notification-dropdown");

    if (bell && dropdown) {
        bell.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdown.classList.toggle("active");
        });

        document.addEventListener("click", () => {
            dropdown.classList.remove("active");
        });
    }
}

// --- INITIALIZATION ---
document.addEventListener("DOMContentLoaded", () => {
    initializeClock();
    applyTheme();
    setupRouting();
    setupProductHandlers();
    setupCustomerHandlers();
    setupSalesHandlers();
    setupInvoiceRegistryHandlers();
    setupInventoryHandlers();
    setupReportsHandlers();
    setupInvestorHandlers();
    setupUserHandlers();
    setupSettingsHandlers();
    setupGlobalSearch();
    setupNotifications();

    const themeToggleBtn = document.getElementById("theme-toggle-btn");
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener("click", () => {
            const isLight = document.body.classList.contains("light-theme");
            applyTheme(isLight ? "dark" : "light");
        });
    }

    // Initial load
    loadSettings();
    loadDashboard();
});
