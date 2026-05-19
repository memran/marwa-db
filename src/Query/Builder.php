<?php

declare(strict_types=1);

namespace Marwa\DB\Query;

use PDO;
use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Support\Collection;

class Builder
{
    private const ALLOWED_OPERATORS = [
        '=',
        '<',
        '>',
        '<=',
        '>=',
        '<>',
        '!=',
        'like',
        'not like',
    ];

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

    /** @var list<array{type:string,column?:string,operator?:string,value?:mixed,values?:array<int,mixed>,not?:bool,boolean:string,sql?:string,first?:string,second?:string,query?:self}> */
    protected array $wheres = [];

    /** @var list<array{type:string,table:string,first:string,operator:string,second:string}> */
    protected array $joins = [];

    /** @var array<int, string|Expression> */
    protected array $groups = [];

    /** @var list<array{type:string,column?:string,operator?:string,value?:mixed,values?:array<int,mixed>,not?:bool,boolean:string,sql?:string,first?:string,second?:string,query?:self}> */
    protected array $havings = [];

    /** @var array<int, array{column:string, direction:string}> */
    protected array $orders = [];

    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $distinct = false;

    /** @var array<string, array<int, mixed>> */
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

    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /** @param array<int, mixed> $bindings */
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

    public function where(string|callable $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if (is_callable($column)) {
            return $this->whereNested($column, $boolean);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $operator = $this->normalizeOperator((string) $operator);
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

    public function orWhere(string|callable $column, mixed $operator = null, mixed $value = null): self
    {
        if (is_callable($column)) {
            return $this->whereNested($column, 'or');
        }

        if (func_num_args() === 2) {
            $this->wheres[] = [
                'type' => 'Basic',
                'column' => $column,
                'operator' => '=',
                'value' => $operator,
                'boolean' => 'or',
            ];
            $this->addBinding($operator, 'where');
            return $this;
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /** @param array<int, mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => '(' . $sql . ')',
            'boolean' => $boolean,
        ];
        $this->addBinding($bindings, 'where');
        return $this;
    }

    /** @param array<int, mixed> $bindings */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    public function whereColumn(string $first, mixed $operator, ?string $second = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $second = (string) $operator;
            $operator = '=';
        }

        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';

        $this->wheres[] = [
            'type' => 'Column',
            'first' => $first,
            'operator' => $this->normalizeOperator((string) $operator),
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereColumn(string $first, mixed $operator, ?string $second = null): self
    {
        if (func_num_args() === 2) {
            return $this->whereColumn($first, $operator, null, 'or');
        }

        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /** @param array<int, mixed> $values */
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

    /** @param array<int, mixed> $values */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, true, $boolean);
    }

    /** @param array<int, mixed> $values */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween expects exactly two values.');
        }

        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $values = array_values($values);

        $this->wheres[] = [
            'type' => 'Between',
            'column' => $column,
            'values' => $values,
            'not' => $not,
            'boolean' => $boolean,
        ];
        $this->addBinding($values, 'where');
        return $this;
    }

    /** @param array<int, mixed> $values */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereBetween($column, $values, $boolean, true);
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

    public function whereJsonContains(string $column, mixed $value): self
    {
        $json = is_array($value) ? json_encode($value) : $value;
        $this->wheres[] = [
            'type'    => 'JsonContains',
            'column'  => $column,
            'value'  => $json,
            'boolean' => 'and',
        ];
        $this->addBinding($json, 'where');
        return $this;
    }

    public function whereJsonLength(string $column, int $length): self
    {
        $this->wheres[] = [
            'type'    => 'JsonLength',
            'column'  => $column,
            'value'  => $length,
            'boolean' => 'and',
        ];
        $this->addBinding($length, 'where');
        return $this;
    }

    public function whereJsonValue(string $column, string $path, mixed $value): self
    {
        $this->wheres[] = [
            'type'    => 'JsonValue',
            'column'  => $column,
            'path'   => $path,
            'value'  => $value,
            'boolean' => 'and',
        ];
        $this->addBinding($value, 'where');
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

    public function whereExists(callable|self $subquery, string $boolean = 'and', bool $not = false): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        [$sql, $bindings] = $this->compileSubquery($subquery);

        $this->wheres[] = [
            'type' => 'Exists',
            'sql' => $sql,
            'not' => $not,
            'boolean' => $boolean,
        ];
        $this->addBinding($bindings, 'where');

        return $this;
    }

    public function whereNotExists(callable|self $subquery, string $boolean = 'and'): self
    {
        return $this->whereExists($subquery, $boolean, true);
    }

    public function whereNested(callable $callback, string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $query = new self($this->cm, $this->connection);
        $query->from = $this->from;
        $callback($query);

        if ($query->wheres === []) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'Nested',
            'query' => $query,
            'boolean' => $boolean,
        ];
        $this->addBinding($query->bindings['where'], 'where');

        return $this;
    }

    /* ---------------------------
     * Join / Group / Having
     * ------------------------- */

    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $type = strtolower($type);
        if (!in_array($type, ['inner', 'left', 'right'], true)) {
            throw new \InvalidArgumentException("Unsupported join type [{$type}].");
        }

        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $this->normalizeOperator($operator),
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $column;
        }

