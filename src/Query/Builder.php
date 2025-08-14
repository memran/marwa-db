<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

use PDO;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Support\Collection;

class Builder
{
    public function __construct(
        protected ConnectionManager $cm,
        protected string $connection = 'default',
    ) {}

    /* ---------------------------
     * Internal State
     * ------------------------- */
    protected ?string $from = null;

    /** @var array<int, string|Expression> */
    protected array $columns = ['*'];

    /** @var array<int, array> */
    protected array $wheres = [];

    /** @var array<int, array{column:string, direction:string}> */
    protected array $orders = [];

    protected ?int $limit = null;
    protected ?int $offset = null;

    /** Structured binding buckets to control order */
    protected array $bindings = [
        'select' => [],
        'from'   => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
        'union'  => [],
    ];

    /* ---------------------------
     * Table / Select
     * ------------------------- */
    public function table(string $table): self
    {
        return $this->from($table);
    }

    public function from(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    public function select(string ...$columns): self
    {
        if (!empty($columns)) {
            $this->columns = $columns;
        }
        return $this;
    }

    public function selectRaw(string $expression, array $bindings = []): self
    {
        // allow mixing raw expressions with normal columns
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = new Expression($expression);
        $this->addBinding($bindings, 'select');
        return $this;
    }

    /* ---------------------------
     * Where Clauses
     * ------------------------- */

    public function where(string $column, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $this->wheres[] = [
            'type'    => 'Basic',
            'column'  => $column,
            'operator' => $operator,
            'value'   => $value,
            'boolean' => $boolean,
        ];
        $this->addBinding($value, 'where');
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, bool $not = false, string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';

        if (count($values) === 0) {
            // Avoid invalid SQL: IN ()
            $this->wheres[] = [
                'type'    => 'Raw',
                'sql'     => $not ? '(1 = 1)' : '(1 = 0)', // always true/false
                'boolean' => $boolean,
            ];
            return $this;
        }

        $this->wheres[] = [
            'type'    => 'In',
            'column'  => $column,
            'values'  => array_values($values),
            'not'     => $not,
            'boolean' => $boolean,
        ];
        $this->addBinding($values, 'where');
        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, true, $boolean);
    }

    public function whereNull(string $column, string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $this->wheres[] = [
            'type'    => 'Null',
            'column'  => $column,
            'not'     => false,
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $this->wheres[] = [
            'type'    => 'Null',
            'column'  => $column,
            'not'     => true,
            'boolean' => $boolean,
        ];
        return $this;
    }

    /* ---------------------------
     * Order / Limit / Offset
     * ------------------------- */

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->orders[] = ['column' => $column, 'direction' => $dir];
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = max(0, $n);
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offset = max(0, $n);
        return $this;
    }

    /* ---------------------------
     * Fetch
     * ------------------------- */

    public function get(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        [$sql, $bindings] = $this->compileSelect();
        $rows = $this->execute($sql, $bindings, $fetchMode);

        return is_array($rows) ? $rows : [];
    }

    public function first(int $fetchMode = PDO::FETCH_ASSOC): array|object|null
    {
        $prev = $this->limit;
        $this->limit(1);
        $rows = $this->get($fetchMode);
        $this->limit = $prev;

        return $rows[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first(PDO::FETCH_ASSOC);
        if ($row === null) {
            return null;
        }
        return is_array($row) ? ($row[$column] ?? null) : ($row->$column ?? null);
    }

    public function pluck(string $column): Collection
    {
        $rows = $this->select($column)->get(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = is_array($r) ? ($r[$column] ?? null) : ($r->$column ?? null);
        }
        return new Collection($out);
    }

    /* ---------------------------
     * Mutations
     * ------------------------- */

    /** @return int affected rows */
    public function insert(array $data): int
    {
        $this->ensureFrom();

        // Single-row insert only (simple and portable)
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->wrap($this->from),
            implode(', ', array_map(fn($c) => $this->wrap($c), $columns)),
            $placeholders
        );

        $bindings = array_values($data);

        return (int) $this->executeAffecting($sql, $bindings);
    }

