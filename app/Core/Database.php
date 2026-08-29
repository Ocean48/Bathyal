<?php

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): ?PDO
    {
        if (self::$instance === null) {
            $host = Config::get('db.host', 'db');
            $port = (int)Config::get('db.port', 3306);
            $dbName = Config::get('db.database', 'bathyal_db');
            $username = Config::get('db.username', 'bathyal_user');
            $password = Config::get('db.password', 'bathyal_secret');

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            // Primary connection attempt
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // If running on host outside docker container where 'db' hostname is unreachable, fallback to localhost port 3307 then 3306
                $fallbackHosts = [
                    ['host' => '127.0.0.1', 'port' => 3307],
                    ['host' => '127.0.0.1', 'port' => 3306],
                ];

                foreach ($fallbackHosts as $target) {
                    try {
                        $fallbackDsn = "mysql:host={$target['host']};port={$target['port']};dbname={$dbName};charset=utf8mb4";
                        self::$instance = new PDO($fallbackDsn, $username, $password, $options);
                        break;
                    } catch (PDOException $fe) {
                        // continue to next fallback
                    }
                }

                if (self::$instance === null) {
                    error_log("Database connection failed: " . $e->getMessage());
                    return null;
                }
            }
        }

        return self::$instance;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new \RuntimeException("Cannot start transaction: database unavailable.");
        }

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

    public static function fetchAll(string $sql, array $params = []): array
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            return [];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            return null;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            return null;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn($column);
    }

    public static function insertGetId(string $sql, array $params = []): string|int
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new \RuntimeException("Database unavailable for insert.");
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new \RuntimeException("Database unavailable for execute.");
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

