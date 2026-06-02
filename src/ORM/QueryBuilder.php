<?php

declare(strict_types=1);

namespace Marwa\DB\ORM;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder as BaseBuilder;
use Marwa\DB\ORM\Relations\Relation;
use Marwa\DB\ORM\Model;

/**
 * Model-aware builder:
 *  - Fixed table (from the model)
 *  - Hydrates rows into Model instances
 *  - Eager loading with batched relations (HasMany/BelongsTo)
 *
 * @method self active()
 * @method self popular()
 */
final class QueryBuilder
{
    /** @var class-string<Model> */
    private string $modelClass;

    private BaseBuilder $qb;

    /** @var array<int,string> */
    private array $eager = [];

    /** @var array<int, array{relation:string, alias:string}> */
    private array $countRelations = [];

    private bool $includeTrashed = false;
    private bool $onlyTrashed = false;
    private bool $softDeleteApplied = false;

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
    public function where(callable|string $col, mixed $op = null, mixed $val = null): self
    {
        if (is_callable($col)) {
            $this->qb->whereNested($col);
            return $this;
        }
        if (func_num_args() === 2) {
            $this->qb->where($col, $op);
            return $this;
        }
        $this->qb->where($col, $op, $val);
        return $this;
    }
    public function orWhere(callable|string $col, mixed $op = null, mixed $val = null): self
    {
        if (is_callable($col)) {
            $this->qb->whereNested($col, 'or');
            return $this;
        }
        if (func_num_args() === 2) {
            $this->qb->orWhere($col, $op);
            return $this;
        }
        $this->qb->orWhere($col, $op, $val);
        return $this;
    }

    public function when(mixed $value, callable $callback, ?callable $default = null): self
    {
        $result = null;

        if ($value) {
            $result = $callback($this, $value);
        } elseif ($default !== null) {
            $result = $default($this, $value);
        }

        return $result instanceof self ? $result : $this;
    }

    public function unless(mixed $value, callable $callback, ?callable $default = null): self
    {
        return $this->when(!$value, $callback, $default);
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

    /** @param int|string|array<int, int|string> $id */
    public function whereKey(int|string|array $id): self
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        $model = new $modelClass([], true);
        $key = $model->getKeyName();

        if (is_array($id)) {
            return $this->whereIn($key, $id);
        }

        return $this->where($key, '=', $id);
    }