        return $this;
    }

    public function groupByRaw(string $expression): self
    {
        $this->groups[] = new Expression($expression);

        return $this;
    }

    public function having(string $column, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $operator = $this->normalizeOperator($operator);

        $this->havings[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];
        $this->addBinding($value, 'having');

        return $this;
    }

    public function orHaving(string $column, string $operator, mixed $value): self
    {
        return $this->having($column, $operator, $value, 'or');
    }

    /** @param array<int, mixed> $bindings */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $this->havings[] = [
            'type' => 'Raw',
            'sql' => '(' . $sql . ')',
            'boolean' => $boolean,
        ];
        $this->addBinding($bindings, 'having');

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
     * -------------------------- */

    /** @return array<int, array<string, mixed>> */
    public function get(int $fetchMode = PDO::FETCH_ASSOC): array
    {
        [$sql, $bindings] = $this->compileSelect();
        $rows = $this->execute($sql, $bindings, $fetchMode);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|object|null */
    public function first(int $fetchMode = PDO::FETCH_ASSOC): array|object|null
    {
        $query = clone $this;
        $query->limit(1);
        $rows = $query->get($fetchMode);

        return $rows[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $query = clone $this;
        $row = $query->select($column)->first(PDO::FETCH_ASSOC);
        if ($row === null) {
            return null;
        }
        return is_array($row) ? ($row[$column] ?? null) : ($row->$column ?? null);
    }

    public function pluck(string $column): Collection
    {
        $query = clone $this;
        $rows = $query->select($column)->get(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = is_array($r) ? ($r[$column] ?? null) : ($r->$column ?? null);
        }
        return new Collection($out);
    }

    /** @param callable(int, array<int, array<string, mixed>>): void $callback */
    public function chunk(int $size, callable $callback, int $fetchMode = PDO::FETCH_ASSOC): void
    {
        $size = max(1, $size);
        $offset = $this->offset ?? 0;

        do {
            $query = clone $this;
            $rows = $query->limit($size)->offset($offset)->get($fetchMode);

            if ($rows === []) {
                break;
            }

            $callback($offset, $rows);
            $offset += $size;
        } while (count($rows) === $size);
    }

    /** @param callable(int|string, array<int, array<string, mixed>>): void $callback */
    public function chunkById(int $size, callable $callback, string $idCol = 'id', int $fetchMode = PDO::FETCH_ASSOC): void
    {
        $size = max(1, $size);
        $lastId = null;

        do {
            $query = clone $this;
            if ($lastId !== null) {
                $query->where($idCol, '>', $lastId);
            }

            $rows = $query->orderBy($idCol, 'asc')->limit($size)->get($fetchMode);

            if ($rows === []) {
                break;
            }

            $lastRow = $rows[array_key_last($rows)];
            $lastId = is_array($lastRow) ? ($lastRow[$idCol] ?? null) : ($lastRow->$idCol ?? null);

            if ($lastId === null) {
                break;
            }

            $callback($lastId, $rows);
        } while (count($rows) === $size);
    }

    /* ---------------------------
     * Mutations
     * ------------------------- */

    /** @param array<string, mixed> $data */
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

    /** @param array<string, mixed> $data */
    public function insertGetId(array $data): int|string
    {
        $this->insert($data);

        return $this->cm->getPdo($this->connection)->lastInsertId();
    }

    /** @param array<string, mixed> $data */
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

    public function increment(string $column, int|float $amount = 1): int
    {
        return $this->updateCounter($column, $amount, '+');
    }

    public function decrement(string $column, int|float $amount = 1): int
    {
        return $this->updateCounter($column, $amount, '-');
    }

    /* ---------------------------
     * Aggregates
     * ------------------------- */

    public function count(string $column = '*'): int
    {
        $query = $this->aggregateQuery('COUNT(' . ($column === '*' ? '*' : $this->wrap($column)) . ') AS aggregate');
        $rows = $query->get(PDO::FETCH_ASSOC);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return 0;
        }
        return (int) (is_array($row) ? ($row['aggregate'] ?? 0) : ($row->aggregate ?? 0));
    }

    public function exists(): bool
    {
        $query = clone $this;
        $query->columns = [new Expression('1')];
        $query->orders = [];
        $query->limit = 1;
        $query->offset = null;

        [$sql, $bindings] = $query->compileSelect();

        $pdo = $this->cm->getPdo($this->connection);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        return (bool) $stmt->fetch();
    }

    public function max(string $column): mixed
    {
        $query = $this->aggregateQuery('MAX(' . $this->wrap($column) . ') AS aggregate');
        $rows = $query->get(PDO::FETCH_ASSOC);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }
        return is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
    }

    public function min(string $column): mixed
    {
        $query = $this->aggregateQuery('MIN(' . $this->wrap($column) . ') AS aggregate');
        $rows = $query->get(PDO::FETCH_ASSOC);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }
        return is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
    }

    public function sum(string $column): int|float|null
    {
        $query = $this->aggregateQuery('SUM(' . $this->wrap($column) . ') AS aggregate');
        $rows = $query->get(PDO::FETCH_ASSOC);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }
        $val = is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
        return $val === null ? null : (is_numeric($val) ? +$val : null);
    }

    public function avg(string $column): ?float
    {
        $query = $this->aggregateQuery('AVG(' . $this->wrap($column) . ') AS aggregate');
        $rows = $query->get(PDO::FETCH_ASSOC);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }
        $val = is_array($row) ? ($row['aggregate'] ?? null) : ($row->aggregate ?? null);
        return $val === null ? null : (float)$val;
    }

    /**
     * @return array{data:array<int, array<string, mixed>>, total:int, per_page:int, current_page:int, last_page:int}
     */
    public function paginate(int $perPage = 15, int $page = 1, int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $countBuilder = clone $this;
        $countBuilder->limit = null;
        $countBuilder->offset = null;
        $total = $countBuilder->count();

        $dataBuilder = clone $this;
        $dataBuilder->limit($perPage)->offset(($page - 1) * $perPage);
        $rows = $dataBuilder->get($fetchMode);

        return (new Pagination())->make($rows, $total, $perPage, $page);
    }

    /* ---------------------------
     * SQL Generation Helpers
     * ------------------------- */

    /** @return array{0:string,1:array<int, mixed>} */
    protected function compileSelect(): array
    {
        $this->ensureFrom();

        $selects = $this->columnsToSql();

        $joinSql = $this->compileJoins();
        [$whereSql, $whereBindings] = $this->compileWhere();
        $groupSql = $this->compileGroups();
        [$havingSql, $havingBindings] = $this->compileHaving();
        $orderSql = $this->compileOrder();
        $limitSql = $this->compileLimit();

        $sql = sprintf(
            'SELECT %s%s FROM %s %s %s %s %s %s %s',
            $this->distinct ? 'DISTINCT ' : '',
            $selects,
            $this->wrapTable($this->from),
            $joinSql,
            $whereSql,
            $groupSql,
            $havingSql,
            $orderSql,
            $limitSql
        );

        $bindings = array_merge(
            $this->bindings['select'],
            $this->bindings['join'],
            $whereBindings,
            $havingBindings
        );

        return [trim(preg_replace('/\s+/', ' ', $sql) ?? $sql), $bindings];
    }

    /** @return array{0:string,1:array<int, mixed>} */
    protected function compileWhere(): array
    {
        return $this->compileConditions($this->wheres, 'WHERE', 'where');
    }

    protected function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $parts = array_map(function (array $join): string {
            $type = $join['type'] === 'inner' ? 'INNER JOIN' : strtoupper($join['type']) . ' JOIN';

            return sprintf(
                '%s %s ON %s %s %s',
                $type,
                $this->wrapTable($join['table']),
                $this->wrap($join['first']),
                $join['operator'],
                $this->wrap($join['second'])
            );
        }, $this->joins);

        return implode(' ', $parts);
    }

    protected function compileGroups(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        $parts = array_map(function (string|Expression $group): string {
            return $group instanceof Expression ? (string) $group : $this->wrap($group);
        }, $this->groups);

        return 'GROUP BY ' . implode(', ', $parts);
    }

    /** @return array{0:string,1:array<int, mixed>} */
    protected function compileHaving(): array
    {
        return $this->compileConditions($this->havings, 'HAVING', 'having');
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
                $out[] = $col === '*' ? '*' : $this->wrapColumn($col);
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
        $quote = $this->identifierQuote();

        $parts = explode('.', $identifier);
        $parts = array_map(function ($p) use ($quote) {
            return $p === '*' ? '*' : $this->quoteIdentifierPart($p, $quote);
        }, $parts);
        return implode('.', $parts);
    }

    protected function wrapColumn(string $identifier): string
    {
        if (preg_match('/^(.+)\s+as\s+(.+)$/i', $identifier, $matches) === 1) {
            return $this->wrap(trim($matches[1])) . ' AS ' . $this->wrap(trim($matches[2]));
        }

        return $this->wrap($identifier);
    }

    protected function wrapTable(string $table): string
    {
        if (preg_match('/^(.+)\s+as\s+(.+)$/i', $table, $matches) === 1) {
            return $this->wrap(trim($matches[1])) . ' AS ' . $this->wrap(trim($matches[2]));
        }

        return $this->wrap($table);
    }

    private function identifierQuote(): string
    {
        return match ($this->cm->getDriver($this->connection)) {
            'pgsql', 'sqlite' => '"',
            default => '`',
        };
    }

    private function quoteIdentifierPart(string $identifier, string $quote): string
    {
        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    /* ---------------------------
     * Bindings / SQL output
     * ------------------------- */

    /** @param array<int, mixed>|string|int|float|null $value */
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

    private function normalizeOperator(string $operator): string
    {
        $normalized = strtolower(trim($operator));
        if (!in_array($normalized, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("Unsupported operator [{$operator}].");
        }

        return $normalized;
    }

    private function aggregateQuery(string $expression): self
    {
        $query = clone $this;
        $query->columns = [new Expression($expression)];
        $query->distinct = false;
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;

        return $query;
    }

    /** Return a merged binding list in execution order for the current SQL. */
    /** @return array<int, mixed> */
    public function getBindings(): array
    {
        // For SELECTs we merge select + where; for updates/inserts we pass explicit bindings in execute
        return array_merge(
            $this->bindings['select'],
            $this->bindings['join'],
            $this->bindings['where'],
            $this->bindings['having']
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
        $this->joins = [];
        $this->groups = [];
        $this->havings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->distinct = false;
        foreach ($this->bindings as $k => $_) {
            $this->bindings[$k] = [];
        }
    }

    /* ---------------------------
     * Execution Core
     * ------------------------- */

    /**
     * Execute a SELECT statement.
     * @param array<int, mixed> $bindings
     * @return array<int, array<string, mixed>>|false
     */
    protected function execute(string $sql, array $bindings, int $fetchMode = PDO::FETCH_ASSOC): array|false
    {
        $pdo = $this->cm->getPdo($this->connection);
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($bindings);

        if (!$ok) {
            return false;
        }
        return $stmt->fetchAll($fetchMode);
    }

    /**
     * Execute INSERT/UPDATE/DELETE.
     * @param array<int, mixed> $bindings
     * @return int affected rows
     */
    protected function executeAffecting(string $sql, array $bindings): int
    {
        $pdo = $this->cm->getPdo($this->connection);
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($bindings);

        if (!$ok) {
            return 0;
        }
        return $stmt->rowCount();
    }

    /**
     * @param list<array{type:string,column?:string,operator?:string,value?:mixed,values?:array<int,mixed>,not?:bool,boolean:string,sql?:string,first?:string,second?:string,query?:self}> $clauses
     * @return array{0:string,1:array<int, mixed>}
     */
    protected function compileConditions(array $clauses, string $keyword, string $bindingType): array
    {
        if (empty($clauses)) {
            return ['', []];
        }

        $parts = [];

        foreach ($clauses as $i => $clause) {
            $bool = $i === 0 ? '' : ' ' . strtoupper($clause['boolean']) . ' ';

            switch ($clause['type']) {
                case 'Basic':
                    $parts[] = $bool . sprintf('(%s %s ?)', $this->wrap($clause['column']), $clause['operator']);
                    break;
                case 'Column':
                    $parts[] = $bool . sprintf('(%s %s %s)', $this->wrap($clause['first']), $clause['operator'], $this->wrap($clause['second']));
                    break;
                case 'In':
                    $count = count($clause['values']);
                    $ph = implode(', ', array_fill(0, $count, '?'));
                    $not = $clause['not'] ? ' NOT' : '';
                    $parts[] = $bool . sprintf('(%s%s IN (%s))', $this->wrap($clause['column']), $not, $ph);
                    break;
                case 'Between':
                    $not = $clause['not'] ? ' NOT' : '';
                    $parts[] = $bool . sprintf('(%s%s BETWEEN ? AND ?)', $this->wrap($clause['column']), $not);
                    break;
                case 'Null':
                    $parts[] = $bool . sprintf('(%s IS %sNULL)', $this->wrap($clause['column']), $clause['not'] ? 'NOT ' : '');
                    break;
                case 'JsonContains':
                    $parts[] = $bool . sprintf('(JSON_CONTAINS(%s, ?) = 1)', $this->wrap($clause['column']));
                    break;
                case 'JsonLength':
                    $parts[] = $bool . sprintf('(JSON_LENGTH(%s) = ?)', $this->wrap($clause['column']));
                    break;
                case 'JsonValue':
                    $path = is_string($clause['path'] ?? null) ? $clause['path'] : '$';
                    $parts[] = $bool . sprintf("(JSON_EXTRACT(%s, '%s') = ?)", $this->wrap($clause['column']), $path);
                    break;
                case 'Raw':
                    $parts[] = $bool . $clause['sql'];
                    break;
                case 'Exists':
                    $parts[] = $bool . '(' . ($clause['not'] ? 'NOT EXISTS' : 'EXISTS') . ' (' . $clause['sql'] . '))';
                    break;
                case 'Nested':
                    [$nestedSql] = $clause['query']->compileConditions($clause['query']->wheres, 'WHERE', 'where');
                    $nestedSql = preg_replace('/^WHERE\s+/i', '', $nestedSql) ?? $nestedSql;
                    $parts[] = $bool . '(' . $nestedSql . ')';
                    break;
            }
        }

        return [$keyword . ' ' . implode('', $parts), $this->bindings[$bindingType]];
    }

    /** @return array{0:string,1:array<int, mixed>} */
    protected function compileSubquery(callable|self $subquery): array
    {
        $query = $subquery instanceof self ? clone $subquery : new self($this->cm, $this->connection);

        if (is_callable($subquery)) {
            $subquery($query);
        }

        return $query->compileSelect();
    }

    protected function updateCounter(string $column, int|float $amount, string $operator): int
    {
        $this->ensureFrom();

        [$whereSql, $whereBindings] = $this->compileWhere();
        $sql = sprintf(
            'UPDATE %s SET %s = %s %s ? %s',
            $this->wrap($this->from),
            $this->wrap($column),
            $this->wrap($column),
            $operator,
            $whereSql
        );

        return (int) $this->executeAffecting($sql, array_merge([$amount], $whereBindings));
    }
}
