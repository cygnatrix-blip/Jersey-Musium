<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper. Every query goes through prepared statements;
 * identifiers (table/column names) are only ever taken from code, never from user input.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $txDepth = 0;

    private function __construct()
    {
        $c = config('database');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);
        $this->pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $this->pdo->exec("SET time_zone = '" . date('P') . "', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(
                is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k),
                $v,
                match (true) {
                    is_int($v)  => PDO::PARAM_INT,
                    is_bool($v) => PDO::PARAM_BOOL,
                    $v === null => PDO::PARAM_NULL,
                    default     => PDO::PARAM_STR,
                }
            );
        }
        $stmt->execute();
        return $stmt;
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $cols)),
            implode(', ', array_map(fn ($c) => ":$c", $cols))
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE `table` SET ... WHERE $where. $where params must be named and not collide with column names. */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn ($c) => "`$c` = :set_$c", array_keys($data)));
        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = $v;
        }
        return $this->execute("UPDATE `$table` SET $set WHERE $where", $params + $whereParams);
    }

    /** Run $fn in a transaction (nesting-safe). Rolls back and rethrows on any error. */
    public function transaction(callable $fn): mixed
    {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
        }
        $this->txDepth++;
        try {
            $result = $fn($this);
            $this->txDepth--;
            if ($this->txDepth === 0) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            $this->txDepth--;
            if ($this->txDepth === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Builds "IN (:p0, :p1...)" safely. Returns [sqlFragment, params]. */
    public static function in(string $prefix, array $values): array
    {
        $values = array_values($values);
        if (!$values) {
            return ['(NULL)', []];
        }
        $keys = [];
        $params = [];
        foreach ($values as $i => $v) {
            $keys[] = ":{$prefix}{$i}";
            $params["{$prefix}{$i}"] = $v;
        }
        return ['(' . implode(', ', $keys) . ')', $params];
    }
}
