<?php

declare(strict_types=1);

namespace Marwa\DB\Logger;

use Psr\Log\LoggerInterface;

final class QueryLogger
{
    /** @var array<int, array{sql:string,bindings:array,time:float,conn:string,error:?string}> */
    private array $entries = [];

    public function __construct(private ?LoggerInterface $logger = null) {}

    public function log(string $sql, array $bindings, float $timeMs, string $connection, ?string $error = null): void
    {
        $this->entries[] = [
            'sql'       => $sql,
            'bindings'  => $bindings,
            'time'      => $timeMs,
            'conn'      => $connection,
            'error'     => $error,
        ];

        // Optional: also forward to PSR-3
        $context = ['bindings' => $bindings, 'time_ms' => $timeMs, 'connection' => $connection];
        $error
            ? $this->logger?->error("[DB] {$sql}", $context + ['error' => $error])
            : $this->logger?->info("[DB] {$sql}",  $context);
    }

    /** @return array<int, array{sql:string,bindings:array,time:float,conn:string,error:?string}> */
    public function all(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
