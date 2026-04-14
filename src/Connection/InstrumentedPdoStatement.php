<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

use Closure;
use PDOStatement;
use Throwable;

final class InstrumentedPdoStatement extends PDOStatement
{
    /** @var Closure(string, array, float, string, ?string): void */
    private Closure $recorder;

    private string $loggedQuery = '';

    protected function __construct(callable $recorder, private string $connectionName = 'default')
    {
        $this->recorder = $recorder instanceof Closure
            ? $recorder
            : Closure::fromCallable($recorder);
    }

    public function setQueryString(string $queryString): void
    {
        $this->loggedQuery = $queryString;
    }

    public function execute(?array $params = null): bool
    {
        $bindings = $params ?? [];
        $start = microtime(true);

        try {
            $result = parent::execute($params);
            $this->record($bindings, $start, $result === false ? $this->errorMessage() : null);

            return $result;
        } catch (Throwable $e) {
            $this->record($bindings, $start, $e->getMessage());
            throw $e;
        }
    }

    private function record(array $bindings, float $start, ?string $error = null): void
    {
        ($this->recorder)(
            $this->loggedQuery,
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