    /** @return int affected rows */
    public function update(array $data): int
    {
        $this->ensureFrom();

        [$whereSql, $whereBindings] = $this->compileWhere();

        $sets = [];
        $setBindings = [];
        foreach ($data as $col => $val) {
            $sets[] = $this->wrap($col) . ' = ?';
            $setBindings[] = $val;
        }

        $sql = sprintf(
            'UPDATE %s SET %s %s',
            $this->wrap($this->from),
            implode(', ', $sets),
            $whereSql
        );

        $bindings = array_merge($setBindings, $whereBindings);

        return (int) $this->executeAffecting($sql, $bindings);
    }

    /** @return int affected rows */
    public function delete(): int
    {
        $this->ensureFrom();

        [$whereSql, $whereBindings] = $this->compileWhere();

        $sql = sprintf(
            'DELETE FROM %s %s',
            $this->wrap($this->from),
            $whereSql
        );

        return (int) $this->executeAffecting($sql, $whereBindings);
    }

    /* ---------------------------
     * Aggregates
     * ------------------------- */

    public function count(string $column = '*'): int
    {
        $this->columns = [new Expression('COUNT(' . ($column === '*' ? '*' : $this->wrap($column)) . ') AS aggregate')];
        $row = $this->first(PDO::FETCH_ASSOC);
        return (int) (is_array($row) ? ($row['aggregate'] ?? 0) : ($row->aggregate ?? 0));
    }

    public function max(string $column): mixed
    {
        $this->columns = [new Expression('MAX(' . $this->wrap($column) . ') AS aggregate')];
        $row = $this->first(PDO::FETCH_ASSOC);
        return is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
    }

    public function min(string $column): mixed
    {
        $this->columns = [new Expression('MIN(' . $this->wrap($column) . ') AS aggregate')];
        $row = $this->first(PDO::FETCH_ASSOC);
        return is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
    }

    public function sum(string $column): int|float|null
    {
        $this->columns = [new Expression('SUM(' . $this->wrap($column) . ') AS aggregate')];
        $row = $this->first(PDO::FETCH_ASSOC);
        $val = is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
        return $val === null ? null : (is_numeric($val) ? +$val : null);
    }

    public function avg(string $column): ?float
    {
        $this->columns = [new Expression('AVG(' . $this->wrap($column) . ') AS aggregate')];
        $row = $this->first(PDO::FETCH_ASSOC);
        $val = is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
        return $val === null ? null : (float)$val;
    }

    /* ---------------------------
     * SQL Generation Helpers
     * ------------------------- */

    /** @return array{0:string,1:array} */
    protected function compileSelect(): array
    {
        $this->ensureFrom();

        $selects = $this->columnsToSql();

        [$whereSql, $whereBindings] = $this->compileWhere();
        $orderSql = $this->compileOrder();
        $limitSql = $this->compileLimit();

        $sql = sprintf(
            'SELECT %s FROM %s %s %s %s',
            $selects,
            $this->wrap($this->from),
            $whereSql,
            $orderSql,
            $limitSql
        );

        $bindings = array_merge(
            $this->bindings['select'],
            $whereBindings
        );

        return [trim(preg_replace('/\s+/', ' ', $sql) ?? $sql), $bindings];
    }

