<?php
declare(strict_types=1);

namespace app\database;

use PDO;
use Exception;

class DatabaseManager
{
    protected static array $connections = [];
    protected static ?array $activeConfig = null;

    public static function getConfig(): array
    {
        if (self::$activeConfig !== null) {
            return self::$activeConfig;
        }

        $configFile = config_path('database.php');
        if (file_exists($configFile)) {
            $config = include $configFile;
            $default = $config['default'] ?? 'mysql';
            self::$activeConfig = $config['connections'][$default] ?? [];
            self::$activeConfig['driver_type'] = $default;
        } else {
            self::$activeConfig = [
                'driver_type' => 'mysql',
                'host'        => '127.0.0.1',
                'port'        => 3306,
                'database'    => 'email_system',
                'username'    => 'root',
                'password'    => '123456',
                'charset'     => 'utf8mb4',
            ];
        }

        return self::$activeConfig;
    }

    public static function setRuntimeConfig(array $config): void
    {
        self::$activeConfig = $config;
        self::$connections['default'] = null;
    }

    public static function getPdo(?array $customConfig = null): PDO
    {
        $cfg = $customConfig ?? self::getConfig();
        $cacheKey = md5(json_encode([$cfg['host'] ?? '', $cfg['port'] ?? '', $cfg['database'] ?? '', $cfg['username'] ?? '']));

        if ($customConfig === null && isset(self::$connections['default'])) {
            return self::$connections['default'];
        }

        if (isset(self::$connections[$cacheKey])) {
            return self::$connections[$cacheKey];
        }

        $driver = $cfg['driver_type'] ?? ($cfg['driver'] ?? 'mysql');
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = $cfg['port'] ?? ($driver === 'mysql' ? 3306 : 54321);
        $dbname = $cfg['database'] ?? '';
        $user = $cfg['username'] ?? 'root';
        $pass = $cfg['password'] ?? '';
        $charset = $cfg['charset'] ?? ($driver === 'mysql' ? 'utf8mb4' : 'utf8');

        if ($driver === 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        } elseif ($driver === 'kingbase' || $driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding={$charset}'";
        } else {
            throw new Exception("Unsupported database driver: {$driver}");
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        if ($customConfig === null) {
            self::$connections['default'] = $pdo;
        } else {
            self::$connections[$cacheKey] = $pdo;
        }

        return $pdo;
    }

    public static function getAdapter(?array $customConfig = null): DatabaseAdapterInterface
    {
        $cfg = $customConfig ?? self::getConfig();
        $driver = $cfg['driver_type'] ?? ($cfg['driver'] ?? 'mysql');

        if ($driver === 'mysql') {
            return new MySqlAdapter(self::getPdo($customConfig));
        }

        return new KingbaseAdapter(self::getPdo($customConfig));
    }
}
