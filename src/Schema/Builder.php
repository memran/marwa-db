<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

use Marwa\DB\Connection\ConnectionManager;

final class Builder
{
    private static ?ConnectionManager $manager = null;
    private static string $connection = 'default';

    public static function useConnectionManager(ConnectionManager $cm, string $conn = 'default'): void
    {
        static::$manager = $cm;
        static::$connection = $conn;
    }

    public static function create(string $table, \Closure $callback): void
    {
        $bp = new Blueprint($table);
        $callback($bp);
        $sql = $bp->toCreateSQL();
        static::exec($sql);
    }

    public static function table(string $table, \Closure $callback): void
    {
        $bp = new Blueprint($table);
        $callback($bp);
        $sql = $bp->toAlterSQL();
        static::exec($sql);
    }

    public static function drop(string $table): void
    {
        static::exec("DROP TABLE IF EXISTS `{$table}`");
    }

    private static function exec(string $sql): void
    {
        $pdo = static::$manager?->getPdo(static::$connection);
        if (!$pdo) {
            throw new \RuntimeException('Connection manager not set for Schema\Builder.');
        }
        $pdo->exec($sql);
    }
}
