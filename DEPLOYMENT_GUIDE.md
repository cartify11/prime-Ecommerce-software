# Production Deployment Guide: cPanel Shared Hosting

This guide outlines the complete, step-by-step process for deploying **Prime E Commerce Hub** onto any standard **cPanel Shared Hosting** environment (e.g. Namecheap, Bluehost, Hostinger, GoDaddy, SiteGround, cPanel with Apache + MySQL/MariaDB).

---

## 📋 System Requirements

| Component | Minimum Version | Recommended Version |
| :--- | :--- | :--- |
| **Web Server** | Apache 2.4+ with `mod_rewrite` | Apache 2.4+ |
| **PHP Runtime** | PHP 7.4.x | PHP 8.1.x or 8.2.x |
| **Database** | MySQL 5.7+ or MariaDB 10.3+ | MariaDB 10.6+ / MySQL 8.0+ |
| **PHP Extensions** | `pdo_mysql`, `json`, `session`, `mbstring`, `curl` | Default on 99% of cPanel hosts |
| **HTTPS Certificate** | Any valid SSL (Let's Encrypt / AutoSSL) | Recommended for production |

---

## 🛠️ Step-by-Step cPanel Deployment

### Step 1: Prepare the Files for Upload

Select all web project files **excluding** the old desktop binaries (`prime-ecommerce-hub.exe`, `*.dll`, `*.pak`, `resources/app.asar`):
- `index.php`, `login.php`, `logout.php`, `install.php`, `.htaccess`
- `api/`
- `assets/`
- `config/`
- `database/`
- `helpers/`
- `middleware/`
- `storage/`

Compress these files into a single `.zip` file (e.g. `prime-hub-web.zip`).

---

### Step 2: Upload Files via cPanel File Manager

1. Log into your cPanel account.
2. Open **File Manager** and navigate to your target root:
   - For primary domain: `public_html/`
   - For subdomain or subfolder: `public_html/hub/` or `subdomain_directory/`
3. Click **Upload** in the top toolbar, select `prime-hub-web.zip`, and wait for upload completion (100%).
4. Back in File Manager, right-click `prime-hub-web.zip` and select **Extract**.
5. Ensure the extracted files sit directly in the root of your desired directory.
6. Verify that `.htaccess` is present. (If hidden, click **Settings** in the top right of File Manager and check *"Show Hidden Files (dotfiles)"*).

---

### Step 3: Create the MySQL Database & User

1. In cPanel, navigate to **Databases** &rarr; **MySQL Database Wizard** (or **MySQL Databases**).
2. **Create a Database**:
   - Name: e.g. `cpaneluser_primehub`
   - Click *Next Step*.
3. **Create Database User**:
   - Username: e.g. `cpaneluser_hubadmin`
   - Password: Generate a strong, random password. **Save this password safely.**
   - Click *Create User*.
4. **Assign Privileges**:
   - Check **ALL PRIVILEGES**.
   - Click *Make Changes*.

---

### Step 4: Run the Web Installation Wizard

1. Open your web browser and navigate to:
   ```
   https://yourdomain.com/install.php
   ```
   *(Or `https://yourdomain.com/hub/install.php` if installed in a subfolder)*
2. The installation wizard will appear:
   - **Database Host**: `localhost` (or `127.0.0.1`)
   - **Database Port**: `3306`
   - **Database Name**: The database created in Step 3 (e.g. `cpaneluser_primehub`)
   - **Database Username**: The user created in Step 3 (e.g. `cpaneluser_hubadmin`)
   - **Database Password**: The user password set in Step 3
   - **Owner Full Name**: Your name (e.g. `Business Owner`)
   - **Admin Username**: Desired admin username (e.g. `admin`)
   - **Admin Password**: Desired secure admin password
3. Click **Initialize Database & Create Admin &rarr;**.
4. The wizard will automatically:
   - Test database connectivity.
   - Generate `config/db_config.php` with safe credentials.
   - Execute `database/schema.sql` (creating all 14 tables with keys & indexes).
   - Execute `database/seed.sql` (populating sequences, permissions, and settings).
   - Create your Super Admin / Owner account.
   - Lock the installer from further execution.

---

### Step 5: Post-Installation Security Hardening

1. **Delete or Rename the Installer**:
   In cPanel File Manager, delete `install.php` and `cli_install.php` so no one can re-run the wizard.
2. **Verify File Permissions**:
   - Folders: `755`
   - Files: `644`
   - `storage/` directory and its subfolders: `755` or `775` (must be writable by the web server).
3. **Verify Apache Direct Access Protections**:
   Try accessing `https://yourdomain.com/config/database.php` in your browser.
   It should return **HTTP 403 Forbidden** due to the `.htaccess` security rules.

---

### Step 6: Migrate Data from Legacy Desktop Application (Optional)

If you have existing business data from the old desktop Electron version:
1. In the old desktop app, click **Settings & Backup** &rarr; **Export Database** (produces a `.json` backup file).
2. Log into the new web system as **Owner** (`admin`).
3. Navigate to **Settings** &rarr; **Database Migration & Backup**.
4. Choose **Import Legacy Desktop JSON**, select your `.json` file, and click **Start Migration**.
5. The relational importer will seamlessly:
   - Import all Investors and preserve capital records.
   - Import all Customers and past balances.
   - Import all Products, SKUs, and stock quantities.
   - Import all Historical Invoices, sale items, and profit calculations.
   - Maintain all relational linkages without creating duplicates.

---

### Step 7: Ongoing Maintenance & Backups

- **Automated Web Backups**: Navigate to **Settings** &rarr; **Database Backup** &rarr; **Create Backup Now**. You can download full JSON snapshots at any time.
- **cPanel phpMyAdmin Backups**: You can also use cPanel's built-in phpMyAdmin to perform raw SQL dumps of the database whenever desired.
- **PHP Version Upgrades**: The codebase is fully compatible with PHP 7.4 through PHP 8.3. You can safely switch PHP versions via cPanel's **MultiPHP Manager**.
