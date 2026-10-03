<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use RuntimeException;

/**
 * Tiny active-record-ish base class. Deliberately blunt: arrays in, arrays out.
 */
abstract class Model
{
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    /** @var string[] columns that are booleans and must be normalised on write */
    protected static array $booleans = [];

    /** @var string[] columns automatically set on insert/update */
    protected static bool $timestamps = true;

    public static function tableName(): string
    {
        if (static::$table === '') {
            throw new RuntimeException('Model ' . static::class . ' has no table.');
        }
        return static::$table;
    }

    protected static function db(): Database
    {
        return Database::instance();
    }

    public static function find(int|string|null $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        return self::db()->selectOne(
            'SELECT * FROM {{' . static::tableName() . '}} WHERE ' . static::$primaryKey . ' = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        return self::db()->selectOne(
            'SELECT * FROM {{' . static::tableName() . '}} WHERE ' . $column . ' = :value LIMIT 1',
            ['value' => $value]
        );
    }

    /**
     * @param array<string,mixed> $where
     * @return array<int,array<string,mixed>>
     */
    public static function all(array $where = [], string $order = '', ?int $limit = null, int $offset = 0): array
    {
        [$clause, $params] = self::buildWhere($where);
        $sql = 'SELECT * FROM {{' . static::tableName() . '}}' . $clause;
        if ($order !== '') {
            $sql .= ' ORDER BY ' . $order;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        return self::db()->select($sql, $params);
    }

    /** @param array<string,mixed> $where */
    public static function count(array $where = []): int
    {
        [$clause, $params] = self::buildWhere($where);
        return (int) self::db()->scalar('SELECT COUNT(*) FROM {{' . static::tableName() . '}}' . $clause, $params);
    }

    /**
     * @param array<string,mixed> $where
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,perPage:int,pages:int}
     */
    public static function paginate(array $where = [], int $page = 1, int $perPage = 12, string $order = 'id DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = self::count($where);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $items = self::all($where, $order, $perPage, ($page - 1) * $perPage);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $data = static::prepare($data, true);
        return self::db()->insert(static::tableName(), $data);
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): int
    {
        $data = static::prepare($data, false);
        if ($data === []) {
            return 0;
        }
        return self::db()->update(
            static::tableName(),
            $data,
            static::$primaryKey . ' = :pk',
            ['pk' => $id]
        );
    }

    public static function delete(int $id): int
    {
        return self::db()->delete(static::tableName(), static::$primaryKey . ' = :pk', ['pk' => $id]);
    }

    /** @param array<string,mixed> $data */
    protected static function prepare(array $data, bool $insert): array
    {
        foreach (static::$booleans as $column) {
            if (array_key_exists($column, $data)) {
                $data[$column] = $data[$column] ? 1 : 0;
            }
        }
        if (static::$timestamps) {
            if ($insert && !array_key_exists('created_at', $data)) {
                $data['created_at'] = self::db()->now();
            }
            if (!$insert) {
                $data['updated_at'] = self::db()->now();
            }
        }
        return $data;
    }

    /**
     * @param array<string,mixed> $where
     * @return array{0:string,1:array<string,mixed>}
     */
    protected static function buildWhere(array $where): array
    {
        if ($where === []) {
            return ['', []];
        }
        $parts = [];
        $params = [];
        $index = 0;
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    $parts[] = '1 = 0';
                    continue;
                }
                $placeholders = [];
                foreach ($value as $item) {
                    $key = 'w' . $index++;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $item;
                }
                $parts[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
                continue;
            }
            if ($value === null) {
                $parts[] = $column . ' IS NULL';
                continue;
            }
            $key = 'w' . $index++;
            $parts[] = $column . ' = :' . $key;
            $params[$key] = $value;
        }
        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    public static function exists(int|string|null $id): bool
    {
        return self::find($id) !== null;
    }
}
