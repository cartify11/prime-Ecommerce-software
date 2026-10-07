# Prime E Commerce Hub — Production Web Edition

> **A secure, multi-user, high-concurrency business management platform for wholesale, retail, and multi-channel e-commerce operations.** Fully converted from the legacy single-computer desktop app into an enterprise-grade web application targeting standard **cPanel Shared Hosting**.

---

## 🌟 Architecture & Core Features

- **Standard Shared Hosting Stack**: Built with vanilla **PHP 7.4+ / 8.x**, **MySQL / MariaDB 10.x**, and **Apache HTTP Server** with optimized `.htaccess`.
- **Zero External Dependencies**: Operates with **100% offline/local bundled assets** (local Chart.js, local Lucide Icons, local fonts, and brand assets). No external CDN calls, no Node.js server, no Docker, and no VPS required.
- **Enterprise-Grade Role-Based Access Control (RBAC)**:
  - **Owner / Super Admin**: Complete, unrestricted authority across all modules, settings, users, and financials.
  - **Manager**: Operational control over products, inventory, customers, sales, and weekly closings (no user credential tampering or destructive database resets).
  - **Sales Staff**: Streamlined POS workflow, instant order placement, customer management, inventory checking, and invoice printing.
  - **Accountant**: Customer ledger management, payment recording, weekly financial closings, and comprehensive sales reports.
- **ACID-Compliant Concurrency Safety**:
  - `FOR UPDATE` pessimistic row locking prevents simultaneous overselling.
  - Atomic stock deductions and restorations.
  - Collision-proof invoice (`INV-xxxx`) and closing (`WCL-xxxx`) sequences with dedicated sequence table.
- **Investor Tracking Module**:
  - Capital investment tracking.
  - Product-level investor attribution.
  - Automated calculation of active stock cost, sales revenue, and net profit.
  - Clean unlinking safeguards ensuring investor deletions never orphan or delete inventory.
- **End-to-End Customer Ledger**:
  - Debit/credit running balance calculations.
  - Detailed payment vouchers and balance reconciliation.
- **Complete Inventory Audit Trail**:
  - Every addition, sale, manual adjustment, and RTO return is recorded in `inventory_transactions`.
- **Legacy Desktop Data Migration**:
  - Direct import tool for the legacy desktop `database.json` file.
  - Preserves foreign keys, customer profiles, product history, and sales.

---

## 🚀 Quick Start (Local Development)

### Prerequisites
- PHP 7.4 or PHP 8.0+ with `pdo_mysql`, `session`, `json`, `curl` extensions enabled.
- MySQL 5.7+ or MariaDB 10.3+.

### Step 1: Initialize Database
```bash
# Automated headless CLI setup:
php cli_install.php
```
*Or visit `http://localhost/install.php` in your browser to run the graphical setup wizard.*

### Step 2: Start Development Server
```bash
php -S 127.0.0.1:8080 -t .
```

### Step 3: Access Application
- **URL**: `http://127.0.0.1:8080`
- **Default Username**: `admin`
- **Default Password**: `Admin@12345`

---

## 🧪 Automated Verification Test Suite

Run the full end-to-end regression and verification suite:
```bash
php test_suite.php
```
Tests all 14 database tables, password hashing, brute-force rate limits, CSRF token issuance, RBAC permission matrices, opening stock logging, duplicate SKU constraints, atomic invoice numbering, customer ledger math, transaction concurrency locking, anti-overselling guards, RTO return stock restoration, investor profit tracking, weekly closings, and backup exports.

Run the live HTTP API integration suite:
```bash
php test_http.php
```

---

## 📂 Project Directory Structure

