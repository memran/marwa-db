<?php

declare(strict_types=1);

namespace Marwa\DB\ORM\Traits;

trait HasQuery
{
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

    public static function where(string $col, mixed $op, mixed $val = null): \Marwa\DB\ORM\QueryBuilder
    {
        if (func_num_args() === 2) {
            return static::query()->where($col, $op);
        }

        return static::query()->where($col, $op, $val);
    }

    public static function firstWhere(string $col, mixed $op, mixed $val = null): ?static
    {
        return static::where(...func_get_args())->first();
    }

    /** @param array<int, mixed> $values */
    public static function whereIn(string $col, array $values): \Marwa\DB\ORM\QueryBuilder
    {
        return static::query()->whereIn($col, $values);
    }

    public static function whereNull(string $col): \Marwa\DB\ORM\QueryBuilder
    {
        return static::query()->whereNull($col);
    }

    public static function whereNotNull(string $col): \Marwa\DB\ORM\QueryBuilder
    {
        return static::query()->whereNotNull($col);
    }

    public static function first(): ?static
    {
        return static::query()->first();
    }

    public static function firstOrFail(): \Marwa\DB\ORM\Model
    {
        return static::query()->firstOrFail();
    }

    public static function count(string $col = '*'): int
    {
        return static::query()->count($col);
    }

    public static function exists(): bool
    {
        return static::query()->exists();
    }

    /** @return array{data:array<int, static>, total:int, per_page:int, current_page:int, last_page:int} */
    public static function paginate(int $perPage = 15, int $page = 1): array
    {
        $qb = static::applySoftDeleteFilter(static::baseQuery());
        $result = $qb->paginate($perPage, $page);
        $result['data'] = array_map(fn($r) => static::hydrateRow($r), $result['data']);
        return $result;
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $instance = static::firstOrNew($attributes);
        $instance->fill($values);

        if ($instance->isDirty() || !$instance->exists) {
            $instance->save();
        }

        return $instance;
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $instance = static::firstOrNew($attributes);

        if (!$instance->exists) {
            $instance->forceFill($values);
            $instance->save();
        }

        return $instance;
    }

    protected static function firstOrNew(array $attributes): static
    {
        $qb = static::applySoftDeleteFilter(static::baseQuery());

        foreach ($attributes as $key => $value) {
            $qb->where($key, '=', $value);
        }

        $row = $qb->first();

        if ($row) {
            return static::hydrateRow($row);
        }

        return new static($attributes);
    }
}
