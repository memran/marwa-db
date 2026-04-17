<?php

declare(strict_types=1);

namespace Marwa\DB\Facades;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder;
use Throwable;

final class DB
{
    private static ?ConnectionManager $cm = null;

    public static function setManager(ConnectionManager $cm): void
    {
        static::$cm = $cm;
    }

    public static function table(string $table, string $conn = 'default'): Builder
    {
        return (new Builder(static::$cm, $conn))->table($table);
    }

    public static function connection(?string $name = null): ConnectionManager
    {
        return static::$cm ?? throw new \RuntimeException('ConnectionManager not set');
    }

    public static function beginTransaction(string $conn = 'default'): void
    {
        static::$cm?->getPdo($conn)->beginTransaction();
    }

    public static function commit(string $conn = 'default'): void
    {
        static::$cm?->getPdo($conn)->commit();
    }

    public static function rollback(string $conn = 'default'): void
    {
        static::$cm?->getPdo($conn)->rollBack();
    }

    public static function transaction(callable $callback, string $conn = 'default'): mixed
    {
        static::beginTransaction($conn);
        try {
            $result = $callback();
            static::commit($conn);
            return $result;
        } catch (Throwable $e) {
            static::rollback($conn);
            throw $e;
        }
    }
}
