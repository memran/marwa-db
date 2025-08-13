<?php

declare(strict_types=1);

namespace Marwa\DB\Connection;

final class LoadBalancer
{
    /**
     * @param array<string> $pool
     */
    public function pick(array $pool): string
    {
        if (empty($pool)) {
            throw new \InvalidArgumentException('Replica pool cannot be empty.');
        }
        return $pool[array_rand($pool)];
    }
}
