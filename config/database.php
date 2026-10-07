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

        $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
        $database = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: ($_ENV['DB_NAME'] ?? ($_ENV['DB_DATABASE'] ?? 'prime_hub')));
        $username = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: ($_ENV['DB_USER'] ?? ($_ENV['DB_USERNAME'] ?? 'root')));
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? '')));

        return [
            'host'     => $host,
            'port'     => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
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

            // Cloud DB SSL Support (e.g. Aiven, TiDB, PlanetScale)
            if (getenv('DB_SSL') === 'true' || getenv('DB_SSL_MODE') === 'REQUIRED') {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                throw new Exception("Unable to connect to the database. Error: " . $e->getMessage());
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
