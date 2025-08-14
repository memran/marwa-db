<?php

declare(strict_types=1);

namespace Marwa\DB\CLI;


abstract class AbstractMigration implements MigrationInterface
{

    public function getConnectionManager()
    {
        global $cm;
        return $cm;
    }
}
