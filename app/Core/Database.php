<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO 数据库封装（MySQL / SQLite 双驱动）
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = (array)Config::get('db', []);
            $driver = (string)($c['driver'] ?? 'mysql');

            if ($driver === 'sqlite') {
                $path = (string)($c['sqlite_path'] ?? GW_STORAGE . '/gitweb.sqlite');
                if (!is_dir(dirname($path))) {
                    @mkdir(dirname($path), 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            } else {
                $charset = (string)($c['charset'] ?? 'utf8mb4');
                $socket = (string)($c['unix_socket'] ?? '');
                $dsn = $socket !== ''
                    ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $c['database'], $charset)
                    : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int)($c['port'] ?? 3306), $c['database'], $charset);
                self::$pdo = new PDO($dsn, (string)($c['username'] ?? ''), (string)($c['password'] ?? ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @return int|string 自增 ID */
    public static function insert(string $table, array $data): int|string
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        foreach ($columns as $col) {
            self::assertIdentifier($col);
        }
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, '`' . implode('`, `', $columns) . '`', $placeholders);
        self::query($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        self::assertIdentifier($table);
        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            self::assertIdentifier((string)$col);
            $sets[] = "`$col` = ?";
            $values[] = $val;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::query($sql, array_merge($values, $params))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        self::assertIdentifier($table);
        return self::query(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $params)->rowCount();
    }

    public static function count(string $from, string $where = '1', array $params = []): int
    {
        self::assertFrom($from);
        $row = self::fetch(sprintf('SELECT COUNT(*) AS c FROM %s WHERE %s', $from, $where), $params);
        return (int)($row['c'] ?? 0);
    }

    public static function exec(string $sql): \PDOStatement
    {
        return self::pdo()->exec($sql) >= 0 ? self::query('SELECT 1') : self::query('SELECT 1');
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('非法标识符: ' . $identifier);
        }
    }

    /** FROM 表达式校验：仅允许内部硬编码的 表名/别名/JOIN ... ON ... 形式 */
    private static function assertFrom(string $from): void
    {
        $pattern = '/^[A-Za-z_][A-Za-z0-9_]*( [a-z]{1,2})?( JOIN [A-Za-z_][A-Za-z0-9_]* [a-z]{1,2} ON [a-z]{1,2}\.[A-Za-z0-9_]+ = [a-z]{1,2}\.[A-Za-z0-9_]+)?$/i';
        if (!preg_match($pattern, $from)) {
            throw new \InvalidArgumentException('非法的 FROM 表达式: ' . $from);
        }
    }
}
