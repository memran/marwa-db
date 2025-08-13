<?php

declare(strict_types=1);

namespace Marwa\DB\Logger;

use Psr\Log\LoggerInterface;

final class QueryLogger
{
    /** @var array<int,array{sql:string,bindings:array,time:float,conn:string}> */
    private array $log = [];

    public function __construct(private ?LoggerInterface $logger = null) {}

    public function record(string $conn, string $sql, array $bindings, float $time): void
    {
        $this->log[] = compact('sql', 'bindings', 'time', 'conn');
        $this->logger?->info('SQL', ['conn' => $conn, 'sql' => $sql, 'bindings' => $bindings, 'time_ms' => $time * 1000]);
    }

    public function all(): array
    {
        return $this->log;
    }
    public function clear(): void
    {
        $this->log = [];
    }
}