    /** @param array<int, mixed> $values */
    public function whereNotIn(string $col, array $values): self
    {
        $this->qb->whereNotIn($col, $values);
        return $this;
    }
    public function whereJsonContains(string $col, mixed $value): self
    {
        $this->qb->whereJsonContains($col, $value);
        return $this;
    }
    public function whereJsonLength(string $col, int $length): self
    {
        $this->qb->whereJsonLength($col, $length);
        return $this;
    }
    public function whereJsonValue(string $col, string $path, mixed $value): self
    {
        $this->qb->whereJsonValue($col, $path, $value);
        return $this;
    }
    public function whereNested(callable $callback, string $boolean = 'and'): self
    {
        $this->qb->whereNested($callback, $boolean);
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
    public function orHaving(string $col, string $op, mixed $val): self
    {
        $this->qb->orHaving($col, $op, $val);
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
    public function limit(int|null $n): self
    {
        $this->qb->limit($n);
        return $this;
    }
    public function offset(int|null $n): self
    {
        $this->qb->offset($n);
        return $this;
    }
    /** @param callable(int, array<Model>): void $callback */
    public function chunk(int $size, callable $callback): void
    {
        $this->applySoftDelete();
        $this->qb->chunk($size, function (int $offset, array $rows) use ($callback): void {
            $models = array_map(fn($row) => $this->hydrate($row), $rows);
            $this->performEagerLoad($models);
            $this->performCountLoad($models);
            $callback($offset, $models);
        });
    }

    /** Aggregates (DB-side) */
    public function count(string $col = '*'): int
    {
        $this->applySoftDelete();
        return $this->qb->count($col);
    }

    public function exists(): bool
    {
        $this->applySoftDelete();
        return $this->qb->exists();
    }

    /** @param callable(int, array<Model>): void $callback */
    public function chunkById(int $size, callable $callback, string $idCol = 'id'): void
    {
        $this->applySoftDelete();
        $this->qb->chunkById($size, function (int|string $lastId, array $rows) use ($callback): void {
            $models = array_map(fn($row) => $this->hydrate($row), $rows);
            $this->performEagerLoad($models);
            $this->performCountLoad($models);
            $callback((int) $lastId, $models);
        }, $idCol);
    }

    public function max(string $col): mixed
    {
        $this->applySoftDelete();
        return $this->qb->max($col);
    }
    public function min(string $col): mixed
    {
        $this->applySoftDelete();
        return $this->qb->min($col);
    }
    public function sum(string $col): int|float|null
    {
        $this->applySoftDelete();
        return $this->qb->sum($col);
    }
    public function avg(string $col): ?float
    {
        $this->applySoftDelete();
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

    public function withCount(string ...$relations): self
    {
        foreach ($relations as $relation) {
            [$name, $alias] = $this->parseCountRelation($relation);
            $this->countRelations[] = ['relation' => $name, 'alias' => $alias];
        }

        return $this;
    }

    /** ---------- Soft Deletes ---------- */

    public function withTrashed(): self
    {
        $this->includeTrashed = true;
        $this->onlyTrashed = false;
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;
        $this->includeTrashed = false;
        return $this;
    }

    public function value(string $column): mixed
    {
        $this->applySoftDelete();
        return $this->qb->value($column);
    }

    public function toSql(): string
    {
        return $this->qb->toSql();
    }

    public function getBindings(): array
    {
        return $this->qb->getBindings();
    }

    public function clear(): void
    {
        $this->qb->clear();
    }

    /** Switch connection for this query */
    public function on(string $connection): self
    {
        $this->connection = $connection;
        $this->qb = (new BaseBuilder($this->cm, $this->connection))->table($this->modelClass::table());
        return $this;
    }

    public function pluck(string $column): \Marwa\DB\Support\Collection
    {
        return $this->qb->pluck($column);
    }

    /**
     * @return array{data:array<int, Model>, total:int, per_page:int, current_page:int, last_page:int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $this->applySoftDelete();
        $qb = clone $this->qb;
        $qb->limit(null)->offset(null);
        $total = $qb->count();

        $rows = $this->qb->limit($perPage)->offset(($page - 1) * $perPage)->get();

        $models = array_map(fn($row) => $this->hydrate($row), $rows);
        $this->performEagerLoad($models);
        $this->performCountLoad($models);

        return (new \Marwa\DB\Query\Pagination())->make($models, $total, $perPage, $page);
    }

    /** ---------- Reads (hydrate) ---------- */

    /** @return array<int, Model> */
    public function get(): array
    {
        $this->applySoftDelete();
        $rows = $this->qb->get();
        $models = array_map(fn($row) => $this->hydrate($row), $rows);
        $this->performEagerLoad($models);
        $this->performCountLoad($models);
        return $models;
    }

    public function first(): ?Model
    {
        $this->applySoftDelete();
        $row = $this->qb->first();
        if (!$row) return null;
        $model = $this->hydrate($row);
        $this->performEagerLoad([$model]);
        $this->performCountLoad([$model]);
        return $model;
    }

    public function firstOrFail(): Model
    {
        $this->applySoftDelete();
        $row = $this->qb->first();
        if (!$row) {
            throw new \Marwa\DB\Exceptions\ORMException(
                "{$this->modelClass} record not found."
            );
        }
        $model = $this->hydrate($row);
        $this->performEagerLoad([$model]);
        $this->performCountLoad([$model]);
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
        return $cls::hydrateRow($data);
    }

    /** ---------- Scope forwarding ---------- */

    public function __call(string $method, array $parameters): mixed
    {
        $scope = 'scope' . ucfirst($method);
        if (method_exists($this->modelClass, $scope)) {
            $tmp = new $this->modelClass([], true);
            $tmp->{$scope}($this->qb, ...$parameters);

            return $this;
        }
        throw new \BadMethodCallException("Call to undefined method " . static::class . "::{$method}()");
    }

    private function applySoftDelete(): void
    {
        if ($this->softDeleteApplied) return;
        $this->softDeleteApplied = true;
        $sds = $this->modelClass::getSoftDeleteState();
        if (!$sds['enabled']) {
            return;
        }
        $incl = $this->includeTrashed || $sds['includeTrashed'];
        $only = $this->onlyTrashed || $sds['onlyTrashed'];
        if ($only) {
            $this->qb->whereNotNull('deleted_at');
        } elseif (!$incl) {
            $this->qb->whereNull('deleted_at');
        }
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

    /** @param array<int, Model> $models */
    private function performCountLoad(array $models): void
    {
        if (!$models || !$this->countRelations) {
            return;
        }

        /** @var class-string<Model> $cls */
        $cls = $this->modelClass;

        $groupedCounts = [];
        foreach ($this->countRelations as $spec) {
            $groupedCounts[$spec['relation']][] = $spec['alias'];
        }

        foreach ($groupedCounts as $relationName => $aliases) {
            if (!method_exists($cls, $relationName)) {
                continue;
            }

            /** @var Model $tmp */
            $tmp = new $cls([], true);
            $descriptor = $tmp->{$relationName}();

            if (!$descriptor instanceof Relation) {
                continue;
            }

            $descriptor->eagerCount($models, ...array_values(array_unique($aliases)));
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseCountRelation(string $relation): array
    {
        $parts = preg_split('/\\s+as\\s+/i', trim($relation), 2);
        $name = trim($parts[0] ?? $relation);
        $alias = trim($parts[1] ?? ($name . '_count'));

        return [$name, $alias];
    }
}
