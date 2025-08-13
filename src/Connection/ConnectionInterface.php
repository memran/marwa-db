<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

interface ConnectionInterface
{
    public function getPdo(string $name = 'default'): \PDO;
}
