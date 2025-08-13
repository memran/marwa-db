<?php

declare(strict_types=1);

namespace Marwa\DB\ORM;

use Marwa\DB\Query\Builder as QueryBuilder;
use Marwa\DB\Connection\ConnectionManager;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;

    protected array $attributes = [];
    protected array $relations = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function query(ConnectionManager $cm, string $conn = 'default'): QueryBuilder
    {
        return (new QueryBuilder($cm, $conn))->table(static::$table);
    }

    public static function find(ConnectionManager $cm, int|string $id, string $conn = 'default'): ?self
    {
        $row = static::query($cm, $conn)->where(static::$primaryKey, '=', $id)->first();
        return $row ? new static($row) : null;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function toArray(): array
    {
        $arr = $this->attributes;
        foreach ($this->relations as $k => $v) {
            $arr[$k] = is_array($v) ? array_map(fn($m) => $m->toArray(), $v) : ($v?->toArray() ?? null);
        }
        return $arr;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