```
├── .htaccess                 # Apache security rules, direct access protection, gzip, headers
├── index.php                 # Main responsive application portal (Auth protected)
├── login.php                 # Secure login page with rate limiting and CSRF protection
├── logout.php                # Session termination and redirect
├── install.php               # Web-based step-by-step installation wizard
├── cli_install.php           # Terminal CLI database installer
├── DEPLOYMENT_GUIDE.md       # Comprehensive cPanel shared hosting deployment guide
│
├── api/                      # REST API Endpoints
│   ├── auth/                 # login, logout, password change, status
│   ├── backup/               # Full JSON database export and restore
│   ├── closings/             # Weekly financial closing previews and commitments
│   ├── customers/            # Customer directory, payments, and ledger statement
│   ├── dashboard/            # High-performance stats, charts, and metrics
│   ├── demo/                 # Relational demo data generator
│   ├── inventory/            # Real-time stock levels, manual adjustments, audit logs
│   ├── invoices/             # Invoice registry, search, next sequence preview
│   ├── investors/            # Investor capital, sponsored products, profit reports
│   ├── migration/            # Legacy desktop database.json importer
│   ├── payments/             # Customer payment recording
│   ├── products/             # Product catalog, categories, inventory setup
│   ├── reports/              # Daily audit, date-range analytics, financial export
│   ├── sales/                # Atomic order placement, invoice details, RTO returns
│   ├── settings/             # Store branding, currency, tax rates
│   └── users/                # Multi-user administration and role assignment
│
├── assets/                   # Bundled Client Assets (No external CDN dependencies)
│   ├── css/styles.css        # Professional dark/light responsive layout theme
│   ├── js/
│   │   ├── app.js            # Client-side state manager and dynamic view renderer
│   │   ├── chart.min.js      # Bundled Chart.js UMD library (Local)
│   │   └── lucide.min.js     # Bundled Lucide Icons library (Local)
│   └── images/               # App icons and brand logos
│
├── config/                   # System Configuration
│   ├── app.php               # Session security, constants, base URL detection
│   ├── database.php          # PDO connection factory and configuration generator
│   └── security.php          # CSRF tokens, rate limiting, and input sanitization
│
├── database/                 # Relational Database Definitions
│   ├── schema.sql            # Full 14-table schema with constraints and indexes
│   └── seed.sql              # Default permissions, sequences, and store settings
│
├── helpers/                  # Core Business & Infrastructure Helpers
│   ├── Audit.php             # System activity and security audit logger
│   ├── Auth.php              # Session-based auth and RBAC permission checker
│   ├── NumberSequence.php    # Concurrency-safe, collision-proof sequence generator
│   └── Response.php          # Standardized JSON response handler
│
├── middleware/               # Middleware Handlers
│   └── auth.php              # Authentication and CSRF validation barrier
│
└── storage/                  # Protected Application Storage (deny from web)
    ├── backups/              # Automated database backup archives
    ├── exports/              # CSV and report export files
    └── logs/                 # Operational and error log files
```

---

## 🔒 Security Best Practices Implemented

1. **SQL Injection Defense**: 100% prepared statements via PHP Data Objects (PDO) with strict type binding. Emulated prepares disabled (`PDO::ATTR_EMULATE_PREPARES => false`).
2. **Brute Force Defense**: IP and username-keyed rate limiting tracked in database (`login_attempts`). Auto-lockout after repeated failures with 15-minute cool-down window.
3. **Session Security**: Session cookies configured with `HttpOnly`, `SameSite=Lax`, and `Secure` (when HTTPS active). Session IDs regenerated on login to defeat session fixation.
4. **CSRF Mitigation**: Cryptographically secure 256-bit token issued per session, required on all data-mutating requests (`POST`, `PUT`, `DELETE`).
5. **Path Traversal & Direct File Access**: Sensitive directories (`config/`, `database/`, `helpers/`, `storage/`) blocked from direct HTTP access in `.htaccess`.
6. **No Insecure Backdoors**: The legacy desktop app's unauthenticated "Forgot Password" loophole was permanently removed and replaced with authenticated password changes and administrator overrides.
