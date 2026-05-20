<?php

declare(strict_types=1);

namespace Marwa\DB\ORM;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder as BaseBuilder;
use Marwa\DB\ORM\Relations\Relation;

/**
 * Model-aware builder:
 *  - Fixed table (from the model)
 *  - Hydrates rows into Model instances
 *  - Eager loading with batched relations (HasMany/BelongsTo)
 */
final class QueryBuilder
{
    /** @var class-string<Model> */
    private string $modelClass;

    private BaseBuilder $qb;

    /** @var array<int,string> */
    private array $eager = [];

    public function __construct(
        private ConnectionManager $cm,
        /** @var class-string<Model> $modelClass */
        string $modelClass,
        private string $connection = 'default'
    ) {
        $this->modelClass = $modelClass;
        $this->qb = (new BaseBuilder($this->cm, $this->connection))
            ->table($modelClass::table());
    }

    /** ---------- Fluent proxies ---------- */
    public function select(string ...$cols): self
    {
        $this->qb->select(...$cols);
        return $this;
    }
    public function distinct(): self
    {
        $this->qb->distinct();
        return $this;
    }
    /** @param array<int, mixed> $bindings */
    public function selectRaw(string $expr, array $bindings = []): self
    {
        $this->qb->selectRaw($expr, $bindings);
        return $this;
    }
    public function where(string $col, mixed $op, mixed $val = null): self
    {
        if (func_num_args() === 2) {
            $this->qb->where($col, $op);
            return $this;
        }

        $this->qb->where($col, $op, $val);
        return $this;
    }
    public function orWhere(string $col, mixed $op, mixed $val = null): self
    {
        if (func_num_args() === 2) {
            $this->qb->orWhere($col, $op);
            return $this;
        }

        $this->qb->orWhere($col, $op, $val);
        return $this;
    }
    public function whereColumn(string $first, mixed $operator, ?string $second = null): self
    {
        if (func_num_args() === 2) {
            $this->qb->whereColumn($first, $operator);
            return $this;
        }

        $this->qb->whereColumn($first, $operator, $second);
        return $this;
    }
    public function orWhereColumn(string $first, mixed $operator, ?string $second = null): self
    {
        if (func_num_args() === 2) {
            $this->qb->orWhereColumn($first, $operator);
            return $this;
        }

        $this->qb->orWhereColumn($first, $operator, $second);
        return $this;
    }
    /** @param array<int, mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->qb->whereRaw($sql, $bindings);
        return $this;
    }
    /** @param array<int, mixed> $bindings */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        $this->qb->orWhereRaw($sql, $bindings);
        return $this;
    }
    /** @param array<int, mixed> $values */
    public function whereIn(string $col, array $values): self
    {
        $this->qb->whereIn($col, $values);
        return $this;
    }
    public function whereNull(string $col): self
    {
        $this->qb->whereNull($col);
        return $this;
    }
    public function whereNotNull(string $col): self
    {
        $this->qb->whereNotNull($col);
        return $this;
    }
    /** @param array<int, mixed> $values */
    public function whereBetween(string $col, array $values): self
    {
        $this->qb->whereBetween($col, $values);
        return $this;
    }
    /** @param array<int, mixed> $values */
    public function whereNotBetween(string $col, array $values): self
    {
        $this->qb->whereNotBetween($col, $values);
        return $this;
    }
    public function whereExists(callable|\Marwa\DB\Query\Builder $subquery): self
    {
        $this->qb->whereExists($subquery);
        return $this;
    }
    public function whereNotExists(callable|\Marwa\DB\Query\Builder $subquery): self
    {
        $this->qb->whereNotExists($subquery);
        return $this;
    }
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->qb->join($table, $first, $operator, $second);
        return $this;
    }
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->qb->leftJoin($table, $first, $operator, $second);
        return $this;
    }
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->qb->rightJoin($table, $first, $operator, $second);
        return $this;
    }
    public function groupBy(string ...$cols): self
    {
        $this->qb->groupBy(...$cols);
        return $this;
    }
    public function groupByRaw(string $expression): self
    {
        $this->qb->groupByRaw($expression);
        return $this;
    }
    public function having(string $col, string $op, mixed $val): self
    {
        $this->qb->having($col, $op, $val);
        return $this;
    }
    /** @param array<int, mixed> $bindings */
    public function havingRaw(string $sql, array $bindings = []): self
    {
        $this->qb->havingRaw($sql, $bindings);
        return $this;
    }
    public function orderBy(string $col, string $dir = 'asc'): self
    {
        $this->qb->orderBy($col, $dir);
        return $this;
    }
    public function limit(int $n): self
    {
        $this->qb->limit($n);
        return $this;
    }
    public function offset(int $n): self
    {
        $this->qb->offset($n);
        return $this;
    }
    /** @param callable(int, array<Model>): void $callback */
    public function chunk(int $size, callable $callback): void
    {
        $this->qb->chunk($size, function (int $offset, array $rows) use ($callback): void {
            $models = array_map(fn($row) => $this->hydrate($row), $rows);
            $callback($offset, $models);
        });
    }

    /** Aggregates (DB-side) */
    public function count(string $col = '*'): int
    {
        return $this->qb->count($col);
    }

    public function exists(): bool
    {
        return $this->qb->exists();
    }

    /** @param callable(int, array<Model>): void $callback */
    public function chunkById(int $size, callable $callback, string $idCol = 'id'): void
    {
        $this->qb->chunkById($size, function (int|string $lastId, array $rows) use ($callback): void {
            $models = array_map(fn($row) => $this->hydrate($row), $rows);
            $callback((int) $lastId, $models);
        }, $idCol);
    }

    public function max(string $col): mixed
    {
        return $this->qb->max($col);
    }
    public function min(string $col): mixed
    {
        return $this->qb->min($col);
    }
    public function sum(string $col): int|float|null
    {
        return $this->qb->sum($col);
    }
    public function avg(string $col): ?float
    {
        return $this->qb->avg($col);
    }

    /** Eager loading (supports dot notation via relation descriptors) */
    public function with(string ...$relations): self
    {
        foreach ($relations as $r) {
            if (!in_array($r, $this->eager, true)) $this->eager[] = $r;
        }
        return $this;
    }

    /** ---------- Reads (hydrate) ---------- */

    /** @return array<int, Model> */
    public function get(): array
    {
        $rows = $this->qb->get();
        $models = array_map(fn($row) => $this->hydrate($row), $rows);
        $this->performEagerLoad($models);
        return $models;
    }

    public function first(): ?Model
    {
        $row = $this->qb->first();
        if (!$row) return null;
        $model = $this->hydrate($row);
        $this->performEagerLoad([$model]);
        return $model;
    }

    public function firstOrFail(): Model
    {
        $row = $this->qb->first();
        if (!$row) {
            throw new \Marwa\DB\Exceptions\ORMException(
                "{$this->modelClass} record not found."
            );
        }
        $model = $this->hydrate($row);
        $this->performEagerLoad([$model]);
        return $model;
    }

    /** ---------- Writes (pass-through) ---------- */
    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        return $this->qb->insert($data);
    }
    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        return $this->qb->update($data);
    }
    public function delete(): int
    {
        return $this->qb->delete();
    }
    /** @param array<string, mixed> $data */
    public function insertGetId(array $data): int|string
    {
        return $this->qb->insertGetId($data);
    }
    public function increment(string $column, int|float $amount = 1): int
    {
        return $this->qb->increment($column, $amount);
    }
    public function decrement(string $column, int|float $amount = 1): int
    {
        return $this->qb->decrement($column, $amount);
    }

    public function getBaseBuilder(): BaseBuilder
    {
        return $this->qb;
    }

    /** ---------- Internals ---------- */

    /** @param array<string, mixed>|object $row */
    private function hydrate(array|object $row): Model
    {
        /** @var class-string<Model> $cls */
        $cls = $this->modelClass;
        $data = is_array($row) ? $row : (array)$row;
        return new $cls($data, true);
    }

    /** @param array<int, Model> $models */
    private function performEagerLoad(array $models): void
    {
        if (!$models || !$this->eager) return;

        /** @var class-string<Model> $cls */
        $cls = $this->modelClass;

        foreach ($this->eager as $name) {
            if (!method_exists($cls, $name)) continue;

            // Need an instance to call the relation method
            /** @var Model $tmp */
            $tmp = new $cls([], true);
            $descriptor = $tmp->{$name}();

            if ($descriptor instanceof Relation) {
                $descriptor->eagerLoad($models, $name);
                continue;
            }

            // Fallback: per-model lazy loading if relation method returns raw value
            foreach ($models as $m) {
                $m->setRelation($name, $m->{$name}());
            }
        }
    }
}
