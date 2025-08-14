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
        /** @var Model $m */
        $m = new $modelClass();
        $this->qb = (new BaseBuilder($this->cm, $this->connection))
            ->table($modelClass::$table);
    }

    /** ---------- Fluent proxies ---------- */
    public function select(string ...$cols): self
    {
        $this->qb->select(...$cols);
        return $this;
    }
    public function selectRaw(string $expr, array $bindings = []): self
    {
        $this->qb->selectRaw($expr, $bindings);
        return $this;
    }
    public function where(string $col, string $op, mixed $val): self
    {
        $this->qb->where($col, $op, $val);
        return $this;
    }
    public function whereIn(string $col, array $values): self
    {
        $this->qb->whereIn($col, $values);
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

    /** Aggregates (DB-side) */
    public function count(string $col = '*'): int
    {
        return $this->qb->count($col);
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

    /** ---------- Writes (pass-through) ---------- */
    public function insert(array $data): int
    {
        return $this->qb->insert($data);
    }
    public function update(array $data): int
    {
        return $this->qb->update($data);
    }
    public function delete(): int
    {
        return $this->qb->delete();
    }

    public function getBaseBuilder(): BaseBuilder
    {
        return $this->qb;
    }

    /** ---------- Internals ---------- */

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
