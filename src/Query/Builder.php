<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Exceptions\QueryException;
use Marwa\DB\Logger\QueryLogger;
use Marwa\DB\Support\Collection;
use Marwa\DB\Query\Expression;
use Marwa\DB\Query\Grammar;
use Marwa\DB\Support\DebugPanel;

final class Builder
{
    private string $table = '';
    private array $selects = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(
        private ConnectionManager $manager,
        private string $connection = 'default',
        private Grammar $grammar = new Grammar(),
        private ?QueryLogger $logger = null
    ) {}

    private function getDebugPanel(): ?DebugPanel
    {
        return $this->cm->getDebugPanel();
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    /**
     * @param string|Expression $column
     */
    private function addSelect(string|Expression $column): void
    {
        // Initialize selects if it’s still the default ['*']
        if ($this->selects === ['*']) {
            $this->selects = [];
        }
        $this->selects[] = $column;
    }

    public function select(string ...$cols): self
    {
        if (!empty($cols)) {
            foreach ($cols as $c) {
                $this->addSelect($c);
            }
        }
        return $this;
    }

    /**
     * Add a "where in" clause.
     */
    public function whereIn(string $column, array $values, bool $not = false): self
    {
        $operator = $not ? 'NOT IN' : 'IN';
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [$column . " {$operator} ({$placeholders})", $values];
        return $this;
    }

    /**
     * Add a "where not in" clause.
     */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, true);
    }


    /**
     * Add a raw select expression to the query.
     *
     * Example: ->selectRaw('MAX(batch) as aggregate')
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->addSelect(new Expression($expression));

        // Merge optional bindings (kept in the same order they are added)
        if ($bindings) {
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function where(string $col, string $op, mixed $val): self
    {
        $this->wheres[] = [$col, $op];
        $this->bindings[] = $val;
        return $this;
    }

    public function orderBy(string $col, string $dir = 'asc'): self
    {
        $this->orders[] = [$col, strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }
    public function offset(int $n): self
    {
        $this->offset = $n;
        return $this;
    }

    public function get(): array
    {
        [$sql, $bind] = $this->compileSelect();
        return $this->run($sql, $bind);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }


    public function insert(array $data): int
    {
        $cols = array_keys($data);
        $placeholders = $this->grammar->parameterize($data);
        $colSql = implode(', ', array_map([$this->grammar, 'wrap'], $cols));
        $sql = "INSERT INTO {$this->grammar->wrap($this->table)} ({$colSql}) VALUES ({$placeholders})";
        $this->bindings = array_values($data);
        $this->execute($sql, $this->bindings);
        return (int)$this->manager->getPdo($this->connection)->lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = [];
        foreach ($data as $col => $_) {
            $sets[] = $this->grammar->wrap($col) . ' = ?';
        }
        $sql = "UPDATE {$this->grammar->wrap($this->table)} SET " . implode(', ', $sets) . $this->compileWhere();
        $bind = array_values($data);
        $bind = array_merge($bind, $this->bindings);
        return $this->execute($sql, $bind);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->grammar->wrap($this->table)}" . $this->compileWhere();
        return $this->execute($sql, $this->bindings);
    }

    private function compileSelect(): array
    {
        // If user never changed selects, keep '*'
        $selectList = $this->selects ?: ['*'];

        $select = implode(', ', array_map(function ($c) {
            if ($c === '*') {
                return '*';
            }
            // Raw expression passes through
            if ($c instanceof Expression) {
                return (string)$c;
            }
            // Normal identifier gets quoted
            return $this->grammar->wrap($c);
        }, $selectList));

        $sql = "SELECT {$select} FROM {$this->grammar->wrap($this->table)}";
        $sql .= $this->compileWhere();

        if ($this->orders) {
            $order = implode(', ', array_map(
                fn($o) => $this->grammar->wrap($o[0]) . ' ' . $o[1],
                $this->orders
            ));
            $sql .= " ORDER BY {$order}";
        }
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return [$sql, $this->bindings];
    }
    public function whereNull(string $column): self
    {
        $this->wheres[] = [new \Marwa\DB\Query\Expression($this->grammar->wrap($column) . ' IS NULL'), null];
        return $this;
    }
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [new \Marwa\DB\Query\Expression($this->grammar->wrap($column) . ' IS NOT NULL'), null];
        return $this;
    }

    private function compileWhere(): string
    {
        if (!$this->wheres) return '';
        $parts = array_map(fn($w) => $this->grammar->wrap($w[0]) . " {$w[1]} ?", $this->wheres);
        return ' WHERE ' . implode(' AND ', $parts);
    }

    /** @return array<int,array<string,mixed>> */
    private function run(string $sql, array $bindings): array
    {
        $start = microtime(true);
        $pdo = $this->manager->getPdo($this->connection);
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            throw new QueryException($e->getMessage(), (int)$e->getCode(), $e);
        } finally {
            $time = microtime(true) - $start;
            if ($this->manager->isDebug($this->connection)) {
                $this->logger?->record($this->connection, $sql, $bindings, $time);
            }
        }
        return $rows;
    }

    private function execute(string $sql, array $bindings): int
    {
        $start = microtime(true);
        $pdo = $this->manager->getPdo($this->connection);
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            throw new QueryException($e->getMessage(), (int)$e->getCode(), $e);
        } finally {
            $time = microtime(true) - $start;
            if ($this->manager->isDebug($this->connection)) {
                $this->logger?->record($this->connection, $sql, $bindings, $time);
            }
        }
    }
    /**
     * Extract a single column from the result set and return a Collection of scalars.
     *
     * Example:
     *   $ids = DB::table('migrations')->pluck('migration')->toArray();
     *
     * @return Collection
     */
    public function pluck(string $column): Collection
    {
        // Only select the requested column to keep it efficient
        $results = (clone $this)->select($column)->get();

        $values = [];
        foreach ($results as $row) {
            if (is_array($row) && array_key_exists($column, $row)) {
                $values[] = $row[$column];
            } elseif (is_object($row) && isset($row->{$column})) {
                $values[] = $row->{$column};
            }
        }

        return new Collection($values);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate("MAX({$column})");
    }
    public function min(string $column): mixed
    {
        return $this->aggregate("MIN({$column})");
    }
    public function sum(string $column): int|float|null
    {
        return $this->aggregate("SUM({$column})");
    }
    public function avg(string $column): ?float
    {
        $v = $this->aggregate("AVG({$column})");
        return $v !== null ? (float)$v : null;
    }
    public function count(string $column = '*'): int
    {
        $v = $this->aggregate("COUNT({$column})");
        return $v !== null ? (int)$v : 0;
    }

    protected function aggregate(string $expression): mixed
    {
        $result = (clone $this)->selectRaw("{$expression} as aggregate")->first();
        if (is_object($result) && isset($result->aggregate)) return $result->aggregate;
        if (is_array($result)  && isset($result['aggregate'])) return $result['aggregate'];
        return null;
    }
}
