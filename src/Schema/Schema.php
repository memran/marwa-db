<?php

declare(strict_types=1);

namespace Marwa\DB\Schema;

use Marwa\DB\Connection\ConnectionManager;

class Schema
{
    protected static ?Builder $factory = null;

    /**
     * Initialize schema builder with a connection
     */
    public static function init(?ConnectionManager $cm = null, ?string $connectionName = null): void
    {
        if (is_null($cm)) {
            $cm = $GLOBALS['cm'];
        }

        static::$factory = new Builder($cm, $connectionName ?? 'default');
    }

    /**
     * Create table.
     */
    public static function create(string $table, callable $callback): void
    {
        static::ensureInitialized();
        static::$factory->create($table, $callback);
    }

    /**
     * Drop table.
     */
    public static function drop(string $table): void
    {
        static::ensureInitialized();
        static::$factory->drop($table);
    }

    protected static function ensureInitialized(): void
    {
        if (!static::$factory) {
            throw new \RuntimeException("Schema builder not initialized. Call Schema::init() first.");
        }
    }
}
