<?php
/**
 * CLI Installer Script for Headless Initial Setup and Automated Testing
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

echo "=== Prime E Commerce Hub CLI Installer ===\n";

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'prime_hub';
$dbUser = 'root';
$dbPass = '';

$adminName = 'Super Admin';
$adminUser = 'admin';
$adminEmail = 'admin@primecommerce.com';
$adminPass = 'Admin@12345';

try {
    echo "1. Connecting to MySQL at {$dbHost}:{$dbPort}...\n";
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $rawPdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "   Connected successfully.\n";

    echo "2. Creating database `{$dbName}` if not exists...\n";
    $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $rawPdo->exec("USE `{$dbName}`");

    echo "3. Saving database config to config/db_config.php...\n";
    Database::saveConfig([
        'host'     => $dbHost,
        'port'     => $dbPort,
        'database' => $dbName,
        'username' => $dbUser,
        'password' => $dbPass,
        'charset'  => 'utf8mb4'
    ]);

    echo "4. Executing database/schema.sql...\n";
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at $schemaFile");
    }
    $rawPdo->exec(file_get_contents($schemaFile));
    echo "   Schema applied successfully.\n";

    echo "5. Executing database/seed.sql...\n";
    $seedFile = __DIR__ . '/database/seed.sql';
    if (!file_exists($seedFile)) {
        throw new Exception("seed.sql not found at $seedFile");
    }
    $rawPdo->exec(file_get_contents($seedFile));
    echo "   Seed data inserted successfully.\n";

    echo "6. Creating or updating owner admin account...\n";
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    
    // Check if user already exists
    $check = $rawPdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$adminUser]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $update = $rawPdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = 'owner', status = 'active' WHERE id = ?");
        $update->execute([$adminName, $adminEmail, $hash, $existing['id']]);
        echo "   Updated existing user '{$adminUser}'.\n";
    } else {
        $insert = $rawPdo->prepare("INSERT INTO users (username, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, 'owner', 'active', NOW())");
        $insert->execute([$adminUser, $adminName, $adminEmail, $hash]);
        echo "   Created new user '{$adminUser}'.\n";
    }

    $rawPdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('system_installed', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");

    echo "\n=== Installation Finished Successfully! ===\n";
    echo "Admin Username: {$adminUser}\n";
    echo "Admin Password: {$adminPass}\n";
    exit(0);
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
