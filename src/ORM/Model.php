<?php

declare(strict_types=1);

namespace Marwa\DB\ORM;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Support\Helpers;
use Marwa\DB\ORM\Traits\Timestamps;
use Marwa\DB\ORM\Traits\SoftDeletes;
use Marwa\DB\ORM\Traits\MassAssignment;
use Marwa\DB\ORM\Traits\CastsAttributes;
use Marwa\DB\ORM\Traits\HasRelationships;

abstract class Model
{
    use Timestamps, SoftDeletes, MassAssignment, CastsAttributes, HasRelationships;

    /** Table + key */
    protected static string $table;
    protected static string $primaryKey = 'id';

    /** Behaviors */
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;

    /** Connection (shared by all models) */
    protected static ?ConnectionManager $cm = null;
    protected static string $connection = 'default';

    /** State */
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    /** Soft delete flags for next query */
    protected static bool $includeTrashed = false;
    protected static bool $onlyTrashed = false;

    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->attributes = $attributes;
        $this->original   = $attributes;
        $this->exists     = $exists;
    }

    /** Wire a ConnectionManager for all models */
    public static function setConnectionManager(ConnectionManager $cm, string $connection = 'default'): void
    {
        static::$cm = $cm;
        static::$connection = $connection;
    }

    /** Model‑aware query builder (hydrates to models, supports eager loading) */
    public static function query(): \Marwa\DB\ORM\QueryBuilder
    {
        if (!static::$cm) {
            throw new \RuntimeException('ConnectionManager not set. Call Model::setConnectionManager().');
        }
        /** @var class-string<static> $cls */
        $cls = static::class;
        return new \Marwa\DB\ORM\QueryBuilder(static::$cm, $cls, static::$connection);
    }

    /** Convenience to start a chain quickly */
    public static function where(string $col, string $op, mixed $val): \Marwa\DB\ORM\QueryBuilder
    {
        return static::query()->where($col, $op, $val);
    }

    /** Base low‑level builder for internal writes */
    protected static function baseQuery(): \Marwa\DB\Query\Builder
    {
        if (!static::$cm) {
            throw new \RuntimeException('ConnectionManager not set. Call Model::setConnectionManager().');
        }
        return (new \Marwa\DB\Query\Builder(static::$cm, static::$connection))->table(static::$table);
    }

    /** Apply default soft‑delete filter to a low‑level builder (if available) */
    protected static function applySoftDeleteFilter(\Marwa\DB\Query\Builder $qb): \Marwa\DB\Query\Builder
    {
        if (!static::$softDeletes) {
            return $qb;
        }
        if (static::$onlyTrashed) {
            // requires whereNotNull in low-level Builder (recommended)
            if (method_exists($qb, 'whereNotNull')) {
                $qb->whereNotNull('deleted_at');
            }
        } elseif (!static::$includeTrashed) {
            if (method_exists($qb, 'whereNull')) {
                $qb->whereNull('deleted_at');
            }
        }
        // reset flags for next call
        static::$includeTrashed = false;
        static::$onlyTrashed = false;
        return $qb;
    }

    /** ===== Fetchers ===== */

    /** @return array<int, static> */
    public static function all(): array
    {
        $qb = static::applySoftDeleteFilter(static::baseQuery());
        $rows = $qb->get();
        return array_map(fn($r) => static::hydrateRow($r), $rows);
    }

    public static function find(int|string $id): ?static
    {
        $qb = static::applySoftDeleteFilter(static::baseQuery());
        $row = $qb->where(static::$primaryKey, '=', $id)->first();
        return $row ? static::hydrateRow($row) : null;
    }

    public static function findOrFail(int|string $id): static
    {
        $m = static::find($id);
        if (!$m) {
            throw new \RuntimeException(static::class . " not found for " . static::$primaryKey . "={$id}");
        }
        return $m;
    }

    /** ===== Mutators ===== */

    /** Mass create + persist (fillable + timestamps) */
    public static function create(array $attributes): static
    {
        $instance = new static();
        $data = static::filterFillable($attributes);

        if (static::$timestamps) {
            $instance->touchTimestamps($data);
        }

        static::baseQuery()->insert($data);

        $id = static::$cm?->getPdo(static::$connection)->lastInsertId();
        if ($id && is_numeric($id)) {
            $fresh = static::find((int)$id);
            if ($fresh) return $fresh;
        }
        return new static($data, true);
    }

    /** Insert or update this instance */
    public function save(): bool
    {
        $data = $this->getDirty();
        if (static::$timestamps) {
            $this->touchTimestamps($data);
        }

        if ($this->exists) {
            if (!$data) return true;
            $affected = static::baseQuery()
                ->where(static::$primaryKey, '=', $this->getKey())
                ->update($data);
            if ($affected > 0) {
                $this->original = array_replace($this->original, $data);
                $this->attributes = array_replace($this->attributes, $data);
                return true;
            }
            return false;
        }

        // Insert path
        $insertData = $this->attributes + $data;
        if (static::$timestamps) {
            $this->touchTimestamps($insertData);
        }

        static::baseQuery()->insert($insertData);

        $id = static::$cm?->getPdo(static::$connection)->lastInsertId();
        if ($id && is_numeric($id)) {
            $this->attributes[static::$primaryKey] = (int)$id;
        }
        $this->original = $this->attributes;
        $this->exists = true;
        return true;
    }

    /** Soft delete (if enabled) otherwise hard delete */
    public function delete(): bool
    {
        if (!$this->exists) return false;

        if (static::$softDeletes) {
            $data = ['deleted_at' => Helpers::now()];
            $affected = static::baseQuery()
                ->where(static::$primaryKey, '=', $this->getKey())
                ->update($data);
            if ($affected > 0) {
                $this->attributes['deleted_at'] = $data['deleted_at'];
                $this->original['deleted_at']   = $data['deleted_at'];
                return true;
            }
            return false;
        }

        $affected = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete();

        if ($affected > 0) {
            $this->exists = false;
            return true;
        }
        return false;
    }

    /** Permanently delete (ignores soft deletes) */
    public function forceDelete(): bool
    {
        if (!$this->exists) return false;

        $affected = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete();

        if ($affected > 0) {
            $this->exists = false;
            return true;
        }
        return false;
    }

    /** Restore a soft-deleted row */
    public function restore(): bool
    {
        if (!static::$softDeletes) return false;

        $affected = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->update(['deleted_at' => null]);

        if ($affected > 0) {
            $this->attributes['deleted_at'] = null;
            $this->original['deleted_at']   = null;
            return true;
        }
        return false;
    }

    /** Reload from DB */
    public function refresh(): static
    {
        $row = static::baseQuery()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->first();

        if ($row) {
            $arr = is_array($row) ? $row : (array)$row;
            $this->attributes = $arr;
            $this->original   = $arr;
            $this->exists     = true;
        }
        return $this;
    }

    /** ===== Mass assignment / dirty tracking ===== */

    public function fill(array $attributes): static
    {
        $filtered = static::filterFillable($attributes);
        $this->attributes = array_replace($this->attributes, $filtered);
        return $this;
    }

    /** @return array<string,mixed> */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $k => $v) {
            $orig = $this->original[$k] ?? null;
            if ($v !== $orig) $dirty[$k] = $v;
        }
        return $dirty;
    }

    /** ===== Accessors / Serialization ===== */

    public function getKey(): int|string|null
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return static::$primaryKey;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $arr = [];
        foreach ($this->attributes as $k => $v) {
            $arr[$k] = $this->castOut($k, $v);
        }
        foreach ($this->relations as $k => $rel) {
            $arr[$k] = is_array($rel)
                ? array_map(fn($m) => $m instanceof self ? $m->toArray() : $m, $rel)
                : ($rel instanceof self ? $rel->toArray() : $rel);
        }
        return $arr;
    }

    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options) ?: '{}';
    }

    /** Soft delete toggles for next fetch */
    public static function withTrashed(): static
    {
        static::$includeTrashed = true;
        return new static();
    }

    public static function onlyTrashed(): static
    {
        static::$onlyTrashed = true;
        return new static();
    }

    /** Hydrate a row into a model instance marked as existing */
    protected static function hydrateRow(array|object $row): static
    {
        $data = is_array($row) ? $row : (array)$row;
        return new static($data, true);
    }
}
