<?php
/**
 * Prime E Commerce Hub - System Installation & Initial Setup Wizard
 * Compatible with cPanel Shared Hosting & Terminal CLI
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

// Check if already installed
$isInstalled = false;
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'system_installed'");
    $val = $stmt->fetchColumn();
    if ($val === '1') {
        // Also check if an active owner account exists
        $ownerCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'");
        if ((int)$ownerCheck->fetchColumn() > 0) {
            $isInstalled = true;
        }
    }
} catch (Exception $e) {
    // Database or tables do not exist yet
}

if ($isInstalled && php_sapi_name() !== 'cli') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$error = '';
$success = '';

// Handle web submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? 'prime_hub');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';

    $adminName  = trim($_POST['admin_name'] ?? '');
    $adminUser  = trim($_POST['admin_username'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_password'] ?? '';
    $adminConf  = $_POST['admin_confirm'] ?? '';

    if (empty($adminName) || empty($adminUser) || empty($adminPass)) {
        $error = 'Owner Name, Username, and Password are required.';
    } elseif (strlen($adminPass) < 6) {
        $error = 'Admin password must be at least 6 characters.';
    } elseif ($adminPass !== $adminConf) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // 1. Test database connection
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $rawPdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Create database if not exists
            $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $rawPdo->exec("USE `{$dbName}`");

            // Save db_config.php
            Database::saveConfig([
                'host'     => $dbHost,
                'port'     => $dbPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
                'charset'  => 'utf8mb4'
            ]);

            // 2. Import schema.sql
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (file_exists($schemaFile)) {
                $rawPdo->exec(file_get_contents($schemaFile));
            }

            // 3. Import seed.sql
            $seedFile = __DIR__ . '/database/seed.sql';
            if (file_exists($seedFile)) {
                $rawPdo->exec(file_get_contents($seedFile));
            }

            // 4. Create Owner / Super Admin account
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $userStmt = $rawPdo->prepare("
                INSERT INTO users (username, name, email, password_hash, role, status, created_at)
                VALUES (?, ?, ?, ?, 'owner', 'active', NOW())
            ");
            $userStmt->execute([$adminUser, $adminName, $adminEmail ?: null, $hash]);

            // Mark system as installed
            $rawPdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('system_installed', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");

            $success = "Installation completed successfully! You can now log in with your administrator account.";
        } catch (Exception $e) {
            $error = 'Installation Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Hub - Installation & Setup</title>
    <link rel="icon" type="image/png" href="assets/images/icon.png">
    <style>
        :root {
            --bg-primary: #0a0d14;
            --bg-secondary: #0f131c;
            --bg-card: rgba(18, 22, 32, 0.95);
            --border-color: rgba(226, 177, 60, 0.2);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-gold: #e2b13c;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: var(--bg-primary); color: var(--text-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .install-box { width: 100%; max-width: 600px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 36px; box-shadow: 0 10px 40px rgba(0,0,0,0.7); }
        .logo-section { text-align: center; margin-bottom: 28px; }
        .logo-section img { width: 56px; height: 56px; object-fit: contain; margin-bottom: 12px; }
        .logo-section h1 { font-size: 1.6rem; color: var(--accent-gold); font-weight: 700; margin-bottom: 4px; }
        .logo-section p { font-size: 0.85rem; color: var(--text-secondary); }
        .form-section { margin-bottom: 24px; }
        .section-heading { font-size: 0.9rem; text-transform: uppercase; color: var(--accent-gold); font-weight: 600; margin-bottom: 14px; padding-bottom: 6px; border-bottom: 1px solid rgba(226, 177, 60, 0.15); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 14px; }
        .form-group.full { grid-column: span 2; }
        label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 6px; font-weight: 500; }
        input { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-size: 0.9rem; outline: none; transition: 0.2s; }
        input:focus { border-color: var(--accent-gold); box-shadow: 0 0 10px rgba(226, 177, 60, 0.2); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; }
        .alert.error { background: rgba(244,63,94,0.15); border: 1px solid var(--accent-rose); color: #fecdd3; }
        .alert.success { background: rgba(16,185,129,0.15); border: 1px solid var(--accent-emerald); color: #a7f3d0; }
        .btn-submit { width: 100%; padding: 12px; background: var(--accent-gold); color: #000; font-weight: 700; font-size: 1rem; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { opacity: 0.9; }
        .btn-login { display: block; text-align: center; text-decoration: none; width: 100%; padding: 12px; background: var(--accent-emerald); color: #fff; font-weight: 700; border-radius: 8px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="install-box">
        <div class="logo-section">
            <img src="assets/images/icon.png" alt="Logo" onerror="this.style.display='none'">
            <h1>Prime E Commerce Hub</h1>
            <p>cPanel & Web Deployment Setup Wizard</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <a href="login.php" class="btn-login">Go to Login Page &rarr;</a>
        <?php else: ?>
            <form method="POST" action="install.php">
                <div class="form-section">
                    <div class="section-heading">1. MySQL Database Configuration</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Database Host</label>
                            <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? '127.0.0.1'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Port</label>
                            <input type="text" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Database Name</label>
                            <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'prime_hub'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Database Username</label>
                            <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                        </div>
                        <div class="form-group full">
                            <label>Database Password</label>
                            <input type="password" name="db_pass" placeholder="Leave blank if no password (e.g. default XAMPP)" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-heading">2. Owner / Super Admin Account</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Owner Full Name</label>
                            <input type="text" name="admin_name" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? 'Asif Ghafoor'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Admin Username</label>
                            <input type="text" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                        </div>
                        <div class="form-group full">
                            <label>Email Address</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@primehub.local'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Password (min 6 chars)</label>
                            <input type="password" name="admin_password" placeholder="Create strong password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="admin_confirm" placeholder="Confirm password" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Initialize Database & Create Admin &rarr;</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