    /** @return array{0:string,1:array} */
    protected function compileWhere(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }
        $parts = [];
        foreach ($this->wheres as $i => $w) {
            $bool = $i === 0 ? '' : ' ' . strtoupper($w['boolean']) . ' ';
            switch ($w['type']) {
                case 'Basic':
                    $parts[] = $bool . sprintf('(%s %s ?)', $this->wrap($w['column']), $w['operator']);
                    break;
                case 'In':
                    $count = count($w['values']);
                    $ph = implode(', ', array_fill(0, $count, '?'));
                    $not = $w['not'] ? ' NOT' : '';
                    $parts[] = $bool . sprintf('(%s%s IN (%s))', $this->wrap($w['column']), $not, $ph);
                    break;
                case 'Null':
                    $parts[] = $bool . sprintf('(%s IS %sNULL)', $this->wrap($w['column']), $w['not'] ? 'NOT ' : '');
                    break;
                case 'Raw':
                    $parts[] = $bool . $w['sql'];
                    break;
            }
        }
        return ['WHERE ' . implode('', $parts), $this->bindings['where']];
    }

    protected function compileOrder(): string
    {
        if (empty($this->orders)) {
            return '';
        }
        $chunks = array_map(
            fn($o) => $this->wrap($o['column']) . ' ' . strtoupper($o['direction']),
            $this->orders
        );
        return 'ORDER BY ' . implode(', ', $chunks);
    }

    protected function compileLimit(): string
    {
        $sql = '';
        if ($this->limit !== null) {
            $sql .= 'LIMIT ' . (int)$this->limit . ' ';
        }
        if ($this->offset !== null) {
            $sql .= 'OFFSET ' . (int)$this->offset . ' ';
        }
        return trim($sql);
    }

    protected function columnsToSql(): string
    {
        if (empty($this->columns)) {
            return '*';
        }
        $out = [];
        foreach ($this->columns as $col) {
            if ($col instanceof Expression) {
                $out[] = (string)$col;
            } else {
                $out[] = $col === '*' ? '*' : $this->wrap($col);
            }
        }
        return implode(', ', $out);
    }

    protected function ensureFrom(): void
    {
        if (!$this->from) {
            throw new \RuntimeException('No table specified. Call table() or from() first.');
        }
    }

    protected function wrap(string $identifier): string
    {
        // support table.column
        $parts = explode('.', $identifier);
        $parts = array_map(function ($p) {
            return $p === '*' ? '*' : '`' . str_replace('`', '``', $p) . '`';
        }, $parts);
        return implode('.', $parts);
    }

    /* ---------------------------
     * Bindings / SQL output
     * ------------------------- */

    protected function addBinding(array|string|int|float|null $value, string $type = 'where'): void
    {
        if (!isset($this->bindings[$type])) {
            $this->bindings[$type] = [];
        }
        if ($value === null) {
            $this->bindings[$type][] = null;
            return;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                $this->bindings[$type][] = $v;
            }
            return;
        }
        $this->bindings[$type][] = $value;
    }

    /** Return a merged binding list in execution order for the current SQL. */
    public function getBindings(): array
    {
        // For SELECTs we merge select + where; for updates/inserts we pass explicit bindings in execute
        return array_merge(
            $this->bindings['select'],
            $this->bindings['where']
        );
    }

    public function toSql(): string
    {
        [$sql] = $this->compileSelect();
        return $sql;
    }

    public function clear(): void
    {
        $this->from = null;
        $this->columns = ['*'];
        $this->wheres = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        foreach ($this->bindings as $k => $_) {
            $this->bindings[$k] = [];
        }
    }

    /* ---------------------------
     * Execution Core
     * ------------------------- */

    /**
     * Execute a SELECT statement.
     * @return array<int, array>|false
     */
    protected function execute(string $sql, array $bindings, int $fetchMode = PDO::FETCH_ASSOC): array|false
    {
        $pdo = $this->cm->getPdo($this->connection);
        $stmt = $pdo->prepare($sql);

        $start = microtime(true);
        $ok = $stmt->execute($bindings);
        $ms = (microtime(true) - $start) * 1000;

        // Log to DebugPanel if present
        $this->cm->getDebugPanel()?->addQuery($sql, $bindings, $ms);

        if (!$ok) {
            return false;
        }
        return $stmt->fetchAll($fetchMode);
    }

    /**
     * Execute INSERT/UPDATE/DELETE.
     * @return int affected rows
     */
    protected function executeAffecting(string $sql, array $bindings): int
    {
        $pdo = $this->cm->getPdo($this->connection);
        $stmt = $pdo->prepare($sql);

        $start = microtime(true);
        $ok = $stmt->execute($bindings);
        $ms = (microtime(true) - $start) * 1000;

        $this->cm->getDebugPanel()?->addQuery($sql, $bindings, $ms);

        if (!$ok) {
            return 0;
        }
        return $stmt->rowCount();
    }
}
