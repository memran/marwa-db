<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Exceptions\QueryException;
use Marwa\DB\Logger\QueryLogger;

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

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(string ...$cols): self
    {
        if ($cols) $this->selects = $cols;
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

    public function count(): int
    {
        $prevSel = $this->selects;
        $this->selects = ['COUNT(*) as aggregate'];
        $row = $this->first();
        $this->selects = $prevSel;
        return (int)($row['aggregate'] ?? 0);
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
        $select = implode(', ', array_map(fn($c) => $c === '*' ? '*' : $this->grammar->wrap($c), $this->selects));
        $sql = "SELECT {$select} FROM {$this->grammar->wrap($this->table)}";
        $sql .= $this->compileWhere();
        if ($this->orders) {
            $order = implode(', ', array_map(fn($o) => $this->grammar->wrap($o[0]) . ' ' . $o[1], $this->orders));
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
}
