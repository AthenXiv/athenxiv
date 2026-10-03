<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper that speaks both MySQL (production) and SQLite (local
 * verification) so the whole platform can be exercised without a MySQL server.
 */
final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;

    private string $driver;

    private string $prefix;

    /** @var int */
    private int $transactionLevel = 0;

    private function __construct()
    {
        $driver = (string) Config::get('db.driver', 'mysql');
        $this->driver = $driver;
        $this->prefix = (string) Config::get('db.prefix', '');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $path = (string) Config::get('db.sqlite_path');
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
                $this->pdo->exec('PRAGMA journal_mode = WAL');
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA busy_timeout = 5000');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    (string) Config::get('db.host'),
                    (int) Config::get('db.port', 3306),
                    (string) Config::get('db.database'),
                    (string) Config::get('db.charset', 'utf8mb4')
                );
                if (!empty(Config::get('db.persistent'))) {
                    $options[PDO::ATTR_PERSISTENT] = true;
                }
                $options[PDO::ATTR_TIMEOUT] = 10;
                $this->pdo = new PDO(
                    $dsn,
                    (string) Config::get('db.username'),
                    (string) Config::get('db.password'),
                    $options
                );
                $this->pdo->exec("SET NAMES " . (string) Config::get('db.charset', 'utf8mb4'));
                $this->pdo->exec("SET time_zone = '+00:00'");
            }
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed (' . $driver . '): ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function instance(): Database
    {
        return self::$instance ??= new self();
    }

    /** Fresh connection, used by the installer which must not cache state. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /** @param array<string|int,mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($this->rewrite($sql));
        foreach ($params as $key => $value) {
            $placeholder = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            $statement->bindValue($placeholder, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    /** @param array<string|int,mixed> $params */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $quoted = array_map(fn (string $c): string => $this->quoteIdentifier($c), $columns);
        $placeholders = array_map(fn (string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table($table)),
            implode(', ', $quoted),
            implode(', ', $placeholders)
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = $this->quoteIdentifier($column) . ' = :set_' . $column;
            $params['set_' . $column] = $value;
        }
        $params = array_merge($params, $whereParams);
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($this->table($table)),
            implode(', ', $sets),
            $where
        );
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($this->table($table)),
            $where
        );
        return $this->query($sql, $params)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->begin();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function begin(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT athenaeum_' . $this->transactionLevel);
        }
        $this->transactionLevel++;
    }

    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            return;
        }
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT athenaeum_' . $this->transactionLevel);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel === 0) {
            return;
        }
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT athenaeum_' . $this->transactionLevel);
        }
    }

    /** True when the schema is present (used by the installer / self-check). */
    public function hasTable(string $table): bool
    {
        $name = $this->table($table);
        try {
            if ($this->isSqlite()) {
                return (bool) $this->scalar(
                    "SELECT 1 FROM sqlite_master WHERE type IN ('table','view') AND name = :n",
                    ['n' => $name]
                );
            }
            return (bool) $this->scalar(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :n',
                ['n' => $name]
            );
        } catch (PDOException) {
            return false;
        }
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($this->isSqlite()) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Allows writing a single portable SQL dialect in repositories:
     * `{{table}}` is replaced with the prefixed, quoted table name.
     */
    private function rewrite(string $sql): string
    {
        if (str_contains($sql, '{{')) {
            $sql = preg_replace_callback(
                '/\{\{([a-z0-9_]+)\}\}/i',
                fn (array $m): string => $this->quoteIdentifier($this->table($m[1])),
                $sql
            ) ?? $sql;
        }
        if ($this->isSqlite()) {
            // MySQL-isms that appear in a handful of queries.
            $sql = str_replace('NOW()', "datetime('now')", $sql);
        }
        return $sql;
    }

    /** Portable "current timestamp" literal for direct SQL use. */
    public function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** True when a column exists; used by bin/migrate.php to stay idempotent. */
    public function hasColumn(string $table, string $column): bool
    {
        $name = $this->table($table);
        try {
            if ($this->isSqlite()) {
                foreach ($this->select('PRAGMA table_info(' . $this->quoteIdentifier($name) . ')') as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }
            return (bool) $this->scalar(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()'
                . ' AND table_name = :t AND column_name = :c',
                ['t' => $name, 'c' => $column]
            );
        } catch (PDOException) {
            return false;
        }
    }

    /** Add a column when it is missing, with a dialect specific type. */
    public function addColumn(string $table, string $column, string $mysqlType, string $sqliteType): bool
    {
        if ($this->hasColumn($table, $column)) {
            return false;
        }
        $type = $this->isSqlite() ? $sqliteType : $mysqlType;
        $this->pdo->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $this->quoteIdentifier($this->table($table)),
            $this->quoteIdentifier($column),
            $type
        ));
        return true;
    }

    /** Create a table from a portable definition (no-op when it exists). */
    public function createTable(string $table, string $mysqlBody, string $sqliteBody): bool
    {
        if ($this->hasTable($table)) {
            return false;
        }
        $this->pdo->exec(sprintf(
            'CREATE TABLE %s (%s)',
            $this->quoteIdentifier($this->table($table)),
            $this->isSqlite() ? $sqliteBody : $mysqlBody
        ));
        return true;
    }
}
