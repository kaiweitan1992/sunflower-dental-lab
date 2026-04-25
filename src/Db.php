<?php
declare(strict_types=1);

namespace Sunflower;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper. All queries go through prepared statements.
 * Configured for MySQL 8 with utf8mb4 and emulated prepares OFF.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::int('DB_PORT', 3306);
        $name = Config::get('DB_NAME', 'sunflower');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');
        $ssl  = Config::bool('DB_SSL', false);

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];
        if ($ssl) {
            // DO Managed MySQL ships a CA bundle. The buildpack auto-trusts
            // most public CAs; this enables TLS without strict CN check.
            // PHP 8.5 introduced the Pdo\Mysql namespaced version. Use whichever exists.
            if (defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
                $opts[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
            } else {
                $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $opts);
            self::$pdo->exec("SET time_zone = '+00:00'");
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            // Don't leak credentials in the message.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return self::$pdo;
    }

    /** Run a SELECT and return all rows. */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Run a SELECT and return one row (or null). */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Run an INSERT / UPDATE / DELETE; returns affected row count. */
    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** Last inserted id as int. */
    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** Run a callable inside a transaction with auto-rollback on exception. */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
