<?php

declare(strict_types=1);

namespace Marwa\DB\Facades;

use Marwa\DB\Connection\ConnectionManager;
use Marwa\DB\Query\Builder;

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
}
