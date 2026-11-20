<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class Database
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException("Cannot unserialize singleton Database connection.");
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
        self::$instance = null;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = self::$config['host'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int)(self::$config['port'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $dbname = self::$config['dbname'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'mkr_college_erp';
        $user = self::$config['user'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $pass = self::$config['pass'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
        $charset = self::$config['charset'] ?? $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            Logger::exception($e, 'database_connect', ['host' => $host, 'database' => $dbname]);
            throw new RuntimeException("Database service unavailable. Please contact the system administrator.", 500, $e);
        }

        return self::$instance;
    }

    public static function getInstance(): PDO
    {
        return self::getConnection();
    }

    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool
    {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }

    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    /**
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
