<?php

declare(strict_types=1);

namespace Marwa\DB\Seeder;

use Marwa\DB\Facades\DB;

abstract class AbstractSeeder implements Seeder
{
    /**
     * Convenience entry point for seeders that want direct access to the DB facade.
     */
    protected function db(): DB
    {
        return new DB();
    }

    abstract public function run(): void;
}
