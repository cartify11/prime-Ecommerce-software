<?php
/**
 * Prime E Commerce Hub - Database Connection Manager
 * Supports cPanel Shared Hosting (MySQL/MariaDB via PDO)
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConfig(): array {
        $configFile = __DIR__ . '/db_config.php';
        if (file_exists($configFile)) {
            $custom = require $configFile;
            if (is_array($custom)) {
                return $custom;
            }
        }

        return [
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_NAME') ?: 'prime_hub',
            'username' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
            'charset'  => 'utf8mb4'
        ];
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $config = self::getConfig();
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $options);
            } catch (PDOException $e) {
                // Production safe error logging - never expose raw DB credentials to user
                error_log("Database Connection Error: " . $e->getMessage());
                throw new Exception("Unable to connect to the database. Please verify database configuration.");
            }
        }

        return self::$instance;
    }

    public static function saveConfig(array $newConfig): bool {
        $configFile = __DIR__ . '/db_config.php';
        $content = "<?php\n// Auto-generated database configuration\nreturn " . var_export($newConfig, true) . ";\n";
        return file_put_contents($configFile, $content, LOCK_EX) !== false;
    }
}
