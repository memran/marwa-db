<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

use Closure;
use PDO;
use PDOStatement;
use Throwable;

final class InstrumentedPdo extends PDO
{
    /** @var Closure(string, array, float, string, ?string): void */
    private Closure $recorder;

    public function __construct(
        string $dsn,
        string $username = '',
        string $password = '',
        array $options = [],
        ?callable $recorder = null,
        private string $connectionName = 'default'
    ) {
        parent::__construct($dsn, $username, $password, $options);

        $this->recorder = $recorder instanceof Closure
            ? $recorder
            : Closure::fromCallable($recorder ?? static fn (string $sql, array $bindings, float $timeMs, string $connection, ?string $error): null => null);

        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [
            InstrumentedPdoStatement::class,
            [$this->recorder, $this->connectionName],
        ]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $start = microtime(true);

        try {
            $statement = $fetchMode === null
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);

            $this->record($query, [], $start, $statement === false ? $this->errorMessage() : null);

            return $statement;
        } catch (Throwable $e) {
            $this->record($query, [], $start, $e->getMessage());
            throw $e;
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $start = microtime(true);

        try {
            if (!array_key_exists(PDO::ATTR_STATEMENT_CLASS, $options)) {
                $options[PDO::ATTR_STATEMENT_CLASS] = [
                    InstrumentedPdoStatement::class,
                    [$this->recorder, $this->connectionName],
                ];
            }

            $statement = parent::prepare($query, $options);

            if ($statement instanceof InstrumentedPdoStatement) {
                $statement->setQueryString($query);
            }

            if ($statement === false) {
                $this->record($query, [], $start, $this->errorMessage());
            }

            return $statement;
        } catch (Throwable $e) {
            $this->record($query, [], $start, $e->getMessage());
            throw $e;
        }
    }

    public function exec(string $statement): int|false
    {
        $start = microtime(true);

        try {
            $result = parent::exec($statement);
            $this->record($statement, [], $start, $result === false ? $this->errorMessage() : null);

            return $result;
        } catch (Throwable $e) {
            $this->record($statement, [], $start, $e->getMessage());
            throw $e;
        }
    }

    private function record(string $sql, array $bindings, float $start, ?string $error = null): void
    {
        ($this->recorder)(
            $sql,
            $bindings,
            (microtime(true) - $start) * 1000,
            $this->connectionName,
            $error
        );
    }

    private function errorMessage(): ?string
    {
        $info = $this->errorInfo();

        return is_array($info) ? ($info[2] ?? null) : null;
    }
}
